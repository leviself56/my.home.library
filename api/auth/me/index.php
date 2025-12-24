<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

auth_bootstrap_session();

handle_endpoint(function () {
	assert_request_method(array('GET'));

	$user = auth_require_login();

	json_success(array('user' => $user));
});

?>
