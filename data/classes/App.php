<?php

abstract class App
{
	protected $attachManager;

	public $aCurrentProjectSpace = null;
	public $aCurrentProject = null;

	protected $method;
	protected $request_method;

	/**
	 * Инициализация AttachManager
	 * @return object || false
	 */
	public function initAttachManager($serverStaticId) {
		if(!$this->attachManager) {
			$this->attachManager = new AttachManager;
			$staticServer = Connect::db()->queryOne(
				'SELECT "id", "cdn_url", "cdn_user", "cdn_password", "external_url", "container", "token", "dt_token", "storage_url"'
				.' FROM "server_static"'
				.' WHERE "id"=:server_static_id;',
				['server_static_id' => $serverStaticId]
			);
			$this->attachManager->init($staticServer);
		}

		return $this->attachManager;
	}

	/**
	 * @param int|null $iLetterId
	 * @param int|null $iProjectId
	 * @param int|null $iVendorId
	 * @return AttachManager
	 */
	public function attachManager($serverStaticId) {
		if(!$this->attachManager) {
			$this->initAttachManager($serverStaticId);
		}

		return $this->attachManager;
	}

	public function setDataDbConnection($ip, $schemaName/*, $bReturnSchemaError*/) {
		Connect::dbChild($ip, $schemaName);
		//return $this->checkDomainDbConnection($bReturnSchemaError);
	}

	/*public function checkDomainDbConnection($bDbCreate = false, $bReturnSchemaError = true) {
		$bResult = !is_null($this->aCurrentProjectSpace) && isset($this->aCurrentProjectSpace['ip']);
		if(!$bResult && $bDbCreate) {
			$bResult = $this->createDomainDbConnection();
		}

		if(!$bResult && $bReturnSchemaError) {
			$this->addError('domain_schema_not_exists');
		}

		return $bResult;
	}*/

	protected function getDomainDbConnectionIp() {
		$sIp = Connect::db()->queryColumn(
			'SELECT "ip"'
			.' FROM "server_domain_db"'
			.' WHERE "cnt_db"<"limit_cnt_db"'
			.' LIMIT 1;'
		);
		if(!$sIp) {
			$sIp = Connect::db()->queryColumn(
				'SELECT "ip"'
				.' FROM "server_domain_db"'
				.' ORDER BY "cnt_db" ASC'
				.' LIMIT 1;'
			);
		}
		return $sIp;
	}

	/*protected function createDomainDbConnection() {
		$bResult = false;
		if($this->aCurrentProjectSpace) {
			$aDomain = Connect::db()->queryOne(
				'SELECT "domain"."id", "domain"."name"'
				.' FROM "project_space"'
				.' INNER JOIN "domain" ON "domain"."id"="project_space"."domain_id"'
				.' WHERE "project_space"."id"=:project_space_id AND "project_space"."is_deleted"=FALSE;',
				['project_space_id' => $this->aCurrentProjectSpace['id']]
			);
			if($aDomain) {
				if(!isset($this->aCurrentProjectSpace['ip'])) {
					$this->aCurrentProjectSpace['ip'] = $this->getDomainDbConnectionIp();
				}
				$aDomainDbData = getDbData($this->aCurrentProjectSpace['domain_id']);
				Connect::db()->getPDO()->beginTransaction();
				try {
					$sSqlFile = SQLPATH.'/md_domain_schema.psql';
					if(file_exists($sSqlFile)) {
						Connect::dbDomain($this->aCurrentProjectSpace['ip'], $aDomainDbData['schema_name'], false);
						Connect::dbDomain()->query(setMasterQuery().'DROP SCHEMA IF EXISTS "'.$aDomainDbData['schema_name'].'" CASCADE;');
						Connect::dbDomain()->query(setMasterQuery().'CREATE SCHEMA IF NOT EXISTS "'.$aDomainDbData['schema_name'].'";');
						Connect::dbDomain()->query(setMasterQuery().'SET search_path="'.$aDomainDbData['schema_name'].'";');
						$aData = explode(';', file_get_contents($sSqlFile));
						foreach($aData as $sQuery) {
							$sQuery = trim($sQuery);
							if($sQuery) {
								Connect::dbDomain()->query($sQuery);
							}
						}
						Connect::db()->query(
							'UPDATE "domain"'
							.' SET "ip"=:ip'
							.' WHERE "id"=:domain_id AND "is_blocked"=FALSE;',
							[
								'ip' => $this->aCurrentProjectSpace['ip'],
								'domain_id' => $this->aCurrentProjectSpace['domain_id'],
							]
						);
						Connect::db()->query(
							'UPDATE "server_domain_db"'
							.' SET "cnt_db"="cnt_db"+1'
							.' WHERE "ip"=:ip;',
							['ip' => $this->aCurrentProjectSpace['ip']]
						);
						$bResult = true;
					}

					Connect::db()->getPDO()->commit();
				} catch(Exception $e) {
					Connect::db()->getPDO()->rollback();
					saveInDefaultLog('Transaction error: App.php ('.$e->getMessage().' '.$e->getTraceAsString().')');
					$this->sendNotificationMail(
						'is_monitoring_domain_db',
						'Ошибка создания доменной схемы данных',
						'Не удалось создать доменную схему данных '.getDbDataString($aDomainDbData).' по адресу '.$this->aCurrentProjectSpace['ip'].'<hr><pre>'.var_export($e, true).'</pre>'
					);
					$this->aCurrentProjectSpace = null;
					Connect::dbDomainClose();
				}
			}
		}

		return $bResult;
	}*/

