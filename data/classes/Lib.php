<?php

require_once dirname(__FILE__).'/Config.php';

// Паттерн для url`ов изображений в html
const HTML_IMAGES_URL_PATTERN = <<<PATTERN
/(?:src\s*=|background\s*=|background\s*:\s*url)\s*(?:(?:"|'|\(\s*"|\(\s*'|\()\s*((?!"|'|"\s*\)|'\s*\)|\)).+?)(?:"|'|"\s*\)|'\s*\)|\))|([^^\s>]+))/i
PATTERN;

// Паттерн для url`ов ссылок в html
const HTML_A_HREF_PATTERN = <<<PATTERN
/(href="([^"]+)")|(href=\'([^\']+)\')/i
PATTERN;

// Паттерн для проверки валидности доменного имени
const DOMAIN_PATTERN = <<<PATTERN
/^([a-zа-яё0-9]([a-zа-яё0-9\-]{0,61}[a-zа-яё0-9])?\.)+[a-zа-яё]{2,6}$/ui
PATTERN;

// Паттерн для проверки наличия тега <script>
const SCRIPT_TAG_PATTERN = <<<PATTERN
/<[\s\n]*script[\s\n]*>[\D\d]*<[\s\n]*\/[\s\n]*script[\s\n]*>/ui
PATTERN;

// Вывод строки дебага
function d($var = null, $isVarDump = false, $exit = true) {
	if($isVarDump) {
		var_dump($var);
	} else {
		print_r($var);
	}
	if($exit) {
		exit;
	}
}

// Пропорциональный ресайз картинки по ширине
function resizeProportionalImage($originalFile, $sizeW) {
	try {
		list($imagewidth, $imageheight, $imageType) = getimagesize($originalFile);
		$ext = getImageExtension($originalFile);
		if($ext == 'png') {
			$src = imagecreatefrompng($originalFile);
		} else if($ext == 'gif') {
			$src = imagecreatefromgif($originalFile);
		} else if($ext == 'jpg' || $ext == 'jpeg') {
			$src = imagecreatefromjpeg($originalFile);
		}
		$r_width = $sizeW;
		$koe = $imagewidth / $sizeW;
		$r_height = ceil($imageheight / $koe);
		$dst = imageCreateTrueColor($r_width, $r_height);
		imageAlphaBlending($dst, false);
		imageSaveAlpha($dst, true);
		ImageCopyResampled($dst, $src, 0, 0, 0, 0, $sizeW, $r_height, $imagewidth, $imageheight);
		ob_start();
		if($ext == 'png') {
			imagepng($dst);
		} else if($ext == 'gif') {
			imagegif($dst);
		} else if($ext == 'jpg' || $ext == 'jpeg') {
			imagejpeg($dst);
		}

		$result = ob_get_contents();
		ob_end_clean();
	} catch(Exception $e) {
		$result = false;
	}

	return $result;
}

// Обрезка изображения через Imagick
function reCropImageImagick($originalFile, $sizeW, $sizeH) {
	$tmpFile = TMPPATH.'/'.uniqid('', true).'.'.getImageExtension($originalFile);
	$cmd = "convert $originalFile -resize {$sizeW}x{$sizeH}^ -gravity center -extent {$sizeW}x{$sizeH} $tmpFile";
	exec($cmd);
	if(file_exists($tmpFile)) {
		$content = file_get_contents($tmpFile);
		unlink($tmpFile);
		return $content;
	} else {
		return "";
	}
}

