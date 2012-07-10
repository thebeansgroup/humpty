<?php
/**
 * Class that clears the symfony minify cache for the named project
 * 
 * @author al
 *
 */
class SymfonyClearMinifyCache extends HumptyBaseEngine
{
  /**
   * Entry point for the engine. 
   * 
   * @param string $project The name of a project to work with
   * @param string $action The name of an action to perform
   * @param array $parameters An array of parameters to use
   *
   * @todo implement clearing the cache
   */
  public function run($project, $action, array $parameters)
  {
    // this variable will tell whether to perform the action on the staging environment
    $staging = false;
    if (isset($parameters['env']) && ($parameters['env'] == 'staging'))
    {
      $staging = true;
    }
    
    // validate the parameters
    if (!SymfonyHumptyUtils::validateProjectName($project, $staging))
    {
      throw new InvalidArgumentException("$project is not a valid project name");
    }
    
    $cacheDir = SymfonyHumptyUtils::getProjectDirectory($project, $staging) . '/web/sfMinifyTSPlugin/cache';

    // if there is no minify cache directory, just return since there's nothing to do.
    if (!file_exists($cacheDir) || !is_dir($cacheDir))
    {
      return;
    }

    // clear the cache
    $this->thread->sendMessage("Executing find $cacheDir -maxdepth 1 -name '*\.css' -type f | xargs rm");
    shell_exec("find $cacheDir -maxdepth 1 -name '*\.css' -type f | xargs rm");
    $this->thread->sendMessage("Executing find $cacheDir -maxdepth 1 -name '*\.js' -type f | xargs rm");
    shell_exec("find $cacheDir -maxdepth 1 -name '*\.js' -type f | xargs rm");
    $this->thread->sendMessage("Executing find $cacheDir -maxdepth 1 -name 'minify_*' -type f | xargs rm");
    shell_exec("find $cacheDir -maxdepth 1 -name 'minify_*' -type f | xargs rm");
  }
}