<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/bootstrap.php";

// $config['debug'] = TRUE;

$request_method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$request = $request_method === 'POST' ? $_POST : $_GET;
$server = (string) ($request['server'] ?? '');
$worker = (string) ($request['worker'] ?? '');
$action = (string) ($request['action'] ?? '');

if( $action === '' ){
  http_response_code(400);
  die("Action required");
}

try {
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

if( $action === 'restartAllServers' && $request_method !== 'POST' ){
  http_response_code(405);
  header('Allow: POST');
  die("This action must use POST");
}

if( action_requires_server($action) && $server === '' ){
  http_response_code(400);
  die("Server required");
}

if( action_requires_server($action) ){
  try {
    $server = validate_control_identifier($server, "Server");
  }
  catch( InvalidArgumentException $e ) {
    http_response_code(400);
    die($e->getMessage());
  }
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
  if( $action === 'restartAllServers' ){
    $lock_status = claim_global_restart_lock();

    if( !$lock_status['allowed'] ){
      app_log('control.global_restart_denied', [
        'locked_until' => gmdate('c', $lock_status['locked_until']),
        'seconds_remaining' => $lock_status['seconds_remaining'],
      ]);

      header("Location: /?control_notice=restart-all-servers-locked", true, 303);
      exit;
    }
  }

  dispatch_control_action($server, $action, $worker !== '' ? $worker : NULL);
}
catch( Throwable $e ) {
  app_log('control.dispatch_failed', [
    'server' => $server !== '' ? $server : NULL,
    'action' => $action,
    'worker' => $worker !== '' ? $worker : NULL,
    'error' => $e->getMessage(),
  ]);
  http_response_code(500);
  die("Control action failed");
}

$redirect_url = '/';

if( $action === 'restartAllServers' ){
  $redirect_url = '/?control_notice=restart-all-servers-started';
}

header("Location: " . $redirect_url, true, 303);
exit;