// Обрезка изображения через GD
function reCropImageGd($originalFile, $sizeW, $sizeH) {
	try {
		list($imageWidth, $imageHeight, $imageType) = getimagesize($originalFile);
		$ext = getImageExtension($originalFile);

		$src = false;
		if($ext == 'png') {
			$src = @imagecreatefrompng($originalFile);
		} else if($ext == 'gif') {
			$src = @imagecreatefromgif($originalFile);
		} else if($ext == 'jpg' || $ext == 'jpeg') {
			$src = @imagecreatefromjpeg($originalFile);
		}

		// При ошибке формирования картинки
		if(!$src) {
			// Создаем пустое изображение
			$src = imagecreatetruecolor($sizeW, $sizeH);
			$bgc = imagecolorallocate($src, 255, 255, 255);
			imagefill($src, 0, 0, $bgc);
		}

		$r_width = $sizeW;
		$r_height = $sizeH;
		$dst_y = $dst_x = $src_y = $src_x = 0;

		$dst = imageCreateTrueColor($sizeW, $sizeH);

		$white = imagecolorallocate($dst, 255, 255, 255);
		imagefill($dst, 0, 0, $white);

		if($imageWidth <= $sizeW) {
			if($imageHeight <= $sizeH) {
				//1 - ширина и высота меньше
				$imageWidth = $imageHeight;
				$r_height = $imageHeight;
				$r_width = $r_height;
				$src_x = ceil(($imageWidth - $r_width) / 2);
				$dst_x = ceil(($sizeW - $r_width) / 2);
				$dst_y = ceil(($sizeH - $r_height) / 2);
				$imageWidth = $imageHeight;
			} else {
				//3 - ширина меньше и высота больше 
				$r_height = $sizeH;
				$r_width = $imageWidth;
				$dst_x = ceil(($sizeW - $r_width) / 2);
				$imageHeight = $sizeH;
			}
		} else {
			if($imageHeight <= $sizeH) {
				//2 - ширина больше и высота меньше
				$r_height = $imageHeight;
				$src_x = ceil(($imageWidth - $r_width) / 2);
				$dst_y = ceil(($sizeH - $r_height) / 2);
				$imageWidth = $sizeW;
			} else {
				//4 - ширина и высота больше
				if($imageHeight > $imageWidth) {
					$imageHeight = $imageWidth;
				} else {
					$src_x = ceil(($imageWidth - $imageHeight) / 2);
					$imageWidth = $imageHeight;
				}
			}
		}

		imageAlphaBlending($dst, false);
		imageSaveAlpha($dst, true);
		ImageCopyResampled($dst, $src, $dst_x, $dst_y, $src_x, $src_y, $r_width, $r_height, $imageWidth, $imageHeight);
		ob_start();
		if($ext == 'png') {
			imagepng($dst);
		} else if($ext == 'gif') {
			imagegif($dst);
		} else if($ext == 'jpg' || $ext == 'jpeg') {
			imagejpeg($dst);
		}

		$result = ob_get_contents();
		ob_end_clean();
	} catch(Exception $e) {
		$result = false;
	}

	return $result;
}

// Обёртка для обрезки изображений с возможностью выбора утилиты для обрезки
function reCropImage($originalFile, $sizeW, $sizeH, $type = 'imagick') {
	if($type == 'gd') {
		return reCropImageGd($originalFile, $sizeW, $sizeH);
	} else {
		return reCropImageImagick($originalFile, $sizeW, $sizeH);
	}
}

// Транслитирация строки
function translit($string, $isOnlyLetters = false) {
	$rus = [
		'а' => 'a',
		'б' => 'b',
		'в' => 'v',
		'г' => 'g',
		'д' => 'd',
		'е' => 'e',
		'ё' => 'e',
		'ж' => 'zh',
		'з' => 'z',
		'и' => 'i',
		'й' => 'i',
		'к' => 'k',
		'л' => 'l',
		'м' => 'm',
		'н' => 'n',
		'о' => 'o',
		'п' => 'p',
		'р' => 'r',
		'с' => 's',
		'т' => 't',
		'у' => 'u',
		'ф' => 'f',
		'х' => 'h',
		'ц' => 'c',
		'ч' => 'ch',
		'ш' => 'sh',
		'щ' => 'sh',
		'ъ' => '',
		'ы' => 'y',
		'ь' => '',
		'э' => 'e',
		'ю' => 'yu',
		'я' => 'ya',
	];
	if(!$isOnlyLetters) {
		// Замена не только букв, но и некоторых знаков
		$rus = array_merge(
			$rus,
			[
				' ' => '_',
				',' => '_',
				'.' => '_',
				'/' => '-',
				'?' => '',
				'!' => '',
				'—' => '-',
				'-' => '-',
				':' => '',
				';' => '',
				'«' => '',
				'»' => '',
				'"' => '',
				"'" => '',
				'(' => '',
				')' => '',
				'–' => '-',
				'…' => '_',
				'&' => '',
				'№' => '',
				'’' => '',
			]
		);
	}

	$result = '';

	for($i = 0; $i < mb_strlen($string, 'UTF-8'); $i++) {
		$char = mb_substr($string, $i, 1, 'UTF-8');

		if(isset($rus[$char])) {
			$result .= $rus[$char];
		} elseif(isset($rus[mb_strtolower($char, 'UTF-8')])) {
			$result .= $rus[mb_strtolower($char, 'UTF-8')];
		} else {
			$result .= $char;
		}
	}

	$result = preg_replace("/([_]{2,})/", "_", $result);

	return mb_strtolower($result, 'UTF-8');
}

