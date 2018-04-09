<?php

class check_auth extends APIServer
{
	protected $type = 'cookie';

	public function get($h) {
		if($this->User) {
			return [
				'user' => [
					'id' => $this->User['id'],
					'name' => $this->User['name'],
					'email' => $this->User['email'],
				]
			];
		} else {
			$this->addError('session_not_found');
		}
	}
}
