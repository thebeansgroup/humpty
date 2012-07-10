<?php
/**
 * Class that acts as an entry point for the rest of the client interface
 */
class HumptyClient
{
  /**
   * @var HumptyClientServerQueue A client server queue object
   */
  protected $serverQueue;

  /**
   * @var HumptyLogger $logger Logger instance
   */
  protected $logger;

  /**
   * Creates a new client manager class that will fire the given project, action and
   * parameters combination across all servers
   */
  public function __construct()
  {
    $this->logger = new HumptyLoggerTerminal(__CLASS__);

    $this->serverQueue = new HumptyClientServerQueue(
      new HumptyConfiguration(realpath(dirname(__FILE__) . '/../../conf/client.properties.php')),
      get_class($this->logger)
    );

  }

  /**
   * Dispatches the commands. This method allows the classes to be instantiated as a library so other methods can be
   * called without performing an action.
   *
   * @param string $project The project name
   * @param string $action An action name
   * @param array $parameters Extra parameters to pass
   */
  public function dispatch($project, $action, array $parameters)
  {
    try
    {
      // make the servers run the command
      $this->serverQueue->messageServers($project, $action, $parameters);
      $this->logger->log("All done");
    }
    catch (Exception $e)
    {
      $this->logger->log($e->getMessage());
      $this->logger->log($e->getTraceAsString());
    }
  }
}