// Генерация уникального идентификатора/строки
function uuid() {
	// The field names refer to RFC 4122 section 4.1.2
	return sprintf('%04x%04x-%04x-%03x4-%04x-%04x%04x%04x',
		mt_rand(0, 65535), mt_rand(0, 65535), // 32 bits for "time_low"
		mt_rand(0, 65535), // 16 bits for "time_mid"
		mt_rand(0, 4095),  // 12 bits before the 0100 of (version) 4 for "time_hi_and_version"
		bindec(substr_replace(sprintf('%016b', mt_rand(0, 65535)), '01', 6, 2)),
		// 8 bits, the last two of which (positions 6 and 7) are 01, for "clk_seq_hi_res"
		// (hence, the 2nd hex digit after the 3rd hyphen can only be 1, 5, 9 or d)
		// 8 bits for "clk_seq_low"
		mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535) // 48 bits for "node"
	);
}

// Получения реального IP пользователя
function getRealIp() {
	$ip = null;
	if(!empty($_SERVER['HTTP_CLIENT_IP'])) {
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	} elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	} elseif(!empty($_SERVER['REMOTE_ADDR'])) {
		$ip = $_SERVER['REMOTE_ADDR'];
	}
	return $ip;
}

function save2log($iTime, $sQuery = '') {
	global $conf;
	if(isset($conf['debug']) && isset($conf['debug']) && $conf['debug']['enable'] == 1 && $conf['debug']['save_slow_log'] == 1 && $iTime >= $conf['debug']['slow_work_time']) {
		$logsPath = TMPPATH.'/log';
		if(!is_dir($logsPath)) {
			mkdir($logsPath, 0755, true);
		}
		$file = $logsPath."/pgsql.slow.log"; //куда пишем логи

		$ip = getRealIp();
		$date = date("Y-m-d H:i:s", time());
		$home = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''; // какая страница сайта

		$s = $date."	".$iTime."	".$ip."	".$home;
		$f = fopen($file, "a+");
		fwrite($f, "$s".(isset($sQuery) ? "	".$sQuery : '')."\r\n");
		fclose($f);
	}
}

function getSubscribeConfirmHsh($iProjectSpaceId, $iWidgetId, $iEmailId) {
	global $conf;
	return md5('subscribe_confirm'.$iProjectSpaceId.'_'.$iWidgetId.'_'.$iEmailId.'_'.$conf['security']['subscribe_confirm_salt']);
}

function getUnsubsCheckEmailHsh($email, $time, $secretApiKey) {
	return md5('email_check'.$email.'_'.$time.'_'.$secretApiKey);
}

// Вычленяем доменное имя из емайла
function getDomainByEmail($email) {
	$aResult = explode('@', $email, 2);
	return isset($aResult[1]) ? $aResult[1] : false;
}

// Подготовка массива для записи в CSV файл. Предполагается что в массиве должны быть заполнены все ячейки от 0 до $lastIndex.
// Если какие-то из ячееек не существуют, они создаются и заполняются пустой строкой.
function prepareArrayToCsv($array) {
	$lastIndex = max(array_keys($array));
	$out = [];
	for($i = 0; $i <= $lastIndex; $i++) {
		$out[$i] = isset($array[$i]) ? $array[$i] : '';
	}
	return $out;
}

// Валидация ИНН. $length = 10 или 12 цифр (юр. лицо или физ. лицо)
function isValidInn($inn, $length = null) {
	if(preg_match('/\D/', $inn)) {
		return false;
	}

	$inn = (string)$inn;
	$len = strlen($inn);

	if($length && $length == $len) {
		if($len == 10) {
			return $inn[9] == (string)(((
						2 * $inn[0] + 4 * $inn[1] + 10 * $inn[2] +
						3 * $inn[3] + 5 * $inn[4] + 9 * $inn[5] +
						4 * $inn[6] + 6 * $inn[7] + 8 * $inn[8]
					) % 11) % 10);
		} elseif($len == 12) {
			$num10 = (string)(((
						7 * $inn[0] + 2 * $inn[1] + 4 * $inn[2] +
						10 * $inn[3] + 3 * $inn[4] + 5 * $inn[5] +
						9 * $inn[6] + 4 * $inn[7] + 6 * $inn[8] +
						8 * $inn[9]
					) % 11) % 10);

			$num11 = (string)(((
						3 * $inn[0] + 7 * $inn[1] + 2 * $inn[2] +
						4 * $inn[3] + 10 * $inn[4] + 3 * $inn[5] +
						5 * $inn[6] + 9 * $inn[7] + 4 * $inn[8] +
						6 * $inn[9] + 8 * $inn[10]
					) % 11) % 10);

			return $inn[11] == $num11 && $inn[10] == $num10;
		}
	}

	return false;
}

