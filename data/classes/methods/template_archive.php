<?php

class template_archive extends APIServer
{
	protected $type = 'cookie';

	public function get($h) {
		$templateId = getIntParam($h, 'template_id');
		if($templateId) {
			$template = Connect::dbChild($this->User['server_data_ip'], $this->User['schema_data_id'])->queryOne(
				'SELECT "content_html" FROM "template" WHERE "user_id"=:user_id AND "id"=:id AND "is_deleted"=FALSE;',
				[
					'id' => $templateId,
					'user_id' => $this->User['id'],
				]
			);
			if($template) {
				// Временная директория для шаблона
				$tmpDir = TMPPATH.'/user'.$this->User['id'].'_template'.$templateId.'_'.md5(rand(0, 100000000));
				if(!is_dir($tmpDir)) {
					mkdir($tmpDir, 0755, true);
				}

				// Ищим картинки в файле
				$letterImages = getHtmlImageUrls($template['content_html']);
				if($letterImages) {
					$search = [];
					$replace = [];
					// Скачиваем картинки во временную директорию
					foreach($letterImages as $imageUrl) {
						if(file_exists($imageUrl)) {
							$filename = basename($imageUrl);
							$search[] = $imageUrl;
							$replace[] = $filename;
							file_put_contents($tmpDir.'/'.$filename, file_get_contents($imageUrl));
						}
					}
					// Заменяем ссылки на картинки в html на прямые
					$template['content_html'] = str_replace($search, $replace, $template['content_html']);
				}

				// Записываем шаблон на диск
				file_put_contents($tmpDir.'/index.html', $template['content_html']);

				// Создаем архив со всем содержимым папки
				$arсhiveName = 'template'.$templateId.'_'.date('YmdHis').'.zip';
				$arсhivePath = $tmpDir.'/'.$arсhiveName;
				$zip = new ZipArchive; // класс для работы с архивами
				if($zip->open($arсhivePath, ZipArchive::CREATE) === true) { // создаем архив, если все прошло удачно продолжаем
					$dir = opendir($tmpDir); // открываем папку с файлами
					while($file = readdir($dir)) { // перебираем все файлы из нашей папки
						if(is_file($tmpDir.'/'.$file) && $file != $arсhiveName) { // проверяем файл ли мы взяли из папки
							$zip->addFile($tmpDir.'/'.$file, $file); // и архивируем
						}
					}
					$zip->close(); // закрываем архив

					// Отдаем архив на скачивание
					header("Content-Type: application/octet-stream");
					header("Accept-Ranges: bytes");
					header("Content-Length: ".filesize($arсhivePath));
					header("Content-Disposition: attachment; filename=".$arсhiveName);
					readfile($arсhivePath); // Читает файл и записывает его в буфер вывода
					// Удаляем временную директорию шаблона и рекурсивно все файлы внутри, а также сам архив
					deleteFolder($tmpDir);
					exit;
				} else {
					$this->addError('an_error_occurred_on_creating_archive');
				}
			} else {
				$this->addError('template_not_found');
			}
		} else {
			$this->addError('required_params_missed');
		}
	}

}
