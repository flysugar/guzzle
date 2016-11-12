<?php
set_time_limit(60); // na razie minuta

// SQL na App Engine
// include 'config.db-ae.inc.php';
include 'bla.inc.php';

$mysqli = false;

// if ( $_SERVER['REMOTE_ADDR']=='127.0.0.1' ||  $_SERVER['REMOTE_ADDR']=='localhost') {
// 	include 'config.db-local.inc.php';
// } else {
// 	include 'config.db-ae.inc.php';
// }
if ( in_array($_SERVER['SERVER_NAME'], array('127.0.0.1', 'localhost')) ) {
	print "[local]\n";
	$mysqli = mysqli_connect('127.0.0.1', 'test', 'test', 'test') or die(mysqli_error());
} else {
	print "[ae]\n";
	$mysqli = mysqli_connect(null, 'test', 'test', 'test', null, '/cloudsql/blarides-149209:europe-west1:blarides') or die(mysqli_error());
}

/* check connection */
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}
$mysqli->set_charset("utf8");
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Guzzle Bla Ride Details</title>
</head>

<pre>
<?php
$res = $mysqli->query("SELECT ride_link, ride_id FROM bla_rides WHERE ride_date='2016-11-11' AND ride_duration IS NULL");

while ($row = $res->fetch_assoc()) {
	print $row['ride_id'] . ':' . $row['ride_link'] . "\n";
	db_update_ride_details($mysqli, $row['ride_link'], $row['ride_id']);
	ob_flush();
	flush();
}
?>
</pre>
</html>
