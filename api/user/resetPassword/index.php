<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

handle_endpoint(function () use ($library) {
	assert_request_method(array('POST'));
	auth_require_role('librarian');

	$payload = request_payload();
	$userId = isset($payload['userId']) ? (int)$payload['userId'] : 0;
	if ($userId <= 0) {
		throw new InvalidArgumentException('userId is required');
	}

	$user = $library->getUser($userId);
	if (!$user) {
		throw new RuntimeException('User not found');
	}

	$newPassword = generate_random_password(8);
	$library->setUserPassword($userId, $newPassword);
	$updatedUser = $library->getUser($userId);

	json_success(array(
		'updated' => true,
		'user' => $updatedUser,
		'password' => $newPassword
	));
});

function generate_random_password($length = 8) {
	$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
	$maxIndex = strlen($alphabet) - 1;
	$result = '';
	while (strlen($result) < $length) {
		$index = random_int(0, $maxIndex);
		$result .= $alphabet[$index];
	}
	return $result;
}

?>
