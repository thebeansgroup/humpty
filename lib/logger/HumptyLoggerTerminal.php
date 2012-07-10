<?php
/**
 * Outputs messages to the terminal
 * 
 * @author al
 *
 */
class HumptyLoggerTerminal extends HumptyLogger
{
  /**
   * Add a new message to the log
   * 
   * @param string $message The message to add to the logger
   */
  public function log($message)
  {
    if (!empty($this->client))
    {
      echo "[{$this->client}] ";
    }
    
    echo $message . "\n";
  }
}
