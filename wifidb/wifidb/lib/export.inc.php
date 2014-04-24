<?php
/*
Export.inc.php, holds the WiFiDB exporting functions.
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
class export extends dbcore
{
    public function __construct($config, $daemon_config) {
        parent::__construct($config, $daemon_config);

        $this->convert = new convert($config, $daemon_config);
        $this->createKML = new createKML($this, $config['timetilldead']);
        $this->daemon_folder_stats = array();
        $this->named = 0;
        $this->month_names  = array(
            1=>'January',
            2=>'February',
            3=>'March',
            4=>'April',
            5=>'May',
            6=>'June',
            7=>'July',
            8=>'August',
            9=>'September',
            10=>'October',
            11=>'November',
            12=>'December',
        );
        $this->ver_array['export'] = array(
            "last_edit"             =>  "22-Apr-2014",
            "ExportAllkml"          =>  "2.1",
            "ExportDailykml"        =>  "1.1",
            "ExportSingleAP"        =>  "1.0",
            "ExportCurrentAPkml"    =>  "1.0",
            "GenerateDaemonKMLData" =>  "1.1",
            "GenerateDaemonKMLLinks"=>  "1.0",
            "HistoryKMLLink"        =>  "1.0",
            "FulldbKMLLink"         =>  "1.0",
            "DailydbKMLLink"        =>  "1.0",
            "GenerateUpdateKML"     =>  "1.0",
            "ExportAllVS1"          =>  "2.0",
            "ExportAllGPX"          =>  "2.0",
            );
    }


    /*
     * Export to Google KML File
     */
    public function ExportAllkml($date = NULL)
    {
        if($date === NULL)
        {
            $date = date($this->date_format);
        }
        $sql = "SELECT `id`, `ssid`, `ap_hash` FROM `wifi`.`wifi_pointers` WHERE `lat` != '0.0000' AND `lat` != '0000.0000' ORDER by `id` ASC";
        $result = $this->sql->conn->query($sql);

        if($this->sql->checkError(__LINE__, __FILE__))
        {
            $this->verbosed("There was an error running the SQL");
            throw new ErrorException("There was an error running the SQL".var_export($this->sql->conn->errorInfo(), 1));
        }
        $NN = $result->rowCount();
        $this->verbosed("APs with GPS: ".$NN);
        
        if($this->named)
        {
            $this->verbosed("Starting Export of Labeled Full KML.");
            $labeled = "_label";
        }else
        {
            $this->verbosed("Starting Export of Non-Labeled Full KML.");
            $labeled = "";
        }
        $daily_folder = $this->PATH.'out/daemon/'.$date;
        if(!@file_exists($daily_folder))
        {
            $this->verbosed("Need to make a daily export folder...", 1);
            if(!@mkdir($daily_folder))
            {
                $this->verbosed("Error making new daily export folder...", -1);
            }
        }else
        {
            if(file_exists($daily_folder."/full_db".$labeled.".kml") && file_exists($daily_folder."/full_db".$labeled.".kmz")){$this->verbosed("Full DB Export for (".$date.") already exists."); return -1;}
        }
        $this->verbosed("Compiling Data for Export.");
        $KML_data="";
        while($array = $result->fetch(2))
        {
            //$this->verbosed($array['id']);
            $ret = $this->ExportSingleAP((int)$array['id']);
            if(is_array($ret) && count($ret[$array['ap_hash']]['gdata']) > 0)
            {
                $this->createKML->ClearData();
                $this->createKML->LoadData($ret);
                $KML_data .= $this->createKML->PlotAllAPs(1, 1, $this->named);
            }
            
        }
        $full_kml_file = $daily_folder."/full_db".$labeled.".kml";
        $this->verbosed("Writing the Full KML File. ($NN APs) : ".$full_kml_file);
        $KML_data = $this->createKML->createFolder("Full Database Export", $KML_data, 0);
        ###
        $this->createKML->createKML($full_kml_file, "WiFiDB Full Database Export", $KML_data, 1);
        chmod($full_kml_file, 0664);
        ###
        $link = $this->PATH.'out/daemon/full_db'.$labeled.'.kml';
        $this->verbosed('Creating symlink from "'.$full_kml_file.'" to "'.$link.'"');
        unlink($link);
        symlink($full_kml_file, $link);
        chmod($link, 0664);
        #####################
        $this->verbosed("Starting to compress the KML to a KMZ.");

        $ret_kmz_name = $this->createKML->CreateKMZ($full_kml_file);
        if($ret_kmz_name == -1)
        {
            $this->verbosed("You did not give a kml file... what am I supposed to do with that? :/ ");
        }
        elseif($ret_kmz_name == -2)
        {
            $this->verbosed("Failed to Zip up the KML to a KMZ file :/ ");
        }
        else
        {
            $this->verbosed("KMZ created at ".$ret_kmz_name);
            chmod($ret_kmz_name, 0664);
            ###
            $link = $this->PATH.'out/daemon/full_db'.$labeled.'.kmz';
            $this->verbosed('Creating symlink from "'.$ret_kmz_name.'" to "'.$link.'"');
            unlink($link);
            symlink($ret_kmz_name, $link);
            chmod($link, 0664);
        }
        
        return $daily_folder;
    }
    
    /*
     * Export All Daily Aps to KML
     */
    public function ExportDailykml($date = NULL)
    {
        if($date === NULL)
        {
            $date = date($this->date_format);
        }
        $date_search = $date."%";
        $select_daily = "SELECT `id` , `points`, `username`, `title`, `date` FROM `wifi`.`user_imports` WHERE `date` LIKE '$date_search'";
        $result = $this->sql->conn->query($select_daily);

        if($this->sql->checkError(__LINE__, __FILE__))
        {
            $this->verbosed("There was an error running the SQL".var_export($this->sql->conn->errorInfo(), 1));
            Throw new ErrorException("There was an error running the SQL".var_export($this->sql->conn->errorInfo(), 1));
        }
        if($result->rowCount() < 1)
        {
            return -1;
        }
        
        if($this->named)
        {
            $this->verbosed("Start of Exporting Labeled Daily KML.");
            $labeled = "_label";
        }else
        {
            $this->verbosed("Start of Exporting Non-Labeled Daily KML.");
            $labeled = "";
        }
        $fetch_imports = $result->fetchAll();
        $data = array();
        foreach($fetch_imports as $import)
        {
            $stage_pts = explode("-", $import['points']);
            foreach($stage_pts as $point)
            {
                $exp = explode(":", $point);
                $hash = $this->GetAPhash($exp[0]);
                $id = $exp[0]+0;
                $ret = $this->ExportSingleAP($id);
                if(is_array($ret) && count($ret[$hash]['gdata']) > 0)
                {
                    $data = array_merge($data, $ret );
                }
            }
            $this->createKML->ClearData();
            $this->createKML->LoadData($data);
            $KML_data = $this->createKML->PlotAllAPs(1, 1, $this->named);
            $KML_data = $this->createKML->createFolder($import['username']." - ".$import['title'], $KML_data, 0);
        }

        $daily_folder = $this->daemon_out.$date;
        if(!@file_exists($daily_folder))
        {
            $this->verbosed("Need to make a daily export folder...", 1);
            if(!mkdir($daily_folder))
            {
                $this->verbosed("Error making new daily export folder...", -1);
                Throw new ErrorException("Error Making new Daily Export Folder. - ".$php_errormsg." - ".$daily_folder);
            }
        }

        $full_kml_file = $daily_folder."/daily_db".$labeled.".kml";
        $this->verbosed("Writing the Daily KML File: ".$full_kml_file);
        $this->createKML->createKML($full_kml_file, "WiFiDB Daily Export ($date)", $KML_data);
        ##
        $link = $this->daemon_out.'daily_db'.$labeled.'.kml';
        $this->verbosed('Creating symlink from "'.$full_kml_file.'" to "'.$link.'"');
        unlink($link);
        symlink($full_kml_file, $link);
        chmod($link, 0664);        
        #####################
        $this->verbosed("Starting to compress the KML to a KMZ.");

        $ret_kmz_name = $this->createKML->CreateKMZ($full_kml_file);
        if($ret_kmz_name == -1)
        {
            $this->verbosed("You did not give a kml file... what am I supposed to do with that? :/ ");
        }
        elseif($ret_kmz_name == -2)
        {
            $this->verbosed("Failed to Zip up the KML to a KMZ file :/ ");
        }
        else
        {
            $this->verbosed("KMZ created at ".$ret_kmz_name);
            chmod($ret_kmz_name, 0664);
            ##
            $link = $this->daemon_out.'daily_db'.$labeled.'.kmz';
            $this->verbosed('Creating symlink from "'.$ret_kmz_name.'" to "'.$link.'"');
            unlink($link);
            symlink($ret_kmz_name, $link);
            chmod($link, 0664);
        }        

        return $daily_folder;
    }

    /*
     * Export All Daily Aps to KML
     */
    public function ExportSingleAP( $id = 0, $new_old = 0, $limit = NULL, $from = NULL)
    {
        if($id === 0 || !is_int($id))
        {
            throw new ErrorException("AP ID is empty or not an Integer, supply one.");
            return 0;
        }
        $sql2 = "SELECT * FROM `wifi`.`wifi_pointers` WHERE `id` = '$id'";

        $prep2 = $this->sql->conn->query($sql2);
        $this->sql->checkError(__LINE__, __FILE__);
        $ap_fetch = $prep2->fetch(2);
        $sql3 = "SELECT
  `wifi_signals`.signal, `wifi_signals`.ap_hash, `wifi_signals`.rssi, `wifi_signals`.time_stamp,
  `wifi_gps`.lat, `wifi_gps`.`long`, `wifi_gps`.sats, `wifi_gps`.hdp, `wifi_gps`.alt, `wifi_gps`.geo,
  `wifi_gps`.kmh, `wifi_gps`.mph, `wifi_gps`.track, `wifi_gps`.date, `wifi_gps`.time
FROM `wifi`.`wifi_signals`
  LEFT JOIN `wifi`.`wifi_gps` ON `wifi_signals`.`gps_id` = `wifi_gps`.`id`
WHERE `wifi_signals`.`ap_hash` = '".$ap_fetch['ap_hash']."'
      AND `wifi_gps`.`lat` != '0.0000'";
        if(!empty($limit))
        {
            $sql3 .= " LIMIT $limit";
            if(!empty($from))
            {
                $sql3 .= ", $from";
            }
        }
        $data[$ap_fetch['ap_hash']] = $ap_fetch;
        $data[$ap_fetch['ap_hash']]['new_old'] = $new_old;
        $prep3 = $this->sql->conn->query($sql3);
        $this->sql->checkError();
        $sig_gps_data = $prep3->fetchAll(2);
        $data[$ap_fetch['ap_hash']]['gdata'] = $sig_gps_data;

        return $data;
    }

    /*
     * Export to Garmin GPX File
     */
    public function ExportGPXAll()
    {
        $this->verbosed("Starting GPX Export of WiFiDB.");
        $sql = "SELECT * FROM `wifi`.`wifi_pointers` WHERE `lat` != 'N 0.0000' AND `lat` != 'N 0000.0000' AND `lat` != 'N 0.0000000' AND `lat` != 'N 00' ORDER by `id` ASC";
        $prep = $this->sql->conn->execute($sql);
        $aparray_all = $prep->fetchAll(2);
        $this->verbosed("Pointers Table Queried.");
        $err = $this->sql->conn->errorCode();
        if($err[0] !== "00000")
        {
            $this->logd("Error fetching from Pointers table to generate GPX All: ".var_export($this->sql->conn->errorInfo(), 1));
            $this->verbosed("Error Fetching data from Pointers Table :(", -1);
            return -1;
        }
        
        foreach($aparray_all as $aparray)
        {
            $file_data  = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\" ?>
<gpx xmlns=\"http://www.topografix.com/GPX/1/1\"
    creator=\"WiFiDB 0.16 Build 2\"
    version=\"1.1\"
    xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
    xsi:schemaLocation=\"http://www.topografix.com/GPX/1/1\">";
            // write file header buffer var
            
            $type = $aparray['sectype'];
            switch($type)
            {
                case 1:
                    $color = "Navaid, Green";
                    break;
                case 2:
                    $color = "Navaid, Amber";
                    break;
                case 3:
                    $color = "Navaid, Red";
                    break;
                default:
                    $color = "Navaid, Green";
                    break;
            }
            $date = $aparray["date"];
            $time = $aparray["time"];
            $alt = $aparray['alt'] * 3.28;
            $lat = $this->convert->dm2dd($aparray['lat']);
            $long = $this->convert->dm2dd($aparray['long']);

            $file_data .= "<wpt lat=\"".$lat."\" lon=\"".$long."\">\r\n"
                                            ."<ele>".$alt."</ele>\r\n"
                                            ."<time>".$date."T".$time."Z</time>\r\n"
                                            ."<name>".$aparray['ssid']."</name>\r\n"
                                            ."<cmt>".$aparray['mac']."</cmt>\r\n"
                                            ."<desc>".$aparray['label']."</desc>\r\n"
                                            ."<sym>".$color."</sym>\r\n<extensions>\r\n"
                                            ."<gpxx:WaypointExtension xmlns:gpxx=\"http://www.garmin.com/xmlschemas/GpxExtensions/v3\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.garmin.com/xmlschemas/GpxExtensions/v3 http://www.garmin.com/xmlschemas/GpxExtensions/v3/GpxExtensionsv3.xsd\">\r\n"
                                            ."<gpxx:DisplayMode>SymbolAndName</gpxx:DisplayMode>\r\n<gpxx:Categories>\r\n"
                                            ."<gpxx:Category>Category ".$type."</gpxx:Category>\r\n</gpxx:Categories>\r\n</gpxx:WaypointExtension>\r\n</extensions>\r\n</wpt>\r\n\r\n";
            if($aparray['rssi'])
            {
                $signals = explode("\\", $aparray['signals']);
            }else
            {
                $signals = explode("-",$aparray['sig']);
            }
            
            $file_data .= "<trk>\r\n<name>GPS Track</name>\r\n<trkseg>\r\n";
            foreach($signals as $signal)
            {
                $sig_exp    = explode(",",$signal);
                $gpsid      = $sig_exp[0];
                
                $sql = "SELECT * FROM `wifi`.`wifi_gps` WHERE `id` = ? LIMIT 1";
                $prepgps = $this->sql->conn->prepare($sql);
                $prepgps->bindParam(1, $gpsid, PDO::PARAM_INT);
                $prepgps->execute();
                $gps = $prepgps->fetch(2);
                
                $alt = $gps['alt'] * 3.28;

                $lat =& $this->convert->dm2dd($gps['lat']);

                $long =& $this->convert->dm2dd($gps['long']);
                $file_data .= "<trkpt lat=\"".$lat."\" lon=\"".$long."\">\r\n"
                                        ."<ele>".$alt."</ele>\r\n"
                                        ."<time>".$date."T".$time."Z</time>\r\n"
                                        ."</trkpt>\r\n";
            }
            $this->verbosed('Plotted AP: '.$aparray['ssid']);
        }
        
        
        $file_data .= "</trkseg>\r\n</trk></gpx>";
        $file_ext = "wifidb_".date($this->datetime_format).".gpx";
        $filename = ($this->gpx_out.$file_ext);
        $filewrite = fopen($filename, "w");
        if($filewrite == FALSE)
        {
            $this->logd("Error trying to write the GPX file: $filename");
            $this->verbosed("Error trying to write the GPX file: $filename  :(", -1);
            return -1;
        }
        $fileappend = fopen($filename, "a");
        fwrite($fileappend, $file_data);
        fclose($fileappend);

        #chmod($daily_folder.'/full_db'.$labeled.'.kml', 0750);

        return 1;
    }

    /*
     * Export to Vistumbler VS1 File
     */
    public function ExportCurrentAPkml()
    {
        $sql = "SELECT `id`, `ssid`, `ap_hash` FROM `wifi`.`wifi_pointers` ORDER BY `id` DESC LIMIT 1";
        $result = $this->sql->conn->query($sql);
        $ap_array = $result->fetch(2);
        $hash = $ap_array['ap_hash'];

        $this->verbosed('Start export of Newest AP: '.$ap_array["ssid"], 1);
        $data = $this->ExportSingleAP((int)$ap_array['id']);
        $count = count($data[$hash]['gdata']);
        if($count < 1)
        {
            $this->verbosed('Did not Find any, not writing AP to file.', -1);
        }else
        {
            $this->verbosed('Found some, writing KML File.', 2);
            $this->createKML->LoadData($data);
            $KML_string = $this->createKML->PlotAPpoint($hash, 0);
            if($this->createKML->createKML($this->daemon_out."newestAP.kml", "Newest AP", $KML_string, 1))
            {
                $this->verbosed('Newest AP File written.', 2);
            }else
            {
                Throw new ErrorException('Could not write NewestAP.');
            }
            #####################################
            $KML_string = $this->createKML->PlotAPpoint($hash, 1);
            if($this->createKML->createKML($this->daemon_out."newestAP_label.kml", "Newest AP Labeled", $KML_string, 1))
            {
                $this->verbosed('Newest AP Labeled File written.', 2);
            }else
            {
                Throw new ErrorException('Could not write Newest AP Labeled.');
            }
            $this->verbosed('File has been written and is ready.', 1);
        }
    }

    /*
     * Generate the Daily Daemon KML files
     */
    public function GenerateDaemonKMLData()
    {
        $date = date($this->date_format);
        $this->named = 0;
        $this->ExportAllkml($date);
        $this->named = 1;
        $this->ExportAllkml($date);
        
        $this->named = 0;
        $this->ExportDailykml($date);
        $this->named = 1;
        $this->ExportDailykml($date);
        
        if($this->HistoryKMLLink() === -1)
        {
            $this->verbosed("Failed to Create Daemon History KML Links", -1);
        }else
        {
            $this->verbosed("Created Daemon History KML Links");
        }
        
        if($this->GenerateUpdateKML() === -1)
        {
            $this->verbosed("Failed to Create Update.kml File", -1);
        }else
        {
            $this->verbosed("Created Update.kml File");
        }
        return 1;
    }
    
    /*
     * Create the Archival KML links
     */
    public function HistoryKMLLink()
    {
        $this->daemon_folder_stats['history'] = array();
        $daemon_export = $this->PATH."out/daemon/";
        $dir = opendir($daemon_export);
        $files = array();
        while ($file = readdir($dir))
        {
            if($file == "." || $file == ".." || $file == ".svn"){continue;}
            if(is_dir($daemon_export.$file))
            {
                $files[] = $file;
            }
        }
        sort($files);
        closedir($dir);
        
        foreach($files as $entry)
        {
            $matches = array();
            preg_match("/([0-9]{4}\-[0-9]{2}\-[0-9]{2})/", $entry, $matches, PREG_OFFSET_CAPTURE);
            if(@$matches[0])
            {
                $date_exp = explode("-", $entry);
                $year = $date_exp[0]+0;
                $month = $date_exp[1]+0;
                $day = $date_exp[2]+0;
                $month_label = $this->month_names[$month];
                $this->daemon_folder_stats['history'][$year][$month_label][$day] = $entry;
            }
            
        }
        $generated = array();
        foreach($this->daemon_folder_stats['history'] as $key=>$year)
        {
            $output = $daemon_export.'history/'.$key.'.kml';
            $current_year = date("Y")+0;
            if(file_exists($output) && $key != $current_year)
            {
                $generated[] = $key.'.kml';
                continue;
            }
            $kml_data = '<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
<Folder>
        <name>'.$key.'</name>
        <open>0</open>';

            foreach($year as $key1=>$month)
            {
                $kml_data .= '
        <Folder>
                <name>'.$key1.'</name>
                <open>0</open>';
                foreach($month as $key2=>$day)
                {
                    if(file_exists($daemon_export.$day.'/daily_db.kmz'))
                    {
                        $daily_db_kmz_nl = '
                        <NetworkLink>
                                <name>Daily KMZ</name>
                                <visibility>0</visibility>
                                <Link>
                                        <href>'.$this->URL_PATH.'out/daemon/'.$day.'/daily_db.kmz</href>
                                </Link>
                        </NetworkLink>';
                    }else
                    {
                        $daily_db_kmz_nl = '';
                    }
                    if(file_exists($daemon_export.$day.'/daily_db.kml'))
                    {
                        $daily_db_kml_nl = '
                        <NetworkLink>
                                <name>Daily KML</name>
                                <visibility>0</visibility>
                                <Link>
                                        <href>'.$this->URL_PATH.'out/daemon/'.$day.'/daily_db.kml</href>
                                </Link>
                        </NetworkLink>';
                    }else
                    {
                        $daily_db_kml_nl = '';
                    }
                    
                    if(file_exists($daemon_export.$day.'/daily_db_label.kml'))
                    {
                        $daily_db_kml_label_nl = '
                        <NetworkLink>
                                <name>Daily Labeled KML</name>
                                <visibility>0</visibility>
                                <Link>
                                        <href>'.$this->URL_PATH.'out/daemon/'.$day.'/daily_db_label.kml</href>
                                </Link>
                        </NetworkLink>';
                    }else
                    {
                        $daily_db_kml_label_nl = '';
                    }
                    
                    if(file_exists($daemon_export.$day.'/daily_db_label.kmz'))
                    {
                        $daily_db_kmz_label_nl = '
                        <NetworkLink>
                                <name>Daily Labeled KMZ</name>
                                <visibility>0</visibility>
                                <Link>
                                        <href>'.$this->URL_PATH.'out/daemon/'.$day.'/daily_db_label.kmz</href>
                                </Link>
                        </NetworkLink>';
                    }else
                    {
                        $daily_db_kmz_label_nl = '';
                    }
                    
                    if(file_exists($daemon_export.$day.'/full_db.kml'))
                    {
                        $full_db_kml_nl = '
                        <NetworkLink>
                                <name>Full DB KML</name>
                                <visibility>0</visibility>
                                <Link>
                                        <href>'.$this->URL_PATH.'out/daemon/'.$day.'/full_db.kml</href>
                                </Link>
                        </NetworkLink>';
                    }else
                    {
                        $full_db_kml_nl = '';
                    }
                    
                    if(file_exists($daemon_export.$day.'/full_db.kmz'))
                    {
                        $full_db_kmz_nl = '
                        <NetworkLink>
                                <name>Full DB KMZ</name>
                                <visibility>0</visibility>
                                <Link>
                                        <href>'.$this->URL_PATH.'out/daemon/'.$day.'/full_db.kmz</href>
                                </Link>
                        </NetworkLink>';
                    }else
                    {
                        $full_db_kmz_nl = '';
                    }
                    
                    if(file_exists($daemon_export.$day.'/full_db_label.kml'))
                    {
                        $full_db_label_kml_nl = '
                        <NetworkLink>
                                <name>Full DB Labeled KML</name>
                                <visibility>0</visibility>
                                <Link>
                                        <href>'.$this->URL_PATH.'out/daemon/'.$day.'/full_db_label.kml</href>
                                </Link>
                        </NetworkLink>';
                    }else
                    {
                        $full_db_label_kml_nl = '';
                    }

                    if(file_exists($daemon_export.$day.'/full_db_label.kmz'))
                    {
                        $full_db_label_kmz_nl = '
                        <NetworkLink>
                                <name>Full DB Labeled KMZ</name>
                                <visibility>0</visibility>
                                <Link>
                                        <href>'.$this->URL_PATH.'out/daemon/'.$day.'/full_db_label.kmz</href>
                                </Link>
                        </NetworkLink>';
                    }else
                    {
                        $full_db_label_kmz_nl = '';
                    }
                    
                    
                    $kml_data .= '
                <Folder>
                        <name>'.$key2.'</name>
                        <open>0</open>'.$daily_db_kml_nl.
                            $daily_db_kmz_nl.
                            $daily_db_kml_label_nl.
                            $daily_db_kmz_label_nl.
                            $full_db_kml_nl.
                            $full_db_kmz_nl.
                            $full_db_label_kml_nl.
                            $full_db_label_kmz_nl.'
                </Folder>';
                }
                $kml_data .= '</Folder>';
            }
            $kml_data .= '</Folder></kml>';
            
            file_put_contents($output, $kml_data);
            $generated[] = $key.'.kml';
        }
        
        $kml_data = '<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2" xmlns:kml="http://www.opengis.net/kml/2.2" xmlns:atom="http://www.w3.org/2005/Atom">
<Folder>
        <name>WiFiDB Archive</name>
        <open>0</open>';
        foreach($generated as $year)
        {
            $year_name = str_replace(".kml", "", $year);
            $kml_data .= '
                <NetworkLink>
                        <name>'.$year_name.'</name>
                        <visibility>0</visibility>
                        <Link>
                                <href>'.$this->URL_PATH.'out/daemon/history/'.$year.'</href>
                        </Link>
                </NetworkLink>';
        }
        $kml_data .= '
</Folder>
</kml>';
        $output = $daemon_export.'history.kml';
        file_put_contents($output, $kml_data);
    }
    
    /*
     * Generate the updated KML Link
     */
    public function GenerateUpdateKML()
    {
        $new_AP_link = $this->createKML->createNetworkLink($this->URL_PATH.'out/daemon/newestAP.kml',"Newest AP (No Label)").$this->createKML->createNetworkLink($this->URL_PATH.'out/daemon/newestAP_label.kml',"Newest AP (Labeled)");
        $new_AP_folder = $this->createKML->createFolder("Newest Access Point", $new_AP_link, 1);
        
        $full_link = $this->createKML->createNetworkLink($this->URL_PATH.'out/daemon/fulldb.kmz', "Full Daily Database Export");
        $full_folder = $this->createKML->createFolder("Daily KMZ", $full_link, 1);

        $archive_link = $this->createKML->createNetworkLink($this->URL_PATH.'out/daemon/history.kml', "Archived History");
        $archive_folder = $this->createKML->createFolder("Historical Archives", $archive_link, 1);

        $kml_data = $new_AP_folder.$full_folder.$archive_folder;

        $ret = $this->createKML->createKML("update", "WiFiDB *ALPHA* Auto KMZ Generation", $kml_data);
        return $ret;
    }

    public function UserAll($user)
    {
        if(!is_string($user))
        {
            throw new ErrorException('$user value for export::UserAll() is not a string');
            return 0;
        }
        $data = array();
        $sql = "SELECT * FROM `wifi`.`user_imports` WHERE `username` = ?";
        $prep = $this->sql->conn->prepare($sql);
        $prep->bindParam(1, $user, PDO::PARAM_STR);
        $prep->execute();
        $this->sql->checkError(__LINE__, __FILE__);
        $fetch = $prep->fetch();
        if($fetch['points'] == "")
        {
            throw new ErrorException("User selected is empty, try again.");
        }
        $points = explode("-", $fetch['points']);
        foreach($points as $point)
        {
            list($id, $new_old) = explode(":", $point);

            $ap_hash = $this->GetAPhash($id);
            $data[$ap_hash]['gdata'] = $this->ExportSingleAP((int)$id, $new_old);
        }
        $data1 = $this->subval_sort($data, 'ssid', 0);
        $this->createKML->LoadData($data1);
        $KML_data = $this->createKML->PlotAllAPs(1, 1, $this->named);
        $KML_data = $this->createKML->createFolder($user, $KML_data, 0);
        $user_fn = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $user);
        $export_file = $this->kml_out.$user_fn.".kml";
        $this->createKML->createKML($export_file, "$user AP's", $KML_data);

        $results = array("mesg" => 'File is ready: <a href="'.$this->kml_htmlpath.$user_fn.'.kml">'.$user_fn.'.kml</a>');
        return $results;
    }

    public function UserList($row)
    {
        if(!is_int($row))
        {
            throw new ErrorException('$row value for export::UserList() is NaN');
            return 0;
        }
        $data = array();
        $sql = "SELECT * FROM `wifi`.`user_imports` WHERE `id` = ?";
        $prep = $this->sql->conn->prepare($sql);
        $prep->bindParam(1, $row, PDO::PARAM_INT);
        $prep->execute();
        $this->sql->checkError(__LINE__, __FILE__);
        $fetch = $prep->fetch();
        if($fetch['points'] == "")
        {
            throw new ErrorException("User Import selected is empty, try again.");
        }
        $points = explode("-", $fetch['points']);
        foreach($points as $point)
        {
            list($id, $new_old) = explode(":", $point);

            $ap_hash = $this->GetAPhash($id);
            $data[$ap_hash]['gdata'] = $this->ExportSingleAP((int)$id, $new_old);
        }
        $data1 = $this->subval_sort($data, 'ssid', 0);
        $this->createKML->LoadData($data1);
        $KML_data = $this->createKML->PlotAllAPs(1, 1, $this->named);
        $KML_data = $this->createKML->createFolder($fetch['username']." - ".$fetch['title']." - ".$fetch['date'], $KML_data, 0);
        $title = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $fetch['title']);
        $export_file = $this->kml_out.$title.".kml";
        $this->createKML->createKML($export_file, "$title", $KML_data);

        $results = array("mesg" => 'File is ready: <a href="'.$this->kml_htmlpath.$title.'.kml">'.$title.'.kml</a>');
        return $results;
    }
}
