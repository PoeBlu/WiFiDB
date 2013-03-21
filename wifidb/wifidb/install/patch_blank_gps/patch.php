<?php
#Database.inc.php, holds the database interactive functions.
#Copyright (C) 2011 Phil Ferland
#
#This program is free software; you can redistribute it and/or modify it under the terms
#of the GNU General Public License as published by the Free Software Foundation; either
#version 2 of the License, or (at your option) any later version.
#
#This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
#without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
#See the GNU General Public License for more details.
#
#You should have received a copy of the GNU General Public License along with this program;
#if not, write to the
#
#   Free Software Foundation, Inc.,
#   59 Temple Place, Suite 330,
#   Boston, MA 02111-1307 USA

$stime = time()*60;
include('../../lib/config.inc.php');
include('../../lib/database.inc.php');
pageheader("Patch (Blank GPS) Page");
?>
</td>
	<td width="80%" bgcolor="#A9C6FA" valign="top" align="center">
		<p align="center">
<table border="1"><tr><th>Status</th><th>Step of Install</th></tr>
<?php
echo '<tr><TH colspan="2">Fix Erroneous Data in '.$ver['wifidb'].'</TH><tr>';
$id 	= array();
$ssid 	= array();
$mac 	= array();
$chan 	= array();
$radio 	= array();
$auth 	= array();
$encry 	= array();

$post_user 	=	$_POST['root_sql_user'];
$post_pwd 	=	$_POST['root_sql_pwd'];

$post_wdb_user 	=	$_POST['wdb_sql_user'];
$post_wdb_pwd 	=	$_POST['wdb_sql_pwd'];

$replace 	=	$_POST['replace'];
$deleteap 	=	$_POST['deleteap'];

if($post_wdb_user != $db_user && $post_wdb_pwd != $db_pwd)
{die("You did not enter the correct Username/Password");}

$conn 	=	mysql_pconnect($host, $post_user, $post_pwd) or die("Unable to connect to SQL server: $host");
mysql_select_db($db,$conn);

$sql0 = "SELECT * FROM $wtable";
$result = mysql_query($sql0, $conn);
while ($newArray = mysql_fetch_array($result))
{
#	echo "<br>ID:".$newArray['id']."<br>SSID: ".$newArray['ssid']."<br>MAC: ".$newArray['mac']."<br>CHAN: ".$newArray['chan']."<br>RADIO: ".$newArray['radio']."<br>ENCRY: ".$newArray['encry']."<br>AUTH: ".$newArray['auth'];
	$id[] 		= $newArray['id'];
	$ssid[] 	= $newArray['ssid'];
    $mac[] 		= $newArray['mac'];
    $chan[] 	= $newArray['chan'];
	$radio[] 	= $newArray['radio'];
	$encry[] 	= $newArray['encry'];
	$auth[] 	= $newArray['auth'];
	$sectype[] 	= $newArray['sectype'];
}
$wtable_c = count($id);

