<?php

require_once CLASSESPATH.'/App.php';

class APIServer extends App
{
	//private $type = 'token';
	protected $param;

	protected $conf;
	protected $res;

	protected $User;
	protected $CrmUser;
	protected $ERROR;
	protected $WARNING;

	public function __construct($h) {
		$type = !isset($this->type) ? 'token' : $this->type;

		include CLASSESPATH.'/APIServerMessages.php'; // include_once - не подойдет
		$this->ERROR = $ERROR;
		$this->WARNING = $WARNING;
		$this->res = ['result' => [], 'errors' => [], 'warnings' => []];

		$this->conf = $h['conf'];
		$this->method = get_called_class();
		$this->request_method = isset($h['request_method']) ? mb_strtolower($h['request_method']) : 'get';
		$this->param = $h['param'];

		if($type == 'cookie') {
			// auth by cookie
			if(!empty($_COOKIE['token'])) {
				$this->User = $this->getUserByToken($_COOKIE['token']);

				if(!is_array($this->User)) {
					$this->addError('bad_token');
				} else {
					if($this->User['is_blocked']) {
						$this->addUserBlockedError($this->User['email'], $this->User['blocking_reasons']);
						// Принудительно разлогиниваем пользователя на всех устройствах
						Connect::db()->query(
							'DELETE FROM "user_session" WHERE "user_id"=:user_id;',
							['user_id' => $this->User['id']]
						);
					}
				}
			} else {
				$this->addError('empty_token');
			}
		}
	}

	public function runMethod() {
		if(!$this->hasErrors()) {
			if(method_exists($this, $this->request_method)) {
				$request_method = $this->request_method;

				$result = $this->$request_method($this->param);
				if($result && is_array($result)) {
					$this->res['result'] += $result;
				}
			} else {
				$this->addError(['request_method_not_found' => ['msg' => 'REST-метод '.$this->request_method.' не найден', 'msg_en' => 'REST-method '.$this->request_method.' not found']]);
			}
		}
		return $this->res;
	}

	// Выносить из APIServer в дочерний класс нельзя, потому что функция используется в самом APIServer
	protected function getUserByToken($token) {
		return Connect::db()->queryOne(
			'SELECT "user".*, '.getIfSqlConstruction('"user"."password" IS NULL', '0', '1').' AS "is_email_confirmed",'
			.' "tariff"."price_per_month", "tariff"."price_per_year", "tariff"."cnt_template_max",'
			.' "tariff"."cnt_image_in_template_max", "tariff"."size_image_max", "tariff"."size_image_total_max",'
			.' "server_data"."ip" as "server_data_ip", "server_static"."external_url" as "server_static_external_url",'
			.' "user_session"."token", "user_session"."dt_update"'
			.' FROM "user"'
			.' INNER JOIN "user_session" ON "user_session"."user_id"="user"."id"'
			.' INNER JOIN "tariff" ON "tariff"."id"="user"."tariff_id"'
			.' INNER JOIN "server_data" ON "server_data"."id"="user"."server_data_id"'
			.' INNER JOIN "server_static" ON "server_static"."id"="user"."server_static_id"'
			.' WHERE "user_session"."token"=:token;',
			['token' => $token]
		);
	}

	protected function getUser($id = null, $email = null) {
		$user = null;
		if($id || $email) {
			$criteria = [];
			if($id) {
				$criteria['id'] = $id;
			} else {
				$criteria['email'] = $email;
			}
			$user = Connect::db()->queryOne(
				'SELECT "user".*, '.getIfSqlConstruction('"user"."password" IS NULL', '0', '1').' AS "is_email_confirmed"'
				.' FROM "user"'
				.' WHERE "user".'
				.($id ? '"id"=:id' : '"email"=:email'),
				$criteria
			);
		}
		return $user;
	}

