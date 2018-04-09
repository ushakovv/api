<?php

class template_preview extends BaseMethod
{
	protected $type = 'cookie';

	public function post($h) {
		$structure = getStringParam($h, 'structure');
		if($structure) {
			return ['template' => ['content_html' => $this->generateContentHtml($structure)]];
		} else {
			$this->addError('required_params_missed');
		}
	}
}
