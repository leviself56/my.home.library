<?php

class homeLib {
	/** @var db */
	protected $db;
	protected $fileStorageDir;
	protected $settingKeys = array('siteTitle', 'siteSubtitle', 'swName', 'swVersion', 'swURL', 'publicLibrary');
	protected $settingsCache = null;
	protected $settingDefaults = array(
		'siteTitle' => 'Home Library',
		'siteSubtitle' => 'Curated by the Self family',
		'swName' => 'HomeLib Console',
		'swVersion' => '1.0',
		'swURL' => '',
		'publicLibrary' => false
	);
	protected $bookConditionOptions = array('New', 'Like New', 'Very Good', 'Good', 'Acceptable', 'Poor');

	public function __construct(array $config = array()) {
		$dbConfig = $this->resolveDbConfig($config);
		$this->db = new db($dbConfig['host'], $dbConfig['user'], $dbConfig['pass'], $dbConfig['name'], $dbConfig['charset']);
		$this->fileStorageDir = dirname(__DIR__) . '/_files';
		if (!is_dir($this->fileStorageDir)) {
			if (!@mkdir($this->fileStorageDir, 0775, true) && !is_dir($this->fileStorageDir)) {
				throw new RuntimeException('Unable to initialize file storage directory');
			}
		}
	}

	public function listBooks(array $filters = array()) {
		$sql = "SELECT b.id, b.dateAdded, b.isbn, b.inLibrary, b.borrowedBy, b.borrowedByUserId, b.returnBy, b.title, b.author, b.dateCreated_file_ids, b.`condition`, borrower.name AS borrowedByName FROM books b LEFT JOIN users borrower ON borrower.id = b.borrowedByUserId";
		$conditions = array();
		$params = array();

		if (array_key_exists('inLibrary', $filters)) {
			$conditions[] = "b.inLibrary = ?";
			$params[] = (int)$filters['inLibrary'] === 1 ? 1 : 0;
		}

		if (!empty($filters['borrowedBy'])) {
			$conditions[] = "b.borrowedBy = ?";
			$params[] = trim($filters['borrowedBy']);
		}

		if ($conditions) {
			$sql .= ' WHERE ' . implode(' AND ', $conditions);
		}

		$sql .= ' ORDER BY b.title ASC, b.id ASC';

		$rows = $this->executeQuery($sql, $params)->fetchAll();
		return $this->hydrateBookRows($rows);
	}

	public function listCheckedOut() {
		$sql = "SELECT b.id, b.dateAdded, b.isbn, b.inLibrary, b.borrowedBy, b.borrowedByUserId, b.returnBy, b.title, b.author, b.dateCreated_file_ids, b.`condition`, borrower.name AS borrowedByName FROM books b LEFT JOIN users borrower ON borrower.id = b.borrowedByUserId WHERE b.inLibrary = 0 ORDER BY (b.returnBy IS NULL) ASC, b.returnBy ASC, b.title ASC";
		$rows = $this->executeQuery($sql)->fetchAll();
		return $this->hydrateBookRows($rows);
	}

	public function getBook($bookId) {
		$bookId = (int)$bookId;
		if ($bookId <= 0) {
			return null;
		}
		$result = $this->executeQuery(
			"SELECT b.id, b.dateAdded, b.isbn, b.inLibrary, b.borrowedBy, b.borrowedByUserId, b.returnBy, b.title, b.author, b.dateCreated_file_ids, b.`condition`, borrower.name AS borrowedByName FROM books b LEFT JOIN users borrower ON borrower.id = b.borrowedByUserId WHERE b.id = ? LIMIT 1",
			array($bookId)
		)->fetchArray();
		return $result ? $this->hydrateBookRow($result) : null;
	}

	public function searchBooks($term) {
		$term = trim((string)$term);
		if ($term === '') {
			return array();
		}
		$like = '%' . $term . '%';
		$sql = "SELECT b.id, b.dateAdded, b.isbn, b.inLibrary, b.borrowedBy, b.borrowedByUserId, b.returnBy, b.title, b.author, b.dateCreated_file_ids, b.`condition`, borrower.name AS borrowedByName FROM books b LEFT JOIN users borrower ON borrower.id = b.borrowedByUserId WHERE b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? ORDER BY b.title ASC LIMIT 100";
		$rows = $this->executeQuery($sql, array($like, $like, $like))->fetchAll();
		return $this->hydrateBookRows($rows);
	}

	public function createBook(array $data) {
		$title = isset($data['title']) ? trim($data['title']) : '';
		if ($title === '') {
			throw new InvalidArgumentException('Title is required');
		}

		$author = $this->nullOrTrim(isset($data['author']) ? $data['author'] : null);
		$isbn = $this->nullOrTrim(isset($data['isbn']) ? $data['isbn'] : null);
		$condition = $this->normalizeBookCondition(isset($data['condition']) ? $data['condition'] : null, 'New');
		$fileIds = $this->normalizeFileIdInput(isset($data['file_ids']) ? $data['file_ids'] : null);
		$this->assertFileIdsExist($fileIds);
		$fileIdPayload = $this->encodeIdList($fileIds);

		$this->executeQuery(
			"INSERT INTO books (dateAdded, isbn, inLibrary, borrowedBy, returnBy, title, author, dateCreated_file_ids, `condition`) VALUES (NOW(), ?, 1, NULL, NULL, ?, ?, ?, ?)",
			array($isbn, $title, $author, $fileIdPayload, $condition)
		);

		return $this->getBook((int)$this->db->lastInsertID());
	}

