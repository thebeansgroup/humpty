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
 * This class represents a child process of the Humpty server.
 * 
 * @author al
 *
 */
class HumptyServerThread extends socketServerClient
{
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
   * @var HumptyBaseEngine An engine that has been invoked
   */
  protected $engine;
  
  /**
   * @var HumptyMessagePacket The message packet currently being handled
   */
  protected $currentRequest;
  
  /**
   * @var string A string to use as a server ID
   */
  protected $serverId;

  /**
   * Constructor
   */
  public function __construct($socket)
  {
    $this->messager = new HumptyMessager();
    $this->logger = new HumptyLoggerTerminal(__CLASS__);

    return parent::__construct($socket);
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
   * Sets the server id so this thread will respond with a consistent ID from this server
   * 
   * @param string $id The id of the server
   */
  public function setServerId($id)
  {
    $this->serverId = $id;
  }

  /**
   * Loops through all queued requests and handles them
   */
  public function handleRequests()
  {
    $requests = $this->messager->getQueuedPackets($this->messager->getTypeInbound());
    
    for ($i=0; $i<count($requests); $i++)
    {
      $this->handleRequest($requests[$i]);
      
      // remove the message from the queue
      $this->messager->removeFirstPacketFromQueue($this->messager->getTypeInbound());
    }
  }
  
  /**
   * Dispatches the request (contained in the messager class) to the appropriate engine
   *
   * @param HumptyMessagePacket $request An inbound message packet
   */
  public function handleRequest($request)
  {
    $this->currentRequest = $request;
    
    $project = $request->getProject(); 
    $action =  $request->getAction();
    
    // retrieve any extra parameters
    $parameters = $request->getParametersAssociative();

    // find the engine to invoke from the server configuration
    if ($actionConfig = $this->configuration->getProjectActionSettings($project, $action))
    {
      // if an engine is configured, try to instantiate it
      if (isset($actionConfig['engine']))
      {
        try
        {
          $this->engine = $this->loadEngine($actionConfig['engine']);
          
          // now run the engine
          $this->sendHeaderMessage(HumptyMessagePacket::STATUS_HEADER,
            "Running engine with: project '$project', action '$action' and parameters " .
            $this->formatArray($parameters));

          // submitted parameter values will override defaults set in the config file. If the 
          // engine is 'Shell', we must not allow the user to specify the command
          if (get_class($this->engine) == 'Shell' || is_subclass_of($this->engine, 'Shell'))
          {
            if (isset($parameters['command']))
            {
              unset($parameters['command']);
            }
          }
            
          $parameters = array_merge($actionConfig, $parameters);

          $this->engine->run($project, $action, $parameters);

          $this->logger->log("sending completed header to {$this->remote_address}:{$this->remote_port}");
          $this->sendHeaderMessage(HumptyMessagePacket::COMPLETED_HEADER, 1);
        }
        catch (Exception $e)
        {
          $this->sendHeaderMessage(HumptyMessagePacket::ABORTED_HEADER, $e->getMessage());
        }
      }
      else
      {
        $this->sendHeaderMessage(HumptyMessagePacket::MISCONFIGURED_HEADER,
          "No engine is configured for $project/$action. Nothing else to do.");
      }
    }
    else
    {
      $this->sendHeaderMessage(HumptyMessagePacket::NOT_IMPLEMENTED_HEADER,
        "No settings match $project/$action.");
    }
  }
  
  /**
   * Converts an array to a nicely formatted string
   * 
   * @param array $array The array to format
   * @return string
   */
  protected function formatArray(array $array)
  {
    $string = '';
    
    foreach ($array as $k => $v)
    {
      $string .= "[$k]: $v\n";
    }
    
    return $string;
  }
  
  /**
   * Loads an engine or throws an exception
   * 
   * @param string $engineClass The name of the engine class to load
   * @return HumptyBaseEngine An instance of the engine
   * @throws InvalidEngineException
   */
  protected function loadEngine($engineClass)
  {
    $this->sendHeaderMessage(HumptyMessagePacket::STATUS_HEADER, "Initialising engine '$engineClass'");
    $engine = new $engineClass($this);

    if (!($engine instanceof HumptyBaseEngine))
    {
      throw new InvalidEngineException("Engine class '$engineClass' doesn't extend 'HumptyBaseEngine'. It needs to.");
    }
    
    $this->sendHeaderMessage(HumptyMessagePacket::STATUS_HEADER, "Engine '$engineClass' initialised.");
    return $engine;
  }
  
  /**
   * Sets a header with a value, and sends it to the client
   *
   * @param string $header The header to set
   * @param string $value The value to set the header to
   */
  public function sendHeaderMessage($header, $value)
  {
    $packet = $this->prepareOutboundMessage();
    $packet->setHeader($header, $value);
    
    return $this->write($packet->encode());
  }

  /**
   * Prepares a new outbound packet with mandatory headers
   * 
   * @return HumptyMessagePacketOutbound
   */
  protected function prepareOutboundMessage()
  {
    $packet = $this->messager->getNewPacketInstance($this->messager->getTypeOutbound());
    $packet->setProject($this->currentRequest->getProject());
    $packet->setAction($this->currentRequest->getAction());
    $packet->setSenderId($this->serverId);

    return $packet;
  }

  /**
   * Sends a message with a message body back to the client
   * 
   * @param string $message The message body to send
   */
  public function sendMessage($message)
  {
    $packet = $this->prepareOutboundMessage();
    $packet->setMessageBody($message);
    
    return $this->write($packet->encode());
  }
  
  /**
   * Reads the read buffer. Once a full message has been read, the complete message will
   * be passed to a dispatcher method that will invoke the correct engine
   * 
   * (non-PHPdoc)
   * @see webroot/studentbeans/lib/externals/Humpty/phpsocketdaemon/socketClient#on_read()
   */
  public function on_read()
  {
    $this->logger->log("Reading data from client at {$this->remote_address}:{$this->remote_port}");

    // if we can extract a request from the buffer, handle it.
    if ($this->messager->extractMessage($this->read_buffer))
    {
      // handle the requests
      $this->handleRequests();
    }
  }

  /**
   * Called when a client connects to the thread
   * 
   * (non-PHPdoc)
   * @see webroot/studentbeans/lib/externals/Humpty/phpsocketdaemon/socketClient#on_connect()
   */
  public function on_connect()
  {
    $this->logger->log("Accepted connection from client at {$this->remote_address}:{$this->remote_port}");
  }

  /**
   * Called when a client disconnects
   * 
   * (non-PHPdoc)
   * @see webroot/studentbeans/lib/externals/Humpty/phpsocketdaemon/socketClient#on_disconnect()
   */
  public function on_disconnect()
  {
    $this->logger->log("Client at {$this->remote_address}:{$this->remote_port} disconnected");
  }
  
  /**
   * Called when data is sent to a client
   * 
   * (non-PHPdoc)
   * @see webroot/studentbeans/lib/externals/Humpty/lib/phpsocketdaemon/socketClient#on_write()
   */
  public function on_write() 
  {
    $this->logger->log("Writing data to client at {$this->remote_address}:{$this->remote_port}");
  }
}
