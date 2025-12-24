<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('GET'));
	auth_require_role('librarian');

	$filters = array();
	if (isset($_GET['type']) && trim($_GET['type']) !== '') {
		$filters['type'] = $_GET['type'];
	}
	if (isset($_GET['username']) && trim($_GET['username']) !== '') {
		$filters['username'] = $_GET['username'];
	}

	$users = $library->listUsers($filters);
	json_success(array(
		'filters' => $filters,
		'count' => count($users),
		'users' => $users
	));
});

?>
