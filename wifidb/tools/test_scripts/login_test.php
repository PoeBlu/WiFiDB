<?php

define("SWITCH_SCREEN", "cli");
define("SWITCH_EXTRAS", "export");

require('../daemon/config.inc.php');
require( $daemon_config['wifidb_install']."/lib/init.inc.php" );

$dbcore->verbose = 1;
$dbcore->named = 1;
var_dump($dbcore->sec->Login("pferland2", "wire"));
var_dump($dbcore->sec->login_val);
?>