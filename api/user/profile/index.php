<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('POST'));
	$user = auth_require_login();

	$payload = request_payload();
	$name = isset($payload['name']) ? trim((string)$payload['name']) : '';

	if ($name === '') {
		throw new InvalidArgumentException('name is required');
	}

	$userId = isset($user['id']) ? (int)$user['id'] : 0;
	$username = isset($user['username']) ? (string)$user['username'] : '';
	if ($userId <= 0 && $username !== '') {
		$record = $library->getUserByUsername($username, false);
		$userId = $record && isset($record['id']) ? (int)$record['id'] : 0;
	}
	if ($userId <= 0) {
		throw new RuntimeException('Unable to resolve user id for profile update');
	}

	$updated = $library->updateUserName($userId, $name);
	$sessionPayload = $updated ? auth_store_user_session($updated) : null;

	json_success(array(
		'updated' => true,
		'user' => $sessionPayload
	));
});

?>