	public function updateBook($bookId, array $data) {
		$bookId = (int)$bookId;
		if ($bookId <= 0) {
			throw new InvalidArgumentException('bookId is required');
		}

		$existing = $this->getBook($bookId);
		if (!$existing) {
			throw new RuntimeException('Book not found');
		}

		$title = isset($data['title']) ? trim((string)$data['title']) : '';
		if ($title === '') {
			throw new InvalidArgumentException('Title is required');
		}

		$author = $this->nullOrTrim(isset($data['author']) ? $data['author'] : null);
		$isbn = $this->nullOrTrim(isset($data['isbn']) ? $data['isbn'] : null);
		$condition = $this->normalizeBookCondition(
			isset($data['condition']) ? $data['condition'] : null,
			isset($existing['condition']) ? $existing['condition'] : 'Good'
		);

		$this->executeQuery(
			"UPDATE books SET title = ?, author = ?, isbn = ?, `condition` = ? WHERE id = ?",
			array($title, $author, $isbn, $condition, $bookId)
		);

		return $this->getBook($bookId);
	}

	public function attachFilesToBook($bookId, $fileIds) {
		$bookId = (int)$bookId;
		if ($bookId <= 0) {
			throw new InvalidArgumentException('bookId is required');
		}

		$book = $this->getBook($bookId);
		if (!$book) {
			throw new RuntimeException('Book not found');
		}

		$normalized = $this->normalizeFileIdInput($fileIds);
		if (empty($normalized)) {
			throw new InvalidArgumentException('At least one file_id value is required');
		}
		$this->assertFileIdsExist($normalized);

		$current = isset($book['dateCreated_file_ids']) && is_array($book['dateCreated_file_ids']) ? $book['dateCreated_file_ids'] : array();
		$combined = array_values(array_unique(array_merge($current, $normalized)));
		$fileIdPayload = $this->encodeIdList($combined);

		$this->executeQuery(
			"UPDATE books SET dateCreated_file_ids = ? WHERE id = ?",
			array($fileIdPayload, $bookId)
		);

		return $this->getBook($bookId);
	}

	public function deleteBook($bookId) {
		$bookId = (int)$bookId;
		if ($bookId <= 0) {
			throw new InvalidArgumentException('bookId is required');
		}

		$book = $this->getBook($bookId);
		if (!$book) {
			throw new RuntimeException('Book not found');
		}

		$fileIds = array();
		if (!empty($book['dateCreated_file_ids'])) {
			$fileIds = array_merge($fileIds, $book['dateCreated_file_ids']);
		}
		$checkoutFiles = $this->executeQuery(
			"SELECT in_file_ids FROM checkOuts WHERE bookID = ?",
			array($bookId)
		)->fetchAll();
		foreach ($checkoutFiles as $row) {
			$fileIds = array_merge($fileIds, $this->decodeIdList(isset($row['in_file_ids']) ? $row['in_file_ids'] : null));
		}
		$fileIds = array_values(array_unique(array_filter(array_map('intval', $fileIds))));

		$this->executeQuery(
			"DELETE FROM checkOuts WHERE bookID = ?",
			array($bookId)
		);

		$this->executeQuery(
			"DELETE FROM books WHERE id = ? LIMIT 1",
			array($bookId)
		);
		if ($this->db->affectedRows() === 0) {
			throw new RuntimeException('Unable to delete book');
		}

		$this->deleteFilesByIds($fileIds);

		return true;
	}

	public function getSetting($key, $default = null) {
		$key = $this->normalizeSettingKey($key);
		if ($key === null) {
			return $default;
		}
		$settings = $this->getSettings(array($key));
		return isset($settings[$key]) ? $settings[$key] : $default;
	}

	public function getSettings(array $keys = array()) {
		$normalizedKeys = array();
		foreach ($keys as $key) {
			$key = $this->normalizeSettingKey($key);
			if ($key !== null) {
				$normalizedKeys[$key] = $key;
			}
		}
		if ($this->settingsCache === null) {
			$this->settingsCache = $this->loadAllSettings();
		}
		if (empty($normalizedKeys)) {
			return $this->settingsCache;
		}
		$result = array();
		foreach ($normalizedKeys as $key) {
			if (isset($this->settingsCache[$key])) {
				$result[$key] = $this->settingsCache[$key];
			}
		}
		return $result;
	}

	public function saveSettings(array $settings) {
		$allowed = $this->settingKeys;
		$filtered = array();
		foreach ($settings as $key => $value) {
			$normalizedKey = $this->normalizeSettingKey($key);
			if ($normalizedKey === null) {
				continue;
			}
			$filtered[$normalizedKey] = $this->normalizeSettingValue($value);
		}
		if (empty($filtered)) {
			return $this->getSettings($allowed);
		}
		foreach ($filtered as $key => $value) {
			$this->executeQuery(
				"INSERT INTO settings (setting_key, setting_value, updated_datetime) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_datetime = NOW()",
				array($key, $value)
			);
		}
		$this->settingsCache = null;
		return $this->getSettings();
	}

	public function getSettingKeys() {
		return $this->settingKeys;
	}

