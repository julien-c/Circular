<?php

/**
 * Use OAuth Echo to upload a picture to Twitpic and then Tweet about it.
 *
 * Although this example uses your user token/secret, you can use
 * the user token/secret of any user who has authorised your application.
 *
 * Remember to set the variable $twitpic_key to be your TwitPic API Key from
 * here:
 *    http://dev.twitpic.com/apps/
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

// we're using a hardcoded image path here. You can easily replace this with
// an uploaded image - see images.php in the examples folder for how to do this
// 'image = "@{$_FILES['image']['tmp_name']};type={$_FILES['image']['type']};filename={$_FILES['image']['name']}",

// this is the image to upload. It should be in the same directory as this file.
$image = 'image.png';
$mime  = 'image/png';
$twitpic_key = '';
$delegator = 'http://api.twitpic.com/2/upload.json';
$x_auth_service_provider = 'https://api.twitter.com/1/account/verify_credentials.json';

generate_verify_header($tmhOAuth, $x_auth_service_provider);
$params = prepare_request($tmhOAuth, $x_auth_service_provider, $twitpic_key, $image, $mime);

// post to OAuth Echo provider
$code = make_request($tmhOAuth, $delegator, $params, false, true);

if ($code != 200) {
  tmhUtilities::pr('There was an error communicating with the delegator.');
  tmhUtilities::pr($tmhOAuth);
  die();
}

$resp = json_decode($tmhOAuth->response['response']);
$params = array(
  'status' => 'I just OAuth echoed a picture: ' . $resp->url
);
$code = make_request($tmhOAuth, $tmhOAuth->url('1/statuses/update'), $params, true, false);

if ($code == 200) {
  tmhUtilities::pr('Picture OAuth Echo\'d!');
  tmhUtilities::pr(json_decode($tmhOAuth->response['response']));
} else {
  tmhUtilities::pr('There was an error from Twitter.');
  tmhUtilities::pr($tmhOAuth);
  die();
}

function generate_verify_header($tmhOAuth, $x_auth_service_provider) {
  // generate the verify crendentials header -- BUT DON'T SEND
  // we prevent the request because we're not the ones sending the verify_credentials request, the delegator is
  $tmhOAuth->config['prevent_request'] = true;
  $tmhOAuth->request('GET', $x_auth_service_provider);
  $tmhOAuth->config['prevent_request'] = false;
}

function prepare_request($tmhOAuth, $x_auth_service_provider, $key, $media, $media_type='image/jpeg') {
  // create the headers for the echo
  $headers = array(
    'X-Auth-Service-Provider'            => $x_auth_service_provider,
    'X-Verify-Credentials-Authorization' => $tmhOAuth->auth_header,
  );

  // load the headers for the request
  $tmhOAuth->headers = $headers;

  // prepare the request to the delegator
  $params = array(
    'key'     => $key,
    'media'   => "@{$media};type={$media_type};filename={$media}",
    'message' => 'trying something out'
  );

  return $params;
}

function make_request($tmhOAuth, $url, $params, $auth, $multipart) {
  // make the request, no auth, multipart, custom headers
  $code = $tmhOAuth->request('POST', $url, $params, $auth, $multipart);
  return $code;
}

?>