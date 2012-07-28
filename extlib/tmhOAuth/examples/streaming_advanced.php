<?php

/**
 * Very basic streaming API example. In production you would store the
 * received tweets in a queue or database for later processing.
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
 * 4) In a terminal or server type:
 *      php /path/to/here/streaming.php
 * 5) To stop the Streaming API either press CTRL-C or, in the folder the
 *      script is running from type:
 *      touch STOP
 *
 * @author themattharris
 */

$count=0;
$first_id=0;
function my_streaming_callback($data, $length, $metrics) {
  global $raw;
  if ($raw) :
    echo $data;
  else :
    $data = json_decode($data, true);

    $date = strtotime($data['created_at']);
    $data['text'] = str_replace(PHP_EOL, '', $data['text']);
    $data['user']['screen_name'] = str_pad($data['user']['screen_name'], 15, ' ');
    echo "{$data['id_str']}\t{$date}\t{$data['user']['screen_name']}\t\t{$data['text']}" . PHP_EOL;

    global $count;
    $count++;

    global $first_id;
    if ($first_id==0)
      $first_id = $data['id_str'];
  endif;

  global $limit;
  if ($count==$limit) :
    return true;
  endif;
  return file_exists(dirname(__FILE__) . '/STOP');
}

require '../tmhOAuth.php';
require '../tmhUtilities.php';
$tmhOAuth = new tmhOAuth(array(
  'consumer_key'    => 'YOUR_CONSUMER_KEY',
  'consumer_secret' => 'YOUR_CONSUMER_SECRET',
  'user_token'      => 'A_USER_TOKEN',
  'user_secret'     => 'A_USER_SECRET',
));

$method = 'https://stream.twitter.com/1/statuses/filter.json';
$track     = tmhUtilities::read_input('Track terms. For multiple terms separate with commas (leave blank for none): ');
$follow    = tmhUtilities::read_input('Follow accounts. For multiple accounts separate with commas (leave blank for none): ');
$locations = tmhUtilities::read_input('Bounding boxes (leave blank for none): ');
$delimited = tmhUtilities::read_input('Delimited? (1,t,true): ');
$limit     = tmhUtilities::read_input('Stop after how many tweets? (leave blank for unlimited): ');
$debug     = tmhUtilities::read_input('Debug? (1,t,true): ');
$raw       = tmhUtilities::read_input('Raw output? (1,t,true): ');

$true = array('1','t','true');

$params = array();
if (strlen($track) > 0)
  $params['track'] = $track;
if (strlen($follow) > 0)
  $params['follow'] = $follow;
if (strlen($locations) > 0)
  $params['locations'] = $locations;
if (in_array($delimited, $true))
  $params['delimited'] = 'length';
if (strlen($limit) > 0)
  $limit = intval($limit);
$debug = in_array($debug, $true);
$raw = in_array($raw, $true);

$tmhOAuth->streaming_request('POST', $method, $params, 'my_streaming_callback');
if ($debug)
  var_dump($tmhOAuth);
?>