	public function checkOutBook($bookId, array $payload) {
		$bookId = (int)$bookId;
		$book = $this->getBook($bookId);
		if (!$book) {
			throw new RuntimeException('Book not found');
		}

		if ((int)$book['inLibrary'] === 0) {
			throw new RuntimeException('Book is already checked out');
		}

		$borrowedByUserId = isset($payload['borrowedByUserId']) ? (int)$payload['borrowedByUserId'] : 0;
		if ($borrowedByUserId <= 0) {
			throw new InvalidArgumentException('borrowedByUserId is required');
		}

		$borrower = $this->getUser($borrowedByUserId);
		if (!$borrower) {
			throw new RuntimeException('Borrower not found');
		}
		$borrowedBy = isset($borrower['name']) && $borrower['name'] !== '' ? $borrower['name'] : (isset($borrower['username']) ? $borrower['username'] : 'Unknown');

		$returnBy = $this->normalizeDate(isset($payload['returnBy']) ? $payload['returnBy'] : null);
		$outComment = $this->nullOrTrim(isset($payload['outComment']) ? $payload['outComment'] : null);

		$this->executeQuery(
			"UPDATE books SET inLibrary = 0, borrowedBy = ?, borrowedByUserId = ?, returnBy = ? WHERE id = ? AND inLibrary = 1",
			array($borrowedBy, $borrowedByUserId, $returnBy, $bookId)
		);

		if ($this->db->affectedRows() === 0) {
			throw new RuntimeException('Unable to check out book');
		}

		$this->executeQuery(
			"INSERT INTO checkOuts (bookID, outComment, checkedOutBy, checkedOutByUserId, dueDate) VALUES (?, ?, ?, ?, ?)",
			array($bookId, $outComment, $borrowedBy, $borrowedByUserId, $returnBy)
		);

		$lastCheckoutId = (int)$this->db->lastInsertID();
		$updated = $this->getBook($bookId);
		if ($updated) {
			$updated['lastCheckOutId'] = $lastCheckoutId;
		}

		return $updated;
	}

	public function checkInBook($bookId, array $payload) {
		$bookId = (int)$bookId;
		$book = $this->getBook($bookId);
		if (!$book) {
			throw new RuntimeException('Book not found');
		}

		if ((int)$book['inLibrary'] === 1) {
			throw new RuntimeException('Book is already checked in');
		}

		$receivedByUserId = isset($payload['receivedByUserId']) ? (int)$payload['receivedByUserId'] : 0;
		if ($receivedByUserId <= 0) {
			throw new InvalidArgumentException('receivedByUserId is required');
		}
		$receiver = $this->getUser($receivedByUserId);
		if (!$receiver) {
			throw new RuntimeException('Receiving user not found');
		}
		$receivedBy = isset($receiver['name']) && $receiver['name'] !== '' ? $receiver['name'] : (isset($receiver['username']) ? $receiver['username'] : 'Unknown');

		$inComment = $this->nullOrTrim(isset($payload['inComment']) ? $payload['inComment'] : null);
		$condition = $this->normalizeBookCondition(
			isset($payload['condition']) ? $payload['condition'] : null,
			isset($book['condition']) ? $book['condition'] : 'Good'
		);
		$fileIds = $this->normalizeFileIdInput(isset($payload['file_ids']) ? $payload['file_ids'] : null);
		$this->assertFileIdsExist($fileIds);
		$fileIdPayload = $this->encodeIdList($fileIds);

		$this->executeQuery(
			"UPDATE books SET inLibrary = 1, borrowedBy = NULL, borrowedByUserId = NULL, returnBy = NULL, `condition` = ? WHERE id = ? AND inLibrary = 0",
			array($condition, $bookId)
		);

		if ($this->db->affectedRows() === 0) {
			throw new RuntimeException('Unable to check in book');
		}

		$openCheckout = $this->getOpenCheckoutRecord($bookId);
		if ($openCheckout) {
			$this->executeQuery(
				"UPDATE checkOuts SET inComment = ?, receivedBy = ?, receivedByUserId = ?, receivedDateTime = NOW(), in_file_ids = ? WHERE id = ?",
				array($inComment, $receivedBy, $receivedByUserId, $fileIdPayload, $openCheckout['id'])
			);
		} else {
			$this->executeQuery(
				"INSERT INTO checkOuts (bookID, outComment, inComment, receivedBy, receivedByUserId, receivedDateTime, in_file_ids) VALUES (?, NULL, ?, ?, ?, NOW(), ?)",
				array($bookId, $inComment, $receivedBy, $receivedByUserId, $fileIdPayload)
			);
		}

		return $this->getBook($bookId);
	}

	public function listFilesForBook($bookId, array $options = array()) {
		$bookId = (int)$bookId;
		if ($bookId <= 0) {
			throw new InvalidArgumentException('bookId is required');
		}

		$book = $this->getBook($bookId);
		if (!$book) {
			throw new RuntimeException('Book not found');
		}

		$options = array_merge(array(
			'only_date_created' => false
		), $options);
		$restrictToDateCreated = !empty($options['only_date_created']);

		$fileIds = array();
		if (!empty($book['dateCreated_file_ids'])) {
			$fileIds = array_merge($fileIds, $book['dateCreated_file_ids']);
		}

		if (!$restrictToDateCreated) {
			$rows = $this->executeQuery(
				"SELECT in_file_ids FROM checkOuts WHERE bookID = ? AND in_file_ids IS NOT NULL AND in_file_ids <> ''",
				array($bookId)
			)->fetchAll();
			foreach ($rows as $row) {
				$ids = $this->decodeIdList($row['in_file_ids']);
				$fileIds = array_merge($fileIds, $ids);
			}
		}

		$fileIds = array_values(array_unique(array_map('intval', $fileIds)));
		$this->assertFileIdsExist($fileIds);

		if (empty($fileIds)) {
			return array(
				'bookId' => $bookId,
				'file_ids' => array(),
				'files' => array()
			);
		}

		$placeholders = implode(',', array_fill(0, count($fileIds), '?'));
		$files = $this->executeQuery(
			"SELECT id, created_datetime, filename FROM files WHERE id IN ($placeholders) ORDER BY FIELD(id, $placeholders)",
			array_merge($fileIds, $fileIds)
		)->fetchAll();

		if ($restrictToDateCreated) {
			foreach ($files as &$fileRow) {
				$fileRow = $this->hydrateFileRow($fileRow);
				$fileRow['public_url'] = sprintf('book/coverFile/?bookId=%d&fileId=%d', $bookId, (int)$fileRow['id']);
			}
			unset($fileRow);
		} else {
			$files = $this->hydrateFileRows($files);
		}

		return array(
			'bookId' => $bookId,
			'file_ids' => $fileIds,
			'files' => $files
		);
	}

