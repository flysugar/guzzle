<?php
// dodaje listę miast do bazy
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

// Zwraca listę miast
// limit - ilość zwracanych miast
// min_population - minimalna ilość mieszkańców
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

// Funkcje pobierające dane z JSON lub z HTML-a

// pobiera szczegóły przejazdu dla podanego linku do przejazdu
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

// pobiera szczegóły profilu użytkownika
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
?>