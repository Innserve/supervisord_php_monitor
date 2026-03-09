<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/bootstrap.php";

// $config['debug'] = TRUE;

foreach($config['supervisor_servers'] as $name => $settings){
  $started_at = microtime(TRUE);
  $config['supervisor_servers'][$name]['list'] = call_supervisor($name,'getAllProcessInfo');
  $config['supervisor_servers'][$name]['version'] = call_supervisor($name,'getSupervisorVersion');
  $config['supervisor_servers'][$name]['fetch_duration_ms'] = (int) round((microtime(TRUE) - $started_at) * 1000);

  app_log('dashboard.server_fetch', [
    'server' => (string) $name,
    'duration_ms' => $config['supervisor_servers'][$name]['fetch_duration_ms'],
    'list_ok' => is_array($config['supervisor_servers'][$name]['list']),
    'version_response_type' => get_debug_type($config['supervisor_servers'][$name]['version']),
  ]);
}

$failed_servers = [];
$healthy_servers = [];

foreach( $config['supervisor_servers'] as $name => $details ){
  $list = $details['list'] ?? [];
  $version = $details['version'] ?? 'unknown';
  $server_url = ($details['url'] ?? '') . ':' . ($details['port'] ?? '');
  $server_label = str_replace("http://","",(string) ($details['url'] ?? ''));

  if( !is_array($list) ){
    $failed_servers[] = [
      'name' => (string) $name,
      'label' => $server_label,
      'error' => (string) $list,
    ];
    continue;
  }

  $dead_process_count = 0;

  foreach( $list as $item ){
    if( !is_array($item) ){
      continue;
    }

    if( is_dead_process_status((string) ($item['statename'] ?? '')) ){
      $dead_process_count++;
    }
  }

  $healthy_servers[] = [
    'collapse_id' => 'server-' . preg_replace('/[^a-z0-9]+/i', '-', (string) $name),
    'name' => (string) $name,
    'label' => $server_label,
    'server_url' => $server_url,
    'version' => (string) $version,
    'list' => $list,
    'dead_process_count' => $dead_process_count,
  ];
}
?>