// Валидация 20-значного корреспондентского или расчетного счета
function isValidBankAccount($schet) {
	if(!preg_match('/\D/', $schet)) {
		$schet = (string)$schet;
		$len = strlen($schet);
		if($len == 20) {
			return true;
		}
	}

	return false;
}

// Валидация КПП
function isValidKpp($kpp) {
	$kpp = (string)$kpp;
	if(strlen($kpp) == 9) {
		if(preg_match('/\d{4}[\dA-Z][\dA-Z]\d{3}/', $kpp)) {
			return true;
		}
	}

	return false;
}

// Валидация БИК
function isValidBik($bik) {
	if(!preg_match('/\D/', $bik)) {
		$bik = (string)$bik;
		if(strlen($bik) == 9) {
			return true;
		}
	}

	return false;
}

// Валидация email
// Полностью кириллические домены вида "инфо@почта.рф" начинают поддерживаться все большим числом серсисов,
// в частности gmail заявила о поддержке, поэтому мы их пропускаем
function isValidEmail($email) {
	// TODO: дописать проверку, что в имени ящика и в имени домена (отдельно друг от друга) должны содержаться либо только кириллические, либо только латинские символы
	return filter_var(preg_replace('/[а-яё]/iu', 'w', $email), FILTER_VALIDATE_EMAIL) ? true : false;
}

// Супер примитивная валидация Российских телефонов
function isValidPhoneNumber($phoneNumber) {
	return preg_match("/^(8|\+7){1}[\d]{10}$/", $phoneNumber) ? true : false;
}

// Валидация формата даты (Y-m-d)
function isValidDate($sValue) {
	if(preg_match("/^(\d{4})\-(\d{2})\-(\d{2})$/", $sValue, $aValue)) {
		if(checkdate($aValue[2], $aValue[3], $aValue[1])) {
			return true;
		}
	}

	return false;
}

// Валидация MySQL формата даты и времени (Y-m-d H:i:s)
function isValidDateTime($sValue) {
	if(preg_match("/^(\d{4})\-(\d{2})\-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/", $sValue, $aValue)) {
		if(checkdate($aValue[2], $aValue[3], $aValue[1]) && $aValue[4] < 24 && $aValue[5] < 60 && $aValue[6] < 60) {
			return true;
		}
	}

	return false;
}

// Нормализация номера телфона: удаляет всё кроме цифр и знака +
function normalizePhoneNumber($phoneNumber) {
	return preg_replace('/[^0-9\+]/i', '', $phoneNumber);
}

// Проверяем по расширению является ли файл картинкой
function isImageExtension($sExtension) {
	$aImagesExts = ['jpg', 'jpeg', 'png', 'gif'];
	return in_array($sExtension, $aImagesExts);
}

// Проверяем по Сontent Type является ли файл картинкой
function isImageMimeType($contentType) {
	$ext = false;
	if(strpos($contentType, 'image/gif') !== false) {
		$ext = 'gif';
	} elseif(strpos($contentType, 'image/png') !== false) {
		$ext = 'png';
	} elseif(strpos($contentType, 'image/jpeg') !== false || strpos($contentType, 'image/pjpeg') !== false) {
		$ext = 'jpeg';
	}
	return $ext;
}

// Проверка является ли файл картинкой
// Получение расширения картинки по её содержимому, а не по полному имени
// false если переданный файл - не является поддерживаемым форматом картинки
function getImageExtension($sPath = null, $sContent = null) {
	$finfo = new finfo(FILEINFO_MIME);
	if($sPath) {
		$sContent = file_get_contents($sPath);
	}
	$contentType = $finfo->buffer($sContent);
	return isImageMimeType($contentType);
}

// Форматированный вывод денежной суммы
function moneyFormat($money) {
	return number_format($money, 2, '.', ' ');
}

// Форматированный вывод времени (если время меньше 1 секунды то выдаем 2 цифры после запятой, если больше то целое число)
function timeFormat($time) {
	return number_format($time, $time >= 1 ? 0 : 2, '.', '');
}

// Форматированный вывод числа с плавающей точкой, с указанным количеством знаков после запятой
function doubleFormat($double, $minDecimals = 0) {
	$tmp = fmod($double, 1);
	if($tmp == 0) {
		$decimals = 0;
	} else {
		if($double > 1) {
			$decimals = 1;
			if($tmp < 0.1) {
				$decimals++;
				$tmp = $tmp * 10;
				if($tmp < 0.1) {
					$decimals = 0;
				}
			}
		} else {
			$decimals = 1; // от 1 до 7
			while($tmp < 0.1) {
				$decimals++;
				$tmp = $tmp * 10;
			}
		}
	}

	if($decimals < $minDecimals) {
		$decimals = $minDecimals;
	}

	return number_format($double, $decimals, '.', '');
}

