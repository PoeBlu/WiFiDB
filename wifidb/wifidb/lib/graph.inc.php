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

ou should have received a copy of the GNU General Public License along with this program;
if not, write to the

   Free Software Foundation, Inc.,
   59 Temple Place, Suite 330,
   Boston, MA 02111-1307 USA
*/

$lastedit = "2009-Mar-15";
$ver_graph=array(
			"graphs"=>array(
							"wifiline"			=> "2.0.4",
							"wifibar" 			=> "2.0.4",
							"imagegrid"			=> "1.0",
							"genboth"			=> "1.0"
							),
			);

class graphs
{
	function genboth()
	{
		include('../lib/config.php');
	echo "Got include_onces<br>";
		mysql_select_db($db,$conn);
	echo "Connected to Wifi<br>";
		$this->sql = "SELECT `id` FROM $wtable";
		$this->results = mysql_query($this->sql, $conn) or die(mysql_error());
	echo "Queried Pointer Table<br>";
		$n=0;
		while ($newArray = mysql_fetch_array($this->results))
		{
			$id_p[$n]=$newArray['id'];
			$n++;
		}
	echo "Built array of AP ID's<br>";
		#Start line graph
		foreach($id_p as $val)
		{
			mysql_select_db($db,$conn);
		echo "Start for ID: ".$val."<br>";
			$this->sql = "SELECT * FROM `$wtable` WHERE `id`='$val'";
			$this->results = mysql_query($this->sql, $conn) or die(mysql_error());
		echo "Queried Pointer table for Info<br>";

			$newArray = mysql_fetch_array($this->results);

			$this->ssid = $newArray['ssid'];
			$this->mac = $newArray['mac'];
			$this->man = $newArray['manuf'];
			$this->sectype = $newArray['sectype'];
			$this->radio = $newArray['radio'];
			$this->chan = $newArray['chan'];

			if ($this->sectype == "1"){$this->auth = "Open";$this->encry="None";}
			elseif($this->sectype == "2"){$this->auth = "Open";$this->encry="WEP";}
			elseif($this->sectype == "3"){$this->auth = "WPA-Personal";$this->encry="TKIP";}
		echo "Created Auth and Encry info from Sectype<br>";
			$source = $this->ssid."-".$this->mac."-".$this->sectype."-".$this->radio."-".$this->chan;

			mysql_select_db($db_st,$conn);
		echo "Connected to Wifi_ST<br>";
			$this->result = mysql_query("SELECT * FROM `$source`", $conn) or die(mysql_error());
			$n=1;
			while ($this->field = mysql_fetch_array($this->result))
			{
				$id[$n]=$this->field['id'];
				$btx[$n]=$this->field['btx'];
				$otx[$n]=$this->field['otx'];
				$nt[$n]=$this->field['nt'];
				$label[$n]=$this->field['label'];
				$sig[$n]=$this->field['sig'];
				$user[$n]=$this->field['user'];
			echo "Got data from ST table<br>";
				$tmp=explode("-",$sig[$n]);
				$this->sigtmp=$sig[$n];

				$sig[$n]="";
				foreach($tmp as $val)
				{
					$tm=explode(",",$val);
					$sig[$n].=$tm[1]."-";
				}
			echo "Cleaned up Signal data for use in 2D graph<br>	[".$sig[$n]."]";
			$n++;
			}
			$n=0;
			foreach($id as $val)
			{
			echo "Start graph gen for Row ID: ".$val."<br>";
			$source_row = $this->ssid."-".$this->mac."-".$this->sectype."-".$this->radio."-".$this->chan."-row-".$val;
			echo $source_row."<br>";
			$file_b="../graph/waps/".$source_row.".png";
			$file_v="../graph/waps/".$source_row."v.png";
			if (file_exists($file_v) and file_exists($file_b))
			{
				echo "File exists, not generating Line graph for this AP's Row<br>";
				continue;
			}
			else{
				if (!file_exists($file_v)){
				graphs::wifigraphline($this->ssid, $this->mac, $this->man, $this->auth, $this->encry, $this->radio, $this->chan, $lat[$n], $long[$n], $btx[$n], $otx[$n], $fa[$n], $lu[$n], $nt[$n], $label[$n], $sig[$n], $source_row );
				echo "Generated Line Graph<br>";
				}
				if(!file_exists($file_b)){
				graphs::wifigraphbar($this->ssid, $this->mac, $this->man, $this->auth, $this->encry, $this->radio, $this->chan, $lat[$n], $long[$n], $btx[$n], $otx[$n], $fa[$n], $lu[$n], $nt[$n], $label[$n], $sig[$n], $source_row );
				echo "Generated Bar Graph<br>";
				}
			}
				unset($this->results);
				unset($this->sql);
				unset($sig);
			$n++;
			}
		}
	#end gen graph
	}

#==============================================================================================================================================================#
#													Image Grid Function												         #
#==============================================================================================================================================================#

