<?php

/**
 * Retrieve a list of friends for the authenticating user and then lookup
 * their details using users/lookup. If you want to retrieve followers you
 * can change the URL from '1/friends/ids' to '1/followers/ids'.
 *
 * Although this example uses your user token/secret, you can use
 * the user token/secret of any user who has authorised your application.
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

define('LOOKUP_SIZE', 100);

require '../tmhOAuth.php';
require '../tmhUtilities.php';
$tmhOAuth = new tmhOAuth(array(
  'consumer_key'    => 'YOUR_CONSUMER_KEY',
  'consumer_secret' => 'YOUR_CONSUMER_SECRET',
  'user_token'      => 'A_USER_TOKEN',
  'user_secret'     => 'A_USER_SECRET',
));

function check_rate_limit($response) {
  $headers = $response['headers'];
  if ($headers['x_ratelimit_remaining'] == 0) :
    $reset = $headers['x_ratelimit_reset'];
    $sleep = time() - $reset;
    echo 'rate limited. reset time is ' . $reset . PHP_EOL;
    echo 'sleeping for ' . $sleep . ' seconds';
    sleep($sleep);
  endif;
}

$cursor = '-1';
$ids = array();
while (true) :
  if ($cursor == '0')
    break;

  $tmhOAuth->request('GET', $tmhOAuth->url('1/friends/ids'), array(
    'cursor' => $cursor
  ));

  // check the rate limit
  check_rate_limit($tmhOAuth->response);

  if ($tmhOAuth->response['code'] == 200) {
    $data = json_decode($tmhOAuth->response['response'], true);
    $ids = array_merge($ids, $data['ids']);
    $cursor = $data['next_cursor_str'];
  } else {
    echo $tmhOAuth->response['response'];
    break;
  }
  usleep(500000);
endwhile;

// lookup users
$paging = ceil(count($ids) / LOOKUP_SIZE);
$users = array();
for ($i=0; $i < $paging ; $i++) {
  $set = array_slice($ids, $i*LOOKUP_SIZE, LOOKUP_SIZE);

  $tmhOAuth->request('GET', $tmhOAuth->url('1/users/lookup'), array(
    'user_id' => implode(',', $set)
  ));

  // check the rate limit
  check_rate_limit($tmhOAuth->response);

  if ($tmhOAuth->response['code'] == 200) {
    $data = json_decode($tmhOAuth->response['response'], true);
    $users = array_merge($users, $data);
  } else {
    echo $tmhOAuth->response['response'];
    break;
  }

}
var_dump($users);

?>