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
    'clientSecret' => $clientSecret,
  )
);

// Verify the CSRF token
if ($_GET['state'] !== $_SESSION['state']) {
  // Request origin cannot be verified - bail out
  http_response_code(401);
  exit(1);
}

// We are on the 2nd step of the authorization code flow: complete the authorization by exchanging the
// authorization code provided in the 'code' parameter of the request by an access token
try {
  $tuenti->setAccessTokenFromAuthorizationResponse();
} catch (TuentiException $e) {
  // Handle the error
  error_log($e);
}