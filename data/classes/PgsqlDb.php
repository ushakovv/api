<?php

/**
 * Класс для работы с PgsqlDb
 */
class PgsqlDb
{
	private $_pdo;
	private $_instance;

	// Данные о подключении для переподнятия коннекта к PostgreSQL
	private $_connectionString;
	private $_dbUser;
	private $_dbPassword;
	private $_dbCharset;

	/**
	 * Получение подключения
	 * @return object || false
	 */
	public function getConnection($_connectionString = '', $_dbUser = '', $_dbPassword = '', $_dbCharset = 'utf8') {
		if(!$this->_pdo) {
			try {
				if($_connectionString) {
					$this->_connectionString = $_connectionString;
				}
				if($_dbUser) {
					$this->_dbUser = $_dbUser;
				}
				if($_dbPassword) {
					$this->_dbPassword = $_dbPassword;
				}

				$this->_pdo = new PDO($this->_connectionString, $this->_dbUser, $this->_dbPassword);
				if($_dbCharset) {
					$this->_dbCharset = $_dbCharset;
					$this->_pdo->query('SET NAMES \''.$_dbCharset.'\';');
				}
			} catch(Exception $e) {
				return false;
			}
		}

		return $this->_pdo;
	}

	/**
	 * Получение состояния объекта
	 * @return PgsqlDb
	 */
	public function getInstance($_connectionString, $_dbUser, $_dbPassword, $_dbCharset) {
		if(!$this->_instance) {
			$this->_instance = new self();
			$this->_instance->getConnection($_connectionString, $_dbUser, $_dbPassword, $_dbCharset);
		}
		return $this->_instance;
	}

	public function getPDO() {
		return $this->_pdo;
	}

	/**
	 * Конструктор запроса SELECT
	 * @param  string $table Таблица
	 * @param  array $rows Столбцы выборки
	 * @param  array $conditions Параметры WHERE
	 * @param  array $row_conditions Дополнительные параметры WHERE
	 * @param  string $sort Сортировка
	 * @return array                  Результат запроса
	 */
	public function select($table, $rows = null, $conditions = [], $row_conditions = [], $sort = '', $bMaster = false) {
		if($rows) {
			$tmpRows = [];
			foreach($rows as $column) {
				$tmpRows[] = ($column != '*') ? ('"'.$column.'"') : $column;
			}
			$rows = implode(', ', $tmpRows);
		} else {
			$rows = '*';
		}
		$sql = ($bMaster ? setMasterQuery() : '').'SELECT '.$rows.' FROM "'.$table.'"';
		$bind = [];
		$delim = ' WHERE';
		foreach($conditions as $field => $value) {
			$sql .= $delim.' "'.$field.'"=:'.$field;
			$delim = ' AND';
			$bind[':'.$field] = $value;
		}
		if($row_conditions) {
			if(!$conditions) {
				$sql .= ' WHERE ';
			}
			foreach($row_conditions as $str) {
				$sql .= $str;
			}
		}
		if($sort) {
			$sql .= ' ORDER BY '.$sort;
		}
		$sql .= ';';

		$result = $this->query($sql, $bind);
		return $result;
	}

	/**
	 * Выполнение запроса
	 * @param  string $sQuery Запрос
	 * @param  array $aBind Параметры запроса
	 * @return PDOStatement       Результат запроса
	 */
	public function query($sQuery = '', $aBind = [], $bThrowExceptionOnError = true) {
		$iTimeStart = microtime(true);

		if(!$sQuery) {
			return false;
		}

		$this->getConnection();

		if($this->_pdo) {
			$oQuery = $this->_pdo->prepare($sQuery);
			if($aBind) {
				foreach($aBind as $key => $value) {
					$aBind[$key] = $this->escape($value, true);
				}
			}
			$bSuccess = $oQuery->execute($aBind);
			if(!$bSuccess) {
				if($bThrowExceptionOnError) {
					$aError = $oQuery->errorInfo();
					throw new PDOException($aError[2]);
				}
			}

			// логгирование запросов (проверка в методе в Lib.p)
			save2log(microtime(true) - $iTimeStart, $this->d($sQuery, $aBind, false, ':', false));
		} else {
			echo 'Нет соединения с базой данных';
			exit;
		}

		return $oQuery;
	}

