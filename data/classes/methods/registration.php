<?php

// Метод для всех пользователей, позволяющий регистрироваться самому
class registration extends APIServer
{
	protected $type = '';

	public function post($h) {
		$name = getStringParam($h, 'name');
		$email = getStringParam($h, 'email');
		$newPassword = getPasswordParam($h, 'password');

		if($name && $email && $newPassword) {
			if(isValidEmail($email)) {
				// Проверка не является ли почта одноразовой (из сервисов почт на 10 минут)
				// Проверяем по списку доменов, занесенных к нам в базу, а также их поддоменов
				$sDomain = getDomainByEmail($email);
				if($this->checkDomainAvailability($sDomain)) {
					$user = Connect::db()->queryOne(
						'SELECT "id", "password"'
						.' FROM "user"'
						.' WHERE "email"=:email;',
						['email' => $email]
					);
					if($user) {
						// Если емайл уже есть в базе, но он еще не подтвержден, то генерируем новый код подтверждения и отправляем его на почту повторно
						if(!$user['password']) {
							$confirmCode = sha1(uuid());
							Connect::db()->update(
								'user',
								[
									'name' => $name,
									'password' => null,
									'new_password' => sha1($newPassword),
									'confirm_code' => $confirmCode,
								],
								['id' => $user['id']]
							);

							$this->sendConfirmMail($email, $newPassword, $user['id'], $confirmCode);
							return ['success'];
						} else {
							$this->addError('email_is_busy');
						}
					} else {
						// Выбираем наименее загруженные серверные ресурсы для привязки к ним нового пользователя
						$schemaData = Connect::db()->queryOne(
							'SELECT "id", "server_data_id"'
							.' FROM "schema_data"'
							.' WHERE "cnt_user" < "cnt_user_max"'
							.' ORDER BY "cnt_user" ASC;'
						);
						if($schemaData) {
							$serverDataId = $schemaData['server_data_id'];
							$schemaDataId = $schemaData['id'];
						} else {
							$serverData = Connect::db()->queryOne(
								'SELECT "id", "ip"'
								.' FROM "server_data"'
								.' WHERE "cnt_schema" < "cnt_schema_max"'
								.' ORDER BY "cnt_schema" ASC;'
							);
							if($serverData) {
								$serverDataId = $serverData['id'];
								$schemaDataId = Connect::db()->insert(
									'schema_data',
									['server_data_id' => $serverDataId]
								);
								if($schemaDataId) {
									// Создаем новую схему данных
									$sqlFilePath = SQLPATH.'/templater_data_schema.psql';
									if(file_exists($sqlFilePath)) {
										$schemaName = 'data_'.$schemaDataId;
										Connect::db()->getPDO()->beginTransaction();
										try {
											Connect::dbChild($serverData['ip'], $schemaDataId, false);
											Connect::dbChild()->query(setMasterQuery().'DROP SCHEMA IF EXISTS "'.$schemaName.'" CASCADE;');
											Connect::dbChild()->query(setMasterQuery().'CREATE SCHEMA IF NOT EXISTS "'.$schemaName.'";');
											Connect::setChildSchema($schemaDataId);
											$sql = explode(';', file_get_contents($sqlFilePath));
											foreach($sql as $query) {
												$query = trim($query);
												if($query) {
													Connect::dbChild()->query($query);
												}
											}

											Connect::db()->getPDO()->commit();
										} catch(Exception $e) {
											Connect::db()->getPDO()->rollback();
											saveInDefaultLog('Transaction error: registration.php ('.$e->getMessage().' '.$e->getTraceAsString().')');
											Connect::dbChildClose();
										}
									}

									// Увеличиваем счетчик схем данных на сервере
									Connect::db()->query(
										'UPDATE "server_data" SET "cnt_schema"="cnt_schema"+1 WHERE id=:server_data_id;',
										['server_data_id' => $serverDataId]
									);
								} else {
									$this->addError('insert_schema_data_error');
								}
							} else {
								$this->addError('registration_temporarily_unavailable_1');
							}
						}

						if(!$this->hasErrors()) {
							$serverStaticId = Connect::db()->queryScalar(
								'SELECT "id"'
								.' FROM "server_static"'
								.' WHERE "enabled"=TRUE'
								.' LIMIT 1;'
							);
							if($serverStaticId) {
								$confirmCode = sha1(uuid());

								$userId = Connect::db()->insert(
									'user',
									[
										'server_data_id' => $serverDataId,
										'schema_data_id' => $schemaDataId,
										'server_static_id' => $serverStaticId,
										'tariff_id' => 1,
										'email' => $email,
										'name' => $name,
										'password' => null,
										'new_password' => sha1($newPassword),
										'confirm_code' => $confirmCode,
									]
								);

								if($userId) {
									Connect::db()->query(
										'UPDATE "schema_data" SET "cnt_user"="cnt_user"+1 WHERE id=:schema_data_id;',
										['schema_data_id' => $schemaDataId]
									);
									$this->sendConfirmMail($email, $newPassword, $userId, $confirmCode);
									return ['success'];
								} else {
									$this->addError('insert_user_error');
								}
							} else {
								$this->addError('registration_temporarily_unavailable_2');
							}
						}
					}
				} else {
					$this->addError('cant_registration_disposable_email');
				}
			} else {
				$this->addError('wrong_email_format');
			}
		} else {
			$this->addError('required_params_missed');
		}
	}

	private function sendConfirmMail($email, $password, $userId, $confirmCode) {
		MailService::sendMail(
			$email,
			'Регистрация на сайте {{SITE_NAME}}',
			'Благодарим вас за регистрацию на сайте <a href="{{SITE_LINK}}">{{SITE_NAME}}</a>
<br/>Для завершения регистрации перейдите по ссылке:
<br/><a href="{{LINK}}">{{LINK}}</a>
<br/>Если ссылка не открывается, скопируйте её и вставьте в адресную строку браузера.
<br/>
<br/>Если вы не регистрировались на сайте <a href="{{SITE_LINK}}">{{SITE_NAME}}</a>, просто проигнорируйте это письмо.
<br/>
<br/>С уважением, команда <a href="{{SITE_LINK}}">{{SITE_NAME}}</a>',
			[
				'EMAIL' => $email,
				'PASSWORD' => $password,
				'LINK' => $this->conf['server']['cabinet_protocol'].'://'.$this->conf['server']['cabinet_domain'].'/confirm_user/?user_id='.$userId.'&confirm_code='.$confirmCode,
				'SITE_NAME' => $this->conf['server']['site_name'],
				'SITE_LINK' => $this->conf['server']['site_protocol'].'://'.$this->conf['server']['site_domain'],
			]
		);

		$this->addWarning('mail_sended');
	}
}