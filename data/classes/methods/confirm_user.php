<?php

class confirm_user extends APIServer
{
	protected $type = '';

	public function get($h) {
		$userId = getIntParam($h, 'user_id');
		$confirmCode = getStringParam($h, 'confirm_code');

		if($userId && $confirmCode) {
			$user = $this->getUser($userId);

			if($user) {
				if($user['confirm_code'] != $confirmCode || $user['confirm_code'] == '') {
					$this->addError('session_has_expired');
				} else {
					Connect::db()->update(
						'user',
						[
							'password' => $user['new_password'],
							'new_password' => null,
							'confirm_code' => null,
						],
						['id' => $userId]
					);

					return [
						'user' => [
							'id' => $userId,
							'name' => $user['name'],
							'email' => $user['email'],
						]
					];
				}
			} else {
				$this->addError('user_not_found');
			}
		} else {
			$this->addError('required_params_missed');
		}
	}
}
