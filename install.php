<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

if (homelib_config_ready()) {
	header('Location: index.php');
	exit;
}

$installerState = isset($_SESSION['homelib_installer']) && is_array($_SESSION['homelib_installer'])
	? $_SESSION['homelib_installer']
	: array();
$errors = array();
$notices = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = isset($_POST['action']) ? $_POST['action'] : '';
	if ($action === 'database') {
		$dbHost = trim((string)($_POST['dbHost'] ?? ''));
		$dbUser = trim((string)($_POST['dbUser'] ?? ''));
		$dbPass = (string)($_POST['dbPass'] ?? '');
		$dbName = trim((string)($_POST['dbName'] ?? ''));
		if ($dbHost === '') {
			$errors[] = 'Database host is required.';
		}
		if ($dbUser === '') {
			$errors[] = 'Database user is required.';
		}
		if ($dbName === '') {
			$errors[] = 'Database name is required.';
		} elseif (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
			$errors[] = 'Database name may only contain letters, numbers, and underscores.';
		}
		if (!$errors) {
			try {
				installer_setup_database($dbHost, $dbUser, $dbPass, $dbName);
				$installerState['db'] = array(
					'host' => $dbHost,
					'user' => $dbUser,
					'pass' => $dbPass,
					'name' => $dbName,
					'charset' => 'utf8mb4'
				);
				$installerState['db_ready'] = true;
				$_SESSION['homelib_installer'] = $installerState;
				header('Location: install.php?step=admin&seed=1');
				exit;
			} catch (Exception $ex) {
				$errors[] = $ex->getMessage();
			}
		}
	} elseif ($action === 'admin') {
		if (empty($installerState['db'])) {
			$errors[] = 'Please complete the database setup first.';
		} else {
			$siteTitle = trim((string)($_POST['siteTitle'] ?? ''));
			$siteSubtitle = trim((string)($_POST['siteSubtitle'] ?? ''));
			$adminName = trim((string)($_POST['adminName'] ?? ''));
			$adminUsername = strtolower(trim((string)($_POST['adminUsername'] ?? '')));
			$adminPassword = (string)($_POST['adminPassword'] ?? '');
			if ($siteTitle === '') {
				$errors[] = 'Site title is required.';
			}
			if ($adminName === '') {
				$errors[] = 'Librarian name is required.';
			}
			if ($adminUsername === '') {
				$errors[] = 'Librarian username is required.';
			} elseif (!preg_match('/^[a-z0-9._-]{3,64}$/', $adminUsername)) {
				$errors[] = 'Username must be 3-64 characters (a-z, 0-9, dot, dash, underscore).';
			}
			if (strlen($adminPassword) < 6) {
				$errors[] = 'Password must be at least 6 characters.';
			}
			if (!$errors) {
				try {
					installer_finalize($installerState['db'], array(
						'siteTitle' => $siteTitle,
						'siteSubtitle' => $siteSubtitle,
						'adminName' => $adminName,
						'adminUsername' => $adminUsername,
						'adminPassword' => $adminPassword
					));
					unset($_SESSION['homelib_installer']);
					header('Location: index.php');
					exit;
				} catch (Exception $ex) {
					$errors[] = $ex->getMessage();
				}
			}
		}
	}
}

$step = (!empty($installerState['db_ready'])) ? 'admin' : 'database';
if ($step === 'admin' && isset($_GET['seed'])) {
	$notices[] = 'Database created successfully. Finish by creating your librarian account and site details.';
}
$dbDefaults = array(
	'host' => '127.0.0.1',
	'user' => 'root',
	'name' => 'my_home_library'
);
$dbState = isset($installerState['db']) ? array_merge($dbDefaults, $installerState['db']) : $dbDefaults;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'database') {
	$dbState['host'] = trim((string)($_POST['dbHost'] ?? $dbState['host']));
	$dbState['user'] = trim((string)($_POST['dbUser'] ?? $dbState['user']));
	$dbState['name'] = trim((string)($_POST['dbName'] ?? $dbState['name']));
}