// Перевод из байтов в мегабайты и вывод с точностью до первой значащей цифры после запятой
function byteToMegabyte($iByte) {
	if($iByte > 0) {
		return doubleFormat($iByte / 1048576);
	}
	return '0';
}

// Округление суммы до копеек (в меньшую сторону)
function roundUpMoneyToCent($float) {
	return intval($float * 100) / 100;
}

// Преобразование даты из базы к текстовому формату и вывод в виде массива из даты и времени
function datetimeFormatArray($datetime, $isFullDate = false) {
	$diverTime = strtotime($datetime);
	$curTime = time();
	$diverMount = date('n', $diverTime);

	$aMonths = [
		1 => 'января',
		2 => 'февраля',
		3 => 'марта',
		4 => 'апреля',
		5 => 'мая',
		6 => 'июня',
		7 => 'июля',
		8 => 'августа',
		9 => 'сентября',
		10 => 'октября',
		11 => 'ноября',
		12 => 'декабря',
	];
	$month = isset($aMonths[$diverMount]) ? $aMonths[$diverMount] : $diverMount;

	if($isFullDate) {
		$format = 'j '.$month.' Y';
	} else {
		$curYear = date('Y', $curTime);
		$diverYear = date('Y', $diverTime);
		$curDate = date('Y-m-d', $curTime);
		$yesterdayDate = date('Y-m-d', $curTime - 86400);
		$tomorrowDate = date('Y-m-d', $curTime + 86400);
		$diverDate = date('Y-m-d', $diverTime);

		if($curDate == $diverDate) {
			$format = 'сегодня';
		} elseif($yesterdayDate == $diverDate) {
			$format = 'вчера';
		} elseif($tomorrowDate == $diverDate) {
			$format = 'завтра';
		} elseif($curYear == $diverYear) {
			$format = 'j '.$month;
		} else {
			$format = 'j '.$month.' Y';
		}
	}

	return [
		'date' => date($format, $diverTime),
		'time' => date('H:i', $diverTime),
	];
}

// Вывод даты и времени в текстовом формате
function datetimeFormat($datetime) {
	$aDatetimeFormat = datetimeFormatArray($datetime);
	return $aDatetimeFormat['date'].' '.$aDatetimeFormat['time'];
}

// Вывод даты в текстовом формате
function dateFormat($datetime) {
	$aDatetimeFormat = datetimeFormatArray($datetime);
	return $aDatetimeFormat['date'];
}

// Множественные формы
function t($n, $form1, $form2, $form5) {
	if($n > 0 && $n < 1) return $form2;
	$n = abs($n) % 100;
	$n1 = $n % 10;
	if($n > 10 && $n < 20) return $form5;
	if($n1 > 1 && $n1 < 5) return $form2;
	if($n1 == 1) return $form1;
	return $form5;
}

// Для логирования в файл из любого места в проекте
function saveInDefaultLog($data) {
	file_put_contents(TMPPATH.'/log/default.log', date('Y-m-d H:i:s', time()).' '.(is_array($data) ? json_encode($data) : $data)."\r\n", FILE_APPEND);
}

// Ограничение на limit
function normaliseLimit($limit) {
	$limit = intval($limit);
	if($limit < 0) {
		$limit = 0;
	} elseif($limit > 100) {
		$limit = 100;
	}
	return $limit;
}

// Ограничение на offset
function normaliseOffset($offset) {
	$offset = intval($offset);
	if($offset < 0) {
		$offset = 0;
	}
	return $offset;
}

// Формируем SQL для лимота и оффсета
function getLimitSql($limit, $offset = 0, $sSqlSyntax = 'postgres') {
	$sLimitSql = '';
	if($sSqlSyntax == 'postgres') {
		$sLimitSql = ' LIMIT '.$limit.' OFFSET '.$offset;
	} elseif($sSqlSyntax == 'mysql') {
		$sLimitSql = ' LIMIT '.$offset.', '.$limit;
	}
	return $sLimitSql;
}

// Получение лимита/оффсета из параметров запроса, нормализация и формирования SQL кода в соответсвующем синтаксисе
// В качестве $h например можно передать $_GET или $_POST, ожидаемые ключи limit и offset
function getLimits($h, $sSqlSyntax = 'postgres') {
	$limit = isset($h['limit']) ? normaliseLimit($h['limit']) : 10;
	$offset = isset($h['offset']) ? normaliseOffset($h['offset']) : 0;
	return getLimitSql($limit, $offset, $sSqlSyntax);
}

