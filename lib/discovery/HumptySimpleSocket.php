<?php
/**
 * A simple class that creates sockets - the one in the phpsocketdaemon class isn't abstracted
 * enough.
 *
 * @todo add error checking to all the methods
 */
class HumptySimpleSocket
{
  /**
   * @var resource $socket The created socket
   */
  protected $socket;

  /**
   * @var string $localIp Our local Ip address
   */
  protected $localIp;

  /**
   * @var int $localPort The port we've connected on
   */
  protected $localPort;

  /**
   * Construct a new socket
   *
   * @param <type> $domain
   * @param <type> $type
   * @param <type> $protocol
   */
  public function __construct($domain=AF_INET, $type=SOCK_STREAM, $protocol=SOL_TCP)
  {
    // create a socket
    $this->socket = socket_create($domain, $type, $protocol);
  }

  /**
   * Closes the socket when the class is destroyed
   */
  public function __destruct()
  {
    if (is_resource($this->socket))
    {
      $this->close();
    }
  }

  /**
   * Close the socket
   */
  public function close()
  {
    $this->validateSocket();

    @socket_shutdown($this->socket);
    @socket_close($this->socket);
  }

  /**
   * Returns the local ip
   * 
   * @return string
   */
  public function getIp()
  {
    return $this->localIp;
  }

  /**
   * Returns the local port
   *
   * @return int
   */
  public function getPort()
  {
    return $this->localPort;
  }

  public function createListen($port, $backlog=128)
  {
    $resource = @socket_create_listen($port, $backlog);

    if ($resource === false)
    {
      $this->throwException("Error creating a socket to listen on port $port");
    }

    $this->getSocketName();
  }

  /**
   * Gets the socket address and port
   */
  protected function getSocketName()
  {
    $this->validateSocket();

    // find our local ip and port
    if (@socket_getsockname($this->socket, $this->localIp, $this->localPort) === false)
    {
      $this->throwException("Error retrieving socket IP and port");
    }
  }

  /**
   * Set an option on the socket
   *
   * @param int $level Protocol level to set the option at
   * @param int $name Option name
   * @param mixed $value Value of the option
   */
  public function setOption($level, $name, $value)
  {
    // set it to broadcast
    if (@socket_set_option($this->socket, $level, $name, $value) === false)
    {
      $this->throwException("Error setting option. Level: $level, name: $name, value: $value");
    }
  }

  /**
   * Connect to a remote address
   *
   * @param string $address The remote address
   * @param string $port The remote port
   */
  public function connect($address, $port)
  {
    $this->validateSocket();

    if (@socket_connect($this->socket, $address, $port) === false)
    {
      $this->throwException("Error connecting to $address:$port");
    }

    $this->getSocketName();
  }

  /**
   * Writes a message to the socket
   *
   * @param string $message The message to write
   */
  public function write($message)
  {
    $this->validateSocket();
    
    if (@socket_write($this->socket, $message, strlen($message)) === false)
    {
      $this->throwException("Error writing to socket");
    }
  }

  /**
   * Makes the socket listen for connections. Only applicable to when the socket is running
   * in stream mode.
   *
   * @param int $backlog The maximum number of connections to queue in a backlog
   * @return boolean True if the socket is successfully listening.
   */
  public function listen($backlog=128)
  {
    $this->validateSocket();

    if (!@socket_listen($this->socket, $backlog))
    {
      $this->throwException("Error listening on {$this->localIp}:{$this->localPort}");
    }

    return true;
  }

  /**
   * Validates that the socket has been initialised correctly.
   *
   * @throws HumptySocketException if the socket isn't a resource
   */
  protected function validateSocket()
  {
    if (!is_resource($this->socket))
    {
      throw new HumptySocketException("Socket isn't a valid resource");
    }
  }

  /**
   * Accepts connections on the socket.
   *
   * @return resource $resource A new socket resource that can be read from
   */
  public function accept()
  {
    $this->validateSocket();

    return @socket_accept($this->socket);
  }

  /**
   * Throws a new HumptySocketException containing a message and the last error message on the
   * socket
   *
   * @param string $message A message to prepend to the socket error message
   * @throws HumptySocketException An exception containing the last error on the socket
   */
  protected function throwException($message)
  {
    $error = socket_strerror(socket_last_error($this->socket));
    socket_clear_error($this->socket);

    throw new HumptySocketException($message . ': ' . $error);
  }

  /**
   * Puts a socket into non-blocking mode
   *
   * @return boolean True if the socket was set to non-block
   */
  public function setNonBlock()
  {
    $this->validateSocket();

    if (@socket_set_nonblock($this->socket) === false)
    {
      $this->throwException("Error setting socket to non-block");
    }

    return true;
  }

  /**
   * Binds a socket to an address
   *
   * @param string $address The address to bind to
   * @param int $port The port to bind to
   * @return bool True on success
   */
  public function bind($address, $port)
  {
    $this->validateSocket();

    if (@socket_bind($this->socket, $address, $port) === false)
    {
      $this->throwException("Error binding socket to $address:$port");
    }
  }
}