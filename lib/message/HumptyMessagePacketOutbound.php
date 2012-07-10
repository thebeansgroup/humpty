<?php
/**
 * Represents outbound message packets
 */
class HumptyMessagePacketOutbound extends HumptyMessagePacket
{
  /**
   * @var string A string that identifies the sender
   */
  protected $senderId;

  /**
   * Sets the sender id
   *
   * @param string $id The string to set the ID to
   */
  public function setSenderId($id)
  {
    $this->senderId = $id;
  }

  /**
   * Returns a header as a string
   * 
   * @param string $header The header name
   * @param string $value The header value
   * @return string The raw header
   */
  public function makeRawHeader($header, $value)
  {
    return $header . self::HEADER_VALUE_SEPARATOR . $value;
  }
  
  /**
   * Encodes the message ready for transmission to the Humpty server.
   * 
   * Messages are in the format:
   * header-1: value-1
   * header-2: value-2
   * [content-length: xxx bytes]
   * \n
   * \n
   * [message body]
   * 
   * A space is inserted before the colon that separates header keys from values.
   * A header is terminated by a single \n character.
   * Two \n characters indicate the end of the header.
   * 
   * If the 'content-length' header is present, a message of that 
   * many bytes will follow the header end characters
   * 
   * @return string The encoded message
   * 
   * @todo Add IDs and timestamps to outgoing messages
   */
  public function encode()
  {
    if (strlen($this->getProject()) == 0)
    {
      throw new InvalidPacketException("No project has been specified.");
    }

    if (strlen($this->getAction()) == 0)
    {
      throw new InvalidPacketException("No action has been specified.");
    }

    date_default_timezone_set('UTC');
    
    $messages = array(
      $this->makeRawHeader(self::PROJECT_HEADER, $this->getProject()),
      $this->makeRawHeader(self::ACTION_HEADER, $this->getAction()),
      $this->makeRawHeader(self::TIMESTAMP_HEADER, strftime('%F %T')),
//      $this->makeRawHeader(self::SENDER_ID_HEADER, $this->localAddress . ':' . $this->localPort)
    );

    if (isset($this->senderId))
    {
      $messages[] = $this->makeRawHeader(self::SENDER_ID_HEADER, $this->senderId);
    }

    foreach ($this->getHeaders() as $k => $v)
    {
      $messages[] = $this->makeRawHeader($k, $v);
    }
    
    // add parameters to the message
    $parameters = $this->getParameters();
    for ($i=0; $i<count($parameters); $i++)
    {
      $messages[] = $this->makeRawHeader(self::PARAMETER_HEADER_PREFIX . $i, serialize($parameters[$i]));
    }

    if (count($messages) > 0)
    {
      $message = implode(self::HEADER_SEPARATOR, $messages) . self::HEADER_END;

      // if there is an outbound content-length header, append the message body
      // to the message
      if ($this->isMessageBodySet())
      {
        $message .= $this->getMessageBody();
      }
      
      return $message;
    }
    
    return '';
  }
}