<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('GET'));
	$user = auth_require_login();
	$type = isset($user['type']) ? strtolower((string)$user['type']) : 'user';
	$isLibrarian = ($type === 'librarian');

	$bookId = isset($_GET['bookId']) ? (int)$_GET['bookId'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
	if ($bookId <= 0) {
		throw new InvalidArgumentException('A valid bookId query parameter is required');
	}

	$options = $isLibrarian ? array() : array('only_date_created' => true);
	$data = $library->listFilesForBook($bookId, $options);
	if (!$isLibrarian) {
		$data['scope'] = 'dateCreated';
	}
	json_success($data);
});

?>
