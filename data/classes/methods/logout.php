<?php

class logout extends APIServer
{
	protected $type = 'cookie';

	public function delete($h) {
		if(isset($h['is_all'])) {
			// Разлогиниваем на всех устройствах
			Connect::db()->query(
				'DELETE FROM "user_session" WHERE "user_id"=:user_id;',
				['user_id' => $this->User['id']]
			);
		} else {
			// разлогиниваем только на данном устройстве, где хранится данный токен
			Connect::db()->query(
				'DELETE FROM "user_session" WHERE token=:token;',
				['token' => $this->User['token']]
			);
		}

		setcookie('token', '', time() - 3600, '/', '.'.$_SERVER['HTTP_HOST']);

		return [
			'user' => [
				'id' => $this->User['id'],
				'email' => $this->User['email'],
				'logout' => 'yes'
			]
		];
	}
}
