<?php

header('Content-type: application/json');

require 'config.php';


// All endpoints require authentication:

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


