<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('POST'));
	$user = auth_require_login();

	$payload = request_payload();
	$currentPassword = isset($payload['currentPassword']) ? (string)$payload['currentPassword'] : '';
	$newPassword = isset($payload['newPassword']) ? (string)$payload['newPassword'] : '';

	if ($currentPassword === '' || $newPassword === '') {
		throw new InvalidArgumentException('currentPassword and newPassword are required');
	}

	$username = isset($user['username']) ? (string)$user['username'] : '';
	$userId = isset($user['id']) ? (int)$user['id'] : 0;
	if ($username === '' && $userId > 0) {
		$current = $library->getUser($userId);
		if ($current && !empty($current['username'])) {
			$username = $current['username'];
		}
	}
	if ($username === '') {
		throw new RuntimeException('Unable to resolve username for the current session');
	}

	try {
		$verifiedUser = $library->authenticateUser($username, $currentPassword);
	} catch (RuntimeException $ex) {
		throw new InvalidArgumentException('Current password is incorrect');
	}

	$targetUserId = isset($verifiedUser['id']) ? (int)$verifiedUser['id'] : 0;
	if ($targetUserId <= 0 && $userId > 0) {
		$targetUserId = $userId;
	}
	if ($targetUserId <= 0) {
		throw new RuntimeException('Unable to resolve user id for password update');
	}

	$library->setUserPassword($targetUserId, $newPassword);
	$updatedRecord = $library->getUser($targetUserId);
	$sessionPayload = $updatedRecord ? auth_store_user_session($updatedRecord) : null;

	json_success(array(
		'updated' => true,
		'user' => $sessionPayload
	));
});

?>
