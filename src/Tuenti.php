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

if (!function_exists('curl_init')) {
	throw new Exception('Tuenti needs the CURL PHP extension.');
}

if (!function_exists('json_decode')) {
	throw new Exception('Tuenti needs the JSON PHP extension.');
}

/**
 * The entry-point to the PHP SDK for Applications.
 */
class Tuenti {

	/**
	 * @var string the base URL of the REST API
	 */
	private $restApiBaseUrl;

	/**
	 * @var string the client identifier of the application
	 */
	private $clientId;

	/**
	 * @var string the client secret of the application
	 */
	private $clientSecret;

	/**
	 * @var TuentiStorage a storage implementation providing data persistence
	 */
	private $storage;

	/**
	 * @var TuentiEnvironment an environment implementation providing access to global state
	 */
	private $environment;

	/**
	 * @var mixed[CURLOPT_XXX] the options to set for cURL transfer
	 */
	private $curlOptions;

	/**
	 * @var string the OAuth 2.0 access token being used by the SDK
	 */
	private $accessToken;

	/**
	 * Creates a new instance of the PHP SDK.
	 *
	 * The following parameters are required for initialization.
	 *
	 * - string 'restApiBaseUrl'
	 *     The base URL of the REST API.
	 *
	 * - string 'clientId'
	 *     The client identifier of the application.
	 *
	 * - string 'clientSecret'
	 *     The client secret of the application.
	 *
	 * Additionally, the following parameters are available for customization.
	 *
	 * - mixed[CURLOPT_XXX] 'curlOptions' (optional)
	 *     The options to set for cURL transfer. See curl_setopt for a
	 *     comprehensive list of options. Note that the CURLOPT_USERAGENT
	 *     option is set by the SDK and will be ignored if specified.
	 *
	 * - TuentiStorage 'storage' (optional)
	 *     A storage implementation providing data persistence using a custom
	 *     method. By default, data is persisted in a PHP session.
	 *
	 * - TuentiEnvironment 'environment' (optional)
	 *     An environment implementation providing access to global state using
	 *     a custom method. By default, PHP superglobals and time are accessed
	 *     directly.
	 *
	 * @param mixed[] $parameters the options for SDK initialization, indexed by
	 *        name
	 * @throws TuentiMissingRequiredParameter if a parameter is missing
	 * @throws TuentiInvalidParameter if a parameter is invalid
	 */
	public function __construct(array $parameters) {
		// Mandatory parameters
		if (isset($parameters['restApiBaseUrl'])) {
			$baseUrlRegex = '#^http[s]?://[a-zA-Z0-9-_\.]+(/[^\s\?]*)*$#';
			if (preg_match($baseUrlRegex, $parameters['restApiBaseUrl']) === 0) {
				throw new TuentiInvalidParameter('restApiBaseUrl',
						'Invalid REST API base URL: '
						. $parameters['restApiBaseUrl']);
			}
			$this->restApiBaseUrl = $parameters['restApiBaseUrl'];
			unset($parameters['restApiBaseUrl']);
		} else {
			throw new TuentiMissingRequiredParameter('restApiBaseUrl');
		}

		if (isset($parameters['clientId'])) {
			if (empty($parameters['clientId'])
					|| !is_string($parameters['clientId'])) {
				throw new TuentiInvalidParameter('clientId',
						'Invalid client ID: ' . $parameters['clientId']);
			}
			$this->clientId = $parameters['clientId'];
			unset($parameters['clientId']);
		} else {
			throw new TuentiMissingRequiredParameter('clientId');
		}

		if (isset($parameters['clientSecret'])) {
			if (empty($parameters['clientSecret'])
					|| !is_string($parameters['clientSecret'])) {
				throw new TuentiInvalidParameter('clientSecret',
						'Invalid client secret: ' . $parameters['clientSecret']);
			}
			$this->clientSecret = $parameters['clientSecret'];
			unset($parameters['clientSecret']);
		} else {
			throw new TuentiMissingRequiredParameter('clientSecret');
		}

		// Optional parameters
		if (isset($parameters['curlOptions'])) {
			$curlOptions = $parameters['curlOptions'];
			if (!empty($curlOptions)) {
				if (!is_array($curlOptions)) {
					throw new TuentiInvalidParameter('curlOptions',
							'Invalid cURL options: ' . gettype($curlOptions));
				}

				foreach (array_keys($curlOptions) as $option) {
					if (!is_long($option)) {
						throw new TuentiInvalidParameter('curlOptions',
								'Invalid cURL options: '
								. gettype($parameters['curlOptions']));
					}
				}
				$this->curlOptions = $parameters['curlOptions'];
			}
			unset($parameters['curlOptions']);
		} else {
			$this->curlOptions = array();
		}

		if (isset($parameters['storage'])) {
			if (!($parameters['storage'] instanceof TuentiStorage)) {
				throw new TuentiInvalidParameter('storage',
					'Provided storage not instance of TuentiStorage');
			}
			$this->storage = $parameters['storage'];
			unset($parameters['storage']);
		} else {
			$this->storage = new Internal_TuentiSessionStorage($this->clientId);
		}

		if (isset($parameters['environment'])) {
			if (!($parameters['environment'] instanceof TuentiEnvironment)) {
				throw new TuentiInvalidParameter('environment',
					'Provided environment not instance of TuentiEnvironment');
			}
			$this->environment = $parameters['environment'];
			unset($parameters['environment']);
		} else {
			$this->environment = new Internal_TuentiGlobalEnvironment();
		}

		if (count($parameters) > 0) {
			// Unrecognized parameters found
			$parameterNames = array_keys($parameters);
			throw new TuentiInvalidParameter($parameterNames[0],
					'Unexpected parameter');
		}

		$this->restoreState();
	}

	/**
	 * Returns the client identifier registered for the SDK.
	 *
	 * @return string the client identifier registered for the SDK
	 */
	public function getClientId() {
		return $this->clientId;
	}

	/**
	 * Returns the client secret registered for the SDK.
	 *
	 * @return string the client secret registered for the SDK
	 */
	public function getClientSecret() {
		return $this->clientSecret;
	}

	/**
	 * Returns whether an OAuth 2.0 access token is available for use by the
	 * SDK, as previously registered using setAccessToken,
	 * setAccessTokenFromAuthorizationCode or
	 * setAccessTokenFromAuthorizationResponse.
	 *
	 * @return boolean true if an OAuth 2.0 access token is available
	 */
	public function hasAccessToken() {
		return $this->accessToken !== NULL;
	}

	/**
	 * Returns the OAuth 2.0 access token being used by the SDK, as previously
	 * registered using setAccessToken, setAccessTokenFromAuthorizationCode or
	 * setAccessTokenFromAuthorizationResponse.
	 *
	 * @return string the OAuth 2.0 access token being used by the SDK
	 */
	public function getAccessToken() {
		return $this->accessToken;
	}

