<!DOCTYPE html>
<head>
  <meta charset="UTF-8" />
</head>
<body>
<?php

/**
 * Update the users profile image, or profile background image using OAuth.
 *
 * Although this example uses your user token/secret, you can use
 * the user token/secret of any user who has authorised your application.
 *
 * Instructions:
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

if ( ! empty($_FILES)) {

  require '../tmhOAuth.php';
  require '../tmhUtilities.php';
  $tmhOAuth = new tmhOAuth(array(
    'consumer_key'    => 'YOUR_CONSUMER_KEY',
    'consumer_secret' => 'YOUR_CONSUMER_SECRET',
    'user_token'      => 'A_USER_TOKEN',
    'user_secret'     => 'A_USER_SECRET',
  ));

  // note the type and filename are set here as well
  $params = array(
    'image' => "@{$_FILES['image']['tmp_name']};type={$_FILES['image']['type']};filename={$_FILES['image']['name']}",
  );

  // if we are setting the background we want it to be displayed
  if ($_POST['method'] == 'update_profile_background_image')
    $params['use'] = 'true';

  $code = $tmhOAuth->request('POST', $tmhOAuth->url("1/account/{$_POST['method']}"),
    $params,
    true, // use auth
    true  // multipart
  );

  if ($code == 200) {
    tmhUtilities::pr(json_decode($tmhOAuth->response['response']));
  }
  tmhUtilities::pr(htmlentities($tmhOAuth->response['response']));
}

?>

<form action="images.php" method="POST" enctype="multipart/form-data">
  <div>
    <select name="method" id="method" >
      <option value="update_profile_image">update_profile_image</option>
      <option value="update_profile_background_image">update_profile_background_image</option>
    </select>
    <input type="file" name="image" />
    <input type="submit" value="Submit" />
  </div>
</form>
</body>
</html>