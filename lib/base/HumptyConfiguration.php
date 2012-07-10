<?php
/**
 * Configuration class for Humpty. Handles parsing config files and returning
 * settings.
 * 
 * @author al
 *
 */
class HumptyConfiguration
{
  /**
   * @var array An array of config values
   */
  private $configuration;
  
  /**
   * Initialises the class. Searches for the named config file and 
   * parses it
   * 
   * @param string $configFile Path to a configuration file
   */
  public function __construct($configFile)
  {
    if (!file_exists($configFile))
    {
      throw new RuntimeException("Config file $configFile doesn't exist.");
    }
    
    require_once($configFile);
    
    if (isset($server))
    {
      $this->configuration['server'] = $server;
    }
    
    if (isset($actions))
    {
      $this->configuration['actions'] = $actions;
    }

    if (isset($discovery))
    {
      $this->configuration['discovery'] = $discovery;
    }
  }
  
  /**
   * Returns the value of a setting
   * 
   * @param string $name The name of the setting to return
   * @return string
   */
  public function getSetting($name)
  {
    if (strpos($name, '-') === false)
    {
      throw new InvalidArgumentException("Configuration key '$name' doesn't exist.");
    }
    
    $key = substr($name, 0, strpos($name, '-'));
    $name = substr($name, strpos($name, '-') + 1);

    if (!isset($this->configuration[$key][$name]))
    {
      throw new InvalidArgumentException("Configuration value '$key/$name' not set");
    }
    
    return $this->configuration[$key][$name];
  }
  
  /**
   * Returns the configuration array for a given project and action 
   * 
   * @param string $project The name of a project
   * @param string $action The name of an action on a project, or in the global space
   * @return mixed An array of configuration data on success, or false if nothing
   * matches the project/action pair.
   */
  public function getProjectActionSettings($project, $action)
  {
    // first, see whether the project/action combination itself exists
    if (isset($this->configuration['actions'][$project][$action]))
    {
      return $this->configuration['actions'][$project][$action];
    }
    // otherwise check the global namespace
    elseif (isset($this->configuration['actions']['global'][$action]))
    {
      return $this->configuration['actions']['global'][$action];
    }

    // otherwise return false
    return false;
  }
}
