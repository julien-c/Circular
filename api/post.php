<?php

header('Content-type: application/json');

$post = $_POST;

// Add token and secret for current user:
session_start();
$post['user_token']  = $_SESSION['access_token']['oauth_token'];
$post['user_secret'] = $_SESSION['access_token']['oauth_token_secret'];


$m = new Mongo();
$mongoposts = $m->tampon->posts;

$mongoposts->insert($post);

echo json_encode(array("id" => (string) $post['_id']));



