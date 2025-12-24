<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('GET'));
	auth_require_role('librarian');

	$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
	if ($userId <= 0) {
		throw new InvalidArgumentException('A valid id query parameter is required');
	}

	$details = $library->getUserDetailsWithLoans($userId);
	if (!$details) {
		json_error('User not found', 404);
	}

	json_success($details);
});

?>
