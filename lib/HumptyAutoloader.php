<?php
/**
 * An auto-loader
 * 
 * @author al
 *
 */
class HumptyAutoloader
{
  /**
   * @var object An instance of this class
   */
  private static $instance;
  
  /**
   * Set up a singleton
   */
  private function __construct()
  {
    $libDirectory = dirname(__FILE__);
    
    $this->classDirectories = array(
      realpath(dirname(__FILE__) . '/../engines')
    );
    
    // add all sub-directories of the current directory to the auto-load array
    foreach (scandir(dirname(__FILE__)) as $file)
    {
      if ($file == '..')
      {
        continue;
      }
      
      $dir = realpath($libDirectory . DIRECTORY_SEPARATOR . $file);
      
      if (is_dir($dir))
      {
        $this->classDirectories[] = $dir;
      }
    }
    
  }

  /**
   * Returns the same instance of this class
   * 
   * @return HumptyAutoloader
   */
  public static function getInstance()
  {
    if (!isset(self::$instance))
    {
      self::$instance = new HumptyAutoloader();
    }

    return self::$instance;
  }
  
  /**
   * Autoloads classes from the array of directories specified in the constructor
   * 
   * @param string $class The name of the class to load
   */
  public function autoload($class)
  {
    // hack to autoload the exception class
    if (strpos($class, 'Exception') == (strlen($class) - strlen('Exception')))
    {
      $class = "Exceptions";
    }
    
    foreach ($this->classDirectories as $dir)
    {
      $classFile = $dir . "/$class.php";

      if (file_exists($classFile))
      {
        require_once($classFile);
        return;
      }
    }
  }
  
  /**
   * Registers the autoloader with spl
   */
  public static function register()
  {
    ini_set('unserialize_callback_func', 'spl_autoload_call');
    
    if (spl_autoload_register(array(self::getInstance(), 'autoload')) === false)
    {
      throw new Exception(sprintf('Unable to register %s::autoload as an autoloading method.', get_class(self::getInstance())));
    }
  }
}
