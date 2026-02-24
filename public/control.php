<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/bootstrap.php";

// $config['debug'] = TRUE;

$server = (string) ($_GET['server'] ?? '');
$worker = (string) ($_GET['worker'] ?? '');
$action = (string) ($_GET['action'] ?? '');

if( $server === '' || $action === '' ){
  http_response_code(400);
  die("Server and action required");
}

try {
  $server = validate_control_identifier($server, "Server");
  $action = validate_control_identifier($action, "Action");
}
catch( InvalidArgumentException $e ) {
  http_response_code(400);
  die($e->getMessage());
}

if( !is_valid_control_action($action) ){
  http_response_code(400);
  die("Unknown action");
}

if( action_requires_worker($action) && $worker === '' ){
  http_response_code(400);
  die("This action requires a worker");
}

if( action_requires_worker($action) ){
  try {
    $worker = validate_control_identifier($worker, "Worker");
  }
  catch( InvalidArgumentException $e ) {
    http_response_code(400);
    die($e->getMessage());
  }
}

try {
  dispatch_control_action($server, $action, $worker !== '' ? $worker : NULL);
}
catch( Throwable $e ) {
  app_log('control.dispatch_failed', [
    'server' => $server,
    'action' => $action,
    'worker' => $worker !== '' ? $worker : NULL,
    'error' => $e->getMessage(),
  ]);
  http_response_code(500);
  die("Control action failed");
}

header("Location: /", true, 303);
exit;