	/**
	 * Sets the OAuth 2.0 access token being used by the SDK. This method should
	 * be used after obtaining an access token by other means than by invoking
	 * setAccessTokenFromAuthorizationCode or
	 * setAccessTokenFromAuthorizationResponse.
	 *
	 * @param string $accessToken the OAuth 2.0 access token to be used by the SDK
	 */
	public function setAccessToken($accessToken) {
		$this->accessToken = $accessToken;
		$this->storage->setPersistentProperty('access_token', $accessToken);
	}

	/**
	 * Exchanges an OAuth 2.0 authorization code provided by the developer for
	 * an access token by invoking the POST method on the
	 * User-Application-Access Token Collection resource and registers it in the
	 * SDK for subsequent use in API requests.
	 *
	 * @param string $code an OAuth 2.0 authorization code
	 * @param string $redirectUri the value of the redirect_uri parameter in the
	 *        request to the Authorization Dialog from which the authorization
	 *        code was obtained
	 * @throws TuentiAuthorizationCodeExchangeFailed if the authorization code provided
	 *         to the method or extracted from the request URL could not be
	 *         exchanged for an access token
	 * @throws TuentiApiServerError an error occurred in the processing of the
	 *         request to the REST API resulting in a 5xx Server Error status
	 *         code being returned
	 */
	public function setAccessTokenFromAuthorizationCode($code, $redirectUri) {
		$parameters = array(
			'grant_type' => 'authorization_code',
			'code' => $code,
			'client_id' => $this->getClientId(),
			'redirect_uri' => $redirectUri
		);
		try {
			$response = $this->api('/users/current/applications/current/access-tokens')
				->withClientCredentials()->post($parameters);
			$this->setAccessToken($response['access_token']);
		} catch (TuentiApiClientError $e) {
			throw new TuentiAuthorizationCodeExchangeFailed($e->getStatusCode(),
					$e->getType(), $e->getDescription());
		}
	}

	/**
	 * Parses the request URL for an OAuth 2.0 authorization code or an error
	 * message returned by the Authorization Dialog. If an authorization code is
	 * found, exchanges the authorization code for an access token by invoking
	 * the POST method on the User-Application-Access Token Collection resource
	 * and registers it in the SDK for subsequent use in API requests.
	 *
	 * @throws TuentiAuthorizationRequestFailed if the request to the
	 *         Authorization Dialog failed and an error was returned instead of
	 *         an authorization code
	 * @throws TuentiAuthorizationCodeNotFound if no code was provided to the
	 *         method and none could be extracted from the request URL
	 * @throws TuentiAuthorizationCodeExchangeFailed if the authorization code provided
	 *         to the method or extracted from the request URL could not be
	 *         exchanged for an access token
	 * @throws TuentiApiServerError an error occurred in the processing of the
	 *         request to the REST API resulting in a 5xx Server Error status
	 *         code being returned
	 */
	public function setAccessTokenFromAuthorizationResponse() {
		$getParameters = $this->environment->getEnvironmentVariable(
				TuentiEnvironment::VARIABLE_GET);
		if (isset($getParameters['code'])) {
			$this->setAccessTokenFromAuthorizationCode($getParameters['code'],
					$this->getCurrentUri());
		} else if (isset($getParameters['error'])) {
			$errorType = $getParameters['error'];
			$errorDescription = $getParameters['error_description'];
			throw new TuentiAuthorizationRequestFailed($errorType,
					$errorDescription);
		} else {
			throw new TuentiAuthorizationCodeNotFound();
		}
	}

	/**
	 * Returns context data for the user's session on Tuenti, as extracted from
	 * the server request in encoded and signed form. See the relevant type of
	 * application for details about the specific context fields provided.
	 *
	 * @return mixed[string] context data for the user's session on Tuenti
	 * @throws TuentiContextNotFound if no context could be extracted from the
	 *         server request
	 * @throws TuentiInvalidContext he context extracted from the server request
	 *         could not be successfully decoded or verified
	 */
	public function getContextFields() {
		return $this->getContext()->getCustomFields();
	}

	/**
	 * Returns context data for the user's session on Tuenti, as extracted from
	 * the server request in encoded and signed form. See the relevant type of
	 * application for details about the specific context fields provided.
	 *
	 * @param string $name the name of the context field
	 * @return mixed the value of the context field
	 * @throws TuentiContextNotFound if no context could be extracted from the
	 *         server request
	 * @throws TuentiInvalidContext he context extracted from the server request
	 *         could not be successfully decoded or verified
	 */
	public function getContextField($name) {
		$value = NULL;
		$fields = $this->getContextFields();
		if (isset($fields[$name])) {
			$value = $fields[$name];
		}
		return $value;
	}

	/**
	 * Returns a TuentiApiRequest object for making a request to the REST API
	 * for Applications. If the authentication method is
	 * not specified on the request object, resource owner credentials will be
	 * used. The request is finally sent upon calling the get, post, put or
	 * delete method on the request object.
	 *
	 * Resource paths must start with a slash and must not end with a slash
	 *
	 * @param string $path the path to the requested resource in the REST API
	 * @return TuentiApiRequest a builder for making a request to the REST API
	 * @throws TuentiInvalidRequestPath if the path is not syntactically valid
	 */
	public function api($path) {
		$requestPathRegex = '/^(\/[0-9a-z_-]+)+$/';
		if (preg_match($requestPathRegex, $path) === 0) {
			throw new TuentiInvalidRequestPath($path);
		}
		return $this->createTuentiApiRequest($this, $this->restApiBaseUrl
				. $path, $this->curlOptions);
	}

	/**
	 * Factory method for creating instances of TuentiApiRequest.
	 *
	 * @param Tuenti $tuenti the SDK instance
	 * @param string $url the absolute URL of the request
	 * @param array $curlOptions list of custom curl options
	 * @param Internal_CurlClient $curlClient (optional)  client for HTTP requests
	 * @return Internal_TuentiApiRequest
	 */
	protected function createTuentiApiRequest(Tuenti $tuenti, $url,
			$curlOptions, $curlClient = NULL) {
		return new Internal_TuentiApiRequest($tuenti, $url, $curlOptions,
				$curlClient);
	}

	/**
	 * Returns an abstraction for context data from the user's session on
	 * Tuenti.
	 *
	 * @return Internal_TuentiContext the internal abstract of the the context
	 */
	private function getContext() {
		if (!isset($this->context)) {
			$this->context = new Internal_TuentiContext($this,
					$this->environment);
		}
		return $this->context;
	}

	/**
	 * Returns the current URI.
	 *
	 * @return string the current URI.
	 */
	private function getCurrentUri() {
		$server = $this->environment->getEnvironmentVariable(
				TuentiEnvironment::VARIABLE_SERVER);
		return 'https://' . $server['HTTP_HOST']
				. strtok($server['REQUEST_URI'], '?');
	}

