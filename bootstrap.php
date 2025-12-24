<?php
if (!defined('HOMELIB_ROOT')) {
	define('HOMELIB_ROOT', __DIR__);
}
if (!defined('HOMELIB_CONFIG_FILE')) {
	define('HOMELIB_CONFIG_FILE', HOMELIB_ROOT . '/config.php');
}

if (!isset($GLOBALS['homelibConfig'])) {
	$loadedConfig = array();
	if (file_exists(HOMELIB_CONFIG_FILE)) {
		$configData = include HOMELIB_CONFIG_FILE;
		if (is_array($configData)) {
			$loadedConfig = $configData;
		}
	}
	$GLOBALS['homelibConfig'] = $loadedConfig;
}

if (!function_exists('homelib_config')) {
	function homelib_config($path = null, $default = null) {
		$config = isset($GLOBALS['homelibConfig']) && is_array($GLOBALS['homelibConfig']) ? $GLOBALS['homelibConfig'] : array();
		if ($path === null || $path === '') {
			return $config;
		}
		$segments = is_array($path) ? $path : explode('.', (string)$path);
		$value = $config;
		foreach ($segments as $segment) {
			if ($segment === '') {
				continue;
			}
			if (is_array($value) && array_key_exists($segment, $value)) {
				$value = $value[$segment];
			} else {
				return $default;
			}
		}
		return $value;
	}
}

if (!function_exists('homelib_has_db_credentials')) {
	function homelib_has_db_credentials() {
		$db = homelib_config('db');
		if (!is_array($db)) {
			return false;
		}
		$requiredKeys = array('host', 'user', 'name');
		foreach ($requiredKeys as $key) {
			if (!isset($db[$key]) || trim((string)$db[$key]) === '') {
				return false;
			}
		}
		return true;
	}
}

if (!function_exists('homelib_has_valid_db_config')) {
	function homelib_has_valid_db_config() {
		return homelib_has_db_credentials() && homelib_config('installed') === true;
	}
}

if (!defined('HOMELIB_CONFIG_READY')) {
	define('HOMELIB_CONFIG_READY', homelib_has_valid_db_config());
}

if (!function_exists('homelib_config_ready')) {
	function homelib_config_ready() {
		return (bool)HOMELIB_CONFIG_READY;
	}
}
?>
