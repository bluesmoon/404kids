<?php

$yql_url = "http://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20xml%20where%20url%3D'http%3A%2F%2Fwww.missingkidsmap.com%2Fread.php%3Fstate%3D@state'&format=json&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys&callback=&state=";

$geo_lookup = "http://query.yahooapis.com/v1/public/yql?q=select%20country.code%2C%20admin1.code%20from%20geo.places%20where%20woeid%20in%20(select%20place.woeid%20from%20flickr.places%20where%20(lat%2Clon)%20in%20(select%20Latitude%2C%20Longitude%20from%20ip.location%20where%20ip%3D@ip))&format=json&diagnostics=false&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys&callback=&ip=";

$supported_countries = array('US', 'CA');

function main()
{
	global $geo_lookup, $yql_url, $supported_countries;

	$tstart = microtime(true);

	$state = 'ZZ';

	// IP lookup code from here: http://us3.php.net/manual/en/language.variables.predefined.php#31724
	if ($_SERVER["HTTP_X_FORWARDED_FOR"]) {
		$ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
	} else {
		if ($_SERVER["HTTP_CLIENT_IP"]) {
			$ip = $_SERVER["HTTP_CLIENT_IP"];
		} else {
			$ip = $_SERVER["REMOTE_ADDR"];
		}
	}

	$geo_lookup_url = $geo_lookup . urlencode($ip);
	$json = http_get($geo_lookup_url);
	$o = json_decode($json, 1);

	if($o && $o['query'] && $o['query']['results']) {
		$place = $o['query']['results']['place'];

		if(in_array($place['country']['code'], $supported_countries)) {
			$state = preg_replace('/^\w\w-/', '', $o['query']['results']['place']['admin1']['code']);
		}
	}

	$missing_kids_url = str_replace('@state', $state, $yql_url);
	$json = http_get($missing_kids_url);
	$o = json_decode($json, 1);
	$children = $o['query']['results']['locations']['location'];

	$child = array_rand($children);

	$tend = microtime(true);

	print_404($children[$child]);

	$t = $tend-$tstart;
	$unit = "s";
	if($t < 1) {
		$t *= 1000;
		$unit = "ms";
	}
	if($t < 1) {
		$t *= 1000;
		$unit = "us";
	}

	$t = round($t);

	echo "<!-- page generated in $t$unit -->\n";
}

function print_404($child)
{
	$img = preg_replace('/.*src=(.*)/', '$1', $child["medpic"]);
	$name = $child["firstname"] . " " . $child["lastname"];
	$age = $child['age'];
	$since = strtotime(preg_replace('|(\d\d)/(\d\d)/(\d\d\d\d)|', '$3-$1-$2', $child['missing']));
	if($age == 0) {
		$age = ceil((time()-$since)/60/60/24/30);
		$age .= ' month';
	}
	else
		$age .= ' year';

	$city = $child['city'];
	$state = $child['st'];
	$status = $child['status'];
	$police = preg_replace("/\\\\'/", "'", $child['policeadd']) . " at " . $child['policenum'];

	header('HTTP/1.0 404 Not Found');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<!-- Put your page header here -->
</head>

<body class="error">
<h1>Sorry, the page you're trying to find is missing.</h1>

<p>
We may not be able to find the page, but perhaps you could help find this missing child:
</p>
<div style="text-align:center;">
<img style="width:320px; padding: 1em;" alt="<?php echo $name ?>" src="<?php echo $img ?>"><br>
<div style="text-align: left;">
<?php echo $age ?> old <?php echo $name ?>, from <?php echo "$city, $state" ?> missing since <?php echo strftime("%B %e, %Y", $since); ?>.<br>
<strong>Status:</strong> <?php echo $status ?>.<br>
<strong>If found, please contact</strong> <?php echo $police ?><br>
</div>
</div>

<!--
<?php
// debugging info
print_r($child);
?>
-->

</body>
</html>

<?php
}

main();


function http_get($urls)
{
	$wantarray = is_array($urls);
	if(!$wantarray) {
		$urls = array($urls);
	}

	$bodies = array();
	$pass_urls = array();
	$pass_urls_indices = array();
	for($i=0; $i<count($urls); $i++) {
		$fname = md5($urls[$i] . strftime("%Y-%j-%H"));
		$b = @file_get_contents("/tmp/$fname");
		if($b) {
			$bodies[$i] = $b;
		} else {
			$pass_urls[]=$urls[$i];
			$pass_urls_indices[]=$i;
		}
	}

	$b = _http_get($pass_urls);

	for($i=0; $i<count($pass_urls); $i++) {
		$bodies[$pass_urls_indices[$i]] = $b[$i];
		$fname = md5($pass_urls[$i] . strftime("%Y-%j-%H"));
		file_put_contents("/tmp/$fname", $b[$i]);
	}

	ksort($bodies);

	return $wantarray?$bodies:$bodies[0];
}

function _http_get($urls)
{
	$mh = curl_multi_init();
	$handles = array();
	foreach($urls as $url) {
		$c = curl_init($url);
		curl_setopt($c, CURLOPT_HEADER, true);
		curl_setopt($c, CURLINFO_HEADER_OUT, false);
		//curl_setopt($c, CURLOPT_VERBOSE, true);
		curl_setopt($c, CURLOPT_CRLF, true);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);

		curl_multi_add_handle($mh, $c);

		$handles[] = $c;
	}

	$active = null;
	do {
		$mrc = curl_multi_exec($mh, $active);
	} while ($mrc == CURLM_CALL_MULTI_PERFORM);

	while ($active && $mrc == CURLM_OK) {
		if (curl_multi_select($mh) != -1) {
			do {
				$mrc = curl_multi_exec($mh, $active);
			} while ($mrc == CURLM_CALL_MULTI_PERFORM);
		}
	}

	$bodies = array();

	foreach($handles as $c) {
		$body = curl_multi_getcontent($c);
		list($header, $body) = explode("\r\n\r\n", $body, 2);
		$bodies[]=$body;
		curl_multi_remove_handle($mh, $c);
	}

	curl_multi_close($mh);

	return $bodies;
}

?>
