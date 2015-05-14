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
	public function __construct($config, $createKMLObj, $convertObj, $ZipObj){
		parent::__construct($config);

		$this->convert = $convertObj;
		$this->createKML = $createKMLObj;
		$this->Zip = $ZipObj;
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
		$this->ver_array['export']  =   array(
			"last_edit"			 =>  "2015-03-06",
			"ExportAllkml"		  =>  "2.1",
			"ExportDailykml"		=>  "1.1",
			"ExportSingleAP"		=>  "1.0",
			"ExportCurrentAPkml"	=>  "1.0",
			"GenerateDaemonKMLData" =>  "1.1",
			"GenerateDaemonKMLLinks"=>  "1.0",
			"HistoryKMLLink"		=>  "1.0",
			"FulldbKMLLink"		 =>  "1.0",
			"DailydbKMLLink"		=>  "1.0",
			"GenerateUpdateKML"	 =>  "1.0",
			"ExportAllVS1"		  =>  "2.0",
			"ExportAllGPX"		  =>  "2.0",
		);
	}

	public function CreateBoundariesKML()
	{
		$boundaries_kml_file = $this->PATH.'out/daemon/boundaries.kml';
		$this->verbosed("Generating World Boundaries KML File : ".$boundaries_kml_file);

		$results = $this->sql->conn->query("SELECT * FROM `wifi`.`boundaries`");
		$fetched = $results->fetchAll(2);
		$KML_data = "";
		foreach($fetched as $boundary)
		{
			$KML_data .= $this->createKML->PlotBoundary($boundary);
		}

		$KMLFolderdata = $this->createKML->createFolder("World Boundaries", $KML_data, 0);
		$this->createKML->createKML($boundaries_kml_file, "World Boundaries", $KMLFolderdata);
		chmod($boundaries_kml_file, 0664);
		return $boundaries_kml_file;
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
		$sql = "SELECT `id`, `ssid`, `ap_hash` FROM `wifi`.`wifi_pointers` WHERE `lat` != '0.0000' AND `mac` != '00:00:00:00:00:00' ORDER by `id` ASC";
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
		$regions_link = $this->createKML->createNetworkLink($this->URL_PATH.'out/daemon/boundaries.kml', "Regions to save precious CPU cycles.", 1, 0, "once", 60);
		$KML_data = $this->createKML->createFolder("WifiDB Newest AP", $regions_link, 1, 1);

		while($array = $result->fetch(2))
		{
			$ret = $this->ExportSingleAP((int)$array['id'], 0);
			if(is_array($ret) && count($ret[$array['ap_hash']]['gdata']) > 0)
			{
				$this->createKML->ClearData();
				$this->createKML->LoadData($ret);
				$KML_data .= $this->createKML->PlotAllAPs(1, 1, $this->named);
			}
		}
		$kmz_filename = $daily_folder."/full_db".$labeled.".kmz";
		$this->verbosed("Writing the Full KMZ File. ($NN APs) : ".$kmz_filename);
		$KML_data = $this->createKML->createFolder("Full Database Export", $KML_data, 0);
		$KML_data = $this->createKML->createKMLstructure("WiFiDB Full Database Export", $KML_data);
		$this->Zip->addFile($KML_data, 'doc.kml');
		$this->Zip->setZipFile($kmz_filename);
		$this->Zip->getZipFile();
		
		if (file_exists($kmz_filename)) 
		{
			$this->verbosed("KMZ created at ".$kmz_filename);
			chmod($kmz_filename, 0664);
			###
			$link = $this->daemon_out.'full_db'.$labeled.'.kmz';
			$this->verbosed('Creating symlink from "'.$kmz_filename.'" to "'.$link.'"');
			unlink($link);
			symlink($kmz_filename, $link);
			chmod($link, 0664);
		}
		else
		{
			$this->verbosed("Failed to Create KMZ file :/ ");
		}		
		
		return $daily_folder;
	}

	public function ExportDailykmlOldVersion($date = NULL)
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
		
		if($this->named)
		{
			$this->verbosed("Start of Exporting Labeled Daily KML.");
			$labeled = "_label";
		}else
		{
			$this->verbosed("Start of Exporting Non-Labeled Daily KML.");
			$labeled = "";
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

		$fetch_imports = $result->fetchAll();
		$KML_data="";
		foreach($fetch_imports as $import)
		{
			$Import_KML_Data = "";
			$stage_pts = explode("-", $import['points']);
			foreach($stage_pts as $point)
			{
				list($id, $new_old) = explode(":", $point);
				$sql = "SELECT * FROM `wifi`.`wifi_pointers` WHERE `id` = '$id' And `lat` != '0.0000' AND `mac` != '00:00:00:00:00:00'";
				$result = $this->sql->conn->query($sql);
				while($array = $result->fetch(2))
				{
					$ret = $this->ExportSingleAP((int)$array['id'], 1);
					if(is_array($ret) && count($ret[$array['ap_hash']]['gdata']) > 0)
					{
						$this->createKML->ClearData();
						$this->createKML->LoadData($ret);
						$Import_KML_Data .= $this->createKML->PlotAllAPs(1, 1, $this->named);
					}
				}
			}
			if($Import_KML_Data != ""){$KML_data .= $this->createKML->createFolder($import['username']." - ".$import['title'], $Import_KML_Data, 0);}
		}
		if($KML_data == ""){$KML_data .= $this->createKML->createFolder("No Daily Exports with GPS", $KML_data, 0);}
		
		$kmz_filename = $daily_folder."/daily_db".$labeled.".kmz";
		$this->verbosed("Writing the Daily KML File: ".$kmz_filename);
		$KML_data = $this->createKML->createFolder("Daily Export", $KML_data, 0);
		$KML_data = $this->createKML->createKMLstructure("WiFiDB Daily Export ($date)", $KML_data);
		$this->Zip->addFile($KML_data, 'doc.kml');
		$this->Zip->setZipFile($kmz_filename);
		$this->Zip->getZipFile();
		
		if (file_exists($kmz_filename)) 
		{
			$this->verbosed("KMZ created at ".$kmz_filename);
			chmod($kmz_filename, 0664);
			###
			$link = $this->daemon_out.'daily_db'.$labeled.'.kmz';
			$this->verbosed('Creating symlink from "'.$kmz_filename.'" to "'.$link.'"');
			unlink($link);
			symlink($kmz_filename, $link);
			chmod($link, 0664);
		}
		else
		{
			$this->verbosed("Failed to Create KMZ file :/ ");
		}				

		return $daily_folder;
	}

	/*
	 * Export All Daily Aps to KML
	 */
	/**
	 * @param null $date
	 * @return string
	 * @throws ErrorException
	 */
	public function ExportDailykml($date = NULL)
	{
		if($date === NULL)
		{
			$date = date($this->date_format);
		}
		$date_search = $date."%";
		$select_daily = "SELECT `id` , `points`, `username`, `title`, `date`, `hash` FROM `wifi`.`user_imports` WHERE `date` LIKE '$date_search'";
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

		$fetch_imports = $result->fetchAll();

		#$KML_data = $this->createKML->createNetworkLink($this->URL_PATH.'out/daemon/boundaries.kml', "Regions to save precious CPU cycles.", 1, 0, "once", 60);
		#$KML_data = $this->createKML->createFolder("Regions to save precious CPU cycles.", $regions_link, 0, 0);
		$exports = array();
		foreach($fetch_imports as $import)
		{
			$KML_data = '';
			$ii = 0;
			$box = array();
			$Import_KML_Data = "";
			$stage_pts = explode("-", $import['points']);
			foreach($stage_pts as $point)
			{
				$exp = explode(":", $point);
				$hash = $this->GetAPhash($exp[0]);
				$id = $exp[0]+0;
				$ret = $this->ExportSingleAP($id);
				if($ret == -1)
				{
					continue;
				}
				$ii++;
				#var_dump($ret[$hash]['gdata']);
				$box[] = $this->FindBox($ret[$hash]['gdata']);
				#var_dump($box);
				#die();
				if(is_array($ret) && count($ret[$hash]['gdata']) > 0)
				{
					$this->createKML->ClearData();
					$this->createKML->LoadData($ret);
					$Import_KML_Data .= $this->createKML->PlotAllAPs(1, 1, $this->named);
				}
			}
			if($ii == 0)
			{
				echo "No GPS File\n";
				continue;
			}
			#var_dump($box);
			#die();
			$final_box = $this->FindMostBox($box);
			#var_dump($final_box);
			list($distance_calc, $minLodPix, $distance) = $this->distance($final_box[0], $final_box[2], $final_box[1], $final_box[3], "K"); # North, East, South, West
			var_dump($distance_calc, $minLodPix, $distance);

			$KML_data .= $this->createKML->PlotRegionBox($final_box, $distance_calc, $minLodPix, $import['id']);
			if($Import_KML_Data != ""){$KML_data .= $this->createKML->createFolder($import['username']." - ".$import['title'], $Import_KML_Data, 0);}
			
			$kmz_filename = $daily_folder."/".$import['hash'].$labeled.".kmz";
			$this->verbosed("Writing KMZ for ".$import['title']." : ".$kmz_filename);
			$this->Zip->addFile($KML_data, 'doc.kml');
			$this->Zip->setZipFile($kmz_filename);
			$this->Zip->getZipFile();

			$exports[] = array($this->daemon_htmlpath.$date."/".$import['hash'].$labeled.".kmz", $import['username']." - ".$import['title']);
			##
		}
		$KML_data = '';
		if($KML_data == ""){$KML_data .= $this->createKML->createFolder("No Daily Exports with GPS", $KML_data, 0);}
		foreach($exports as $export)
		{
			$KML_data .= $this->createKML->createNetworkLink($export[0], $export[1], 0, 1, "onInterval", 3600);
		}
		
		$kmz_filename = $daily_folder."/daily_db".$labeled.".kmz";
		$this->verbosed("Writing the Daily KML File: ".$kmz_filename);
		$KML_data = $this->createKML->createFolder("Daily Export", $KML_data, 0);
		$KML_data = $this->createKML->createKMLstructure("WiFiDB Daily Export ($date)", $KML_data);
		$this->Zip->addFile($KML_data, 'doc.kml');
		$this->Zip->setZipFile($kmz_filename);
		$this->Zip->getZipFile();
		
		if (file_exists($kmz_filename)) 
		{
			$this->verbosed("KMZ created at ".$kmz_filename);
			chmod($kmz_filename, 0664);
			###
			$link = $this->daemon_out.'daily_db'.$labeled.'.kmz';
			$this->verbosed('Creating symlink from "'.$kmz_filename.'" to "'.$link.'"');
			unlink($link);
			symlink($kmz_filename, $link);
			chmod($link, 0664);
		}
		else
		{
			$this->verbosed("Failed to Create KMZ file :/ ");
		}

		return $daily_folder;
	}

	/*
	 * Export All Daily Aps to KML
	 */
	public function ExportSingleAP( $id = 0, $new_ap = 0, $limit = NULL, $from = NULL)
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
WHERE `wifi_signals`.`ap_hash` = '".$ap_fetch['ap_hash']."' AND `wifi_gps`.`lat` != '0.0000'";
		if(!empty($limit))
		{
			$sql3 .= " LIMIT $limit";
			if(!empty($from))
			{
				$sql3 .= ", $from";
			}
		}
		#echo $sql3;
		$data[$ap_fetch['ap_hash']] = $ap_fetch;
		$data[$ap_fetch['ap_hash']]['new_ap'] = $new_ap;
		$data[$ap_fetch['ap_hash']]['lat'] = $ap_fetch['lat'];
		$data[$ap_fetch['ap_hash']]['long'] = $ap_fetch['long'];
		$data[$ap_fetch['ap_hash']]['alt'] = $ap_fetch['alt'];
		$prep3 = $this->sql->conn->query($sql3);
		$this->sql->checkError();
		$sig_gps_data = $prep3->fetchAll(2);
		if(count($sig_gps_data) < 1)
		{
			#echo "No GPS\n";
			return -1;
		}
		$data[$ap_fetch['ap_hash']]['gdata'] = $sig_gps_data;

		return $data;
	}

	/*
	 * Export to Garmin GPX File
	 */
	public function ExportGPXAll()
	{
		$this->verbosed("Starting GPX Export of WiFiDB.");
		$sql = "SELECT * FROM `wifi`.`wifi_pointers` WHERE `lat` != '0.0000' AND `mac` != '00:00:00:00:00:00' ORDER by `id` ASC";
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
				$sig_exp	= explode(",",$signal);
				$gpsid	  = $sig_exp[0];

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

	public function ExportCurrentAPkmlApi($labelap=0, $new_icons=0)
	{
		$sql = "SELECT `id`, `ssid`, `ap_hash` FROM `wifi`.`wifi_pointers` WHERE `lat` != '0.0000' ORDER BY `id` DESC LIMIT 1";
		$result = $this->sql->conn->query($sql);
		$ap_array = $result->fetch(2);
		if($ap_array['ap_hash'])
		{
			$hash = $ap_array['ap_hash'];
			$id = (int)$ap_array['id'];
			$data = $this->ExportSingleAP($id, $new_icons);
			$this->createKML->LoadData($data);
			if($labelap){$KML_string = $this->createKML->PlotAPpoint($hash, 1);}else{$KML_string = $this->createKML->PlotAPpoint($hash, 0);}
		}
		else
		{
			$KML_string = $this->createKML->createFolder("No Access Points Found", "", 0, 0);
		}
		if($labelap){$KML_string = $this->createKML->createKMLstructure("Newest AP Labeled", $KML_string);}else{$KML_string = $this->createKML->createKMLstructure("Newest AP", $KML_string);}
		Return $KML_string;
	}

	public function ExportCurrentAPkml()
	{
		$sql = "SELECT `id`, `ssid`, `ap_hash` FROM `wifi`.`wifi_pointers` ORDER BY `id` DESC LIMIT 1";
		$result = $this->sql->conn->query($sql);
		$ap_array = $result->fetch(2);
		$hash = $ap_array['ap_hash'];

		$this->verbosed('Start export of Newest AP: '.$ap_array["ssid"], 1);
		$data = $this->ExportSingleAP((int)$ap_array['id'], 1);
		$count = count($data[$hash]['gdata']);
		if($count < 1)
		{
			$this->verbosed('Did not Find any GPS, not writing AP to file.', -1);
		}else
		{
			$this->verbosed('Found some GPS, writing KML File.', 2);
			$this->createKML->LoadData($data);
			
			#Export Newest AP KMZ
			$KML_data = $this->createKML->PlotAPpoint($hash, 0);
			
			$kmz_filename = $this->daemon_out."newestAP.kmz";
			$this->verbosed("Writing the Newest AP File: ".$kmz_filename);
			$KML_data = $this->createKML->createKMLstructure("Newest AP", $KML_data);
			$this->Zip->addFile($KML_data, 'doc.kml');
			$this->Zip->setZipFile($kmz_filename);
			$this->Zip->getZipFile();
			
			if (file_exists($kmz_filename)) 
			{
				$this->verbosed("Newest AP KMZ created at ".$kmz_filename);
				chmod($kmz_filename, 0664);
			}
			else
			{
				$this->verbosed("Failed to Create Newest KMZ file :/ ");
				#Throw new ErrorException('Could not write Newest AP KMZ.');
			}

			#Export Newest AP KMZ w/Label
			$KML_data = $this->createKML->PlotAPpoint($hash, 1);
			
			$kmz_filename = $this->daemon_out."newestAP_label.kmz";
			$this->verbosed("Writing the Newest AP Labeled File: ".$kmz_filename);
			$KML_data = $this->createKML->createKMLstructure("Newest AP Labeled", $KML_data);
			$this->Zip->addFile($KML_data, 'doc.kml');
			$this->Zip->setZipFile($kmz_filename);
			$this->Zip->getZipFile();
			
			if (file_exists($kmz_filename)) 
			{
				$this->verbosed("Newest AP Labeled KMZ created at ".$kmz_filename);
				chmod($kmz_filename, 0664);
			}
			else
			{
				$this->verbosed("Failed to Create Newest Labeled KMZ file :/ ");
				#Throw new ErrorException('Could not write Newest AP Labeled KMZ.');
			}
		}
	}

	public function GatherAllExports()
	{
		$scan = scandir($this->daemon_out);
		foreach($scan as $file)
		{
			if($file === "."){continue;}
			if($file === ".."){continue;}
			if($file === "history"){continue;}
			if($file === "history.kml"){continue;}
			if($file === "history.kmz"){continue;}
			if($file === "boundaries.kml"){continue;}
			if($file === "full_db.kml"){continue;}
			if($file === "full_db.kmz"){continue;}
			if($file === "full_db_label.kml"){continue;}
			if($file === "full_db_label.kmz"){continue;}
			if($file === "daily_db_label.kmz"){continue;}
			if($file === "daily_db_label.kml"){continue;}
			if($file === "daily_db.kmz"){continue;}
			if($file === "daily_db.kml"){continue;}
			if($file === "newestAP_label.kml"){continue;}
			if($file === "newestAP_label.kmz"){continue;}
			if($file === "newestAP.kml"){continue;}
			if($file === "newestAP.kmz"){continue;}
			if($file === "update.kml"){continue;}
			if($file === "update.kmz"){continue;}
			var_dump($file);
			foreach(scandir($this->daemon_out.$file) as $subfile)
			{
				if($subfile === "."){continue;}
				if($subfile === ".."){continue;}
				if($subfile === "daily_db.kml"){continue;}

				if($this->named) {
					if ($subfile === "daily_db.kmz") {
						var_dump($subfile);
						continue;
					}

				} else {
					if ($subfile === "daily_db_label.kmz") {
						var_dump($subfile);
						continue;
					}
				}

			}
			echo "\n";
		}
	}

	/*
	 * Generate the Daily Daemon KML files
	 */
	public function GenerateDaemonKMLData()
	{
		$date = date($this->date_format);
		$this->named = 0;
		#$this->GatherAllExports();
		$this->ExportAllkml($date);
		$this->named = 1;
		#$this->GatherAllExports();
		$this->ExportAllkml($date);

		$this->named = 0;
		#$this->ExportDailykml($date);
		$this->ExportDailykmlOldVersion($date);
		$this->named = 1;
		#$this->ExportDailykml($date);
		$this->ExportDailykmlOldVersion($date);

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
			$output = $daemon_export.'history/'.$key.'.kmz';
			$current_year = date("Y")+0;
			if(file_exists($output) && $key != $current_year)
			{
				$generated[] = $key.'.kmz';
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
						<open>0</open>'.$daily_db_kmz_nl.
						$daily_db_kmz_label_nl.
						$full_db_kmz_nl.
						$full_db_label_kmz_nl.'
				</Folder>';
				}
				$kml_data .= '</Folder>';
			}
			$kml_data .= '</Folder></kml>';
			
			$this->Zip->addFile($kml_data, 'doc.kml');
			$this->Zip->setZipFile($output);
			$this->Zip->getZipFile();
			
			if (file_exists($output)) 
			{
				$this->verbosed("KMZ created at ".$output);
				chmod($output, 0664);
			}
			else
			{
				$this->verbosed("Failed to Create KMZ file :/ ");
			}		

			$generated[] = $key.'.kmz';
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
		
		$this->Zip->addFile($kml_data, 'doc.kml');
		$this->Zip->setZipFile($output);
		$this->Zip->getZipFile();
		
		if (file_exists($output)) 
		{
			$this->verbosed("KMZ created at ".$output);
			chmod($output, 0664);
		}
		else
		{
			$this->verbosed("Failed to Create KMZ file :/ ");
		}		
	}

	/*
	 * Generate the updated KML Link
	 */
	public function GenerateUpdateKML()
	{

		$full_link = $this->createKML->createNetworkLink($this->URL_PATH.'out/daemon/full_db.kmz', "Full DB Export (No Label)", 0, 0, "onInterval", 3600).
			$this->createKML->createNetworkLink($this->URL_PATH.'out/daemon/full_db_label.kmz', "Full DB Export (Label)", 0, 0, "onInterval", 3600);
		$full_folder = $this->createKML->createFolder("WifiDB Full DB Export", $full_link, 1, 1);

		$daily_link = $this->createKML->createNetworkLink($this->URL_PATH.'out/daemon/daily_db.kmz', "Daily DB Export (No Label)", 1, 0, "onInterval", 3600).
			$this->createKML->createNetworkLink($this->URL_PATH.'out/daemon/daily_db_label.kmz', "Daily DB Export (Label)", 0, 0, "onInterval", 3600);
		$daily_folder = $this->createKML->createFolder("WifiDB Daily DB Export", $daily_link, 1, 1);

		$new_AP_link = $this->createKML->createNetworkLink($this->URL_PATH.'api/latest.php?labeled=0',"Newest AP w/ Fly To (No Label)", 0, 1, "onInterval", 60).
			$this->createKML->createNetworkLink($this->URL_PATH.'api/latest.php?labeled=1',"Newest AP w/ Fly To (Labeled)", 0, 1, "onInterval", 60).
			$this->createKML->createNetworkLink($this->URL_PATH.'api/latest.php?labeled=0',"Newest AP (No Label)", 0, 0, "onInterval", 60).
			$this->createKML->createNetworkLink($this->URL_PATH.'api/latest.php?labeled=1',"Newest AP (Labeled)", 1, 0, "onInterval", 60);
		$new_AP_folder = $this->createKML->createFolder("WifiDB Newest AP", $new_AP_link, 1, 1);

		//$archive_link = $this->createKML->createNetworkLink($this->URL_PATH.'out/daemon/history.kmz', "Archived History", 0, 0, "onInterval", 86400);
		//$archive_folder = $this->createKML->createFolder("Historical Archives", $archive_link, 1);

		//$KML_data = $full_folder.$daily_folder.$new_AP_folder.$regions_folder;#.$archive_folder;
		$KML_data = $new_AP_folder.$daily_folder.$full_folder;#.$archive_folder;
		$KML_data = $this->createKML->createKMLstructure("Combined KMZ Network Link", $KML_data);
		
		$kmz_filename = $this->daemon_out.'update.kmz';
		$this->verbosed("Writing KMZ : ".$kmz_filename);
		$this->Zip->addFile($KML_data, 'doc.kml');
		$this->Zip->setZipFile($kmz_filename);
		$this->Zip->getZipFile();
		
		if (file_exists($kmz_filename)) 
		{
			$this->verbosed("KMZ created at ".$kmz_filename);
			chmod($kmz_filename, 0664);
		}
		else
		{
			$this->verbosed("Failed create KMZ file :/ ");
		}
		
		return $kmz_filename;
	}
	
	public function UserAllKml($user)
	{
		if(!is_string($user))
		{
			throw new ErrorException('$user value for export::UserAll() is not a string');
			return 0;
		}
		$sql = "SELECT * FROM `wifi`.`user_imports` WHERE `username` = ?";
		$prep = $this->sql->conn->prepare($sql);
		$prep->bindParam(1, $user, PDO::PARAM_STR);
		$prep->execute();
		$this->sql->checkError(__LINE__, __FILE__);
		$user_imports = $prep->fetchAll();
		$uicount = count($user_imports);

		$KML_data="";
		if($uicount < 1)
		{
			throw new ErrorException("User selected is empty, try again.");
		}else
		{
			foreach($user_imports as $import)
			{
				$points = explode("-", $import['points']);
				foreach($points as $point)
				{
					list($id, $new_old) = explode(":", $point);
					$sql = "SELECT * FROM `wifi`.`wifi_pointers` WHERE `id` = '$id' And `lat` != '0.0000' AND `mac` != '00:00:00:00:00:00'";
					$result = $this->sql->conn->query($sql);
					while($array = $result->fetch(2))
					{
						$ret = $this->ExportSingleAP((int)$array['id'], 1);
						if(is_array($ret) && count($ret[$array['ap_hash']]['gdata']) > 0)
						{
							$this->createKML->ClearData();
							$this->createKML->LoadData($ret);
							$KML_data .= $this->createKML->PlotAllAPs(1, 1, $this->named);
						}
					}
				}
			}
		}
		return $KML_data;
	}

	public function UserAll($user)
	{
		$KML_data = $this->UserAllKml($user);
		if($KML_data == "")
		{
			$results = array("mesg" => 'This export has no APs with gps. No KMZ file has been exported');
		}
		else
		{
			$user_fn = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $user);
			$kmz_filename = $this->kml_out.$user_fn.".kmz";
			#$this->verbosed("Writing KMZ for ".$user_fn." : ".$kmz_filename);
			$KML_data = $this->createKML->createKMLstructure($user_fn, $KML_data);
			$this->Zip->addFile($KML_data, 'doc.kml');
			$this->Zip->setZipFile($kmz_filename);
			$this->Zip->getZipFile();
			
			if (file_exists($kmz_filename)) 
			{
				$results = array("mesg" => 'File is ready: <a href="'.$this->kml_htmlpath.$user_fn.'.kmz">'.$user_fn.'.kmz</a>');
			}
			else
			{
				$results = array("mesg" => 'Error: No kmz file... what am I supposed to do with that? :/');
			}
		}
		return $results;
	}

	public function SingleApKML($id, $limit = NULL, $from = NULL)
	{
		if(!is_int($id))
		{
			throw new ErrorException('$id value for export::SingleApKML() is NaN');
			return 0;
		}

		$KML_data = "";
		$export_id="";
		$export_ssid="";
		$sql = "SELECT * FROM `wifi`.`wifi_pointers` WHERE `id` = '$id' And `lat` != '0.0000'";
		$result = $this->sql->conn->query($sql);
		while($array = $result->fetch(2))
		{
			$export_id = (int)$array['id'];
			$export_ssid = $array['ssid'];
			$ret = $this->ExportSingleAP($id, 1, $limit, $from);
			if(is_array($ret) && count($ret[$array['ap_hash']]['gdata']) > 0)
			{
				$this->createKML->ClearData();
				$this->createKML->LoadData($ret);
				$KML_data .= $this->createKML->PlotAllAPs(1, 1, $this->named);
			}
		}

		if($KML_data == "")
		{
			$results = array("mesg" => 'This AP has no gps. No KMZ file has been exported');
		}
		else
		{
			$KML_data = $this->createKML->createFolder($export_id." - ".$export_ssid, $KML_data, 0);
			$title = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $export_id."-".$export_ssid);
			$kmz_filename = $this->kml_out.$title.".kmz";
			#$this->verbosed("Writing KMZ for ".$title." : ".$kmz_filename);
			$KML_data = $this->createKML->createKMLstructure($title, $KML_data);
			$this->Zip->addFile($KML_data, 'doc.kml');
			$this->Zip->setZipFile($kmz_filename);
			$this->Zip->getZipFile();
			
			if (file_exists($kmz_filename)) 
			{
				$results = array("mesg" => 'File is ready: <a href="'.$this->kml_htmlpath.$title.'.kmz">'.$title.'.kmz</a>');
			}
			else
			{
				$results = array("mesg" => 'Error: No kmz file... what am I supposed to do with that? :/');
			}
		}
		return $results;

	}

	public function UserListKml($points, $username, $title, $date, $named=0, $only_new=0, $new_icons=0, $regions=0)
	{
		$box = array();
		$KML_data="";
		$Import_KML_Data="";
		$points = explode("-", $points);
		foreach($points as $point)
		{
			list($id, $new_old) = explode(":", $point);
			if($only_new == 1 and $new_old == 1){continue;}
			$sql = "SELECT * FROM `wifi`.`wifi_pointers` WHERE `id` = '$id' And `lat` != '0.0000' AND `mac` != '00:00:00:00:00:00'";
			$result = $this->sql->conn->query($sql);
			while($array = $result->fetch(2))
			{
				$ret = $this->ExportSingleAP((int)$array['id'], $new_icons);
				$box[] = $this->FindBox($ret[$array['ap_hash']]['gdata']);
				if(is_array($ret) && count($ret[$array['ap_hash']]['gdata']) > 0)
				{
					$this->createKML->ClearData();
					$this->createKML->LoadData($ret);
					$Import_KML_Data .= $this->createKML->PlotAllAPs(1, 1, $named);
				}
			}
		}
		if($Import_KML_Data)
		{
			if($regions)
			{
				$final_box = $this->FindMostBox($box);
				#list($distance_calc, $minLodPix, $distance) = $this->distance($final_box[0], $final_box[2], $final_box[1], $final_box[3], "K"); # North, East, South, West
				$KML_data = $this->createKML->PlotRegionBox($final_box, 100, 0, $title).$Import_KML_Data;
			}
			else
			{
				$KML_data = $Import_KML_Data;
			}
		}
		return $KML_data;
	}	
	
	public function UserList($row)
	{
		if(!is_int($row))
		{
			throw new ErrorException('$row value for export::UserList() is NaN');
			return 0;
		}
		$sql = "SELECT * FROM `wifi`.`user_imports` WHERE `id` = ?";
		$prep = $this->sql->conn->prepare($sql);
		$prep->bindParam(1, $row, PDO::PARAM_INT);
		$prep->execute();
		$this->sql->checkError(__LINE__, __FILE__);
		$fetch = $prep->fetch();
		
		$KML_data = $this->UserListKml($fetch['points'], $fetch['username'], $fetch['title'], $fetch['date']);
		if($KML_data == "")
		{
			$results = array("mesg" => 'This export has no APs with gps. No KMZ file has been exported');
		}
		else
		{
			$KML_data = $this->createKML->createFolder($fetch['username']." - ".$fetch['title']." - ".$fetch['date'], $KML_data, 0);
			$title = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), $fetch['title']);
			$kmz_filename = $this->kml_out.$title.".kmz";
			#$this->verbosed("Writing KMZ for ".$title." : ".$kmz_filename);
			$KML_data = $this->createKML->createKMLstructure($title, $KML_data);
			$this->Zip->addFile($KML_data, 'doc.kml');
			$this->Zip->setZipFile($kmz_filename);
			$this->Zip->getZipFile();
			
			if (file_exists($kmz_filename)) 
			{
				$results = array("mesg" => 'File is ready: <a href="'.$this->kml_htmlpath.$title.'.kmz">'.$title.'.kmz</a>');
			}
			else
			{
				$results = array("mesg" => 'Error: No kmz file... what am I supposed to do with that? :/');
			}			
		}
		return $results;
	}

	public function exp_search($ResultList)
	{
		$KML_data = "";
		foreach($ResultList as $ResultAP) {
			$ret = $this->ExportSingleAP((int)$ResultAP['id'], 1);
			if(is_array($ret) && count($ret[$ResultAP['ap_hash']]['gdata']) > 0)
			{
				$this->createKML->ClearData();
				$this->createKML->LoadData($ret);
				$KML_data .= $this->createKML->PlotAllAPs(1, 1, $this->named);
			}
		}

		if($KML_data == "")
		{
			$results = array("mesg" => 'This export has no APs with gps. No KMZ file has been exported');
		}
		else
		{
			$KML_data = $this->createKML->createFolder("Search Export", $KML_data, 0);
			$title = preg_replace(array('/\s/', '/\.[\.]+/', '/[^\w_\.\-]/'), array('_', '.', ''), "Search_Export");
			$kmz_filename = $this->kml_out.$title.".kmz";
			#$this->verbosed("Writing KMZ for ".$title." : ".$kmz_filename);
			$KML_data = $this->createKML->createKMLstructure($title, $KML_data);
			$this->Zip->addFile($KML_data, 'doc.kml');
			$this->Zip->setZipFile($kmz_filename);
			$this->Zip->getZipFile();
			
			if (file_exists($kmz_filename)) 
			{
				$results = array("mesg" => 'File is ready: <a href="'.$this->kml_htmlpath.$title.'.kmz">'.$title.'.kmz</a>');
			}
			else
			{
				$results = array("mesg" => 'Error: No kmz file... what am I supposed to do with that? :/');
			}
		}
		return $results;
	}

	function FindMostBox($points = array())
	{
		$North = NULL;
		$South = NULL;
		$East = NULL;
		$West = NULL;
		foreach($points as $elements)
		{
			#var_dump($elements);
			if(@$elements[0] == '' || @$elements[1] == '')
			{
				return -1;
			}
			if($North == NULL)
			{
				$North = $elements[0];
			}
			if($South == NULL)
			{
				$South = $elements[1];
			}

			if($East == NULL)
			{
				$East = $elements[2];
			}
			if($West == NULL)
			{
				$West = $elements[3];
			}

			if((float)$North < (float)$elements[0])
			{
				$North = $elements[0];
			}
			if((float)$South > (float)$elements[1])
			{
				$South = $elements[1];
			}
			if((float)$East < (float)$elements[2])
			{
				$East = $elements[2];
			}
			if((float)$West > (float)$elements[3])
			{
				$West = $elements[3];
			}
		}
		#var_dump(array( $North, $South, $East, $West));
		return array( $North, $South, $East, $West);
	}

	function FindBox($points = array())
	{
		$North = NULL;
		$South = NULL;
		$East = NULL;
		$West = NULL;
		foreach($points as $elements)
		{
			#var_dump($elements);
			if(@$elements['long'] == '' || @$elements['lat'] == '')
			{
				continue;
			}
			if($North == NULL)
			{
				$North = $elements['lat'];
			}
			if($South == NULL)
			{
				$South = $elements['lat'];
			}

			if($East == NULL)
			{
				$East = $elements['long'];
			}
			if($West == NULL)
			{
				$West = $elements['long'];
			}

			if((float)$North < (float)$elements['lat'])
			{
				$North = $elements['lat'];
			}
			if((float)$South > (float)$elements['lat'])
			{
				$South = $elements['lat'];
			}
			if((float)$East < (float)$elements['long'])
			{
				$East = $elements['long'];
			}
			if((float)$West > (float)$elements['long'])
			{
				$West = $elements['long'];
			}
		}
		#var_dump(array( $North, $South, $East, $West));
		return array( $North, $South, $East, $West);
	}

	function distance($lat1, $lon1, $lat2, $lon2, $unit)
	{
		$theta = $lon1 - $lon2;
		$dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
		$dist = acos($dist);
		$dist = rad2deg($dist);
		$miles = $dist * 60 * 1.1515;
		$unit = strtoupper($unit);
		if ($unit == "K") {
			$ret = ($miles * 1.609344);
		}
		elseif ($unit == "N")
		{
			$ret = ($miles * 0.8684);
		}
		else
		{
			$ret = $miles;
		}
		if($ret < 100)
		{
			$distance_calc = 1000;
			$minLodPix = 768;
		}

		if($ret > 100 && $ret < 400)
		{
			$distance_calc = 1000;
			$minLodPix = 768;
		}

		if($ret > 400)
		{
			$distance_calc = 3000;
			$minLodPix = 512;
		}
		return array($distance_calc, $minLodPix, $ret);
	}
	
	function get_point($latitude, $longitude, $bearing, $distance, $unit = 'm')
	{
		//	Radius of earth.  3959 miles or 6371 kilometers.
		//  Pass in coordinates in Decimal form.  Example: -41.5786214
		if ($unit == 'm')
		{
			$radius = 3959;
		}
		elseif ($unit == 'km')
		{
			$radius = 6371;
		}

		//	New latitude in degrees.
		$new_latitude = rad2deg(asin(sin(deg2rad($latitude)) * cos($distance / $radius) + cos(deg2rad($latitude)) * sin($distance / $radius) * cos(deg2rad($bearing))));
				
		//	New longitude in degrees.
		$new_longitude = rad2deg(deg2rad($longitude) + atan2(sin(deg2rad($bearing)) * sin($distance / $radius) * cos(deg2rad($latitude)), cos($distance / $radius) - sin(deg2rad($latitude)) * sin(deg2rad($new_latitude))));

		//  Assign new latitude and longitude to an array to be returned to the caller.
		$coord['latitude'] = $new_latitude;
		$coord['longitude'] = $new_longitude;

		return $coord;
	}

}
