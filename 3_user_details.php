<?php
set_time_limit(60); // na razie minuta

require 'vendor/autoload.php';
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
function db_store_cities($link='https://raw.githubusercontent.com/maqmaq/nosql/master/my.json') {
	GLOBAL $client;
	$mysqli = new mysqli("127.0.0.1", "test", "test", "test");
	$table = 'bla_cities';

	/* check connection */
	if (mysqli_connect_errno()) {
	    printf("Connect failed: %s\n", mysqli_connect_error());
	    exit();
	}
	$mysqli->set_charset("utf8");
	$mysqli->query("DELETE FROM $table");

	$r = $client->get('https://raw.githubusercontent.com/maqmaq/nosql/master/my.json');
	$json = $r->getBody();
	$json_decoded = json_decode($json);
	$i=0;
	foreach ($json_decoded as $city) {
		print "Importing ".$city->nazwa."...";
		$sql = 'INSERT INTO '.$table.' (name, lat, lon, population, link) VALUES (';
		$sql .= '"'.$mysqli->real_escape_string(trim($city->nazwa)).'", ';
		$sql .= '"'.$mysqli->real_escape_string(trim($city->szerokosc)).'", ';
		$sql .= '"'.$mysqli->real_escape_string(trim($city->dlugosc)).'", ';
		$sql .= intval($mysqli->real_escape_string(trim($city->ludnosc))).', ';
		$sql .= '"'.$mysqli->real_escape_string(trim($city->link)).'")';

		// debug: print $sql . "\n";
		if ( !$mysqli->query($sql) ) {
			echo "Query failed: (" . $mysqli->errno . ") " . $mysqli->error;
		}
		print "done.\n";
	}
	echo "Imported " . count($json_decoded) . " cities.";
	$mysqli->close();
}

function db_get_cities($limit=20, $min_population=50000) {
	$mysqli = new mysqli("127.0.0.1", "test", "test", "test");
	$table = 'bla_cities';

	/* check connection */
	if (mysqli_connect_errno()) {
	    printf("Connect failed: %s\n", mysqli_connect_error());
	    exit();
	}
	$mysqli->set_charset("utf8");

	$sql = "SELECT name FROM bla_cities WHERE population >= $min_population ORDER BY population DESC LIMIT $limit";
	if ( $res = $mysqli->query($sql) ) {
		$ret = $res->fetch_all(MYSQLI_ASSOC);
		$mysqli->close();
		return $ret;
	} else {
		print "SQL error: $sql";
	}
}

function get_ride_details($ride_link) {
	GLOBAL $client; // guzzle client
	$details = [];

	// link bez https://...
	$r = $client->get($ride_link, [
		'headers' => [
			'referer' => 'https://www.blablacar.pl/szukac-wspolna-jazda'
		]
	]);

	$html = $r->getBody();
	$trasa = phpQuery::newDocument($html);
	$mapa = $trasa->find('div.RideMap');
	$punkty = pq($mapa)->find('div.RideMap-canvas ul li');

	// szczegoly przejazdu: dlugosc, czas
	$trasa_szczegoly_arr = trim(pq($mapa)->find('div.RideMap-info')->html());
	$trasa_szczegoly = explode(',', $trasa_szczegoly_arr);
	$details['ride_distance'] = intval(str_replace(' km', '', $trasa_szczegoly[0]));
	$details['ride_duration'] = trim($trasa_szczegoly[1]); 

	// składowe ceny: dla kierowcy, dla serwisu
	$cena = $trasa->find('div.Booking div.Block-section div.u-clearfix');
	$cena_suma = pq($cena)->find('span:nth-child(2)');
	$details['driver_fee'] = intval(str_replace(' zł', '', trim(pq($cena_suma)->elements[0]->nodeValue)));
	$details['bla_fee'] = intval(str_replace(' zł', '', trim( pq($cena_suma)->elements[1]->nodeValue)));

	$ride_stops = [];
	foreach ( pq($punkty) as $punkt) {
			$nazwa_punktu = trim(pq($punkt)->text());
			$lat = pq($punkt)->attr('data-latitude');
			$lon = pq($punkt)->attr('data-longitude');

			$ride_stops[] = $nazwa_punktu.'|'.$lat.'|'.$lon;
	}
	$ride_stops_str = implode(';', $ride_stops);
	$details['ride_stops'] = $ride_stops_str;

	return $details;

}

function get_user_details($ride_link) {
	GLOBAL $client; // guzzle client
	$user_details = [];

	// link bez https://...
	$r = $client->get($ride_link, [
		'headers' => [
			'referer' => 'https://www.blablacar.pl/szukac-wspolna-jazda'
		]
	]);

	$html = $r->getBody();
	$trasa = phpQuery::newDocument($html);
	$user_data = $trasa->find('div.ProfileCard');

	// link do profilu
	$profile_card = pq($user_data)->find('h4.ProfileCard-info a');
	$user_details['username'] = trim(pq($profile_card)->text());
	$user_details['user_profile_link'] = trim(pq($profile_card)->attr('href'));

	// srednia ocen
	$oceny = pq($user_data)->find('div.ProfileCard-row p.ratings-container span');
	$avg_rating = trim(pq($oceny)->elements[0]->nodeValue);
	$avg_rating = explode('/', $avg_rating); // pozostawiamy tylko pierwsza czesc sredniej oceny
	$avg_rating = str_replace(',', '.', $avg_rating[0]); // zamieniamy przecinek na kropke
	$user_details['user_avg_rating'] = $avg_rating;

	// ilosc ocen
	$user_details['user_num_ratings'] =  filter_var(str_replace('-','',trim(pq($oceny)->elements[1]->nodeValue)), FILTER_SANITIZE_NUMBER_INT);

	return $user_details;
}

