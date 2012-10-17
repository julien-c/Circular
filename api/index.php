<?php


require_once __DIR__.'/vendor/autoload.php';

$app = new Silex\Application();

$app->get('/posts', function() use ($app) {
	return 'Hello '.$app->escape($name);
});

$app->post('/posts', function(Request $request) {

});

$app->delete('/posts/{id}', function(Request $request, $id) {

});

$app->put('/posts/{id}', function(Request $request, $id) {
	
	return $app->json(array('id' => (string) $post['_id']));
});

$app->run();