	/**
	 * Reads the state from storage and restores it.
	 */
	private function restoreState() {
		$accessToken = $this->storage->getPersistentProperty('access_token');
		if ($accessToken !== NULL) {
			$this->accessToken = $accessToken;
		}
	}
}

/**
 * The TuentiApiRequest interface provides an abstraction for requests to the
 * REST API for Applications. It makes use of the Builder pattern and
 * exposes a fluent interface to developers. Instances are returned from the
 * Tuenti::api method.
 *
 * @see Tuenti::api()
 */
interface TuentiApiRequest {

	/**
	 * Executes a request to the REST API using the HTTP GET method.
	 *
	 * @param mixed[] $parameters (optional) the parameters to pass to the API
	 *        method, indexed by name
	 * @return mixed[string] the response parameters from the invoked API method
	 * @throws TuentiAccessTokenNotFound if the chosen authentication method was
	 *         resource owner credentials and no access token had been
	 *         registered in the SDK
	 * @throws TuentiApiClientError if an error occurred in the processing of
	 *         the request to the REST API resulting in a 4xx Client Error
	 *         status code being returned
	 * @throws TuentiApiServerError if an error occurred in the processing of
	 *         the request to the REST API resulting in a 5xx Server Error
	 *         status code being returned
	 * @throws TuentiCurlError if an error occurred when executing the request
	 *         using cURL
	 * @throws TuentiApiRequestAlreadySent if the request had already been sent
	 *         by a previous call to get, post, put or delete
	 */
	public function get(array $parameters = array());

	/**
	 * Executes a request to the REST API using the HTTP POST method.
	 *
	 * @param mixed[] $parameters (optional) the parameters to pass to the API
	 *        method, indexed by name
	 * @return mixed[string] the response parameters from the invoked API method
	 * @throws TuentiAccessTokenNotFound if the chosen authentication method was
	 *         resource owner credentials and no access token had been
	 *         registered in the SDK
	 * @throws TuentiApiClientError if an error occurred in the processing of
	 *         the request to the REST API resulting in a 4xx Client Error
	 *         status code being returned
	 * @throws TuentiApiServerError if an error occurred in the processing of
	 *         the request to the REST API resulting in a 5xx Server Error
	 *         status code being returned
	 * @throws TuentiCurlError if an error occurred when executing the request
	 *         using cURL
	 * @throws TuentiApiRequestAlreadySent if the request had already been sent
	 *         by a previous call to get, post, put or delete
	 */
	public function post(array $parameters = array());

	/**
	 * Executes a request to the REST API using the HTTP PUT method.
	 *
	 * @return mixed[string] the response parameters from the invoked API method
	 * @throws TuentiAccessTokenNotFound if the chosen authentication method was
	 *         resource owner credentials and no access token had been
	 *         registered in the SDK
	 * @throws TuentiApiClientError if an error occurred in the processing of
	 *         the request to the REST API resulting in a 4xx Client Error
	 *         status code being returned
	 * @throws TuentiApiServerError if an error occurred in the processing of
	 *         the request to the REST API resulting in a 5xx Server Error
	 *         status code being returned
	 * @throws TuentiCurlError if an error occurred when executing the request
	 *         using cURL
	 * @throws TuentiApiRequestAlreadySent if the request had already been sent
	 *         by a previous call to get, post, put or delete
	 */
	public function put();

	/**
	 * Executes a request to the REST API using the HTTP DELETE method.
	 *
	 * @return mixed[string] the response parameters from the invoked API method
	 * @throws TuentiAccessTokenNotFound if the chosen authentication method was
	 *         resource owner credentials and no access token had been
	 *         registered in the SDK
	 * @throws TuentiApiClientError if an error occurred in the processing of
	 *         the request to the REST API resulting in a 4xx Client Error
	 *         status code being returned
	 * @throws TuentiApiServerError if an error occurred in the processing of
	 *         the request to the REST API resulting in a 5xx Server Error
	 *         status code being returned
	 * @throws TuentiCurlError if an error occurred when executing the request
	 *         using cURL
	 * @throws TuentiApiRequestAlreadySent if the request had already been sent
	 *         by a previous call to get, post, put or delete
	 */
	public function delete();

	/**
	 * Sets the authentication method of the request to client identification.
	 *
	 * @return TuentiApiRequest the API request builder
	 */
	public function withClientIdentifier();

	/**
	 * Sets the authentication method of the request to client credentials.
	 *
	 * @return TuentiApiRequest the API request builder
	 */
	public function withClientCredentials();

	/**
	 * Sets the authentication method of the request to resource owner
	 * credentials.
	 *
	 * @return TuentiApiRequest the API request builder
	 */
	public function withAccessToken();
}

/**
 * An interface that provides an extension point for storing persistent data
 * between server requests where the PHP SDK is used.
 */
interface TuentiStorage {

	/**
	 * Returns the value of the given persistent property from the storage.
	 *
	 * @param string $key the key of the property
	 * @return mixed the value of the property
	 */
	public function getPersistentProperty($key);

	/**
	 * Sets the value of the given persistent property in the storage.
	 *
	 * @param string $key the key of the property
	 * @param mixed $value the value of the property
	 */
	public function setPersistentProperty($key, $value);
}

/**
 * An interface that provides an extension point for customizing access to
 * global state.
 */
interface TuentiEnvironment {

	/**
	 * @var string the constant for the the $_SERVER superglobal
	 */
	const VARIABLE_SERVER = 'SERVER';

	/**
	 * @var string the constant for the the $_GET superglobal
	 */
	const VARIABLE_GET = 'GET';

	/**
	 * @var string the constant for the the $_POST superglobal
	 */
	const VARIABLE_POST = 'POST';

	/**
	 * @var string the constant for the the $_FILES superglobal
	 */
	const VARIABLE_FILES = 'FILES';

	/**
	 * @var string the constant for the the $_COOKIE superglobal
	 */
	const VARIABLE_COOKIE = 'COOKIE';

	/**
	 * @var string the constant for the the $_REQUEST superglobal
	 */
	const VARIABLE_REQUEST = 'REQUEST';

	/**
	 * @var string the constant for the the $_ENV superglobal
	 */
	const VARIABLE_ENV = 'ENV';

	/**
	 * Returns the value of a PHP superglobal from the SDK's execution
	 * environment.
	 *
	 * @param string $name the name of the variable for which to return the value
	 * @return mixed the value of the variable
	 */
	public function getEnvironmentVariable($name);

	/**
	 * Returns the current time.
	 *
	 * @return int the current time measured in seconds
	 */
	public function getCurrentTime();
}

/**
 * EXCEPTIONS
 */

/**
 * An abstract base class for SDK exceptions.
 */
abstract class TuentiException extends Exception {

