<?php

class Connect
{
	protected static $_db = null;
	protected static $_dbChild = null;
	private static $_currentDataSchemaName = null;

	/**
	 * @return PgsqlDb
	 */
	public static function db() {
		return self::createDbObject(self::$_db, true);
	}

	/**
	 * @return PgsqlDb
	 */
	public static function dbChild($ip = null, $schemaDataId = null, $isUseSchema = true) {
		$schemaName = self::getChildSchemaName($schemaDataId);
		return self::createDbObject(self::$_dbChild, false, $isUseSchema, $ip, $schemaName);
	}

	public static function setChildSchema($schemaDataId) {
		$schemaName = self::getChildSchemaName($schemaDataId);
		self::$_dbChild->query('SET search_path=\''.$schemaName.'\';');
	}

	private static function getChildSchemaName($schemaDataId) {
		return 'data_'.$schemaDataId;
	}

	private static function createDbObject(&$oDb, $bMain = false, $bUseSchema = true, $sIp = null, $sDataSchemaName = null) {
		// проверка только для доменной базы
		if($sIp && $sDataSchemaName && $sDataSchemaName != self::$_currentDataSchemaName) {
			$oDb = null;
		}
		// Для любого типа баз
		if(!$oDb) {
			$conf = Config::get('database');
			if($bMain) {
				$sIp = $conf['main_host'];
				$iPort = $conf['main_port'];
				$sSchemaName = $conf['main_schema_name'];
				$sDbName = $conf['main_db_name'];
			} else {
				$iPort = $conf['data_port'];
				self::$_currentDataSchemaName = $sDataSchemaName;
				$sSchemaName = $sDataSchemaName;
				$sDbName = $conf['data_db_name'];
			}
			if(isset($sIp)) {
				$conf = Config::get('database');
				$_connectionString = 'pgsql:host='.$sIp.';port='.$iPort.';dbname='.$sDbName;
				//if(!$bMain) d($_connectionString);
				$oDb = (new PgsqlDb())->getInstance($_connectionString, $conf['db_user'], $conf['db_pwd'], $conf['charset']);
				if($oDb) {
					if($bUseSchema) {
						$oDb->query('SET search_path=\''.$sSchemaName.'\';');
					}
				} else {
					echo 'Нет соединения с базой данных';
					exit;
				}
			}
		}

		return $oDb;
	}

	public static function dbClose() {
		self::$_db = null;
	}

	public static function dbChildClose() {
		self::$_dbChild = null;
	}

	/*
	public static function isDbConnectionCreated() {
		return (self::$_db ? true : false);
	}

	public static function isdbChildConnectionCreated() {
		return (self::$_dbChild ? true : false);
	}
	*/
}
