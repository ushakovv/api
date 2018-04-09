<?php

$file = '1.zip';
header("Content-Type: application/octet-stream");
header("Accept-Ranges: bytes");
header("Content-Length: ".filesize($file));
header("Content-Disposition: attachment; filename=".basename($file));
readfile($file); // Читает файл и записывает его в буфер вывода

exit;