<?php
/**
 * Exception class for when an attempt is made to access a inbound header that doesn't exist
 * 
 * @author al
 */
class HeaderAbsentException extends Exception
{}

/**
 * Exception class for when an outbound header has already been set and an
 * attempt is made to set it again.
 * 
 * @author al
 */
class HeaderAlreadySetException extends Exception
{}

/**
 * An exception for when a client or server hasn't been initialised correctly
 */
class InitialisationException extends Exception
{}

/**
 * An exception for when a header is invalid
 */
class InvalidHeaderException extends Exception
{}

/**
 * An exception for when users try to invoke engines that are not valid
 */
class InvalidEngineException extends Exception
{}

/**
 * An exception thrown when a packet is invalid
 */
class InvalidPacketException extends Exception
{}

/**
 * Used when an attempt is made to set a property on a class that doesn't exist
 */
class InvalidPropertyException extends Exception
{}

/**
 * Used when there are problems with sockets created by humpty
 */
class HumptySocketException extends Exception
{}

/**
 * Used when an invalid class is supplied
 */
class InvalidClassException extends Exception
{}