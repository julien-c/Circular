<?php

/**
 * Obtain a users token and secret using xAuth.
 * This example is intended to be run from the command line. To use it:
 *
 * Instructions:
 * 1) If you don't have one already, create a Twitter application on
 *      https://dev.twitter.com/apps
 * 2) From the application details page copy the consumer key and consumer
 *      secret into the place in this code marked with (YOUR_CONSUMER_KEY
 *      and YOUR_CONSUMER_SECRET)
 * 3) In a terminal or server type:
 *      php /path/to/here/oob.php
 *
 * @author themattharris
 */

require '../tmhOAuth.php';
require '../tmhUtilities.php';
$tmhOAuth = new tmhOAuth(array(
  'consumer_key'    => 'YOUR_CONSUMER_KEY',
  'consumer_secret' => 'YOUR_CONSUMER_SECRET',
));

function welcome() {
  echo <<<EOM
tmhOAuth PHP Out-of-band.
This script runs the OAuth flow in out-of-band mode. You will need access to
a web browser to authorise the application. At the end of this script you will
be presented with the user token and secret needed to authenticate as the user.

EOM;
}

function request_token($tmhOAuth) {
  $code = $tmhOAuth->request('POST', $tmhOAuth->url('oauth/request_token', ''), array(
    'oauth_callback' => 'oob',
  ));

  if ($code == 200) {
    $oauth_creds = $tmhOAuth->extract_params($tmhOAuth->response['response']);

    // update with the temporary token and secret
    $tmhOAuth->config['user_token']  = $oauth_creds['oauth_token'];
    $tmhOAuth->config['user_secret'] = $oauth_creds['oauth_token_secret'];

    $url = $tmhOAuth->url('oauth/authorize', '') . "?oauth_token={$oauth_creds['oauth_token']}";
    echo <<<EOM

Copy and paste this URL into your web browser and follower the prompts to get a pin code.
    {$url}

EOM;
  } else {
    echo "There was an error communicating with Twitter. {$tmhOAuth->response['response']}" . PHP_EOL;
    die();
  }
}

function access_token($tmhOAuth, $pin) {
  $code = $tmhOAuth->request('POST', $tmhOAuth->url('oauth/access_token', ''), array(
    'oauth_verifier' => trim($pin)
  ));

  if ($code == 200) {
    $oauth_creds = $tmhOAuth->extract_params($tmhOAuth->response['response']);

    // print tokens
    echo <<<EOM
Congratulations, below is the user token and secret for {$oauth_creds['screen_name']}.
Use these to make authenticated calls to Twitter using the application with
consumer key: {$tmhOAuth->config['consumer_key']}

User Token: {$oauth_creds['oauth_token']}
User Secret: {$oauth_creds['oauth_token_secret']}

EOM;
  } else {
    echo "There was an error communicating with Twitter. {$tmhOAuth->response['response']}" . PHP_EOL;
  }
  var_dump($tmhOAuth);
  die();
}

welcome();
request_token($tmhOAuth);
$pin = tmhUtilities::read_input('What was the Pin Code?: ');
access_token($tmhOAuth, $pin);

?>