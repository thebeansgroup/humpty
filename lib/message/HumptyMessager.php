<?php
/**
 * This class manages the encoding and parsing of messages between the Humpty client and
 * server.
 * 
 * A Humpty message is a set of headers terminated by $this->inboundPacketClass->getHeaderEnd(). 
 * 
 * These correspond to entries in the Humpty config file.
 * 
 * @author al
 *
 */
class HumptyMessager
{
  /**
   * @var A constant used when instantiating new packet classes
   */
  const INBOUND = 'inbound';

  /**
   * @var A constant used when instantiating new packet classes
   */
  const OUTBOUND = 'outbound';
  
  /**
   * @var array An array packets to send
   */
  protected $outboundQueue = array();

  /**
   * @var array An array of received packets
   */
  protected $inboundQueue = array();

  /**
   * @var string The name of a class to use for incoming packets
   */
  protected $inboundPacketClass;
  
  /**
   * @var string The name of a class to use for outgoing packets
   */
  protected $outboundPacketClass;
  
  /**
   * Constructor. Takes the name of a class to use for message packets
   * 
   * @param string $inboundPacketClass The name of a class to use for incoming message packets
   * @param string $outboundPacketClass The name of a class to use for outgoing message packets
   */
  public function __construct($inboundPacketClass='HumptyMessagePacketInbound', $outboundPacketClass='HumptyMessagePacketOutbound')
  {
    $this->inboundPacketClass = $inboundPacketClass;
    $this->outboundPacketClass = $outboundPacketClass;
  }
  
  /**
   * Returns the value of the self::INBOUND constant
   * 
   * @return string
   */
  public function getTypeInbound()
  {
    return self::INBOUND;
  }

  /**
   * Returns the value of the self::OUTBOUND constant
   * 
   * @return string
   */
  public function getTypeOutbound()
  {
    return self::OUTBOUND;
  }
  
  /**
   * Returns a new instance of the named type of packet class
   *  
   * @param string $type The type of packet class to instantiate, either self::INBOUND or self::OUTBOUND
   * @return object A message packet class
   */
  public function getNewPacketInstance($type)
  {
    $property = $type . 'PacketClass';
    
    if (property_exists($this, $property))
    {
      return new $this->$property();
    }
    
    throw new InvalidArgumentException("$type is not a valid type of message packet class to instantiate.");
  }

  /**
   * Returns queued packets of the given type
   *
   * @param string $type The type of packets to return
   * @return array All queued packets of the named type
   */
  public function getQueuedPackets($type)
  {
    $property = $type . 'Queue';

    if (property_exists($this, $property))
    {
      return $this->$property;
    }

    throw new InvalidArgumentException("$type is not a valid packet queue type.");
  }
  
  /**
   * Removes the first packet from the named queue
   * 
   * @param string $type The type of packet queue to manipulate
   */
  public function removeFirstPacketFromQueue($type)
  {
    $property = $type . 'Queue';

    if (property_exists($this, $property))
    {
      array_shift($this->$property);
      return;
    }

    throw new InvalidArgumentException("$type is not a valid packet queue type.");
  }
  
  /**
   * Extracts a message from a buffer and saves it into class variables. A message body isn't 
   * required - if no content-length header is present, only headers will be parsed.
   * 
   * @param string &$buffer A reference to a buffer. If a message can be read from the buffer,
   * this method will modify the buffer by removing the message from it.
   * @return boolean True if a message was successfully extracted, or false if no message could be extracted
   */
  public function extractMessage(&$buffer)
  {
    // try to decode as many messages as possible, and save them to the inbound queue 
    $anyMessagesDecoded = false;

    while (true)
    {
      $request = $this->getNewPacketInstance($this->getTypeInbound());

      // if the buffer is decoded to a valid message
      if ($request->decode($buffer))
      {
        // add it to the queue
        $this->inboundQueue[] = $request;

        // save the fact that we have decoded a message
        $anyMessagesDecoded = true;
      }
      else
      {
        // return whether any messages were decoded
        return $anyMessagesDecoded;
      }
    }
    
    return false;
  }
}