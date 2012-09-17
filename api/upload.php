<?php

require 'api-common.php';
require '../extlib/resize-class/resize-class.php';

/***
 *
 * This API endpoint enables image uploading and creates a square 100x100px thumbnail.
 *
 */


if (!isset($_FILES['userfile']['tmp_name'])) {
	header('HTTP/1.1 400 Bad Request');
	break;
}


$extension = pathinfo($_FILES['userfile']['name'], PATHINFO_EXTENSION);
if (!$extension) {
	if (isset($_FILES['userfile']['type'])) {
		// e.g. "image/png"
		$extension = substr($_FILES['userfile']['type'], strpos($_FILES['userfile']['type'], "/") + 1);
	}
	else {
		// Sensible default?
		$extension = "jpg";
	}
}



$filename = 'uploads/' . $user['user_id'] . '/' . md5_file($_FILES['userfile']['tmp_name']) . '.' . $extension;
$filepath = '../' . $filename;
$fileurl  = APP_URL . $filename;

$thumbnailname = 'uploads/' . $user['user_id'] . '/' . md5_file($_FILES['userfile']['tmp_name']) . '.100x100' . '.' . $extension;
$thumbnailpath = '../' . $thumbnailname;
$thumbnailurl  = APP_URL . $thumbnailname;





if (!is_dir(pathinfo($filepath, PATHINFO_DIRNAME))) {
	// Recursively create directory:
	mkdir(pathinfo($filepath, PATHINFO_DIRNAME), 0777, true);
}


if (move_uploaded_file($_FILES['userfile']['tmp_name'], $filepath)) {
	
	
	// Create a square 100x100px thumbnail.
	// @see http://net.tutsplus.com/tutorials/php/image-resizing-made-easy-with-php/
	
	// *** 1) Initialise / load image
	$resizeObj = new resize($filepath);
	
	// *** 2) Resize image (options: exact, portrait, landscape, auto, crop)
	$resizeObj->resizeImage(100, 100, 'crop');
	
	// *** 3) Save image
	$resizeObj->saveImage($thumbnailpath, 100);
	
	
	
    echo json_encode(array('url' => $fileurl, 'thumbnail' => $thumbnailurl));
}
else {
    header('HTTP/1.1 400 Bad Request');
	exit;
}

