<?php

/**
 * Copyright 2013 Tuenti Technologies S.L.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once('../../src/Tuenti.php');

// Configuration
$restApiBaseUrl = 'TUENTI_REST_API_BASE_URL';
$clientId = 'YOUR_CLIENT_ID';
$clientSecret = 'YOUR_CLIENT_SECRET';

// Create the SDK instance
$tuenti = new Tuenti(
  array(
    'restApiBaseUrl' => $restApiBaseUrl,
    'clientId' => $clientId,
    'clientSecret' => $clientSecret
  )
);

try {

  if ($tuenti->hasAccessToken()) {

    try {

      // Provided we have an access token, we can call the endpoints in the REST
      // API requiring resource owner credentials
      $user = $tuenti->api('/users/current')->get();
      $userFriends = $tuenti->api('/users/current/friends')->get();

    } catch (TuentiApiInvalidToken $e) {

      // The access token has most likely expired or been revoked, so let us
      // discard it and request user authorization again
      $tuenti->setAccessToken(NULL);

    }
  }

  if (!$tuenti->hasAccessToken()) {

    // We don't have yet the user's authorization: prepare the parameters for
    // the displaying the authorization dialog
    $authorizationRedirectUri = 'https://' . $_SERVER['HTTP_HOST']
        . dirname($_SERVER['REQUEST_URI']) . '/authorization_embedded.php';

    // Retrieve the context data for the user's session on Tuenti
    $user = $tuenti->getContextField('tuenti_user');
    $page = $tuenti->getContextField('tuenti_page');

    // Generate a CSRF state token and persist it in the session for later use
    $_SESSION['state'] = md5(uniqid(rand(), true));

  }

} catch (TuentiException $e) {
  $errorMessage = get_class($e) . ': ' . $e->getMessage();
}

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>Tuenti PHP SDK for Applications - Example Embedded Application</title>
    <style>
      body { font: 13px/1.4 sans-serif; } 
      a { color: #378fcf; } 
      pre { padding: 12px; background: #eee; }
    </style>
  </head>
  <body>
    <h1>Tuenti PHP SDK for Applications - Example Embedded Application</h1>
    <div>
    <?php if (isset($errorMessage)): ?>
      <p>An error has occurred: <?php echo $errorMessage; ?></p>
    <?php endif ?>
    <?php if (isset($page)): ?>
      <p>This page is embedded in an premium page in Tuenti with id <?php echo $page['id']; ?>
      and this user <strong>is <?php echo $page['follower']? '' : 'not'; ?></strong> a follower.</p>
    <?php endif ?>
      <p>This user's locale is <?php echo $user['locale']; ?>.</p>
      <p>This user's data: <pre><?php print_r($user); ?></pre></p>
    <?php if ($tuenti->hasAccessToken()): ?>
      <p>We have a valid access token.</p>
      <p>This user's friends data: <pre><?php print_r($userFriends); ?></pre></p>
    <?php else: ?>
      <p>We don't have an access token:
      <a href="javascript:showAuthorizationDialog()">Request user authorization.</a></p>
    <?php endif ?>
    </div>
    <script type="text/javascript">

  window.onTuentiSdkReady = function() {
    Tuenti.initialize({
      client_id: '<?php echo $clientId ?>',
    });
  };

  // Load the SDK asynchronously
  (function(d){
    var sdkScript,
    id = 'tuenti-js',
    firstScript = d.getElementsByTagName('script')[0];

    if (!d.getElementById(id)) {
      sdkScript = d.createElement('script');
      sdkScript.id = id;
      sdkScript.async = true;
      sdkScript.src = "https://static-api.tuenti.net/2.0/js/sdk-embedded.js"
      firstScript.parentNode.insertBefore(sdkScript, firstScript);
    }
  }(document));

  function showAuthorizationDialog() {
    Tuenti.UI.Dialog.show(
      { dialog: 'authorize',
        redirect_uri: '<?php echo $authorizationRedirectUri ?>',
        response_type: 'code',
        scope: 'user.basic.read,user.friends.read',
        state: '<?php echo $_SESSION['state']?>' },
      function(response) {
        if (response.code) {
          console.log('Authorization code ' + response.code + ' obtained');
          if (confirm('Authorization successul. OK to reload?')) {
            window.location.reload();
          }
        } else if (response.error) {
          var message = 'Authorization failed: ' + response.error;
          console.log(message);
          alert(message);
        }
      }
    );
  }

  </script>
  </body>
</html>