	public function getBookHistory($bookId) {
		$bookId = (int)$bookId;
		if ($bookId <= 0) {
			throw new InvalidArgumentException('bookId is required');
		}

		$book = $this->getBook($bookId);
		if (!$book) {
			throw new RuntimeException('Book not found');
		}

		$events = $this->executeQuery(
			"SELECT c.id, c.bookID, c.outComment, c.inComment, c.receivedBy, c.receivedByUserId, receiver.name AS receivedByName, c.receivedDateTime, c.created_datetime, c.checkedOutBy, c.checkedOutByUserId, borrower.name AS checkedOutByName, c.dueDate, c.in_file_ids FROM checkOuts c LEFT JOIN users borrower ON borrower.id = c.checkedOutByUserId LEFT JOIN users receiver ON receiver.id = c.receivedByUserId WHERE c.bookID = ? ORDER BY c.id DESC",
			array($bookId)
		)->fetchAll();

		foreach ($events as &$event) {
			$event['id'] = (int)$event['id'];
			$event['in_file_ids'] = $this->decodeIdList(isset($event['in_file_ids']) ? $event['in_file_ids'] : null);
			$event['checkedOutByUserId'] = isset($event['checkedOutByUserId']) && (int)$event['checkedOutByUserId'] > 0 ? (int)$event['checkedOutByUserId'] : null;
			$event['receivedByUserId'] = isset($event['receivedByUserId']) && (int)$event['receivedByUserId'] > 0 ? (int)$event['receivedByUserId'] : null;
			if (!empty($event['checkedOutByName'])) {
				$event['checkedOutBy'] = $event['checkedOutByName'];
			}
			if (!empty($event['receivedByName'])) {
				$event['receivedBy'] = $event['receivedByName'];
			}
			unset($event['checkedOutByName'], $event['receivedByName']);
			$event['status'] = ($event['receivedDateTime'] ? 'returned' : 'checked_out');
		}
		unset($event);

		return array(
			'bookId' => $bookId,
			'events' => $events
		);
	}

	public function createFile(array $data) {
		$content = null;
		if (isset($data['content'])) {
			$content = $data['content'];
		} elseif (isset($data['content_base64'])) {
			$content = $data['content_base64'];
		}

		if ($content === null) {
			throw new InvalidArgumentException('content is required');
		}

		$metadata = array();
		$binary = $this->extractFileBinary($content, $metadata);

		$hintExtension = null;
		if (!empty($data['filename'])) {
			$hintExtension = $this->extensionFromFilename($data['filename']);
		}
		if (!$hintExtension && isset($metadata['extension'])) {
			$hintExtension = $metadata['extension'];
		}

		$storedName = $this->generateStoredFilename($hintExtension);
		$filePath = $this->buildFilePath($storedName);

		if (file_put_contents($filePath, $binary) === false) {
			throw new RuntimeException('Unable to write file to storage');
		}

		$this->executeQuery(
			"INSERT INTO files (filename) VALUES (?)",
			array($storedName)
		);

		return $this->getFile((int)$this->db->lastInsertID());
	}

	public function getFile($fileId, $includePath = false) {
		$fileId = (int)$fileId;
		if ($fileId <= 0) {
			return null;
		}

		$row = $this->executeQuery(
			"SELECT id, created_datetime, filename FROM files WHERE id = ? LIMIT 1",
			array($fileId)
		)->fetchArray();

		if (!$row) {
			return null;
		}

		$row = $this->hydrateFileRow($row);
		if ($includePath) {
			$row['path'] = $this->buildFilePath($row['filename']);
		}

		return $row;
	}

	public function listFiles(array $filters = array()) {
		$sql = "SELECT id, created_datetime, filename FROM files";
		$conditions = array();
		$params = array();

		if (!empty($filters['filename'])) {
			$conditions[] = "filename LIKE ?";
			$params[] = '%' . trim($filters['filename']) . '%';
		}

		if ($conditions) {
			$sql .= ' WHERE ' . implode(' AND ', $conditions);
		}

		$sql .= ' ORDER BY created_datetime DESC, id DESC';

		$rows = $this->executeQuery($sql, $params)->fetchAll();
		return $this->hydrateFileRows($rows);
	}

	public function searchFiles($term) {
		$term = trim((string)$term);
		if ($term === '') {
			return array();
		}

		$sql = "SELECT id, created_datetime, filename FROM files WHERE filename LIKE ? ORDER BY created_datetime DESC, id DESC LIMIT 100";
		$rows = $this->executeQuery($sql, array('%' . $term . '%'))->fetchAll();
		return $this->hydrateFileRows($rows);
	}

	public function createUser(array $data) {
		$username = $this->normalizeUsername(isset($data['username']) ? $data['username'] : '');
		if ($username === '') {
			throw new InvalidArgumentException('username is required');
		}
		if (!preg_match('/^[a-z0-9._-]{3,64}$/', $username)) {
			throw new InvalidArgumentException('username must be 3-64 characters (a-z, 0-9, dot, dash, underscore)');
		}

		$name = isset($data['name']) ? trim($data['name']) : '';
		if ($name === '') {
			throw new InvalidArgumentException('name is required');
		}

		$type = $this->normalizeUserType(isset($data['type']) ? $data['type'] : null);
		$password = $this->normalizePasswordInput(isset($data['password']) ? $data['password'] : '', true);
		$passwordHash = $this->hashPassword($password);

		if ($this->userExistsByUsername($username)) {
			throw new RuntimeException('Username already exists');
		}

		$this->executeQuery(
			"INSERT INTO users (type, username, name, password) VALUES (?, ?, ?, ?)",
			array($type, $username, $name, $passwordHash)
		);

		return $this->getUser((int)$this->db->lastInsertID());
	}

