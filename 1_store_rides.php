<?php
@set_time_limit(60); // na razie minuta

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

// SQL na App Engine
// include 'config.db-ae.inc.php';
include 'bla.inc.php';

$no_of_cities = 2;
if (
		$_SERVER['REQUEST_METHOD']=='POST' &&
		intval($_POST['no_of_cities'])>1 &&
		intval($_POST['no_of_cities']) % 2 == 0
	) {
	$no_of_cities = intval($_POST['no_of_cities']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Guzzle Bla Rides</title>
</head>
<body>

<?php
show_some_data($mysqli);
?>

<form method="POST">
	No of top cities (even, min: 2): <input type="text" value="<?= $no_of_cities ?>" name="no_of_cities" /> <input type="submit" value="get" />
</form>

<pre>
<?php
if ( $_SERVER['REQUEST_METHOD']=='POST' && isset($no_of_cities) ) {
	$top_cities = db_get_cities($mysqli, $no_of_cities);
	$data_obj = new DateTime( date("Y-m-d") );

	// dzisiaj
	$data = $data_obj->format("d/m/Y");

	// jutro
	// $data_jutro = $data_obj->modify("+1 day")->format("d/m/Y");

	print "fetching rides for $data... cities:";
	print_r($top_cities);

	for ($i=0;$i<count($top_cities);$i++) {
		$city_from = $top_cities[$i]['name'];
		for ($j=0;$j<count($top_cities);$j++) {
			$city_to = $top_cities[$j]['name'];
			if ($city_from==$city_to) continue;
			print "FROM $city_from to $city_to: ";

			db_store_rides($mysqli, $city_from, $city_to, $data);
		}
	}
}
?>
</pre>
</html>
