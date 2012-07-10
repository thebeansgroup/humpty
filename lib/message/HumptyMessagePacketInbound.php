<?php
class HumptyMessagePacketInbound extends HumptyMessagePacket
{
  /**
   * @var An error code for when a header block is not correctly terminated
   */
  const EXCEPTION_INVALID_HEADER_TERMINATION = 0;
  
  /**
   * @var An error code for when an individual header key is not correctly separated from its
   * value
   */
  const EXCEPTION_INVALID_HEADER_SEPARATION = 1;
  
  /**
   * Saves header values into class variables
   */
  protected function saveHeadersToProperties()
  {
    $properties = array(
      'project', 
      'action'
    );
    
    foreach ($properties as $property)
    {
      try
      {
        $this->saveHeaderToProperty($property);
      }
      catch (InvalidPropertyException $e)
      {}
    }
    
    // now parse parameters
    foreach ($this->headers as $header => $v)
    {
      // for each parameter header we find, remove it from the headers array, and add it to 
      // the parameters array
      if (strpos($header, self::PARAMETER_HEADER_PREFIX) === 0)
      {
        // unserialise the parameter
        $parameter = unserialize($v);
        $this->addParameter($parameter[0], $parameter[1]);
        $this->removeHeader($header);
      }
    }
  }

  /**
   * Saves the header with the name 'self::$property_HEADER' into $this->$property and removes
   * it from the headers array
   * 
   * @param string $property The name of a header and class property
   * @throws InvalidPropertyException 
   */
  protected function saveHeaderToProperty($property)
  {
    if (!property_exists($this, $property))
    {
      throw new InvalidPropertyException("The property '$property' doesn't exist.");
    }
    
    $header = constant('self::' . strtoupper($property) . '_HEADER');
    
    if ($this->isHeaderSet($header))
    {
      
      $this->$property = $this->getHeader($header);
      $this->removeHeader($header);
    }
  }
  
  /**
   * Parses a raw string of headers into an array
   * 
   * @param string $headers A string of headers to parse
   * @return mixed An array of headers or false if none were present
   * 
   * @todo update this since refactoring
   */
  protected function parseRawHeaders($rawHeaders)
  {
    // make sure the header string ends with self::HEADER_END
    if (substr($rawHeaders, strlen($rawHeaders)-strlen(self::HEADER_END), strlen(self::HEADER_END)) !== self::HEADER_END)
    {
      throw new UnexpectedValueException("The supplied message isn't correctly terminated.", self::EXCEPTION_INVALID_HEADER_TERMINATION);
    }
    
    // split headers on the self::HEADER_SEPARATOR string
    $headers = explode(self::HEADER_SEPARATOR, trim(substr($rawHeaders, 0, strlen($rawHeaders)-strlen(self::HEADER_END))));

    if (count($headers) > 0)
    {
      // loop through and remove colons and leading spaces
      foreach ($headers as $key => $value)
      {
        $components = explode(self::HEADER_VALUE_SEPARATOR, $value);

        // if there are more than 2 components (this would happen if a header value contained self::HEADER_VALUE_SEPARATOR, 
        // combine all components above key position 1 into the value at key position 1.
        if (count($components) > 2)
        {
          for ($i=2; $i<=count($components); $i++)
          {
            $components[1] .= self::HEADER_VALUE_SEPARATOR . $components[$i];
            unset($components[$i]);
          }
        }

        if (count($components) !== 2)
        {
          throw new UnexpectedValueException("A header isn't properly separated.", self::EXCEPTION_INVALID_HEADER_SEPARATION);
        }
        
        $headers[trim($components[0])] = trim($components[1]); 

        // unset the raw header
        unset($headers[$key]);
      }
      
      return $headers;
    }
    
    return false;
  }
  
  /**
   * Extracts a message from a buffer and saves it into class variables. A message body isn't 
   * required - if no content-length header is present, only headers will be parsed.
   * 
   * @param string &$buffer A reference to a buffer. If a message can be read from the buffer,
   * this method will modify the buffer by removing the message from it.
   * @return boolean True if a message was successfully extracted, or false if no message could be extracted
   * 
   * @todo make this work properly - just need to implement the marked code
   */
  public function decode(&$buffer)
  {
    // try to read a complete message
    if (strpos($buffer, self::HEADER_END) !== false)
    {
      // extract the header and parse it
      $requestHeaders = substr(
        $buffer, 
        0, 
        strpos($buffer, self::HEADER_END) + strlen(self::HEADER_END)
      );

      // try to extract the headers into an array 
      try
      {
        $headers = $this->parseRawHeaders($requestHeaders);
      }
      catch (UnexpectedValueException $e)
      {
        // if there was a problem with invalid headers, return a message to the client
        // telling them the message was invalid. Remove the message from the read buffer,
        // including any message body
// implement this
        return false;
      }
        
      if ($headers)
      {
        // remove the raw headers from the read buffer
        $buffer = substr($buffer, strlen($requestHeaders));

        // check whether there is a content-length header
        if (isset($headers[self::CONTENT_LENGTH_HEADER]))
        {
          // try to read that many bytes from the buffer as the message body
          $message = substr($buffer, 0, $headers[self::CONTENT_LENGTH_HEADER]);

          if (strlen($message) != $headers[self::CONTENT_LENGTH_HEADER])
          {
            // if we failed to read the full message body, reconstruct the buffer, and 
            // return false
            $buffer = $requestHeaders . $buffer;
            return false;
          }
          
          // otherwise remove the message from the buffer
          $buffer = substr($buffer, $headers[self::CONTENT_LENGTH_HEADER]);
          
          // save the message body
          $this->messageBody = $message;
        }
        
        $this->headers = $headers;
        
        // update class variables with the contents of the received headers
        $this->saveHeadersToProperties();
      }
            
      return true;
    }
    
    return false;
  }
}