	public function sendNotificationMail($sKey = null, $sSubject, $sMessage, $aNotificationUsers = null) {
		if(!$aNotificationUsers) {
			$aNotificationUsers = Connect::db()->queryAllAssoc(
				'SELECT "email"'
				.' FROM "system_notification"'
				.($sKey ? ' WHERE "'.$sKey.'"=TRUE;' : ';'),
				[],
				'email',
				true
			);
		}
		if($aNotificationUsers) {
			$conf = Config::get();
			foreach($aNotificationUsers as $sEmail => $null) {
				SendMail::from($conf['mail']['from_email'], $conf['mail']['from_name'])
				->to($sEmail)
				->subject($sSubject)
				->message($sMessage)
				->important(true)
				->send();
			}
		}
	}

	/*
	 * Методы по проверке доступов
	 */

	// Функция получения прав юзера в PS (project_space)
	// @param {integer} iProjectSpaceId - ид PS
	// @param {string/array} Access - значение или массив прав доступа к PS:
	//		'creator' - тот, кто создал PS;
	//		'admin' - тот, кому дали права создавать проекты;
	//		'user' - тот, кому расшарили проект.
	// @returns {array}
	protected function getProjectSpaceAccess($iProjectSpaceId, $aAccess = null, $bConnect = true, $bDbCreate = false, $bReturnSchemaError = true, $bUser = true) {
		if(is_null($this->aCurrentProjectSpace) || $iProjectSpaceId != $this->aCurrentProjectSpace['id']) {
			$aCriteria = ['project_space_id' => $iProjectSpaceId];
			if($bUser) {
				$aCriteria['current_user_id'] = $this->User['id'];
			}
			$this->aCurrentProjectSpace = Connect::db()->queryOne(
				'SELECT "project_space".*, "domain"."name" AS "domain_name", "domain"."is_blocked" AS "domain_is_blocked",'
				.' "domain"."blocking_reasons" AS "domain_blocking_reasons", "domain"."ip", "tariff"."key" AS "user_tariff_key",'
				.' "server_static"."external_url" AS "server_static_external_url", "user"."vendor_id"'
				.($bUser ? ', "user2project_space"."access"' : '')
				.' FROM "project_space"'
				.($bUser ? ' INNER JOIN "user2project_space" ON "user2project_space"."user_id"=:current_user_id AND "user2project_space"."project_space_id"="project_space"."id"' : '')
				.' INNER JOIN "domain" ON "domain"."id"="project_space"."domain_id"'
				.' INNER JOIN "user" ON "user"."id"="project_space"."user_id"'
				.' INNER JOIN "tariff" ON "tariff"."id"="user"."tariff_id"'
				.' INNER JOIN "server_static" ON "server_static"."id"="project_space"."server_static_id"'
				.' WHERE "project_space"."id"=:project_space_id'
				.' AND "project_space"."is_deleted"=FALSE'
				.($aAccess ? ' AND '.$this->getJsonbSearchSqlConstruction($aAccess, '"user2project_space"."access"') : '').';',
				$aCriteria
			);
			if($this->aCurrentProjectSpace && isset($this->aCurrentProjectSpace['access'])) {
				$this->aCurrentProjectSpace['access'] = json_decode($this->aCurrentProjectSpace['access'], true);
			}
		}

		if($this->aCurrentProjectSpace) {
			$isBlockAccessError = false;
			if($this->aCurrentProjectSpace['domain_is_blocked']) {
				$this->aCurrentProjectSpace = null;
				$this->addError(
					'domain_is_blocked',
					[
						'domain' => $this->aCurrentProjectSpace['domain_name'],
						'msg' => 'Домен '.$this->aCurrentProjectSpace['domain_name'].' заблокирован!'
							.($this->aCurrentProjectSpace['domain_blocking_reasons'] ? '<br />Причина блокировки: '.$this->aCurrentProjectSpace['domain_blocking_reasons'] : '')
							.'<br />Для получения более подробной информации обратитесь в службу поддержки.',
					]
				);
			} elseif(!$this->aCurrentProjectSpace['is_confirmed']) {
				// Не делаем данную проверку при удалении
				if($this->method != 'project_space' || ($this->method == 'project_space' && $this->request_method != 'delete')) {
					// Проверка что данный домен подтвержден
					if(!$this->checkProjectSpaceConfirm($this->aCurrentProjectSpace['domain_name'], $this->aCurrentProjectSpace['confirm_key'], $this->aCurrentProjectSpace['confirm_value'], $this->aCurrentProjectSpace['id'])) {
						$this->addError($this->getProjectSpaceConfirmError($this->aCurrentProjectSpace['domain_name'], $this->aCurrentProjectSpace['confirm_key'], $this->aCurrentProjectSpace['confirm_value']));
						$this->aCurrentProjectSpace = null;
						$isBlockAccessError = true;
					}
				}
			} else {
				if($bConnect
					&& !$this->setDomainDbConnection(
						$iProjectSpaceId,
						$this->aCurrentProjectSpace,
						$bDbCreate,
						$bReturnSchemaError
					)
				) {
					$this->aCurrentProjectSpace = null;
				}
			}
		}

		if(is_null($this->aCurrentProjectSpace) && !$isBlockAccessError) {
			$this->addError('access_denied');
		}

		return $this->aCurrentProjectSpace ? true : false;
	}

