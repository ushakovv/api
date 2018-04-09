<?php

$conf = Config::get();
echo '<hr><b>Request method:</b> post<br><form method="post" action="http://'.$conf['server']['api_domain'].'/image/" enctype="multipart/form-data">'
	.'<br>file: <input type="file" name="file" value="">'
	.'<br>template_id: <input type="text" name="letter_id" value="6">'
	.'<br><input type="submit"></form>';


$aMethods = [
	/*[
		'request_method' => 'get',
		'h' => [
			'id' => 1,
		],
	],
	[
		'request_method' => 'get',
		'h' => [
			'template_id' => 6,
		],
	],*/
	[
		'request_method' => 'post',
		'h' => [
			'template_id' => 6,
			'file' => '',
		],
	],
];
