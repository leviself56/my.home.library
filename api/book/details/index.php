<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('GET'));
	$publicLibrary = (bool)$library->getSetting('publicLibrary', false);
	if ($publicLibrary) {
		auth_bootstrap_session();
	} else {
		auth_require_login();
	}

	$bookId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
	if ($bookId <= 0) {
		throw new InvalidArgumentException('A valid id query parameter is required');
	}

	$book = $library->getBook($bookId);
	if (!$book) {
		json_error('Book not found', 404);
	}

	$book = auth_redact_book_payload($book);
	json_success($book);
});

?>
