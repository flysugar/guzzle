<?php
set_time_limit(60); // na razie minuta

// SQL na App Engine
include 'config.db-ae.inc.php';
include 'bla.inc.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
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
	<title>Guzzle Bla Users</title>
</head>

<pre>
<?php

$res = $mysqli->query("SELECT ride_link, ride_id FROM bla_rides WHERE ride_date='2016-11-11' AND user_avg_rating IS NULL");
while ( $row = $res->fetch_assoc() ) {
	print $row['ride_id'] . ':' . $row['ride_link'] . "\n";
	db_update_user_details($mysqli, $row['ride_link'], $row['ride_id']);
	ob_flush();
	flush();
}
?>
</pre>
</html>
