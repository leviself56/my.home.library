<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('GET'));
	auth_require_login();

	$filters = array();
	if (isset($_GET['status']) && trim($_GET['status']) !== '') {
		$parsed = normalize_in_library($_GET['status']);
		if ($parsed !== null) {
			$filters['inLibrary'] = $parsed;
		}
	}
	if (isset($_GET['inLibrary']) && trim($_GET['inLibrary']) !== '') {
		$parsed = normalize_in_library($_GET['inLibrary']);
		if ($parsed !== null) {
			$filters['inLibrary'] = $parsed;
		}
	}
	if (isset($_GET['borrowedBy']) && trim($_GET['borrowedBy']) !== '') {
		$filters['borrowedBy'] = trim($_GET['borrowedBy']);
	}

	$books = $library->listBooks($filters);
	$books = auth_redact_book_collection($books);

	json_success(array(
		'filters' => $filters,
		'count' => count($books),
		'books' => $books
	));
});

function normalize_in_library($value) {
	$value = strtolower(trim((string)$value));
	if ($value === '' || $value === 'all') {
		return null;
	}
	if (in_array($value, array('1', 'true', 'yes', 'in', 'available'), true)) {
		return 1;
	}
	if (in_array($value, array('0', 'false', 'no', 'out', 'checkedout', 'unavailable'), true)) {
		return 0;
	}
	return ((int)$value) === 1 ? 1 : 0;
}

?>
