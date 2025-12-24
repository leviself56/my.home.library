<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

auth_bootstrap_session();

handle_endpoint(function () {
	assert_request_method(array('POST'));

	auth_require_login();
	auth_logout();

	json_success(array('user' => null));
});

?>
