<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

auth_bootstrap_session();

handle_endpoint(function () use ($library) {
	assert_request_method(array('POST'));

	$payload = request_payload();
	$username = isset($payload['username']) ? $payload['username'] : null;
	$password = isset($payload['password']) ? $payload['password'] : null;

	$user = $library->authenticateUser($username, $password);
	$sessionUser = auth_store_user_session($user);

	json_success(array('user' => $sessionUser));
});

?>