	public function setUserPassword($userId, $password) {
		$userId = (int)$userId;
		if ($userId <= 0) {
			throw new InvalidArgumentException('userId is required');
		}

		$user = $this->getUser($userId);
		if (!$user) {
			throw new RuntimeException('User not found');
		}

		$password = $this->normalizePasswordInput($password, true);
		$passwordHash = $this->hashPassword($password);

		$this->executeQuery(
			"UPDATE users SET password = ? WHERE id = ?",
			array($passwordHash, $userId)
		);

		return $this->getUser($userId);
	}

	public function updateUserName($userId, $name) {
		$userId = (int)$userId;
		if ($userId <= 0) {
			throw new InvalidArgumentException('userId is required');
		}

		$name = isset($name) ? trim((string)$name) : '';
		if ($name === '') {
			throw new InvalidArgumentException('name is required');
		}

		$this->executeQuery(
			"UPDATE users SET name = ? WHERE id = ?",
			array($name, $userId)
		);

		return $this->getUser($userId);
	}

	public function deleteUser($userId) {
		$userId = (int)$userId;
		if ($userId <= 0) {
			throw new InvalidArgumentException('userId is required');
		}

		$user = $this->getUser($userId);
		if (!$user) {
			throw new RuntimeException('User not found');
		}

		$this->executeQuery(
			"DELETE FROM users WHERE id = ? LIMIT 1",
			array($userId)
		);

		if ($this->db->affectedRows() === 0) {
			throw new RuntimeException('Unable to delete user');
		}

		return true;
	}

	public function getUser($userId) {
		$userId = (int)$userId;
		if ($userId <= 0) {
			return null;
		}

		$row = $this->executeQuery(
			"SELECT id, type, created_datetime, username, name FROM users WHERE id = ? LIMIT 1",
			array($userId)
		)->fetchArray();

		return $row ? $row : null;
	}

	public function getUserByUsername($username, $includePassword = false) {
		$username = $this->normalizeUsername($username);
		if ($username === '') {
			return null;
		}

		$columns = 'id, type, created_datetime, username, name';
		if ($includePassword) {
			$columns .= ', password';
		}

		$row = $this->executeQuery(
			"SELECT $columns FROM users WHERE username = ? LIMIT 1",
			array($username)
		)->fetchArray();

		return $row ?: null;
	}

	public function authenticateUser($username, $password) {
		$username = $this->normalizeUsername($username);
		$password = $this->normalizePasswordInput($password, false);
		if ($username === '') {
			throw new InvalidArgumentException('username is required');
		}

		$user = $this->getUserByUsername($username, true);
		if (!$user || empty($user['password'])) {
			throw new RuntimeException('Invalid username or password');
		}

		if (!$this->passwordMatches($user['password'], $password)) {
			throw new RuntimeException('Invalid username or password');
		}

		unset($user['password']);
		return $user;
	}

	public function getUserDetailsWithLoans($userId) {
		$userId = (int)$userId;
		if ($userId <= 0) {
			throw new InvalidArgumentException('userId is required');
		}

		$user = $this->getUser($userId);
		if (!$user) {
			return null;
		}

		$loans = $this->collectActiveLoansForUser($user);
		return array(
			'user' => $user,
			'current_loans' => $loans,
			'active_count' => count($loans)
		);
	}

	public function listUsers(array $filters = array()) {
		$sql = "SELECT id, type, created_datetime, username, name FROM users";
		$conditions = array();
		$params = array();

		if (isset($filters['type'])) {
			$typeFilter = $this->normalizeUserType($filters['type'], true);
			if ($typeFilter !== null) {
				$conditions[] = "type = ?";
				$params[] = $typeFilter;
			}
		}

		if (!empty($filters['username'])) {
			$conditions[] = "username LIKE ?";
			$params[] = strtolower(trim($filters['username'])) . '%';
		}

		if ($conditions) {
			$sql .= ' WHERE ' . implode(' AND ', $conditions);
		}

		$sql .= ' ORDER BY created_datetime DESC, id DESC';

		return $this->executeQuery($sql, $params)->fetchAll();
	}

