<?php

class template extends BaseMethod
{
	protected $type = 'cookie';

	protected $aTemplateSortFields2RealFields = [
		'dt_add' => '"dt_add"',
		'dt_update' => '"dt_update"',
	];

	public function get($h) {
		$id = getIntParam($h, 'id');
		if($id) {
			$template = $this->getTemplate($id);
		} else {
			$templates = $this->getTemplates($h);
		}

		if(!$this->hasErrors()) {
			return $id ? ['template' => $template] : ['templates' => $templates];
		}
	}

	private function getTemplate($id) {
		$template = Connect::dbChild($this->User['server_data_ip'], $this->User['schema_data_id'])->queryOne(
			'SELECT "id", "structure", "content_html", "dt_add", "dt_update"'
			.' FROM "template"'
			.' WHERE "user_id"=:user_id AND "id"=:id AND "is_deleted"=FALSE;',
			[
				'user_id' => $this->User['id'],
				'id' => $id
			]
		);
		if($template) {
			$template['preview_url'] = $this->getPreviewUrl($this->User['id'], $id);
		} else {
			$this->addError('template_not_found');
		}
		return $template;
	}

	private function getTemplates($h) {
		// Лимиты
		$sLimits = getLimits($h);

		// Сортировка
		$sSortConditions = getSortConditions($h, $this->aTemplateSortFields2RealFields, 'dt_update', 'desc');

		$list = Connect::dbChild($this->User['server_data_ip'], $this->User['schema_data_id'])->queryAll(
			'SELECT "id", "dt_add", "dt_update", "version"'
			.' FROM "template"'
			.' WHERE "user_id"=:user_id AND "is_deleted"=FALSE'
			.' ORDER BY '.$sSortConditions.$sLimits.';',
			['user_id' => $this->User['id']]
		);
		foreach($list as $index => $data) {
			$list[$index]['preview_url'] = $this->getPreviewImageUrl($data['id'], $data['version'], true);
		}
		$countAll = Connect::dbChild()->queryScalar(
			'SELECT COUNT("id")'
			.' FROM "template"'
			.' WHERE "user_id"=:user_id AND "is_deleted"=FALSE;',
			['user_id' => $this->User['id']]
		);
		if(!$this->hasErrors()) {
			return [
				'list' => $list,
				'count' => count($list),
				'count_all' => $countAll,
			];
		}
	}

	private function getPreviewImageUrl($templateId, $version = null, $isFull = false) {
		$middleUrlPart = $this->getTemplateFolderPath($templateId).'/preview_'.md5($templateId.$this->conf['security']['template_hash_salt']).'.png';
		if($isFull) {
			return $this->User['server_static_external_url'].$middleUrlPart.($version ? '?v='.$version : '');
		} else {
			return $middleUrlPart;
		}
	}

	// Используется для добавления шаблона, все поля письма на текущий момент всегда передается из кабинета пустыми
	public function post($h) {
		$curDt = date('Y-m-d H:i:s', time());
		$templateId = Connect::dbChild($this->User['server_data_ip'], $this->User['schema_data_id'])->insert(
			'template',
			[
				'user_id' => $this->User['id'],
				'structure' => '',
				'dt_add' => $curDt,
				'dt_update' => $curDt,
			]
		);
		// pixel preview
		$this->createShotPixel($templateId);

		return ['template' => $this->getTemplate($templateId)];
	}

	public function put($h) {
		$id = getIntParam($h, 'id');
		$structure = getStringParam($h, 'structure');
		if($id && !is_null($structure)) {
			$template = Connect::dbChild($this->User['server_data_ip'], $this->User['schema_data_id'])->queryOne(
				'SELECT "id", "structure" FROM "template" WHERE "user_id"=:user_id AND "id"=:id AND "is_deleted"=FALSE;',
				[
					'id' => $id,
					'user_id' => $this->User['id'],
				]
			);
			if($template) {
				//if($structure != $template['structure']) { // почему-то отрабатывает не корректно при изменении картинки
					$contentHtml = $this->generateContentHtml($structure);
					Connect::dbChild()->query(
						'UPDATE "template"'
						.' SET "structure"=:structure, "content_html"=:content_html, "dt_update"=NOW(), "version"="version"+1'
						.' WHERE "id"=:id;',
						[
							'id' => $id,
							'structure' => $structure,
							'content_html' => $contentHtml,
						]
					);
					$this->createShot($id);

					// Получаем изображения используемые в шаблоне
					$letterImages = getHtmlImageUrls($contentHtml);
					// todo 1: Получает список всех изображений на CDN, привязанных к шаблону
					// todo 2: Удаляем из базы и из CDN все изображения которые в данный момент не используются в шаблоне



					return ['template' => ['id' => $id]];
				//}
			} else {
				$this->addError('template_not_found');
			}
		} else {
			$this->addError('required_params_missed');
		}
	}

