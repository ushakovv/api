<?php

class image extends BaseMethod
{
	protected $type = 'cookie';

	public function post($h) {
		$templateId = getIntParam($h, 'template_id');
		$file = isset($_FILES['file']) && $_FILES['file']['size'] ? $_FILES['file'] : null;

		if($templateId && $file) {
			if(is_uploaded_file($file['tmp_name'])) {
				if($this->checkTemplateAccess($templateId)) {
					$content = file_get_contents($file['tmp_name']); // charset=binary
					$imageExtension = getImageExtension(null, $content); // false if not image

					if($imageExtension) {
						if($file['size'] <= $this->User['size_image_max']) {
							$md5File = md5_file($file['tmp_name']);
							$image = Connect::dbChild()->getOne(
								'image',
								['md5_file', 'url'],
								[
									'template_id' => $templateId,
									'size' => $file['size'],
									'md5_file' => $md5File,
									'name' => $file['name'],
								]
							);

							if(!$image) {
								Connect::dbChild()->query(
									'INSERT INTO "image" ("template_id", "size", "md5_file", "name")'
									.' VALUES (:template_id, :size, :md5_file, :name);',
									[
										'template_id' => $templateId,
										'size' => $file['size'],
										'md5_file' => $md5File,
										'name' => $file['name'],
									]
								);
							} else {
								$md5File = $image['md5_file'];
							}

							try {
								if($md5File) {
									if(!$image) {
										$path = $this->getImageUrl($templateId, $md5File);
										$url = $this->attachManager($this->User['server_static_id'])->save($content, $path);
										// ---------
										if(!$url) {
											$url = $this->attachManager($this->User['server_static_id'])->save($content, $path);
										}
										// ---------

										if($url) {
											list($imageWidth, $imageHeight, $imageType) = getimagesize($url);
											Connect::dbChild()->update(
												'image',
												[
													'url' => $url,
													'extension' => $imageExtension,
													'width' => $imageWidth,
													'height' => $imageHeight,
												],
												[
													'template_id' => $templateId,
													'md5_file' => $md5File,
												]
											);
											/*
											// image preview
											$preview = reCropImage($file['tmp_name'], 100, 100);
											if($preview !== false) {
												$pPath = $this->getImageUrl($templateId, $attachId, false, true);
												$this->attachManager($this->User['server_static_id'])->save($preview, $pPath);
											}
											*/
										} else {
											Connect::dbChild()->delete(
												'image',
												[
													'template_id' => $templateId,
													'md5_file' => $md5File,
												]
											);
											$this->addError('attach_loading_error');
										}
									} else {
										$url = $image['url'];
									}

									if(!$this->hasErrors()) {
										return ['image' => ['url' => $url]];
									}
								} else {
									$this->addError('insert_error');
								}
							} catch(Exception $e) {
								Connect::dbChild()->delete(
									'image',
									[
										'template_id' => $templateId,
										'md5_file' => $md5File,
									]
								);
								$this->addError('attach_has_wrong_format');
							}
						} else {
							$this->addError('image_is_too_big', ['msg' => 'Размер изображения не должен превышать '.byteToMegabyte($this->User['size_image_max']).' мб']);
						}
					} else {
						$this->addError('insert_in_letter_can_only_image');
					}
				}
			} else {
				$this->addError('upload_file_error');
			}
		} else {
			$this->addError('required_params_missed');
		}
	}

	private function getImageUrl($templateId, $md5File, $isFull = false, $isPreview = false) {
		$middleUrlPart = $this->getTemplateFolderPath($templateId).'/'.$md5File.'_'.md5($templateId.$this->conf['security']['template_hash_salt']).($isPreview ? '_preview' : '').'.png';
		if($isFull) {
			return $this->User['server_static_external_url'].$middleUrlPart;
		} else {
			return $middleUrlPart;
		}
	}
}
