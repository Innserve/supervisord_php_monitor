<?php
declare(strict_types=1);

require_once "../vendor/autoload.php";
require_once "../config/config.inc";
require_once "../lib/functions.inc";

$config['refresh'] = $config['refresh'] ?? 60;
$config['supervisor_servers'] = $config['supervisor_servers'] ?? [];

foreach($config['supervisor_servers'] as $name => $settings){
  $config['supervisor_servers'][$name]['list'] = do_the_request($name,'getAllProcessInfo');
  $config['supervisor_servers'][$name]['version'] = do_the_request($name,'getSupervisorVersion');
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Supervisord Monitoring</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link type="text/css" rel="stylesheet" href="css/bootstrap.min.css"/>
    <link type="text/css" rel="stylesheet" href="css/bootstrap-responsive.min.css"/>
    <link type="text/css" rel="stylesheet" href="css/custom.css"/>
    <script type="text/javascript" src="js/jquery-1.10.1.min.js"></script>
    <script type="text/javascript" src="js/bootstrap.min.js"></script>
    <meta http-equiv='refresh' content='<?=$config['refresh']?>'>
  </head>
  <body>
    <div class="container">
      <div class="row">
        <?php foreach($config['supervisor_servers'] as $name=>$details){ ?>
        <div class="span6">
          <table class="table table-bordered table-condensed table-striped">
            <tr>
              <th colspan="4">
                <a href="<?=$details['url'].":".$details['port']?>"><?=$name?></a>
                <?php
                echo '&nbsp;<i>'.str_replace("http://","",$details['url']).'</i>';
                echo '&nbsp;- v<i>'.$details['version'].'</i>';
                if(!isset($details['list']['error'])){
                ?>
                <span class="server-btns pull-right">
                  <a href="<?php echo ('/control/stopall/'.$name); ?>" class="btn btn-mini btn-inverse" type="button"><i class="icon-stop icon-white"></i> Stop all</a>
                  <a href="<?php echo ('/control/startall/'.$name); ?>" class="btn btn-mini btn-success" type="button"><i class="icon-play icon-white"></i> Start all</a>
                  <a href="<?php echo ('/control/restartall/'.$name); ?>" class="btn btn-mini btn-primary" type="button"><i class="icon icon-refresh icon-white"></i> Restart all</a>
                </span>
                <?php
                }
                ?>
              </th>
            </tr>
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
              if($status=='RUNNING'){
                $class = 'success';
                list($pid,$uptime) = explode(",",$item['description']);
              }
              elseif($status=='STARTING') {
                $class = 'info';
              }
              elseif($status=='FATAL') {
                $class = 'important';
              }
              elseif($status=='STOPPED') {
                $class = 'inverse';
              }
              else {
                $class = 'error';
              }

              $uptime = str_replace("uptime ","",$uptime);
              ?>
              <tr>
                <td><?= $item_name; ?></td>
                <td width="10"><span class="label label-<?php echo $class;?>"><?php echo $status;?></span></td>
                <td width="80" style="text-align:right"><?php echo $uptime;?></td>
                <td style="width:1%">
                  <div class="actions">
                    <?php if($status=='RUNNING'){ ?>
                    <a href="<?php echo ('/control/stop/'.$name.'/'.$item_name);?>" class="btn btn-mini btn-inverse" type="button"><i class="icon-stop icon-white"></i></a>
                    <a href="<?php echo ('/control/restart/'.$name.'/'.$item_name);?>" class="btn btn-mini btn-inverse" type="button"><i class="icon-refresh icon-white"></i></a>
                    <?php } if($status=='STOPPED' || $status == 'EXITED' || $status=='FATAL'){ ?>
                    <a href="<?php echo ('/control/start/'.$name.'/'.$item_name);?>" class="btn btn-mini btn-success" type="button"><i class="icon-play icon-white"></i></a>
                    <?php } ?>
                  </div>
                </td>
              </tr>
              <?php
            }

            ?>
          </table>
        </div>
        <?php
        }
        ?>
      </div>
    </div>

    <div class="footer">
      <p>Powered by <a href="https://github.com/Innserve/supervisord_php_monitor" target="_blank">Supervisord Monitor</a></p>
    </div>

  </body>
</html>
