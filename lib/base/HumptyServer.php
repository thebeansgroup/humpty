<?php
/**
 * Entry point that initialises all the server code
 */
class HumptyServer
{
  /**
   * @var HumptyConfiguration $config A configuration object
   */
  protected $config;

  /**
   * @var int $discoveryServer The process id of a discovery server class
   */
  protected $discoveryServerPid;

  /**
   * @var HumptyLogger $logger Logger instance
   */
  protected $logger;

  /**
   * Constructor that initialises the server
   */
  public function __construct()
  {
    $this->config = new HumptyConfiguration(realpath(dirname(__FILE__) . '/../../conf/server.properties.php'));
    $this->logger = new HumptyLoggerTerminal(__CLASS__);
  }

  /**
   * Destroys the discovery server when the class is destroyed
   */
  public function __destruct()
  {
    // kill the discovery server if it's listening
    if ($this->discoveryServerPid > 0)
    {
      posix_kill($this->discoveryServerPid, SIGTERM);
    }
  }

  /**
   * Starts and forks a new discovery server
   */
  public function startDiscoveryServer()
  {
    $discoveryServer = new HumptyDiscoveryServer($this->config, get_class($this->logger));
    $this->discoveryServerPid = $discoveryServer->startServer();

    $this->logger->log("Started discovery server with pid: {$this->discoveryServerPid}");
  }

  /**
   * Daemonises the server
   */
  public function daemonise()
  {
    $this->startDiscoveryServer();

    // create a new daemon
    $daemon = new HumptySocketDaemon();

    // initialise a server
    $server = $daemon->create_server(
      'HumptyServerManager',
      'HumptyServerThread',
      $this->config->getSetting('server-address'),
      $this->config->getSetting('server-port'),
      AF_INET,
      SOCK_STREAM,
      SOL_TCP
    );
    $server->setConfiguration($this->config);

    // start the daemon running
    $daemon->process();
  }
}