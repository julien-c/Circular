<?php

require 'api-common.php';

/***
 *
 * This (private) API endpoint only record stats about user usage.
 *
 */




// Increment number of hits and record screen name:

$m = new Mongo();
$m->tampon->users->update(
	array('user_id' => $user['user_id']),
	array(
		'$set' => array('user_screen_name' => $user['user_screen_name']),
		'$inc' => array('hits' => 1)
	), 
	array('upsert' => true)
);




