<?php

require 'api-common.php';

/***
 *
 * This API endpoint enables bulk modifications of posts' times via POST.
 *
 */




if (!isset($_POST['posts'])) {
	exit;
}

$posts = $_POST['posts'];



$m = new Mongo();
$mongoposts = $m->tampon->posts;


foreach ($posts as $post) {
	
	$mongoposts->update(
		array('_id' => new MongoId($post['id']), 'user.user_id' => $user['user_id']),
		array('$set' => array('time' => new MongoDate($post['time'])))
	);
	// We only update the post if it is owned by the current user.
	
}


echo json_encode(array("success" => true));




