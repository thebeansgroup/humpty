<?php
/**
 * Class that runs commands in the shell. 
 * 
 * @author al
 *
 */
class Shell extends HumptyBaseEngine
{
  /**
   * Entry point for the engine. This engine will run shell commands, appending whatever
   * parameters are given as a 'params' parameter to the client. 
   * 
   * @param string $project The name of a project to work with
   * @param string $action The name of an action to perform
   * @param array $parameters An array of parameters to use
   * @return boolean
   */
  public function run($project, $action, array $parameters)
  {
    // make sure there is a 'command' parameter
    if (!isset($parameters['command']))
    {
      throw new InvalidArgumentException("No 'command' parameter given.");
    }
    
    $command = (isset($parameters['params'])) ? $parameters['command'] . ' ' . $parameters['params'] :
      $parameters['command'];
    
    // execute command
    $output = shell_exec($command);

    // return output
    return $this->thread->sendMessage($output);
  }
}