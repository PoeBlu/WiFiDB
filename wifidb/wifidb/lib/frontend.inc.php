<?php
/*
Frontend.inc.php, Functions to generate the frontend data and some views..
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


#===========================================================#
#   WiFiDB Database Class that holds DB based functions     #
#===========================================================#
class frontend extends dbcore
{
    #===========================#
    #   __construct (default)   #
    #===========================#
    function __construct($config)
    {
        parent::__construct($config);
        if($GLOBALS['switches']['extras'] != "API")
        {
            require_once($config['wifidb_install'].'/lib/misc.inc.php');
            require_once($config['wifidb_install'].'/lib/manufactures.inc.php');
            
            $this->meta = new stdClass();
            $this->meta->ads = $config['ads'];
            $this->meta->tracker = $config['tracker'];
            $this->meta->header = $config['header'];
            
            define('WWW_DIR', $this->PATH);
            define('SMARTY_DIR', $this->PATH.'/smarty/');
            require_once(SMARTY_DIR.'Smarty.class.php');
            $this->smarty = new Smarty();
            $this->smarty->setTemplateDir( WWW_DIR.'smarty/templates/'.$this->theme.'/' );
            $this->smarty->setCompileDir( WWW_DIR.'smarty/templates_c/' );
            $this->smarty->setCacheDir( WWW_DIR.'smarty/cache/' );
            $this->smarty->setConfigDir( WWW_DIR.'/smarty/configs/');
            
            $this->smarty->assign('wifidb_host_url', $this->URL_PATH);
            $this->smarty->assign('wifidb_meta_header', $this->meta->header);
            $this->smarty->assign('wifidb_theme', $this->theme);
            $this->smarty->assign('wifidb_version_label', $this->ver_array['wifidb']);
            $this->smarty->assign('wifidb_current_uri', '?return='.$_SERVER['PHP_SELF']);
            
            $this->smarty->assign('critical_error_message', '');
            
            $this->smarty->assign("redirect_func", "");
            $this->smarty->assign("redirect_html", "");
            $this->smarty->assign('wifidb_login_label', $this->sec->LoginLabel);
            $this->htmlheader();
            $this->htmlfooter();
        }
        $this->ver_array['Frontend']    =   array(
                                                    "AllUsers"       =>  "1.0",
                                                    "AllUsersAP"     =>  "1.0",
                                                    "dump"           =>  "1.0",
                                                    "GenPageCount"   =>  "1.0",
                                                    "GetAnnouncement"=>  "1.0",
                                                    "HTMLFooter"     =>  "1.0",
                                                    "HTMLHeader"     =>  "1.0",
                                                    "UserAPList"     =>  "1.0",
                                                    "UserLists"      =>  "1.0"
                                                );
    }
    
    function APFetch($id = "")
    {
        $sql = "SELECT * FROM `wifi`.`wifi_pointers` WHERE `id` = ?";
        $prep = $this->sql->conn->prepare($sql);
        $prep->bindParam(1, $id, PDO::PARAM_INT);
        $prep->execute();
        $newArray = $prep->fetch(2);
        $ap_data = array(
            'id'=>$newArray['id'],
            'radio'=>$newArray['radio'],
            'manuf'=>$newArray['manuf'],
            'mac'=>$newArray['mac'],
            'ssid'=>$newArray['ssid'],
            'chan'=>$newArray['chan'],
            'encry'=>$newArray['encry'],
            'auth'=>$newArray['auth'],
            'btx'=>$newArray["BTx"],
            'otx'=>$newArray["OTx"],
            'fa'=>$newArray["FA"],
            'la'=>$newArray["LA"],
            'nt'=>$newArray["NT"],
            'label'=>$newArray["label"],
            'user'=>$newArray["username"]
        );
        
        $sql = "SELECT  `lat` FROM `wifi`.`wifi_signals`, `wifi`.`wifi_gps`
                WHERE `wifi_signals`.`ap_hash` =  ?
                AND `wifi_gps`.`id` =  `wifi_signals`.`gps_id`
                AND `wifi_gps`.`lat` != '0.0000' AND `wifi_gps`.`lat` != 'N 0.0000'
                ORDER BY `wifi_gps`.`date` ASC, `wifi_gps`.`time` DESC LIMIT 1";
        $ap_hash = $newArray["ap_hash"];
        $prep1 = $this->sql->conn->prepare($sql);
        $prep1->bindParam(1, $ap_hash, PDO::PARAM_STR);
        #var_dump($prep1);
        $prep1->execute();
        $lastgps = $prep1->fetch(2);
        if($lastgps !== FALSE)
        {
            $lat_check = explode(" ", $lastgps['lat']);
            $lat_c = $lat_check[1]+0;
            $gps_globe = ($lat_c == "0" ? "off" : "on");
        }else
        {
            $gps_globe = "off";
        }
        
        $sql = "SELECT  `wifi_signals`.`id`, `wifi_signals`.`ap_hash`, `signal`, `rssi`, `username`,
                `lat`, `long`, `sats`, `hdp`, `alt`, `geo`, `kmh`, `mph`,
                `track`, `date`, `time`
                FROM `wifi`.`wifi_signals`, `wifi`.`wifi_gps`
                WHERE `wifi_signals`.`ap_hash` =  ?
                AND `wifi_gps`.`id` =  `wifi_signals`.`gps_id`
                ORDER BY `wifi_gps`.`date` ASC, `wifi_gps`.`time` ASC";
        $prep1 = $this->sql->conn->prepare($sql);
        $prep1->bindParam(1, $newArray["ap_hash"], PDO::PARAM_STR);
        $prep1->execute();
        
        $flip = 0;
        $prev_date = 0;
        $date_range = -1;
        $signal_runs = array();
        $signals = $prep1->fetchAll(2);
            
        foreach($signals as $field)
        {
            if($flip){$class="light";$flip=0;}else{$class="dark";$flip=1;}
            #$sql_gps = "SELECT * FROM `wifi`.`wifi_gps` WHERE `id` = '{$field['id']}'";
            #$gps_res = $this->sql->conn->query($sql_gps);
            #$gps = $gps_res->fetch(2);
            #if($gps['id']==''){continue;}
            if($prev_date < strtotime($field['date']))
            {
                $date_range++;
                $signal_runs[$date_range]['id'] = $date_range;
                $signal_runs[$date_range]['start'] = $field['date']." ".$field['time'];
                $signal_runs[$date_range]['descstart'] = $field['time'];
                $signal_runs[$date_range]['user'] = $field['username'];
            }else
            {
                if($signal_runs[$date_range]['user'] != $field['username'])
                {
                    $signal_runs[$date_range]['user'] .= " and ".$field['username'];
                }
                $signal_runs[$date_range]['desc'] = $field['date'].": ".$signal_runs[$date_range]['descstart']." - ".$field['time'];
                $signal_runs[$date_range]['stop'] = $field['date']." ".$field['time'];
            }

            $prev_date = strtotime($field['date']);

            $signal_runs[$date_range]['gps'][] = array(   
                                    'class'=>$class,
                                    'lat'=>$field["lat"],
                                    'long'=>$field["long"],
                                    'sats'=>$field["sats"],
                                    'date'=>$field["date"],
                                    'time'=>$field["time"],
                                    'signal'=>$field["signal"]
                                );
        }
        
        $list = array();
        $id_find = "%-{$id}:%";
        $prep2 = $this->sql->conn->query("SELECT * FROM `wifi`.`user_imports` WHERE `points` LIKE '{$id_find}'");
        
        while ($field = $prep2->fetch(1))
        {
            if($flip){$class="light";$flip=0;}else{$class="dark";$flip=1;}
            preg_match("/(?P<ap_id>{$id}):(?P<stat>\d+)/", $field['points'], $matches);
            $list[]= array(
                            'class'=>$class,
                            'id'=>$field['id'],
                            'nu'=>$matches['stat'],
                            'date'=>$field['date'],
                            'aps'=>$field['aps'],
                            'username'=>$field['username'],
                            'title'=>$field['title'],
                            'title_id'=>$field['file_id']
                            );

        }
        $this->smarty->assign('wifidb_page_label', "Access Point Page ({$newArray['ssid']})");
        $this->smarty->assign('wifidb_ap_signal_all', $signal_runs);
        $this->smarty->assign('wifidb_assoc_lists', $list);
        $this->smarty->assign('wifidb_ap_globe', $gps_globe);
        $this->smarty->assign('wifidb_ap', $ap_data);
        #var_dump($ap_data);
        #$this->smarty->display('ap.tpl');
        $this->smarty->display('fetch.tpl');
    }
    
    function GetAnnouncement()
    {
        $result = $this->sql->conn->query("SELECT `body` FROM `wifi`.`annunc` WHERE `set` = '1'");
        $array = $result->fetch(2);
        if($this->sql->checkError() || $array['body'] == "")
        {
            return 0;
        }
        return $array;
    }
    
    
    function htmlheader()
    {
        if(@WIFIDB_INSTALL_FLAG != "installing" && $this->sec->login_check)
        {
            $this->smarty->assign("login_val", "1");
            $login_bar = 'Welcome, <a class="links" href="'.$this->URL_PATH.'cp/">'.$this->sec->username.'</a>';
            $wifidb_mysticache_link = 1;
        }else
        {
            $this->smarty->assign("login_val", "0");
            $wifidb_mysticache_link = 0;
            $login_bar = "";
        }
        $this->smarty->assign("install_header", $this->check_install_folder());
        $announc = $this->GetAnnouncement();
        
        $this->smarty->assign("wifidb_announce_header", '<p class="annunc_text">'.$announc['body'].'</p>');
        $this->smarty->assign("wifidb_mysticache_link", $wifidb_mysticache_link);
        $this->login_bar = $login_bar;
        return 1;
    }
    

    function htmlfooter()
    {
        $out = '';
        if($this->sec->login_check)
        {
            if($this->sec->privs >= 1000)
            {
                $out .= '<a class="links" href="'.$this->URL_PATH.'/cp/?func=admin_cp">Admin Control Panel</a>  |-|  ';
            }
            if($this->sec->privs >= 10)
            {
                $out .= '<a class="links" href="'.$this->URL_PATH.'/cp/?func=mod_cp">Moderator Control Panel</a>  |-|  ';
            }
            if($this->sec->privs >= 1)
            {
                $out .= '<a class="links" href="'.$this->URL_PATH.'/cp/">User Control Panel</a>';
            }

        }
        $this->footer .= $this->meta->tracker.$this->meta->ads;
        return 1;
    }
    
    #===================================#
    #   Grab the stats for All Users    #
    #===================================#
    function AllUsers()
    {
        $sql = "SELECT * FROM `{$this->sql->db}`.`{$this->sql->users_t}` ORDER BY `username` ASC";
        $result = $this->sql->conn->query($sql);
        $users = array();
        while ($user_array = $result->fetch(2))
        {
            $users[] = $user_array["username"];
        }
        $users = array_unique($users);
        
        $row_color = 0;
        $this->all_users_data = array();
        foreach($users as $user)
        {
            $this->all_users_data[$user] = array();

            if($row_color == 1)
            {$row_color = 0; $color = "light";}
            else{$row_color = 1; $color = "dark";}
            
            $tablerowid = 0;
            $row_color2 = 1;
            $pre_user = 1;
            
            $sql = "SELECT * FROM `{$this->sql->db}`.`{$this->sql->users_t}` WHERE `username`= ? ORDER BY `id` ASC";
            $result = $this->sql->conn->prepare($sql);
            $result->execute(array($user));
            
            $imports = $result->rowCount();
            while ($user_array = $result->fetch(2))
            {
                if($user_array['points'] === ""){continue;}
                
                $tablerowid++;
                $username = $user_array['username'];
                
                if ($user_array['title'] === "" or $user_array['title'] === " "){ $user_array['title']="UNTITLED";}
                if ($user_array['date'] === ""){ $user_array['date']="No date, hmm..";}
                
                $search = array('\n','\r','\n\r');
                $user_array['notes'] = str_replace($search, "", $user_array['notes']);
                
                if ($user_array['notes'] == ""){ $user_array['notes']="No Notes, hmm..";}
                $notes = $user_array['notes'];
                $points = explode("-",$user_array['points']);
                $pc = count($points);
                
                if($row_color2 == 1)
                {$row_color2 = 0; $color2 = "light";}
                else{$row_color2 = 1; $color2 = "dark";}

                if($pre_user)
                {
                    $this->all_users_data[$user] = array(
                                'rowid' => $tablerowid,
                                'class' => $color,
                                'id' => $user_array['id'],
                                'imports' => $imports,
                                'username' => $username,
                            );
                    $pre_user = 0;
                }
                $this->all_users_data[$user]['data'][] = array(
                                'class' => $color2,
                                'title' => $user_array['title'],
                                'notes' => wordwrap($notes, 56, "<br />\n"),
                                'aps' => $pc,
                                'date' => $user_array['date']
                            );
                
            }
            return 1;
        }
    }

    #=======================================#
    #   Grab All the AP's for a given user  #
    #=======================================#
    function AllUsersAPs($user="")
    {
        if($user == ""){return 0;}
        
        $args = array(
            'ord' => FILTER_SANITIZE_ENCODED,
            'sort' => FILTER_SANITIZE_ENCODED,
            'to' => FILTER_SANITIZE_NUMBER_INT,
            'from' => FILTER_SANITIZE_NUMBER_INT
        );
        
        $inputs = filter_input_array(INPUT_GET, $args);
        
        if($inputs['from'] == ''){$inputs['from'] = 0;}
        if($inputs['to'] == ''){$inputs['to'] = 100;}
        if($inputs['sort'] == ''){$inputs['sort'] = 'id';}
        if($inputs['ord'] == ''){$inputs['ord'] = 'ASC';}
        
        $prep = array();
        $apprep = array();
        $prep['allaps'] = array();
        $prep['username'] = $user;
        
        $sql = "SELECT count(`id`) FROM `{$this->sql->db}`.`{$this->sql->pointers_table}` WHERE `username` = ?";
        $result = $this->sql->conn->prepare($sql);
        $result->execute(array($user));
        $rows = $result->fetch(1);
        $prep['total_aps'] = $rows[0];
        
        $flip = 0;
        $sql = "SELECT `id`,`ssid`,`mac`,`radio`,`auth`,`encry`,`chan`,`lat`, `FA`,`LA` FROM 
                `{$this->sql->db}`.`{$this->sql->pointers_table}` 
                WHERE `username` = ? ORDER BY `{$inputs['sort']}` {$inputs['ord']} LIMIT {$inputs['from']}, {$inputs['to']}";
        
        $result = $this->sql->conn->prepare($sql);
        $result->execute(array($user));
        
        while($array = $result->fetch(2))
        {
            if($flip)
                {$style = "dark";$flip=0;}
            else
                {$style="light";$flip=1;}
            
            if($array['lat'] == "N 0.0000")
                {$globe = "off";}
            else
                {$globe = "on";}
            
            if($array['ssid'] == "")
                {$ssid = "Unknown";}
            else
                {$ssid = $array['ssid'];}
            
            $apprep[] = array(
                        "id" => $array['id'],
                        "class" => $style,
                        "globe" => $globe,
                        "ssid" => $ssid,
                        "mac" => $array['mac'],
                        "radio" => $array['radio'],
                        "auth" => $array['auth'],
                        "encry" => $array['encry'],
                        "chan" => $array['chan'],
                        "fa"   => $array['FA'],
                        "la"   => $array['LA']
                        );
        }
        $prep['allaps'] = $apprep;
        $this->all_users_aps = $prep;
        $this->gen_pages($prep['total_aps'], $inputs['from'], $inputs['to'], $inputs['sort'], $inputs['ord'], 'allap', $user);
        return 1;
    }

    #===================================#
    #   Grab all user Import lists      #
    #===================================#
    function UsersLists($username="")
    {
        if($username == ""){return 0;}
        $total_aps = array();
        $sql = "SELECT * FROM `{$this->sql->db}`.`{$this->sql->users_t}` WHERE `username` LIKE ? ORDER BY `id` DESC LIMIT 1";
        $user_query = $this->sql->conn->prepare($sql);
        $user_query->execute(array($username));
        $user_last = $user_query->fetch(2);
        
        $sql = "SELECT * FROM `{$this->sql->db}`.`{$this->sql->users_t}` WHERE `username` LIKE ? ORDER BY `id` DESC LIMIT 1";
        $user_query = $this->sql->conn->prepare($sql);
        $user_query->execute(array($username));
        $user_first = $user_query->fetch(2);
        
        $sql = "SELECT * FROM `{$this->sql->db}`.`{$this->sql->users_t}` WHERE `username` LIKE ? ORDER BY `id` ASC";
        $user_query = $this->sql->conn->prepare($sql);
        $user_query->execute(array($username));
        
        while($imports = $user_query->fetch(2))
        {
            if($imports['points'] == ""){continue;}
            $points = explode("-",$imports['points']);
            foreach($points as $key=>$pt)
            {
                $pt_ex = explode(":", $pt);
                if($pt_ex[1] == 1)
                {
                    #var_dump($pt_ex);
                    unset($points[$key]);
                }
            }
            $pts_count = count($points);
            $total_aps[] = $pts_count;
        }
        $total = 0;
        if(count(@$total_aps))
        {
            foreach($total_aps as $totals)
            {
                $total += $totals;
            }
            
            $sql = "SELECT * FROM `{$this->sql->db}`.`{$this->sql->users_t}` WHERE `username` LIKE ? AND `id` != ? ORDER BY `id` DESC";
            #echo $sql."\r\n";
            $other_imports = $this->sql->conn->prepare($sql);
            $other_imports->execute(array($username, $user_first['id']));
            $other_rows = $other_imports->rowCount();
            
            #var_dump($other_rows);
            
            if($other_rows > 0)
            {
                #var_dump($other_rows);
                $flip = 0;
                $other_imports_array = array();
                while($imports = $other_imports->fetch(2))
                {
                    #var_dump($imports);
                    if($imports['points'] == ""){continue;}
                    if($flip){$style = "dark";$flip=0;}else{$style="light";$flip=1;}
                    $import_id = $imports['id'];
                    $import_title = $imports['title'];
                    $import_date = $imports['date'];
                    $import_ap = $imports['aps'];
                    
                    $other_imports_array[] = array(
                                                    'class' => $style,
                                                    'id' => $import_id,
                                                    'title' => $import_title,
                                                    'aps' => $import_ap,
                                                    'date' => $import_date
                                                   );
                }
            }
        }
        $this->user_all_imports_data = array();
        $this->user_all_imports_data['user_id'] = $user_first['id'];
        $this->user_all_imports_data['username'] = $user_first['username'];
        $this->user_all_imports_data['first_import_date'] = $user_first['date'];
        $this->user_all_imports_data['total_aps'] = $total;
        
        $this->user_all_imports_data['newest_id'] = $user_last['id'];
        $this->user_all_imports_data['newest_aps'] = $user_last['aps'];
        $this->user_all_imports_data['newest_gps'] = $user_last['gps'];
        $this->user_all_imports_data['newest_title'] = $user_last['title'];
        $this->user_all_imports_data['newest_date'] = $user_last['date'];
        
        $this->user_all_imports_data['other_imports'] = $other_imports_array;
        return 1;
    }

    #===============================================#
    #   Grab the AP's for a given user's Import     #
    #===============================================#
    function UserAPList($row=0)
    {
        if(!$row){return 0;}
        $sql = "SELECT * FROM `{$this->sql->db}`.`{$this->sql->users_t}` WHERE `id`= ?";
        $result = $this->sql->conn->prepare($sql);
        $result->execute(array($row));
        $user_array = $result->fetch(2);
        
        $all_aps_array = array();
        $all_aps_array['allaps'] = array();
        $all_aps_array['username'] = $user_array['username'];
        
        $all_aps_array['notes'] = $user_array['notes'];
        $all_aps_array['title'] = $user_array['title'];
        
        $points = explode("-", $user_array['points']);
        $flip = 0;
        $sql = "SELECT `id`, `ssid`, `mac`, `chan`, `radio`, `auth`, `encry`, `LA`, `FA`, `lat` FROM `{$this->sql->db}`.`{$this->sql->pointers_table}` WHERE `id`= ?";
        $result = $this->sql->conn->prepare($sql);
        $count = 0;
        foreach($points as $ap)
        {
            $ap_exp = explode(":" , $ap);
            $apid = $ap_exp[0];
            
            #if($ap_exp[0] == 0){continue;}
            $count++;
            
            if($flip)
                {$style = "dark";$flip=0;}
            else
                {$style="light";$flip=1;}
            
            if($ap_exp[1] == 1)
            {
                $update_or_new = "Update";
            }else
            {
                $update_or_new = "New";
            }
            $result->execute(array($apid));
            $ap_array = $result->fetch(2);
            
            if($ap_array['lat'] == "N 0.0000")
            {
                $globe = "off";
            }else
            {
                $globe = "on";
            }
            if($ap_array['ssid'] == "")
                {$ssid = "Unnamed";}
            else
                {$ssid = $ap_array['ssid'];}
            $all_aps_array['allaps'][] = array(
                    'id' => $ap_array['id'],
                    'class' => $style,
                    'un' => $update_or_new,
                    'globe' => $globe,
                    'ssid' => $ssid,
                    'mac' => $ap_array['mac'],
                    'chan' => $ap_array['chan'],
                    'radio' => $ap_array['radio'],
                    'auth' => $ap_array['auth'],
                    'encry' => $ap_array['encry'],
                    'fa' => $ap_array['FA'],
                    'la' => $ap_array['LA']
                );
        }
        $all_aps_array['total_aps'] = $count;
        $this->users_import_aps = $all_aps_array;
        return 1;
    }

    #======================#
    #   DUMP VAR TO HTML   #
    #======================#
    function Dump($value="" , $level=0)
    {
        if ($level==-1)
        {
            $trans[' ']='&there4;';
            $trans["\t"]='&rArr;';
            $trans["\n"]='&para;;';
            $trans["\r"]='&lArr;';
            $trans["\0"]='&oplus;';
            return strtr(htmlspecialchars($value),$trans);
        }
        if ($level==0) echo '<pre>';
        $type= gettype($value);
        echo $type;
        if ($type=='string')
        {
            echo '('.strlen($value).')';
            $value= dump($value,-1);
        }
        elseif ($type=='boolean') $value= ($value?'true':'false');
        elseif ($type=='object')
        {
            $props= get_class_vars(get_class($value));
            echo '('.count($props).') <u>'.get_class($value).'</u>';
            foreach($props as $key=>$val)
            {
                echo "\n".str_repeat("\t",$level+1).$key.' => ';
                dump($value->$key,$level+1);
            }
            $value= '';
        }
        elseif ($type=='array')
        {
            echo '('.count($value).')';
            foreach($value as $key=>$val)
            {
                echo "\n".str_repeat("\t",$level+1).dump($key,-1).' => ';
                dump($val,$level+1);
            }
            $value= '';
        }
        echo " <b>$value</b>";
        if ($level==0) echo '</pre>';
        return 1;
    }
    
    function GeneratePages($total_rows, $from, $inc, $sort, $ord, $func="", $user="", $ssid="", $mac="", $chan="", $radio="", $auth="", $encry="")
    {
        if($ssid=="" && $mac=="" && $chan=="" && $radio=="" && $auth=="" && $encry=="")
        {
            $no_search = 0;
        }else
        {
            $no_search = 1;
        }
        
        $function_and_username = "";
        if($func != "")
        {
            $function_and_username = "func=".$func;
        }
        
        if($user != "")
        {
            $function_and_username .= "&amp;user={$user}&amp;";
        }
        
        $pages = ($total_rows/$inc);
        $mid_page = round($from/$inc, 0);
        if($no_search)
        {
            $pages_together = "Pages: &lt;&#45;&#45;  &#91<a class=\"links\" href=\"?{$function_and_username}from=0&to={$inc}&sort={$sort}&ord={$ord}&ssid={$ssid}&mac={$mac}&chan={$chan}&radio={$radio}&auth={$auth}&encry={$encry}\">First</a>&#93 &#45; \r\n";
        }else
        {
            $pages_together = "Pages: &lt;&#45;&#45;  &#91<a class=\"links\" href=\"?{$function_and_username}from=0&to={$inc}&sort={$sort}&ord={$ord}\">First</a>&#93 &#45; \r\n";
        }
        for($I=($mid_page - 5); $I<=($mid_page + 5); $I++)
        {
            if($I <= 0){continue;}
            if($I > $pages){break;}
            $cal_from = ($I*$inc);
            if($I==1)
            {
                $cal_from = $cal_from-$inc;
                if($no_search)
                {
                    $pages_together .= " <a class=\"links\" href=\"?{$function_and_username}from={$cal_from}&to={$inc}&sort={$sort}&ord={$ord}&ssid={$ssid}&mac={$mac}&chan={$chan}&radio={$radio}&auth={$auth}&encry={$encry}\">{$I}</a> &#45; \r\n";
                }else
                {
                    $pages_together .= " <a class=\"links\" href=\"?{$function_and_username}from={$cal_from}&to={$inc}&sort={$sort}&ord={$ord}\">{$I}</a> &#45; \r\n";
                }
            }elseif($mid_page == $I)
            {
                $pages_together .= " <b><i>{$I}</i></b> - \r\n";
            }else
            {
                if($no_search)
                {
                    $pages_together .= " <a class=\"links\" href=\"?{$function_and_username}from={$cal_from}&to={$inc}&sort={$sort}&ord={$ord}&ssid={$ssid}&mac={$mac}&chan={$chan}&radio={$radio}&auth={$auth}&encry={$encry}\">{$I}</a> &#45; \r\n";
                }else
                {
                    $pages_together .= " <a class=\"links\" href=\"?{$function_and_username}from={$cal_from}&to={$inc}&sort={$sort}&ord={$ord}\">{$I}</a> &#45; \r\n";
                }
            }
        }
        $pages_together .= " &#91<a class=\"links\" href=\"?{$function_and_username}from=".(($pages*$inc)-$inc)."&to={$inc}&sort={$sort}&ord={$ord}&ssid={$ssid}&mac={$mac}&chan={$chan}&radio={$radio}&auth={$auth}&encry={$encry}\">Last</a>&#93 &#45;&#45;&gt; \r\n";
        $this->pages_together = $pages_together;
        return 1;
    }
    
    #==============================#
    #   Redirects the user after   #
    #   something has happened.    #
    #==============================#
    function redirect_page($return = "", $delay = 0)
    {
        if($return == ''){$return = $this->URL_PATH;}
        $this->smarty->assign("redirect_func", "<script type=\"text/javascript\">
            function reload()
            {
                //window.open('{$return}')
                location.href = '{$return}';
            }
        </script>");
        $this->smarty->assign("redirect_html", " onload=\"setTimeout('reload()', {$delay})\"");
    }
    
    function Search($ssid, $mac, $radio, $chan, $auth, $encry, $ord, $sort, $from, $inc)
    {
        $sql1 = "SELECT * FROM `wifi`.`wifi_pointers` WHERE 
            `ssid` LIKE ? AND 
            `mac` LIKE ? AND 
            `radio` LIKE ? AND 
            `chan` LIKE ? AND 
            `auth` LIKE ? AND 
            `encry` LIKE ? ORDER BY `{$sort}` {$ord} LIMIT {$from}, {$inc}";
        $prep1 = $this->sql->conn->prepare($sql1);
        
        $sql2 = "SELECT * FROM `wifi`.`wifi_pointers` WHERE 
                `ssid` LIKE ? AND 
                `mac` LIKE ? AND 
                `radio` LIKE ? AND 
                `chan` LIKE ? AND 
                `auth` LIKE ? AND 
                `encry` LIKE ? ORDER BY `{$sort}` {$ord}";
        $prep2 = $this->sql->conn->prepare($sql2);
        
        $save_url = 'ord='.$ord.'&sort='.$sort.'&from='.$from.'&to='.$inc;
        $export_url = '';
        if($ssid!='')
        {
            $save_url   .= '&ssid='.$ssid;
            $export_url .= '&ssid='.$ssid;
        }

        if($mac!='')
        {
            $save_url   .= '&mac='.$mac;
            $export_url .= '&mac='.$mac;
        }

        if($radio!='')
        {
            $save_url   .= '&radio='.$radio;
            $export_url .= '&radio='.$radio;
        }

        if($chan!='')
        {
            $save_url   .= '&chan='.$chan;
            $export_url .= '&chan='.$chan;
        }

        if($auth!='')
        {
            $save_url   .= '&auth='.$auth;
            $export_url .= 'auth='.$auth.'&';
        }

        if($encry!='')
        {
            $save_url   .= '&encry='.$encry;
            $export_url .= '&encry='.$encry;
        }

        $ssid = $ssid."%";
        $prep1->bindParam(1, $ssid, PDO::PARAM_STR);
        $prep2->bindParam(1, $ssid, PDO::PARAM_STR);
        $mac = $mac."%";
        $prep1->bindParam(2, $mac, PDO::PARAM_STR);
        $prep2->bindParam(2, $mac, PDO::PARAM_STR);
        $radio = $radio."%";
        $prep1->bindParam(3, $radio, PDO::PARAM_STR);
        $prep2->bindParam(3, $radio, PDO::PARAM_STR);
        $chan = $chan."%";
        $prep1->bindParam(4, $chan, PDO::PARAM_STR);
        $prep2->bindParam(4, $chan, PDO::PARAM_STR);
        $auth = $auth."%";
        $prep1->bindParam(5, $auth, PDO::PARAM_STR);
        $prep2->bindParam(5, $auth, PDO::PARAM_STR);
        $encry = $encry."%";
        $prep1->bindParam(6, $encry, PDO::PARAM_STR);
        $prep2->bindParam(6, $encry, PDO::PARAM_STR);
        $prep1->execute();
        $prep2->execute();
        $total_rows = $prep2->rowCount();
        
        $row_color = 0;
        $results_all = array();
        $i=0;
        while ($newArray = $prep1->fetch(2))
        {
            if($row_color == 1)
            {
                $row_color = 0;
                $results_all[$i]['class'] = "light";    
            }
            else
            {
                $row_color = 1;
                $results_all[$i]['class'] = "dark";
            }
            $results_all[$i]['id'] = $newArray['id'];
            $results_all[$i]['ssid'] = $newArray['ssid'];
            $results_all[$i]['mac'] = $newArray['mac'];
            $results_all[$i]['chan'] = $newArray['chan'];
            $results_all[$i]['auth'] = $newArray['auth'];
            $results_all[$i]['encry'] = $newArray['encry'];
            $results_all[$i]['radio']=$newArray['radio'];
            $results_all[$i]['ap_hash']=$newArray['ap_hash'];
            $i++;
        }
        
        return array($total_rows, $results_all, $save_url, $export_url);
    }
    
    
    /*
     * Export to Vistumbler VS1 File
     */
    public function search_export($array = "")
    {
        if($array === "")
        {
            return 0;
        }
        $no_gps = 0;
        $total = count($array);
        $date = date($this->datetime_format);
        $rand = rand();
        $temp_kml = "../tmp/save_".$date."_".$rand."_tmp.kml";
        
        fopen($temp_kml, "w");
        $fileappend = fopen($temp_kml, "a");

        $filename = "save_".$date.'_'.$rand.'.kmz';
        $moved = $this->PATH.'/out/kmz/lists/'.$filename;
        
        fwrite($fileappend, "
<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<kml xmlns=\"".$this->KML_SOURCE_URL."\">
<!--WiFiDB $this->ver_str Search Export-->
<Document>
    <name>RanInt WifiDB KML</name>
    <Style id=\"openStyleDead\">
        <IconStyle>
            <scale>0.5</scale>
            <Icon>
                <href>".$this->open_loc."</href>
            </Icon>
        </IconStyle>
    </Style>
    <Style id=\"wepStyleDead\">
        <IconStyle>
            <scale>0.5</scale>
            <Icon>
                <href>".$this->WEP_loc."</href>
            </Icon>
        </IconStyle>
    </Style>
    <Style id=\"secureStyleDead\">
        <IconStyle>
            <scale>0.5</scale>
            <Icon>
                <href>".$this->WPA_loc."</href>
            </Icon>
        </IconStyle>
    </Style>
    <Style id=\"Location\">
        <LineStyle>
            <color>7f0000ff</color>
            <width>4</width>
        </LineStyle>
    </Style>
    <Folder>
        <name>WiFiDB Access Points</name>
        <description>Total Number of APs: $total</description>
    <Folder>
        <name>Access Points for WiFiDB Search</name>
        ");
        $NN = 0;
        foreach($array as $aps)
        {
            $id = $aps['id'];
            $ssids_ptb = $this->make_ssid($aps['ssid']);
            $ssid = $ssids_ptb[0];
            $mac = $aps['mac'];
            $sectype = $aps['sectype'];
            $chan = $aps['chan'];
            $half_mac = substr(str_replace(":", "", $mac), 0, 6);
            $manuf = $this->manufactures[$half_mac];
            switch($sectype)
            {
                case 1:
                    $type = "#openStyleDead";
                    $auth = "Open";
                    $encry = "None";
                    break;
                case 2:
                    $type = "#wepStyleDead";
                    $auth = "Open";
                    $encry = "WEP";
                    break;
                case 3:
                    $type = "#secureStyleDead";
                    $auth = "WPA-Personal";
                    $encry = "TKIP-PSK";
                    break;
            }
            $otx   = $aps["otx"];
            $btx   = $aps["btx"];
            $nt    = $aps['nt'];
            $label = $aps['label'];

            $signal_exp = explode("-", $aps['sig']);
            
            $first = explode(",", $signal_exp[0]);
            $first_gps_id = $first[0];
            $result = $this->sql->conn->query("SELECT `date`,`time` FROM `wifi`.`wifi_gps` WHERE `id` = '{$first_gps_id}'");
            $first_data = $result->fetch(1);
            $fa = $first_data["date"]." ".$first_data["time"];

            $sig_c = count($signal_exp);
            $last = explode(",", $signal_exp[$sig_c-1]);
            $last_gps_id = $last[0];
            $result = $this->sql->conn->query("SELECT `date`,`time` FROM `wifi`.`wifi_gps` WHERE `id` = '{$last_gps_id}'");
            $last_data = $result->fetch(1);
            $fa = $last_data["date"]." ".$last_data["time"];
            
            $sql_1 = "SELECT * FROM `wifi`.`wifi_gps` WHERE `id` = ?";
            $result_1 = $this->sql->conn->prepare($sql_1);
            foreach($signal_exp as $signal)
            {
                $sig_exp = explode(",", $signal);
                $result_1->bindParam(1, $sig_exp[0], PDO::PARAM_STR);
                $result_1->execute();
                $gps = $result_1->fetch(1);
                
                if(!preg_match('/^(\-?\d+(\.\d+)?),\s*(\-?\d+(\.\d+)?)$/', $gps['lat']) || $gps['lat'] == "N 0.0000" || $gps['lat'] == "N 0000.0000" || $gps['lat'] == "0.00")
                {
                    $zero = 1;
                    continue;
                }
                $alt = $gps['alt'];
                $lat = $this->convert_dm_dd($gps['lat']);
                $long = $this->convert_dm_dd($gps['long']);
                $zero = 0;
                $NN++;
                break;
            }
            if($zero == 1)
            {
                $this->mesg[] = 'No GPS Data, Skipping Access Point: '.$aps['ssid'];
                $zero = 0;
                $no_gps++;
                $total++;
                continue;
            }
            fwrite( $fileappend, "
                <Placemark id=\"{$mac}\">
                    <name>{$ssid}</name>
                    <description>
                        <![CDATA[<b>SSID: </b>{$ssid}<br />
                            <b>Mac Address: </b>{$mac}<br />
                            <b>Network Type: </b>{$nt}<br />
                            <b>Radio Type: </b>{$aps['radio']}<br />
                            <b>Channel: </b>{$chan}<br />
                            <b>Authentication: </b>{$auth}<br />
                            <b>Encryption: </b>{$encry}<br />
                            <b>Basic Transfer Rates: </b>{$btx}<br />
                            <b>Other Transfer Rates: </b>{$otx}<br />
                            <b>First Active: </b>{$fa}<br />
                            <b>Last Updated: </b>{$la}<br />
                            <b>Latitude: </b>{$lat}<br />
                            <b>Longitude: </b>{$long}<br />
                            <b>Manufacturer: </b>{$manuf}<br />
                            <b>Label: </b>{$label}<br />
                            <a href=\"{$this->URL_PATH}/opt/fetch.php?id={$id}\">WiFiDB Link</a>
                        ]]>
                    </description>
                    <styleUrl>{$type}</styleUrl>
                    <Point id=\"{$mac}_GPS\">
                        <coordinates>{$long},{$lat},{$alt}</coordinates>
                    </Point>
                </Placemark>
                ");
        }
        fwrite( $fileappend, "</Folder>
        </Folder>
    </Document>
</kml>");
        fclose( $fileappend );
        if($no_gps < $total)
        {
            $this->mesg[] = '<tr><td colspan="2" style="border-style: solid; border-width: 1px">Zipping up the files into a KMZ file.</td></tr>';
            $zip = new ZipArchive;
            if ($zip->open($filename, ZipArchive::CREATE) === TRUE)
            {
                $zip->addFile($temp_kml, 'doc.kml');
                $zip->close();
                unlink($temp_kml);
            }else
            {
                $this->mesg[] = 'Blown up';
                $this->mesg[] = 'Your Google Earth KML file is not ready.';
                continue;
            }

            $this->mesg[] = 'Move KMZ file from its tmp home to its permanent residence';
            if(copy($filename, $moved))
            {
                $this->mesg[] = 'Your Google Earth KML file is ready, you can download it from <a class="links" href="'.$moved.'">Here</a>';
            }else
            {
                $this->mesg[] = 'Your Google Earth KML file is not ready.';
            }
            unlink($filename);
        }else
        {
            $this->mesg[] = 'Your Google Earth KML file is not ready.';
        }
    }
}
?>
