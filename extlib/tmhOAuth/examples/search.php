<?php

date_default_timezone_set('UTC');

require '../tmhOAuth.php';
require '../tmhUtilities.php';
$tmhOAuth = new tmhOAuth(array());

$params = array(
  'q'        => 'Search Query',
  'since_id' => 'Get results since this ID (or leave blank for earliest allowed)',
  'pages'    => 'How many pages should be retrieved?',
  'rpp'      => 'Results per page (default 15)',
  'max_id'   => 'Max ID to accept. This isn\'t sent to Search but instead used to filter the received results',
  'geocode'  => 'Geo co-ordinates (e.g. 37.781157,-122.398720,1mi)',
  'lang'     => 'Restrict results to a specific language? (en,fr,de etc)'
);

foreach ($params as $k => $v) :
  $p[$k] = tmhUtilities::read_input("{$v}: ");
  if (empty($p[$k]))
    unset($p[$k]);
endforeach;

$pages = intval($p['pages']);
$pages = $pages > 0 ? $pages : 1;
$results = array();

for ($i=1; $i < $pages; $i++) {
  $args = array_intersect_key(
    $p, array(
      'q'        => '',
      'since_id' => '',
      'rpp'      => '',
      'geocode'  => '',
      'lang'     => ''
  ));
  $args['page'] = $i;

  $tmhOAuth->request(
    'GET',
    'http://search.twitter.com/search.json',
    $args,
    false
  );

  echo "Received page {$i}\t{$tmhOAuth->url}" . PHP_EOL;

  if ($tmhOAuth->response['code'] == 200) {
    $data = json_decode($tmhOAuth->response['response'], true);
    foreach ($data['results'] as $tweet) {
      $results[$tweet['id_str']] = $tweet;
    }
  } else {
    $data = htmlentities($tmhOAuth->response['response']);
    echo 'There was an error.' . PHP_EOL;
    var_dump($data);
    break;
  }
}

$save = json_encode($results);
file_put_contents('results.json', $save);

echo count($results) . ' results' . PHP_EOL;
foreach ($results as $result) {
  $date = strtotime($result['created_at']);
  $result['from_user'] = str_pad($result['from_user'], 15, ' ');
  $result['text'] = str_replace(PHP_EOL, '', $result['text']);
  echo "{$result['id_str']}\t{$date}\t{$result['from_user']}\t\t{$result['text']}" . PHP_EOL;
}

?>