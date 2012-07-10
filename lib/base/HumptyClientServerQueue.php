<?php
/**
 * Class that instantiates humpty clients and manages communications with multiple servers. It does the following:
 *
 *  * discovers servers to see which ones can respond to tasks
 *  * records when a server says it has performed an action
 *  * retries with servers to get them to perform an action
 */
class HumptyClientServerQueue implements ClientManager
{
  /**
   * Reply time out for server discovery in microseconds. This class will wait
   * self::REPLY_TIME_OUT seconds for servers to respond to a discovery request.
   */
  const REPLY_TIME_OUT = 0.1;

  /**
   * @var array An array of client connections to different servers
   */
  protected $connections = array();

  /**
   * @var array An array of ip and port details for all listening servers.
   * IP addresses are keys in the array, and port numbers are values
   */
  protected $servers = array();

  /**
   * @var array An array of connections to servers. Keys are IP addresses, values
   * are HumptyClientThread objects
   */
  protected $serverConnections = array();

  /**
   * @var array An array of willing servers yet to report that they have completed their task
   */
  protected $uncompletedServers = array();

  /**
   * @var HumptyConfiguration A configuration object
   */
  protected $config;

  /**
   * @var HumptyLogger $logger Logger instance
   */
  protected $logger;

  /**
   * @var string $localIp The local IP we are running from
   */
  protected $localIp;

  /**
   * @var HumptySocketDaemon A daemon controlling the queue of client sockets
   */
  protected $daemon;

  /**
   * Constructor
   * 
   * @param HumptyConfiguration $config 
   */
  public function __construct(HumptyConfiguration $config, $loggerClass)
  {
    $this->config = $config;
    $this->logger = new $loggerClass(__CLASS__);

    if (!is_subclass_of($this->logger, 'HumptyLogger'))
    {
      throw new InvalidClassException("The logger class must extend HumptyLogger");
    }
  }

  /**
   * Broadcasts to the subnet to see which IPs servers are listening on.
   */
  protected function discoverServers()
  {
    $discoverySocket = new HumptySimpleSocket(
      AF_INET,
      SOCK_DGRAM,
      SOL_UDP
    );

    // set it to broadcast
    $discoverySocket->setOption(SOL_SOCKET, SO_BROADCAST, 1);

    // connect to the broadcast ip and port
    $discoverySocket->connect(
      $this->config->getSetting('discovery-address'),
      $this->config->getSetting('discovery-port')
    );

    $this->localIp = $discoverySocket->getIp();

    // set up a socket to listen for replies on
    $replySocket = new HumptySimpleSocket();
    $replySocket->bind($this->localIp, $this->config->getSetting('discovery-port'));
    $replySocket->setNonBlock();
    $replySocket->listen();
    
    // compose the discovery message containing the details of the port servers should reply to
    $message = HumptyDiscoveryServer::DISCOVERY_FROM_HEADER . ': ' .
      $this->localIp . ':' . $this->config->getSetting('discovery-port');

    // write the discovery message to the socket
    $discoverySocket->write($message);

    // reply time out in milliseconds
    $replyTimeOut = microtime(true) + self::REPLY_TIME_OUT;

    // listen for replies, and save address details to the server array
    do
    {
      if ($server = $replySocket->accept())
      {
        socket_getpeername($server, $remoteAddress, $remotePort);
        if ($data = @socket_read($server, 128))
        {
          preg_match("/" . HumptyDiscoveryServer::DISCOVERY_RESPONSE_HEADER . ": (\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d+)/", $data, $matches);

          if (count($matches) != 3)
          {
            continue;
          }
          else
          {
            $port = $matches[2];
            $this->servers[$remoteAddress] = $port;
          }
        }
      }
    } while (microtime(true) <= $replyTimeOut);

    // close the sockets
    $discoverySocket->close();
    $replySocket->close();
  }

  /**
   * Opens a connection to each server in the $this->servers array
   */
  protected function connectToServers()
  {
    $this->daemon = new HumptySocketDaemon();
    $this->daemon->setTimerInterval(1);

    foreach ($this->servers as $serverIp => $serverPort)
    {
      $client = $this->daemon->create_client(
        'HumptyClientThread',
        $serverIp,
        $serverPort
      );

      $client->setConfiguration($this->config);
      $client->setManager($this);

      $this->serverConnections[$serverIp] = $client;
    }
  }

  /**
   * Commands servers to carry out the specified task.
   *
   * @param string $project The project to invoke
   * @param string $action The action to invoke
   * @param array $parameters Extra parameters to send
   */
  protected function commandServers($project, $action, array $parameters)
  {
    // connect to all the servers if we need to
    if (empty($this->serverConnections))
    {
      $this->connectToServers();
    }

    $this->daemon->set_block();

    // connect to each server and issue the command
    foreach ($this->serverConnections as $ip => $server)
    {
      // add the server's ip to the uncompleted servers array
      $this->uncompletedServers[$ip] = 1;

      // queue a message for the server to see if it can perform our intended action
      $server->sendMessage($project, $action, $parameters);
    }

    // run the daemon that controls all connections to servers
    $this->daemon->process();
  }

  /**
   * Records that the named server has completed the task
   *
   * @param string $ip The IP of a server that was carrying out a task
   */
  protected function completedServer($ip)
  {
    if (isset($this->uncompletedServers[$ip]))
    {
      unset($this->uncompletedServers[$ip]);
    }
  }

  /**
   * Execute a command on servers
   *
   * @param string $project The project to invoke
   * @param string $action The action to invoke
   * @param array $parameters Extra parameters to send
   */
  public function messageServers($project, $action, array $parameters)
  {
    // discover servers if necessary
    if (count($this->servers) == 0)
    {
      try 
      {
        $this->discoverServers();
      }
      catch (HumptySocketException $e)
      {
        $this->logger->log($e->getMessage());
        throw $e;
      }
    }

    $this->commandServers($project, $action, $parameters);
  }

  /**
   * Allows this class to manage clients it creates. They call this
   * methods in their on_timer methods.
   *
   * @param HumptyClientThread $thread A client controlled by this class
   */
  public function manageClient(HumptyClientThread $thread)
  {
    $this->logger->log('Ping back from client thread communicating with ' .
      $thread->getRemoteAddress() . ':' . $thread->getRemotePort());

    // if a server has responded, remove them from the uncompletedServers array
    if ($thread->getStatus() !== HumptyClientThread::STATUS_WAITING_FOR_SERVER)
    {
      $this->logger->log("Server at {$thread->getRemoteAddress()} responded: " . $thread->getStatus());
      $this->completedServer($thread->getRemoteAddress());
    }

    // if all servers have responded, stop the daemon from processing
    if (count($this->uncompletedServers) == 0)
    {
      $this->daemon->set_unblock();
    }
  }
}