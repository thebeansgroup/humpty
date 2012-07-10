<?php
/**
 * Class for utilities that help Humpty work with symfony projects
 * 
 * @author al
 *
 * @todo correct the WEB_ROOT constant before deploying
 */
class SymfonyHumptyUtils
{
  const WEB_ROOT = '/var/www/html';
  //const WEB_ROOT = '/var/www/html/development/projects/sbeans-symfony/branch/webroot';
  
  /**
   * Identifies that we should work with all modules, not just a specific one
   */
  const ALL_MODULES = 'all';

  /**
   * Returns the path to the directory that should contain the named project
   * 
   * @param string $project The name of a project
   * @param boolean $staging - whether we refer to the staging environment
   * @return string The project directory path
   */
  public static function getProjectDirectory($project, $staging = false)
  {
    $stagingPath = $staging ? '/staging' : '';

    return self::WEB_ROOT . "$stagingPath/$project";
  }

  /**
   * Validates that a project name corresponds to the name of a valid project. A valid
   * project name is the name of a symfony directory within /var/www/html
   * 
   * @param string $project The name of a symfony project to look for
   * @param boolean $staging - whether we refer to the staging environment
   * @return boolean True if the project name is valid
   */
  public static function validateProjectName($project, $staging = false)
  {
    // make sure the project corresponds to a valid symfony directory in the web root
    $symfonyPath = self::getProjectDirectory($project, $staging) . "/symfony";

    return (file_exists($symfonyPath) && is_file($symfonyPath));
  }

  /**
   * Returns the path to an application in a given project
   *
   * @param string $project The project name
   * @param string $application The application name
   * @param boolean $staging - whether we refer to the staging environment
   * @return string The application directory path
   */
  public static function getApplicationDirectory($project, $application, $staging = false)
  {
    return self::getProjectDirectory($project, $staging) . "/apps/$application";
  }

  /**
   * Validates that an application name corresponds to the name of a valid application
   * within the named symfony project. 
   * 
   * @param string $project The name of a symfony project to look for
   * @param string $application The name of the application to look for
   * @return boolean True if the application name is valid for the named project
   */
  private static function validateApplicationName($project, $application)
  {
    // make sure the project is a valid project
    if (!self::validateProjectName($project))
    {
      throw new UnexpectedValueException("'$project' is not a valid project name");
    }

    $applicationPath = self::getApplicationDirectory($project, $application);

    // make sure the application is a valid directory
    return (file_exists($applicationPath) && is_dir($applicationPath));
  }

  /**
   * Returns the path to a module in a given application and project
   *
   * @param string $project The project name
   * @param string $application The application name
   * @param string $module The name of a module
   * @return string The module directory path
   */
  public static function getModuleDirectory($project, $application, $module)
  {
    return self::getApplicationDirectory($project, $application) . "/modules/$module";
  }

  /**
   * Validates that a module name corresponds to the name of a valid module 
   * within the named symfony project and application. 
   * 
   * @param string $project The name of a symfony project to look for
   * @param string $application The name of the application to look for
   * @param string $module The name of the module to look for
   * @return boolean True if the module name is valid for the named project and application
   */
  public static function validateModuleName($project, $application, $module)
  {
    // make sure the given application is valid 
    if (!self::validateApplicationName($project, $application))
    {
      throw new UnexpectedValueException("'$application' is not a valid application name for project $project");
    }
    
    if ($module == self::ALL_MODULES)
    {
      return true;
    }

    $modulePath = self::getModuleDirectory($project, $application, $module);
    
    return (file_exists($modulePath) && is_dir($modulePath));
  }

  /**
   * Returns an array of names of each modules in an application
   *
   * @param string $project The name of a project
   * @param string $application The name of an application
   * @return array An array of module names
   */
  public static function retrieveAllModulesInApplication($project, $application)
  {
    $moduleDirectory = self::getApplicationDirectory($project, $application) . '/modules/';

    $modules = array();

    foreach (scandir($moduleDirectory) as $file)
    {
      // ignore hiddne directories and . and ..
      if (strpos($file, '.') === 0)
      {
        continue;
      }

      if (is_dir($moduleDirectory . $file))
      {
        $modules[] = $file;
      }
    }

    return $modules;
  }
}