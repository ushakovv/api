<?php

define ('ROOTPATH', dirname(dirname(dirname(__FILE__))));
define ('DATAPATH', ROOTPATH.'/data');
define ('SQLPATH', ROOTPATH.'/sql');
define ('BINPATH', ROOTPATH.'/bin');
define ('TMPPATH', ROOTPATH.'/tmp');
define ('BACKUPPATH', ROOTPATH.'/tmp/backup');
define ('CONFPATH', ROOTPATH.'/conf');
define ('HTDOCSPATH', ROOTPATH.'/htdocs');
define ('CLASSESPATH', DATAPATH.'/classes');
define ('METHODPATH', CLASSESPATH.'/methods');
define ('CRONPATH', ROOTPATH.'/cron');
define ('PHANTOMPATH', CLASSESPATH.'/phantomjs');

spl_autoload_register(
	function ($class_name) {
		if(file_exists(CLASSESPATH.'/'.$class_name.'.php')) {
			require_once CLASSESPATH.'/'.$class_name.'.php';
		} elseif(file_exists(METHODPATH.'/'.$class_name.'.php')) {
			require_once METHODPATH.'/'.$class_name.'.php';
		} elseif(file_exists(METHODPATH.'/base/'.$class_name.'.php')) {
			require_once METHODPATH.'/base/'.$class_name.'.php';
		}
	}
);

require_once ROOTPATH.'/vendor/autoload.php';
require_once CLASSESPATH.'/Lib.php';

date_default_timezone_set(Config::get('server', 'timezone'));

if(Config::get('debug', 'enable')) {
	error_reporting(E_ALL);
	ini_set('display_errors', true);
}
