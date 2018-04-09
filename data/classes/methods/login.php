<?php

class login extends APIServer
{
	protected $type = '';

	// Вызов из ифрейма
	public function get($h) {
		return $this->post($h);
	}

	// Метод для кабинета
	public function post($h) {
		$email = getStringParam($h, 'email');
		$password = getPasswordParam($h, 'password');
		$isRemember = getBoolParam($h, 'is_remember');
		$isIframeLogin = getBoolParam($h, 'is_iframe_login');

		if($email && $password) {
			$user = $this->getUser(null, $email);

			if($user) {
				if(!$user['is_blocked']) {
					if($user['is_email_confirmed']) {
						/*if($user['dt_next_login']) {
							$iNextLoginTime = strtotime($user['dt_next_login']);
							$iCurTime = time();
							if($iCurTime < $iNextLoginTime) {
								$this->addNextTryLoginError($iNextLoginTime - $iCurTime);
							}
						}*/

						if(!$this->hasErrors()) {
							/*if($user['cnt_login_fail'] >= $this->conf['captcha']['count_login_fail_before_code_check']) {
								// Проверка каптчи
								$key = getStringParam($h, 'key');
								$code = getStringParam($h, 'code');
								if($key && $code) {
									if(!$this->checkCaptcha($key, $code)) {
										$this->addError('wrong_code');
									}
								} else {
									$this->addError('empty_code');
									$this->addError('too_many_login_fails');
								}
							}*/

							if(!$this->hasErrors()) {
								if(sha1($password) == $user['password']) {
									$this->createUserSession($user['id'], $isRemember);
									// сбрасываем счетчик неудачных попыток логина
									Connect::db()->query(
										'UPDATE "user" SET "cnt_login_fail"=0, "dt_next_login"=NULL WHERE "id"=:user_id;',
										['user_id' => $user['id']]
									);

									if($isIframeLogin) {
										redirect($this->conf['server']['cabinet_protocol'].'://'.$this->conf['server']['cabinet_domain']);
									} else {
										return [
											'user' => [
												'id' => $user['id'],
												'name' => $user['name'],
												'email' => $user['email'],
											]
										];
									}
								} else {
									$this->addError('bad_password');
								}/*else {
									$sDtNextLogin = null;
									$iCntLoginFail = $user['cnt_login_fail'] + 1;
									$isBlocked = false;
									$sBlockingReasons = '';
									if($iCntLoginFail >= $this->conf['captcha']['count_login_fail_before_user_block']) {
										// превышено пороговое количество ошибок, аккаунт блокируется до выяснения всех обстоятельств
										$this->addError('too_many_login_fails');

										$isBlocked = true;
										$sBlockingReasons = 'Попытка перебора паролей для входа в аккаунт.';
										$this->addUserBlockedError($user['email'], $sBlockingReasons);
									} elseif($iCntLoginFail >= $this->conf['captcha']['count_login_fail_before_login_delay']) {
										// после достаточно большого количества неудачных попыток логина, когда явно видно что идёт перебор паролей,
										// после каждой ошибки делаем задержку на количество секунд равное количеству ошибок (прогрессивая шкала)
										// при такой схеме для подбора простейшего пароля 1000 при последовательном переборе только цифр начиная с 1 потребуется примерно 5 суток
										$this->addError('too_many_login_fails');
										$this->addNextTryLoginError($iCntLoginFail);
										$sDtNextLogin = date('Y-m-d H:i:s', time() + $iCntLoginFail);
									} elseif($iCntLoginFail >= $this->conf['captcha']['count_login_fail_before_code_check']) {
										// если количество неудачных попыток логина превысило определенное число, то выводим предупреждение о необходимости следующий раз логиниться, вводя капчу
										$this->addError('too_many_login_fails');
									}
									$this->addError('bad_password');

									// увеличиваем счетчик неудачных попыток логина
									Connect::db()->query(
										'UPDATE "user"'
										.' SET "cnt_login_fail"="cnt_login_fail"+1, "dt_next_login"=:dt_next_login,'
										.' "is_blocked"=:is_blocked, "blocking_reasons"=:blocking_reasons'
										.' WHERE "id"=:user_id;',
										[
											'user_id' => $user['id'],
											'dt_next_login' => $sDtNextLogin,
											'is_blocked' => $isBlocked,
											'blocking_reasons' => $sBlockingReasons,
										]
									);
								}*/
							}
						}
					} else {
						$this->addError('email_is_not_confirmed');
					}
				} else {
					$this->addUserBlockedError($user['email'], $user['blocking_reasons']);
				}
			} else {
				$this->addError('user_not_found');
			}
		} else {
			$this->addError('required_params_missed');
		}
	}
}
