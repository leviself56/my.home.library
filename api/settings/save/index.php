<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('POST'));
	auth_require_role('librarian');

	$payload = request_payload();
	$settingsPayload = array();

	if (isset($payload['settings']) && is_array($payload['settings'])) {
		$settingsPayload = $payload['settings'];
	}

	$allowedKeys = $library->getSettingKeys();
	foreach ($allowedKeys as $key) {
		if (array_key_exists($key, $payload)) {
			$settingsPayload[$key] = $payload[$key];
		}
	}

	if (empty($settingsPayload)) {
		throw new InvalidArgumentException('No settings provided');
	}

	$updated = $library->saveSettings($settingsPayload);

	json_success(array('settings' => $updated));
});

?>