	/*private function processLetterTemplate($iLetterId, $iProjectId, $iProjectSpaceId, $sContentHtml, $bIsNewLetterTemplate = true) {
		// Сохранение ссылок из письма
		$this->processLetterLinks($iLetterId, $sContentHtml, false);
		// Сохраняем данные о версие письма
		$this->addLetterVersion($iLetterId);
		// Генерация превью
		if($sContentHtml) {
			$this->createShot($iLetterId, $iProjectId, $iProjectSpaceId);
		} else {
			// pixel preview
			$this->createShotPixel($iLetterId, $iProjectId, $iProjectSpaceId);
		}
		if($bIsNewLetterTemplate) {
			// Пересчёт количества писем в проекте
			$this->updateProjectCntLetter();
		}
	}*/

	public function delete($h) {
		$id = getIntParam($h, 'id');
		if($id) {
			$template = Connect::dbChild($this->User['server_data_ip'], $this->User['schema_data_id'])->queryOne(
				'SELECT "id", "structure" FROM "template" WHERE "user_id"=:user_id AND "id"=:id AND "is_deleted"=FALSE;',
				[
					'id' => $id,
					'user_id' => $this->User['id'],
				]
			);

			if($template) {
				Connect::dbChild()->query(
					'UPDATE "image" SET "is_deleted"=TRUE WHERE "template_id"=:id;',
					['id' => $id]
				);
				Connect::dbChild()->query(
					'UPDATE "template" SET "is_deleted"=TRUE WHERE "id"=:id;',
					['id' => $id]
				);
				return ['success'];
			} else {
				$this->addError('template_not_found');
			}
		} else {
			$this->addError('required_params_missed');
		}
	}

	/*private function deleteShot($letterId) {
		try {
			$this->attachManager($this->User['server_static_id'])->delete($this->getPreviewImageUrl($letterId, null, true));
			return true;
		} catch(Exception $e) {
			return false;
		}
	}

	// Удаляем файлы аттачмента и его запись в бд, но не привязки аттачмента к письму
	private function deleteAttachment($iLetterId, $attachment) {
		if(!empty($attachment['url'])) {
			try {
				$this->attachManager($this->User['server_static_id'])->delete($attachment['url']);
			} catch(Exception $e) {
			}
		}
		if(!empty($attachment['preview'])) {
			try {
				$this->attachManager($this->User['server_static_id'])->delete($attachment['preview']);
			} catch(Exception $e) {
			}
		}

		Connect::dbChild()->query(
			'DELETE FROM "attachment" WHERE "id"=:id;',
			['id' => $attachment['id']]
		);
	}*/

	private function createShot($templateId) {
		$pUrl = '"'.$this->getPreviewUrl($this->User['id'], $templateId).'"';
		$tmpPath = TMPPATH.'/shot'.$templateId.'.png';
		if(!is_dir(TMPPATH)) {
			mkdir(TMPPATH, 0777, true);
		}

		try {
			echo exec(
				BINPATH.'/phantomjs '.
				($this->conf['server']['api_protocol'] == 'https' ? '--ssl-protocol=any --ignore-ssl-errors=yes ' : '').
				PHANTOMPATH.'/screenshot.local.js '. // файл перегенерируется скриптом update_screenshot.local.js.sh
				$pUrl.' '.
				$tmpPath
			);

			if(file_exists($tmpPath)) {
				$image = resizeProportionalImage($tmpPath, $this->conf['template']['preview_width']);

				if($image) {
					$path = $this->getPreviewImageUrl($templateId);
					$this->attachManager($this->User['server_static_id'])->save($image, $path);
					unlink($tmpPath);
					return true;
				}
			}
		} catch(Exception $e) {
		}
	}

	protected function getPreviewUrl($userId, $templateId) {
		return $this->conf['server']['api_protocol'].'://'.$this->conf['server']['api_domain']
		.'/template_preview_share?user_id='.$userId.'&id='.$templateId.'&hsh='.$this->getTemplatePreviewHsh($userId, $templateId);
	}

	private function createShotPixel($templateId, $version = 1) {
		$tmpPath = HTDOCSPATH.'/pixel.png';

		try {
			$image = reCropImage($tmpPath, 1, 1);
			if($image) {
				$path = $this->getPreviewImageUrl($templateId);
				$fUrl = $this->attachManager($this->User['server_static_id'])->save($image, $path);
				return $fUrl;
			}
		} catch(Exception $e) {
		}
		return false;
	}
}
