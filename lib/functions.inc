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

  $this_server = $config['supervisor_servers'][$server];

  $encoder = new Encoder();
  $client = new Client( $this_server['url'].":".$this_server['port']."/RPC2" );

  if( $config['debug'] ?? FALSE ) {
    $client->setDebug(1);
  }

  if(isset($this_server['username']) && isset($this_server['password'])){
    $client->setCredentials($this_server['username'], $this_server['password']);
  }

  $request = new Request( 'supervisor.'.$method, $encoder->encode($params) );

  $response = $client->send($request, $config['timeout'], []);

  if( !$response->faultCode() ){
    $value = $encoder->decode($response->value());
  } else {
    $value = $response->faultString();
  }

  if( is_bool($value) ){
    $value = $value ? "True" : "False";
  }

  return $value;

}