	function imagegrid($image, $w, $h, $s, $color)
	{
		$ws = $w/$s;
		$hs = $h/$s;
		for($iw=0; $iw < $ws; ++$iw)
		{
			imageline($image, ($iw-0)*$s, 60 , ($iw-0)*$s, $w , $color);
		}
		for($ih=0; $ih<$hs; ++$ih)
		{
			imageline($image, 0, $ih*$s, $w , $ih*$s, $color);
		}
	}

#==============================================================================================================================================================#
#													WiFi Graph Linegraph												         #
#==============================================================================================================================================================#

	function wifigraphline($ssid, $mac, $man, $auth, $encry, $radio, $chan, $lat, $long, $BTx, $OTx, $FA, $LU, $NT, $label, $sig, $date, $bgc, $linec="rand", $text="rand")
	{
		$n=0;
		$nn=1;
		$y=20;
		$yy=21;
		$u=20;
		$uu=21;
		if ($text == 'rand' or $text == '')
		{
			$tr = rand(50,200);
			$tg = rand(50,200);
			$tb = rand(50,200);
		}else
		{
			$text_color = explode(':', $text);
			$tr=$text_color[0];
			$tg=$text_color[1];
			$tb=$text_color[2];
		}
		if ($linec == 'rand' or $linec == '')
		{
			$r = rand(50,200);
			$g = rand(50,200);
			$b = rand(50,200);
		}else
		{
			$line_color = explode(':', $linec);
			$r=$line_color[0];
			$g=$line_color[1];
			$b=$line_color[2];
		}
		if ($ssid==""or$ssid==" " )
		{
			$ssid="UNNAMED";
		}
			$signal = explode("-", $sig);
			$count = count($signal);
			$c1 = 'SSID: '.$ssid.'   Channel: '.$chan.'   Radio: '.$radio.'   Network: '.$NT.'   OTx: '.$OTx;
			$check = strlen($c1);
			$c2 = 'Mac: '.$mac.'   Auth: '.$auth.' '.$encry.'   BTx: '.$BTx.'   Lat: '.$lat.'   Long: '.$long;
			$check2 = strlen($c2);
			$c3 = 'Manuf: '.$man.'   Label: '.$label.'   First: '.$FA.'   Last: '.$LU;
			$check3 = strlen($c3);
			#FIND OUT IF THE IMG NEEDS TO BE WIDER
		if(900 < ($count*6.2))
		{
			$Height = 480;
			$wid    = ($count*6.2)+40;
		}
		elseif(900 < ($check3*6))
		{
			$Height = 480;
			$wid    = ($check3*6)+40;
		}
		elseif(900 < ($check2*6.2))
		{
			$Height = 480;
			$wid    = ($check2*6.2)+40;
		}
		elseif(900 < ($check*6))
		{
			$Height = 480;
			$wid    = ($check*6)+40;
		}
		else
		{
			$wid    = 900;
			$Height = 480;
		}
		$img    = ImageCreateTrueColor($wid, $Height);
		$bgcc	= explode(":",$bgc);
		$bg     = imagecolorallocate($img, $bgcc[0], $bgcc[1], $bgcc[2]);
		if($bgc !== "000:000:000")
		{
			$grid   = imagecolorallocate($img,0,0,0);
		}else
		{
			$grid   = imagecolorallocate($img,255,255,255);
		}
		$tcolor = imagecolorallocate($img, $tr, $tg, $tb);
		$col = imagecolorallocate($img, $r, $g, $b);
		imagefill($img,0,0,$bg); #PUT HERE SO THAT THE TEXT DOESNT HAVE BLACK FILLINGS (eww)
		imagestring($img, 4, 21, 3, $c1, $tcolor);
		imagestring($img, 4, 21, 23, $c2, $tcolor);
		imagestring($img, 4, 21, 43, $c3, $tcolor);
		#signal strenth numbers--
		$p=460;
		$I=0;
		while($I<105)
		{
			imagestring($img, 4, 3, $p, $I, $tcolor);
			$I=$I+5;
			$p=$p-20;
		}
		#end signal strenth numbers--
		imagesetstyle($img, array($bg, $grid));
		$counting=$count-1;
		$n=0;
		$nn=1;
		imagesetstyle($img,array($bg,$grid));
		graphs::imagegrid($img,$wid,$Height,19.99,$grid);
		while($count>0)
		{
			#if($nn==$counting+1){break;}
			imageline($img, $y ,459-($signal[$n]*4), $y=$y+6 ,459-($signal[$nn]*4) ,$col);
			imageline($img, $u ,460-($signal[$n]*4), $u=$u+6 ,460-($signal[$nn]*4) ,$col);
			imageline($img, $yy ,459-($signal[$n]*4), $yy=$yy+6 ,459-($signal[$nn]*4) ,$col);
			imageline($img, $uu ,460-($signal[$n]*4), $uu=$uu+6 ,460-($signal[$nn]*4) ,$col);
			$n++;
			$nn++;
			$count--;
		}
		$name= $GLOBALS['wifidb_install'].'/out/graph/'.$date.'v.png';
		echo '<h1>'.$ssid.'</h1><br>';
		echo '<img src="'.$name.'"><br />';
		ImagePNG($img, $name);
		ImageDestroy($img);
	}

