<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('GET'));
	$publicLibrary = (bool)$library->getSetting('publicLibrary', false);
	if ($publicLibrary) {
		auth_bootstrap_session();
		$user = auth_current_user();
	} else {
		$user = auth_require_login();
	}
	$type = 'user';
	if (is_array($user) && isset($user['type'])) {
		$type = strtolower((string)$user['type']);
	}
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
