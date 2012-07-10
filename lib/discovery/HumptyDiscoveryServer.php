<?php
/**
 * A discovery server. It listens for UDP broadcast messages and sends a reply.
 *
 * This allows clients to broadcast to discover listening servers.
 */
class HumptyDiscoveryServer
{
  const DISCOVERY_FROM_HEADER = 'humpty-discovery-from';

  const DISCOVERY_RESPONSE_HEADER = 'humpty-discovery-response';

  /**
   * @var HumptyConfiguration A configuration object
   */
  protected $config;

  /**
   * @var HumptyLogger $logger A logger
   */
  protected $logger;

  /**
   * @var resource The socket we're listening on
   */
  protected $socket;

  /**
   * Starts a new discovery server and returns the process id of
   * the process that is listening for data
   *
   * @param HumptyConfiguration $config A configuration object
   * @param string $loggerClass The name of a class to use for logging
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
   * Starts the server as a new process
   */
  public function startServer()
  {
    // fork
    $pid = pcntl_fork();

    // if we're in the child process, start the server and listen for data
    if ($pid > 0)
    {
      return $pid;
    }
    elseif ($pid === 0)
    {
      return $this->listen();
    }
  }

  /**
   * Starts a server and makes it listen on the socket
   */
  protected function listen()
  {
    // Set time limit to indefinite execution
    set_time_limit (0);

    $this->socket = socket_create(
      AF_INET,
      SOCK_DGRAM,
      SOL_UDP
    );

    if (!is_resource($this->socket))
    {
      $this->logger->log("Failed to create a socket for the discovery server. The reason was: " .
        socket_strerror(socket_last_error()));
    }

    if (!@socket_bind(
      $this->socket,
      $this->config->getSetting('discovery-address'),
      $this->config->getSetting('discovery-port')))
    {
      $this->logger->log("Failed to bind to socket while initialising the discovery server.");
    }

    // enter an infinite loop, waiting for data
    $data = '';
    while (true)
    {
      if (@socket_recv($this->socket, $data, 9999, MSG_WAITALL))
      {
        $this->logger->log("Discovery server received the following: $data");

        $this->handleMessage($data);
      }
    }
  }

  /**
   * Closes the socket on destroy
   */
  public function __destruct()
  {
    if (is_resource($this->socket))
    {
      socket_shutdown($this->socket);
      socket_close($this->socket);
    }
  }

  /**
   * Handles a message received by the server
   *
   * @param string $data Data received by the server
   */
  protected function handleMessage($data)
  {
    // check whether the message was for us
    if (strpos($data, self::DISCOVERY_FROM_HEADER) !== false)
    {
      // if it was, pull out the sending IP so we know where to respond to
      preg_match("/" . self::DISCOVERY_FROM_HEADER . ": (\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d+)/", $data,   $matches);

      if (count($matches) > 1)
      {
        $callerIp = $matches[1];
        $callerPort = $matches[2];
      }

      // connect to the sender, telling them we exist.
      $replySocket = new HumptySimpleSocket();

      try
      {
        // connect to the broadcast ip and port
        $replySocket->connect(
          $callerIp,
          $callerPort
        );

        // compose the discovery message
        $message = HumptyDiscoveryServer::DISCOVERY_RESPONSE_HEADER . ': ' .
          $replySocket->getIp() . ':' . $this->config->getSetting('server-port');

        $this->logger->log("Discovery server sending response to: $callerIp:$callerPort");

        // try to write the discovery message to the socket
        $replySocket->write($message);

        $replySocket->close();
      }
      catch (HumptySocketException $e)
      {
        $this->logger->log($e->getMessage());
      }
    }
  }
}