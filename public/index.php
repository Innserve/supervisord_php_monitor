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
      <div class="row">
        <?php foreach($config['supervisor_servers'] as $name=>$details){
          $list = $details['list'] ?? [];
          $version = $details['version'] ?? 'unknown';
          $server_url = ($details['url'] ?? '') . ':' . ($details['port'] ?? '');
          $server_label = str_replace("http://","",(string) ($details['url'] ?? ''));
          $server_has_error = !is_array($list);
        ?>
        <div class="col col-lg-6 col-xl-4 col-xxl-3">
          <table class="table table-bordered table-sm table-striped">
            <thead>
              <tr>
                <th colspan="4">
                  <a href="<?=h($server_url)?>" class='link-secondary' target="_blank" rel="noopener noreferrer">
                    <?=h($name)?> (<?=h($server_label);?>)
                  </a>
                  <?php
                  echo '&nbsp; v<i>' . h($version) . '</i>';
                  if(!$server_has_error){
                  ?>
                    <span class="server-btns float-end">
                      <a href="<?=h(control_url('stopAllProcesses', (string) $name))?>" class="btn btn-xs btn-danger" type="button">
                        <i class="bi bi-stop-circle"></i> Stop all
                      </a>
                      <a href="<?=h(control_url('startAllProcesses', (string) $name))?>" class="btn btn-xs btn-success" type="button">
                        <i class="bi bi-play-circle"></i> Start all
                      </a>
                      <a href="<?=h(control_url('restartAllProcesses', (string) $name))?>" class="btn btn-xs btn-warning" type="button">
                        <i class="bi bi-arrow-clockwise"></i> Restart all
                      </a>
                    </span>
                  <?php
                  }
                  ?>
                </th>
              </tr>
            </thead>
            <tbody>
              <?php
              if( $server_has_error ){
                ?>
                <tr>
                  <td colspan="4" class="table-danger">Failed to load process list: <?=h($list)?></td>
                </tr>
                <?php
              }
              else {
              foreach($list as $item){
                if( !is_array($item) ){
                  continue;
                }

                if($item['group'] != $item['name']){
                  $item_name = $item['group'].":".$item['name'];
                }
                else {
                  $item_name = $item['name'];
                }

                $pid = '&nbsp;';
                $uptime = '&nbsp;';
                $status = $item['statename'];

                switch ($status) {
                  case 'RUNNING':
                    $class = 'table-success';
                    if( isset($item['description']) && str_contains((string) $item['description'], ',') ){
                      list($pid,$uptime) = explode(",", (string) $item['description'], 2);
                    }
                    break;
                  case 'STARTING':
                    $class = 'table-warning';
                    break;
                  case 'FATAL':
                    $class = 'table-danger';
                    break;
                  case 'STOPPED':
                    $class = 'table-danger';
                    break;
                  default:
                    $class = 'table-secondary';
                    break;
                }

                $uptime = str_replace("uptime ","",$uptime);
                ?>
                <tr>
                  <td><?=h($item_name);?></td>
                  <td style="text-align:center" class='<?=h($class);?>'><?=h($status);?></td>
                  <td style="text-align:right"><?=h($uptime);?></td>
                  <td style="text-align:right">
                    <div class="actions">
                      <?php if($status=='RUNNING'){ ?>
                      <a href="<?=h(control_url('stopProcess', (string) $name, (string) $item_name))?>" class="btn btn-xs btn-danger" type="button">
                        <i class="bi bi-stop-circle"></i>
                      </a>
                      <a href="<?=h(control_url('restartProcess', (string) $name, (string) $item_name))?>" class="btn btn-xs btn-warning" type="button">
                        <i class="bi bi-arrow-clockwise"></i>
                      </a>
                      <?php } if( in_array( $status, ['STOPPED', 'EXITED', 'FATAL'] ) ){ ?>
                      <a href="<?=h(control_url('startProcess', (string) $name, (string) $item_name))?>" class="btn btn-xs btn-success" type="button">
                        <i class="bi bi-play-circle"></i>
                      </a>
                      <?php } ?>
                    </div>
                  </td>
                </tr>
                <?php
              }
              }
              ?>
            </tbody>
          </table>
        </div>
        <?php
        }
        ?>
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
