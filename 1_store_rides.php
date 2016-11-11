<?php
set_time_limit(60); // na razie minuta

// if ( $_SERVER['REMOTE_ADDR']=='127.0.0.1' ||  $_SERVER['REMOTE_ADDR']=='localhost') {
// 	include 'config.db-local.inc.php';
// } else {
// 	include 'config.db-ae.inc.php';
// }

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
	<title>Guzzle Bla Rides</title>
</head>

<pre>
<?php
function db_store_rides($mysqli, $city_from, $city_to, $data, $limit=50) {
	// $stmt_del = $mysqli->prepare("DELETE FROM bla_rides WHERE ride_id=?");
	$stmt_add = $mysqli->prepare("INSERT IGNORE INTO bla_rides (ride_id, ride_from, ride_to, ride_date, username, ride_link, user_age, price_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
	// get first 50 rides sorted by price
	if ( $rides = get_rides($city_from, $city_to, $data, $limit) ) {
		// print_r($rides);

		foreach ($rides as $ride) {
			// print_r($ride);
			print "Adding " . implode('|', $ride) . '...';
			// $stmt_del->bind_param('i', $ride['ride_id']);
			// $stmt_del->execute();

			// print $ride['price_type'];
			// exit;
			// dla MySQL musimy zrobic RRRR-MM-DD
			$ride_date = explode('/', $ride['ride_date']);
			$ride_date = implode('-', array($ride_date[2], $ride_date[1], $ride_date[0]));

			$stmt_add->bind_param('isssssis',
							$ride['ride_id'],
							$ride['ride_from'],
							$ride['ride_to'],
							$ride_date,
							$ride['username'],
							$ride['link'],
							$ride['age'],
							$ride['price_type']
						);

			if ( $stmt_add->execute() ) {
				printf("%d Row inserted.\n", $stmt_add->affected_rows);
				print "DONE\n";
			} else {
				print "problems with add statement..." . $stmt_add->error;
			}
			ob_flush();
        	flush();
		}
		print count($rides) . " added.\n";
	}
}

$top_20_cities = db_get_cities(100);
$data_obj = new DateTime( date("Y-m-d") );

// dzisiaj
$data = $data_obj->format("d/m/Y");

// jutro
// $data_jutro = $data_obj->modify("+1 day")->format("d/m/Y");

for ($i=0;$i<count($top_20_cities);$i++) {
	$city_from = $top_20_cities[$i]['name'];
	for ($j=0;$j<count($top_20_cities);$j++) {
		$city_to = $top_20_cities[$j]['name'];
		if ($city_from==$city_to) continue;
		print "FROM $city_from to $city_to: ";

		db_store_rides($mysqli, $city_from, $city_to, $data);
	}
}
exit;

$top_100_cities = db_get_cities(2);
$city_from = current($top_100_cities);
$data = date("d/m/Y");

// $stmt_del = $mysqli->prepare("DELETE FROM bla_rides WHERE ride_id=?");
$stmt_add = $mysqli->prepare("INSERT IGNORE INTO bla_rides (ride_id, ride_from, ride_to, ride_date, username, ride_link, user_age, price_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
while ($city_to = next($top_100_cities)) {
	print 'FROM ' . $city_from['name'] . ' to ' . $city_to['name'] . '<br/>';
	// get first 50 rides sorted by price
	if ( $rides = get_rides($city_from['name'], $city_to['name'], $data, 50) ) {
		// print_r($rides);

		foreach ($rides as $ride) {
			// print_r($ride);
			print "Adding " . implode('|', $ride) . '...';
			// $stmt_del->bind_param('i', $ride['ride_id']);
			// $stmt_del->execute();

			// print $ride['price_type'];
			// exit;
			// dla MySQL musimy zrobic RRRR-MM-DD
			$ride_date = explode('/', $ride['ride_date']);
			$ride_date = implode('-', array($ride_date[2], $ride_date[1], $ride_date[0]));
			$stmt_add->bind_param('isssssis',
							$ride['ride_id'],
							$ride['ride_from'],
							$ride['ride_to'],
							$ride_date,
							$ride['username'],
							$ride['link'],
							$ride['age'],
							$ride['price_type']
						);

			if ( $stmt_add->execute() ) {
				printf("%d Row inserted.\n", $stmt_add->affected_rows);
				print "DONE\n";
			} else {
				print "problems with add statement..." . $stmt_add->error;
			}
			ob_flush();
        	flush();
		}
		print count($rides) . " added.\n";
	}
}
?>
</pre>
</html>