// Получение условий сортрировки из параметров запроса, формирования SQL кода
// В качестве $h например можно передать $_GET или $_POST, ожидаемые ключи sort и sort_order
// $aSortFields2RealFields - разрешенные для сортировки поля (пример для PostgreSQL: ['id' => '"act"."id"', 'date' => '"act"."date"'])
function getSortConditions($h, $aSortFields2RealFields, $sDefaultSortField, $sDefaultSortOrder) {
	$sSort = isset($h['sort']) ? (isset($aSortFields2RealFields[$h['sort']]) ? $h['sort'] : $sDefaultSortField) : $sDefaultSortField;
	$sSortOrder = isset($h['sort_order']) ? strtolower($h['sort_order']) : $sDefaultSortOrder;
	if(in_array($sSortOrder, ['desc', '-1', -1])) {
		$sSortOrder = 'DESC';
	} else {
		$sSortOrder = 'ASC';
	}
	$sSortConditions = $aSortFields2RealFields[$sSort].' '.$sSortOrder.' NULLS LAST';
	if($sSort != $sDefaultSortField) {
		$sSortConditions .= ', '.$aSortFields2RealFields[$sDefaultSortField].' '.$sSortOrder.' NULLS LAST';
	}

	return $sSortConditions;
}

// Формирует CQL контрукции IF для заданной СУБД
function getIfSqlConstruction($sCondition, $sThen, $sElse, $sSqlSyntax = 'postgres') {
	if($sSqlSyntax == 'postgres') {
		return 'CASE WHEN '.$sCondition.' THEN '.$sThen.' ELSE '.$sElse.' END';
	} elseif($sSqlSyntax == 'mysql') {
		return 'IF('.$sCondition.', '.$sThen.', '.$sElse.')';
	}
}

// Явно указываем, что следующий запрос должен быть обращён к master серверу БД.
// Строка должна доклеиваться в начало выполняемого запроса (мини хак в PostgreSQL).
function setMasterQuery() {
	return '/*REPLICATION*/';
}

