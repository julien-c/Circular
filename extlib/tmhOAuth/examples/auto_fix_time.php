<?php

/**
 * Verify the user token and secret works. If successful we will be given the
 * details of the user. If not an error explaining why will be returned.
 *
 * Although this example uses your user token/secret, you can use
 * the user token/secret of any user who has authorised your application.
 *
 * This example differs from others in that it will reattempt a request if
 * the timestamp is detected to be off from the Twitter servers.
 *
 * Instructions:
 * 1) If you don't have one already, create a Twitter application on
 *      https://dev.twitter.com/apps
 * 2) From the application details page copy the consumer key and consumer
 *      secret into the place in this code marked with (YOUR_CONSUMER_KEY
 *      and YOUR_CONSUMER_SECRET)
 * 3) From the application details page copy the access token and access token
 *      secret into the place in this code marked with (A_USER_TOKEN
 *      and A_USER_SECRET)
 * 4) Visit this page using your web browser.
 *
 * @author themattharris
 */

require '../tmhOAuth.php';
require '../tmhUtilities.php';
$tmhOAuth = new tmhOAuth(array(
  'consumer_key'    => 'YOUR_CONSUMER_KEY',
  'consumer_secret' => 'YOUR_CONSUMER_SECRET',
  'user_token'      => 'A_USER_TOKEN',
  'user_secret'     => 'A_USER_SECRET',
));

// for the demo set the timestamp to yesterday
$tmhOAuth->config['force_timestamp'] = true;
$tmhOAuth->config['timestamp'] = strtotime('yesterday');

$code = tmhUtilities::auto_fix_time_request($tmhOAuth, 'GET', $tmhOAuth->url('1/account/verify_credentials'));

if ($code == 200) {
  if ($tmhOAuth->auto_fixed_time)
    echo 'Had to auto adjust the time. Please check the date and time is correct on your device/server';

  tmhUtilities::pr(json_decode($tmhOAuth->response['response']));
} else {
  tmhUtilities::pr(htmlentities($tmhOAuth->response['response']));
}

?>