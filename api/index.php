<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/config.php';

$app = new Silex\Application();


/***
 *
 * Auth.
 *
 * Sample output:
 * 	array (
 * 		'id' => '507ed38198dee47b47000001',
 * 		'users' => array (
 * 			'507ed38198dee47b47000000' => MongoId('507ed38198dee47b47000000'),
 * 			'507ed31498dee4ce42000002' => MongoId('507ed31498dee4ce42000002'),
 * 		))
 *
 */

$app->before(function (Request $request) use ($app) {
	// All endpoints require authentication:
	session_start();
	if (!isset($_SESSION['account'])) {
		return new Response('Unauthorized', 401);
	}
	
	// All right, our user is authenticated.
	$users = array();
	foreach ($_SESSION['account']['users'] as $id => $value) {
		$users[$id] = new MongoId($id);
	}
	$app['account'] = array(
		'id'    => $_SESSION['account']['id'],
		'users' => $users
	);
});


/***
 *
 * Accepting JSON in request body.
 * @note: the method described in http://silex.sensiolabs.org/doc/cookbook/json_request_body.html doesn't allow us to get the whole parameter array.
 *
 */

$app->before(function (Request $request) use ($app) {
	if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
		$app['data'] = json_decode($request->getContent(), true);
	}
});



/***
 *
 * The `/posts` endpoint conforms to Backbone.sync's default CRUD/REST implementation.
 * @see http://backbonejs.org/#Sync
 *
 */

$app->get('/posts', function () use ($app) {
	// Retrieve all posts by users managed by current account, sorted by time ascending:
	
	$m = new Mongo();
	$posts = $m->tampon->posts
		->find(array('user._id' => array('$in' => $app['account']['users'])))
		->sort(array('time' => 1));
	
	
	$out = array();
	
	foreach ($posts as $post) {
		// Don't expose authentication info through the API, only the user ID:
		$post['user'] = (string) $post['user']['_id'];
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
	// @fixme
	// return $app->json($out);
});



$app->post('/posts', function (Request $request) use ($app) {
	
	$post = $app['data'];
	
	// Check that this account really manages this user
	if (!array_key_exists($post['user'], $app['account']['users'])) {
		return new Response('Unauthorized', 401);
	}
	
	// Add user information:
	$m = new Mongo();
	$user = $m->tampon->users->findOne(array('_id' => new MongoId($post['user'])));
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
	
	return $app->json(array('id' => (string) $post['_id']));
});



$app->delete('/posts/{id}', function (Request $request, $id) use ($app) {
	// According to the assert, this looks like a valid MongoId
	
	$m = new Mongo();
	$m->tampon->posts->remove(array(
		'_id'      => new MongoId($id),
		'user._id' => array('$in' => $app['account']['users'])
	));
	// We only delete the post if it is owned by the current user.
})
->assert('id', '\w{24}');



$app->put('/posts/{id}', function (Request $request, $id) use ($app) {
	
	$put = $app['data'];
	
	// The only possible update right now is clicking "Post now" on a scheduled post:
	
	if (isset($put['time']) && $put['time'] == "now") {
		
		$m = new Mongo();
		$post = $m->tampon->posts->findOne(array(
			'_id'      => new MongoId($id),
			'user._id' => array('$in' => $app['account']['users'])
		));
		// We only update the post if it is owned by the current user.
		
		if ($post) {
			// Move to sending queue:
			$m->tampon->queue->insert($post);
			$m->tampon->posts->remove(array('_id' => $post['_id']));
		}
	}
})
->assert('id', '\w{24}');



/***
 *
 * The `/times` endpoint enables bulk modifications of posts' times via POST.
 *
 */

$app->post('/times', function (Request $request) use ($app) {
	
	$posts = $app['data']['posts'];
	
	$m = new Mongo();
	$mongoposts = $m->tampon->posts;
	
	foreach ($posts as $post) {
		$mongoposts->update(
			array(
				'_id'      => new MongoId($post['id']), 
				'user._id' => array('$in' => $app['account']['users'])
			),
			array('$set' => array('time' => new MongoDate($post['time'])))
		);
		// We only update the post if it is owned by the current user.	
	}
	
	return $app->json(array("success" => true));
});



/***
 *
 * The `/upload` endpoint enables image uploading and creates a square 100x100px thumbnail.
 *
 */

$app->post('/upload', function (Request $request) use ($app) {
	$file = $request->files->get('userfile');
	if ($file->isValid()) {
		$extension = $file->guessExtension();
		$md5 = md5_file($file->getRealPath());
		
		$filename      = 'uploads/' . $app['account']['id'] . '/' . $md5 . '.' . $extension;
		$thumbnailname = 'uploads/' . $app['account']['id'] . '/' . $md5 . '.100x100' . '.' . $extension;
		
		$file = $file->move(__DIR__.'/../uploads/'.$app['account']['id'], $md5.'.'.$extension);
		
		// Create thumbnail:
		$simpleResize = new SimpleResize($file->getRealPath());
		$simpleResize->resizeImage(100, 100, 'crop');
		$simpleResize->saveImage(__DIR__.'/../'.$thumbnailname, 100);
		
		return $app->json(array('url' => APP_URL.$filename, 'thumbnail' => APP_URL.$thumbnailname));
	}
});



/***
 *
 * The `/settings` endpoint lets users interact with server-stored account-wide settings (for now, email).
 *
 */

$app->get('/settings', function (Request $request) use ($app) {
	$m = new Mongo();
	$account = $m->tampon->accounts->findOne(array('_id' => new MongoId($app['account']['id'])));
	unset($account['users']);
	return $app->json($account);
});

$app->post('/settings', function (Request $request) use ($app) {
	$email = $app['data']['email'];
	
	$m = new Mongo();
	if ($email) {
		$m->tampon->accounts->update(
			array('_id'  => new MongoId($app['account']['id'])),
			array('$set' => array('email' => $email))
		);
	}
	else {
		$m->tampon->accounts->update(
			array('_id'  => new MongoId($app['account']['id'])),
			array('$unset' => array('email' => true))
		);
	}
});



/* Run, App, Run! */

$app->run();

