<?php
use PhpXmlRpc\Encoder;
use PhpXmlRpc\Request;
use PhpXmlRpc\Client;


function do_the_request( string $server, string $method, array $params = [] ) {

  global $config, $servers;

  if( !isset($servers[$server]) ){
    die("Invalid server: ".$server);
  }

  $encoder = new Encoder();
  $client = new Client( $servers[$server]['url'].":".$servers[$server]['port']."/RPC2" );
  $request = new Request( 'supervisor.'.$method, $encoder->encode($params) );

  $response = $client->send($request, $config['timeout'], []);
  $value = $encoder->decode($response->value());

  return $value;
}
