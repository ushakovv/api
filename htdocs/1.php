<?php

// Allow from any origin
if(isset($_SERVER['HTTP_ORIGIN'])) {
	header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
	header('Access-Control-Allow-Credentials: true');
	header('Access-Control-Max-Age: 86400');    // cache for 1 day
}
// Access-Control headers are received during OPTIONS requests
if($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
	if(isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
		header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
	}
	if(isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
		header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
	}
}

echo '<b>$_REQUEST:</b><br>';
print_r($_REQUEST);
if($_SERVER['REQUEST_METHOD'] == 'PUT' || $_SERVER['REQUEST_METHOD'] == 'DELETE') {
	echo '<br><br><b>php://input:</b><br>';
	$putdata = file_get_contents('php://input');
	print_r($putdata);

	echo '<br><br><b>php://input processed:</b><br>';
	$_PUT_DELETE_GET = [];

	$json = json_decode($putdata, true);
	if(is_array($json)) {
		$_PUT_DELETE_GET = $json;
	} else {
		$exploded = explode('&', $putdata);
		foreach($exploded as $pair) {
			$item = explode('=', $pair);
			if(count($item) == 2) {
				$_PUT_DELETE_GET[urldecode($item[0])] = urldecode($item[1]);
			}
		}
	}
	print_r($_PUT_DELETE_GET);
}

echo '<br><b>$_FILES:</b><br>';
print_r($_FILES);
exit;
