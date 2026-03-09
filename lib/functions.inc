<?php
declare(strict_types=1);

use PhpXmlRpc\Encoder;
use PhpXmlRpc\Request;
use PhpXmlRpc\Client;

function h( mixed $value ) :string {
  return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function control_url( string $action, string $server, ?string $worker = NULL ) :string {
  $params = [
    'action' => $action,
    'server' => $server,
  ];

  if( $worker !== NULL ){
    $params['worker'] = $worker;
  }

  return '/control?' . http_build_query($params);
}

function validate_supervisor_server_config( mixed $servers ) :array {
  if( !is_array($servers) ){
    throw new RuntimeException("SERVERS must decode to an object/map of supervisor server definitions");
  }

  foreach( $servers as $name => $server ){
    if( !is_string($name) || $name === '' ){
      throw new RuntimeException("Supervisor server names must be non-empty strings");
    }

    if( !is_array($server) ){
      throw new RuntimeException("Supervisor server '{$name}' must be an object");
    }

    foreach( ['url', 'port'] as $required_key ){
      if( !isset($server[$required_key]) || trim((string) $server[$required_key]) === '' ){
        throw new RuntimeException("Supervisor server '{$name}' is missing required key '{$required_key}'");
      }
    }

    $has_username = isset($server['username']) && trim((string) $server['username']) !== '';
    $has_password = isset($server['password']) && trim((string) $server['password']) !== '';

    if( $has_username xor $has_password ){
      throw new RuntimeException("Supervisor server '{$name}' must set both username and password when using auth");
    }
  }

  return $servers;
}

function call_supervisor( string $server, string $method, array $params = [] ) :array|string {
  global $config;

  if( !isset($config['supervisor_servers'][$server]) ){
    die("Invalid server: ".$server);
  }

  $this_server = $config['supervisor_servers'][$server];

  $encoder = new Encoder();
  $client = new Client( $this_server['url'].":".$this_server['port']."/RPC2" );

  if( $config['debug'] ?? FALSE ) {
    $client->setDebug(1);
  }

  if(isset($this_server['username']) && isset($this_server['password'])){
    $client->setCredentials($this_server['username'], $this_server['password']);
  }

  $request = new Request( 'supervisor.'.$method, $encoder->encode($params) );

  try {
    $response = $client->send($request, $config['timeout'], []);
  }
  catch( Throwable $e ) {
    return 'RPC request failed: ' . $e->getMessage();
  }

  if( !$response->faultCode() ){
    $value = $encoder->decode($response->value());
  } else {
    $value = $response->faultString();
  }

  if( is_bool($value) ){
    $value = $value ? "True" : "False";
  }

  return $value;

}

function do_the_request( string $server, string $method, array $params = [] ) :array|string {
  return call_supervisor($server, $method, $params);
}

/**
 * Build the supervisor worker identifier used in control actions.
 *
 * @param array $item Supervisor process payload from getAllProcessInfo.
 * @return string
 */
function process_display_name( array $item ) :string {
  $group = (string) ($item['group'] ?? '');
  $name = (string) ($item['name'] ?? '');

  if( $group !== '' && $group !== $name ){
    return $group . ":" . $name;
  }

  return $name;
}

/**
 * Identify terminal process states that are safe to auto-restart.
 *
 * @param string $status Supervisor statename value.
 * @return bool
 */
function is_dead_process_status( string $status ) :bool {
  return in_array($status, ['BACKOFF', 'EXITED', 'FATAL', 'UNKNOWN'], TRUE);
}

/**
 * Identify process states that can be started from the UI.
 *
 * @param string $status Supervisor statename value.
 * @return bool
 */
function can_start_process_status( string $status ) :bool {
  return in_array($status, ['STOPPED', 'EXITED', 'FATAL', 'BACKOFF', 'UNKNOWN'], TRUE);
}

/**
 * Map supervisor status names to table classes.
 *
 * @param string $status Supervisor statename value.
 * @return string
 */
function process_status_class( string $status ) :string {
  switch( $status ){
    case 'RUNNING':
      return 'table-success';
    case 'STARTING':
    case 'STOPPING':
      return 'table-warning';
    case 'BACKOFF':
    case 'FATAL':
    case 'STOPPED':
    case 'EXITED':
    case 'UNKNOWN':
      return 'table-danger';
    default:
      return 'table-secondary';
  }
}

/**
 * Expose the supervisor-provided reason string for dead processes.
 *
 * @param array $item Supervisor process payload from getAllProcessInfo.
 * @return string|null
 */
function dead_process_note( array $item ) :?string {
  $status = (string) ($item['statename'] ?? '');

  if( !is_dead_process_status($status) ){
    return NULL;
  }

  $spawn_error = trim((string) ($item['spawnerr'] ?? ''));
  if( $spawn_error !== '' ){
    return $spawn_error;
  }

  $description = trim((string) ($item['description'] ?? ''));
  if( $description !== '' ){
    return $description;
  }

  return NULL;
}

/**
 * Fetch only restartable dead processes for a supervisor host.
 *
 * @param string $server Configured supervisor server key.
 * @return array
 */
function dead_processes_for_server( string $server ) :array {
  $list = call_supervisor($server, 'getAllProcessInfo');

  if( !is_array($list) ){
    throw new RuntimeException(is_string($list) ? $list : 'Failed to load process list');
  }

  $dead_processes = [];

  foreach( $list as $item ){
    if( !is_array($item) ){
      continue;
    }

    if( is_dead_process_status((string) ($item['statename'] ?? '')) ){
      $dead_processes[] = $item;
    }
  }

  return $dead_processes;
}

function supervisor_action_map() :array {
  return [
    'startProcess' => [
      'scope' => 'worker',
      'rpc_sequence' => [['method' => 'startProcess']],
    ],
    'stopProcess' => [
      'scope' => 'worker',
      'rpc_sequence' => [['method' => 'stopProcess']],
    ],
    'restartProcess' => [
      'scope' => 'worker',
      'rpc_sequence' => [
        ['method' => 'stopProcess'],
        ['sleep_seconds' => 2],
        ['method' => 'startProcess'],
      ],
    ],
    'startAllProcesses' => [
      'scope' => 'server',
      'rpc_sequence' => [['method' => 'startAllProcesses']],
    ],
    'stopAllProcesses' => [
      'scope' => 'server',
      'rpc_sequence' => [['method' => 'stopAllProcesses']],
    ],
    'restartAllProcesses' => [
      'scope' => 'server',
      'rpc_sequence' => [
        ['method' => 'stopAllProcesses'],
        ['sleep_seconds' => 2],
        ['method' => 'startAllProcesses'],
      ],
    ],
    'restartDeadProcesses' => [
      'scope' => 'server',
      'rpc_sequence' => [],
    ],
  ];
}

function is_valid_control_action( string $action ) :bool {
  return isset(supervisor_action_map()[$action]);
}

function action_requires_worker( string $action ) :bool {
  $map = supervisor_action_map();
  return isset($map[$action]) && $map[$action]['scope'] === 'worker';
}

function validate_control_identifier( string $value, string $label ) :string {
  $value = trim($value);

  if( $value === '' ){
    throw new InvalidArgumentException($label . " is required");
  }

  if( preg_match('/[\r\n]/', $value) ){
    throw new InvalidArgumentException($label . " is invalid");
  }

  return $value;
}

function dispatch_control_action( string $server, string $action, ?string $worker = NULL ) :void {
  $map = supervisor_action_map();

  if( !isset($map[$action]) ){
    throw new InvalidArgumentException("Unknown action");
  }

  if( $action === 'restartDeadProcesses' ){
    $dead_processes = dead_processes_for_server($server);

    app_log('control.restart_dead_scan', [
      'server' => $server,
      'dead_process_count' => count($dead_processes),
    ]);

    foreach( $dead_processes as $item ){
      $dead_worker = process_display_name($item);
      $started_at = microtime(TRUE);
      $result = call_supervisor($server, 'startProcess', [$dead_worker, 1]);
      $duration_ms = (int) round((microtime(TRUE) - $started_at) * 1000);
      $ok = is_array($result) || in_array($result, ['True', 'False'], TRUE);

      app_log('control.rpc_call', [
        'server' => $server,
        'action' => $action,
        'rpc_method' => 'startProcess',
        'worker' => $dead_worker,
        'duration_ms' => $duration_ms,
        'ok' => $ok,
        'result_type' => get_debug_type($result),
        'error' => $ok ? NULL : (is_string($result) ? $result : NULL),
      ]);
    }

    return;
  }

  foreach( $map[$action]['rpc_sequence'] as $step ){
    if( isset($step['sleep_seconds']) ){
      sleep((int) $step['sleep_seconds']);
      continue;
    }

    $method = (string) $step['method'];
    $params = ($map[$action]['scope'] === 'worker')
      ? [(string) $worker, 1]
      : [1];

    $started_at = microtime(TRUE);
    $result = call_supervisor($server, $method, $params);
    $duration_ms = (int) round((microtime(TRUE) - $started_at) * 1000);
    $ok = is_array($result) || in_array($result, ['True', 'False'], TRUE);

    app_log('control.rpc_call', [
      'server' => $server,
      'action' => $action,
      'rpc_method' => $method,
      'worker' => $worker,
      'duration_ms' => $duration_ms,
      'ok' => $ok,
      'result_type' => get_debug_type($result),
      'error' => $ok ? NULL : (is_string($result) ? $result : NULL),
    ]);
  }
}
