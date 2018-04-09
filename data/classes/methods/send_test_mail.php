<?php

class send_test_mail extends APIServer
{
	protected $type = 'cookie';

	public function post($h) {
		$templateId = getIntParam($h, 'template_id');
		$email = getStringParam($h, 'email');
		if($templateId && $email) {
			$template = Connect::dbChild($this->User['server_data_ip'], $this->User['schema_data_id'])->queryOne(
				'SELECT "content_html" FROM "template" WHERE "user_id"=:user_id AND "id"=:id AND "is_deleted"=FALSE;',
				[
					'id' => $templateId,
					'user_id' => $this->User['id'],
				]
			);
			if($template) {
				MailService::sendMail(
					$email,
					'Тестовая отправка с сайта {{SITE_NAME}}',
					$template['content_html'],
					['SITE_NAME' => $this->conf['server']['site_name']]
				);
				return ['success'];
			} else {
				$this->addError('template_not_found');
			}
		} else {
			$this->addError('required_params_missed');
		}
	}
}