	/**
	 * Creates a new exception.
	 *
	 * @param string $message the description of the exception
	 */
	public function __construct($message = '') {
		parent::__construct($message);
	}
}

/**
 * An exception thrown when attempting to create an instance of the SDK and an
 * invalid parameter has been provided.
 */
class TuentiInvalidParameter extends TuentiException {

	/**
	 * Creates a new exception.
	 *
	 * @param string $parameterName the name of the invalid parameter
	 * @param string $description (optional) the description of the exception
	 */
	public function __construct($parameterName, $description = NULL) {
		parent::__construct('Invalid value for parameter "' . $parameterName
				. '"' . (!empty($description) ? ': ' . $description : ''));
	}
}

/**
 * An exception thrown when attempting to create an instance of the SDK and not
 * all required parameters have been provided.
 */
class TuentiMissingRequiredParameter extends TuentiException {

	/**
	 * Creates a new exception.
	 *
	 * @param string $parameterName the name of the invalid parameter
	 */
	public function __construct($parameterName) {
		parent::__construct('Missing value for parameter "' . $parameterName);
	}
}

/**
 * An exception thrown when the API request path is not syntactically valid
 */
class TuentiInvalidRequestPath extends TuentiException {

	/**
	 * Creates a new exception.
	 *
	 * @param string $path the request path
	 */
	public function __construct($path) {
		parent::__construct('"' . $path . '"is not a valid resource path.'
			. ' Resource paths must start with a slash and must not end with a slash.');
	}
}

/**
 * An exception thrown when the chosen authentication method was resource owner
 * credentials and no access token had been registered in the SDK.
 */
class TuentiAccessTokenNotFound extends TuentiException {

	/**
	 * Creates a new exception.
	 */
	public function __construct() {
		parent::__construct('You are attempting to authenticate with resource'
			. ' owner credentials, but there is no access token available in'
			. ' the SDK - in order to obtain an access token you must go'
			. ' through the authorization process first');
	}
}

/**
 * An exception thrown when the Tuenti Context is not found
 */
class TuentiContextNotFound extends TuentiException {

	/**
	 * Creates a new exception.
	 */
	public function __construct() {
		parent::__construct('No context was sent along the request - either you'
			. ' are in an external site not embedded in Tuenti, or you are not'
			. ' in the initial page of the application.');
	}
}

/**
 * Exception raised when no valid authorization response has been found in the
 * request.
 */
class TuentiAuthorizationCodeNotFound extends TuentiException {

	/**
	 * Creates a new exception.
	 */
	public function __construct() {
		parent::__construct('Couldn\'t find authorization response in the request '
			. '- are you sure this is an authorization response?');
	}
}

/**
 * An abstract base class for exceptions from REST API requests.
 */
abstract class TuentiApiRequestException extends TuentiException {

	/**
	 * @var int the error status code
	 */
	private $statusCode;

	/**
	 * @var string the error type
	 */
	private $type;

	/**
	 * @var string the error description
	 */
	private $description;

	/**
	 * Creates a new exception, based on data returned from the REST API.
	 *
	 * @param int $statusCode the HTTP status code of the error
	 * @param string $type (optional) the error type
	 * @param string $description (optional) the error description
	 */
	public function __construct($statusCode, $type = NULL, $description = NULL) {
		$message = sprintf('Error %d', $statusCode);
		if (!empty($type)) {
			$message .= sprintf(' [%s]', $type);
		}
		if (!empty($description)) {
			$message .= sprintf(' %s', $description);
		}
		parent::__construct($message);

		$this->statusCode = $statusCode;
		$this->type = $type;
		$this->description = $description;
	}

	/**
	 * Returns the HTTP status code of the error.
	 *
	 * @return int the HTTP status code
	 */
	public function getStatusCode() {
		return $this->statusCode;
	}

	/**
	 * Returns the type of the error.
	 *
	 * @return string the error type
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Returns the description of the error.
	 *
	 * @return string the error description
	 */
	public function getDescription() {
		return $this->description;
	}
}

/**
 * An exception thrown if the processing of the request of the REST API resulted
 * in a 4XX Client Error
 */
class TuentiApiClientError extends TuentiApiRequestException {}

/**
 * An exception thrown if the processing of the request of the REST API resulted
 * in a 5XX Server Error
 */
class TuentiApiServerError extends TuentiApiRequestException {}

/**
 * An exception thrown if the processing of the request of the REST API resulted
 * in a 400 Bad Request with the invalid_request error code, indicating that the
 * request is missing a required parameter, includes an unsupported parameter or
 * parameter value, repeats the same parameter, uses more than one method for
 * including an access token, or is otherwise malformed.
 *
 * @see http://tools.ietf.org/html/rfc6750#section-6.2.1
 */
class TuentiApiInvalidRequest extends TuentiApiClientError {

	/**
	 * Creates a new exception, based on data returned from the REST API.
	 *
	 * @param string $description (optional) the error description
	 */
	public function __construct($description = NULL) {
		parent::__construct(400, 'invalid_request', $description);
	}
}

/**
 * An exception thrown if the processing of the request of the REST API resulted
 * in a 401 Unauthorized with the invalid_token error code, indicating that the
 * access token provided is expired, revoked, malformed, or invalid for other
 * reasons.
 *
 * @see http://tools.ietf.org/html/rfc6750#section-6.2.2
 */
class TuentiApiInvalidToken extends TuentiApiClientError {

	/**
	 * Creates a new exception, based on data returned from the REST API.
	 *
	 * @param string $description (optional) the error description
	 */
	public function __construct($description = NULL) {
		parent::__construct(401, 'invalid_token', $description);
	}
}

/**
 * An exception thrown if the processing of the request of the REST API resulted
 * in a 403 Forbidden with the insufficient_scope error code, indicating that
 * the request requires higher privileges than provided by the access token.
 *
 * @see http://tools.ietf.org/html/rfc6750#section-6.2.3
 */
class TuentiApiInsufficientScope extends TuentiApiClientError {

	/**
	 * Creates a new exception, based on data returned from the REST API.
	 *
	 * @param string $description (optional) the error description
	 */
	public function __construct($description = NULL) {
		parent::__construct(403, 'insufficient_scope', $description);
	}
}

/**
 * An exception thrown if the processing of the request of the REST API resulted
 * in a 401 Unauthorized with a WWW-Authenticate header using the Bearer
 * authentication scheme, indicating that an access token is required.
 *
 * @see http://tools.ietf.org/html/rfc2617
 */
class TuentiApiAccessTokenRequired extends TuentiApiClientError {

	/**
	 * Creates a new exception, based on data returned from the REST API.
	 *
	 * @param string $type (optional) the error type
	 * @param string $description (optional) the error description
	 */
	public function __construct($type = NULL, $description = NULL) {
		parent::__construct(401, $type, $description);
	}
}

