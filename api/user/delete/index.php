<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('POST'));
	$sessionUser = auth_require_role('librarian');

	$payload = request_payload();
	$userId = isset($payload['userId']) ? (int)$payload['userId'] : 0;
	if ($userId <= 0) {
		json_error('userId is required', 422);
	}

	if ($sessionUser && isset($sessionUser['id']) && (int)$sessionUser['id'] === $userId) {
		json_error('You cannot delete your own account.', 400);
	}

	$library->deleteUser($userId);

	json_success(array('deleted' => true));
});

?>
