<?php
/*
Init.inc.php, Initialization script for WiFiDB both CLI, Daemon, and HTTP
Copyright (C) 2012 Phil Ferland

This program is free software; you can redistribute it and/or modify it under the terms
of the GNU General Public License as published by the Free Software Foundation; either
version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU General Public License for more details.

ou should have received a copy of the GNU General Public License along with this program;
if not, write to the

   Free Software Foundation, Inc.,
   59 Temple Place, Suite 330,
   Boston, MA 02111-1307 USA
*/


// Show all erorrs with strict santex
//***DEV USE ONLY***
error_reporting(E_ALL | E_STRICT); 
ini_set("screen.enabled", TRUE);
//


date_default_timezone_set('UTC'); //setting the time zone to GMT(Zulu) for internal keeping, displays will soon be customizable for the users time zone
ini_set("memory_limit","2048M"); //lots of objects need lots of memory



set_exception_handler('exception_handler');
$error = 0;
$config = array();

if(!require('config.inc.php'))
{
    if(@WIFIDB_INSTALL_FLAG != "installing")
    {
        $error = 1;
        $error_msg = 'There was no config file found. You will need to install WiFiDB first.<br>
            Please go to /[WiFiDB ROOT]/install/index2.php (The install page) to do that.';
    }
}else
{
    if(@WIFIDB_INSTALL_FLAG != "installing")
    {
        if($GLOBALS['switches']['screen'] == "CLI")
        {
            require $daemon_config['wifidb_install'].'/lib/config.inc.php' ;
        }else
        {
            require 'config.inc.php' ;
        }
        $dsn = $config['srvc'].':host='.$config['host'];
        $options = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        );
        
        $conn = new PDO($dsn, $config['db_user'], $config['db_pwd'], $options);
        
        $sql = "SELECT `table` FROM `wifi`.`settings` WHERE `id` = '1'";
        $res = $conn->query($sql);
        $fetch = $res->fetch(2);

        unset($res);
        unset($conn);
        if($fetch['table'] != 'nextruntime')
        {
            $cwd = getcwd().'/';
            $gen_cwd = $_SERVER['DOCUMENT_ROOT'].$config['root'].'/install/upgrade/';
            if($cwd != $gen_cwd)
            {
                $error =1;
                $error_msg = 'The database is still in an old format, you will need to do an upgrade first.<br>
                    If this database is older than Version 0.20 I would do a <a href="/'.$config['hosturl'].$config['root'].'/install/">Full Fresh Install</a>, After making a backup of all your data.</br>
                    Please go <a href="/'.$config['hosturl'].$config['root'].'/install/upgrade/index.php">/[WiFiDB]/install/upgrade/index.php</a> to do that.';
            }
        }
        unset($fetch);
        unset($gen_cwd);
        unset($cwd);
        unset($sql);
    }
}
if($error)
{
    echo $error_msg;
    die();
}
if( (!@isset($_COOKIE['wifidb_client_check']) || !@$_COOKIE['wifidb_client_timezone']) && !($GLOBALS['switches']['screen'] == "CLI" || $GLOBALS['switches']['extras'] == "API"))
{
    print_js();
    exit();
}else
{
    require $config['wifidb_install'].'lib/config.inc.php' ;
    require $config['wifidb_install'].'lib/sql.inc.php';
    require $config['wifidb_install'].'lib/dbcore.inc.php' ;
    require $config['wifidb_install'].'/lib/languages.inc.php' ;
    require $config['wifidb_install'].'lib/manufactures.inc.php' ;
    require $config['wifidb_install'].'lib/wdbmail.inc.php' ;
    require $config['wifidb_install'].'lib/wdb_xml.inc.php' ;
    require $config['wifidb_install'].'lib/security.inc.php';
    require $config['wifidb_install'].'lib/MAIL5.php';
    switch($GLOBALS['switches']['screen'])
    {
        case "CLI":
            require $config['wifidb_install'].'lib/daemon.inc.php' ;
            require $config['wifidb_tools'].'daemon/config.inc.php';
            require $config['wifidb_tools'].'daemon/wdbcli.inc.php';
            
            switch($GLOBALS['switches']['extras'])
            {
                ####
                case "export":
                    require_once($config['wifidb_install'].'/lib/export.inc.php');
                    $dbcore = new export($config, $daemon_config);
                break;
                case "import":
                    require_once($config['wifidb_install'].'/lib/import.inc.php');
                    $dbcore = new import($config, $daemon_config);
                ####
                case "daemon":
                    require_once($config['wifidb_install'].'/lib/import.inc.php');
                    require_once($config['wifidb_install'].'/lib/export.inc.php');
                    $dbcore = new daemon($config, $daemon_config, new languages($config['wifidb_install']));
                break;
                ####
                case "cli":
                    $dbcore = new wdbcli($config, $daemon_config);
                break;

                default:
                    die("Uknown Switch Modifier Set.");
                    break;
            }
            $dbcore->mail   = new MAIL5();
            $dbcore->cli = 1;
            break;
        ################
        case "HTML":
            if($GLOBALS['switches']['extras'] !== "API")
            {
                require $config['wifidb_install'].'lib/frontend.inc.php';
                $dbcore         = new frontend($config, new languages($config['wifidb_install']));
                $dbcore->mail   = new MAIL5();
                $dbcore->sec->LoginCheck();
            }else
            {
                require $config['wifidb_install'].'lib/api.inc.php';
                $dbcore = new api($config, new languages($config['wifidb_install']));
            }
            $dbcore->cli = 0;
            break;

        Default:
            die("Unknown Switch Set.");
            break;
    }
#done setting up WiFiDB, weather it be the daemon or the web interface, or just plain failing.
}



