<?php
//last edited -> 2015-Apr-01
global $daemon_config;
$daemon_config = array(

	// I dont think this is used any more.. But lets keep it around until I do an audit to see if it is really needed
	'dim' => DIRECTORY_SEPARATOR,

	//Defaults for unclaimed imports
	'default_user'	=> 'WiFiDB',
	'default_title'	=> 'Recovery',
	'default_notes'	=> 'WiFiDB Recovery run by an administrator.',

	//path to the folder that wifidb is installed in default is /var/www/wifidb/ , because I use Debian. fuck windows
	'wifidb_install'		=>	'/wifidb/www/wifidb/',

	//Another one that I think is not used anymore, but will wait to see if it is or not..
	'console_line_limit'	=>	3000,

	//Same as above...
	'console_trim_log'	  =>	1,

	//Well... Im pretty sure this is self explanatory... 1 it will delete the dead pids in the [pid_file_loc]/wifidb/ path.
	'DeleteDeadPids'		=>	1,

	//Enable the Node Syncing commands to sync the filesystem data between the WiFiDB Nodes.
	'NodeSyncing'		=> 0,

	//Location of the wifidb folder for the pids of the daemons.
	'pid_file_loc'		  =>	'/wifidb/run/',

	//Path for the daemon logs to go and take a dump in
	'daemon_log_folder'	 =>	'/wifidb/code/tools/log/',

	// DANGER The Number of import processes its equal to ho much you want to kill your system.
	// Really though, For a 8 CPU, 16GB test environment, 20 threads used 90% CPU for MariaDB and about 4GB of RAM at about 340,000 Access Points and their signal/GPS history.
	// If you have a smaller system (1-2CPU 4GB of RAM) running WiFiDB, I would recommend no more than 2-4 processes.
	'NumberOfThreads'	   =>  20,

	// Each Import 'Node' is an independant WiFiDB install that has its SQL Database syncing with the other nodes. The wifidb_nodename helps keep track of which server did the importing.
	'wifidb_nodename'	   =>  '1',

	//Word used to set and search for import/export schedules
	'status_running'		=>  'Running',

	//Word used to set and search for import/export schedules
	'status_waiting'		=>  'Waiting',

	//IF you are running windows you need to define the install path to the PHP binary, this is so the daemon can restart itself every once and a while.
	'php_install'	=>	'C:\\program files\\php5\\',

	//In seconds: 1800 = 30 min interval
	// Sleep for the Import/Export Daemon
	'time_interval_to_check'	=>	1800,

	// Database Statistics Daemon sleep, really should be at once a day (86400 seconds) if you have a very large database.
	'DBSTATS_time_interval_to_check' => 86400,

	//The level that you want the log file to write, off (0), Errors only (1), Detailed Errors [when available] (2). That is all for now.
	'log_level'	=>	1,

	//0, no out put STUF; 1, let me see the world.
	'verbose'	=>	1,

	//if you want the CLI output to be color coded 1 => ON, 0 => OFF
	//if you ware running windows, this is disabled for you, so even if you turn it on, its not going to work :-p
	'colors_setting'	=>	1,

	//Default colors for the CLI
	//Allowed colors:
			//LIGHTGRAY, BLUE, GREEN, RED, YELLOW
	'BAD_CLI_COLOR'	=>	'RED',
	'GOOD_CLI_COLOR'	=>	'GREEN',
	'OTHER_CLI_COLOR'	=>	'YELLOW',

	//Debug functions turned on, may also include dropping tables and re-createing them
	//so only turn on if you really know what you are doing
	'debug' => 0
);