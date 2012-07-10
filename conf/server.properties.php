<?php
/*
    Copyright 2008 studentbeans.com
    All rights reserved.

    This file is part of Humpty.

    Humpty is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Humpty is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Humpty.  If not, see <http://www.gnu.org/licenses/>.

*/


/**
 * Configure which port the daemon should listen on. This must match the setting in
 * the client.properties.php file.
 */
$server = array(
  'address' => 0,
  'port' => 49333,
  'connectionType' => SOCK_STREAM,
  'protocol' => SOL_TCP
);

// settings for the udp discovery server
$discovery = array(
  'address' => '0.0.0.0',
  'port' => 44566
);

// An array of actions. The first key is the name of a project, the second
// is an action name, and the value is an array of parameters.
// In the parameters array, there are 2 reserved keys - 'engine', and 'command'.
// If 'engine' == 'Shell', the shell will be used to execute command 'command'.
// If 'engine' != 'Shell', a php class by that name will be instantiated if it exists in the
// 'engines' directory and
// implements the 'Runnable' interface. It will be invoked with all remaining parameters from
// this array, plus any given in the received message (which will overwrite these defaults).
// The php class will also be given the name of the project and the name of the action.

// if the first key below is 'global', the project to apply the message to must be given in
// the message itself since it could apply to multiple projects.
$actions['global']['symfony-clear-cache'] = array(
  'engine' => 'SymfonyClearCache', 
  'app' => 'frontend', 
  'module' => 'all'
);

$actions['global']['symfony-clear-minify-cache'] = array(
  'engine' => 'SymfonyClearMinifyCache'
);

$actions['global']['symfony-clear-project-autoload-cache'] = array(
  'engine' => 'SymfonyClearProjectAutoloadCache'
);

/*
 * Be incredibly careful allowing the execution of shell commands
 */
$actions['test']['ls'] = array(
  'engine' => 'Shell',
  'command' => '/bin/ls'
);