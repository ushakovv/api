<?php

return [
	'database' => [
		'main_host' => 'localhost',
		'main_port' => 5432,
		'main_db_name' => 'templater_login',
		'main_schema_name' => 'public',

		'data_port' => 5432,
		'data_db_name' => 'templater_data',

		'db_user' => 'postgres',
		'db_pwd' => '',
		'charset' => 'utf8',
	],
	/*'backup' => [
		'folder_path' => '', // если оставить пустым, то путь останется по умолчанию [path_to_project]/tmp/backup
	],*/
	'server' => [
		'timezone' => 'Europe/Moscow',
		'api_protocol' => 'http',
		'api_domain' => 'api.mailmaker.loc',
		'cur_server_domain' => 'api.mailmaker.loc', // Пример когда настроена репликация: ap1.mailmaker.loc
		'cabinet_protocol' => 'http',
		'cabinet_domain' => 'cabinet.mailmaker.loc',
		'site_name' => 'Mail Maker',
		'site_protocol' => 'http',
		'site_domain' => 'mailmaker.loc',
		/*
		'cur_server_ip_list' => '89.108.93.68',
		'external_ip' => '89.108.93.68',


		'crm_domain' => 'crm.maildarts.ru',
		'crm_name' => 'MailDarts CRM',
		'cabinet_name' => 'MailDarts Cabinet',
		'domain_id' => 0, // указание ид домена для скриптов, запускающихся на том же сервере, где лежит доменная схема
		'process_param' => 'MD', // префикс гирман тасков доменных баз и параметр запуска кроновских скриптов
		*/
	],
	'security' => [
		'template_hash_salt' => 'BJHd,234329(D*b2VDFsp2=3e=12',
		//'subscribe_confirm_salt' => 'SM{Qm2nxO@mvB@mdsd==21',
	],
	'mail' => [
		'property_limiter_left' => '{{',
		'property_limiter_right' => '}}',
	],
	'user' => [
		'default_password_len' => 8,
	],
	'template' => [
		'screenshot_width' => 1024,
		// screenshot_height рассчитывается автоматически
		'preview_width' => 208,
		'preview_height' => 262,
	],
	'debug' => [
		'enable' => 1,
		'save_slow_log' => 1,
		'slow_work_time' => 2.0, // выводить в лог запросы, выполняющиеся дольше, чем slow_work_time (в секундах - можно указывать дробное число, например, 0.05)
	],
	'test' => [
		'enable' => 1,
	],
	'mjml' => [
		'json2html_convert_url' => 'http://fedora220.loc:8080/'
	],
];