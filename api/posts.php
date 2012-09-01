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
		
		$out = array();
		
		foreach ($posts as $post) {
			unset($post['user_token']);
			unset($post['user_secret']);
			
			$post['id'] = (string) $post['_id'];
			unset($post['_id']);
			
			$out[] = $post;
		}
		
		echo json_encode($out);
		
		break;
	
	
		
	case "POST":
		
		// Here's how to handle requests encoded as application/json in PHP:
		// The alternative using `Backbone.emulateJSON = true;` isn't more elegant.
		// @see http://backbonejs.org/#Sync-emulateJSON
		
		$post = json_decode(file_get_contents('php://input'), true);
		
		// Add token and secret for current user:
		$post['user_token']  = $_SESSION['access_token']['oauth_token'];
		$post['user_secret'] = $_SESSION['access_token']['oauth_token_secret'];


		$m = new Mongo();
		$mongoposts = $m->tampon->posts;

		$mongoposts->insert($post);

		echo json_encode(array("id" => (string) $post['_id']));

		break;
		
		
	case "DELETE":
		$id = basename($_SERVER['REQUEST_URI']);
		
		if (strlen($id) == 24) {
			// Looks like a valid MongoId
			
			$m = new Mongo();
			$m->tampon->posts->remove(array('_id' => new MongoId($id)));
			// XXX: Add security
		}
		else {
			header('HTTP/1.1 400 Bad Request');
			exit;
		}
		
		break;
		
		
	default:
		header('HTTP/1.1 400 Bad Request');
		break;
}


