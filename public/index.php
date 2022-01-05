<?php
declare(strict_types=1);

require_once "../vendor/autoload.php";
require_once "../config/config.inc";
require_once "../lib/functions.inc";

// $config['debug'] = TRUE;

$config['version'] = '1.0.1';

$config['refresh'] = $config['refresh'] ?? 60;
$config['supervisor_servers'] = $config['supervisor_servers'] ?? [];

foreach($config['supervisor_servers'] as $name => $settings){
  $config['supervisor_servers'][$name]['list'] = do_the_request($name,'getAllProcessInfo');
  $config['supervisor_servers'][$name]['version'] = do_the_request($name,'getSupervisorVersion');
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
            <small class="text-muted">v<span id="gh_version_number"><?=$config['version']?></span></small>
          </h2>
          <span id="refresh_container" class='float-end pe-3'>
            Refresh in
            <span id="refresh_count" class="fw-bold fs-4"><?=$config['refresh']?></span>s
            <i id='start_stop_refresh' class="bi bi-pause-circle fs-4 fw-bold ms-2 text-primary cur-point"></i>
          </span>
        </div>
      </div>
      <div class="row">
        <div class="col">
          <nav class="nav">
            <a class="nav-link" target="_blank" href="https://github.com/Innserve/supervisord_php_monitor">
              <i class="bi bi-github"></i> Github
            </a>
            <a class="nav-link" target="_blank" href="https://github.com/Innserve/supervisord_php_monitor/issues">
              <i class="bi bi-exclamation-diamond"></i> Issues
            </a>
            <a class="nav-link" target="_blank" href="https://github.com/Innserve/supervisord_php_monitor/releases">
              <i class="bi bi-file-diff"></i> Releases <span id="version_badge"><i class="bi bi-question-circle"></i></span>
            </a>
          </nav>
        </div>
      </div>
    </div>

    <div class="container-fluid">
      <div class="row">
        <?php foreach($config['supervisor_servers'] as $name=>$details){ ?>
        <div class="col col-lg-6 col-xl-4 col-xxl-3">
          <table class="table table-bordered table-sm table-striped">
            <thead>
              <tr>
                <th colspan="4">
                  <a href="<?=$details['url'].":".$details['port']?>" class='link-secondary' target="_blank">
                    <?=$name?> (<?=str_replace("http://","",$details['url']);?>)
                  </a>
                  <?php
                  echo '&nbsp; v<i>'.$details['version'].'</i>';
                  if(!isset($details['list']['error'])){
                  ?>
                    <span class="server-btns float-end">
                      <a href="/control?action=stopAllProcesses&server=<?=$name?>" class="btn btn-xs btn-danger" type="button">
                        <i class="bi bi-stop-circle"></i> Stop all
                      </a>
                      <a href="/control?action=startAllProcesses&server=<?=$name?>" class="btn btn-xs btn-success" type="button">
                        <i class="bi bi-play-circle"></i> Start all
                      </a>
                      <a href="/control?action=restartAllProcesses&server=<?=$name?>" class="btn btn-xs btn-warning" type="button">
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
              foreach($details['list'] as $item){
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
                    list($pid,$uptime) = explode(",",$item['description']);
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
                  <td><?=$item_name;?></td>
                  <td style="text-align:center" class='<?=$class;?>'><?=$status;?></td>
                  <td style="text-align:right"><?=$uptime;?></td>
                  <td style="text-align:right">
                    <div class="actions">
                      <?php if($status=='RUNNING'){ ?>
                      <a href="/control?action=stopProcess&server=<?=$name?>&worker=<?=$item_name?>" class="btn btn-xs btn-danger" type="button">
                        <i class="bi bi-stop-circle"></i>
                      </a>
                      <a href="/control?action=restartProcess&server=<?=$name?>&worker=<?=$item_name?>" class="btn btn-xs btn-warning" type="button">
                        <i class="bi bi-arrow-clockwise"></i>
                      </a>
                      <?php } if( in_array( $status, ['STOPPED', 'EXITED', 'FATAL'] ) ){ ?>
                      <a href="/control?action=startProcess&server=<?=$name?>&worker=<?=$item_name?>" class="btn btn-xs btn-success" type="button">
                        <i class="bi bi-play-circle"></i>
                      </a>
                      <?php } ?>
                    </div>
                  </td>
                </tr>
                <?php
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
          <p>Powered by <a href="https://github.com/Innserve/supervisord_php_monitor" target="_blank">Supervisord Monitor</a></p>
        </div>
      </div>
    </div>

    <script type="text/javascript" src="jquery/jquery.min.js"></script>
    <script type="text/javascript" src="bootstrap/js/bootstrap.min.js"></script>
    <script type="text/javascript" src="js/super_monitor.js"></script>

  </body>

</html>