/**
 * An exception thrown if the processing of the request of the REST API resulted
 * in a 401 Unauthorized with a WWW-Authenticate header using the Basic
 * authentication scheme, indicating that client credentials are required.
 *
 * @see http://tools.ietf.org/html/rfc2617
 */
class TuentiApiClientCredentialsRequired extends TuentiApiClientError {

	/**
	 * Creates a new exception, based on data returned from the REST API.
	 *
	 * @param string $type (optional) the error type
	 * @param string $description (optional) the error description
	 */
	public function __construct($type = NULL, $description = NULL) {
		parent::__construct(401, $type, $description);
	}
}

/**
 * An exception thrown if the processing of the request of the REST API resulted
 * in a 429 Too Many Requests, indicating that the request rate limit for the
 * application was exceeded.
 */
class TuentiApiRateLimitExceeded extends TuentiApiClientError {

	/**
	 * Creates a new exception, based on data returned from the REST API.
	 *
	 * @param string $type (optional) the error type
	 * @param string $description (optional) the error description
	 */
	public function __construct($type = NULL, $description = NULL) {
		parent::__construct(429, $type, $description);
	}
}

/**
 * An exception thrown if the processing of the request of the REST API resulted
 * in a 503 Service Unavailable, indicating that the API is disabled for
 * maintenance.
 */
class TuentiApiUnavailable extends TuentiApiServerError {

	/**
	 * Creates a new exception, based on data returned from the REST API.
	 *
	 * @param string $type (optional) the error type
	 * @param string $description (optional) the error description
	 */
	public function __construct($type = NULL, $description = NULL) {
		parent::__construct(503, $type, $description);
	}
}

/**
 * An exception thrown when the REST API request to exchange an authorization
 * code for an access token failed.
 */
class TuentiAuthorizationCodeExchangeFailed extends TuentiApiClientError {}

/**
 * Exception representing an failed request to the Authorization Dialog.
 */
class TuentiAuthorizationRequestFailed extends TuentiException {

	/**
	 * @var string the error type
	 */
	private $type;

	/**
	 * @var string the error description
	 */
	private $description;

	/**
	 * Creates a new exception, based on data returned from the Authorization
	 * Dialog of the API.
	 *
	 * @param string $type the error type
	 * @param string $description the error description
	 */
	public function __construct($type, $description) {
		parent::__construct('Authorization request failed: ' . $type . ' - ' . $description);

		$this->type = $type;
		$this->description = $description;
	}

	/**
	 * Returns the type of the error.
	 *
	 * @return string the error type
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Returns the description of the error.
	 *
	 * @return string the error description
	 */
	public function getDescription() {
		return $this->description;
	}
}

/**
 * An exception thrown if a session context fails validation.
 */
class TuentiInvalidContext extends TuentiException {}

/**
 * An exception thrown if the curl request fails.
 */
class TuentiCurlError extends TuentiException {

	/**
	 * Creates a new exception, based on the error returned from curl request
	 *
	 * @param int $errorNumber (optional) the curl error number
	 * @param string $errorMessage (optional) the error message
	 */
	public function __construct($errorNumber, $errorMessage) {
		$message = sprintf("Curl Error [%d]: %s", $errorNumber, $errorMessage);
		parent::__construct($message);
	}

}

/**
 * An exception thrown if the request was already sent
 */
class TuentiApiRequestAlreadySent extends TuentiException {

	/**
	 * Creates a new exception.
	 */
	public function __construct() {
		parent::__construct('This instance of TuentiApiRequest has already been'
				. ' used to send a request.');
	}
}

/**
 * INTERNAL CLASSES
 */

/**
 * An implementation of an API request.
 *
 * NOTE: This class is not part of the public interface of the SDK.
 */
class Internal_TuentiApiRequest implements TuentiApiRequest {

	/**
	 * @var int a constant indicating authentication by client identification
	 */
	const AUTHENTICATION_CLIENT_IDENTIFIER = 1;

	/**
	 * @var int a constant indicating authentication by client credentials
	 */
	const AUTHENTICATION_CLIENT_CREDENTIALS = 2;

	/**
	 * @var int a constant indicating authentication by resource owner
	 *      credentials
	 */
	const AUTHENTICATION_RESOURCE_OWNER_CREDENTIALS = 3;

	/**
	 * @var Tuenti the Tuenti instance
	 */
	private $tuenti;

	/**
	 * @var string the absolute URL of the request
	 */
	private $url;

	/**
	 * @var Internal_TuentiCurlClient curl client to do the request
	 */
	private $curlClient;

	/**
	 * @var bool whether the request was already sent or not
	 */
	private $requestAlreadySent = FALSE;

	/**
	 * @var array set of auth methods defined for this request
	 */
	private $authenticationMethods = array();

	/**
	 * @var array list of parameters to be sent on the query
	 */
	private $queryParameters = array();

	/**
	 * @var array list of parameters to be sent as body parameters
	 */
	private $bodyParameters = array();

	/**
	 * @var mixed[CURLOPT_XXX] the options to set for cURL transfer
	 */
	private $curlOptions;

	/**
	 * @var array list of default options to be passed to the curl client
	 */
	private static $defaultCurlOptions = array(
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_USERAGENT => 'tuenti-php-sdk-1.0',
	);

	/**
	 * Creates a new API request for the given path.
	 *
	 * @param Tuenti $tuenti the SDK instance
	 * @param string $url the absolute URL of the request
	 * @param mixed[] $curlOptions (optional) the options to set for cURL transfer
	 * @param Internal_TuentiCurlClient $curlClient (optional) client for HTTP requests
	 */
	public function __construct(Tuenti $tuenti, $url,
			array $curlOptions = array(), $curlClient = NULL) {
		$this->tuenti = $tuenti;
		$this->url = $url;
		if ($curlClient === NULL) {
			$curlClient = new Internal_TuentiCurlClient();
		}
		$this->curlClient = $curlClient;
		$this->curlOptions = $curlOptions + self::$defaultCurlOptions;
		$this->curlOptions[CURLOPT_USERAGENT]
				= self::$defaultCurlOptions[CURLOPT_USERAGENT];
		$this->curlOptions[CURLOPT_HEADERFUNCTION]
				= array($this->curlClient, 'responseHeaderReceived');
	}

	/**
	 * {@inheritdoc}
	 * @param mixed[] $parameters (optional) the parameters to pass to the API
	 *        method, indexed by name
	 * @see TuentiApiRequest::get()
	 */
	public function get(array $parameters = array()) {
		$this->queryParameters = $this->queryParameters + $parameters;
		$customCurlOptions = array(
			CURLOPT_HTTPGET => TRUE
		);
		$this->curlOptions = $this->curlOptions + $customCurlOptions;
		return $this->request('get');
	}

