<?php

/***
 *
 * This API endpoint conforms to Backbone.sync's default CRUD/REST implementation.
 * @see http://backbonejs.org/#Sync
 *
 * The other endpoint (`times.php`) enables bulk modifications of posts' times.
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
	'user_screen_name' => $_SESSION['access_token']['screen_name'],
	'user_token'       => $_SESSION['access_token']['oauth_token'],
	'user_secret'      => $_SESSION['access_token']['oauth_token_secret']
);


switch ($_SERVER['REQUEST_METHOD']) {
	
	case "GET":
		
		$m = new Mongo();
		$posts = $m->tampon->posts->find(array('user_id' => $user['user_id']));
		
		$out = array();
		
		foreach ($posts as $post) {
			unset($post['user_id']);
			unset($post['user_screen_name']);
			unset($post['user_token']);
			unset($post['user_secret']);
			
			$post['id'] = (string) $post['_id'];
			unset($post['_id']);
			
			$out[] = $post;
		}
		
		echo json_encode($out);
		
		break;
	
	
		
	case "POST":
		
		// Here's how we handle requests encoded as application/json in PHP:
		// (The alternative using `Backbone.emulateJSON = true;` isn't more elegant. @see http://backbonejs.org/#Sync-emulateJSON)
		
		$post = json_decode(file_get_contents('php://input'), true);
		
		// Add user information:
		$post = array_merge($post, $user);
		
		
		$m = new Mongo();
		
		if (isset($post['time']) && $post['time'] == "now") {
			// If explicitly requested, send it right now through `queue`:
			$m->tampon->queue->insert($post);
		}
		else {
			$m->tampon->posts->insert($post);
		}
		
		// MongoId are assumed to be unique accross collections
		// @see http://stackoverflow.com/questions/5303869/mongodb-are-mongoids-unique-across-collections
		
		echo json_encode(array("id" => (string) $post['_id']));
		
		break;
		
		
	case "DELETE":
		
		// Which post are we deleting?
		$id = basename($_SERVER['REQUEST_URI']);
		
		if (strlen($id) == 24) {
			// Looks like a valid MongoId
			
			$m = new Mongo();
			$m->tampon->posts->remove(array('_id' => new MongoId($id), 'user_id' => $user['user_id']));
			// We only delete the post if it is owned by the current user.
		}
		else {
			header('HTTP/1.1 400 Bad Request');
			exit;
		}
		
		break;
		
		
	case "PUT":
		
		// Which post are we updating?
		$id = basename($_SERVER['REQUEST_URI']);
		
		if (strlen($id) == 24) {
			// Looks like a valid MongoId
			
			$put = json_decode(file_get_contents('php://input'), true);
			
			// The only possible update right now is "Post now" on a scheduled post:
			if (isset($put['time']) && $put['time'] == "now") {
				
				$m = new Mongo();
				$post = $m->tampon->posts->findOne(array('_id' => new MongoId($id), 'user_id' => $user['user_id']));
				// We only update the post if it is owned by the current user.
				
				if ($post) {
					// Move to sending queue:
					$m->tampon->queue->insert($post);
					$m->tampon->posts->remove(array('_id' => $post['_id']));
				}
			}
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


