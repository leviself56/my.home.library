<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('GET'));
	auth_require_role('librarian');

	$books = $library->listCheckedOut();
	json_success(array(
		'count' => count($books),
		'books' => $books
	));
});

?>