// Сумма прописью
function moneyToString($money) {
	// Сумму ограничиваем разрядом миллиардов
	if($money > 0 && $money <= 999999999999) {
		$nul = 'ноль';
		$ten = [
			['', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'],
			['', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'],
		];
		$a20 = ['десять', 'одиннадцать', 'двенадцать', 'тринадцать', 'четырнадцать', 'пятнадцать', 'шестнадцать', 'семнадцать', 'восемнадцать', 'девятнадцать'];
		$tens = [2 => 'двадцать', 'тридцать', 'сорок', 'пятьдесят', 'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто'];
		$hundred = ['', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот', 'шестьсот', 'семьсот', 'восемьсот', 'девятьсот'];
		$unit = [ // Units
			['копейка', 'копейки', 'копеек', 1],
			['рубль', 'рубля', 'рублей', 0],
			['тысяча', 'тысячи', 'тысяч', 1],
			['миллион', 'миллиона', 'миллионов', 0],
			['миллиард', 'милиарда', 'миллиардов', 0],
		];

		list($rub, $kop) = explode('.', sprintf("%015.2f", floatval($money)));
		$out = [];
		if(intval($rub) > 0) {
			foreach(str_split($rub, 3) as $uk => $v) { // by 3 symbols
				if(!intval($v)) continue;
				$uk = sizeof($unit) - $uk - 1; // unit key
				$gender = $unit[$uk][3];
				list($i1, $i2, $i3) = array_map('intval', str_split($v, 1));
				// mega-logic
				$out[] = $hundred[$i1]; # 1xx-9xx
				if($i2 > 1) $out[] = $tens[$i2].' '.$ten[$gender][$i3]; # 20-99
				else $out[] = $i2 > 0 ? $a20[$i3] : $ten[$gender][$i3]; # 10-19 | 1-9
				// units without rub & kop
				if($uk > 1) $out[] = t($v, $unit[$uk][0], $unit[$uk][1], $unit[$uk][2]);
			}
		} else $out[] = $nul;
		$out[] = t(intval($rub), $unit[1][0], $unit[1][1], $unit[1][2]); // rub
		$out[] = $kop.' '.t($kop, $unit[0][0], $unit[0][1], $unit[0][2]); // kop
		return trim(preg_replace('/ {2,}/', ' ', join(' ', $out)));
	} else {
		return $money;
	}
}

// Вычленяем домен из url
function getDomainByUrl($url) {
	$url = strtolower(trim($url));
	if(preg_match(DOMAIN_PATTERN, $url)) {
		return $url;
	} else {
		$tryParseUrl = parse_url(trim($url), PHP_URL_HOST);
		return $tryParseUrl ? $tryParseUrl : $url;
	}
}

// Получаем домен второго уровня из домена любого уровня >=2
function getDomainSecondLevel($domain) {
	$domainSecondLevel = '';
	$aHost = explode('.', $domain);
	if($aHost) {
		// Получаем второй уровень доменного имени
		for($i = count($aHost) - 1, $j = 0; $j < 2; $i--, $j++) {
			if(!empty($aHost[$i])) {
				$domainSecondLevel = $aHost[$i].($domainSecondLevel ? '.' : '').$domainSecondLevel;
			}
		}
	}

	return $domainSecondLevel;
}

// Определяем размер удаленного файла по url
function getRemoteFileSize($url) {
	ob_start();
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_NOBODY, 1);
	curl_exec($ch);
	curl_close($ch);
	$head = ob_get_contents();
	ob_end_clean();
	$regex = '/Content-Length:\s([0-9].+?)\s/';
	preg_match($regex, $head, $matches);
	return isset($matches[1]) ? $matches[1] : 0;
}

// Нормализуем ключ свойства подписчика
function normalizePropsKey($key) {
	return preg_replace('/[^a-zа-яё0-9_\s]/ui', '', preg_replace('|\s+|', '_', mb_strtolower(trim($key))));
}

//
// Стандартные функции для получения переданных параметров
// В качестве $h например можно передать $_GET или $_POST
//

// Получаем строковый параметр
function getStringParam($h, $param) {
	return isset($h[$param]) ? trim($h[$param]) : null;
}

// Получение перечисляемых параметров
function getEnumParam($h, $param, $variants, $default = null) {
	$value = getStringParam($h, $param);
	if(!in_array($value, $variants)) {
		$value = $default;
	}
	return $value;
}

// Получаем пароль
function getPasswordParam($h, $param) {
	return isset($h[$param]) ? trim($h[$param]) : null;
}

// Получаем числовой параметр
function getIntParam($h, $param) {
	return isset($h[$param]) ? intval($h[$param]) : null;
}

// Получаем числовой параметр с дробной частью
function getFloatParam($h, $param) {
	return isset($h[$param]) ? floatval($h[$param]) : null;
}

// Получаем значение флага (1, 0, null)
function getFlagParam($h, $param) {
	return isset($h[$param]) && $h[$param] != '' ? ($h[$param] && strtolower(trim($h[$param])) != 'false' ? true : false) : null;
}

// Получаем булевое значение (true, false)
function getBoolParam($h, $param) {
	return isset($h[$param]) && $h[$param] && strtolower(trim($h[$param])) != 'false';
}

// Получаем данные в виде массива или через разделитель запятую
function getArrayParam($h, $param) {
	$aTmpData = !empty($h[$param]) ? (is_array($h[$param]) ? $h[$param] : explode(',', $h[$param])) : [];
	$aOutData = [];
	foreach($aTmpData as $data) {
		$tmpData = trim($data);
		if($tmpData) {
			$aOutData[] = $tmpData;
		}
	}
	return $aOutData ? $aOutData : null;
}

// Получаем челые числа в виде массива или через разделитель запятую
function getIntArrayParam($h, $param) {
	$data = !empty($h[$param]) ? (is_array($h[$param]) ? $h[$param] : explode(',', $h[$param])) : null;
	$intvalFunc = create_function('$item,$key', 'return intval($item);');
	array_walk($data, $intvalFunc);
	return $data;
}

// Получаем объект (ассоциативный массив) в виде массива или json`а
function getObjectParam($h, $param) {
	return isset($h[$param]) ? (is_array($h[$param]) ? $h[$param] : json_decode($h[$param], true)) : null;
}

// Получаем дату
function getDateParam($h, $sParam) {
	return isset($h[$sParam]) && $h[$sParam] && isValidDate($h[$sParam]) ? $h[$sParam] : null;
}

// Получаем дату и время
function getDateTimeParam($h, $sParam) {
	return isset($h[$sParam]) && $h[$sParam] && isValidDateTime($h[$sParam]) ? $h[$sParam] : null;
}

// Поиск в строке любой из массива подстрок
// В случае успеха - возвращает позицию первого включения любой из указанных строк; а если ничего не найдено - то false
function strpos_array($haystack, $needles = [], $offset = 0) {
	$chr = [];
	foreach($needles as $needle) {
		$res = strpos($haystack, $needle, $offset);
		if($res !== false) $chr[$needle] = $res;
	}
	if(empty($chr)) return false;
	return min($chr);
}

// Редирект и прерывание дальнейшего выполнения кода
function redirect($link) {
	header('Location: '.$link);
	exit;
}

function getDbDataString($aDbData) {
	return $aDbData['db_name'].'-'.$aDbData['schema_name'];
}

function getDbData($iDomainId, $bMain = false, $bString = false) {
	global $conf;
	if($bMain) {
		$aResult = [
			'db_name' => $conf['database']['main_db_name'],
			'schema_name' => $conf['database']['main_schema_name'],
		];
	} else {
		$aResult = [
			'db_name' => $conf['database']['domain_db_name'],
			'schema_name' => 'domain_'.$iDomainId,
		];
	}

	return (!$bString ? $aResult : getDbDataString($aResult));
}

// Обёртка для команды gearadmin --status
function getGearadminStatus($host = null, $port = null) {
	$info = shell_exec('gearadmin'.($host ? ' -h '.$host : '').($port ? ' -p '.$port : '').' --status');
	$count = explode(chr(10), $info);
	$tasks = [];
	foreach($count as $row) {
		$record = explode("\t", $row);
		if(count($record) > 3) {
			$tasks[$record[0]] = [
				'name' => $record[0],
				'queue' => $record[1],
				'running' => $record[2],
				'workers' => $record[3],
			];
		}
	}
	return $tasks;
}

// Более универсальный способ запросить удаленную страницу (чем file_get_contents($url), который иногда может былокироваться)
function getCurlData($url) {
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_TIMEOUT, 10);
	curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.16) Gecko/20110319 Firefox/3.6.16');
	$curlData = curl_exec($curl);
	curl_close($curl);
	return $curlData;
}

