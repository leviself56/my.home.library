<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('GET'));
	auth_require_login();

	$sessionUser = auth_current_user();
	if (!$sessionUser || empty($sessionUser['id'])) {
		json_error('Unable to determine current user', 401);
	}

	$history = $library->getUserHistory((int)$sessionUser['id']);
	$events = array();
	foreach ($history['events'] as $event) {
		$events[] = array(
			'id' => isset($event['id']) ? (int)$event['id'] : null,
			'book' => array(
				'id' => isset($event['book']['id']) ? (int)$event['book']['id'] : null,
				'title' => isset($event['book']['title']) ? $event['book']['title'] : null,
				'author' => isset($event['book']['author']) ? $event['book']['author'] : null
			),
			'created_datetime' => isset($event['created_datetime']) ? $event['created_datetime'] : null,
			'dueDate' => isset($event['dueDate']) ? $event['dueDate'] : null,
			'outComment' => isset($event['outComment']) ? $event['outComment'] : null,
			'receivedDateTime' => isset($event['receivedDateTime']) ? $event['receivedDateTime'] : null,
			'status' => isset($event['status']) ? $event['status'] : null
		);
	}

	json_success(array(
		'events' => $events
	));
});

?>
