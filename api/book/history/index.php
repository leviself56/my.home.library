<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('GET'));
	auth_require_role('librarian');

	$bookId = isset($_GET['bookId']) ? (int)$_GET['bookId'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
	if ($bookId <= 0) {
		throw new InvalidArgumentException('A valid bookId query parameter is required');
	}

	$history = $library->getBookHistory($bookId);
	json_success($history);
});

?>
