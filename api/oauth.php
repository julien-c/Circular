<?php

/**
 * @see tmhOAuth/examples/oauth_flow.php
 * 
 * If the user doesn't already have an active session, we use `authenticate` instead of `authorize` 
 * so that a user having already authorized the app doesn't have to do it again.
 * If on the other hand, the user already has an active session, we use `authorize`
 * so that he can log out from his current Twitter account and log in as somebody else if needed.
 */

require 'config.php';
require '../extlib/tmhOAuth/tmhOAuth.php';
require '../extlib/tmhOAuth/tmhUtilities.php';
require_once __DIR__.'/vendor/autoload.php';


$tmhOAuth = new tmhOAuth(array(
	'consumer_key'    => CONSUMER_KEY,
	'consumer_secret' => CONSUMER_SECRET,
));

header('Content-type: application/json');

(new CustomSessionHandler)->setup();




function outputError($tmhOAuth) {
	header('HTTP/1.1 500 Internal Server Error');
	echo json_encode($tmhOAuth->response['response']);
}

function wipe() {
	session_destroy();
	echo json_encode(array('wiped' => "success"));
}


// Step 1: Request a temporary token
function request_token($tmhOAuth) {
	$code = $tmhOAuth->request(
		'POST',
		$tmhOAuth->url('oauth/request_token', ''),
		array(
			'oauth_callback' => tmhUtilities::php_self()
		)
	);
	
	if ($code == 200) {
		$_SESSION['oauth'] = $tmhOAuth->extract_params($tmhOAuth->response['response']);
		if (isset($_SESSION['account']['id'])) {
			// We already have a logged in user account
			authorize($tmhOAuth);
		}
		else {
			authenticate($tmhOAuth);
		}
	} 
	else {
		outputError($tmhOAuth);
	}
}


// Step 2: Direct the user to the authenticate web page
function authenticate($tmhOAuth) {
	$authurl = $tmhOAuth->url("oauth/authenticate", '') .  "?oauth_token={$_SESSION['oauth']['oauth_token']}";
	
	echo json_encode(array('authurl' => $authurl));
}


function authorize($tmhOAuth) {
	$authurl = $tmhOAuth->url("oauth/authorize", '') .  "?oauth_token={$_SESSION['oauth']['oauth_token']}";
	
	echo json_encode(array('authurl' => $authurl));
}



// Step 3: This is the code that runs when Twitter redirects the user to the callback. Exchange the temporary token for a permanent access token
function access_token($tmhOAuth) {
	$tmhOAuth->config['user_token']  = $_SESSION['oauth']['oauth_token'];
	$tmhOAuth->config['user_secret'] = $_SESSION['oauth']['oauth_token_secret'];
	
	$code = $tmhOAuth->request(
		'POST',
		$tmhOAuth->url('oauth/access_token', ''),
		array(
			'oauth_verifier' => $_REQUEST['oauth_verifier']
		)
	);
	
	if ($code == 200) {
		unset($_SESSION['oauth']);
		
		$access_token = $tmhOAuth->extract_params($tmhOAuth->response['response']);
		
		$user_id = (int) $access_token['user_id'];
		
		$m = new MongoClient();
		$user = $m->circular->users->findOne(array('user_id' => $user_id));
		
		if ($user) {
			// This Twitter user has already logged in before.
			
		}
		else {
			// This Twitter user has never logged in before, add him to our users.
			$user = array(
				'user_id'          => (int) $access_token['user_id'],
				'user_screen_name' =>       $access_token['screen_name'],
				'user_token'       =>       $access_token['oauth_token'],
				'user_secret'      =>       $access_token['oauth_token_secret']
			);
			
			$m->circular->users->insert($user, array('safe' => true));
		}
		
		// We now have our $user, including $user['_id'] (the user's MongoId in our system).
		// Now let's figure out which account manages this user:
		
		if (isset($_SESSION['account']['id'])) {
			// We already have a logged in user account
			
			$m->circular->accounts->update(
				array('_id' => new MongoId($_SESSION['account']['id'])),
				array('$addToSet' => array('users' => $user['_id'])),
				array('safe' => true)
			);
			$account = $m->circular->accounts->findOne(array('_id' => new MongoId($_SESSION['account']['id'])));
			// Is there a way to do the previous two operations in one operation?
			
			// Remove the user from any other account (we don't want any user to be managed by several accounts)
			// This enables "merging" accounts.
			$m->circular->accounts->update(
				array(
					'_id' => array('$ne' => new MongoId($_SESSION['account']['id'])),
					'users' => $user['_id']
				),
				array(
					'$pull' => array('users' => $user['_id'])
				)
			);
			// Should we remove accounts that have no more users?
		}
		else {
			// No account's logged in right now
			
			$account = $m->circular->accounts->findOne(array('users' => $user['_id']));
			if ($account) {
				// We have retrieved an existing account for this user.
			}
			else {
				// We don't have an account for this user, let's create one:
				$account = array('users' => array($user['_id']));
				
				$m->circular->accounts->insert(
					$account,
					array('safe' => true)
				);
			}
		}
		
		// At this point, we have our $account, containing MongoId references to several users.
		
		$_SESSION['account']['id']    = (string) $account['_id'];
		$_SESSION['account']['users'] = array();
		foreach ($account['users'] as $user) {
			$_SESSION['account']['users'][(string) $user] = array();
		}
		
		
		header('Location: ' . APP_URL);
	}
	else {
		outputError($tmhOAuth);
	}
}


// Step 4: Now the user has authenticated, do something with the permanent token and secret we received
function verify_credentials($tmhOAuth, $id) {
	$m = new MongoClient();
	$user = $m->circular->users->findOne(array('_id' => new MongoId($id)));
	
	$tmhOAuth->config['user_token']  = $user['user_token'];
	$tmhOAuth->config['user_secret'] = $user['user_secret'];
	
	$code = $tmhOAuth->request(
		'GET',
		$tmhOAuth->url('1.1/account/verify_credentials')
	);
	
	if ($code == 200) {
		$response = json_decode($tmhOAuth->response['response']);
		
		$_SESSION['account']['users'][$id] = array(
			'user_id'           => $response->id,
			'user_screen_name'  => $response->screen_name,
			'profile_image_url' => $response->profile_image_url,
			'name'              => $response->name,
			'id'                => $id
		);
	}
	else {
		outputError($tmhOAuth);
	}
}



/* Auth Flow */

if (isset($_REQUEST['wipe'])) {
	// Logging out
	wipe();
	return;
}

if (isset($_REQUEST['start'])) {
	// Let's start the OAuth dance
	request_token($tmhOAuth);
}
elseif (isset($_REQUEST['oauth_verifier'])) {
	access_token($tmhOAuth);
}
elseif (isset($_SESSION['account'])) {
	// Some credentials already stored in this browser session.
	
	foreach ($_SESSION['account']['users'] as $id => $user) {
		if (!isset($user['profile_image_url'])) {
			verify_credentials($tmhOAuth, $id);
		}
	}
	
	echo json_encode($_SESSION['account']);
}
else {
	// User's not logged in.
	echo json_encode(array('loggedin' => false));
}