// zwraca ilość stron z wynikami wyszukiwania dla danego zapytani
function result_pages_count($from="Warszawa", $to="Łódź", $data=null, $limit=20) {
	print "result_pages_count($from, $to, $data, $limit)\n";
	GLOBAL $client; // guzzle client

	if ($data==null)
		$data = date("d/m/Y");

	$r = $client->get('search_xhr?fn='.$from.'&tn='.$to.'&db='.$data.'&limit='.$limit, [
		'headers' => [
			'referer' => 'https://www.blablacar.pl/szukac-wspolna-jazda'
		]
	]);

	$json = $r->getBody();
	$json_decoded = json_decode($json);
	$html = $json_decoded->html->results;
	$d = phpQuery::newDocument($html);
	$wyniki = $d->find('div[class="pagination"] ul li')->not('.prev')->not('.next');

	return count($wyniki);
}

// Zwraca listę przejazdów na podanej trasie z dodatkowymi informacjami
// nazwa uzytkownika, link do szczegolow, wiek, typ ceny
function get_rides($from="Warszawa", $to="Łódź", $data=null, $limit=20, $page=1, $sort="trip_price_euro", $order="asc") {
	print "get_rides($from, $to, $data, $limit, $page, $sort, $order)\n";
	GLOBAL $client; // guzzle client

	if ($data==null)
		$data = date("d/m/Y");

	$r = $client->get('search_xhr?fn='.$from.'&tn='.$to.'&db='.$data.'&sort='.$sort.'&order='.$order.'&limit='.$limit.'&page='.$page, [
		'headers' => [
			'referer' => 'https://www.blablacar.pl/szukac-wspolna-jazda'
		]
	]);

	$json = $r->getBody();
	$json_decoded = json_decode($json);
	$ride_ids = $json_decoded->results; 
	$gps_from = $json_decoded->search->fc;
	$gps_to = $json_decoded->search->tc;
	$html = $json_decoded->html->results;
	$d = phpQuery::newDocument($html);

	// $results_no = intval(trim($d->find('div[class="pagination-info span3"]')->text()));
	// $wyniki = $d->find('div[class="pagination"] ul li')->not('.prev')->not('.next');
	// $results_page_no = count($wyniki);

	// if ($page > $results_page_no) { // brak podanej strony
	// 	return null;
	// }

	$results = [];

	$lis = $d->find("ul[class='trip-search-results'] li");
	$ride_id = current($ride_ids);
	foreach ( $lis as $li) {
		$result = [];
		$result['ride_id'] = $ride_id;
		$result['ride_from'] = $from;
		$result['gps_from'] = $gps_from;
		$result['ride_to'] = $to;
		$result['gps_to'] = $gps_to;
		$result['ride_date'] = $data;

		// username
		$result['username'] =  trim(pq($li)->find('h2[class="ProfileCard-info ProfileCard-info--name u-truncate"]')->text());

		// link do szczegolow
		$result['link'] = trim(pq($li)->find('a[class="trip-search-oneresult"]')->attr('href'));

		// wiek
		$result['age'] = intval(str_replace(' lat/lata', '', trim(pq($li)->find('div[class="ProfileCard-info"]')->text())));

		// typ ceny
		$result['price_type'] = trim(pq($li)->find('div[class="price price-black"]')->find('span[class="priceUnit"]')->text());

		// dodajemy do tablicy
		$results[] = $result;

		// przewijamy ride id
		$ride_id = next($ride_ids);
	}

	return $results;
}

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

// uzupełnia szczegóły dla podanej trasy
function db_update_ride_details($mysqli, $ride_link, $ride_id) {
	$table = 'bla_rides';

	print "updating ride $ride_id ($ride_link)...";
	if ( $details = get_ride_details($ride_link) ) {
		// print_r( $details );

		$stmt_details = $mysqli->prepare("UPDATE bla_rides SET ride_distance=?, ride_duration=?, driver_fee=?, bla_fee=?, ride_stops=? WHERE ride_id=?");
		$stmt_details->bind_param('isiisi',
							$details['ride_distance'],
							$details['ride_duration'],
							$details['driver_fee'],
							$details['bla_fee'],
							$details['ride_stops'],
							$ride_id
						);
		if ( $stmt_details->execute() ) {
			printf("%d Ride updated.\n", $stmt_details->affected_rows);
		} else {
			print "problems with ride update statement..." . $stmt_details->error;
		}
	} else {
		echo "problem with link.\n";
	}
}

// uaktualnia baze o szczegoly uzytkownika
function db_update_user_details($mysqli, $ride_link, $ride_id) {
	$stmt_user = $mysqli->prepare("UPDATE bla_rides SET user_profile_link=?, user_avg_rating=?, user_num_ratings=? WHERE ride_id=?\n");

	if ( $details = get_user_details($ride_link) ) {
		// $sql = "UPDATE bla_rides SET user_profile_link='{$details['user_profile_link']}', user_avg_rating={$details['user_avg_rating']}, user_num_ratings={$details['user_num_ratings']} WHERE ride_id='{$row['ride_id']}'\n";
		// print $sql;
		echo "updating {$details['username']} ({$details['user_profile_link']}) profile data...";
		$stmt_user->bind_param('sdis',
								$details['user_profile_link'],
								$details['user_avg_rating'],
								$details['user_num_ratings'],
								$ride_id
							);

		if ( $stmt_user->execute() ) {
			printf("%d Row inserted.", $stmt_user->affected_rows);
			print "DONE\n";
		} else {
			print "problems with user statement..." . $stmt_user->error;
		}
	}
}

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
