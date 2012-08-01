<?php

header('Content-type: application/json');

if (!isset($_POST['posts'])) {
	exit;
}

$posts = $_POST['posts'];



$m = new Mongo();
$mongoposts = $m->tampon->posts;


foreach ($posts as $post) {
	
	$mongoposts->update(
		array('_id' => new MongoId($post['id'])),
		array('$set' => array('time' => new MongoDate($post['timestamp'])))
	);
	
}


echo json_encode(array("success" => true));




