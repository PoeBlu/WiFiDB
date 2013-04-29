<?php
/*
import.inc.php, holds the WiFiDB Importing functions.
Copyright (C) 2011 Phil Ferland

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

class import extends wdbcli
{
    function __construct($config, $daemon_config, $export_obj = NULL)
    {
        parent::__construct($config, $daemon_config);
        if($export_obj != NULL)
        {
            $this->export = $export_obj;
        }else
        {
            $this->export = new export($config, $daemon_config);
        }

        $this->log_level    = $daemon_config['log_level'];
        $this->log_interval = 1;
        $this->verbose      = $daemon_config['verbose'];
    }
    
####################
    public function import_vs1($source="" , $user="Unknown")
    {
        $r = 0;
        $increment_ids = 0;
        $apdata = array();
        $gdata = array();
        # We need to check and see if the file location was passed, if not fail gracefully.
        if ($source == NULL)
        {
            $this->logd("The file that needs to be imported was not included in the import public function.");
            $this->verbosed("The file that needs to be imported was not included in the import public function", -1);
            throw new ErrorException;
        }
        $this->logd("Source File Path: ".$source);

        # Open the file and dump its contents into an array.
        
        $File_return     = explode("\r\n", utf8_decode(file_get_contents($source)));
        
        # count how many rows in the file. If it is less than or equal to 8, it is a blank file.
        $this->logd("First row of file: ".$File_return[1]);
        $file_rows = count($File_return);
        $this->logd("Number of rows in file: ".$file_rows);
        
        # get the MD5 hash for the file data.
        $hash = hash_file('md5', $source);
        
        # Now lets loop through the file and see what we have.
        $this->verbosed("Compiling data from file to array:", 3);
        $this->logd("Compiling data from file to array:");
        $sql = "SELECT `id` FROM `wifi`.`files_tmp` WHERE `hash`= ? LIMIT 1";
        $prep = $this->sql->conn->prepare($sql);
        $prep->bindParam(1, $hash, PDO::PARAM_STR);
        $prep->execute();
        $file_tmp_array = $prep->fetch(2);
        $file_tmp_id = $file_tmp_array['id'];
        foreach($File_return as $key => $file_line)
        {
            $encoding = mb_detect_encoding($file_line);
            $file_line_alt = iconv($encoding, 'UTF-8//TRANSLIT', $file_line);
            if($key == 0)
            {
                $file_line_alt = str_replace("?","",$file_line_alt);
            }
            $first_char = trim(substr($file_line_alt,0,1));
            if($first_char == "#"){continue;}
            if($file_line_alt == ""){continue;}
            
            $file_line_exp = explode("|",$file_line_alt);
            $file_line_exp_count = count($file_line_exp);
            switch($file_line_exp_count)
            {
                case 6:
                    #This is from an older version of the VS1 GPS data, sanatize and order it into an array.
                    $gps_line = $file_line_exp;
                    $date_exp = explode("-",$gps_line[4]);
                    if(strlen($date_exp[0]) <= 2)
                    {
                        $gpsdate = $date_exp[2]."-".$date_exp[0]."-".$date_exp[1];
                    }else
                    {
                        $gpsdate = $gps_line[4];
                    }
                    if($gps_line[1] == "" || $gps_line[2] == ""){continue;}
                    if($gps_line[0] == 0){$increment_ids = 1;}
                    if($increment_ids){$gps_line[0]++;}

                    $gdata[$gps_line[0]] = array(
                                'id'    =>  $gps_line[0],
                                'lat'	=>  $gps_line[1],
                                'long'	=>  $gps_line[2],
                                'sats'	=>  $gps_line[3],
                                'hdp'   =>  '0',
                                'alt'   =>  '0',
                                'geo'   =>  '0',
                                'kmh'   =>  '0',
                                'mph'   =>  '0',
                                'track' =>  '0',
                                'date'	=>  $gpsdate,
                                'time'	=>  $gps_line[5]
                    );
                    break;
                case 12:
                    #This is the current version of the VS1 export, sanatize and order it into an array.
                    $gps_line = $file_line_exp;
                    $date_exp = explode("-", $gps_line[10]);
                    if(strlen($date_exp[0]) <= 2)
                    {
                        $gpsdate = $date_exp[2]."-".$date_exp[0]."-".$date_exp[1];
                    }else
                    {
                        $gpsdate = $gps_line[10];
                    }
                    if($gps_line[1] == "" || $gps_line[2] == ""){continue;}
                    if($gps_line[0] == 0){$increment_ids = 1;}
                    if($increment_ids){$gps_line[0]++;}
                    $gdata[$gps_line[0]] = array(
                                'id'    =>  $gps_line[0],
                                'lat'	=>  $gps_line[1],
                                'long'	=>  $gps_line[2],
                                'sats'	=>  $gps_line[3],
                                'hdp'	=>  $gps_line[4],
                                'alt'	=>  $gps_line[5],
                                'geo'	=>  $gps_line[6],
                                'kmh'	=>  $gps_line[7],
                                'mph'	=>  $gps_line[8],
                                'track'	=>  $gps_line[9],
                                'date'	=>  $gpsdate,
                                'time'	=>  $gps_line[11]
                            );
                    break;
                case 13:
                    #This is to generate a sanitized and ordered array for each AP.
                    $ap_line = $file_line_exp;
                    $apdata[] = array(
                                'ssid'      =>  $ap_line[0],
                                'mac'       =>  $ap_line[1],
                                'manuf'     =>  $ap_line[2],
                                'auth'      =>  $ap_line[3],
                                'encry'     =>  $ap_line[4],
                                'sectype'   =>  $ap_line[5],
                                'radio'     =>  $ap_line[6],
                                'manuf'     =>  $this->manufactures($ap_line[1]),
                                'chan'      =>  $ap_line[7],
                                'btx'       =>  $ap_line[8],
                                'otx'       =>  $ap_line[9],
                                'nt'        =>  $ap_line[10],
                                'HighSig'   =>  0,
                                'HighRSSI'  =>  0,
                                'label'     =>  $ap_line[11],
                                'signals'   =>  $ap_line[12]
                            );
                    $this->rssi_signals_flag = 0;
                    break;
                case 15:
                    #This is to generate a sanitized and ordered array for each AP.
                    $ap_line = $file_line_exp;
                    $apdata[] = array(
                                'ssid'      =>  $ap_line[0],
                                'mac'       =>  $ap_line[1],
                                'manuf'     =>  $ap_line[2],
                                'auth'      =>  $ap_line[3],
                                'encry'     =>  $ap_line[4],
                                'sectype'   =>  $ap_line[5],
                                'radio'     =>  $ap_line[6],
                                'manuf'     =>  $this->manufactures($ap_line[1]),
                                'chan'      =>  $ap_line[7],
                                'btx'       =>  $ap_line[8],
                                'otx'       =>  $ap_line[9],
                                'nt'        =>  $ap_line[10],
                                'HighSig'   =>  $ap_line[11],
                                'HighRSSI'  =>  $ap_line[12],
                                'label'     =>  $ap_line[13],
                                'signals'   =>  $ap_line[14]
                            );
                            $this->rssi_signals_flag = 1;
                    break;
                
                default:
                    echo "--------------------------------\r\n";
                    $this->logd("Error parsing File.\r\n".var_export($file_line_alt, 1));
                    $this->verbosed($file_line_exp_count."\r\nummm.... thats not supposed to happen... :/\r\n", -1);
                    #$this->mail->mail();
                    break;
            }
            if($this->verbose)
            {
                if($r===0){echo "|\r";}
                if($r===10){echo "/\r";}
                if($r===20){echo "-\r";}
                if($r===30){echo "\\\r";}
                if($r===40){echo "|\r";}
                if($r===50){echo "/\r";}
                if($r===60){echo "-\r";}
                if($r===70){echo "\\\r";$r=0;}
                $r++;
            }
        }
        $vs1data = array('gpsdata'=>$gdata, 'apdata'=>$apdata);
        $ap_count = count($vs1data['apdata']);
        $gps_count = count($vs1data['gpsdata']);
        
        $this->verbosed("Importing GPS data [$gps_count]:");
        $this->logd("Importing GPS data [$gps_count].");
        $this->logd("GPS: ".$gps_count." | APS: ".$ap_count);
        
        foreach($vs1data['gpsdata'] as $key=>$gps)
        {
            if(!preg_match('/^(\-?\d+(\.\d+)?),\s*(\-?\d+(\.\d+)?)$/', $gps['lat'] == ''))
            {
                $gps['lat'] = "N 0000.0000";
                $gps['long'] = "E 0000.0000";
                #die("############################################################## NEEDS TO BE REMOVED!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");
            }
            if(!preg_match('/(\d{2})-(\d{2})-(\d{4})/', $gps['date']))
            {
                $gps['date'] = "1969-01-01";
                $gps['time'] = "00:00:00";
            }
            $sql = "INSERT INTO `wifi`.`wifi_gps` ( `id`, `lat`, `long`, `sats`, `hdp`, `alt`, `geo`, `kmh`, `mph`, `track`, `date`, `time`)
                    VALUES ('',
                            '{$gps['lat']}',
                            '{$gps['long']}',
                            '{$gps['sats']}',
                            '{$gps['hdp']}',
                            '{$gps['alt']}',
                            '{$gps['geo']}',
                            '{$gps['kmh']}',
                            '{$gps['mph']}',
                            '{$gps['track']}',
                            '{$gps['date']}',
                            '{$gps['time']}'
                           )";
            $prep = $this->sql->conn->prepare($sql);
            $prep->bindParam(1,$gps['lat'], PDO::PARAM_STR);
            $prep->bindParam(2,$gps['long'], PDO::PARAM_STR);
            $prep->bindParam(3,$gps['sats'],PDO::PARAM_INT);
            $prep->bindParam(4,$gps['hdp'],PDO::PARAM_STR);
            $prep->bindParam(5,$gps['alt'],PDO::PARAM_STR);
            $prep->bindParam(6,$gps['geo'],PDO::PARAM_STR);
            $prep->bindParam(7,$gps['kmh'],PDO::PARAM_STR);
            $prep->bindParam(8,$gps['mph'],PDO::PARAM_STR);
            $prep->bindParam(9,$gps['track'],PDO::PARAM_STR);
            $prep->bindParam(10,$gps['date'],PDO::PARAM_STR);
            $prep->bindParam(11,$gps['time'],PDO::PARAM_STR);
            $prep->execute();
            
            $err = $this->sql->conn->errorCode();
            if($err[0] == "00000")
            {
                $vs1data['gpsdata'][$gps['id']]['import_id'] = $this->sql->conn->lastInsertId();
            }else
            {
                $this->verbosed("Failed Insert of GPS.".var_export($this->sql->conn->errorInfo(),1), -1);
                $this->logd("Failed Insert of GPS.".var_export($this->sql->conn->errorInfo(),1));
                throw new ErrorException;
            }
            if($this->verbose)
            {
                if($r===0){echo "|\r";}
                if($r===10){echo "/\r";}
                if($r===20){echo "-\r";}
                if($r===30){echo "\\\r";}
                if($r===40){echo "|\r";}
                if($r===50){echo "/\r";}
                if($r===60){echo "-\r";}
                if($r===70){echo "\\\r";$r=0;}
                $r++;
            }
        }
        
        $this->verbosed("Importing AP Data [$ap_count]:");
        $this->logd("Importing AP Data [$ap_count]");
        $imported_aps = array();
        
        foreach($vs1data['apdata'] as $key=>$aps)
        {
            $sql = "UPDATE `wifi`.`files_tmp` SET `tot` = ? WHERE `id` = ?";
            $prep = $this->sql->conn->prepare($sql);
            $prep->execute(array($key-$ap_count,$file_tmp_id));
            $err = $this->sql->conn->errorCode();
            if($err[0] != "00000")
            {
                $this->verbosed(var_export($this->sql->conn->errorInfo(),1), -1);
                $this->logd("Error Updating Temp Files Table for current AP.\r\n".var_export($this->sql->conn->errorInfo(),1));
                throw new ErrorException;
            }

            $ap_hash = md5($aps['ssid'].$aps['mac'].$aps['chan'].$aps['sectype'].$aps['radio'].$aps['auth'].$aps['encry']);
            $ssid_len = strlen($aps['ssid']);
            if($ssid_len < 7)
            {
                $pad = 20;
            }else
            {
                $pad = $ssid_len;
            }
            $key_c = $key+1;
            $ssid = str_pad($aps['ssid'], $pad);
            $chan = str_pad($aps['chan'], $pad);
            $radio = str_pad($aps['radio'], $pad);
            $encry = str_pad($aps['encry'], $pad);
            $this->verbosed("------------------------
            File AP/Total: {$key_c}/{$ap_count}
            SSID:  {$ssid} | MAC: {$aps['mac']}
            CHAN:  {$chan} | SECTYPE: {$aps['sectype']}
            RADIO: {$radio}| AUTH: {$aps['auth']}
            ENCRY: {$encry}| APHASH:".$ap_hash, 1);
            $this->logd("Starting Import of AP ({$ap_hash}), {$aps['ssid']} ");

            $sql = "SELECT `id`, `signals`, `LA` FROM `wifi`.`wifi_pointers` WHERE `ap_hash` = ? LIMIT 1";
            $res = $this->sql->conn->prepare($sql);
            $res->bindParam(1, $ap_hash, PDO::PARAM_STR);
            $res->execute();
            $fetch = $res->fetch(2);
            if($fetch['id'])
            {
                $prev_signals = $fetch['signals'];
                $prev_LA_time = $fetch['LA'];
                $prev_id      = $fetch['id'];
                $no_pointer   = 1;
            }else
            {
                $prev_signals = "";
                $prev_LA_time = 0;
                $prev_id      = 0;
                $no_pointer   = 0;
            }

            if($this->rssi_signals_flag)
            {
                $ap_sig_exp = explode("\\", $aps['signals']);
            }
            else
            {
               $ap_sig_exp = explode("-", $aps['signals']);
            }
                
            $compile_sig = array();
            $sig_high = 0;
            $rssi_high = 0;
            $gps_center = 0;
            $FA = 0;
            

            $this->verbosed("Starting Import of Wifi Signal... ", 1);
            $this->logd("Starting Import of Wifi Signal... ");

            foreach($ap_sig_exp as $key=>$sig_gps_id)
            {
                $sig_gps_exp = explode(",", $sig_gps_id);
                
                $gps_id = $sig_gps_exp[0];
                $signal = $sig_gps_exp[1];
                $rssi   =  (@$sig_gps_exp[2] ? $sig_gps_exp[2] : 0);
                
                if(!@$vs1data['gpsdata'][$gps_id]){continue;}
                
                if($signal >= $sig_high){$sig_high = $signal; $gps_center = $gps_id;}else{$sig_high = $signal; $gps_center = $gps_id;}
                if($rssi >= $rssi_high){$rssi_high = $rssi; $gps_center = $gps_id;}else{$rssi_high = $rssi; $gps_center = $gps_id;}
                if(!$FA){$FA = $gps_id;}
                $LA = $gps_id;
                
                $sql = "INSERT INTO `wifi`.`wifi_signals` (`id`, `ap_hash`, `signal`, `rssi`, `gps_id`, `time_stamp`) VALUES (NULL, ?, ?, ?, ?, ?)";
                $time_stamp = strtotime($vs1data['gpsdata'][$gps_id]['date']." ".$vs1data['gpsdata'][$gps_id]['time']);
                $preps = $this->sql->conn->prepare($sql);
                $preps->bindParam(1, $ap_hash, PDO::PARAM_STR);
                $preps->bindParam(2, $signal, PDO::PARAM_INT);
                $preps->bindParam(3, $rssi, PDO::PARAM_INT);
                $preps->bindParam(4, $vs1data['gpsdata'][$gps_id]['import_id'], PDO::PARAM_INT);
                $preps->bindParam(5, $time_stamp, PDO::PARAM_INT);
                $preps->execute();

                $sql = "UPDATE `wifi`.`wifi_gps` SET `ap_hash` = ? WHERE `id` = ?";
                $prepg = $this->sql->conn->prepare($sql);
                $prepg->bindParam(1, $ap_hash, PDO::PARAM_STR);
                $prepg->bindParam(2, $vs1data['gpsdata'][$gps_id]['import_id'], PDO::PARAM_STR);
                $prepg->execute();

                $compile_sig[] = $vs1data['gpsdata'][$gps_id]['import_id'].",".$this->sql->conn->lastInsertId();
                
                $err = $this->sql->conn->errorCode();
                if($err[0] != "00000")
                {
                    $this->verbosed(var_export($this->sql->conn->errorInfo(),1), -1);
                    $this->logd("Error inserting wifi signal.\r\n".var_export($this->sql->conn->errorInfo(),1));
                    throw new ErrorException;
                }
                
                if($this->verbose)
                {
                    if($r===0){echo "|\r";}
                    if($r===10){echo "/\r";}
                    if($r===20){echo "-\r";}
                    if($r===30){echo "\\\r";}
                    if($r===40){echo "|\r";}
                    if($r===50){echo "/\r";}
                    if($r===60){echo "-\r";}
                    if($r===70){echo "\\\r";$r=0;}
                    $r++;
                }
            }
            
            if(count($compile_sig) < 1 )
            {
                $this->verbosed("This AP has No vaild GPS in the file, this means a corrupted file. APs with corrupted data will not have signal data until there is valid GPS data.", -1);
                $this->logd("This AP has No vaild GPS in the file, this means a corrupted file. APs with corrupted data will not have signal data until there is valid GPS data.");
                $FA_time = date("Y-m-d H:i:s");
                $LA_time = date("Y-m-d H:i:s");
            }else
            {
                $sig_imp = implode("-", $compile_sig);
                if($prev_signals != "")
                {
                    $aps['signals'] = $prev_signals."-".$sig_imp;
                }else
                {
                    $aps['signals'] = $sig_imp;
                }
                
                $LA_time = $vs1data['gpsdata'][$LA]['date'].' '.$vs1data['gpsdata'][$LA]['time'];
                $FA_time = $vs1data['gpsdata'][$FA]['date'].' '.$vs1data['gpsdata'][$FA]['time'];
            }

            if($no_pointer)
            {
                $LA_time_sec = strtotime($FA_time);
                $prev_LA_time_sec = strtotime($prev_LA_time);
                
                if($LA_time_sec <= $prev_LA_time_sec)
                {
                    $sql = "UPDATE `wifi`.`wifi_pointers` SET `signals` = ? WHERE `ap_hash` = ?";
                    $prep = $this->sql->conn->prepare($sql);
                    $prep->bindParam(1, $aps['signals'], PDO::PARAM_STR);
                    $prep->bindParam(2, $ap_hash, PDO::PARAM_STR);
                    $prep->execute();
                }else
                {
                    $sql = "UPDATE `wifi`.`wifi_pointers` SET `LA` = ?, `signals` = ? WHERE `ap_hash` = ?";
                    $prep = $this->sql->conn->prepare($sql);
                    $prep->bindParam(1, $LA_time, PDO::PARAM_STR);
                    $prep->bindParam(2, $aps['signals'], PDO::PARAM_STR);
                    $prep->bindParam(3, $ap_hash, PDO::PARAM_STR);
                    $prep->execute();
                }
                
                $err = $this->sql->conn->errorCode();
                if($err != "00000")
                {
                    $this->verbosed(var_export($this->sql->conn->errorInfo(),1), -1);
                    $this->logd("Error Updating AP Pointer. ".var_export($this->sql->conn->errorInfo(),1));
                    throw new ErrorException;
                }
                else
                {
                    $imported_aps[] = $prev_id.":1";
                    $this->verbosed("Updated AP Pointer {".$prev_id."}.", 2);
                    $this->logd("Updated AP Pointer. {".$prev_id."}");
                }
                $imported_aps[] = $prev_id.":1";
            }else
            {
######################################################################################################
                if(!$gps_center)
                {
                    $vs1data['gpsdata'][$gps_center]['lat'] = "N 0000.0000";
                    $vs1data['gpsdata'][$gps_center]['long'] = "E 0000.0000";
                    $vs1data['gpsdata'][$gps_center]['alt'] = "0";
                    $this->verbosed("bad center : $source\r\n");
                }
######################################################################################################

                $sql = "INSERT INTO `wifi`.`wifi_pointers` ( `id`, `ssid`, `mac`,`chan`,`sectype`,`radio`,`auth`,`encry`,`manuf`,`lat`,`long`,`alt`,`BTx`,`OTx`,`NT`,`label`,`LA`,`FA`,`username`,`ap_hash`, `signals`, `rssi_high`, `signal_high`) VALUES ( NULL,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,? )";
                
                $prep = $this->sql->conn->prepare($sql);
                $prep->bindParam(1, $aps['ssid'], PDO::PARAM_STR);
                $prep->bindParam(2, $aps['mac'], PDO::PARAM_STR);
                $prep->bindParam(3, $aps['chan'], PDO::PARAM_INT);
                $prep->bindParam(4, $aps['sectype'], PDO::PARAM_INT);
                $prep->bindParam(5, $aps['radio'], PDO::PARAM_STR);
                $prep->bindParam(6, $aps['auth'], PDO::PARAM_STR);
                $prep->bindParam(7, $aps['encry'], PDO::PARAM_STR);
                $prep->bindParam(8, $aps['manuf'], PDO::PARAM_STR);
                $prep->bindParam(9, $vs1data['gpsdata'][$gps_center]['lat'], PDO::PARAM_STR);
                $prep->bindParam(10, $vs1data['gpsdata'][$gps_center]['long'], PDO::PARAM_STR);
                $prep->bindParam(11, $vs1data['gpsdata'][$gps_center]['alt'], PDO::PARAM_INT);
                $prep->bindParam(12, $aps['btx'], PDO::PARAM_STR);
                $prep->bindParam(13, $aps['otx'], PDO::PARAM_STR);
                $prep->bindParam(14, $aps['nt'], PDO::PARAM_STR);
                $prep->bindParam(15, $aps['label'], PDO::PARAM_STR);
                $prep->bindParam(16, $LA_time, PDO::PARAM_STR);
                $prep->bindParam(17, $FA_time, PDO::PARAM_STR);
                $prep->bindParam(18, $user, PDO::PARAM_STR);
                $prep->bindParam(19, $ap_hash, PDO::PARAM_STR);
                $prep->bindParam(20, $aps['signals'], PDO::PARAM_STR);
                $prep->bindParam(21, $rssi_high, PDO::PARAM_INT);
                $prep->bindParam(22, $sig_high, PDO::PARAM_INT);
                $prep->execute();
                $err = $this->sql->conn->errorCode();
                if($err != "00000")
                {
                    $this->verbosed(var_export($this->sql->conn->errorInfo(),1), -1);
                    $this->logd("Error insering wifi pointer. ".var_export($this->sql->conn->errorInfo(),1));
                    throw new ErrorException;
                }
                else
                {
                    $imported_aps[] = $this->sql->conn->lastInsertId().":0";
                    
                    $this->verbosed("Inserted APs Pointer {".$this->sql->conn->lastInsertId()." of $ap_count}.", 2);
                    $this->logd("Inserted APs pointer. {".$this->sql->conn->lastInsertId()."}");
                    
                    $sql = "UPDATE `wifi`.`settings` SET `size` = '".$this->sql->conn->lastInsertId()."' WHERE `id`='2'";
                    $this->sql->conn->query($sql);
                }
            }
            $this->export->exportCurrentAPkml();
            $this->verbosed("------------------------\r\n", 1);# Done with this AP.
        }
        #Finish off Import and give credit to the user.
        
        $imported = implode("-", $imported_aps);
        $date = date("Y-m-d H:i:s");

        $ret = array(
                'imported'=> $imported,
                'date'=>$date,
                'aps'=>$ap_count,
                'gps'=>$gps_count
            );
        return $ret;
    }
}

?>
