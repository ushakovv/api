<?php

// TODO: в последствие переделать класс на работу через MailDarts
class MailService
{
	public static function sendMail($email, $subject, $contentHtml, $props = []) {
		$conf = Config::get();

		if($props) {
			$search = [];
			$replace = [];
			foreach($props as $s => $r) {
				$search[] = $conf['mail']['property_limiter_left'].$s.$conf['mail']['property_limiter_right'];
				$replace[] = $r;
			}
			$subject = str_replace($search, $replace, $subject);
			$contentHtml = str_replace($search, $replace, $contentHtml);
		}

		SendMail::from('info@mailmaker.loc', 'MailMaker')
			->to($email)
			->subject($subject)
			->message($contentHtml)
			->important(true) // Приоритет письма. True, если важное. По умолчанию false. (Письмо будет продублировано в папку "важные" на клиенте адресата. Сайт же mail.ru пометит его красным восклицательным знаком.)
			->send();
	}
}
