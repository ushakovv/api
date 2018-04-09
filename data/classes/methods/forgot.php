<?php

class forgot extends APIServer
{
	protected $type = '';

	public function post($h) {
		$email = getStringParam($h, 'email');
		if($email) {
			$newPassword = generatePassword($this->conf['user']['default_password_len']);
			$confirmCode = sha1(uuid());

			$user = Connect::db()->getOne(
				'user',
				['id', 'is_blocked', 'blocking_reasons'],
				['email' => $email]
			);

			if($user) {
				if(!$user['is_blocked']) {
					Connect::db()->update(
						'user',
						[
							'new_password' => sha1($newPassword),
							'confirm_code' => $confirmCode,
						],
						['id' => $user['id']]
					);

					MailService::sendMail(
						$email,
						'Запрос на восстановление пароля на сайте {{SITE_NAME}}',
						'Вы получили это письмо, потому что кто-то (возможно, Вы) запросил на сайте <a href="{{SITE_LINK}}">{{SITE_NAME}}</a> восстановление пароля для пользователя, зарегистрированного под Вашим email адресом.
<br/>
<br/>Чтобы восстановить пароль, перейдите по ссылке:
<br/><a href="{{LINK}}">{{LINK}}</a>
<br/>Если ссылка не открывается, скопируйте её и вставьте в адресную строку браузера.
<br/>
<br/>Ваш логин: <b>{{EMAIL}}</b>
<br/>Ваш новый пароль: <b>{{PASSWORD}}</b>
<br/>
<br/>Если Вы не запрашивали изменение пароля или вспомнили свой пароль, просто проигнорируйте это письмо и пользуйтесь своим текущим паролем.
<br/>
<br/>С уважением, команда <a href="{{SITE_LINK}}">{{SITE_NAME}}</a>',
						[
							'EMAIL' => $email,
							'PASSWORD' => $newPassword,
							'LINK' => $this->conf['server']['cabinet_protocol'].'://'.$this->conf['server']['cabinet_domain'].'/confirm_user/?user_id='.$user['id'].'&confirm_code='.$confirmCode,
							'SITE_NAME' => $this->conf['server']['site_name'],
							'SITE_LINK' => $this->conf['server']['site_protocol'].'://'.$this->conf['server']['site_domain'],
						]
					);

					$this->addWarning('mail_sended');

					return ['success'];
				} else {
					$this->addUserBlockedError($email, $user['blocking_reasons']);
				}
			} else {
				$this->addError('user_not_found');
			}
		} else {
			$this->addError('required_params_missed');
		}
	}
}
