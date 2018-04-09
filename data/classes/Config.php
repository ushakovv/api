<?php

class Config
{
	private static $_config = null;

	protected static function _init() {
		self::$_config = mergeArray(
			require(CONFPATH.'/config.php'),
			require(CONFPATH.'/config.local.php')
		);
	}

	/**
	 * @param string|null $section
	 * @param string|null $param
	 * @return array
	 */
	static function get($section = null, $param = null) {
		if(!self::$_config) self::_init();
		return isset($section)
			? (isset($param) ? self::$_config[$section][$param] : self::$_config[$section])
			: self::$_config;
	}
}
