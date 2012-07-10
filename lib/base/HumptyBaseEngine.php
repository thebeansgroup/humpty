<?php
/**
 * Abstract base class for engines
 */
abstract class HumptyBaseEngine
{
  /**
   * @var HumptyServerThread The thread that instantiated this engine.
   */
  protected $thread;
  
  /**
   * Constructor.
   * 
   * @param HumptyServerThread $thread The thread that instantiated this engine.
   */
  public function __construct(HumptyServerThread $thread)
  {
    $this->thread = $thread;
  }
  
  /**
   * Carries out the action for the engine.
   * 
   * @param string $project The name of a project to work with
   * @param string $action The name of an action to perform
   * @param array $parameters An array of parameters to use
   * @return boolean
   */
  abstract public function run($project, $action, array $parameters);
}