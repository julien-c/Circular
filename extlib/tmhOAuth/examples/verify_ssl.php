<?php

/**
 * Verify whether a verified SSL connection can be made to the Twitter API
 *
 * This example is designed to check whether your local curl implementation
 * is working with the provided cacert.pem file in this repository.
 *
 * It is recommended that you try and run this example script before updating
 * your production copy of tmhOAuth.
 *
 * Instructions:
 * 1) If you are not comfortable using the include cacert.pem file you can
 *      obtain your own copy from http://curl.haxx.se/ca/cacert.pem - just
 *      ensure you place it in the same folder as tmhOAuth or update the
 *      config['curl_cainfo'] and config['curl_capath'] values.
 * 2) In a terminal or server type:
 *      php /path/to/here/verify_ssl.php
 * 3) If all went well you will be told a verified SSL connection was successfully made
 *
 * @author themattharris
 */

require '../tmhOAuth.php';
require '../tmhUtilities.php';

// since version 0.6 tmhOAuth automatically sets the SSL parameters to true.
// we repeat the new SSL configuratio here for readability of this test. You
// don't need to do this in a production script.

$tmhOAuth = new tmhOAuth(array(
  'curl_ssl_verifypeer' => true,
  'curl_ssl_verifyhost' => 2,
));


// Make an SSL request to the Twitter API help/test endpoint
$code = $tmhOAuth->request(
  'GET',
  $tmhOAuth->url('1/help/test'),
  array(),
  false
);

// Verify the SSL worked as expected
if ($code == 200 && $tmhOAuth->response['info']['ssl_verify_result'] === 0) {
  echo 'A verified SSL connection was successfully made to ' . $tmhOAuth->response['info']['url'] . PHP_EOL;
} elseif ($code == 200 && $tmhOAuth->response['info']['ssl_verify_result'] !== 0) {
  echo 'ERROR: A verified SSL connection could not be successfully made to ' . $tmhOAuth->response['info']['url'] . PHP_EOL;
  echo 'The error was: ' . $tmhOAuth->response['error'];
} elseif ($code !== 200) {
  echo 'ERROR: There was a problem making the request' . PHP_EOL;
  if (!empty($tmhOAuth->response['error']))
    echo 'The error was: ' . $tmhOAuth->response['error'] . PHP_EOL;
  else
    echo 'The HTTP response code was: ' . $code . PHP_EOL;
}

?>