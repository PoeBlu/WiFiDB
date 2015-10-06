<?php
define("SWITCH_SCREEN", "CLI");
define("SWITCH_EXTRAS", "cli");

if(!(require('../config.inc.php'))){die("You need to create and configure your config.inc.php file in the [tools dir]/daemon/config.inc.php");}
if($daemon_config['wifidb_install'] == ""){die("You need to edit your daemon config file first in: [tools dir]/daemon/config.inc.php");}
require $daemon_config['wifidb_install']."/lib/init.inc.php";

if(!file_exists($GLOBALS['daemon_log_folder']))
{
	if(mkdir($GLOBALS['daemon_log_folder']))
	{echo "Made WiFiDB Log Folder [".$GLOBALS['daemon_log_folder']."]\r\n";}
	else{echo "Could not make Log Folder [".$GLOBALS['daemon_log_folder']."]\r\n";}
}
if(!file_exists($GLOBALS['pid_file_loc']))
{
	if(mkdir($GLOBALS['pid_file_loc']))
	{echo "Made WiFiDB PID Folder [".$GLOBALS['pid_file_loc']."]\r\n";}
	else{echo "Could not make PID Folder [".$GLOBALS['pid_file_loc']."]\r\n";}
}

###########################
$PHP_OS							=	PHP_OS;
$OS								=	$PHP_OS[0];
$conn							= 	$GLOBALS['conn'];
$db								= 	$GLOBALS['db'];
$db_st							= 	$GLOBALS['db_st'];
$wtable							=	$GLOBALS['wtable'];
$users_t						=	$GLOBALS['users_t'];
$gps_ext						=	$GLOBALS['gps_ext'];
$files							=	$GLOBALS['files'];
$user_logins_table				=	$GLOBALS['user_logins_table'];
$root							= 	$GLOBALS['root'];
$half_path						=	$GLOBALS['half_path'];
$WFDBD_PID						=	$GLOBALS['pid_file_loc'].'imp_expd.pid';
$WFDBD_STATS_PID				=	$GLOBALS['pid_file_loc'].'dbstatsd.pid';
$WFDBD_GEON_PID					=	$GLOBALS['pid_file_loc'].'geonamed.pid';
$pid_file						=	$GLOBALS['pid_file_loc'].'daemonperfd.pid';
$This_is_me 					=	getmypid();
$verbose						=	$GLOBALS['verbose'];
$screen_output					=	$GLOBALS['screen_output'];
$PERF_time_interval_to_check	=	$GLOBALS['PERF_time_interval_to_check'];
$enable_mail_users				=	0;
$date_format					=	"Y-m-d H:i:s.u";
$BAD_CLI_COLOR					=	$GLOBALS['BAD_CLI_COLOR'];
$GOOD_CLI_COLOR					=	$GLOBALS['GOOD_CLI_COLOR'];
$OTHER_CLI_COLOR				=	$GLOBALS['OTHER_CLI_COLOR'];
$subject						=	"WiFiDB Daemon Performance Monitor Updates";
$type							=	"perfmon";
###########################
if($GLOBALS['colors_setting'] == 0 or $OS == "W")
{
	$COLORS = array(
					"LIGHTGRAY"	=> "",
					"BLUE"		=> "",
					"GREEN"		=> "",
					"RED"		=> "",
					"YELLOW"	=> ""
					);
}else
{
	$COLORS = array(
					"LIGHTGRAY"	=> "\033[0;37m",
					"BLUE"		=> "\033[0;34m",
					"GREEN"		=> "\033[0;32m",
					"RED"		=> "\033[0;31m",
					"YELLOW"	=> "\033[1;33m"
					);
}
###########################
fopen($pid_file, "w");
$fileappend = fopen($pid_file, "a");
$write_pid = fwrite($fileappend, "$This_is_me");
if(!$write_pid){die($GLOBALS['COLORS'][$BAD_CLI_COLOR]."Could not write pid file, thats not good... >:[".$GLOBALS['COLORS'][$OTHER_CLI_COLOR]);}
###########################
#wait for MySQL to become responsive to the script...
$sql_stat = mysql_stat($conn);
echo $sql_stat."\r\n";
$sql_stat_7 = substr($sql_stat, 0, 6);
echo $sql_stat_7."\r\n";
while($sql_stat_7 != "Uptime")
{
	$sql_stat = mysql_stat($conn);
	$sql_stat_7 = substr($sql_stat, 0, 6);
	#echo $sql_stat_7."\r\n";
}
###########################
###########################
verbosed($GLOBALS['COLORS'][$GOOD_CLI_COLOR]."
WiFiDB 'Daemon Performance Montitor'
Version: 1.0.0
- Daemon Start: 2009-12-07
- Last Daemon File Edit: 2010-06-22
( /tools/daemon/daemonperfd.php )
- By: Phillip Ferland ( pferland@randomintervals.com )
- http://www.randomintervals.com/wifidb/

PID: [ $This_is_me ]
".$GLOBALS['COLORS'][$OTHER_CLI_COLOR], $verbose, $screen_output, 1);

while(TRUE)
{
	$date	=	date("Y-m-d G:i:s");
	if ( $OS == 'L')
	{
		$os_type = "Linux Based WiFiDB Daemon";
		$output = array();
#		echo $WFDBD_PID."\r\n";
		if(file_exists($WFDBD_PID))
		{
			$pid_open = file($WFDBD_PID);
			exec('ps v '.$pid_open[0] , $output, $sta);
			if(isset($output[1]))
			{
				$start = trim($output[1], " ");
				preg_match_all("/(\d+?)(\.)(\d+?)/", $start, $match);
				$mem = $match[0][0];
				
				preg_match_all("/(php.*)/", $start, $matc);
				$CMD = $matc[0][0];
				
				preg_match_all("/(\d+)(\:)(\d+)/", $start, $mat);
				$time = $mat[0][0];
				
				$patterns[1] = '/  /';
				$patterns[2] = '/ /';
				$ps_stats = preg_replace($patterns , "|" , $start);
				$ps_Sta_exp = explode("|", $ps_stats);
				$pid = str_replace(' ?',"",$ps_Sta_exp[0]);
				
				$insert = "INSERT INTO `$db`.`daemon_perf_mon` (`id`, `timestamp`, `pid`, `uptime`, `CMD`, `mem`, `mesg`) VALUES ('', '$date', '$pid', '$time', '$CMD', '$mem', '')";
			#	echo $insert."\r\n";
				if(!$result = mysql_query($insert, $conn))
				{
					mail_users("There was an error inserting the Daemon Performance data into the `daemon_perf_mon` table. :-(\r\n".mysql_error($conn)."\r\n\r\n-WiFiDB Service", $subject, $type, 1);
					verbosed($GLOBALS['COLORS'][$BAD_CLI_COLOR]."Success!".$GLOBALS['COLORS'][$OTHER_CLI_COLOR], $verbose, $screen_output, 1);
				}else{
					verbosed($GLOBALS['COLORS'][$GOOD_CLI_COLOR]."Success!".$GLOBALS['COLORS'][$OTHER_CLI_COLOR], $verbose, $screen_output, 1);
				}
			}else
			{
				$insert = "INSERT INTO `$db`.`daemon_perf_mon` (`id`, `timestamp`, `pid`, `uptime`, `CMD`, `mem`, `mesg`) VALUES ('', '$date', '00000', '0', '', '0.00', 'NOT RUNNING!')";
			#	echo $insert."\r\n";
				if(!$result = mysql_query($insert, $conn))
				{
					mail_users("There was an error inserting the Daemon Performance data into the `daemon_perf_mon` table. \r\nAlso, The Daemon is configured to run, but is not!. Fix it quick before imports start to pile up on the lawn. :-(\r\n".mysql_error($conn)."\r\n\r\n-WiFiDB Service", $subject, $type, 1);
					verbosed($GLOBALS['COLORS'][$GOOD_CLI_COLOR]."FAILURE!\r\n".mysql_error($conn).$GLOBALS['COLORS'][$OTHER_CLI_COLOR], $verbose, $screen_output, 1);
				}else{
					mail_users("The Daemon is configured to run, but is not!. Fix it quick before imports start to pile up on the lawn. :-(", $subject, $type, 1);
					verbosed($GLOBALS['COLORS'][$GOOD_CLI_COLOR]."Success!".$GLOBALS['COLORS'][$OTHER_CLI_COLOR], $verbose, $screen_output, 1);
				}
			}
		}else
		{
			$insert = "INSERT INTO `$db`.`daemon_perf_mon` (`id`, `timestamp`, `pid`, `uptime`, `CMD`, `mem`, `mesg`) VALUES ('', '$date', '00000', '0', '', '0.00', 'NOT RUNNING!')";
		#	echo $insert."\r\n";
			if(!$result = mysql_query($insert, $conn))
			{
				mail_users("There was an error inserting the Daemon Performance data into the `daemon_perf_mon` table.\r\nAlso, The Daemon is configured to run, but is not!. Fix it quick before imports start to pile up on the lawn. :-(\r\n".mysql_error($conn)."\r\n\r\n-WiFiDB Service", $subject, $type, 1);
				verbosed($GLOBALS['COLORS'][$GOOD_CLI_COLOR]."FAILURE!\r\n".mysql_error($conn).$GLOBALS['COLORS'][$OTHER_CLI_COLOR], $verbose, $screen_output, 1);
			}
			else{
				mail_users("The Daemon is configured to run, but is not!. Fix it quick before imports start to pile up on the lawn. :-(\r\n\r\n-WiFiDB Service", $subject, $type, 1, 1);
				verbosed($GLOBALS['COLORS'][$GOOD_CLI_COLOR]."Success!".$GLOBALS['COLORS'][$OTHER_CLI_COLOR], $verbose, $screen_output, 1);
			}
		}
	}elseif( $OS == 'W')
	{
		$output = array();
		if(file_exists($WFDBD_PID))
		{
			$pid_open = file($WFDBD_PID);
			exec('tasklist /V /FI "PID eq '.$pid_open[0].'" /FO CSV' , $output, $sta);
			if(isset($output[2]))
			{
				$ps_stats = explode("," , $output[2]);
				
				$proc =  str_replace('"',"",$ps_stats[0]);
				$pid =  str_replace('"',"",$ps_stats[1]);
				$mem =  str_replace('"',"",$ps_stats[4]).','.str_replace('"',"",$ps_stats[5]);
				$time =  str_replace('"',"",$ps_stats[8]);

				$insert = "INSERT INTO `$db`.`daemon_perf_mon` (`id`, `timestamp`, `pid`, `uptime`, `CMD`, `mem`, `mesg`) VALUES ('', '$date', '$pid', '$time', '$proc', '$mem', '')";
				if(!$result = mysql_query($insert, $conn))
				{
					mail_users("There was an error inserting the Daemon Performance data into the `daemon_perf_mon` table. :-(\r\n".mysql_error($conn)."\r\n\r\n-WiFiDB Service", $subject, $type, 1);
					verbosed($GLOBALS['COLORS'][$GOOD_CLI_COLOR]."FAILURE!\r\n".mysql_error($conn).$GLOBALS['COLORS'][$OTHER_CLI_COLOR], $verbose, $screen_output, 1);
				}				
			}else
			{
				$insert = "INSERT INTO `$db`.`daemon_perf_mon` (`id`, `timestamp`, `pid`, `uptime`, `CMD`, `mem`, `mesg`) VALUES ('', '', '00000', '0', '', '0.00', 'NOT RUNNING!')";
				if(!$result = mysql_query($insert, $conn))
				{
					mail_users("There was an error inserting the Daemon Performance data into the `daemon_perf_mon` table. 
Also, The Daemon is configured to run, but is not!. Fix it quick before imports start to pile up on the lawn. :-(\r\n".mysql_error($conn), $subject, $type, 1, 1); 
					verbosed($GLOBALS['COLORS'][$GOOD_CLI_COLOR]."FAILURE!\r\n".mysql_error($conn).$GLOBALS['COLORS'][$OTHER_CLI_COLOR], $verbose, $screen_output, 1);
				}else{
					mail_users("The Daemon is configured to run, but is not!. Fix it quick before imports start to pile up on the lawn. :-(", $subject, $type, 1, 1); 
					verbosed($GLOBALS['COLORS'][$GOOD_CLI_COLOR]."DAEMON NOT RUNNING FAILURE!\r\n".mysql_error($conn).$GLOBALS['COLORS'][$OTHER_CLI_COLOR], $verbose, $screen_output, 1);
				}
			}
		}else
		{
			$insert = "INSERT INTO `$db`.`daemon_perf_mon` (`id`, `timestamp`, `pid`, `uptime`, `CMD`, `mem`, `mesg`) VALUES ('', '', '00000', '0', '', '0.00', 'NOT RUNNING!')";
			if(!$result = mysql_query($insert, $conn))
			{
				mail_users("There was an error inserting the Daemon Performance data into the `daemon_perf_mon` table. 
Also, The Daemon is configured to run, but is not!. Fix it quick before imports start to pile up on the lawn. :-(\r\n".mysql_error($conn), $subject, $type, 1, 1); 
				verbosed($GLOBALS['COLORS'][$GOOD_CLI_COLOR]."FAILURE!\r\n".mysql_error($conn).$GLOBALS['COLORS'][$OTHER_CLI_COLOR], $verbose, $screen_output, 1);
			}else{
				mail_users("The Daemon is configured to run, but is not!. Fix it quick before imports start to pile up on the lawn. :-(", $subject, $type, 1, 1); 
				verbosed($GLOBALS['COLORS'][$GOOD_CLI_COLOR]."DAEMON NOT RUNNING FAILURE!\r\n".mysql_error($conn).$GLOBALS['COLORS'][$OTHER_CLI_COLOR], $verbose, $screen_output, 1);
			}
		}
	}else
	{
		$insert = "INSERT INTO `$db`.`daemon_perf_mon` (`id`, `timestamp`, `pid`, `uptime`, `CMD`, `mem`, `mesg`) VALUES ('', '', '00000', '0', '', '0.00', 'NOT RUNNING!')";
		if(!$result = mysql_query($insert, $conn))
		{
			mail_users("There was an error inserting the Daemon Performance data into the `daemon_perf_mon` table. 
Also, The Daemon is configured to run, but is not!. Fix it quick before imports start to pile up on the lawn. :-(\r\n".mysql_error($conn), $subject, $type, 1, 1); 
			verbosed($GLOBALS['COLORS'][$GOOD_CLI_COLOR]."FAILURE!\r\n".mysql_error($conn).$GLOBALS['COLORS'][$OTHER_CLI_COLOR], $verbose, $screen_output, 1);
		}else{
			mail_users("The Daemon is configured to run, but is not!. Fix it quick before imports start to pile up on the lawn. :-(", $subject, $type, 1, 1); 
			verbosed($GLOBALS['COLORS'][$GOOD_CLI_COLOR]."DAEMON NOT RUNNING FAILURE!\r\n".mysql_error($conn).$GLOBALS['COLORS'][$OTHER_CLI_COLOR], $verbose, $screen_output, 1);
		}
	}
	echo $date." -> Daemon Performance Monitor is going to sleep for ".($PERF_time_interval_to_check/60)." Minuets\r\n";
	echo $COLORS["LIGHTGRAY"];
	sleep($PERF_time_interval_to_check);
}
?>