	/**
	 * {@inheritdoc}
	 * @param mixed[] $parameters (optional) the parameters to pass to the API
	 *        method, indexed by name
	 * @see TuentiApiRequest::post()
	 */
	public function post(array $parameters = array()) {
		$this->bodyParameters = array_merge($this->bodyParameters, $parameters);
		$customCurlOptions = array(
			CURLOPT_POST => 1
		);
		$this->curlOptions = $this->curlOptions + $customCurlOptions;
		return $this->request('post');
	}

	/**
	 * {@inheritdoc}
	 * @see TuentiApiRequest::put()
	 */
	public function put() {
		$customCurlOptions = array(
			CURLOPT_PUT => TRUE,
		);
		$this->curlOptions = $this->curlOptions + $customCurlOptions;
		return $this->request('put');
	}

	/**
	 * {@inheritdoc}
	 * @see TuentiApiRequest::delete()
	 */
	public function delete() {
		$customCurlOptions = array(
			CURLOPT_CUSTOMREQUEST => 'DELETE',
		);
		$this->curlOptions = $this->curlOptions + $customCurlOptions;
		return $this->request('delete');
	}

	/**
	 * {@inheritdoc}
	 * @see TuentiApiRequest::withClientIdentifier()
	 */
	public function withClientIdentifier() {
		$this->authenticationMethods[
				self::AUTHENTICATION_CLIENT_IDENTIFIER] = TRUE;
		return $this;
	}

	/**
	 * {@inheritdoc}
	 * @see TuentiApiRequest::withClientCredentials()
	 */
	public function withClientCredentials() {
		$this->authenticationMethods[
				self::AUTHENTICATION_CLIENT_CREDENTIALS] = TRUE;
		return $this;
	}

	/**
	 * {@inheritdoc}
	 * @see TuentiApiRequest::withAccessToken()
	 */
	public function withAccessToken() {
		$this->authenticationMethods[
				self::AUTHENTICATION_RESOURCE_OWNER_CREDENTIALS] = TRUE;
		return $this;
	}

	/**
	 * Makes a request to the REST API, setting the proper authorization method
	 * and handling errors received. It returns the API response as decoded JSON response
	 *
	 * @param string $method the request method (get, post, put or delete)
	 * @return mixed[string] the response parameters from the invoked API method
	 * @throws TuentiAccessTokenNotFound if the chosen authentication method was
	 *         resource owner credentials and no access token had been
	 *         registered in the SDK
	 * @throws TuentiApiClientError if an error occurred in the processing of
	 *         the request to the REST API resulting in a 4xx Client Error
	 *         status code being returned
	 * @throws TuentiApiServerError if an error occurred in the processing of
	 *         the request to the REST API resulting in a 5xx Server Error
	 *         status code being returned
	 * @throws TuentiCurlError if an error occurred when executing the request
	 *         using cURL
	 * @throws TuentiApiRequestAlreadySent if the request had already been sent
	 *         by a previous call to get, post, put or delete
	 */
	private function request($method) {
		if ($this->requestAlreadySent) {
			throw new TuentiApiRequestAlreadySent();
		}

		$this->setAuthenticationMethods($method);
		$this->curlOptions[CURLOPT_URL] = $this->buildRequestUrl();
		if (!empty($this->bodyParameters)) {
			$this->curlOptions[CURLOPT_POSTFIELDS]
					= http_build_query($this->bodyParameters, null, '&');
		}
		$this->curlClient->setOptions($this->curlOptions);
		$rawResponse = $this->curlClient->execute();
		$this->requestAlreadySent = TRUE;

		if ($this->curlClient->hasError()) {
			$error = new TuentiCurlError($this->curlClient->getErrorNumber(),
					$this->curlClient->getErrorMessage());
			$this->curlClient->close();
			throw $error;
		}

		$statusCode = (int)$this->curlClient->getInfo(CURLINFO_HTTP_CODE);
		$responseHeaders = $this->curlClient->getResponseHeaders();
		$this->curlClient->close();

		$response = json_decode($rawResponse, TRUE);
		$this->assertValidResponse($statusCode, $responseHeaders, $response);
		return $response;
	}

	/**
	 * Set the request headers and parameters for the previously chosen 
	 * authentication methods and the given request method.
	 *
	 * @param string $requestMethod the HTTP request method
	 */
	private function setAuthenticationMethods($requestMethod) {
		if (empty($this->authenticationMethods)) {
			$this->authenticationMethods[self::AUTHENTICATION_RESOURCE_OWNER_CREDENTIALS] = TRUE;
		}

		if (isset($this->authenticationMethods[self::AUTHENTICATION_CLIENT_IDENTIFIER])) {
			$clientId = $this->tuenti->getClientId();
			$this->queryParameters['client_id'] = $clientId;
		}

		if (isset($this->authenticationMethods[self::AUTHENTICATION_CLIENT_CREDENTIALS])) {
			$this->curlOptions[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
			$this->curlOptions[CURLOPT_USERPWD] = $this->tuenti->getClientId()
					. ':' . $this->tuenti->getClientSecret();
		}

		if (isset($this->authenticationMethods[self::AUTHENTICATION_RESOURCE_OWNER_CREDENTIALS])) {
			if (!$this->tuenti->hasAccessToken()) {
				throw new TuentiAccessTokenNotFound();
			}
			$accessToken = $this->tuenti->getAccessToken();
			if (isset($this->authenticationMethods[self::AUTHENTICATION_CLIENT_CREDENTIALS])) {
				if ($requestMethod === 'post') {
					$this->bodyParameters['access_token'] = $accessToken;
				} else {
					$this->queryParameters['access_token'] = $accessToken;
				}
			} else {
				$this->addHeader('Authorization: Bearer ' . $accessToken);
			}
		}
	}

	/**
	 * Builds the full request URL, based on the resource URL and the query
	 * parameters set.
	 * 
	 * @return string the request URL
	 */
	private function buildRequestUrl() {
		$parts = parse_url($this->url);
		$query = '';
		if (!empty($this->queryParameters)) {
			$query = '?' . http_build_query($this->queryParameters, null, '&');
		}
		return $parts['scheme'] . '://' . $parts['host'] . $parts['path'] . $query;
	}

	/**
	 * Adds an HTTP header to be sent in the request.
	 *
	 * @param string $header the header to be added
	 */
	private function addHeader($header) {
		if (!isset($this->curlOptions[CURLOPT_HTTPHEADER])) {
			$this->curlOptions[CURLOPT_HTTPHEADER] = array();
		}
		$this->curlOptions[CURLOPT_HTTPHEADER][] = $header;
	}

	/**
	 * Asserts that the given HTTP status code and JSON-decoded response body
	 * from the request represent a valid response.
	 *
	 * @param int $statusCode the HTTP status code of the request
	 * @param mixed[string] $headers the headers received in the response
	 * @param mixed[string] $response the response parameters from the invoked
	 *         API method
	 * @throws TuentiApiClientError if an error occurred in the processing of
	 *         the request to the REST API resulting in a 4xx Client Error
	 *         status code being returned
	 * @throws TuentiApiServerError if an error occurred in the processing of
	 *         the request to the REST API resulting in a 5xx Server Error
	 *         status code being returned
	 */
	private function assertValidResponse($statusCode, $headers, $response) {
		$errorType = isset($response['error'])
				? $response['error'] : '';
		$errorMessage = isset($response['error_description'])
				? $response['error_description'] : NULL;
		if ($statusCode >= 400 && $statusCode < 500) {
			if ($statusCode === 400 && $errorType === 'invalid_request') {
				throw new TuentiApiInvalidRequest($errorMessage);
			} else if ($statusCode === 401) {
				if ($errorType === 'invalid_token') {
					throw new TuentiApiInvalidToken($errorMessage);
				}

				if (isset($headers['WWW-Authenticate'])) {
					$wwwAuthenticateHeader = $headers['WWW-Authenticate'];
					$scheme = strtok($wwwAuthenticateHeader, ' ');
					if ($scheme === 'Basic') {
						throw new TuentiApiClientCredentialsRequired(
								$errorType, $errorMessage);
					} else if ($scheme === 'Bearer') {
						throw new TuentiApiAccessTokenRequired(
								$errorType, $errorMessage);
					}
				}
			} else if ($statusCode === 403 && $errorType === 'insufficient_scope') {
				throw new TuentiApiInsufficientScope($errorMessage);
			} else if ($statusCode === 429) {
				throw new TuentiApiRateLimitExceeded($errorType, $errorMessage);
			}
			throw new TuentiApiClientError($statusCode, $errorType,
						$errorMessage);
		} else if ($statusCode >= 500 && $statusCode < 600) {
			if ($statusCode === 503) {
				throw new TuentiApiUnavailable($errorType, $errorMessage);
			} else {
				throw new TuentiApiServerError($statusCode, $errorType,
						$errorMessage);
			}
		}
	}
}

/**
 * Signed and encoded context data for the user's session on Tuenti.
 *
 * NOTE: This class is not part of the public interface of the SDK.
 */
final class Internal_TuentiContext {