	public function getUserHistory($userId) {
		$userId = (int)$userId;
		if ($userId <= 0) {
			throw new InvalidArgumentException('userId is required');
		}

		$user = $this->getUser($userId);
		if (!$user) {
			throw new RuntimeException('User not found');
		}

		$matches = $this->buildUserMatchValues($user);
		$conditions = array();
		$params = array();
		$conditions[] = 'c.checkedOutByUserId = ?';
		$params[] = $userId;
		if (!empty($matches)) {
			$placeholders = implode(',', array_fill(0, count($matches), '?'));
			$conditions[] = 'c.checkedOutBy IN (' . $placeholders . ')';
			$params = array_merge($params, $matches);
		}

		$where = '(' . implode(' OR ', $conditions) . ')';
		$sql = "SELECT c.id, c.bookID, c.outComment, c.inComment, c.receivedBy, c.receivedByUserId, receiver.name AS receivedByName, c.receivedDateTime, c.created_datetime, c.checkedOutBy, c.checkedOutByUserId, borrower.name AS checkedOutByName, c.dueDate, c.in_file_ids, b.title AS bookTitle, b.author AS bookAuthor FROM checkOuts c LEFT JOIN books b ON b.id = c.bookID LEFT JOIN users borrower ON borrower.id = c.checkedOutByUserId LEFT JOIN users receiver ON receiver.id = c.receivedByUserId WHERE $where ORDER BY c.id DESC";
		$events = $this->executeQuery($sql, $params)->fetchAll();
		foreach ($events as &$event) {
			$event['id'] = (int)$event['id'];
			$event['bookID'] = (int)$event['bookID'];
			$event['in_file_ids'] = $this->decodeIdList(isset($event['in_file_ids']) ? $event['in_file_ids'] : null);
			$event['book'] = array(
				'id' => $event['bookID'],
				'title' => isset($event['bookTitle']) ? $event['bookTitle'] : null,
				'author' => isset($event['bookAuthor']) ? $event['bookAuthor'] : null
			);
			$event['checkedOutByUserId'] = isset($event['checkedOutByUserId']) && (int)$event['checkedOutByUserId'] > 0 ? (int)$event['checkedOutByUserId'] : null;
			$event['receivedByUserId'] = isset($event['receivedByUserId']) && (int)$event['receivedByUserId'] > 0 ? (int)$event['receivedByUserId'] : null;
			if (!empty($event['checkedOutByName'])) {
				$event['checkedOutBy'] = $event['checkedOutByName'];
			}
			if (!empty($event['receivedByName'])) {
				$event['receivedBy'] = $event['receivedByName'];
			}
			unset($event['bookTitle'], $event['bookAuthor'], $event['checkedOutByName'], $event['receivedByName']);
			$event['status'] = ($event['receivedDateTime'] ? 'returned' : 'checked_out');
		}
		unset($event);

		return array(
			'user' => $user,
			'events' => $events
		);
	}

	protected function getOpenCheckoutRecord($bookId) {
		$bookId = (int)$bookId;
		if ($bookId <= 0) {
			return null;
		}
		$row = $this->executeQuery(
			"SELECT id FROM checkOuts WHERE bookID = ? AND inComment IS NULL ORDER BY id DESC LIMIT 1",
			array($bookId)
		)->fetchArray();
		return $row ? $row : null;
	}

	protected function executeQuery($sql, array $params = array()) {
		if (empty($params)) {
			return $this->db->query($sql);
		}
		return $this->db->query($sql, $params);
	}

	protected function hydrateBookRows(array $rows) {
		$result = array();
		foreach ($rows as $row) {
			$result[] = $this->hydrateBookRow($row);
		}
		return $result;
	}

	protected function hydrateBookRow(array $row) {
		$row['dateCreated_file_ids'] = $this->decodeIdList(isset($row['dateCreated_file_ids']) ? $row['dateCreated_file_ids'] : null);
		$row['condition'] = $this->normalizeBookCondition(isset($row['condition']) ? $row['condition'] : null);
		$row['borrowedByUserId'] = isset($row['borrowedByUserId']) && (int)$row['borrowedByUserId'] > 0 ? (int)$row['borrowedByUserId'] : null;
		if (!empty($row['borrowedByName'])) {
			$row['borrowedBy'] = $row['borrowedByName'];
		}
		unset($row['borrowedByName']);
		return $row;
	}

	protected function hydrateFileRows(array $rows) {
		$result = array();
		foreach ($rows as $row) {
			$result[] = $this->hydrateFileRow($row);
		}
		return $result;
	}

	protected function buildUserMatchValues(array $user) {
		$matches = array();
		foreach (array('name', 'username') as $field) {
			if (!isset($user[$field])) {
				continue;
			}
			$value = trim((string)$user[$field]);
			if ($value === '') {
				continue;
			}
			$matches[$value] = $value;
		}
		return array_values($matches);
	}

	protected function hydrateFileRow(array $row) {
		$row['id'] = (int)$row['id'];
		$row['show_url'] = 'api/files/show/?id=' . $row['id'];
		if (!empty($row['filename'])) {
			$row['file_url'] = 'api/_files/' . rawurlencode($row['filename']);
		}
		return $row;
	}

	protected function collectActiveLoansForUser(array $user) {
		$matches = $this->buildUserMatchValues($user);
		$userId = isset($user['id']) ? (int)$user['id'] : 0;
		$conditions = array();
		$params = array();
		if ($userId > 0) {
			$conditions[] = 'borrowedByUserId = ?';
			$params[] = $userId;
		}
		if (!empty($matches)) {
			$placeholders = implode(',', array_fill(0, count($matches), '?'));
			$conditions[] = 'borrowedBy IN (' . $placeholders . ')';
			$params = array_merge($params, $matches);
		}
		if (empty($conditions)) {
			return array();
		}

		$where = '(' . implode(' OR ', $conditions) . ')';
		$sql = "SELECT id, title, author, borrowedBy, borrowedByUserId, returnBy FROM books WHERE inLibrary = 0 AND $where ORDER BY (returnBy IS NULL) ASC, returnBy ASC, title ASC";
		$books = $this->executeQuery($sql, $params)->fetchAll();
		if (empty($books)) {
			return array();
		}

		$bookIds = array();
		foreach ($books as $row) {
			$bookIds[] = (int)$row['id'];
		}
		$bookIds = array_values(array_unique(array_filter($bookIds)));

		$openCheckouts = array();
		if (!empty($bookIds)) {
			$placeholdersBooks = implode(',', array_fill(0, count($bookIds), '?'));
			$rows = $this->executeQuery(
				"SELECT id, bookID, created_datetime, dueDate, outComment FROM checkOuts WHERE bookID IN ($placeholdersBooks) AND inComment IS NULL ORDER BY id DESC",
				$bookIds
			)->fetchAll();
			foreach ($rows as $row) {
				$bookId = (int)$row['bookID'];
				if (isset($openCheckouts[$bookId])) {
					continue;
				}
				$row['id'] = (int)$row['id'];
				$row['bookID'] = $bookId;
				$openCheckouts[$bookId] = $row;
			}
		}

		$result = array();
		foreach ($books as $book) {
			$bookId = (int)$book['id'];
			$loan = array(
				'bookId' => $bookId,
				'title' => isset($book['title']) ? $book['title'] : null,
				'author' => isset($book['author']) ? $book['author'] : null,
				'borrowedBy' => isset($book['borrowedBy']) ? $book['borrowedBy'] : null,
				'borrowedByUserId' => isset($book['borrowedByUserId']) && (int)$book['borrowedByUserId'] > 0 ? (int)$book['borrowedByUserId'] : null,
				'dueDate' => isset($book['returnBy']) ? $book['returnBy'] : null,
				'checkoutId' => null,
				'checkedOutAt' => null,
				'outComment' => null
			);
			if (isset($openCheckouts[$bookId])) {
				$loan['checkoutId'] = (int)$openCheckouts[$bookId]['id'];
				$loan['checkedOutAt'] = $openCheckouts[$bookId]['created_datetime'];
				if (!empty($openCheckouts[$bookId]['dueDate'])) {
					$loan['dueDate'] = $openCheckouts[$bookId]['dueDate'];
				}
				if (isset($openCheckouts[$bookId]['outComment'])) {
					$loan['outComment'] = $openCheckouts[$bookId]['outComment'];
				}
			}
			$result[] = $loan;
		}

		return $result;
	}