$siteTitleValue = $_POST['siteTitle'] ?? 'Home Library';
$siteSubtitleValue = $_POST['siteSubtitle'] ?? 'Carefully Curated';
$adminNameValue = $_POST['adminName'] ?? '';
$adminUsernameValue = $_POST['adminUsername'] ?? '';

function installer_setup_database($host, $user, $pass, $name) {
	$mysqli = installer_connect($host, $user, $pass);
	$collation = 'utf8mb4_unicode_ci';
	$charset = 'utf8mb4';
	$dbIdentifier = $name;
	$query = sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s', $dbIdentifier, $charset, $collation);
	if (!$mysqli->query($query)) {
		throw new RuntimeException('Unable to create database: ' . $mysqli->error);
	}
	if (!$mysqli->select_db($name)) {
		throw new RuntimeException('Unable to select database: ' . $mysqli->error);
	}
	installer_run_schema($mysqli, HOMELIB_ROOT . '/database/migrations/default-schema.sql');
	$mysqli->close();
}

function installer_finalize(array $dbConfig, array $payload) {
	installer_assert_config_destination_is_writable();
	installer_assert_session_storage_ready();
	$mysqli = installer_connect($dbConfig['host'], $dbConfig['user'], $dbConfig['pass'], $dbConfig['name']);
	$configWritten = false;
	$transactionStarted = false;
	try {
		if (!$mysqli->begin_transaction()) {
			throw new RuntimeException('Unable to start installer transaction: ' . $mysqli->error);
		}
		$transactionStarted = true;
		installer_seed_defaults($mysqli, $payload['siteTitle'], $payload['siteSubtitle']);
		installer_create_librarian($mysqli, $payload['adminName'], $payload['adminUsername'], $payload['adminPassword']);
		$config = array(
			'installed' => true,
			'db' => array(
				'host' => $dbConfig['host'],
				'user' => $dbConfig['user'],
				'pass' => $dbConfig['pass'],
				'name' => $dbConfig['name'],
				'charset' => isset($dbConfig['charset']) ? $dbConfig['charset'] : 'utf8mb4'
			)
		);
		installer_write_config_file($config);
		$configWritten = true;
		if (!$mysqli->commit()) {
			throw new RuntimeException('Unable to finalize installer transaction: ' . $mysqli->error);
		}
		$transactionStarted = false;
	} catch (Exception $ex) {
		if ($transactionStarted) {
			$mysqli->rollback();
		}
		if ($configWritten && file_exists(HOMELIB_CONFIG_FILE)) {
			@unlink(HOMELIB_CONFIG_FILE);
		}
		$mysqli->close();
		throw $ex;
	}
	$mysqli->close();
}

function installer_connect($host, $user, $pass, $dbName = '') {
	$mysqli = @new mysqli($host, $user, $pass, $dbName ?: '');
	if ($mysqli->connect_error) {
		throw new RuntimeException('Database connection failed: ' . $mysqli->connect_error);
	}
	$mysqli->set_charset('utf8mb4');
	return $mysqli;
}

function installer_run_schema(mysqli $mysqli, $path) {
	if (!file_exists($path)) {
		throw new RuntimeException('Schema file not found: ' . $path);
	}
	$sql = file_get_contents($path);
	if ($sql === false) {
		throw new RuntimeException('Unable to read schema file.');
	}
	if (!$mysqli->multi_query($sql)) {
		throw new RuntimeException('Failed to run schema: ' . $mysqli->error);
	}
	do {
		if ($result = $mysqli->store_result()) {
			$result->free();
		}
	} while ($mysqli->more_results() && $mysqli->next_result());
	if ($mysqli->errno) {
		throw new RuntimeException('Schema execution error: ' . $mysqli->error);
	}
}

function installer_seed_defaults(mysqli $mysqli, $siteTitle, $siteSubtitle) {
	$settings = array(
		'siteTitle' => $siteTitle,
		'siteSubtitle' => $siteSubtitle,
		'swName' => 'my.home.library',
		'swVersion' => '1.0',
		'swURL' => 'https://github.com/leviself56/my.home.library',
		'publicLibrary' => false
	);
	foreach ($settings as $key => $value) {
		installer_upsert_setting($mysqli, $key, $value);
	}
}

