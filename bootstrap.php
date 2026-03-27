<?php
declare(strict_types=1);

require_once __DIR__ . "/vendor/autoload.php";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required("SERVERS")->notEmpty();

require_once __DIR__ . "/config/config.inc";
require_once __DIR__ . "/lib/functions.inc";

$config["version"] = "1.0.4";
$config["refresh"] = isset($config["refresh"]) ? (int) $config["refresh"] : 60;
$config["timeout"] = isset($config["timeout"]) ? (int) $config["timeout"] : 3;
$config["supervisor_servers"] = validate_supervisor_server_config(
  $config["supervisor_servers"] ?? []
);
$config["app_meta"] = [
  "repo" => "https://github.com/Innserve/supervisord_php_monitor",
  "issues" => "https://github.com/Innserve/supervisord_php_monitor/issues",
  "releases" => "https://github.com/Innserve/supervisord_php_monitor/releases",
  "tags_api" => "https://api.github.com/repos/Innserve/supervisord_php_monitor/tags",
];

function app_request_id() :string {
  static $request_id = NULL;

  if( $request_id === NULL ){
    $request_id = bin2hex(random_bytes(8));
  }

  return $request_id;
}

function app_log( string $event, array $context = [] ) :void {
  $payload = [
    "ts" => gmdate("c"),
    "event" => $event,
    "request_id" => app_request_id(),
  ] + $context;

  error_log(json_encode($payload, JSON_UNESCAPED_SLASHES));
}

header("X-Request-Id: " . app_request_id());