	protected function deleteFilesByIds(array $ids) {
		if (empty($ids)) {
			return;
		}
		$unique = array();
		foreach ($ids as $id) {
			$id = (int)$id;
			if ($id > 0) {
				$unique[$id] = $id;
			}
		}
		foreach ($unique as $fileId) {
			$this->deleteFileById($fileId);
		}
	}

	protected function deleteFileById($fileId) {
		$fileId = (int)$fileId;
		if ($fileId <= 0) {
			return;
		}
		$file = $this->getFile($fileId, true);
		if ($file && !empty($file['path']) && file_exists($file['path'])) {
			@unlink($file['path']);
		}
		$this->executeQuery(
			"DELETE FROM files WHERE id = ? LIMIT 1",
			array($fileId)
		);
	}

	protected function decodeIdList($value) {
		if ($value === null || $value === '') {
			return array();
		}
		if (is_string($value)) {
			$decoded = json_decode($value, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				$value = $decoded;
			} else {
				return array();
			}
		}
		if (!is_array($value)) {
			$value = array($value);
		}
		$result = array();
		foreach ($value as $entry) {
			$id = (int)$entry;
			if ($id > 0) {
				$result[$id] = $id;
			}
		}
		return array_values($result);
	}

	protected function encodeIdList(array $ids) {
		if (empty($ids)) {
			return '[]';
		}
		$filtered = array();
		foreach ($ids as $id) {
			$id = (int)$id;
			if ($id > 0) {
				$filtered[$id] = $id;
			}
		}
		return json_encode(array_values($filtered));
	}

	protected function normalizeFileIdInput($value) {
		if ($value === null) {
			return array();
		}
		if (is_string($value)) {
			$value = trim($value);
			if ($value === '') {
				return array();
			}
			if ($value[0] === '[') {
				$decoded = json_decode($value, true);
				if (json_last_error() === JSON_ERROR_NONE) {
					$value = $decoded;
				} else {
					$value = preg_split('/[,\s]+/', $value);
				}
			} else {
				$value = preg_split('/[,\s]+/', $value);
			}
		}
		if (!is_array($value)) {
			$value = array($value);
		}
		$result = array();
		foreach ($value as $entry) {
			if (is_array($entry)) {
				continue;
			}
			$id = (int)$entry;
			if ($id > 0) {
				$result[$id] = $id;
			}
		}
		return array_values($result);
	}

	protected function assertFileIdsExist(array $ids) {
		if (empty($ids)) {
			return;
		}
		$placeholders = implode(',', array_fill(0, count($ids), '?'));
		$rows = $this->executeQuery(
			"SELECT id FROM files WHERE id IN ($placeholders)",
			$ids
		)->fetchAll();
		$found = array();
		foreach ($rows as $row) {
			$found[(int)$row['id']] = true;
		}
		foreach ($ids as $id) {
			if (!isset($found[$id])) {
				throw new RuntimeException('One or more file IDs do not exist');
			}
		}
	}

	protected function buildFilePath($filename) {
		return $this->fileStorageDir . '/' . ltrim($filename, '/');
	}

	protected function generateStoredFilename($extension = null) {
		$extension = $extension ? '.' . ltrim($extension, '.') : '.bin';
		if (!preg_match('/^[a-z0-9]{1,8}$/', ltrim($extension, '.'))) {
			$extension = '.bin';
		}
		do {
			$base = 'file_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
			$candidate = $base . $extension;
		} while (file_exists($this->buildFilePath($candidate)));
		return $candidate;
	}

	protected function extensionFromMime($mime) {
		$map = array(
			'image/jpeg' => 'jpg',
			'image/png' => 'png',
			'image/gif' => 'gif',
			'image/webp' => 'webp',
			'image/svg+xml' => 'svg',
			'application/pdf' => 'pdf'
		);
		$mime = strtolower(trim((string)$mime));
		return isset($map[$mime]) ? $map[$mime] : null;
	}

	protected function extensionFromFilename($filename) {
		$filename = trim((string)$filename);
		if ($filename === '') {
			return null;
		}
		$filename = str_replace('\\', '/', $filename);
		$filename = basename($filename);
		$dot = strrpos($filename, '.');
		if ($dot === false) {
			return null;
		}
		$extension = strtolower(substr($filename, $dot + 1));
		$extension = preg_replace('/[^a-z0-9]+/', '', $extension);
		return $extension === '' ? null : $extension;
	}

