<?php

function auth_bootstrap_session() {
  static $bootstrapped = false;
  if ($bootstrapped) {
    return;
  }
  $bootstrapped = true;

  auth_prepare_session_storage();

  $status = session_status();
  if ($status === PHP_SESSION_DISABLED) {
    throw new RuntimeException('Sessions are disabled');
  }
  if ($status === PHP_SESSION_ACTIVE) {
    return;
  }

  $sessionLifetime = 60 * 60 * 24 * 30; // roughly 30 days
  $sameSite = 'Lax';
  if (auth_is_https_request()) {
    $sameSite = homelib_config(array('session', 'sameSite'), 'Lax');
  }
  $cookieDomain = homelib_config(array('session', 'domain'), '');
  $cookiePath = homelib_config(array('session', 'path'), '/');
  if ($cookiePath === '' || strpos($cookiePath, '/') !== 0) {
    $cookiePath = '/';
  }
  $cookieSecure = auth_is_https_request();
  if (strcasecmp($sameSite, 'None') === 0) {
    $cookieSecure = true;
    $sameSite = 'None';
  }
  $options = array(
    'name' => 'homelib_session',
    'cookie_lifetime' => $sessionLifetime,
    'cookie_path' => $cookiePath,
    'cookie_domain' => $cookieDomain,
    'cookie_secure' => $cookieSecure,
    'cookie_httponly' => true,
    'use_strict_mode' => true,
    'cookie_samesite' => $sameSite
  );

  ini_set('session.gc_maxlifetime', $sessionLifetime);
  ini_set('session.cookie_lifetime', $sessionLifetime);

  if (version_compare(PHP_VERSION, '7.3.0', '>=') && function_exists('session_start')) {
    session_start($options);
    auth_refresh_session_cookie($options, $sessionLifetime);
    return;
  }

  session_name($options['name']);
  $path = $options['cookie_path'];
  if (stripos($path, 'samesite=') === false) {
    $path .= '; samesite=' . $sameSite;
  }
  session_set_cookie_params(
    $sessionLifetime,
    $path,
    $options['cookie_domain'],
    $options['cookie_secure'],
    $options['cookie_httponly']
  );
  session_start();
  auth_refresh_session_cookie($options, $sessionLifetime);
}

function auth_is_https_request() {
  if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
    return true;
  }
  if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
    return true;
  }
  return false;
}

function auth_store_user_session(array $user) {
  auth_bootstrap_session();
  $filtered = auth_filter_user_payload($user);
  $_SESSION['auth_user'] = $filtered;
  return $filtered;
}

function auth_current_user() {
  auth_bootstrap_session();
  return isset($_SESSION['auth_user']) ? $_SESSION['auth_user'] : null;
}

function auth_is_authenticated() {
  return auth_current_user() !== null;
}

function auth_filter_user_payload($user) {
  if (!$user) {
    return null;
  }
  $allowed = array('id', 'username', 'name', 'type', 'created_datetime');
  $filtered = array();
  foreach ($allowed as $field) {
    if (isset($user[$field])) {
      $filtered[$field] = $user[$field];
    }
  }
  return $filtered;
}

function auth_current_user_type() {
  $user = auth_current_user();
  if (!$user || !isset($user['type'])) {
    return null;
  }
  return strtolower((string)$user['type']);
}

function auth_is_librarian() {
  return auth_current_user_type() === 'librarian';
}

function auth_is_reader() {
  return auth_current_user_type() === 'user';
}

function auth_require_login() {
  $user = auth_current_user();
  if (!$user) {
    json_error('Authentication required', 401);
  }
  return $user;
}

function auth_require_role($roles) {
  $user = auth_require_login();
  $roles = is_array($roles) ? $roles : array($roles);
  $roles = array_map('strtolower', $roles);
  $type = isset($user['type']) ? strtolower($user['type']) : '';
  if (!in_array($type, $roles, true)) {
    json_error('Forbidden', 403);
  }
  return $user;
}

function auth_redact_book_payload(array $book) {
  if (auth_is_librarian()) {
    return $book;
  }
  unset($book['borrowedBy'], $book['borrowedByUserId'], $book['returnBy']);
  if (isset($book['dateCreated_file_ids'])) {
    unset($book['dateCreated_file_ids']);
  }
  return $book;
}

function auth_redact_book_collection(array $books) {
  if (auth_is_librarian()) {
    return $books;
  }
  foreach ($books as &$book) {
    $book = auth_redact_book_payload($book);
  }
  unset($book);
  return $books;
}

function auth_logout() {
  auth_bootstrap_session();
  $_SESSION = array();
  if (session_status() === PHP_SESSION_ACTIVE) {
    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
  }
}

function auth_prepare_session_storage() {
  static $prepared = false;
  if ($prepared) {
    return;
  }
  $prepared = true;
  $override = homelib_config(array('session', 'storagePath'));
  $default = HOMELIB_ROOT . '/storage/sessions';
  $path = $override ? $override : $default;
  $normalized = rtrim($path, DIRECTORY_SEPARATOR);
  if ($normalized === '') {
    throw new RuntimeException('Invalid session storage path.');
  }
  if (!is_dir($normalized)) {
    if (!@mkdir($normalized, 0775, true)) {
      throw new RuntimeException('Unable to create session storage directory: ' . $normalized);
    }
  }
  if (!is_writable($normalized)) {
    throw new RuntimeException('Session storage directory is not writable: ' . $normalized);
  }
  $htaccess = $normalized . DIRECTORY_SEPARATOR . '.htaccess';
  if (!file_exists($htaccess)) {
    @file_put_contents($htaccess, "Deny from all\n");
  }
  ini_set('session.save_handler', 'files');
  ini_set('session.save_path', $normalized);
  session_save_path($normalized);
}

function auth_refresh_session_cookie(array $options, $lifetime) {
  if (headers_sent()) {
    return;
  }
  if (!function_exists('session_id')) {
    return;
  }
  $id = session_id();
  if (!$id) {
    return;
  }
  $expires = time() + (int)$lifetime;
  $sameSite = isset($options['cookie_samesite']) ? $options['cookie_samesite'] : 'Lax';
  $cookieData = array(
    'expires' => $expires,
    'path' => $options['cookie_path'],
    'domain' => $options['cookie_domain'],
    'secure' => !empty($options['cookie_secure']),
    'httponly' => !empty($options['cookie_httponly']),
    'samesite' => $sameSite
  );
  if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
    setcookie(session_name(), $id, $cookieData);
    return;
  }
  $path = $cookieData['path'];
  if (stripos($path, 'samesite=') === false) {
    $path .= '; samesite=' . $sameSite;
  }
  setcookie(session_name(), $id, $expires, $path, $cookieData['domain'], $cookieData['secure'], $cookieData['httponly']);
}

?>
