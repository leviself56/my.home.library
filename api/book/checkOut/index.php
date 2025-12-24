<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('POST'));
	auth_require_role('librarian');

	$payload = request_payload();
	$bookId = extract_book_id($payload);

	$result = $library->checkOutBook($bookId, $payload);
	json_success($result);
});

function extract_book_id($payload) {
	if (isset($payload['bookId'])) {
		$bookId = (int)$payload['bookId'];
	} elseif (isset($payload['id'])) {
		$bookId = (int)$payload['id'];
	} elseif (isset($_GET['bookId'])) {
		$bookId = (int)$_GET['bookId'];
	} elseif (isset($_GET['id'])) {
		$bookId = (int)$_GET['id'];
	} else {
		$bookId = 0;
	}

	if ($bookId <= 0) {
		throw new InvalidArgumentException('bookId is required');
	}

	return $bookId;
}

?>