function exception_handler($err)
{ 
    $trace = array();
    foreach ($err->getTrace() as $a => $b)
        {
        foreach ($b as $c => $d) {
            if ($c == 'args') {
                foreach ($d as $e => $f)
                {
                    if($a === 2)
                    {
                        $trace[$a] = array(strval($a), "*********", $f);
                    }else
                    {
                        $trace[$a] = array(strval($a), $e, $f);
                    }

                }
            } else {
                $trace[$a] = array(strval($a),$c,$d);
            }
        }
    }
    $trace['main'] = array( 'PDOError' =>strval($err->getCode()), 'Message'=>$err->getMessage(),'Code'=>strval($err->getCode()), 'File'=>$err->getFile(), 'Line'=>strval($err->getLine()));
    var_dump($trace);
    exit();
}


function print_js()
{
    ?>
<script type="text/javascript">
    function checkTimeZone()
    {
        var expiredays = 86400;
        var rightNow = new Date();
        var date1 = new Date(rightNow.getFullYear(), 0, 1, 0, 0, 0, 0);
        var date2 = new Date(rightNow.getFullYear(), 6, 1, 0, 0, 0, 0);
        var temp = date1.toGMTString();
        var date3 = new Date(temp.substring(0, temp.lastIndexOf(" ")-1));
        var temp = date2.toGMTString();
        var date4 = new Date(temp.substring(0, temp.lastIndexOf(" ")-1));
        var hoursDiffStdTime = (date1 - date3) / (1000 * 60 * 60);
        var hoursDiffDaylightTime = (date2 - date4) / (1000 * 60 * 60);
        if (hoursDiffDaylightTime == hoursDiffStdTime)
        {
            var exdate=new Date();
            exdate.setDate(exdate.getDate()+expiredays);
            document.cookie="wifidb_client_dst=" +escape("0")+((expiredays==null) ? "" : ";expires=" +exdate.toUTCString());

            var exdate=new Date();
            exdate.setDate(exdate.getDate()+expiredays);
            document.cookie="wifidb_client_check=" +escape("1")+((expiredays==null) ? "" : ";expires=" +exdate.toUTCString());

            var exdate=new Date();
            exdate.setDate(exdate.getDate()+expiredays);
            document.cookie="wifidb_client_timezone=" +escape(hoursDiffStdTime)+((expiredays==null) ? "" : ";expires=" +exdate.toUTCString());
        }
        else
        {
            var exdate=new Date();
            exdate.setDate(exdate.getDate()+expiredays);
            document.cookie="wifidb_client_dst" + "=" +escape("1")+((expiredays==null) ? "" : ";expires=" +exdate.toUTCString());

            var exdate=new Date();
            exdate.setDate(exdate.getDate()+expiredays);
            document.cookie="wifidb_client_check" + "=" +escape("1")+((expiredays==null) ? "" : ";expires=" +exdate.toUTCString());

            var exdate=new Date();
            exdate.setDate(exdate.getDate()+expiredays);
            document.cookie="wifidb_client_timezone=" +escape(hoursDiffStdTime)+((expiredays==null) ? "" : ";expires=" +exdate.toUTCString());
        }
        location.href = '<?php echo $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'];?>';
    }
    </script>
    <body onload = "checkTimeZone();"> </body>
    <?php
}
?>