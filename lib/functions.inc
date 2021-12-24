<?php
// use PhpXmlRpc\Value;
// use PhpXmlRpc\Request;
// use PhpXmlRpc\Client;
// use PhpXmlRpc\Helper;
// use PhpXmlRpc\PhpXmlRpc;

function do_the_request( string $server, string $method, array $params = [] ) {

  global $config, $servers;

  if( !isset($servers[$server]) ){
    die("Invalid server: ".$server);
  }

  // $client = new Client( $servers[$server]['url'] );
  // $request = new Request( 'supervisor.'.$method );
  // $client->setDebug(1);
  // $response = $client->send($request, $config['timeout'], []);

  $response = xmlrpc_decode(
                trim(
                  file_get_contents(
                    $servers[$server]['url'].":".$servers[$server]['port']."/RPC2",
                    FALSE,
                    stream_context_create([
                      'http' => [
                        'method'  => 'POST',
                        'header'  => ['Content-Type: text/xml'],
                        'content' => xmlrpc_encode_request('supervisor.'.$method, $params),
                        'timeout' => $config['timeout']
                      ]
                    ])
                  )
                )
              );

  return $response;
}