<!doctype html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supervisord Monitoring</title>

    <!-- Bootstrap Stylesheet -->
    <link type="text/css" rel="stylesheet" href="bootstrap/css/bootstrap.min.css"/>
    <link type="text/css" rel="stylesheet" href="bootstrap-icons/bootstrap-icons.css"/>
    <link type="text/css" rel="stylesheet" href="css/super_monitor.css"/>

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>

  <body>

    <div class="container-fluid mt-3 mb-3">
      <div class="row">
        <div class="col">
          <h2 class='float-start'>
            Supervisor PHP Monitor
            <small class="text-muted">v<span id="gh_version_number"><?=h($config['version'])?></span></small>
          </h2>
          <span id="refresh_container" class='float-end pe-3'>
            Refresh in
            <span id="refresh_count" class="fw-bold fs-4"><?=h($config['refresh'])?></span>s
            <i id='start_stop_refresh' class="bi bi-pause-circle fs-4 fw-bold ms-2 text-primary cur-point"></i>
          </span>
        </div>
      </div>
      <div class="row">
        <div class="col">
          <nav class="nav">
            <a class="nav-link" target="_blank" rel="noopener noreferrer" href="<?=h($config['app_meta']['repo'])?>">
              <i class="bi bi-github"></i> Github
            </a>
            <a class="nav-link" target="_blank" rel="noopener noreferrer" href="<?=h($config['app_meta']['issues'])?>">
              <i class="bi bi-exclamation-diamond"></i> Issues
            </a>
            <a class="nav-link" target="_blank" rel="noopener noreferrer" href="<?=h($config['app_meta']['releases'])?>">
              <i class="bi bi-file-diff"></i> Releases
            </a>
          </nav>
        </div>
      </div>
    </div>

    <div class="container-fluid">
      <?php if( $failed_servers !== [] ){ ?>
      <div class="row">
        <div class="col-12">
          <div class="server-error-strip" role="status" aria-live="polite">
            <?php foreach( $failed_servers as $failed_server ){ ?>
            <span class="server-error-pill">
              <i class="bi bi-plug-fill"></i>
              Connection to <?=h($failed_server['name'])?> (<?=h($failed_server['label'])?>) failed:
              <?=h($failed_server['error'])?>
            </span>
            <?php } ?>
          </div>
        </div>
      </div>
      <?php } ?>

      <div class="row g-3">
          <?php foreach( $healthy_servers as $server ){ ?>
          <div class="col-12 col-lg-6 col-xl-4">
          <section class="server-panel">
            <div class="server-panel-header">
              <div class="server-panel-meta">
                <a href="<?=h($server['server_url'])?>" class="link-secondary fw-semibold" target="_blank" rel="noopener noreferrer">
                  <?=h($server['name'])?> (<?=h($server['label'])?>)
                </a>
                <span class="text-muted">v<?=h($server['version'])?></span>
                <span class="server-dead-badge<?= $server['dead_process_count'] === 0 ? ' server-dead-badge-ok' : '' ?>">
                  <i class="bi <?= $server['dead_process_count'] === 0 ? 'bi-check-circle-fill' : 'bi-x-octagon-fill' ?>"></i>
                  <?=h((string) $server['dead_process_count'])?> dead
                </span>
              </div>
              <div class="server-panel-actions">
                <?php if( $server['dead_process_count'] > 0 ){ ?>
                <a href="<?=h(control_url('restartDeadProcesses', $server['name']))?>" class="btn btn-xs btn-outline-danger" type="button">
                  <i class="bi bi-arrow-repeat"></i> Restart dead
                </a>
                <?php } ?>
                <a href="<?=h(control_url('stopAllProcesses', $server['name']))?>" class="btn btn-xs btn-danger" type="button">
                  <i class="bi bi-stop-circle"></i> Stop all
                </a>
                <a href="<?=h(control_url('startAllProcesses', $server['name']))?>" class="btn btn-xs btn-success" type="button">
                  <i class="bi bi-play-circle"></i> Start all
                </a>
                <a href="<?=h(control_url('restartAllProcesses', $server['name']))?>" class="btn btn-xs btn-warning" type="button">
                  <i class="bi bi-arrow-clockwise"></i> Restart all
                </a>
                <button class="btn btn-xs btn-secondary server-toggle" type="button" data-target="<?=h($server['collapse_id'])?>" aria-expanded="false">
                  <i class="bi bi-chevron-down"></i> Expand
                </button>
              </div>
            </div>

            <div id="<?=h($server['collapse_id'])?>" class="server-panel-body" hidden>
              <table class="table table-bordered table-sm table-striped mb-0">
                <thead>
                  <tr>
                    <th>Process</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Uptime</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach( $server['list'] as $item ){
                    if( !is_array($item) ){
                      continue;
                    }

                    $item_name = process_display_name($item);
                    $status = (string) ($item['statename'] ?? 'UNKNOWN');
                    $class = process_status_class($status);
                    $uptime = '&nbsp;';

                    if(
                      $status === 'RUNNING'
                      && isset($item['description'])
                      && str_contains((string) $item['description'], ',')
                    ){
                      [, $uptime] = explode(",", (string) $item['description'], 2);
                    }

                    $uptime = str_replace("uptime ","",$uptime);
                    $dead_note = dead_process_note($item);
                  ?>
                  <tr>
                    <td>
                      <div><?=h($item_name);?></div>
                      <?php if( $dead_note !== NULL ){ ?>
                      <div class="process-note"><?=h($dead_note)?></div>
                      <?php } ?>
                    </td>
                    <td class="text-center <?=h($class);?>"><?=h($status);?></td>
                    <td class="text-end"><?=h($uptime);?></td>
                    <td class="text-end">
                      <div class="actions">
                        <?php if( $status === 'RUNNING' ){ ?>
                        <a href="<?=h(control_url('stopProcess', $server['name'], $item_name))?>" class="btn btn-xs btn-danger" type="button">
                          <i class="bi bi-stop-circle"></i>
                        </a>
                        <a href="<?=h(control_url('restartProcess', $server['name'], $item_name))?>" class="btn btn-xs btn-warning" type="button">
                          <i class="bi bi-arrow-clockwise"></i>
                        </a>
                        <?php } ?>
                        <?php if( can_start_process_status($status) ){ ?>
                        <a href="<?=h(control_url('startProcess', $server['name'], $item_name))?>" class="btn btn-xs btn-success" type="button">
                          <i class="bi bi-play-circle"></i>
                        </a>
                        <?php } ?>
                      </div>
                    </td>
                  </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          </section>
          </div>
          <?php } ?>
      </div>
    </div>

    <div class="container-fluid">
      <div class="row">
        <div class="col text-center mt-3" id="footer">
          <p>Powered by <a href="<?=h($config['app_meta']['repo'])?>" target="_blank" rel="noopener noreferrer">Supervisord Monitor</a></p>
        </div>
      </div>
    </div>

    <script type="text/javascript" src="jquery/jquery.min.js"></script>
    <script type="text/javascript" src="bootstrap/js/bootstrap.min.js"></script>
    <script type="text/javascript" src="js/super_monitor.js"></script>

  </body>

</html>
