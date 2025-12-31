<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('GET'));

	$requestedKeys = array();
	if (isset($_GET['keys']) && trim($_GET['keys']) !== '') {
		$parts = preg_split('/[\s,]+/', $_GET['keys']);
		foreach ($parts as $part) {
			$part = trim($part);
			if ($part !== '') {
				$requestedKeys[] = $part;
			}
		}
	}

	$settings = empty($requestedKeys) ? $library->getSettings() : $library->getSettings($requestedKeys);

	json_success(array(
		'count' => count($settings),
		'settings' => $settings
	));
});

?>
