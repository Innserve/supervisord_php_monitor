<?php

// OPTIONAL: Refresh Dashboard every x seconds. 60s by default, 0 to disable
$config['refresh'] = 60;

// Set timeout connecting to remote supervisord RPC2 interface
$config['timeout'] = 3;

$servers_json = (string) ($_ENV['SERVERS'] ?? '');
$config['supervisor_servers'] = json_decode($servers_json, TRUE, 512, JSON_THROW_ON_ERROR);

// Exclude tst01 even while Chef discovery still includes its Supervisor recipe.
unset($config['supervisor_servers']['tst01']);