echo '<tr><TH colspan="2">Finished Building Array of All Access Points</th></tr>';
$numAP 		= 	count($id);
$II = 0;
foreach($id as $APFORID)
{
	if($II == ($numAP)){continue;}

	$table = $ssid[$II].'-'.$mac[$II].'-'.$sectype[$II].'-'.$radio[$II].'-'.$chan[$II];
	$table_gps = $table.$gps_ext;
	echo '<tr><td>Checking AP:'.$table.'</td><td>';
	mysql_select_db($db_st,$conn);
	$resultc = mysql_query("SELECT * FROM `$table_gps`", $conn);
	$num_rows = mysql_num_rows($resultc);
#	echo $num_rows;
	$result = mysql_query("SELECT * FROM `$table_gps`", $conn);
	while ($newArray = mysql_fetch_array($result))
	{
#		echo $newArray['lat']." <-Lat<br>";
#		echo $newArray['long']." <-Long<br>";
#		echo $newArray['date']." <-Date<br>";
#		echo $newArray['time']." <-Time<br>";
		if($newArray['lat'] != '')
		{
			continue;
		}elseif($newArray['lat'] == '' && $newArray['long'] != '')
		{
			$gpspoints[] = array(
								"id"	=>	$newArray['id'],
								"lat"	=>	$newArray['lat'],
								"long"	=>	$newArray['long'],
								"sats"	=>	$newArray['sats'],
								"date"	=>	$newArray['date'],
								"time"	=>	$newArray['time']
								);
		}
	}
#	var_dump($gpspoints);
	$gpsfound = count($gpspoints);
	if($gpsfound != 0)
	{
		echo "Found some bad GPS Points<br>";
#		echo $gpsfound.'&'.$num_rows;
		if($gpsfound < $num_rows)
		{
			foreach($gpspoints as $gpspoint)
			{
				$sql3 = "SELECT * FROM $table";
				$result = mysql_query($sql3, $conn);
				while ($newsigArray = mysql_fetch_array($result))
				{
					$sig = $newsigArray['sig'];
					$sig_exp = explode("-", $sig);
			echo "Before repair:<br>";
#					var_dump($sig_exp);
					foreach($sig_exp as $gps_signal)
					{
						$gps_signal_exp = explode(",",$gps_signal);
						if($gps_signal_exp[0] == $gpspoint['id'])
						{
							$gps_and_signal_removed_array[] = array(
												"APid"		=>	$APFORID,
												"sig_row_id"=>	$newsigArray['id'],
												"ssid"		=>	$ssid[$II],
												"mac"		=>	$mac[$II],
												"radio"		=>	$radio[$II],
												"sectype"	=>	$sectype[$II],
												"auth"		=>	$auth[$II],
												"encry"		=>	$encry[$II],
												"chan"		=>	$chan[$II],
												"sig"		=>	$gps_signal_exp[1],
												"gpsid"		=>	$gps_signal_exp[0]
												);
							if($replace==1)
							{
								echo "Replaced With `valid` data<br>";
								$gpsid = $gpspoint['id'];

								$lat = "N 0.0000";
								$long = "E 0.0000";
								$sats = "0";
								$date = "1/1/1971";
								$time = "00:00:00";

								$update_gps1 = "DELETE FROM `$table_gps` WHERE `$table_gps`.`id` = '$gpsid' AND CONVERT(`$table_gps`.`lat` USING utf8) = '' AND CONVERT(`$table_gps`.`long` USING utf8) = '' AND `$table_gps`.`sats` = 0 AND CONVERT(`$table_gps`.`date` USING utf8) = '' AND CONVERT(`$table_gps`.`time` USING utf8) = '' LIMIT 1";
								$update_result1 = mysql_query($update_gps1, $conn);
								if($update_result1)
								{
									echo "GPS Point removed<br>";
								}else{echo mysql_error()."error";}
								$update_gps2 = "INSERT INTO `$table_gps` ( `id` , `lat` , `long` , `sats` , `date` , `time` ) VALUES ( '$gpsid', '$lat', '$long', '$sats', '$date', '$time')";
								$update_result2 = mysql_query($update_gps2, $conn);
								if($update_result1)
								{
									echo "GPS Point rapaired<br>";
								}else{echo mysql_error()."error";}
							}else{
								echo "Removed Erroneous data<br>";
								unset($gps_signal);
								$tb_gps_id = $gpspoint["id"];
								$remove_GPS = "DELETE FROM `$table_gps` WHERE `$table_gps`.`id` = '$tb_gps_id' AND CONVERT(`$table_gps`.`time USING utf8) = '' LIMIT 1";
								$remove_GPS_result = mysql_query($remove_GPS, $conn);
								if($remove_GPS_result){echo "Removed GPS Point from table<br>";}
								else{echo mysql_error()."error";}
							}
						}
						echo "<br>After Repair:<br>";
				#		var_dump($sig_exp);
						$sig_repair = implode("-", $sig_exp);
						$id = $newsigArray['id'];
						$update_sig = "UPDATE `$table` SET `sig`= '$sig_repair'  WHERE `id`='$id'";
						$update_result = mysql_query($update_sig, $conn);
						if($update_result){echo "Fixed Data and entered back in DB";}else{echo mysql_error()."error";}

					}
				}
			}
		}elseif($gpsfound == $num_rows)
		{
			mysql_select_db($db_st,$conn);
			$sql3 = "SELECT * FROM `$table`";
			$result = mysql_query($sql3, $conn);
			while ($newsigArray = mysql_fetch_array($result))
			{
				$sig = $newsigArray['sig'];
				$AP_removed_array[] = array(
											"id"		=>	$APFORID,
											"ssid"		=>	$ssid[$II],
											"mac"		=>	$mac[$II],
											"radio"		=>	$radio[$II],
											"sectype"	=>	$sectype[$II],
											"auth"		=>	$auth[$II],
											"encry"		=>	$encry[$II],
											"chan"		=>	$chan[$II],
											"sig"		=>	$sig
											);
			}
			foreach($gpspoints as $gpspoint)
			{
				if($replace=="on" && $deleteap==NULL)
				{
					mysql_select_db($db_st,$conn);
					$gpsid = $gpspoint['id'];

					$lat = "N 0.0000";
					$long = "E 0.0000";
					$sats = "0";
					$date = "1971-01-01";
					$time = "00:00:00";

					$update_gps1 = "DELETE FROM `$table_gps` WHERE `$table_gps`.`id` = '$gpsid' AND CONVERT(`$table_gps`.`lat` USING utf8) = '' AND CONVERT(`$table_gps`.`long` USING utf8) = '' AND `$table_gps`.`sats` = 0 AND CONVERT(`$table_gps`.`date` USING utf8) = '' AND CONVERT(`$table_gps`.`time` USING utf8) = '' LIMIT 1";
					$update_result1 = mysql_query($update_gps1, $conn);
					if($update_result1)
					{
						echo "GPS Point removed<br>";
					}else{echo mysql_error()."error";}
					$update_gps2 = "INSERT INTO `$table_gps` ( `id` , `lat` , `long` , `sats` , `date` , `time` ) VALUES ( '$gpsid', '$lat', '$long', '$sats', '$date', '$time')";
					$update_result2 = mysql_query($update_gps2, $conn);
					if($update_result1)
					{
						echo "GPS Point rapaired<br>";
					}else{echo mysql_error()."error";}
				}elseif($deleteap=="on" and $replace == NULL)
				{
					mysql_select_db($db,$conn);
					$remove_pointer = "DELETE FROM `$wtable` WHERE `id`='$APFORID'";
					$remove_pointer_result = mysql_query($remove_pointer, $conn);
					if($remove_pointer_result)
					{
						echo "Access Point, Pointer removed<br>";
					}else{echo mysql_error()."error";}
					mysql_select_db($db_st,$conn);
					$remove_AP = "DROP TABLE `$table`";
					$remove_AP_result = mysql_query($remove_AP, $conn);
					if($remove_AP_result)
					{
						echo "Access Point removed<br>";
					}else{echo mysql_error()."error";}
					$remove_gps = "DROP TABLE `$table_gps`";
					$remove_gps_result = mysql_query($remove_gps, $conn);
					if($remove_gps_result)
					{
						echo "Access Point, GPS Points removed<br>";
					}else{echo mysql_error()."error";}
					break;
				}
			}
		}
	}else
		{
			Echo "AP is Clean.";
		}
	echo "</td></tr>";
if (!is_null($gpspoints)){
	foreach ($gpspoints as $i => $value)
	{
		unset($gpspoints[$i]);
	}
	$gpspoints = array_values($gpspoints);
}
$II++;
}
echo "</table><br><table border=\"1\"><tr><th colspan=\"9\">APs that where removed or updated</th></tr><tr><th>ID</th><th>SSID</th><th>MAC</th><th>Radio</th><th>Sectype</th><th>Auth</th><th>Encry</th><th>Chan</th><th>Sig</th></tr>";
if (!is_null($AP_removed_array)){
foreach($AP_removed_array as $AP_removed)
{
	echo '<tr><td>'.$AP_removed["id"].'</td>'
	.'<td>'.$AP_removed["ssid"].'</td>'
	.'<td>'.$AP_removed["mac"].'</td>'
	.'<td>'.$AP_removed["radio"].'</td>'
	.'<td>'.$AP_removed["sectype"].'</td>'
	.'<td>'.$AP_removed["auth"].'</td>'
	.'<td>'.$AP_removed["encry"].'</td>'
	.'<td>'.$AP_removed["chan"].'</td>'
	.'<td>'.$AP_removed["sig"].'</td>';
}
}else
{
echo "<tr><th colspan=\"9\">No Access Points where found that had erroneous data.</td></tr>";
}
echo "</table><br><table border=\"1\"><tr><th colspan=\"11\">GPS and Signal history that was removed or updated</th></tr><tr><th>APID</th><th>Sig row ID</th><th>SSID</th><th>MAC</th><th>Radio</th><th>Sectype</th><th>Auth</th><th>Encry</th><th>Chan</th><th>Sig</th><th>GPSid</th></tr>";
if (!is_null($gps_and_signal_removed_array)){
foreach($gps_and_signal_removed_array as $gps_and_signal_removed)
{
echo '<tr><td>'.$gps_and_signal_remove["APid"].'</td>'
	.'<td>'.$gps_and_signal_remove["sig_row_id"].'</td>'
	.'<td>'.$gps_and_signal_remove["ssid"].'</td>'
	.'<td>'.$gps_and_signal_remove["mac"].'</td>'
	.'<td>'.$gps_and_signal_remove["radio"].'</td>'
	.'<td>'.$gps_and_signal_remove["sectype"].'</td>'
	.'<td>'.$gps_and_signal_remove["auth"].'</td>'
	.'<td>'.$gps_and_signal_remove["encry"].'</td>'
	.'<td>'.$gps_and_signal_remove["chan"].'</td>'
	.'<td>'.$gps_and_signal_remove["sig"].'</td>'
	.'<td>'.$gps_and_signal_remove["gpsid"].'</td></tr>';

}
}else{
echo "<tr><th colspan=\"11\">No Access Points where found that had erroneous data.</td></tr>";
}
echo "</table>";
$etime = time()*60;
$total_run = $etime - $stime;
echo "<h2>Total run time: ".$total_run."<br>";
$filename = $_SERVER['SCRIPT_FILENAME'];
footer($filename);
?>