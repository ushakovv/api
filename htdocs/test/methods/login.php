<?php

$conf = Config::get();

$aMethods = [
	[
		'request_method' => 'get',
		'h' => [
			'email' => 'p.ushakov@extteam.ru',
			'password' => '1q2w3e4r5t',
			'is_remember' => '1',
		],
	],
	/*[
		'request_method' => 'get',
		'h' => [
			'email' => 'ilicherv.am@gmail.com',
			'password' => '123456',
			'is_remember' => '1',
			//'is_iframe_login' => '1',
			//'LINK_EXAMPLE' => $conf['server']['api_protocol'].'://'.$conf['server']['api_domain'].'/login?email=ilicherv.am@gmail.com'
			//	.'&password=1&is_remember=1&is_iframe_login=1',
		],
	],*/
];
