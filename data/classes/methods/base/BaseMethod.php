<?php

class BaseMethod extends APIServer
{
	protected function checkTemplateAccess($id) {
		$template = Connect::dbChild($this->User['server_data_ip'], $this->User['schema_data_id'])->queryOne(
			'SELECT "content_html" FROM "template" WHERE "user_id"=:user_id AND "id"=:id AND "is_deleted"=FALSE;',
			[
				'id' => $id,
				'user_id' => $this->User['id'],
			]
		);
		if($template) {
			return true;
		} else {
			$this->addError('template_not_found');
		}

		return false;
	}

	protected function getTemplateFolderPath($templateId) {
		return '/data_'.$this->User['schema_data_id'].'/user_'.$this->User['id'].'/template_'.$templateId;
	}

	protected function getTemplatePreviewHsh($userId, $templateId) {
		return md5($userId.$templateId.$this->conf['security']['template_hash_salt']);
	}

	// json structure to html
	protected function generateContentHtml($structure) {
		$html = '';
		$myCurl = curl_init();
		curl_setopt_array($myCurl, [
			CURLOPT_URL => $this->conf['mjml']['json2html_convert_url'],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => http_build_query(['payload' => $structure]),
		]);
		$response = curl_exec($myCurl);
		$jsonArray = json_decode($response, true);
		curl_close($myCurl);
		if(is_array($jsonArray) && isset($jsonArray['errors']) && !$jsonArray['errors']) {
			$html = $jsonArray['html'];
		} else {
			$this->addWarning('generate_html_problem');
		}

		return $html;
	}
}