	/**
	 * @var string[] an array of the reserved JWT claims included in the context
	 */
	private static $reservedClaims = array('exp', 'iat', 'aud', 'iss');

	/**
	 * @var string the raw context data
	 */
	private $rawContext;

	/**
	 * @var mixed[string] the decoded and verified context data
	 */
	private $parsedContext;

	/**
	 * @var string the error message from failed validation
	 */
	private $validationError;

	/**
	 * Creates a new context for the given data and audience.
	 *
	 * @param Tuenti $tuenti the SDK instance
	 * @param TuentiEnvironment $environment the environment of the SDK
	 */
	public function __construct(Tuenti $tuenti, TuentiEnvironment $environment) {
		$postGlobal = $environment->getEnvironmentVariable(
				TuentiEnvironment::VARIABLE_POST);
		$this->rawContext = isset($postGlobal['tuenti_context'])
				? $postGlobal['tuenti_context'] : NULL;

		$clientId = $tuenti->getClientId();
		$clientSecret = $tuenti->getClientSecret();
		$requestTime = $environment->getCurrentTime();

		// Validates the context
		$rawContextComponents = explode('.', $this->rawContext);
		$rawContextComponentCount = count($rawContextComponents);
		if ($rawContextComponentCount === 3) {
			list ($encodedHeader, $encodedContext, $encodedSignature)
					= $rawContextComponents;
			if (!$this->isHeaderValid($encodedHeader)) {
				$this->validationError = 'Invalid header';
			}

			$context = json_decode(self::base64UrlDecode($encodedContext), TRUE);
			if ($this->validationError === NULL
					&& !$this->isContextValid($context, $clientId,
							$requestTime)) {
				$this->validationError = 'Invalid payload';
			}

			if ($this->validationError === NULL
					&& !$this->isSignatureValid($encodedHeader, $encodedContext,
							$encodedSignature, $clientSecret)) {
				$this->validationError = 'Invalid signature';
			}

			if ($this->validationError === NULL) {
				$this->parsedContext = $context;
			}
		} else {
			$this->validationError = 'Expected 3 dot-separated components, found '
					. $rawContextComponentCount;
		}
	}

	/**
	 * Returns the custom JWT claims from the context data.
	 *
	 * @return mixed[string] the custom context fields
	 */
	public function getCustomFields() {
		$this->assertValid();
		return array_diff_key($this->parsedContext,
				array_flip(self::$reservedClaims));
	}

	/**
	 * Asserts that the context data is present and valid.
	 *
	 * @throws TuentiContextNotFound if no context data was found
	 * @throws TuentiInvalidContext the provided context data could not be
	 *         successfully decoded or verified
	 */
	private function assertValid() {
		if (empty($this->rawContext)) {
			throw new TuentiContextNotFound();
		}

		if ($this->validationError !== NULL) {
			throw new TuentiInvalidContext($this->validationError);
		}
	}

	/**
	 * Returns whether the given JWS header is valid.
	 *
	 * @param mixed[string] $encodedHeader the encoded JWS header
	 * @return boolean true if the header is valid
	 */
	private function isHeaderValid($encodedHeader) {
		$header = json_decode(self::base64UrlDecode($encodedHeader), TRUE);
		$algorithm = isset($header['alg']) ? $header['alg'] : '';
		$type = isset($header['typ']) ? $header['typ'] : '';
		return $type === 'JWT' && $algorithm === 'HS256';
	}

	/**
	 * Returns whether the given JWS payload is valid.
	 *
	 * @param mixed[string] $context the encoded JWS payload
	 * @param string $clientId the client identifier of the application
	 * @param integer $requestTime the current request time
	 * @return boolean true if the payload is valid
	 */
	private function isContextValid($context, $clientId, $requestTime) {
		$expectedIssuer = 'http://tuenti.com';
		$expirationTime = isset($context['exp']) ? $context['exp'] : 0;
		$audience = isset($context['aud']) ? $context['aud'] : '';
		$issuer = isset($context['iss']) ? $context['iss'] : '';
		return $issuer === $expectedIssuer && $audience === $clientId
				&& $expirationTime > $requestTime;
	}

	/**
	 * Returns whether the given JWS signature is valid.
	 *
	 * @param string $encodedHeader the encoded JWS header
	 * @param string $encodedContext the encoded JWS payload
	 * @param string $encodedSignature the encoded JWS signature
	 * @param string $clientSecret the client secret of the application
	 * @return boolean true if the signature is valid
	 */
	private function isSignatureValid($encodedHeader, $encodedContext,
			$encodedSignature, $clientSecret) {
		$signature = self::base64UrlDecode($encodedSignature);
		$signingInput = $encodedHeader . '.' . $encodedContext;
		$actualSignature = hash_hmac('sha256', $signingInput,
				$clientSecret, TRUE);
		return $actualSignature === $signature;
	}