	protected function extractFileBinary($content, &$metadata = null) {
		$metadata = array('mime' => null, 'extension' => null);
		if (preg_match('#^data:([^;]+);base64,#i', $content, $matches)) {
			$metadata['mime'] = strtolower($matches[1]);
			$metadata['extension'] = $this->extensionFromMime($metadata['mime']);
			$content = substr($content, strpos($content, ',') + 1);
		}
		$content = preg_replace('/\s+/', '', $content);
		$binary = base64_decode($content, true);
		if ($binary === false) {
			throw new InvalidArgumentException('content must be base64 encoded');
		}
		return $binary;
	}

	protected function normalizeUserType($value, $allowNull = false) {
		if ($value === null) {
			return $allowNull ? null : 'user';
		}

		$value = strtolower(trim((string)$value));
		if ($value === '' || $value === 'all') {
			return $allowNull ? null : 'user';
		}

		if (in_array($value, array('librarian', 'librarians'), true)) {
			return 'librarian';
		}
		if (in_array($value, array('user', 'users', 'member', 'members'), true)) {
			return 'user';
		}

		throw new InvalidArgumentException('Invalid user type');
	}

	protected function normalizeBookCondition($value, $fallback = 'Good') {
		$value = $this->nullOrTrim($value);
		if ($value === null) {
			return $fallback;
		}
		$lower = strtolower($value);
		foreach ($this->bookConditionOptions as $option) {
			if (strtolower($option) === $lower) {
				return $option;
			}
		}
		return $fallback;
	}

	protected function loadAllSettings() {
		$defaults = $this->settingDefaults;
		if (empty($this->settingKeys)) {
			return $defaults;
		}
		try {
			$placeholders = implode(',', array_fill(0, count($this->settingKeys), '?'));
			$sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)";
			$rows = $this->executeQuery($sql, $this->settingKeys)->fetchAll();
		} catch (Exception $ex) {
			return $defaults;
		}
		$settings = $defaults;
		foreach ($rows as $row) {
			$key = $this->normalizeSettingKey(isset($row['setting_key']) ? $row['setting_key'] : null);
			if ($key === null) {
				continue;
			}
			$settings[$key] = $this->normalizeSettingValue(isset($row['setting_value']) ? $row['setting_value'] : null);
		}
		return $settings;
	}

	protected function normalizeSettingKey($key) {
		if ($key === null) {
			return null;
		}
		$candidate = trim((string)$key);
		if ($candidate === '') {
			return null;
		}
		foreach ($this->settingKeys as $allowed) {
			if (strcasecmp($allowed, $candidate) === 0) {
				return $allowed;
			}
		}
		return null;
	}

	protected function normalizeSettingValue($value) {
		if ($value === null || is_array($value)) {
			return '';
		}
		$clean = trim((string)$value);
		$clean = preg_replace('/\s+/u', ' ', $clean);
		if ($clean === null) {
			$clean = '';
		}
		if (strlen($clean) > 200) {
			$clean = substr($clean, 0, 200);
		}
		return $clean;
	}

	protected function userExistsByUsername($username) {
		$username = $this->normalizeUsername($username);
		if ($username === '') {
			return false;
		}
		$row = $this->executeQuery(
			"SELECT id FROM users WHERE username = ? LIMIT 1",
			array($username)
		)->fetchArray();
		return !empty($row);
	}

	protected function normalizeDate($candidate) {
		$candidate = $this->nullOrTrim($candidate);
		if ($candidate === null) {
			return null;
		}

		try {
			$dt = new DateTimeImmutable($candidate);
			return $dt->format('Y-m-d');
		} catch (Exception $ex) {
			throw new InvalidArgumentException('Invalid date value: ' . $candidate);
		}
	}

	protected function nullOrTrim($value) {
		if (!isset($value)) {
			return null;
		}
		if (is_string($value)) {
			$value = trim($value);
		}
		if ($value === '') {
			return null;
		}
		return $value;
	}

	protected function normalizePasswordInput($password, $enforceStrength = true) {
		$password = trim((string)$password);
		if ($password === '') {
			throw new InvalidArgumentException('password is required');
		}
		if ($enforceStrength) {
			$length = strlen($password);
			if ($length < 6) {
				throw new InvalidArgumentException('password must be at least 6 characters');
			}
			if ($length > 128) {
				throw new InvalidArgumentException('password must be 128 characters or less');
			}
		}
		return $password;
	}

	protected function hashPassword($password) {
		return md5($password);
	}

	protected function passwordMatches($expectedHash, $password) {
		$expectedHash = (string)$expectedHash;
		if ($expectedHash === '') {
			return false;
		}
		return hash_equals($expectedHash, $this->hashPassword($password));
	}

	protected function normalizeUsername($value) {
		$value = strtolower(trim((string)$value));
		return $value;
	}

	protected function resolveDbConfig(array $config) {
		if (empty($config)) {
			$config = function_exists('homelib_config') ? homelib_config() : array();
		}
		$db = array();
		if (isset($config['db']) && is_array($config['db'])) {
			$db = $config['db'];
		} else {
			$db = $config;
		}
		$db = array_merge(
			array(
				'host' => null,
				'user' => null,
				'pass' => '',
				'name' => null,
				'charset' => 'utf8mb4'
			),
			(array) $db
		);
		foreach (array('host', 'user', 'name') as $key) {
			$value = isset($db[$key]) ? trim((string)$db[$key]) : '';
			if ($value === '') {
				throw new RuntimeException('Missing database configuration value: ' . $key);
			}
			$db[$key] = $value;
		}
		if (!isset($db['charset']) || trim((string)$db['charset']) === '') {
			$db['charset'] = 'utf8mb4';
		}
		return $db;
	}
}

?>