	public function getAll($table, $columns = [], $conditions = [], $row_conditions = [], $sort = '') {
		$query = $this->select($table, $columns, $conditions, $row_conditions, $sort);
		if(!$query)
			return false;
		$result = [];
		if(in_array('id', $columns) || in_array($table.'.id', $columns)) {
			while($row = $query->fetch(PDO::FETCH_ASSOC)) {
				$result[$row['id']] = $row;
			}
		} else {
			try {
				$result = $query->fetchAll(PDO::FETCH_ASSOC);
			} catch(Exception $e) {
				die($e->getMessage());
			}
		}
		return $result;
	}

	public function getOne($table, $columns = [], $conditions = [], $row_conditions = [], $sort = '', $bMaster = false) {
		$query = $this->select($table, $columns, $conditions, $row_conditions, $sort, $bMaster);
		if(!$query)
			return false;
		return $query->fetch(PDO::FETCH_ASSOC);
	}

	public function getScalar($sTableName, $sColumn, $aConditions = []) {
		$sQuery = 'SELECT "'.$sColumn.'" FROM "'.$sTableName.'"';
		$aBind = [];
		if($aConditions) {
			$sQuery .= ' WHERE';
			$sDelimiter = '';
			foreach($aConditions as $sField => $Value) {
				$sQuery .= $sDelimiter.' "'.$sField.'"=:'.$sField;
				$sDelimiter = ' AND';
				$aBind[':'.$sField] = $Value;
			}
		}
		$sQuery .= ';';

		return $this->query($sQuery, $aBind)->fetchColumn();
	}

	public function getCount($sTableName, $conditions) {
		$sQuery = 'SELECT COUNT(*) FROM "'.$sTableName.'"';
		$aBind = [];
		if($conditions) {
			$sQuery .= ' WHERE';
			$sDelimiter = '';
			foreach($conditions as $sField => $Value) {
				$sQuery .= $sDelimiter.' "'.$sField.'"=:'.$sField;
				$sDelimiter = ' AND';
				$aBind[':'.$sField] = $Value;
			}
		}
		$sQuery .= ';';

		return intval($this->query($sQuery, $aBind)->fetchColumn());
	}

	public function update($table, $data, $conditions) {
		$sql = 'UPDATE "'.$table.'" SET';
		$bind = [];
		$delim = '';
		foreach($data as $field => $value) {
			$sql .= $delim.' "'.$field.'"=:'.$field;
			$delim = ',';
			$bind[':'.$field] = $value;
		}
		$sql .= ' WHERE';

		$delim = '';
		foreach($conditions as $field => $value) {
			$sql .= $delim.' "'.$field.'"=:where_'.$field;
			$delim = ' AND';
			$bind[':where_'.$field] = $value;
		}
		$sql .= ';';

		return $this->query($sql, $bind);
	}

	public function insert($sTableName, $aData, $sReturning = 'id', $sOnConflict = '', $bArray = true) {
		$Query = $this->multiInsert($sTableName, [0 => $aData], [], [], '', $sOnConflict, $sReturning, $bArray);
		if($sReturning) {
			return $this->queryScalar($Query);
		}

		return 0;
	}

	public function insertIgnore($sTableName, $aData, $sReturning = 'id') {
		return $this->insert($sTableName, $aData, $sReturning, 'DO NOTHING');
	}

	public function getFieldValue($sTableName, $sField, $Value) {
		if($Value == '_DEFAULT_VALUE_') {
			return 'DEFAULT';
		} elseif($Value == '_FIELD_VALUE_') {
			return '"'.$sTableName.'"."'.$sField.'"';
		} else {
			return $this->escape($Value);
		}
	}

