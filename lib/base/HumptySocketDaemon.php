<?php
/**
 * Instantiates server and client instances
 */
class HumptySocketDaemon extends socketDaemon
{
  /**
   * Creates a new server and adds it to the daemon's internal array of servers
   *
   * @param string $server_class The name of the class to use to create the server
   * @param string $client_class The name of the class that will be allowed to connect to the server
   * @param $bind_address
   * @param $bind_port
   * @return object The new instantiated server class object
   */
  public function create_server($server_class, $client_class, $bind_address = 0, $bind_port = 0, $domain = AF_INET, $type = SOCK_STREAM, $protocol = SOL_TCP)
  {
    $server = new $server_class($client_class, $bind_address, $bind_port, $domain, $type, $protocol);

    if (!is_subclass_of($server, 'socketServer'))
    {
      throw new socketException("Invalid server class specified! Has to be a subclass of socketServer");
    }

    $this->servers[(int)$server->socket] = $server;

    return $server;
  }

  /**
   * Creates a new client
   *
   * @param string $client_class The name of the client class
   * @param $remote_address
   * @param $remote_port
   * @param $bind_address
   * @param $bind_port
   * @return unknown_type
   */
  public function create_client($client_class, $remote_address, $remote_port, $bind_address = 0, $bind_port = 0, $domain = AF_INET, $type = SOCK_STREAM, $protocol = SOL_TCP)
  {
    $client = new $client_class($bind_address, $bind_port, $domain, $type, $protocol);

    if (!is_subclass_of($client, 'socketClient'))
    {
      throw new socketException("Invalid client class specified! Has to be a subclass of socketClient");
    }

    $client->set_non_block();
    $client->connect($remote_address, $remote_port);
    $this->clients[(int)$client->socket] = $client;

    return $client;
  }
}