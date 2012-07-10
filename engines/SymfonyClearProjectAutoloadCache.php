<?php
/**
 * Class that clears the symfony minify cache for the named project
 *
 * @author al
 *
 */
class SymfonyClearProjectAutoloadCache extends HumptyBaseEngine
{
  /**
   * Entry point for the engine.
   *
   * @param string $project The name of a project to work with
   * @param string $action The name of an action to perform
   * @param array $parameters An array of parameters to use
   *
   * @todo implement clearing the project-autoload cache
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

    $projectAutoloadCacheFile = SymfonyHumptyUtils::getProjectDirectory($project, $staging) . '/cache/project_autoload.cache';

    if (!file_exists($projectAutoloadCacheFile))
    {
      return;
    }

    // clear the cache
    $this->thread->sendMessage("Executing rm $projectAutoloadCacheFile");
    shell_exec("rm $projectAutoloadCacheFile");
  }
}