<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('POST'));
	auth_require_role('librarian');

	$payload = request_payload();
	$bookId = isset($payload['bookId']) ? (int)$payload['bookId'] : 0;
	if ($bookId <= 0) {
		throw new InvalidArgumentException('bookId is required');
	}

	$library->deleteBook($bookId);

	json_success(array('deleted' => true));
});

?>
