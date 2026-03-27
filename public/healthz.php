<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/bootstrap.php";

header("Content-Type: application/json; charset=utf-8");

echo json_encode([
  "ok" => TRUE,
  "status" => "healthy",
  "request_id" => app_request_id(),
  "configured_servers" => count($config["supervisor_servers"]),
  "version" => $config["version"],
], JSON_UNESCAPED_SLASHES);
