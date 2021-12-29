<?php
declare(strict_types=1);

require_once "../vendor/autoload.php";
require_once "../config/config.inc";
require_once "../lib/functions.inc";

// $config['debug'] = TRUE;

$server = $_GET['server'] ?? '';
$worker = $_GET['worker'] ?? '';
$action = $_GET['action'] ?? '';

if( $server == '' || $action == '' ){
  die("Server and action required");
}

if( in_array($action, ['startProcess', 'stopProcess', 'restartProcess']) && $worker == '' ){
  die("This action requires a worker");
}

switch ($action) {
  case 'startProcess':
  case 'stopProcess':
    single_worker_action( $server, $worker, $action );
    break;
  case 'startAllProcesses':
  case 'stopAllProcesses':
    full_server_action( $server, $action );
    break;
  case 'restartProcess':
    single_worker_action( $server, $worker, 'stopProcess' );
    sleep(2);
    single_worker_action( $server, $worker, 'startProcess' );
    break;
  case 'restartAllProcesses':
    full_server_action( $server, 'stopAllProcesses' );
    sleep(2);
    full_server_action( $server, 'startAllProcesses' );
    break;
  default:
    die("Unknown action");
}

header("Location: /");

function single_worker_action( string $server, string $worker, string $action ) :void {
  echo $server . " / " . $worker . " / " . $action . "<br />";
  $response = do_the_request( $server, $action, [$worker, 1] ); // TRUE tells supervisor to wait before responding http://supervisord.org/api.html
  echo "Done - " . $response . "<br />";
}

function full_server_action( string $server, string $action ) :void {
  echo $server . " / " . $action . "<br />";
  $response = do_the_request( $server, $action, [1] );          // TRUE tells supervisor to wait before responding http://supervisord.org/api.html
  echo "Done - " . $response . "<br />";
}