	/**
		* Вставка многих записей, как в multiInsert, только при конфликте по уникальному ключу никаких действий не производится
	*/
	public function multiInsertIgnore($sTableName, $aData, $aField = [], $aBind = [], $sFieldAssoc = '', $sReturning = '', $bArray = true) {
		return $this->multiInsert($sTableName, $aData, $aField, $aBind, $sFieldAssoc, 'DO NOTHING', $sReturning, $bArray);
	}

	/**
		* Вставка многих записей
		* @param  string $sTableName Название таблицы
		* @param  array $aData Данные
		* @param  string $sOnConflict Действия в случае конфликта по уникальному ключу
		* @param  array $aField Массив с названиями полей из $aData, которые надо вставить, например, ['id' => null, 'name' => '']. Если массив не задан, то будут браться все поля
		* Если нужна запись ключа ассоциативного массива (задан параметр $sFieldAssoc) только в одно поле, то в массиве должно быть одно значение, равное $sFieldAssoc
		* @param  array $aBind Массив с названиями и значениями полей, которые будут одинаковы для всех записей $aData, например, ['parent_id' => 100, 'task_id' => 5]
		* @param  string $sFieldAssoc Поле, в которое будет записываться ключ ассоциативного массива. Если поле указано, то оно будет автоматически включено в $aField
		* @param  string $sReturning Возвращаемые значения
		* @param  string $sReturning Возвращаемые значения
		* @param  bool $bArray Если передается true, то в $aDataLine лежит массив, иначе - значение
		* @return string $sQuery Возвращается, если указан параметр $sReturning
	*/
	public function multiInsert($sTableName, $aData, $aField = [], $aBind = [], $sFieldAssoc = '', $sOnConflict = '', $sReturning = '', $bArray = true) {
		if($aData) {
			if(!$aField) {
				$aField = (current($aData) ?: []);
			}
			$bFieldAssoc = ($sFieldAssoc != '');
			if($bFieldAssoc && !array_key_exists($sFieldAssoc, $aField)) {
				$aField[$sFieldAssoc] = null;
			}
			if($aBind) {
				foreach($aBind as $sKey => $null) {
					$aField[$sKey] = null;
				}
			}
			$sQuery = 'INSERT INTO "'.$sTableName.'" ("'.implode('", "', array_keys($aField)).'") VALUES ';
			$bFirst = true;
			foreach($aData as $sKey => $aDataLine) {
				$sQuery .= (!$bFirst ? ',' : '').'(';
				$bFirst = true;
				foreach($aField as $sField => $DefaultValue) {
					if(!array_key_exists($sField, $aBind)) {
						$Value = (!$bFieldAssoc || $sField != $sFieldAssoc ? ($bArray ? (array_key_exists($sField, $aDataLine) ? $this->escape($aDataLine[$sField]) : $this->getFieldValue($sTableName, $sField, $DefaultValue)) : $this->escape($aDataLine)) : $this->escape($sKey));
						$sQuery .= (!$bFirst ? ',' : '').$Value;
					} else {
						$sQuery .= (!$bFirst ? ',' : '').$this->escape($aBind[$sField]);
					}
					$bFirst = false;
				}
				$bFirst = false;
				$sQuery .= ')';
			}
			$sQuery .= ($sOnConflict ? ' ON CONFLICT '.$sOnConflict : '');
			$sQuery .= ($sReturning ? ' RETURNING '.$sReturning : '').';';

			return (!$sReturning ? $this->query($sQuery) : $sQuery);
		}
	}