function installer_upsert_setting(mysqli $mysqli, $key, $value) {
	$stmt = $mysqli->prepare('INSERT INTO settings (setting_key, setting_value, updated_datetime) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_datetime = NOW()');
	if (!$stmt) {
		throw new RuntimeException('Failed to prepare settings statement: ' . $mysqli->error);
	}
	$stmt->bind_param('ss', $key, $value);
	if (!$stmt->execute()) {
		$stmt->close();
		throw new RuntimeException('Failed to update setting ' . $key . ': ' . $stmt->error);
	}
	$stmt->close();
}

function installer_create_librarian(mysqli $mysqli, $name, $username, $password) {
	$stmt = $mysqli->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
	if (!$stmt) {
		throw new RuntimeException('Failed to prepare user lookup: ' . $mysqli->error);
	}
	$stmt->bind_param('s', $username);
	$stmt->execute();
	$stmt->store_result();
	$hash = md5($password);
	if ($stmt->num_rows > 0) {
		$stmt->bind_result($existingId);
		$stmt->fetch();
		$stmt->close();
		$update = $mysqli->prepare('UPDATE users SET type = \'librarian\', name = ?, password = ? WHERE id = ?');
		if (!$update) {
			throw new RuntimeException('Failed to prepare librarian update: ' . $mysqli->error);
		}
		$update->bind_param('ssi', $name, $hash, $existingId);
		if (!$update->execute()) {
			$update->close();
			throw new RuntimeException('Unable to update librarian: ' . $update->error);
		}
		$update->close();
		return;
	}
	$stmt->close();
	$insert = $mysqli->prepare('INSERT INTO users (type, username, name, password) VALUES (\'librarian\', ?, ?, ?)');
	if (!$insert) {
		throw new RuntimeException('Failed to prepare user creation: ' . $mysqli->error);
	}
	$insert->bind_param('sss', $username, $name, $hash);
	if (!$insert->execute()) {
		$insert->close();
		throw new RuntimeException('Unable to create librarian: ' . $insert->error);
	}
	$insert->close();
}

function installer_write_config_file(array $config) {
	$content = "<?php\nreturn " . var_export($config, true) . ";\n";
	if (file_put_contents(HOMELIB_CONFIG_FILE, $content, LOCK_EX) === false) {
		throw new RuntimeException('Unable to write config.php. Please check file permissions.');
	}
}

function installer_assert_config_destination_is_writable() {
	$configFile = HOMELIB_CONFIG_FILE;
	$configDir = dirname($configFile);
	if (file_exists($configFile)) {
		if (!is_writable($configFile)) {
			throw new RuntimeException('Config file exists but is not writable. Please adjust permissions and try again.');
		}
		return;
	}
	if (!is_dir($configDir)) {
		throw new RuntimeException('Config directory not found: ' . $configDir);
	}
	if (!is_writable($configDir)) {
		throw new RuntimeException('Config directory is not writable. Please fix permissions and try again.');
	}
	$testFile = rtrim($configDir, '/\\') . '/.homelib-install-' . uniqid('', true) . '.tmp';
	$testResult = @file_put_contents($testFile, 'write-test', LOCK_EX);
	if ($testResult === false) {
		throw new RuntimeException('Unable to write to the config directory. Please fix permissions and try again.');
	}
	@unlink($testFile);
}

function installer_assert_session_storage_ready() {
	$storageDir = HOMELIB_ROOT . '/storage/sessions';
	if (!is_dir($storageDir)) {
		if (!@mkdir($storageDir, 0775, true)) {
			throw new RuntimeException('Unable to create session storage directory: ' . $storageDir);
		}
	}
	if (!is_writable($storageDir)) {
		throw new RuntimeException('Session storage directory is not writable: ' . $storageDir);
	}
	$testFile = rtrim($storageDir, '/\\') . '/.homelib-session-test-' . uniqid('', true) . '.tmp';
	$testWrite = @file_put_contents($testFile, 'session-write-test', LOCK_EX);
	if ($testWrite === false) {
		throw new RuntimeException('Unable to write to session storage directory. Please fix permissions and try again.');
	}
	@unlink($testFile);
	$htaccess = rtrim($storageDir, '/\\') . '/.htaccess';
	if (!file_exists($htaccess)) {
		@file_put_contents($htaccess, "Deny from all\n");
	}
}

