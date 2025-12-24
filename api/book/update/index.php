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

	$book = $library->updateBook($bookId, $payload);
	$book = auth_redact_book_payload($book);

	json_success($book);
});

?>
