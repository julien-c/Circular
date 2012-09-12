<?php

/***
 *
 * This API endpoint enables bulk modifications of posts' times via POST.
 *
 */


header('Content-type: application/json');

// This endpoint requires authentication:

session_start();

if (!isset($_SESSION['access_token'])) {
	header('HTTP/1.1 401 Unauthorized');
	exit;
}

// All right, our user is authenticated.

// $_SESSION['access_token'] contains oauth_token, oauth_token_secret, user_id, screen_name

$user = array(
	'user_id'          => (int) $_SESSION['access_token']['user_id'],
	'user_screen_name' =>       $_SESSION['access_token']['screen_name'],
	'user_token'       =>       $_SESSION['access_token']['oauth_token'],
	'user_secret'      =>       $_SESSION['access_token']['oauth_token_secret']
);




if (!isset($_POST['posts'])) {
	exit;
}

$posts = $_POST['posts'];



$m = new Mongo();
$mongoposts = $m->tampon->posts;


foreach ($posts as $post) {
	
	$mongoposts->update(
		array('_id' => new MongoId($post['id']), 'user_id' => $user['user_id']),
		array('$set' => array('time' => new MongoDate($post['time'])))
	);
	// We only update the post if it is owned by the current user.
	
}


echo json_encode(array("success" => true));




