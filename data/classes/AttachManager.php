<?php
require_once CLASSESPATH.'/CdnFileSystem.php';

class AttachManager
{
	private $_fileSystem = null;
	public $basePath = "/";

	public function init($serverStatic) {
		if(!$this->_fileSystem) {
			$this->_fileSystem = new CdnFileSystem(
				$serverStatic['id'], $serverStatic['cdn_url'], $serverStatic['cdn_user'],
				$serverStatic['cdn_password'], $serverStatic['external_url'],
				$serverStatic['container'], $serverStatic['token'], $serverStatic['dt_token'],
				$serverStatic['storage_url']
			);
		}

		return $this->_fileSystem;
	}

	public function getFileSystem() {
		return $this->_fileSystem;
	}

	function save($sContent, $sPath) {
		return $this->getFileSystem()->save($sContent, $sPath);
	}

	function delete($sUrl) {
		$fileUrl = parse_url($sUrl, PHP_URL_PATH);
		// Если передана пустая строка то будет произведено удаление всех файлов на сервере (WebDAV)
		// поэтому выполняем запрос только если передан урл
		if($fileUrl) {
			$this->getFileSystem()->delete($fileUrl);
		}
	}

}
