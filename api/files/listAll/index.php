<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('GET'));
	auth_require_role('librarian');

	$filters = array();
	if (isset($_GET['filename']) && trim($_GET['filename']) !== '') {
		$filters['filename'] = trim($_GET['filename']);
	}

	$files = $library->listFiles($filters);
	json_success(array(
		'filters' => $filters,
		'count' => count($files),
		'files' => $files
	));
});

?>