	public function delete($table, $conditions, $row_conditions = []) {
		$sql = 'DELETE FROM "'.$table.'" WHERE ';

		$bind = [];
		$delim = '';
		foreach($conditions as $field => $value) {
			$sql .= $delim.' "'.$field.'"=:'.$field;
			$delim = ' AND';
			$bind[':'.$field] = $value;
		}

		if($row_conditions) {
			$isFirst = true;
			foreach($row_conditions as $condition) {
				$sql .= ($isFirst && !$conditions ? '' : ' AND ').'('.$condition.')';
				$isFirst = false;
			}
		}

		$sql .= ';';
		return $this->query($sql, $bind);
	}

	/**
	 * Извлечение всех элементов из результата запроса и возврат в виде ассоциативного массива
	 * @param  [type] $oQuery      - объект Запрос
	 * @param  string $sKey - ключ группировки
	 * @param  bool $bKeyExclude - условие, надо ли исключать значение ключа из выборки
	 * @return array  $aResult     - результат
	 */
	public function queryAllAssoc($sQuery, $aBind = [], $sKey = 'id', $bKeyExclude = false) {
		$aResult = [];
		if($bKeyExclude) {
			$aReplace = [$sKey => ''];
		}
		$oQuery = $this->query($sQuery, $aBind);
		while($aData = $oQuery->fetch(PDO::FETCH_ASSOC)) {
			$aResult[$aData[$sKey]] = (!$bKeyExclude ? $aData : (array_diff_key($aData, $aReplace) ?: ''));
		}
		return $aResult;
	}

	/**
	 * Извлечение одного числового значения из результата запроса
	 * @param  [type] $query Результат запроса
	 * @return integer      Результат
	 */
	public function queryScalar($sQuery, $aBind = []) {
		return intval($this->queryColumn($sQuery, $aBind));
	}

	/**
	 * Извлечение одного значения из результата запроса
	 * @param  [type] $query Результат запроса
	 * @return string      Результат
	 */
	public function queryColumn($sQuery, $aBind = []) {
		$result = 0;
		if($row = $this->query($sQuery, $aBind)->fetch(PDO::FETCH_NUM)) {
			$result = current($row);
		}
		return $result;
	}

	public function queryOne($sQuery, $aBind = []) {
		return $this->query($sQuery, $aBind)->fetch(PDO::FETCH_ASSOC);
	}

	public function queryAll($sQuery, $aBind = []) {
		return $this->query($sQuery, $aBind)->fetchAll(PDO::FETCH_ASSOC);
	}

	// Эскейпинг специальных поисковых символов для постгреса, чтобы по ним можно было искать как по обычным символам
	public function escapeSearchValue($sValue) {
		return preg_replace('/(%|_)/', '\\\${1}', $sValue);
	}

	public function escapeValue($Value, $bQuery = false) {
		if(is_bool($Value)) {
			return ($Value ? 'TRUE' : 'FALSE');
		} elseif(is_null($Value)) {
			return (!$bQuery ? 'NULL' : $Value);
		} else {
			return (!$bQuery ? $this->getPDO()->quote($Value) : $Value);
		}
	}

	/**
	 * Экранирование входных данных
	 * @param  mixed $Value  Данные
	 * @param  bool  $bQuery Если true, то не надо экранировать строки (вызов из метода query)
	 * @return mixed
	 */
	public function escape($Value, $bQuery = false) {
		if(is_array($Value)) {
			$aResult = [];
			foreach($Value as $ValueLine) {
				$aResult[] = $this->escapeValue($ValueLine, $bQuery);
			}
			return implode(',', $aResult);
		} else {
			return $this->escapeValue($Value, $bQuery);
		}
	}

	public function d($sQuery, $aBind = [], $bExit = true, $sPrefix = ':', $bPrint = true) {
		foreach($aBind as $k => $v) {
			$v = $this->escape($v);
			$sQuery = preg_replace('/'.$sPrefix.$k.'\b/', $v, $sQuery);
		}
		if($bPrint) {
			print_r($sQuery);
			if($bExit) {
				exit;
			}
		} else {
			return $sQuery;
		}
	}

	public function closeConnection() {
		if($this->_pdo) {
			$this->_pdo = null;
		}
	}
}
