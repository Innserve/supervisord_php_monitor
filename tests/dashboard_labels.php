<?php
declare(strict_types=1);

// Render the real template with synthetic data and no RPC, logging or lock-file I/O.
$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/supervisor-labels-' . bin2hex(random_bytes(8));
$files = [];
$directories = [$fixture, "$fixture/public", "$fixture/public/css", "$fixture/public/js", "$fixture/config"];

function check( bool $condition, string $message ) :void {
    if( !$condition ){
        throw new RuntimeException($message);
    }
}

try {
    foreach( $directories as $directory ){
        check(mkdir($directory, 0700), 'Could not create fixture directory');
    }

    $helpers = file_get_contents($root . '/lib/functions.inc');
    foreach( ['call_supervisor_batch', 'global_restart_lock_status'] as $function ){
        $helpers = str_replace('function ' . $function . '(', 'function unused_fixture_' . $function . '(', $helpers, $count);
        check($count === 1, 'Fixture I/O seam changed: ' . $function);
    }

    $contents = [
        '/helpers.php' => $helpers,
        '/public/index.php' => file_get_contents($root . '/public/index.php'),
        '/config/config.inc' => file_get_contents($root . '/config/config.inc'),
        '/public/css/super_monitor.css' => '',
        '/public/js/super_monitor.js' => '',
        '/bootstrap.php' => <<<'PHP'
<?php
require __DIR__ . '/helpers.php';
$fixture_servers = [
    'inntranet-prod-web-01' => ['url' => 'http://10.7.1.19', 'port' => '9001'],
    'inntranet-prod-web-02' => ['url' => 'http://10.7.1.20', 'port' => '9001'],
    'inntranet-prod-web-03' => ['url' => 'http://10.7.1.21', 'port' => '9001'],
    'inntranet-prod-web-04' => ['url' => 'http://10.7.1.22', 'port' => '9001'],
    'uat01' => ['url' => 'http://10.7.1.13', 'port' => '9001'],
    'uat02' => ['url' => 'http://10.7.1.14', 'port' => '9001'],
    'unknown<&"host' => ['url' => 'http://192.0.2.1', 'port' => '9001'],
    'tst01' => ['url' => 'http://192.0.2.2', 'port' => '9001'],
];
$_ENV['SERVERS'] = json_encode($fixture_servers, JSON_THROW_ON_ERROR);
require __DIR__ . '/config/config.inc';
$config['supervisor_servers'] = validate_supervisor_server_config($config['supervisor_servers']);
$config['version'] = 'fixture';
$config['refresh'] = 0;
$config['app_meta'] = ['repo' => '#repo', 'issues' => '#issues', 'releases' => '#releases'];
$fixture_calls = [];
function app_log( string $event, array $context = [] ) :void {}
function global_restart_lock_status() :array {
    return ['is_locked' => FALSE, 'locked_until' => 0, 'seconds_remaining' => 0];
}
function call_supervisor_batch( string $server, array $requests ) :array {
    global $fixture_calls;
    $fixture_calls[] = $server;
    if( getenv('SUPERVISOR_LABEL_FIXTURE_FAIL') === '1' ){
        return ['fixture connection unavailable', 'fixture'];
    }
    return [[
        [
            'name' => 'app_sync_data_updates', 'group' => 'app_sync_data_updates',
            'statename' => in_array($server, ['inntranet-prod-web-01', 'inntranet-prod-web-02'], TRUE) ? 'STOPPED' : 'RUNNING',
            'description' => 'pid 1, uptime 0:01:00',
        ],
        ['name' => 'worker', 'group' => 'group', 'statename' => 'FATAL', 'description' => 'fixture only'],
    ], 'fixture'];
}
register_shutdown_function(function() use (&$fixture_calls, &$config) {
    file_put_contents('php://stderr', json_encode([
        'calls' => $fixture_calls, 'servers' => $config['supervisor_servers'],
    ], JSON_THROW_ON_ERROR));
});
PHP,
    ];

    foreach( $contents as $relative => $content ){
        $files[] = $fixture . $relative;
        check(file_put_contents($fixture . $relative, $content) !== FALSE, 'Could not write fixture');
    }

    $names = [
        'inntranet-prod-web-01' => ['live01', '10.7.1.19'],
        'inntranet-prod-web-02' => ['live02', '10.7.1.20'],
        'inntranet-prod-web-03' => ['live03', '10.7.1.21'],
        'inntranet-prod-web-04' => ['live04', '10.7.1.22'],
        'uat01' => ['uat01', '10.7.1.13'],
        'uat02' => ['uat02', '10.7.1.14'],
        'unknown<&"host' => ['unknown<&"host', '192.0.2.1'],
    ];

    foreach( [FALSE, TRUE] as $failed ){
        $process = proc_open(
            [PHP_BINARY, '-d', 'allow_url_fopen=0', "$fixture/public/index.php"],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $fixture,
            ['SUPERVISOR_LABEL_FIXTURE_FAIL' => $failed ? '1' : '0']
        );
        check(is_resource($process), 'Could not run fixture');
        $html = stream_get_contents($pipes[1]);
        $metadata = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        check(proc_close($process) === 0, 'Fixture render failed: ' . $metadata);
        $metadata = json_decode($metadata, TRUE, 512, JSON_THROW_ON_ERROR);
        check($metadata['calls'] === array_keys($names), 'RPC keys/order or TST exclusion changed');
        check(!str_contains($html, 'unknown<&"host'), 'Unknown label was not escaped');

        $document = new DOMDocument();
        libxml_use_internal_errors(TRUE);
        $document->loadHTML($html, LIBXML_NONET);
        libxml_clear_errors();
        $xpath = new DOMXPath($document);

        foreach( $names as $key => [$name, $address] ){
            check($metadata['servers'][$key]['url'] === 'http://' . $address, 'RPC endpoint changed');
            check($metadata['servers'][$key]['port'] === '9001', 'RPC port changed');
            $text = $name . ' (' . $address . ')';
            if( $failed ){
                check(str_contains($document->textContent, 'Connection to ' . $text . ' failed:'), 'Missing failed-server label: ' . $name);
                continue;
            }
            $id = 'server-' . preg_replace('/[^a-z0-9]+/i', '-', $key);
            check($document->getElementById($id) !== NULL, 'Panel identity changed');
            $summary = $xpath->query('//*[@class="server-panel-summary"][@data-target="' . $id . '"]')->item(0);
            check($summary !== NULL && str_contains($summary->textContent, $text), 'Missing familiar heading: ' . $name);
            check($summary->getAttribute('aria-controls') === $id, 'Panel selector changed');
        }

        foreach( $xpath->query('//a[starts-with(@href, "/control?")]') as $link ){
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            check(isset($names[$query['server']]), 'Control routed through a display alias');
            if( isset($query['worker']) ){
                check(in_array($query['worker'], ['app_sync_data_updates', 'group:worker'], TRUE), 'Worker identifier changed');
            }
        }
        $open_links = $xpath->query('//a[@title="Open supervisor UI"]');
        check($open_links->length === ($failed ? 0 : count($names)), 'Supervisor link count changed');
        foreach( $open_links as $link ){
            $address = parse_url($link->getAttribute('href'), PHP_URL_HOST);
            $match = array_values(array_filter($names, fn($item) => $item[1] === $address));
            check(count($match) === 1 && $link->getAttribute('aria-label') === 'Open supervisor UI for ' . $match[0][0], 'Accessible name/endpoint mismatch');
        }
        check($failed || $xpath->query('//a[starts-with(@href, "/control?")]')->length > 0, 'Control links missing');
    }
    echo "Dashboard label, routing, selector and escaping checks passed.\n";
}
finally {
    foreach( array_reverse($files) as $file ){
        if( is_file($file) ){
            unlink($file);
        }
    }
    foreach( array_reverse($directories) as $directory ){
        if( is_dir($directory) ){
            rmdir($directory);
        }
    }
}
