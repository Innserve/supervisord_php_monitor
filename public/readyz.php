<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/bootstrap.php";

header("Content-Type: application/json; charset=utf-8");

$server_names = array_keys($config["supervisor_servers"]);
$sample_server = $server_names[0] ?? NULL;

if( $sample_server === NULL ){
  http_response_code(503);
  echo json_encode([
    "ok" => FALSE,
    "status" => "not_ready",
    "error" => "No supervisor servers configured",
    "request_id" => app_request_id(),
  ], JSON_UNESCAPED_SLASHES);
  exit;
}

$started_at = microtime(TRUE);
$version = call_supervisor((string) $sample_server, "getSupervisorVersion");
$duration_ms = (int) round((microtime(TRUE) - $started_at) * 1000);

$ok = is_string($version) && preg_match('/^\d+(?:\.\d+){1,3}$/', trim($version)) === 1;
http_response_code($ok ? 200 : 503);

app_log('readiness.sample_check', [
  'server' => (string) $sample_server,
  'ok' => $ok,
  'duration_ms' => $duration_ms,
  'error' => $ok ? NULL : $version,
]);

echo json_encode([
  "ok" => $ok,
  "status" => $ok ? "ready" : "not_ready",
  "sample_server" => $sample_server,
  "duration_ms" => $duration_ms,
  "request_id" => app_request_id(),
  "error" => $ok ? NULL : $version,
], JSON_UNESCAPED_SLASHES);
