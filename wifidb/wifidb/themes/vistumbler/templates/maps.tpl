<html>
<head>
	<meta name="viewport" content="initial-scale=1.0, user-scalable=yes" />
	<meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
	<link href="https://code.google.com/apis/maps/documentation/javascript/examples/default.css" rel="stylesheet" type="text/css" />
	<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?sensor=false"></script>
	<style type="text/css">
		.maps_label {background-color:#ffffff;font-weight:bold;border:2px #006699 solid;}
	</style>
	<script type="text/javascript">
		function initialize()
		{
			var myOptions = {
				zoom: 16,
				center: new google.maps.LatLng({$lat}, {$long}),
				mapTypeId: google.maps.MapTypeId.ROADMAP
			}
			var open = 'https://raw.githubusercontent.com/RIEI/Vistumbler/master/VistumblerMDB/Images/open.png';
			var wep = 'https://raw.githubusercontent.com/RIEI/Vistumbler/master/VistumblerMDB/Images/secure-wep.png';
			var secure = 'https://raw.githubusercontent.com/RIEI/Vistumbler/master/VistumblerMDB/Images/secure.png';
			var map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
			{$maps_pointer}
		}
		window.onload = initialize;
	</script>
</head>
<body>
<div style="width:100%;height:100%;" id="map_canvas"></div>
</body>
</html>
