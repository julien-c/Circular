<?php

/**
 * Obtain a users token and secret using xAuth.
 * Twitter must have granted you xAuth access to use this.
 *
 * Instructions:
 * 1) If you don't have one already, create a Twitter application on
 *      https://dev.twitter.com/apps
 * 2) From the application details page copy the consumer key and consumer
 *      secret into the place in this code marked with (YOUR_CONSUMER_KEY
 *      and YOUR_CONSUMER_SECRET)
 * 3) Fill in the username and password of the user you wish to obtain
 *      the user token and secret for.
 * 4) In a terminal or server type:
 *      php /path/to/here/xauth.php
 *
 * @author themattharris
 */

require '../tmhOAuth.php';
require '../tmhUtilities.php';
$tmhOAuth = new tmhOAuth(array(
  'consumer_key'    => 'YOUR_CONSUMER_KEY',
  'consumer_secret' => 'YOUR_CONSUMER_SECRET',
));

$code = $tmhOAuth->request('POST', $tmhOAuth->url('oauth/access_token', ''), array(
  'x_auth_username' => '',
  'x_auth_password' => '',
  'x_auth_mode'     => 'client_auth'
));

if ($code == 200) {
  $tokens = $tmhOAuth->extract_params($tmhOAuth->response['response']);
  tmhUtilities::pr($tokens);
} else {
  tmhUtilities::pr(htmlentities($tmhOAuth->response['response']));
}

?>