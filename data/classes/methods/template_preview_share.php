<?php

class template_preview_share extends BaseMethod
{
	protected $type = '';

	public function get($h) {
		$userId = getIntParam($h, 'user_id');
		$id = getIntParam($h, 'id');
		$hsh = getStringParam($h, 'hsh');
		if($userId && $id && $hsh) {
			if($hsh == $this->getTemplatePreviewHsh($userId, $id)) {
				$user = Connect::db()->queryOne(
					'SELECT "user"."schema_data_id", "server_data"."ip" as "server_data_ip"'
					.' FROM "user"'
					.' INNER JOIN "server_data" ON "server_data"."id"="user"."server_data_id"'
					.' WHERE "user"."id"=:user_id;',
					['user_id' => $userId]
				);
				if($user) {
					$template = Connect::dbChild($user['server_data_ip'], $user['schema_data_id'])->queryOne(
						'SELECT "content_html" FROM "template" WHERE "user_id"=:user_id AND "id"=:id AND "is_deleted"=FALSE;',
						[
							'id' => $id,
							'user_id' => $userId,
						]
					);
					if($template) {
						echo $template['content_html'];
						exit;
					} // шаблон не найден
				} // пользователь не найден
			} // не верный хэш
		} // не переданы обязательные параметры

		// если дошли сюда, то получаем просто пустую страницу (какая именно ошибка произошла пользователю не показываем)
		exit;
	}
}
