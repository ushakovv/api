<?php

class CdnFileSystem
{
	private $_serverStaticId = null;
	private $_serverUrl = null;
	private $_host = null;
	private $_user = null;
	private $_password = null;
	private $_container = null;
	private $_token = null;
	private $_dtToken = null;
	private $_storageUrl = null;

	public function __construct($server_static_id, $host, $user, $password, $ext_url, $container, $token, $dt_token, $storage_url) {
		$this->_serverStaticId = $server_static_id;
		$this->_host = $host;
		$this->_user = $user;
		$this->_password = $password;
		$this->_serverUrl = $ext_url;
		$this->_container = $container;
		$this->_token = $token;
		$this->_dtToken = $dt_token;
		$this->_storageUrl = $storage_url;
	}

	private function auth() {
		$myCurl = curl_init();
		curl_setopt_array($myCurl, [
			CURLOPT_URL => $this->_host,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,
			CURLOPT_HTTPHEADER => [
				'X-Auth-User:'.trim($this->_user),
				'X-Auth-Key:'.trim($this->_password),
			],
			CURLOPT_SSL_VERIFYPEER => false,
		]);
		$sResponse = curl_exec($myCurl);
		curl_close($myCurl);

		$aLines = explode("\n", $sResponse);
		$aHeaders = [];
		foreach($aLines as $line) {
			$tmp = explode(': ', $line);
			$aHeaders[$tmp[0]] = isset($tmp[1]) ? $tmp[1] : null;
		}

		$this->_token = isset($aHeaders['X-Storage-Token']) ? trim($aHeaders['X-Storage-Token']) : null;
		$this->_dtToken = isset($aHeaders['X-Expire-Auth-Token']) ? date('Y-m-d H:i:s', time() + (isset($aHeaders['X-Expire-Auth-Token']) ? intval($aHeaders['X-Expire-Auth-Token']) : 0)) : null;
		$this->_storageUrl = isset($aHeaders['X-Storage-Url']) ? trim($aHeaders['X-Storage-Url']) : null;

		Connect::db()->update(
			'server_static',
			[
				'token' => $this->_token,
				'dt_token' => $this->_dtToken,
				'storage_url' => $this->_storageUrl,
			],
			['id' => $this->_serverStaticId]
		);
	}

	public function save($sContent, $sPath) {
		// авторизуемся если токен устарел
		if(strtotime($this->_dtToken) - time() <= 0) {
			$this->auth();
		}

		$finfo = new finfo(FILEINFO_MIME);
		$contentType = $finfo->buffer($sContent);

		$aHttpHeader = ['X-Auth-Token:'.trim($this->_token)];
		$isImage = isImageMimeType($contentType);
		if($isImage) {
			$aHttpHeader[] = 'Content-Type:'.$isImage; // $isImage == file mime type
		}

		$countTry = 0;
		while($countTry <= 1) {
			$myCurl = curl_init();
			curl_setopt_array($myCurl, [
				CURLOPT_URL => trim($this->_storageUrl).trim($this->_container).$sPath, // TRIM
				CURLOPT_CUSTOMREQUEST => 'PUT',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER => true,
				CURLOPT_HTTPHEADER => $aHttpHeader,
				CURLOPT_POSTFIELDS => $sContent,
				CURLOPT_SSL_VERIFYPEER => false,
			]);
			$sResponse = curl_exec($myCurl);
			curl_close($myCurl);

			if(strpos($sResponse, 'HTTP/1.1 401 Unauthorized') !== false) {
				$countTry++;
				$this->auth();
			} else {
				if(strpos($sResponse, 'HTTP/1.1 201 Created') !== false) {
					return $this->_serverUrl.$sPath;
				} else {
					return false;
				}
			}
		}
	}

	public function delete($sPath) {
		// авторизуемся если токен устарел
		if(strtotime($this->_dtToken) - time() <= 0) {
			$this->auth();
		}

		$sPath = str_replace($this->_serverUrl, '', $sPath); // if $sPath == $sUrl

		$countTry = 0;
		while($countTry <= 1) {
			$myCurl = curl_init();
			curl_setopt_array($myCurl, [
				CURLOPT_URL => trim($this->_storageUrl).trim($this->_container).$sPath,
				CURLOPT_CUSTOMREQUEST => 'DELETE',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER => true,
				CURLOPT_HTTPHEADER => [
					'X-Auth-Token:'.trim($this->_token),
				],
				CURLOPT_SSL_VERIFYPEER => false,
			]);
			$sResponse = curl_exec($myCurl);
			curl_close($myCurl);

			if(strpos($sResponse, 'HTTP/1.1 401 Unauthorized') !== false) {
				$countTry++;
				$this->auth();
			} else {
				if(strpos($sResponse, 'HTTP/1.1 204 No Content') !== false) {
					// -------
					// selcdn.ru -> selcdn.com
					$storageUrl = str_replace('selcdn.ru', 'selcdn.com', $this->_storageUrl);
					// -------
					$this->clearCache($storageUrl.$this->_container.$sPath);
					return true;
				} elseif(strpos($sResponse, 'HTTP/1.1 404 Not Found') !== false) {
					return true;
				} else {
					return false;
				}
			}
		}

		return false;
	}

	private function clearCache($sStorageUrl) {
		// авторизуемся если токен устарел
		if(strtotime($this->_dtToken) - time() <= 0) {
			$this->auth();
		}

		$countTry = 0;
		while($countTry <= 1) {
			$myCurl = curl_init();
			curl_setopt_array($myCurl, [
				CURLOPT_URL => trim($this->_storageUrl),
				CURLOPT_CUSTOMREQUEST => 'PURGE',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER => true,
				CURLOPT_HTTPHEADER => [
					'X-Auth-Token:'.trim($this->_token),
					'Content-Length:'.strlen($sStorageUrl),
				],
				CURLOPT_POSTFIELDS => $sStorageUrl,
				CURLOPT_SSL_VERIFYPEER => false,
			]);
			$sResponse = curl_exec($myCurl);
			curl_close($myCurl);

			if(strpos($sResponse, 'HTTP/1.1 401 Unauthorized') !== false) {
				$countTry++;
				$this->auth();
			} elseif(strpos($sResponse, 'HTTP/1.1 201 Created') !== false) {
				return true;
			} else {
				return false;
			}
		}
	}
}