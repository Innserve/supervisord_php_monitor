<?php
declare(strict_types=1);

use PhpXmlRpc\Encoder;
use PhpXmlRpc\Request;
use PhpXmlRpc\Client;

function do_the_request( string $server, string $method, array $params = [] ) :array|string {
  global $config;

  if( !isset($config['supervisor_servers'][$server]) ){
    die("Invalid server: ".$server);
  }

  $encoder = new Encoder();
  $client = new Client( $config['supervisor_servers'][$server]['url'].":".$config['supervisor_servers'][$server]['port']."/RPC2" );
  $request = new Request( 'supervisor.'.$method, $encoder->encode($params) );

  $response = $client->send($request, $config['timeout'], []);
  $value = $encoder->decode($response->value());

  return $value;
}
