<?php
#========================================================================================================================#
#											Header (writes the Headers for all pages)									 #
#========================================================================================================================#

function pageheader($title, $output="detailed")
{
	global $login_check;
	
	$root		= 	$GLOBALS['root'];
	$hosturl	= 	$GLOBALS['hosturl'];
	$conn		=	$GLOBALS['conn'];
	$db			=	$GLOBALS['db'];
	$head		= 	$GLOBALS['header'];
	$half_path	=	$GLOBALS['half_path'];
	include_once($half_path.'/lib/database.inc.php');
	include_once($half_path.'/lib/security.inc.php');
	include_once($half_path.'/lib/config.inc.php');
	
	$token = session_starter();
	
	$sec = new security();
	
	echo "<html>\r\n<head>\r\n<title>Wireless DataBase".$GLOBALS['ver']['wifidb']." --> ".$title."</title>\r\n".$head."\r\n</head>\r\n";
	check_install_folder();
	if($output == "detailed")
	{
		$login_check = $sec->login_check();
		if(is_array($login_check))
		{
			$login_check = 0;
		}
		# START YOUR HTML EDITS HERE #
		?>
		<link rel="stylesheet" href="<?php if($root != ''){echo '/'.$root;}?>/themes/vistumbler/styles.css">
			<body style="background-color: #145285">
			<table style="width: 90%; " class="no_border" align="center">
				<tr>
					<td>
					<table>
						<tr>
							<td style="width: 228px">
							<a href="http://www.randomintervals.com">
							<img alt="Random Intervals Logo" src="<?php if($root != ''){echo '/'.$root;}?>/themes/vistumbler/img/logo.png" class="no_border" /></a></td>
						</tr>
					</table>

					</td>
				</tr>
			</table>
			<table style="width: 90%" align="center">
				<tr>
					<td style="width: 165px; height: 114px" valign="top">
						<table style="width: 100%" cellpadding="0" cellspacing="0">
							<tr>
								<td style="width: 10px; height: 20px" class="cell_top_left">
									<img alt="" src="<?php if($root != ''){echo '/'.$root;}?>/themes/vistumbler/img/1x1_transparent.gif" width="10" height="1" />
								</td>
								<td class="cell_top_mid" style="height: 20px">
									<img alt="" src="<?php if($root != ''){echo '/'.$root;}?>/themes/vistumbler/img/1x1_transparent.gif" width="185" height="1" />
								</td>
								<td style="width: 10px" class="cell_top_right">
									<img alt="" src="<?php if($root != ''){echo '/'.$root;}?>/themes/vistumbler/img/1x1_transparent.gif" width="10" height="1" />
								</td>
							</tr>
							<tr>
								<td class="cell_side_left">&nbsp;</td>
								<td class="cell_color">
									<div class="inside_dark_header">WiFiDB Links</div>
									<div class="inside_text_bold"><strong>
										<a href="<?php if($root != ''){echo '/'.$root;}?>/?token=<?php echo $token;?>">Main Page</a></strong></div>
									<div class="inside_text_bold"><strong>
										<a href="<?php if($root != ''){echo '/'.$root;}?>/all.php?sort=SSID&ord=ASC&from=0&to=100&token=<?php echo $token;?>">View All APs</a></strong></div>
									<div class="inside_text_bold"><strong>
										<a href="<?php if($root != ''){echo '/'.$root;}?>/import/?token=<?php echo $token;?>">Import</a></strong></div>
									<div class="inside_text_bold"><strong>
										<a href="<?php if($root != ''){echo '/'.$root;}?>/opt/scheduling.php?token=<?php echo $token;?>">Files Waiting for Import</a></strong></div>
									<div class="inside_text_bold"><strong>
										<a href="<?php if($root != ''){echo '/'.$root;}?>/opt/scheduling.php?func=done&token=<?php echo $token;?>">Files Already Imported</a></strong></div>
									<div class="inside_text_bold"><strong>
										<a href="<?php if($root != ''){echo '/'.$root;}?>/opt/scheduling.php?func=daemon_kml&token=<?php echo $token;?>">Daemon Generated kml</a></strong></div>
									<div class="inside_text_bold"><strong>
										<a href="<?php if($root != ''){echo '/'.$root;}?>/console/?token=<?php echo $token;?>">Daemon Console</a></strong></div>
									<div class="inside_text_bold"><strong>
										<a href="<?php if($root != ''){echo '/'.$root;}?>/opt/export.php?func=index&token=<?php echo $token;?>">Export</a></strong></div>
									<div class="inside_text_bold"><strong>
										<a href="<?php if($root != ''){echo '/'.$root;}?>/opt/search.php?token=<?php echo $token;?>">Search</a></strong></div>
									<div class="inside_text_bold"><strong>
										<a href="<?php if($root != ''){echo '/'.$root;}?>/themes/?token=<?php echo $token;?>">Themes</a></strong></div>
									<div class="inside_text_bold"><strong>
										<a href="<?php if($root != ''){echo '/'.$root;}?>/opt/userstats.php?func=allusers&token=<?php echo $token;?>">View All Users</a></strong></div>
									<div class="inside_text_bold"><strong>
										<a class="links" href="http://forum.techidiots.net/forum/viewforum.php?f=47">Help / Support</a></strong></div>
									<div class="inside_text_bold"><strong>
										<a href="<?php if($root != ''){echo '/'.$root;}?>/ver.php?token=<?php echo $token;?>">WiFiDB Version</a></strong></div>
									<br>
									<div class="inside_dark_header">[Mysticache]</div>
									<div class="inside_text_bold"><a class="links" href="<?php if($root != ''){echo $hosturl.$root;}?>/caches.php?token=<?php echo $token;?>">View shared Caches</a></div>
									<?php
									if($login_check)
									{
									?>
									<div class="inside_text_bold"><a class="links" href="<?php if($root != ''){echo $hosturl.$root;}?>/cp/?func=boeyes&boeye_func=list_all">List All My Caches</a></div>
									<?php
									}
									
									?>
								</td>
								<td class="cell_side_right">&nbsp;</td>
							</tr>
							<tr>
								<td class="cell_bot_left">&nbsp;</td>
								<td class="cell_bot_mid">&nbsp;</td>
								<td class="cell_bot_right">&nbsp;</td>
							</tr>
						</table>
					</td>
					<td style="height: 114px" valign="top" class="center">
						<table style="width: 100%" cellpadding="0" cellspacing="0">
							<tr>
								<td style="width: 10px; height: 20px" class="cell_top_left">
									<img alt="" src="<?php if($root != ''){echo '/'.$root;}?>/themes/vistumbler/img/1x1_transparent.gif" width="10" height="1" />
								</td>
								<?php
								if($login_check)
								{
									$user_logins_table = $GLOBALS['user_logins_table'];
									list($cookie_pass_seed, $username) = explode(':', $_COOKIE['WiFiDB_login_yes']);
								#	echo $username."<BR>";
									$sql0 = "SELECT * FROM `$db`.`$user_logins_table` WHERE `username` = '$username' LIMIT 1";
								#	echo $sql0;
									$result = mysql_query($sql0, $conn);
									$newArray = mysql_fetch_array($result);
									$last_login = $newArray['last_login'];
									
									
			#						echo $priv."<BR>";
									?>
									<td class="cell_top_mid" style="height: 20px" align="left">Welcome, <a class="links" href="<?php echo $hosturl.$root; ?>/cp/"><?php echo $username;?></a><font size="1"> (Last Logon: <?php echo $last_login;?>)</font></td>
									<td class="cell_top_mid" style="height: 20px" align="right"><a class="links" href="<?php echo $hosturl.$root; ?>/login.php?func=logout_proc">Logout</a></td>
									<?php
								}else
								{
									$filtered = filter_var($_SERVER['QUERY_STRING'],FILTER_SANITIZE_ENCODED);
									$SELF = $_SERVER['PHP_SELF'];
									if($SELF == '/wifidb/login.php')
									{
										$SELF = "/$root/";
										$filtered = '';
									}
									if($filtered != '')
									{$SELF = $SELF.'?'.$filtered;}
									?>
									<td class="cell_top_mid" style="height: 20px" align="left"></td>
									<td class="cell_top_mid" style="height: 20px" align="right"><a class="links" href="<?php echo $hosturl.$root; ?>/login.php">Login</a></td>
									<?php
								}
								?>
								<td style="width: 10px" class="cell_top_right">
									<img alt="" src="<?php if($root != ''){echo '/'.$root;}?>/themes/vistumbler/img/1x1_transparent.gif" width="10" height="1" />
								</td>
							</tr>
							<tr>
								<td class="cell_side_left">&nbsp;</td>
								<td class="cell_color_centered" align="center" colspan="2">
								<div align="center">
		<?php
	}
}


#========================================================================================================================#
#											Footer (writes the footer for all pages)									 #
#========================================================================================================================#

function footer($filename = '')
{
	?>
							</div>
							<br>
							</td>
							<td class="cell_side_right">&nbsp;</td>
						</tr>
						<tr>
							<td class="cell_bot_left">&nbsp;</td>
							<td class="cell_bot_mid" colspan="2">&nbsp;</td>
							<td class="cell_bot_right">&nbsp;</td>
						</tr>
					</table>
				<div class="inside_text_center" align=center><strong>
				Random Intervals Wireless DataBase<?php echo $GLOBALS['ver']['wifidb'].'<br />'; ?></strong></div>
				<br />
				<?php
				echo $GLOBALS['tracker'];
				echo $GLOBALS['ads']; 
				?>
				</td>
			</tr>
		</table>
	</body>
	</html>
	<?php
}
?>