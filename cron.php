<?php
/*
	ARGS: 	job_id [1: store_rides, 2: ride_details, 3: user_details]
			no_of_cities: intval, %2==0
*/
$job_id = intval($_REQUEST['job_id']);
if ( !in_array($job_id, array(1,2,3)) ) {
	http_response_code(500);
	syslog(LOG_ERR, "bad job id: " . $job_id);
	exit;
}

http_response_code(200);

syslog(LOG_INFO, "Running job_id: $job_id");

include 'bla.inc.php';
$mysqli = false;

if ( in_array($_SERVER['SERVER_NAME'], array('127.0.0.1', 'localhost')) ) {
	syslog(LOG_DEBUG, "Running on local DB");
	$mysqli = mysqli_connect('127.0.0.1', 'test', 'test', 'test') or die(mysqli_error());
} else {
	syslog(LOG_DEBUG, "Running on app engine DB");
	$mysqli = mysqli_connect(null, 'test', 'test', 'test', null, '/cloudsql/blarides-149209:europe-west1:blarides') or die(mysqli_error());
}

/* check connection */
if (mysqli_connect_errno()) {
    syslog(LOG_ERR, "Connect failed: " . mysqli_connect_error());
    exit();
}
$mysqli->set_charset("utf8");

// dzisiejsza data
$data_obj = new DateTime( date("Y-m-d") );
// jutro
// $data_jutro = $data_obj->modify("+1 day")->format("d/m/Y");

switch ($job_id) {
	case 1:
			$no_of_cities = 100;
			if ( isset($_REQUEST['no_of_cities']) && intval($_REQUEST['no_of_cities'])>1 && intval($_REQUEST['no_of_cities'])%2==0) {
				$no_of_cities = intval($_REQUEST['no_of_cities']);
			}
			$top_cities = db_get_cities($mysqli, $no_of_cities);
			// dzisiaj
			$data = $data_obj->format("d/m/Y");

			syslog(LOG_INFO, "fetching rides for $data... cities" . implode(',', $top_cities));

			for ($i=0;$i<count($top_cities);$i++) {
				$city_from = $top_cities[$i]['name'];
				for ($j=0;$j<count($top_cities);$j++) {
					$city_to = $top_cities[$j]['name'];
					if ($city_from==$city_to) continue;
					syslog(LOG_INFO, "FROM $city_from to $city_to: ");

					db_store_rides($mysqli, $city_from, $city_to, $data);
				}
			}
			break;
	case 2:
			$data = $data_obj->format("Y-m-d");
			$res = $mysqli->query("SELECT ride_link, ride_id FROM bla_rides WHERE ride_date='$data' AND ride_duration IS NULL");

			while ($row = $res->fetch_assoc()) {
				syslog(LOG_INFO, $row['ride_id'] . ':' . $row['ride_link']);
				db_update_ride_details($mysqli, $row['ride_link'], $row['ride_id']);
			}
			break;

	case 3:
			$data = $data_obj->format("Y-m-d");
			$res = $mysqli->query("SELECT ride_link, ride_id FROM bla_rides WHERE ride_date='$data' AND user_avg_rating IS NULL");
			while ( $row = $res->fetch_assoc() ) {
				syslog(LOG_INFO, $row['ride_id'] . ':' . $row['ride_link']);
				db_update_user_details($mysqli, $row['ride_link'], $row['ride_id']);
			}
			break;
}

syslog(LOG_INFO, "job ended on " . date("Y-m-d, h:m:s"));
?>