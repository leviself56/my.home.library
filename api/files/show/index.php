<?php
require_once(dirname(dirname(__DIR__)).'/_libraries/core.php');

$library = new homeLib();

auth_require_role('librarian');

$fileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($fileId <= 0) {
	http_response_code(400);
	echo 'Invalid file id';
	exit;
}

$file = $library->getFile($fileId, true);
if (!$file || !isset($file['path']) || !is_file($file['path'])) {
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
