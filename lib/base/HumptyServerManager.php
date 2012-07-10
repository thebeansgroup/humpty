<?php
/*
    Copyright 2008 studentbeans.com
    All rights reserved.

    This file is part of Humpty.

    Humpty is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Humpty is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Humpty.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * This class represents the overall server which manages all its child processes
 * 
 * @author al
 *
 */
class HumptyServerManager extends socketServer
{
  /**
   * @var object Reference to the configuration class
   */
  protected $configuration;

  /**
   * @var string An ID to identify the server
   */
  protected $id;

  /**
   * Constructor. Outputs info on which address and port the server is binding to.
   * 
   * @param <type> $client_class
   * @param <type> $bind_address
   * @param <type> $bind_port
   * @param <type> $domain
   * @param <type> $type
   * @param <type> $protocol
   */
  public function __construct($client_class, $bind_address = 0, $bind_port = 0, $domain = AF_INET, $type = SOCK_STREAM, $protocol = SOL_TCP)
  {
    echo "Creating server to listen on port $bind_port\n";

    // create a server id
    $this->id = md5($bind_address . $bind_port . rand());

    return parent::__construct($client_class, $bind_address, $bind_port, $domain, $type, $protocol);
  }

  /**
   * Saves a handle to the server configuration class 
   * 
   * @param HumptyConfiguration $config
   */
  public function setConfiguration(HumptyConfiguration $config)
  {
    $this->configuration = $config;
  }
  
  /**
   * Gives each thread access to the configuration object
   * 
   * (non-PHPdoc)
   * @see webroot/studentbeans/lib/externals/Humpty-orig/phpsocketdaemon/socketServer#accept()
   * @return object A new server thread to handle the request
   */
  public function accept()
  {
    $client = parent::accept();
    
    $client->setConfiguration($this->configuration);
    $client->setServerId($this->id);

    return $client;
  }
}