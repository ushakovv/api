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

$start = microtime(true); // если $conf['debug']['save_slow_log']=0 то переменная нигде не используется

require_once(dirname(dirname(__FILE__)).'/data/classes/Bootstrap.php');

$aRes = ['result' => [], 'errors' => [], 'warnings' => []];

$conf = Config::get();

$request_method = $_SERVER['REQUEST_METHOD'];

$_PUT_DELETE_GET = [];
if($_SERVER['REQUEST_METHOD'] == 'PUT' || $_SERVER['REQUEST_METHOD'] == 'DELETE' || $_SERVER['REQUEST_METHOD'] == 'GET') {
	$putdata = file_get_contents('php://input');
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
}

$aParam = array_merge($_GET, $_POST, $_PUT_DELETE_GET, $_FILES);
$method = isset($aParam['method']) ? trim($aParam['method'], "/") : null;

$aRes['method'] = $method;
$aRes['request_method'] = $request_method;
if($conf['debug']['enable']) {
	$aRes['server'] = $conf['server']['cur_server_domain'];
}

if(count($aRes['errors']) == 0) {
	if(isset($method) && file_exists(CLASSESPATH.'/methods/'.$method.'.php')) {
		unset($aParam['method']);

		$oAPI = new $method([
			'conf' => $conf,
			'request_method' => $request_method,
			'param' => $aParam
		]);

		$aResTmp = $oAPI->runMethod();
		$aRes['errors'] += $aResTmp['errors'];
		$aRes['warnings'] += $aResTmp['warnings'];
		if(count($aRes['errors']) == 0) {
			$aRes['result'] += $aResTmp['result'];
		}
	} else {
		$aRes['errors'] += ['method_not_found' => ['msg' => 'Метод не найден', 'msg_en' => 'Method not found']];
	}
}

if(count($aRes['result']) == 0) {
	unset($aRes['result']);
}
if(count($aRes['errors']) == 0) {
	unset($aRes['errors']);
}
if(count($aRes['warnings']) == 0) {
	unset($aRes['warnings']);
}

/*
// Закрываем соединения с базами если были открыты
if(App::isDbConnectionCreated()) {
	Connect::db()->closeConnection();
}
if(App::isDbDomainConnectionCreated()) {
	Connect::dbDomain()->closeConnection();
}
*/

header('Content-Type: application/json');
echo json_encode($aRes);