	protected function createUserSession($userId, $remember = false) {
		// проверка существования токета защитит нас от коллизий md5
		do {
			$token = md5(uuid());
			$users = Connect::db()->getOne(
				'user_session',
				['user_id'],
				['token' => $token]
			);
		} while($users);
		$ip = getRealIp();
		$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;

		Connect::db()->query(
			'INSERT INTO "user_session" ("token", "user_id", "dt_create", "dt_update", "ip", "user_agent")'
			.' VALUES (:token, :user_id, NOW(), NOW(), :ip, :user_agent);',
			[
				'token' => $token,
				'user_id' => $userId,
				'ip' => $ip,
				'user_agent' => $userAgent,
			]
		);
		setcookie('token', $token, $remember ? (time() + 3600 * 24 * 30) : 0, '/', '.'.$this->conf['server']['site_domain']);

		//return $token;
	}

	protected function createCrmUserSession($crmUserId, $remember = false) {
		// проверка существования токета защитит нас от коллизий md5
		do {
			$token = md5(uuid());
			$users = Connect::db()->getOne(
				'crm_user_session',
				['crm_user_id'],
				['token' => $token]
			);
		} while($users);

		$ip = getRealIp();
		$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;

		Connect::db()->query(
			'INSERT INTO "crm_user_session" ("token", "crm_user_id", "dt_create", "dt_update", "ip", "user_agent")'
			.' VALUES (:token, :crm_user_id, NOW(), NOW(), :ip, :user_agent)'
			.' ON CONFLICT DO NOTHING;', // CONFLICT - это перестраховка
			[
				'token' => $token,
				'crm_user_id' => $crmUserId,
				'ip' => $ip,
				'user_agent' => $userAgent,
			]
		);
		setcookie('token', $token, $remember ? (time() + 3600 * 24 * 30) : 0, '/', $this->conf['server']['crm_domain']);
		// --- для корректной работы тестов по CRM методам ---
		setcookie('crm_token', $token, $remember ? (time() + 3600 * 24 * 30) : 0, '/', '.'.$this->conf['server']['site_domain']);
		// ---------------------------------------------------

		return $token;
	}

	public function addError($error, $mergeError = []) {
		$errorObj = is_string($error) ? [$error => $this->ERROR[$error]] : $error;
		if($mergeError) {
			$errorObj = mergeArrayAssoc($errorObj, [key($errorObj) => $mergeError]);
		}

		$this->res['errors'] += $errorObj;
	}

	public function addWarning($warning, $mergeWarning = []) {
		$warningObj = is_string($warning) ? [$warning => $this->WARNING[$warning]] : $warning;
		if($mergeWarning) {
			$warningObj = mergeArrayAssoc($warningObj, [key($warningObj) => $mergeWarning]);
		}
		$this->res['warnings'] += $warningObj;
	}

	public function hasErrors() {
		return (count($this->res['errors']) > 0);
	}

	public function hasWarnings() {
		return (count($this->res['warnings']) > 0);
	}

	protected function addUserBlockedError($sEmail, $sBlockingReasons = '') {
		$this->addError(
			'user_is_blocked',
			[
				'email' => $sEmail,
				'msg' => 'Ваш аккаунт '.$sEmail.' заблокирован!'
					.($sBlockingReasons ? '<br />Причина блокировки: '.$sBlockingReasons : '')
					.'<br />Для получения более подробной информации обратитесь в службу поддержки.',
			]
		);
	}

	protected function addNextTryLoginError($iCntLoginFail) {
		$this->addError('next_try_login_after_some_time', ['msg' => 'Следующую попытку авторизации будет возможно провести не ранее чем через '.$iCntLoginFail.' '.t($iCntLoginFail, 'секунду', 'секунды', 'секунд')]);
	}

	// Проверка, не является ли домен одноразовым (из сервисов почт на 10 минут)
	protected function checkDomainAvailability($sDomain) {
		// Проверяем по списку доменов, занесенных к нам в базу, а также их поддоменов
		$aDomainDisposable = Connect::db()->queryOne(
			'SELECT "domain"'
			.' FROM "domain_disposable"'
			.' WHERE "domain"=:domain OR "domain" LIKE :like_domain;',
			[
				'domain' => $sDomain,
				'like_domain' => '%.'.Connect::db()->escapeSearchValue($sDomain),
			]
		);
		return $aDomainDisposable ? false : true;
	}
}
