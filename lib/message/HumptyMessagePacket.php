<?php
/**
 * Class that represents a single message, either inbound or outbound.
 *  
 * @author al
 *
 */
class HumptyMessagePacket
{
  /**
   * @var Sent when the server completes a task
   */
  const COMPLETED_HEADER = 'completed';

  /**
   * @var If the server is misconfigured
   */
  const MISCONFIGURED_HEADER = 'misconfigured';

  /**
   * @var The name of the content length header
   */
  const CONTENT_LENGTH_HEADER = 'content-length';

  /**
   * @var Header that identifies the sender of a message
   */
  const SENDER_ID_HEADER = 'sender-id';
  
  /**
   * @var The name of the timestamp header
   */
  const TIMESTAMP_HEADER = 'timestamp';
  
  /**
   * @var The prefix for parameter headers
   */
  const PARAMETER_HEADER_PREFIX = 'parameter-';
  
  /**
   * @var The name of the project header
   */
  const PROJECT_HEADER = 'project';

  /**
   * @var The name of the action header
   */
  const ACTION_HEADER = 'action';

  /**
   * @var The name of a header for status messages
   */
  const STATUS_HEADER = 'status';

  /**
   * A header used to tell clients that an action isn't configured on a server
   */
  const NOT_IMPLEMENTED_HEADER = 'not-implemented';

  /**
   * A header used when an action is aborted by the server
   */
  const ABORTED_HEADER = 'aborted';

  /**
   * @var string that separates header keys from values
   */
  const HEADER_VALUE_SEPARATOR = ': ';
  
  /**
   * @var string that separates headers
   */
  const HEADER_SEPARATOR = "\n";
  
  /**
   * @var string that ends a header
   */
  const HEADER_END = "\n\n";
  
  /**
   * @var array An array of headers
   */
  protected $headers = array();
  
  /**
   * @var array An array of parameters
   */
  protected $parameters = array();
  
  /**
   * @var string A message body
   */
  protected $messageBody;

  /**
   * @var string Variables for the project and action names
   */
  protected $project, $action;

  /**
   * @var mixed The local address and port of the machine the messager is running on
   */
  /*
  protected $localAddress, $localPort;
  
  public function __construct($localAddress, $localPort)
  {
    $this->localAddress = $localAddress;
    $this->localPort = $localPort;
  }
  */
  
  /**
   * Returns the name of the project from the parsed headers
   *
   * @return string
   */
  public function getProject()
  {
    return $this->project;
  }

  /*
   * Returns the name of the action from the parsed headers
   *
   * @return string
   */
  public function getAction()
  {
    return $this->action;
  }

  /**
   * Returns the name of the project from the parsed headers
   *
   * @param string $name The name to set
   * @return string
   */
  public function setProject($name)
  {
    return $this->project = $name;
  }

  /*
   * Returns the name of the action from the parsed headers
   *
   * @return string
   */
  public function setAction($name)
  {
    return $this->action = $name;
  }
  
  /**
   * Returns the header separator string
   * 
   * @return string The header separator string
   */
  public function getHeaderSeparator()
  {
    return self::HEADER_SEPARATOR;
  }
  
  /**
   * Returns the header end string
   *
   * @return string The header end string
   */
  public function getHeaderEnd()
  {
    return self::HEADER_END;
  }
  
  /**
   * Sets a header 
   * 
   * @param string $header The name of the header to set
   * @param string $value The value of the header to set
   * @throws HeaderAlreadySetException if the header is already set in the header array
   */
  public function setHeader($header, $value)
  {
    if ($this->isHeaderSet($header))
    {
      throw new HeaderAlreadySetException("The header '$header' is already set (with value {$this->headers[$name]}");
    }

    $this->headers[$header] = $value;
  }
  
  /**
   * Removes a header from the header array
   * 
   * @param string $header The header to remove
   */
  public function removeHeader($header)
  {
    if ($this->isHeaderSet($header))
    {
      unset($this->headers[$header]);
    }
  }
  
  /**
   * Returns all of the headers
   * 
   * @return array An array of headers
   */
  public function getHeaders()
  {
    return $this->headers;
  }
  
  /**
   * Returns all of the headers
   * 
   * @return array An array of headers
   */
  public function getHeader($header)
  {
    if ($this->isHeaderSet($header))
    {
      return $this->headers[$header];
    }
    
    throw new HeaderAbsentException("The header '$header' is not present");
  }
  
  /**
   * Returns whether a named header is present in the header array
   * 
   * @param string $header The name of the header to search for
   * @return bool
   */
  public function isHeaderSet($header)
  {
    return isset($this->headers[$header]);
  }
  
  /**
   * Resets the header array to an empty array
   */
  protected function clearHeaders()
  {
    $this->headers = array();
  }
  
  /**
   * Adds a parameter
   * 
   * @param string $name The name of a parameter
   * @param mixed $value The value to set a parameter as
   */
  public function addParameter($name, $value)
  {
    $this->parameters[] = array($name, $value);
  }
  
  /**
   * Returns the message parameters
   * 
   * @return array of message parameters. Each value is an array, where the first key
   * is the parameter name, the second is the parameter value
   */
  public function getParameters()
  {
    return $this->parameters;
  }
  
  /**
   * Returns the message parameters as a one-dimensional associative array
   * 
   * @return array
   */
  public function getParametersAssociative()
  {
    $parameters = $this->getParameters();
    $paramsAssoc = array();
    
    for ($i=0; $i<count($parameters); $i++)
    {
      $paramsAssoc[$parameters[$i][0]] = $parameters[$i][1];
    }
    
    return $paramsAssoc;
  }
  
  /**
   * Sets a message body that will be sent when the message is encoded
   * 
   * @param string $message The message to send
   */
  public function setMessageBody($message)
  {
    $this->setHeader(self::CONTENT_LENGTH_HEADER, strlen($message));
    $this->messageBody = $message;
  }

  /**
   * Gets the message body
   * 
   * @return string The message body   */
  public function getMessageBody()
  {
    return $this->messageBody;
  }
  
  /**
   * Returns whether a message body is set
   * 
   * @return bool
   */
  public function isMessageBodySet()
  {
    return ($this->isHeaderSet(self::CONTENT_LENGTH_HEADER) && 
        (strlen($this->getMessageBody()) == $this->getHeader(self::CONTENT_LENGTH_HEADER)));
  }
  
  /**
   * Clears the message body
   */
  protected function clearMessageBody()
  {
    $this->messageBody = '';
    
    if ($this->isHeaderSet(self::CONTENT_LENGTH_HEADER))
    {
      $this->removeHeader(self::CONTENT_LENGTH_HEADER);
    }
  }
}