	// Функция получения ролей юзера в проекте
	// @param {integer} iProjectId - ид проекта
	// @param {bool} bUser - проверка доступа авторизованному пользователю
	// @returns {array}
	protected function getProjectAccess($iProjectId, $bUser = true) {
		if(is_null($this->aCurrentProject)) {
			$aCriteria = ['project_id' => $iProjectId];
			if($bUser) {
				$aCriteria['current_user_id'] = $this->User['id'];
			}
			$aRoles = $this->getMethodRoles($this->method.'.'.$this->request_method);
			$this->aCurrentProject = Connect::dbDomain()->queryOne(
				'SELECT "project".*'
				.($bUser ? ', "user2project"."roles"' : '')
				.' FROM "project"'
				.($bUser ? ' INNER JOIN "user2project" ON "user2project"."user_id"=:current_user_id AND "user2project"."project_id"="project"."id"'
				.' AND '.$this->getJsonbSearchSqlConstruction($aRoles, '"user2project"."roles"', '', true) : '')
				.' WHERE "project"."id"=:project_id'
				.' AND "project"."is_deleted"=FALSE;',
				$aCriteria
			);
			if($this->aCurrentProject) {
				$this->aCurrentProject['roles'] = json_decode($this->aCurrentProject['roles'], true);
			} else {
				$this->aCurrentProject = null;
			}
		}

		if(is_null($this->aCurrentProject)) {
			$this->addError('access_denied');
		}

		return ($this->aCurrentProject ? true : false);
	}

	// Функция получения конструкции поиска в JSONB
	// @param {array/string} Data - массив значений/значение для поиска в JSONB
	// @param {string} sSourceField - поле, в котором надо производить поиск
	// @param {string} sCompareField - поле, которое надо искать в sSourceField (Data не используется)
	// @param {bool} bKeys - если TRUE, то из массива Data берутся ключи, иначе - значения
	// @returns {string}
	public function getJsonbSearchSqlConstruction($Data = null, $sSourceField = '', $sCompareField = '', $bKeys = false) {
		if(!$sCompareField && is_array($Data) && count($Data) > 1) {
			return 'JSONB_EXISTS_ANY('.$sSourceField.', '.(!$sCompareField ? 'array['.Connect::db()->escape(!$bKeys ? $Data : array_keys($Data)).']' : $sCompareField).')';
		} else {
			return 'JSONB_EXISTS('.$sSourceField.', '.(!$sCompareField ? Connect::db()->escape($Data) : $sCompareField).')';
		}
	}

