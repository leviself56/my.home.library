<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('GET'));
	auth_require_login();

	$query = '';
	if (isset($_GET['q'])) {
		$query = (string)$_GET['q'];
	} elseif (isset($_GET['term'])) {
		$query = (string)$_GET['term'];
	}

	$query = trim($query);
	$results = ($query === '') ? array() : $library->searchBooks($query);

	json_success(array(
		'query' => $query,
		'count' => count($results),
		'books' => auth_redact_book_collection($results)
	));
});

?>
