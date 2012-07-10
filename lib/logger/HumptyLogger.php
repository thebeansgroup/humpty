<?php
/**
 * Provides a message logging facility to Humpty so it can report its status easily
 * 
 * @author al
 *
 */
abstract class HumptyLogger
{
  /**
   * 
   * @var string The name of the client class whose messages we are logging
   */
  protected $client;
  
  /**
   * Constructor 
   * 
   * @param string $client The name of the invoking class. Will be used to provide more 
   * meaning full log messages
   */
  public function __construct($client)
  {
    $this->client = $client;
  }
  
  /**
   * Must be implemented by child classes that can decide how to handle messages
   * 
   * @param string $message The message to log
   */
  abstract public function log($message);
}