function installer_escape($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>my.home.library · Installer</title>
	<link rel="stylesheet" href="assets/css/app.css" />
	<style>
		.install-shell { max-width: 720px; margin: 0 auto; padding: 40px 16px; }
		.install-card { background: #fff; border-radius: 16px; padding: 32px; box-shadow: 0 10px 30px rgba(20, 23, 28, 0.08); }
		.install-card h1 { margin-top: 0; }
		.install-steps { display: flex; gap: 16px; margin-bottom: 24px; }
		.install-step { flex: 1; padding: 12px 16px; border-radius: 12px; text-align: center; border: 1px solid #dfe3ea; font-weight: 600; }
		.install-step.is-active { border-color: #222; background: #f5f6f7; }
		.install-messages { margin-bottom: 24px; }
		.install-messages ul { margin: 0; padding-left: 20px; }
		.install-messages li { margin: 4px 0; }
		.install-form label { display: block; margin-bottom: 16px; font-weight: 600; }
		.install-form input, .install-form textarea { width: 100%; margin-top: 6px; }
		.install-actions { margin-top: 24px; display: flex; justify-content: flex-end; }
	</style>
</head>
<body>
	<main class="install-shell">
		<section class="install-card">
			<h1>my.home.library Setup</h1>
			<p class="muted">Complete the steps below to prepare your library console.</p>
			<div class="install-steps">
				<div class="install-step<?php echo $step === 'database' ? ' is-active' : ''; ?>">1. Database</div>
				<div class="install-step<?php echo $step === 'admin' ? ' is-active' : ''; ?>">2. Librarian &amp; Site</div>
			</div>
			<?php if ($errors): ?>
				<div class="install-messages" style="color:#b42318;">
					<strong>We hit a snag:</strong>
					<ul>
						<?php foreach ($errors as $error): ?>
							<li><?php echo installer_escape($error); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			<?php if ($notices): ?>
				<div class="install-messages" style="color:#17663c;">
					<ul>
						<?php foreach ($notices as $notice): ?>
							<li><?php echo installer_escape($notice); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ($step === 'database'): ?>
				<form method="post" class="install-form">
					<input type="hidden" name="action" value="database" />
					<label>Database host
						<input type="text" name="dbHost" required value="<?php echo installer_escape($dbState['host']); ?>" />
					</label>
					<label>Database user
						<input type="text" name="dbUser" required value="<?php echo installer_escape($dbState['user']); ?>" />
					</label>
					<label>Database password
						<input type="password" name="dbPass" autocomplete="new-password" />
					</label>
					<label>Database name
						<input type="text" name="dbName" required value="<?php echo installer_escape($dbState['name']); ?>" />
					</label>
					<p class="muted">We will create the database (if needed) and load the default schema.</p>
					<div class="install-actions">
						<button type="submit">Save and continue</button>
					</div>
				</form>
			<?php else: ?>
				<form method="post" class="install-form">
					<input type="hidden" name="action" value="admin" />
					<label>Site title
						<input type="text" name="siteTitle" required value="<?php echo installer_escape($siteTitleValue); ?>" />
					</label>
					<label>Site subtitle
						<input type="text" name="siteSubtitle" value="<?php echo installer_escape($siteSubtitleValue); ?>" />
					</label>
					<label>Librarian name
						<input type="text" name="adminName" required value="<?php echo installer_escape($adminNameValue); ?>" />
					</label>
					<label>Librarian username
						<input type="text" name="adminUsername" required value="<?php echo installer_escape($adminUsernameValue); ?>" />
					</label>
					<label>Librarian password
						<input type="password" name="adminPassword" required minlength="6" autocomplete="new-password" />
					</label>
					<p class="muted">This account can manage books, readers, and settings.</p>
					<p class="muted">Ensure the root directory is writable before finishing setup (e.g. run <code>chmod -R 775 /path/to/my.home.library/</code> on Linux)</p>
					<div class="install-actions">
						<button type="submit">Complete setup</button>
					</div>
				</form>
			<?php endif; ?>
		</section>
	</main>
</body>
</html>
