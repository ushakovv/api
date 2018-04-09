<?php
$sSuccessMsg = "\x1b[32m".'OK'."\x1b[0m";
$sFailMsg = "\x1b[31m".'Need install!'."\x1b[0m";

echo 'Zip extention:       '.(extension_loaded('zip') ? $sSuccessMsg : $sFailMsg)."\r\n";
echo 'Function zip_open:   '.(function_exists('zip_open') ? $sSuccessMsg : $sFailMsg)."\r\n";
echo 'Class ZipArchive:    '.(class_exists('ZipArchive', false) ? $sSuccessMsg : $sFailMsg)."\r\n";
echo 'Class GearmanWorker: '.(class_exists('GearmanWorker', false) ? $sSuccessMsg : $sFailMsg)."\r\n";
echo 'Imagick:             '.(extension_loaded('imagick') ? $sSuccessMsg : $sFailMsg)."\r\n";

exec('wget -h', $aOutWget);
echo 'Wget:                '.($aOutWget ? $sSuccessMsg : $sFailMsg)."\r\n";

// Gearadmin на аппах нужен, так как именно с них мы лезем на основной сервер гирмана для проверки статуса задач
exec('gearadmin -h', $aOutGearadmin);
echo 'Gearadmin:           '.($aOutGearadmin ? $sSuccessMsg : $sFailMsg)."\r\n";
