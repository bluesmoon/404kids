<?php

$yql_url = "http://query.yahooapis.com/v1/public/yql?q=select%20*%20from%20xml%20where%20url%3D'http%3A%2F%2Fwww.missingkidsmap.com%2Fread.php%3Fstate%3D%STATE_CODE%'&format=json&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys&callback=";

$state_url = "http://query.yahooapis.com/v1/public/yql?q=select%20admin1.code%20from%20geo.places%20where%20woeid%3D%STATE_WOEID%&format=json&callback=";

$geo_lookup = "http://geoip.pidgets.com?format=json&ip=";

$supported_countries = array('US', 'CA');

function main()
{
	global $state_url, $yql_url, $supported_countries;

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

	/*
	$json = http_get($geo_lookup . urlencode($ip));
	$o = json_decode($json, 1);

	if($o && in_array($o['country_code'], $supported_countries)) {
		$state = $o['region'];
	}
	*/

	$missing_kids_url = str_replace("%STATE_CODE%", $state, $yql_url);
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
<title>Welcome to the moon</title>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8">

<link rel="shortcut icon" href="http://i1.bluesmoon.info/favicon.ico" type="image/x-icon">
<link rel="openid.server" href="http://www.livejournal.com/openid/server.bml">
<link rel="openid.delegate" href="http://bluesmoon.livejournal.com/">

<link rel="pgpkeys" type="application/pgp-keys" title="Philip S Tellis's GPG Public Key" href="http://bluesmoon.info/bluesmoon.asc">
<link rel="pgpkeys" type="application/pgp-keys" title="Philip S Tellis's GPG Public Key (Key server)" href="http://pgpkeys.mit.edu:11371/pks/lookup?op=get&amp;search=0x1F140E17">

<link rel="stylesheet" type="text/css" href="http://a1.bluesmoon.info/blue.css">

</head>

<body class="error">
<div id="content">
<?php include "/home/ptellis/templates/header.html" ?>

 <div class="vcard">
  <h1>Sorry, the page you're trying to find is missing.</h1>
  <br>
 </div> <!-- end vcard -->

 <div id="body">
  <div id="bodycontent">

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
print_r($child);
?>
-->

  </div>
 </div> <!-- end body -->
 <div id="footer">

  <ul>
   <li class="feed"><a title="Flickr photostream" href="http://api.flickr.com/services/feeds/photos_public.gne?id=57155801@N00&amp;lang=en-us&amp;format=rss_200">PHOTO FEED</a></li>
   <li class="feed"><a title="Tech blog" href="http://feeds.feedburner.com/bluestech">BLOG FEED</a></li>
   <li><a title="Validate this page's markup" href="http://validator.w3.org/check?uri=referer">HTML 4.01</a></li>
   <li><a title="Validate this page's CSS" href="http://jigsaw.w3.org/css-validator/check/referrer">CSS 2.1</a></li>
   <li>&copy; PHILIP TELLIS 2009</li>
  </ul>

 </div> <!-- end footer -->

</div> <!-- end content -->


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