	/**
	 * Decodes the given input, encoded using URL-safe base64 encoding.
	 *
	 * @param string $input the string to decode
	 * @return string the decoded string
	 */
	private static function base64UrlDecode($input) {
		$input = strtr($input, '-_', '+/');
		return base64_decode($input, TRUE);
	}
}

/**
 * A storage implementation for the SDK that persists data in the PHP session.
 *
 * NOTE: This class is not part of the public interface of the SDK.
 */
final class Internal_TuentiSessionStorage implements TuentiStorage {

	/**
	 * @var string the client identifier of the application
	 */
	private $clientId;

	/**
	 * Creates a new session storage.
	 *
	 * @param string $clientId the client identifier of the application
	 */
	public function __construct($clientId) {
		$this->clientId = $clientId;

		if (!session_id()) {
			session_start();
		}
	}

	/**
	 * Returns the value of the given persistent property from the current
	 * session.
	 *
	 * @param string $key the key of the property
	 * @return mixed the value of the property
	 * @see TuentiStorage::getPersistentProperty()
	 */
	public function getPersistentProperty($key) {
		$fieldName = $this->getSessionFieldName($key);
		return isset($_SESSION[$fieldName]) ? $_SESSION[$fieldName] : NULL;
	}

	/**
	 * Sets the value of the given persistent property in the current session.
	 *
	 * @param string $key the key of the property
	 * @param mixed $value the value of the property
	 * @see TuentiStorage::setPersistentProperty()
	 */
	public function setPersistentProperty($key, $value) {
		$fieldName = $this->getSessionFieldName($key);
		if ($value !== NULL) {
			$_SESSION[$fieldName] = $value;
		} else {
			unset($_SESSION[$fieldName]);
		}
	}

	/**
	 * Returns the name of the field in $_SESSION at which to store the property
	 * with the given key.
	 *
	 * @param string $key the key of the property
	 * @return string the $_SESSION field name
	 */
	private function getSessionFieldName($key) {
		return implode('_', array('tuenti', $this->clientId, $key));
	}
}

/**
 * An environment implementation for the SDK that accesses PHP superglobals
 * directly.
 *
 * NOTE: This class is not part of the public interface of the SDK.
 */
final class Internal_TuentiGlobalEnvironment implements TuentiEnvironment {

	/**
	 * Creates a new global environment.
	 */
	public function __construct() {}

	/**
	 * Returns the value of a PHP superglobal from the SDK's execution
	 * environment.
	 *
	 * @param string $name the name of the variable for which to return the value
	 * @return mixed the value of the variable
	 * @see TuentiEnvironment::getEnvironmentVariable()
	 */
	public function getEnvironmentVariable($name) {
		$value = NULL;
		switch ($name) {
			case TuentiEnvironment::VARIABLE_SERVER:
				$value = $_SERVER;
				break;
			case TuentiEnvironment::VARIABLE_GET:
				$value = $_GET;
				break;
			case TuentiEnvironment::VARIABLE_POST:
				$value = $_POST;
				break;
			case TuentiEnvironment::VARIABLE_FILES:
				$value = $_FILES;
				break;
			case TuentiEnvironment::VARIABLE_COOKIE:
				$value = $_COOKIE;
				break;
			case TuentiEnvironment::VARIABLE_REQUEST:
				$value = $_REQUEST;
				break;
			case TuentiEnvironment::VARIABLE_ENV:
				$value = $_ENV;
				break;
		}
		return $value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getCurrentTime() {
		return time();
	}
}

/**
 * A wrapper for the Client URL Library.
 *
 * NOTE: This class is not part of the public interface of the SDK.
 */
class Internal_TuentiCurlClient {

	/**
	 * @var resource the cURL handle
	 */
	private $handle;

	/**
	 * @var mixed[string] the headers received in the response
	 */
	private $responseHeaders = array();

	/**
	 * Creates a new cURL client.
	 *
	 * @see curl_init
	 */
	public function __construct() {
		$this->handle = $this->initialize();
	}

	/**
	 * Initializes a cURL session and returns a handle for it.
	 *
	 * @return resource the cURL handle for the session
	 */
	protected function initialize() {
		return curl_init();
	}

	/**
	 * Sets the given options for cURL transfer.
	 *
	 * @param mixed[CURLOPT_XXX] $curlOptions the options to set
	 * @see curl_setopt
	 */
	public function setOptions($curlOptions) {
		foreach ($curlOptions as $name => $value) {
			curl_setopt($this->handle, $name, $value);
		}
	}

	/**
	 * Executes a cURL request with the previously set options.
	 *
	 * @return string the response of the request
	 * @see curl_exec
	 */
	public function execute() {
		return curl_exec($this->handle);
	}

	/**
	 * Returns the response headers received for the current cURL handle.
	 *
	 * @return mixed[string] the headers received in the response
	 */
	public function getResponseHeaders() {
		return $this->responseHeaders;
	}

	/**
	 * A callback to be invoked by cURL as response headers are received.
	 * Intended for use with CURLOPT_HEADERFUNCTION, if needed.
	 *
	 * @param resource $handle the cURL handle
	 * @param string $header a response header
	 * @return int the number of bytes received
	 * @see CURLOPT_HEADERFUNCTION
	 */
	public function responseHeaderReceived($handle, $header) {
		if ($handle === $this->handle) {
			$components = explode(':', $header, 2);
			if (count($components) === 2) {
				list($name, $value) = $components;
				$this->responseHeaders[$name] = trim($value);
			}
		}
		return strlen($header);
	}

	/**
	 * Returns whether an error occurred when the last request was processed.
	 *
	 * @return boolean true if an error occurred
	 */
	public function hasError() {
		$errorNumber = $this->getErrorNumber();
		return $errorNumber !== 0;
	}

	/**
	 * Returns the code of the error that occurred when the last request was
	 * processed, if any.
	 *
	 * @return int the error number, or 0 if the last request was successful
	 * @see curl_errno
	 */
	public function getErrorNumber() {
		return curl_errno($this->handle);
	}

	/**
	 * Returns the description of the error that occurred when the last request
	 * was processed, if any.
	 *
	 * @return string the description of the error, or NULL if the last request
	 *         was successful
	 * @see curl_error
	 */
	public function getErrorMessage() {
		$errorMessage = NULL;
		if ($this->hasError()) {
			$errorMessage = curl_error($this->handle);
		}
		return $errorMessage;
	}

	/**
	 * Returns the requested piece of information about the last request.
	 *
	 * @param CURLINFO_XXX $option the type of information to return
	 * @return mixed the requested information
	 * @see curl_getinfo
	 */
	public function getInfo($option) {
		return curl_getinfo($this->handle, $option);
	}

	/**
	 * Closes the curl handle.
	 */
	public function close() {
		curl_close($this->handle);
	}
}
