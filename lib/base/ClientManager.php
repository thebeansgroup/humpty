<?php
/**
 * Allows a class that manages groups of threads to control them. The
 * manageClient method should be called by the client thread in the
 * on_timer method.
 *
 * @author al
 */
interface ClientManager
{
  public function manageClient(HumptyClientThread $thread);
}
