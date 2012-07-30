<?php

$post = $_POST;

$m = new \Mongo();
$posts = $m->tampon->posts;

$posts->insert($post);

var_dump($post);

