<?php

require 'api-common.php';

/***
 *
 * This API endpoint conforms to Backbone.sync's default CRUD/REST implementation.
 * @see http://backbonejs.org/#Sync
 *
 * The other endpoint (`times.php`) enables bulk modifications of posts' times via POST.
 *
 */





switch ($_SERVER['REQUEST_METHOD']) {
	
	case "GET":
		
		// Retrieve all posts by current user, sorted by time ascending:
		$m = new Mongo();
		$posts = $m->tampon->posts->find(array('user.user_id' => $user['user_id']))->sort(array('time' => 1));
		
		$out = array();
		
		foreach ($posts as $post) {
			// Don't expose authentication info through the API:
			unset($post['user']);
			// Don't display Twitter request info either:
			unset($post['url']);
			unset($post['type']);
			
			
			// Translation layer/adapter for Backbone:
			// XXX: Use the exact same data in Backbone as in Mongo
			// @see http://stackoverflow.com/questions/12390553/how-to-make-backbones-and-mongodbs-ids-work-seamlessly
			$post['id'] = (string) $post['_id'];
			unset($post['_id']);
			if (isset($post['time'])) {
				$post['time'] = $post['time']->sec;
			}
			$post['status'] = $post['params']['status'];
			unset($post['params']['status']);
			
			$out[] = $post;
		}
		
		echo json_encode($out);
		
		break;
	
	
		
	case "POST":
		
		// Here's how we handle requests encoded as application/json in PHP:
		// (The alternative using `Backbone.emulateJSON = true;` isn't more elegant. @see http://backbonejs.org/#Sync-emulateJSON)
		
		$post = json_decode(file_get_contents('php://input'), true);
		
		// Add user information:
		$post['user'] = $user;
		// Add Twitter request info:
		if (isset($post['picture'])) {
			// $post['url']  = '1.1/statuses/update_with_media';
			// Until we move to API v1.1:
			$post['url']  = 'https://upload.twitter.com/1/statuses/update_with_media.json';
			$post['type'] = 'post_with_media';
		}
		else {
			$post['url']  = '1/statuses/update';
			$post['type'] = 'post';
		}
		
		// Nest status into `params`:
		$post['params'] = array('status' => $post['status']);
		unset($post['status']);
		// XXX: Apparently Backbone has poor support for nested attributes
		// @see http://stackoverflow.com/questions/6351271/backbone-js-get-and-set-nested-object-attribute
		
		
		
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
			$m->tampon->posts->remove(array('_id' => new MongoId($id), 'user.user_id' => $user['user_id']));
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
			
			// The only possible update right now is clicking "Post now" on a scheduled post:
			
			if (isset($put['time']) && $put['time'] == "now") {
				
				$m = new Mongo();
				$post = $m->tampon->posts->findOne(array('_id' => new MongoId($id), 'user.user_id' => $user['user_id']));
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


