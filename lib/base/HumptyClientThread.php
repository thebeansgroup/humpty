<?php
/*
 Copyright 2009 studentbeans.com
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
 * Class that sends requests to the humpty server
 *
 * @author al
 *
 */
class HumptyClientThread extends socketClient
{
  /**
   * Various constants that indicate the state of play with the server
   */
  const STATUS_WAITING_FOR_SERVER = 'waiting for server';
  const STATUS_SERVER_NOT_RESPONDING = 'server not responding';
  const STATUS_SERVER_NOT_CONFIGURED = 'server not configured for task';
  const STATUS_SERVER_COMPLETED = 'task complete';
  const STATUS_SERVER_ABORTED = 'server aborted';

  /**
   * @var HumptyLogger An instance of a HumptyLogger that can be used to report messages
   */
  protected $logger;
  
  /**
   * @var HumptyMessage The class that will handle encoding and decoding of messages
   */
  protected $messager;
  
  /**
   * @var object Reference to the configuration class
   */
  protected $configuration;

  /**
   * @var ClientManager The manager controlling this thread
   */
  protected $manager;

  /**
   * @var int A status code that describes what the situation is with the server
   */
  protected $status = self::STATUS_WAITING_FOR_SERVER;

  /**
   * @var int The number of times our on_timer method can be called without receiving a
   * response before the server is deemed to be unresponsive
   */
  protected $receiveCyclesLeft = 3;

  /**
   * Constructor. Sets up references to the messager and logger classes
   */
  public function __construct($bind_address = 0, $bind_port = 0, $domain = AF_INET, $type = SOCK_STREAM, $protocol = SOL_TCP)
  {
    $client = parent::__construct($bind_address, $bind_port, $domain, $type, $protocol);

    $this->messager = new HumptyMessager();
    $this->logger = new HumptyLoggerTerminal(__CLASS__);

    return $client;
  }
  
  /**
   * Set timeout for responses
   * 
   * (non-PHPdoc)
   * @see webroot/studentbeans/lib/externals/Humpty-orig/phpsocketdaemon/socketClient#connect()
   *
   * @todo allow the remote address & port to be configured in the config file
   */
  public function connect($remote_address, $remote_port)
  {
    parent::connect($remote_address, $remote_port);
    $this->set_receive_timeout(3, 0);
  }
  
  /**
   * Returns the current status of the communication
   * 
   * @return int
   */
  public function getStatus()
  {
    return $this->status;
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
   * Associates a ClientManager with this thread. The manager will be invoked in the
   * on_timer method
   *
   * @param ClientManager $manager The class managing this thread
   */
  public function setManager(ClientManager $manager)
  {
    $this->manager = $manager;
  }
  
  /**
   * Sends a message to listening servers
   * 
   * @param string $project The name of a project to run an action against
   * @param string $action The name of an action to run
   * @param array $parameters An array of extra parameters to send
   */
  public function sendMessage($project, $action, array $parameters)
  {
    $packet = $this->messager->getNewPacketInstance($this->messager->getTypeOutbound());
    
    $packet->setProject($project);
    $packet->setAction($action);
    
    foreach ($parameters as $parameter)
    {
      if (($pos = strpos($parameter, ':')) !== false)
      {
        $packet->addParameter(substr($parameter, 0, $pos), substr($parameter, ($pos + 1)));
      }
      else
      {
        throw new InvalidHeaderException("Parameters must contain a colon (:) to separate keys from values");
      }
    }
    
    // send the message
    $this->write($packet->encode());
  }
  
  /**
   * Makes the script block until a response is received after
   * sending a request to the server
   */
  public function on_write()
  {
    $this->logger->log("Writing to server at {$this->remote_address}:{$this->remote_port}");
  }

  /**
   * Handles incoming data which is in $this->read_buffer,
   * looks for the message end marker, then passes the complete instruction to 'handle_response'
   */
  public function on_read()
  {
    $this->logger->log("Reading response from server at {$this->remote_address}:{$this->remote_port}");
    
    // if we can extract a response from the buffer, handle it.
    if ($this->messager->extractMessage($this->read_buffer))
    {
      // handle the response
      $this->handleResponse();
    }
  }
  
  /**
   * Called when the client connects to a server
   * 
   * (non-PHPdoc)
   * @see webroot/studentbeans/lib/externals/Humpty/lib/phpsocketdaemon/socketClient#on_connect()
   */
  public function on_connect() 
  {
    $this->logger->log("Connecting to server at {$this->remote_address}:{$this->remote_port}");
  }
  
  /**
   * Called when the client disconnects from a server
   * 
   * (non-PHPdoc)
   * @see webroot/studentbeans/lib/externals/Humpty/lib/phpsocketdaemon/socketClient#on_disconnect()
   */
  public function on_disconnect() 
  {
    $this->logger->log("Disconnected from server at {$this->remote_address}:{$this->remote_port}");
  }
  
  /**
   * Displays received messages (contained in the messager class)
   */
  protected function handleResponse()
  {
    // print out all queued inbound packets
    $responses = $this->messager->getQueuedPackets($this->messager->getTypeInbound());
    
    for ($i=0; $i<count($responses); $i++)
    {
      $receivedHeaders = $responses[$i]->getHeaders();

      // if the server hasn't implemented that feature, set our status
      if (in_array(HumptyMessagePacket::NOT_IMPLEMENTED_HEADER, array_keys($receivedHeaders)))
      {
        $this->status = self::STATUS_SERVER_NOT_CONFIGURED;
      }
      // if the server aborted, set our status
      elseif (in_array(HumptyMessagePacket::ABORTED_HEADER, array_keys($receivedHeaders)))
      {
        $this->status = self::STATUS_SERVER_ABORTED;
      }
      elseif (in_array(HumptyMessagePacket::COMPLETED_HEADER, array_keys($receivedHeaders)))
      {
        $this->status = self::STATUS_SERVER_COMPLETED;
      }
      else
      {
        // we increment this here because we are receiving data
        
      }

      foreach($responses[$i]->getHeaders() as $header => $value)
      {
        $this->logger->log("Received [{$header}]: {$value}");
      }

      // if there is a message body, display it
      if ($messageBody = $responses[$i]->getMessageBody())
      {
        $this->logger->log("Message received: $messageBody");
      }
      
      // remove the message from the queue
      $this->messager->removeFirstPacketFromQueue($this->messager->getTypeInbound());
    }
  }

  /**
   * Returns the remote address of the machine this client is interacting with
   * 
   * @return string
   */
  public function getRemoteAddress()
  {
    return $this->remote_address;
  }

  /**
   * Returns the remote port of the machine this client is interacting with
   *
   * @return string
   */
  public function getRemotePort()
  {
    return $this->remote_port;
  }

  /**
   * Invokes the manager client
   */
  public function on_timer()
  {
    if ($this->status == self::STATUS_WAITING_FOR_SERVER)
    {
      $this->receiveCyclesLeft--;
    }
echo "status: {$this->status} \n";
    if ($this->receiveCyclesLeft == 0)
    {
      $this->status = self::STATUS_SERVER_NOT_RESPONDING;
    }

    $this->manager->manageClient($this);
  }
}


