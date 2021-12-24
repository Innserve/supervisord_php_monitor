<?php

// Refresh Dashboard every x seconds. 0 to disable
$config['refresh'] = 60;

// Set timeout connecting to remote supervisord RPC2 interface
$config['timeout'] = 3;

// Supervisord servers
$config['supervisor_servers'] = array(
  'example0' => array(
    'url' => 'http://ipaddress',
    'port' => '9001'
   ),
  'example1' => array(
    'url' => 'http://hostname',
    'port' => '9001'
   ),
);
