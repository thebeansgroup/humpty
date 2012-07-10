<?php
/**
 * Class that clears the symfony cache for the named project and module. If the module == 'all',
 * all caches will be cleared for the project. 
 * 
 * @author al
 *
 */
class SymfonyClearCache extends HumptyBaseEngine
{
  /**
   * Entry point for the engine. 
   * 
   * @param string $project The name of a project to work with
   * @param string $action The name of an action to perform
   * @param array $parameters An array of parameters to use
   */
  public function run($project, $action, array $parameters)
  {
    // make sure there is are parameters called 'module' and 'app'.
    if (!isset($parameters['module']) || !isset($parameters['app']))
    {
      throw new InvalidArgumentException("No 'module' or 'app' parameters given.");
    }

    // this variable will tell whether to perform the action on the staging environment
    $staging = false;
    if (isset($parameters['env']) && ($parameters['env'] == 'staging'))
    {
      $staging = true;
    }

    $application = $parameters['app'];
    $module = $parameters['module'];
    
    // validate the parameters
    if (!SymfonyHumptyUtils::validateModuleName($project, $application, $module))
    {
      throw new InvalidArgumentException("$module is not a valid module name for app " . 
        " $application and project $project");
    }

    $cacheDir = SymfonyHumptyUtils::getProjectDirectory($project, $staging) . '/cache/' . $application;

    // if there is no cache directory for the application, just return since there's nothing to do.
    if (!file_exists($cacheDir) || !is_dir($cacheDir))
    {
      return;
    }

    // if we are working with all modules, get the name of all modules in the application
    if ($module == SymfonyHumptyUtils::ALL_MODULES)
    {
      $cacheDirectories = array($cacheDir);
    }
    else
    {
      $cacheDirectories = explode("\n", shell_exec("find " . escapeshellarg($cacheDir) .
        " -name " . escapeshellarg($module) . " -type d"));
    }

    // clear the cache for each module
    if (count($cacheDirectories) == 0)
    {
      continue;
    }

    // loop through all cache directories, clearing all *.cache files
    foreach ($cacheDirectories as $dir)
    {
      if (strlen(trim($dir)) == 0)
      {
        continue;
      }

      // find all directories containing cache files
      $cacheDirs = explode("\n", shell_exec("find " . escapeshellarg($dir) .
        " -name '*\.cache' -type f -printf '%h\n' | uniq"));

      foreach ($cacheDirs as $cacheDir)
      {
        if (!empty($cacheDir) && is_dir($cacheDir))
        {
          // sometimes there are too many files to use rm, so we need to use xargs...
          $this->thread->sendMessage("Executing find " . escapeshellarg($cacheDir) .
            " -maxdepth 1 -name '*\.cache' -type f | sed -e 's/\"/\\\"/g' -e 's/\(.*\)/\"\\1\"/' | xargs rm");
          shell_exec("find " . escapeshellarg($cacheDir) . " -maxdepth 1 -name '*\.cache' -type f " .
            "| sed -e 's/\"/\\\"/g' -e 's/\(.*\)/\"\\1\"/' | xargs rm");
        }
      }
    }    
  }
}