	#==============================================================================================================================================================#
	#													WiFi Graph Bargraph													         #
	#==============================================================================================================================================================#
	function wifigraphbar($ssid, $mac, $man, $auth, $encry, $radio, $chan, $lat, $long, $BTx, $OTx, $FA, $LU, $NT, $label, $sig, $date, $bgc, $linec="rand", $text="rand")
	{
		$p=460;
		$I=0;

		if ($text == 'rand' or $text == '')
		{
			$tr = rand(50,200);
			$tg = rand(50,200);
			$tb = rand(50,200);
		}else
		{
			$text_color = explode(':', $text);
			$tr=$text_color[0];
			$tg=$text_color[1];
			$tb=$text_color[2];
		}
		if ($linec == 'rand' or $linec == '')
		{
			$r = rand(50,200);
			$g = rand(50,200);
			$b = rand(50,200);
		}else
		{
			$line_color = explode(':', $linec);
			$r=$line_color[0];
			$g=$line_color[1];
			$b=$line_color[2];
		}
		if ($ssid==""or$ssid==" ")
		{
			$ssid="UNNAMED";
		}
		$signal = explode("-", $sig);
		$count = (count($signal)-1);
		$c1 = 'SSID: '.$ssid.'   Channel: '.$chan.'   Radio: '.$radio.'   Network: '.$NT.'   OTx: '.$OTx;
		$check = strlen($c1);
		$c2 = 'Mac: '.$mac.'   Auth: '.$auth.' '.$encry.'   BTx: '.$BTx.'   Lat: '.$lat.'   Long: '.$long;
		$check2 = strlen($c2);
		$c3 = 'Manuf: '.$man.'   Label: '.$label.'   First: '.$FA.'   Last: '.$LU;
		$check3 = strlen($c3);
		#FIND OUT IF THE IMG NEEDS TO BE WIDER
		if(900 < ($count*3))
		{
			$Height = 480;
			$wid    = ($count*3)+38;
		}
		elseif(900 < ($check3*8))
		{
			$Height = 480;
			$wid    = ($check3*8)+40;
		}
		elseif(900 < ($check2*8))
		{
			$Height = 480;
			$wid    = ($check2*8)+40;
		}
		elseif(900 < ($check*8))
		{
			$Height = 480;
			$wid    = ($check*8)+40;
		}
		else
		{
			$wid    = 900;
			$Height = 480;
		}
		$img    = ImageCreateTrueColor($wid, $Height);
		$bgcc	= explode(":",$bgc);
		$bg     = imagecolorallocate($img, $bgcc[0], $bgcc[1], $bgcc[2]);
		if($bgc !== "000:000:000")
		{
			$grid   = imagecolorallocate($img,0,0,0);
		}else
		{
			$grid   = imagecolorallocate($img,255,255,255);
		}
		$tcolor = imagecolorallocate($img, $tr, $tg, $tb);
		$col = imagecolorallocate($img, $r, $g, $b);
		imagefill($img,0,0,$bg); #PUT HERE SO THAT THE TEXT DOESNT HAVE BLACK FILLINGS (eww)
		imagestring($img, 4, 21, 3, $c1, $tcolor);
		imagestring($img, 4, 21, 23, $c2, $tcolor);
		imagestring($img, 4, 21, 43, $c3, $tcolor);
		#signal strenth numbers--
		while($I<105)
		{
			imagestring($img, 4, 3, $p, $I, $tcolor);
			$I=$I+5;
			$p=$p-20;
		}
		#end signal strenth numbers--
		imagesetstyle($img, array($bg, $grid));
		$X=20;
		$n=0;
		imagesetstyle($img,array($bg,$grid));
		graphs::imagegrid($img,$wid,$Height,19.99,$grid);
		while($count>=0)
		{
			#if($n==$count+1){break;}
			if ($signal[$n]==0)
			{
				$signal[$n]=1;
				imageline($img, $X ,459, $X, 459-($signal[$n]), $col);
				$X++;
				imageline($img, $X ,459, $X, 459-($signal[$n]), $col);
				$X=$X+2;
			}
			else
			{
				imageline($img, $X ,459, $X, 459-($signal[$n]*4), $col);
				$X++;
				imageline($img, $X ,459, $X, 459-($signal[$n]*4), $col);
				$X=$X+2;
			}
			$n++;
			$count--;
		}
		$name = $GLOBALS['wifidb_install'].'/out/graph/'.$date.'.png';
		echo '<h1>'.$ssid.'</h1><br>';
		echo '<img src="'.$name.'"><br />';
		ImagePNG($img, $name);
		ImageDestroy($img);
	}

	function timeline($bgcolor = "",$lcolor = "", $start = "", $end = "")
	{
		if ($text == 'rand')
		{
			$tr = rand(50,200);
			$tg = rand(50,200);
			$tb = rand(50,200);
		}else
		{
			$text_color = explode(':', $text);
			$tr=$text_color[0];
			$tg=$text_color[1];
			$tb=$text_color[2];
		}
		if ($linec == 'rand')
		{
			$r = rand(50,200);
			$g = rand(50,200);
			$b = rand(50,200);
		}else
		{
			$line_color = explode(':', $linec);
			$r=$line_color[0];
			$g=$line_color[1];
			$b=$line_color[2];
		}
	}
}#end Graphs CLASS
?>