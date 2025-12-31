<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

$publicLibrary = (bool)$library->getSetting('publicLibrary', false);
if ($publicLibrary) {
	auth_bootstrap_session();
} else {
	auth_require_login();
}

$bookId = isset($_GET['bookId']) ? (int)$_GET['bookId'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$fileId = isset($_GET['fileId']) ? (int)$_GET['fileId'] : 0;

if ($bookId <= 0 || $fileId <= 0) {
	http_response_code(400);
	echo 'A valid bookId and fileId are required';
	exit;
}

$book = $library->getBook($bookId);
if (!$book) {
	http_response_code(404);
	echo 'Book not found';
	exit;
}

$coverIds = isset($book['dateCreated_file_ids']) && is_array($book['dateCreated_file_ids'])
	? $book['dateCreated_file_ids']
	: array();

if (!in_array($fileId, $coverIds, true)) {
	http_response_code(404);
	echo 'Cover image not found for this book';
	exit;
}

$file = $library->getFile($fileId, true);
if (!$file || empty($file['path']) || !is_file($file['path'])) {
	http_response_code(404);
	echo 'File not found';
	exit;
}

$mime = 'application/octet-stream';
if (function_exists('finfo_open')) {
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	if ($finfo) {
		$detected = finfo_file($finfo, $file['path']);
		if ($detected !== false) {
			$mime = $detected;
		}
		finfo_close($finfo);
	}
}

$size = filesize($file['path']);
header('Content-Type: ' . $mime);
if ($size !== false) {
	header('Content-Length: ' . $size);
}
header('Content-Disposition: inline; filename="' . basename($file['filename']) . '"');
header('Cache-Control: private, max-age=31536000');
readfile($file['path']);
exit;
?>
