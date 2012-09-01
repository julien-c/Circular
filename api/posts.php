<?php

header('Content-type: application/json');

// This endpoint requires authentication:

session_start();

if (!isset($_SESSION['access_token'])) {
	header('HTTP/1.1 401 Unauthorized');
	exit;
}

// All right, our user is authenticated.

switch ($_SERVER['REQUEST_METHOD']) {
	
	case "GET":
	
		$m = new Mongo();
		$posts = $m->tampon->posts->find(array('user_token' => $_SESSION['access_token']['oauth_token']));
		
		foreach ($posts as $post) {
			unset($post['user_token']);
			unset($post['user_secret']);
			var_dump($post);
		}
		
		break;
	
	
		
	case "POST":
		
		var_dump($HTTP_RAW_POST_DATA);
		var_dump($_REQUEST);
		// Add token and secret for current user:
		$post['user_token']  = $_SESSION['access_token']['oauth_token'];
		$post['user_secret'] = $_SESSION['access_token']['oauth_token_secret'];


		$m = new Mongo();
		$mongoposts = $m->tampon->posts;

		$mongoposts->insert($post);

		echo json_encode(array("id" => (string) $post['_id']));

		break;
		
}


