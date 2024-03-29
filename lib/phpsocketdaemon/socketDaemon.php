<?php
/*
 phpSocketDaemon 1.0
 Copyright (C) 2006 Chris Chabot <chabotc@xs4all.nl>
 See http://www.chabotc.nl/ for more information

 This library is free software; you can redistribute it and/or
 modify it under the terms of the GNU Lesser General Public
 License as published by the Free Software Foundation; either
 version 2.1 of the License, or (at your option) any later version.

 This library is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 Lesser General Public License for more details.

 You should have received a copy of the GNU Lesser General Public
 License along with this library; if not, write to the Free Software
 Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * This class manages an array of clients and an array of servers. 
 */
class socketDaemon {
  public $servers = array();
  public $clients = array();
  private $block = true;
  private $timerInterval = 1;

  /**
   * Creates a new server and adds it to the daemon's internal array of servers
   *
   * @param string $server_class The name of the class to use to create the server
   * @param string $client_class The name of the class that will be allowed to connect to the server
   * @param $bind_address
   * @param $bind_port
   * @return object The new instantiated server class object
   */
  public function create_server($server_class, $client_class, $bind_address = 0, $bind_port = 0)
  {
    $server = new $server_class($client_class, $bind_address, $bind_port);

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
  public function create_client($client_class, $remote_address, $remote_port, $bind_address = 0, $bind_port = 0)
  {
    $client = new $client_class($bind_address, $bind_port);

    if (!is_subclass_of($client, 'socketClient'))
    {
      throw new socketException("Invalid client class specified! Has to be a subclass of socketClient");
    }

    $client->set_non_block();
    $client->connect($remote_address, $remote_port);
    $this->clients[(int)$client->socket] = $client;

    return $client;
  }

  private function create_read_set()
  {
    $ret = array();

    foreach ($this->clients as $socket)
    {
      $ret[] = $socket->socket;
    }

    foreach ($this->servers as $socket)
    {
      $ret[] = $socket->socket;
    }

    return $ret;
  }

  private function create_write_set()
  {
    $ret = array();

    foreach ($this->clients as $socket)
    {
      if (!empty($socket->write_buffer) || $socket->connecting)
      {
        $ret[] = $socket->socket;
      }
    }

    foreach ($this->servers as $socket)
    {
      if (!empty($socket->write_buffer))
      {
        $ret[] = $socket->socket;
      }
    }

    return $ret;
  }

  private function create_exception_set()
  {
    $ret = array();
    foreach ($this->clients as $socket)
    {
      $ret[] = $socket->socket;
    }

    foreach ($this->servers as $socket)
    {
      $ret[] = $socket->socket;
    }

    return $ret;
  }

  private function clean_sockets()
  {
    foreach ($this->clients as $socket)
    {
      if ($socket->disconnected || !is_resource($socket->socket))
      {
        if (isset($this->clients[(int)$socket->socket]))
        {
          unset($this->clients[(int)$socket->socket]);
        }
      }
    }
  }

  private function get_class($socket)
  {
    if (isset($this->clients[(int)$socket]))
    {
      return $this->clients[(int)$socket];
    }
    elseif (isset($this->servers[(int)$socket]))
    {
      return $this->servers[(int)$socket];
    }
    else
    {
      throw (new socketException("Could not locate socket class for $socket"));
    }
  }

  public function set_block()
  {
    $this->block = true;
  }

  public function set_unblock()
  {
    $this->block = false;
  }

  public function process()
  {
    // if socketClient is in write set, and $socket->connecting === true, set connecting to false and call on_connect
    $read_set      = $this->create_read_set();
    $write_set     = $this->create_write_set();
    $exception_set = $this->create_exception_set();
    $event_time    = microtime(true);

    while ($this->block && (($events = @socket_select($read_set, $write_set, $exception_set, 2)) !== false))
    {
      if ($events > 0)
      {
        foreach ($read_set as $socket)
        {
          $socket = $this->get_class($socket);

          if (is_subclass_of($socket,'socketServer'))
          {
            $client = $socket->accept();
            $this->clients[(int)$client->socket] = $client;
          }
          elseif (is_subclass_of($socket, 'socketClient'))
          {
            // regular on_read event
            $socket->read();
          }
        }

        foreach ($write_set as $socket)
        {
          $socket = $this->get_class($socket);
          
          if (is_subclass_of($socket, 'socketClient'))
          {
            if ($socket->connecting === true)
            {
              $socket->on_connect();
              $socket->connecting = false;
            }
            
            $socket->do_write();
          }
        }

        foreach ($exception_set as $socket)
        {
          $socket = $this->get_class($socket);

          if (is_subclass_of($socket, 'socketClient'))
          {
            $socket->on_disconnect();

            if (isset($this->clients[(int)$socket->socket]))
            {
              unset($this->clients[(int)$socket->socket]);
            }
          }
        }
      }
      if (microtime(true) - $event_time > $this->timerInterval)
      {
        // only do this if more then 0.03 seconds passed, else we'd keep looping this for every bit received
        foreach ($this->clients as $socket)
        {
          $socket->on_timer();
        }

        $event_time = microtime(true);
      }

      $this->clean_sockets();
      $read_set      = $this->create_read_set();
      $write_set     = $this->create_write_set();
      $exception_set = $this->create_exception_set();
    }
  }

  /**
   * Set the number of seconds between calling the on_timer methods of sockets
   *
   * @param float $seconds The number of seconds
   */
  public function setTimerInterval($seconds)
  {
    $this->timerInterval = $seconds;
  }
}
