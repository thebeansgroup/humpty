Introduction
------------

Humpty allows remote invocation of configured commands. It works as follows:

The humpty client is invoked, either via the command line, or by including the humpty autoloader and instantiating
a HumptyClient class and calling the 'dispatch' method on it.

The client sends a broadcast message on the network, and all humpty servers will reply with their network addresses.
The client will then connect to each server in turn, and tell it to execute the desired command. If the server is
not configured to execute the desired command, it will report this to the client. When all servers have replied,
either successfully executing commands, or replying that they cannot execute the commands, the client will return.

Installation
------------

Humpty can be installed in any directory. Make sure that if iptables is running on any machines on which you want
to run the humpty server, it is configured to allow tcp traffic to the server and udp traffic to the discovery
service. Port numbers for these services can be found in the conf/server.properties.php file. Using
the default port numbers, you could use the following commands as root:

1) use the following one-liner to easily see the positions of existing rules in your chains. There may
   be a REJECT rule at the end of your chain. Note its index (the first field given using the following
   one-liner). Call this value X 

   /sbin/iptables -L | awk 'BEGIN { FS = "\n"; i=-1; ORS = "" } ;  { line=$1; if (index($1, "target") > 0) {i=0} if (i>-1) { print i ": "; i++} print line "\n" }'

2) Insert new lines before your REJECT rule using the following, but replacing X with your value from
   above:

   iptables -I RH-Firewall-1-INPUT X -m state --state NEW -m tcp -p tcp --dport 49333 -j ACCEPT
   iptables -I RH-Firewall-1-INPUT X -m state --state NEW -m udp -p udp --dport 44561 -j ACCEPT

If you want to save these changes permanently, run the following if you're using Red Hat:

   iptables-save > iptables
   mv iptables /etc/sysconfig

Configuration
-------------

Commands in humpty are arranged by a project and an action. When a client request that an action for a given project
be invoked, the engine configured in conf/server.properties.php is invoked with the name of the project, action and
any extra parameters. Default parameters can be provided in the configuration file, but the client can override them
by supplying them as part of the invocation. The only parameter that can't be overridden is the 'command' parameter
for the 'Shell' engine - this is to stop arbitrary execution of commands remotely.

Grouping actions by project allows default parameters to be easily configured.

The special project 'global' will still invoke the configured engine and pass the name of the project, action and
any parameters supplied by the client. It is down to the engine to decide what to do based on this data. So, for
example, an action can be configured to be part of the 'global' project, but the client must still invoke it with
a project name (e.g. 'website'). This will then get passed into the engine as the project name (instead of 'global').

Engines
-------

Engines are the workhorse of Humpty. Writing one is simple - just create a new class that extends HumptyBaseEngine
(in the lib/base directory) and implement a 'run' method. This method will be invoked with several parameters, and
also has access to a class variable 'thread'. This allows the client to send messages back to the client by calling
'$this->thread->sendMessage' or '$this->thread->sendHeaderMessage'. See HumptyServerThread for the methods that are
available.