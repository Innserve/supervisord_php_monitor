<?php

// OPTIONAL: Refresh Dashboard every x seconds. 60s by default, 0 to disable
$config['refresh'] = 60;

// Set timeout connecting to remote supervisord RPC2 interface
$config['timeout'] = 3;

$config['supervisor_servers'] = json_decode( $_ENV['SERVERS'], TRUE );
