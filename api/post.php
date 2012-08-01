<?php

header('Content-type: application/json');


$post = $_POST;

$m = new Mongo();
$posts = $m->tampon->posts;

$posts->insert($post);

echo json_encode(array("id" => (string) $post['_id']));



