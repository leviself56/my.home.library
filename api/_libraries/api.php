<?php

bootstrap_cors();

function bootstrap_cors() {
	static $initialized = false;
	if ($initialized || headers_sent()) {
		return;
	}
	$initialized = true;

	$origin = isset($_SERVER['HTTP_ORIGIN']) ? trim($_SERVER['HTTP_ORIGIN']) : '';
	$allowedOrigins = array(
		'http://home.library',
		'https://home.library',
		'http://local.api',
		'https://local.api',
		'http://localhost',
		'http://127.0.0.1',
		'http://localhost:8000'
	);
	$defaultOrigin = $allowedOrigins[0];
	$allowOrigin = $defaultOrigin;
	if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
		$allowOrigin = $origin;
	}

	header('Access-Control-Allow-Origin: ' . $allowOrigin);
	header('Vary: Origin');
	header('Access-Control-Allow-Credentials: true');
	$allowMethods = 'GET, POST, OPTIONS';
	header('Access-Control-Allow-Methods: ' . $allowMethods);

	$allowHeaders = 'Content-Type, Accept, X-Requested-With';
	if (!empty($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
		$allowHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'];
	}
	header('Access-Control-Allow-Headers: ' . $allowHeaders);

	if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'OPTIONS') {
		http_response_code(204);
		exit;
	}
}

function read_json_input() {
	static $cached = null;
	if ($cached !== null) {
		return $cached;
	}

	$raw = file_get_contents('php://input');
	if ($raw === false || $raw === '') {
		$cached = array();
		return $cached;
	}

	$data = json_decode($raw, true);
	if (json_last_error() !== JSON_ERROR_NONE) {
		throw new InvalidArgumentException('Invalid JSON payload: ' . json_last_error_msg());
	}

	$cached = is_array($data) ? $data : array();
	return $cached;
}

function json_response(array $payload, $status = 200) {
	http_response_code($status);
	if (!headers_sent()) {
		header('Content-Type: application/json');
		header('Cache-Control: no-store');
	}
	echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	exit;
}

function json_success($data = array(), $status = 200) {
	json_response(array(
		'success' => true,
		'data' => $data
	), $status);
}

function json_error($message, $status = 400, array $details = array()) {
	$payload = array(
		'success' => false,
		'error' => $message
	);

	if (!empty($details)) {
		$payload['details'] = $details;
	}

	json_response($payload, $status);
}

function assert_request_method(array $allowed) {
	$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
	$allowed = array_map('strtoupper', $allowed);
	if (!in_array($method, $allowed, true)) {
		header('Allow: ' . implode(', ', $allowed));
		json_error('Method not allowed', 405);
	}
}

function request_payload() {
	$payload = read_json_input();
	if (empty($payload) && !empty($_POST)) {
		$payload = $_POST;
	}
	return $payload;
}

function handle_endpoint(callable $callback) {
	try {
		$callback();
	} catch (InvalidArgumentException $ex) {
		json_error($ex->getMessage(), 422);
	} catch (RuntimeException $ex) {
		json_error($ex->getMessage(), 409);
	} catch (Exception $ex) {
		json_error('Internal server error', 500, array('message' => $ex->getMessage()));
	}
}

?>
