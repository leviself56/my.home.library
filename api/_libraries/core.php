<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';
require_once __DIR__ . '/class.db.php';
require_once __DIR__ . '/class.simple_api.php';
require_once __DIR__ . '/class.homeLib.php';
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/auth.php';

auth_bootstrap_session();

?>