	// Функция получения конструкции объекта JSONB
	// @param {array/string} Data - массив значений/значение для преобразования в JSONB
	// @param {bool} bEmpty - если TRUE, то надо создать JSONB с пустыми значениями
	// @param {bool} bValues - если FALSE, то из массива Data берутся ключи, иначе - значения
	public function getJsonbObjectSqlConstruction($Data, $bEmpty = false, $bValues = false) {
		$sJsonb = json_encode((is_array($Data) ? (!$bEmpty ? $Data : array_fill_keys(array_keys(!$bValues ? $Data : array_flip($Data)), '')) : [$Data => '']), JSON_FORCE_OBJECT);

		return Connect::db()->escape($sJsonb).'::jsonb';
	}

	// Функция получения части JSONB
	// @param {connection} oDb - соединение с базой данных для экранирования
	// @param {array} aData - массив:
	//		sField = '"email"."projects"', aData = [1, 'groups'] => "email"."projects"->'1'->'groups'
	//		sField = null, aData = [1 => ['groups' => [2 => '']]] => {"1":{"groups":{"2":""}}}
	// @param {string} sField - название поля, из которого берется часть
	public function getJsonbPart($oDb, $aData, $sField = null) {
		if($sField) {
			$sResult = $sField;
			foreach($aData as $sKey) {
				$sResult .= '->'.$oDb->escape($sKey);
			}
		} else {
			$sResult = $oDb->escape(json_encode($aData, JSON_FORCE_OBJECT));
		}

		return $sResult;
	}

	protected function getProjectSpaceConfirmError($sDomainName, $sConfirmKey, $sConfirmValue) {
		return [
			'need_confirm_project_space' => [
				'msg' => '<p>Необходимо подтвердить владение доменом <b>'.$sDomainName.'</b>.'
					.' Для этого можно воспользоваться одним из следующих способов:'
					.'<ul><li>Загрузите в корневой каталог вашего сайта файл с именем <b>'.$sConfirmKey.'.html</b> и содержащий текст <b>'.$sConfirmValue.'</b></li>'
					.'<li style="list-style: none; color: #888;">или</li>'
					.'<li>Настройте в <b>DNS</b> для домена <b>maildarts_'.$sConfirmKey.'.'.$sDomainName.'</b> TXT запись со значением <b>'.$sConfirmValue.'</b></li></ul></p>',
				'msg_en' => 'Need confirm access to domain',
			],
		];
	}

	protected function checkProjectSpaceConfirm($sDomainName, $sConfirmKey, $sConfirmValue, $iProjectSpaceId) {
		$isConfirmed = false;

		// Проверка по DNS TXT записи
		$oIdn = new IdnaConvert(['idn_version'=>2008]);
		$sDomainPunycode = $oIdn->encode($sDomainName);
		$aTxt = @dns_get_record('maildarts_'.$sConfirmKey.'.'.$sDomainPunycode, DNS_TXT);
		if($aTxt) {
			foreach($aTxt as $sTxt) {
				if(isset($sTxt['txt']) && preg_replace('/\s/', '', $sTxt['txt']) == $sConfirmValue) {
					$isConfirmed = true;
					break;
				}
			}
		}

		// Проверка по файлу на сервере
		if(!$isConfirmed) {
			$sClientConfirmValue = getCurlData('//'.$sDomainName.'/'.$sConfirmKey.'.html');
			if($sClientConfirmValue && $sClientConfirmValue == $sConfirmValue) {
				$isConfirmed = true;
			}
		}

		// Если домен подтвержден, то проставляем флаг в базе
		if($isConfirmed) {
			Connect::db()->query(
				'UPDATE "project_space" SET "is_confirmed"=TRUE, "confirm_key"=NULL, "confirm_value"=NULL'
				.' WHERE "id"=:project_space_id;',
				['project_space_id' => $iProjectSpaceId]
			);
		}

		return $isConfirmed;
	}
}
