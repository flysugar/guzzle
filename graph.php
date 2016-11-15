<?php
$mysqli = false;

if ( in_array($_SERVER['SERVER_NAME'], array('127.0.0.1', 'localhost')) ) {
	// echo "Running on local DB";
	$mysqli = mysqli_connect('127.0.0.1', 'test', 'test', 'test') or die(mysqli_error());
} else {
	// echo "Running on app engine DB";
	$mysqli = mysqli_connect(null, 'test', 'test', 'test', null, '/cloudsql/blarides-149209:europe-west1:blarides') or die(mysqli_error());
}
/* check connection */
if (mysqli_connect_errno()) {
    echo "Connect failed: " . mysqli_connect_error();
    exit();
}
$mysqli->set_charset("utf8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>graphs</title>
	<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
	<script type="text/javascript">
		var myMapsApiKey = 'AIzaSyB6w38jBtnwSXDeP_VxkJDirebyhKqgqgo';
		google.charts.load('upcoming', {'packages':['geochart', 'calendar'], mapsApiKey: myMapsApiKey});
		google.charts.setOnLoadCallback(drawMarkersMap);

		function drawMarkersMap() {

			var options = {
				region: 'PL',
				resolution: 'provinces',
				displayMode: 'markers',
				colorAxis: {colors: ['green', 'red']}
			};

			var data = google.visualization.arrayToDataTable([
				['City',   'Driver Fee (to)'],
<?php 
$sql = "select ride_to as miasto, round(avg(driver_fee), 2) as oplata from bla_rides WHERE driver_fee > 0 group by ride_to order by avg(driver_fee) DESC LIMIT 10";
if ( $res = $mysqli->query($sql) ) {
	while ($row = $res->fetch_assoc()) {
		echo "['{$row['miasto']}', {$row['oplata']}],";
	}
}
?>
			]);
	        var chart = new google.visualization.GeoChart(document.getElementById('driver_fee_to'));
	        chart.draw(data, options);

			var data_from = google.visualization.arrayToDataTable([
				['City',   'Driver Fee (from)'],
<?php 
$sql = "select ride_from as miasto, round(avg(driver_fee), 2) as oplata from bla_rides WHERE driver_fee > 0 group by ride_from order by avg(driver_fee) DESC LIMIT 10";
if ( $res = $mysqli->query($sql) ) {
	while ($row = $res->fetch_assoc()) {
		echo "['{$row['miasto']}', {$row['oplata']}],";
	}
}
?>
			]);
			var chart2 = new google.visualization.GeoChart(document.getElementById('driver_fee_from'));
	        chart2.draw(data_from, options);

			var data_bla_from = google.visualization.arrayToDataTable([
				['City',   'Bla Fee (from)'],
<?php 
$sql = "select ride_from as miasto, round(avg(bla_fee), 2) as oplata from bla_rides WHERE bla_fee > 0 group by ride_from order by avg(bla_fee) DESC LIMIT 10";
if ( $res = $mysqli->query($sql) ) {
	while ($row = $res->fetch_assoc()) {
		echo "['{$row['miasto']}', {$row['oplata']}],";
	}
}
?>
			]);
			var chart3 = new google.visualization.GeoChart(document.getElementById('bla_fee_from'));
	        chart3.draw(data_bla_from, options);

			var data_bla_to = google.visualization.arrayToDataTable([
				['City',   'Bla Fee (to)'],
<?php 
$sql = "select ride_to as miasto, round(avg(bla_fee), 2) as oplata from bla_rides WHERE bla_fee > 0 group by ride_to order by avg(bla_fee) DESC LIMIT 10";
if ( $res = $mysqli->query($sql) ) {
	while ($row = $res->fetch_assoc()) {
		echo "['{$row['miasto']}', {$row['oplata']}],";
	}
}
?>
			]);
			var chart4 = new google.visualization.GeoChart(document.getElementById('bla_fee_to'));
	        chart4.draw(data_bla_to, options);


			var chart5 = new google.visualization.GeoChart(document.getElementById('bla_fee_to_min')).draw(google.visualization.arrayToDataTable([
					['City',   'Bla Fee (to)'],
<?php 
$sql = "select ride_to as miasto, round(avg(bla_fee), 2) as oplata from bla_rides WHERE bla_fee > 0 group by miasto order by avg(bla_fee) ASC LIMIT 10";
if ( $res = $mysqli->query($sql) ) {
	while ($row = $res->fetch_assoc()) {
		echo "['{$row['miasto']}', {$row['oplata']}],";
	}
}
?>
					]),
				options);

			var chart6 = new google.visualization.GeoChart(document.getElementById('bla_fee_from_min')).draw(google.visualization.arrayToDataTable([
					['City',   'Bla Fee (from) min'],
<?php 
$sql = "select ride_from as miasto, round(avg(bla_fee), 2) as oplata from bla_rides WHERE bla_fee > 0 group by miasto order by avg(bla_fee) ASC LIMIT 10";
if ( $res = $mysqli->query($sql) ) {
	while ($row = $res->fetch_assoc()) {
		echo "['{$row['miasto']}', {$row['oplata']}],";
	}
}
?>
					]),
				options);

			var chart7 = new google.visualization.GeoChart(document.getElementById('driver_fee_from_min')).draw(google.visualization.arrayToDataTable([
					['City',   'Driver Fee (from) min'],
<?php 
$sql = "select ride_from as miasto, round(avg(driver_fee), 2) as oplata from bla_rides where driver_fee>0 group by miasto order by avg(driver_fee) ASC LIMIT 10";
if ( $res = $mysqli->query($sql) ) {
	while ($row = $res->fetch_assoc()) {
		echo "['{$row['miasto']}', {$row['oplata']}],";
	}
}
?>
					]),
				options);

			var chart8 = new google.visualization.GeoChart(document.getElementById('driver_fee_to_min')).draw(google.visualization.arrayToDataTable([
					['City',   'Driver Fee (to) min'],
<?php 
$sql = "select ride_to as miasto, round(avg(driver_fee), 2) as oplata from bla_rides where driver_fee>0 group by miasto order by avg(driver_fee) ASC LIMIT 10";
if ( $res = $mysqli->query($sql) ) {
	while ($row = $res->fetch_assoc()) {
		echo "['{$row['miasto']}', {$row['oplata']}],";
	}
}
?>
					]),
				options);

	       var dataTable = new google.visualization.DataTable();
	       dataTable.addColumn({ type: 'date', id: 'Date' });
	       dataTable.addColumn({ type: 'number', id: 'Won/Loss' });
	       dataTable.addRows([
<?php 
$sql = "select year(ride_date) as y, month(ride_date)-1 as m, day(ride_date) as d, count(ride_id) as c from bla_rides group by ride_date";
if ( $res = $mysqli->query($sql) ) {
	while ($r = $res->fetch_assoc()) {
		echo "[ new Date({$r['y']}, {$r['m']}, {$r['d']}), {$r['c']} ],\n";
	}
}
?>
	        ]);

	       var chart = new google.visualization.Calendar(document.getElementById('calendar'));

	       var options = {
	         title: "Ilość Przejazdów",
	         height: 350,
	         daysOfWeek: 'MTWTFSS'
	       };

	       chart.draw(dataTable, options);
		}
    </script>
    <style type="text/css">
body {
	font-family: Roboto;
}

div.map {
	width: 600px;
}
    </style>
</head>
<body>

	<div id='calendar'></div>

<h2>MAX(10)</h2>
<table>
<tr>
	<th>avg(driver_fee) from max(10)</th>
	<th>avg(driver_fee) to max(10)</th>
</tr>
<tr>
	<td><div class="map" id="driver_fee_from"></div></td>
	<td><div class="map" id="driver_fee_to"></div></td>
</tr>
<tr>
	<th>avg(bla_fee) from max(10)</th>
	<th>avg(bla_fee) to max(10)</th>
</tr>
<tr>
	<td><div class="map" id="bla_fee_from"></div></td>
	<td><div class="map" id="bla_fee_to"></div></td>
</tr>
</table>

<h2>MIN(10)</h2>
<table>
<tr>
	<th>avg(driver_fee) from min(10)</th>
	<th>avg(driver_fee) to min(10)</th>
</tr>
<tr>
	<td><div class="map" id="driver_fee_from_min"></div></td>
	<td><div class="map" id="driver_fee_to_min"></div></td>
</tr>
<tr>
	<th>avg(bla_fee) from min(10)</th>
	<th>avg(bla_fee) to min(10)</th>
</tr>
<tr>
	<td><div class="map" id="bla_fee_from_min"></div></td>
	<td><div class="map" id="bla_fee_to_min"></div></td>
</tr>
</table>
	
<?php // print_r($_GET); ?>
</body>
</html>