function execCommand($sCommand) {
	exec($sCommand, $aResult, $iStatus);
	return [
		'result' => $aResult,
		'status' => $iStatus,
		'success' => ($iStatus == 0),
	];
}

function mergeArray(array $array1, array $array2 = null, array $_ = null) {
	$args = func_get_args();
	$res = array_shift($args);
	while(!empty($args)) {
		$next = array_shift($args);
		foreach($next as $k => $v) {
			if(is_integer($k))
				isset($res[$k]) ? $res[] = $v : $res[$k] = $v;
			elseif(is_array($v) && isset($res[$k]) && is_array($res[$k]))
				$res[$k] = mergeArray($res[$k], $v);
			else
				$res[$k] = $v;
		}
	}
	return $res;
}

// Объединение многоуровневых ассоциативных массивов
// $bFirst - указывает, что обрабатывается первый уровень массива. При вызове данного метода этот параметр никогда явно не нужно передавать.
function mergeArrayAssoc($aData1, $aData2, $bFirst = true) {
	if(is_array($aData2)) {
		foreach($aData2 as $sData2Key => $aData2Line) {
			if(is_array($aData1)) {
				if(!isset($aData1[$sData2Key])) {
					$aData1[$sData2Key] = $aData2Line;
				} else {
					$aData1[$sData2Key] = mergeArrayAssoc($aData1[$sData2Key], $aData2Line, false);
				}
			} else {
				$aData1 = $aData2;
			}
		}
	} elseif(!is_array($aData1) || !empty($aData2) || !$bFirst) {
		$aData1 = $aData2;
	}

	return $aData1;
}

function setArrayAssocKey(&$aData, $Key, $Value = '', $bReplace = false) {
	if(!isset($aData[$Key]) || $bReplace) {
		$aData[$Key] = $Value;
	}
}

// Генерация пароля заданной длины по разрешенному алфавиту символов
function generatePassword($iLength, $sAlphabet = '0123456789abcdifghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()_+-={}?<>') {
	$sPass = '';

	$iAlphabetMaxIndex = strlen($sAlphabet) - 1;

	for($i = 0; $i < $iLength; $i++) {
		$sPass .= substr($sAlphabet, rand(0, $iAlphabetMaxIndex), 1);
	}

	return $sPass;
}

// Получаем изображения используемые html коде
function getHtmlImageUrls($contentHtml) {
	preg_match_all(HTML_IMAGES_URL_PATTERN, $contentHtml, $matches);
	$letterImages = [];
	foreach($matches[1] as $image) {
		if($image && !in_array($image, $letterImages)) {
			$letterImages[] = $image;
		}
	}
	foreach($matches[2] as $image) {
		if($image && !in_array($image, $letterImages)) {
			$letterImages[] = $image;
		}
	}

	return $letterImages;
}

// recursive delete folder
function deleteFolder($dir) {
	$files = array_diff(scandir($dir), ['.', '..']);
	foreach($files as $file) {
		(is_dir("$dir/$file")) ? delFolder("$dir/$file") : unlink("$dir/$file");
	}
	return rmdir($dir);
}