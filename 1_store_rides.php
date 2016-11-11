<?php
set_time_limit(60); // na razie minuta

if ( $_SERVER['REMOTE_ADDR']=='127.0.0.1' ||  $_SERVER['REMOTE_ADDR']=='localhost') {
	include 'config.db-local.inc.php';
} else {
	include 'config.db-ae.inc.php';
}

requi re 'vendor/autoload.php';
require 'phpQuery.php';

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

$mysqli = new mysqli("127.0.0.1", "test", "test", "test");
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
	<title>Guzzle Bla</title>
</head>

<pre>
<?php
/*
create table bla_cities (
	id int unsigned not null auto_increment,
	name varchar(255),
	lat varchar(255),
	lon varchar(255),
	population int unsigned,
	link varchar(255),
	primary key(id)
) CHARACTER SET utf8 COLLATE utf8_bin;
*/
// Pomocnicza funkcja wypełniająca tablicę danymi o miastach


/*
CREATE TABLE bla_rides (
	id int unsigned not null auto_increment,
	ride_id int unsigned not null,
	username varchar(255),
	user_profile_link varchar(255),
	user_age tinyint unsigned,
	user_avg_rating float(1,1) unsigned,
	user_num_ratings tinyint unsigned,
	driver_fee tinyint unsigned,
	bla_fee tinyint unsigned,
	price_type varchar(255),
	ride_distance int unsigned,
	ride_duration varchar(255),
	ride_from varchar(255),
	ride_to varchar(255),
	ride_date datetime,
	ride_stops varchar(255),
	ride_link varchar(255),
	primary key(id)
) CHARACTER SET utf8 COLLATE utf8_bin;

CREATE VIEW v_rides AS SELECT ride_from, ride_to, ride_date, username, user_age, price_type, ride_link FROM bla_rides;

FLOW:
1. Zbudowanie listy duzych miast i zapisanie tras pomiędzy nimi na kilka dni do przodu
2. Przejrzenie tabeli i uzupelnienie szczegolow trasy (na podstawie linku do trasy)
3. Przejrzenie tabeli i uzupelnienie o szczegoly uzytkownika (na podstawie tego samego linku)
4. ...Analizy!
*/

$client = new Client([
	"base_uri"	=> "https://www.blablacar.pl/",
	"verify" 	=> __DIR__ . "/cacert.pem",
	"cookies" 	=> true,
	"allow_redirects" => true,
	"http_errors" => false,
	"debug" => false,
    "headers" => [
    	'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.82 Safari/537.36',
	 	'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
		'accept-language' => 'pl-PL,pl;q=0.8,en-US;q=0.6,en;q=0.4'
    ]
]);

$res = $mysqli->query("SELECT ride_link, ride_id FROM bla_rides LIMIT 10");
while ( $row = $res->fetch_assoc() ) {
	print $row['ride_id'] . ':' . $row['ride_link'] . "\n";
	db_update_user_details($mysqli, $row['ride_link'], $row['ride_id']);
	ob_flush();
	flush();
}
exit;

while ($row = $res->fetch_assoc()) {
	print $row['ride_id'] . ':' . $row['ride_link'] . "\n";
	db_update_ride_details($mysqli, $row['ride_link'], $row['ride_id']);
	ob_flush();
	flush();
}
exit;

$top_20_cities = db_get_cities(20);
$city_from = current($top_20_cities);
$data = date("d/m/Y");

$stmt_del = $mysqli->prepare("DELETE FROM bla_rides WHERE ride_id=?");
$stmt_add = $mysqli->prepare("INSERT INTO bla_rides (ride_id, ride_from, ride_to, ride_date, username, ride_link, user_age, price_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
while ($city_to = next($top_20_cities)) {
	print 'FROM ' . $city_from['name'] . ' to ' . $city_to['name'] . '<br/>';
	// get first 50 rides sorted by price
	if ( $rides = get_rides($city_from['name'], $city_to['name'], $data, 50) ) {
		// print_r($rides);

		foreach ($rides as $ride) {
			// print_r($ride);
			print "Adding " . implode('|', $ride) . '...';
			$stmt_del->bind_param('i', $ride['ride_id']);
			$stmt_del->execute();

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
// print_r( get_user_details('podroz-katowice-warszawa-660109409') );
// exit;

// $from 	= "Warszawa";
// $to 	= "Łódź";
// $data 	= "10/11/2016"; // date("d/m/Y")
// $limit 	= 20;
// $sort 	= 'trip_price_euro'; // trip_date | trip_price_euro
// $order 	= 'asc'; // asc | desc

// $pages = result_pages_count($from, $to, $data, $limit);
// for ($page=1; $page<=$pages; $page++) {
// 	print_r( get_rides($from, $to, $data, $limit, $page, $sort, $order) );
// }
?>

</pre>
</html>
