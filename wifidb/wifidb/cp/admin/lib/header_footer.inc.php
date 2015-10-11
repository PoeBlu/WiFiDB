<?php
/*
Database.inc.php, holds the database interactive functions.
Copyright (C) 2011 Phil Ferland

This program is free software; you can redistribute it and/or modify it under the terms
of the GNU General Public License as published by the Free Software Foundation; either
version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program;
if not, write to the

   Free Software Foundation, Inc.,
   59 Temple Place, Suite 330,
   Boston, MA 02111-1307 USA
*/


function admin_pageheader($page = '')
{
	global $login_check;

	$ver 		=	$GLOBALS['ver']['wifidb'];
	$root		= 	$GLOBALS['root'];
	$hosturl	= 	$GLOBALS['hosturl'];
	$conn		=	$GLOBALS['conn'];
	$db			=	$GLOBALS['db'];
	$head		= 	$GLOBALS['header'];
	$half_path	=	$GLOBALS['half_path'];
	$mode = $GLOBALS['mode'];
	$func = $GLOBALS['func'];

	include_once($half_path.'/lib/database.inc.php');
	include_once($half_path.'/lib/security.inc.php');
	include_once($half_path.'/lib/config.inc.php');

	$sec = new security();

	?>
	<html>
		<head>
			<title>Wireless DataBase <?php echo $ver;?> --&#60; Administrator Control Panel --&#60; <?php echo $page;?></title>
			<?php echo $head;?>
		</head>
	<?php

	#check_install_folder();
	# START YOUR HTML EDITS HERE #
	$filtered = $_SERVER['QUERY_STRING'];
	$SELF = $_SERVER['PHP_SELF'];
	if($SELF == '/wifidb/login.php')
	{
		$SELF = "/$root/";
		$filtered = '';
	}
	if($filtered != '')
	{$SELF = $SELF.'?'.$filtered;}
	?>
	<link rel="stylesheet" href="<?php echo $GLOBALS['UPATH']; ?>/themes/wifidb/styles.css">
	<body topmargin="10" leftmargin="0" rightmargin="0" bottommargin="10" marginwidth="10" marginheight="10">
	<div align="center">
	<table border="0" width="100%" cellspacing="5" cellpadding="2">
		<tr style="background-color: #315573;"><td colspan="2">
		<table width="100%">
			<tr>
				<td style="width: 215px">
					&nbsp;&nbsp;&nbsp;&nbsp;<a href="http://www.randomintervals.com"><img border="0" src="<?php echo $hosturl.$root; ?>/img/logo.png"></a>
				</td>
				<td>
					<p align="center"><b>
					<font style="size: 5;font-family: Arial;color: #FFFFFF;">
					Wireless DataBase <?php echo $ver;?> <br /><br /></font>
					<font style="font-size: medium;text-decoration: underline;color: #FFFFFF;">
					<strong><em>Administrator Control Panel Page</em></strong></b></p>
				</td>
			</tr>
		</table>
		</td></tr>
		<tr>
			<td style="background-color: #304D80;width: 15%;vertical-align: top;">
			<img alt="" src="<?php echo $GLOBALS['UPATH']; ?>/themes/wifidb/img/1x1_transparent.gif" width="185" height="1" /><br>
			<span class="content_head"><strong><em><a class="links_s" href="<?php echo $GLOBALS['UPATH']; ?>/">[WiFIDB]</a></em></strong></span><br>
			<span class="content_head"><strong><em><a class="links_s" href="<?php echo $GLOBALS['UPATH']; ?>/caches.php">[Mytsticache]</a></em></strong></span><br>
		</td>
		<td style="background-color: #A9C6FA;width: 80%;vertical-align: top;" align="center">
		<table width="100%">
			<tr>
				<?php
				$user_logins_table = $GLOBALS['user_logins_table'];
				list($cookie_pass_seed, $username) = explode(':', $_COOKIE['WiFiDB_admin_login_yes']);
				$sql0 = "SELECT * FROM `$db`.`$user_logins_table` WHERE `username` = '$username' LIMIT 1";
				$result = mysql_query($sql0, $conn);
				$newArray = mysql_fetch_array($result);
				$last_login = $newArray['last_login'];

				?>
				<td>Welcome, <a class="links" href="javascript:confirmation()"><?php echo $username;?></a><font size="1"> (Last Login: <?php echo $last_login;?>)</font></td>
				<td align="right"><a class="links" href="<?php echo $GLOBALS['UPATH']; ?>/login.php?func=logout_proc&a_c=1">Logout</a></td>
			</tr>
		</table>
		<table width="100%" align="center" border='1'>
			<tr class="sub_header">
				<td align="center" width="25%" class="<?php if($func == 'overview' or $func == ''){echo 'cp_select_column';}else{echo 'dark';} ?>" >&nbsp;&nbsp;<a class="links<?php if($func == 'overview' or $func == ''){echo '_s';}?>" href="?func=overview">Overview</a>&nbsp;&nbsp;</td>
				<td align="center" width="25%" class="<?php if($func == 'uandp'){echo 'cp_select_column';}else{echo 'dark';} ?>" >&nbsp;&nbsp;<a class="links<?php if($func == 'uandp'){echo '_s';}?>" href="?func=uandp">Users and Permissions</a>&nbsp;&nbsp;</td>
				<td align="center" width="25%" class="<?php if($func == 'maint'){echo 'cp_select_column"';}else{echo 'dark';} ?>" >&nbsp;&nbsp;<a class="links<?php if($func == 'maint'){echo '_s';}?>" href="?func=maint">Maintenance</a>&nbsp;&nbsp;</td>
				<td align="center" width="25%" class="<?php if($func == 'system'){echo 'cp_select_column';}else{echo 'dark';} ?>" >&nbsp;&nbsp;<a class="links<?php if($func == 'system'){echo '_s';}?>" href="?func=system">System</a>&nbsp;&nbsp;</td>
			</tr>
			<?php
			$mode = strtolower($mode);
			$func = strtolower($func);
			set_flow($func, $mode);
			?>
		</table>
		<p align="center">
		<br>
	<!-- KEEP BELOW HERE -->
	<?php
}


#========================================================================================================================#
#									Footer (writes the footer for all pages)								#
#========================================================================================================================#

function admin_footer($filename = '', $output = "detailed")
{
	$half_path	=	$GLOBALS['half_path'];
	include_once($half_path.'/lib/database.inc.php');
	include_once($half_path.'/lib/config.inc.php');
	$root = $GLOBALS['root'];
	$tracker = $GLOBALS['tracker'];
	$ads = $GLOBALS['ads'];
	$file_ex = explode("/", $filename);
	$count = count($file_ex);
	$filename_1 = $file_ex[($count)-1];
	if($output == "detailed")
	{
		?>
		</p>
		<br>
		</td>
		</tr>
		<tr>
		<td bgcolor="#315573" height="23"></td>
		<td bgcolor="#315573" width="0" align="center">
		<?php
		if (file_exists($filename_1))
		{
		?>
			<h6><i><u><?php echo $filename_1;?></u></i> was last modified:  <?php echo date ("Y F d @ H:i:s", getlastmod());?></h6>
		<?php
		}
		?>
		</td>
		</tr>
		<tr>
		<td></td>
		<!--  ADS AND TRACKER" -->
		<td align="center">
		<?php
		echo $tracker;
		echo $ads;
		?>
		</td>
		<!-- END ADS AND TRACKER" -->
		</tr>
		</table>
		</body>
		</html>
		<?php
	}
}

?>