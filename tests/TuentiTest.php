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

/**
 * Unit tests for the Tuenti class.
 *
 * @see Tuenti
 */
class TuentiTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var string the base URL of the REST API for tests
	 */
	const REST_API_BASE_URL = 'http://tuenti.test';

	/**
	 * @var string the client identifier of the test application
	 */
	const CLIENT_ID = 'my-app';

	/**
	 * @var string the client secret of the test application
	 */
	const CLIENT_SECRET = 'my-secret';

	/**
	 * @var int the current request time
	 */
	const TIME = 1000000001;

	/**
	 * @test
	 * @expectedException TuentiMissingRequiredParameter
	 * @dataProvider missingMandatoryConstructorParameterDataProvider
	 */
	public function shouldFailOnConstructionDueToMissingMandatoryParameters($parameters) {
		new Tuenti($parameters);
	}

	/**
	 * A data provider for shouldFailOnConstructionDueToMissingMandatoryParameters.
	 *
	 * @return array(mixed[])
	 */
	public static function missingMandatoryConstructorParameterDataProvider() {
		return array(
			array(array()),
			array(array(
				'restApiBaseUrl' => 'https://example.com/apibase',
				'clientId' => 'client_id',
			)),
			array(array(
				'clientId' => 'client_id',
				'clientSecret' => 'client_secret'
			)),
			array(array(
				'restApiBaseUrl' => 'https://example.com/apibase',
				'clientSecret' => 'client_secret'
			)),
		);
	}

	/**
	 * @test
	 * @dataProvider constructorParameterProvider
	 */
	public function shouldValidateParametersOnConstruction($parameterName, $value, $shouldPass, $assertMessage) {
		$parameters = $this->getDefaultParameters();
		$parameters[$parameterName] = $value;
		try {
			new Tuenti($parameters);
			if (!$shouldPass) {
				$this->fail($assertMessage);
			}
		} catch (TuentiInvalidParameter $e) {
			if ($shouldPass) {
				$this->fail($assertMessage);
			}
		}
	}

	/**
	 * Provider for shouldValidateParametersOnConstruction
	 * @return array
	 */
	public function constructorParameterProvider() {
		return array(
			array('restApiBaseUrl', '', FALSE, 'restApiBaseUrl must not be empty'),
			array('restApiBaseUrl', 'rubbish', FALSE, 'restApiBaseUrl be a valid URL'),
			array('restApiBaseUrl', 'ftp://example.com', FALSE, 'restApiBaseUrl must be an http address'),
			array('restApiBaseUrl', 'http://example.com', TRUE, 'restApiBaseUrl can be an http address'),
			array('restApiBaseUrl', 'https://example.com', TRUE, 'restApiBaseUrl can be an https address'),
			array('restApiBaseUrl', 'http://', FALSE, 'restApiBaseUrl must have a server'),
			array('restApiBaseUrl', 'https://', FALSE, 'restApiBaseUrl must have a server'),
			array('restApiBaseUrl', 'http://example.com/test', TRUE, 'restApiBaseUrl can have an URL query'),
			array('restApiBaseUrl', 'https://example.com/test', TRUE, 'restApiBaseUrl can have an URL query'),
			array('restApiBaseUrl', 'http://example.com/test?test=value&othervariable=anothervalue&urlencoded=%22%23%24',
				FALSE, 'restApiBaseUrl must not have parameters'),
			array('restApiBaseUrl', 'https://example.com/test?test=value&othervariable=anothervalue&urlencoded=%22%23%24',
				FALSE, 'restApiBaseUrl must not have parameters'),
			array('restApiBaseUrl', 'http://example.com:8080/test', FALSE, 'restApiBaseUrl must not have port'),
			array('restApiBaseUrl', 'https://example.com:8080/test', FALSE, 'restApiBaseUrl must not have port'),

			array('clientId', 12345, FALSE, 'clientId must be a string'),
			array('clientSecret', 12345, FALSE, 'clientId must be a string'),

			array('curlOptions', 'rubbish', FALSE, 'curlOptions only accepts arrays'),
			array('curlOptions', array(), TRUE, 'curlOptions can be empty'),
			array('curlOptions', array(CURLOPT_CONNECTTIMEOUT => 10),
				TRUE, 'curlOptions must accept valid CURLOPT parameters'),
			array('curlOptions', array('asdf' => 10), FALSE, 'curlOptions should reject invalid CURLOPT parameters'),

			array('storage', 'rubbish', FALSE, 'storage only accepts instances of TuentiStorage'),
			array('storage', new Internal_TuentiSessionStorage(self::CLIENT_ID),
				TRUE, 'storage should accept instances of TuentiStorage'),

			array('environment', 'rubbish', FALSE, 'environment only accepts instances of TuentiEnvironment'),
			array('environment', new Internal_TuentiGlobalEnvironment(),
				TRUE, 'environment should accept instances of TuentiEnvironment'),

			array('unexpectedParameter', 'rubbish', FALSE, 'Unexpected parameters should trigger an exception'),
		);
	}

	/**
	 * @test
	 */
	public function shouldSetFieldsFromConstructorParameters() {
		$parameters = $this->getDefaultParameters();
		unset($parameters['storage']);
		$tuenti = new Tuenti($parameters);
		$this->assertEquals(self::CLIENT_ID, $tuenti->getClientId());
		$this->assertEquals(self::CLIENT_SECRET, $tuenti->getClientSecret());
	}

	/**
	 * @test
	 */
	public function shouldPersistAndRestoreState() {
		$token = 'thisisatoken';

		$storageMock = $this->getMock('TuentiStorage');
		$storageMock->expects($this->exactly(2))
				->method('getPersistentProperty')
				->with('access_token')
				->will($this->onConsecutiveCalls(
						NULL, $token));
		$storageMock->expects($this->once())
				->method('setPersistentProperty')
				->with('access_token', $token);

		$parameters = array('storage' => $storageMock) + $this->getDefaultParameters();
		$tuenti = new Tuenti($parameters);
		$this->assertFalse($tuenti->hasAccessToken(), 'No access token was expected');

		$tuenti->setAccessToken($token);
		$this->assertTrue($tuenti->hasAccessToken(), 'Access token was expected');
		$this->assertEquals($token, $tuenti->getAccessToken());

		$tuenti = new Tuenti($parameters);
		$this->assertEquals($token, $tuenti->getAccessToken(),
				'Access token hasn\'t been restored from persistent storage');
	}

	/**
	 * @test
	 * @expectedException TuentiContextNotFound
	 */
	public function shouldThrowContextNotFound() {
		$tuenti = new Tuenti($this->getDefaultParameters());
		$tuenti->getContextFields();
	}

	/**
	 * @test
	 */
	public function shouldReturnContextFields() {
		$rawContext = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJodHRwOlwvXC90dWVudGkuY29tIiwiYXVkIjoibXktYXBwIiwiaWF0IjoxMzY0ODE2MjMwLCJleHAiOjEzNjQ4MTk4MzAsInR1ZW50aV91c2VyIjp7ImxvY2FsZSI6ImVzX0VTIn0sInR1ZW50aV9wYWdlIjp7ImlkIjoiMTIzNDUiLCJmb2xsb3dlciI6dHJ1ZX19.4AbVt-c9IW-s7x31TOa5k3n1iwMI9Ao1OUF5M7dX2fU';
		$environmentMock = $this->mockEnvironmentWithContext($rawContext);
		$tuenti = new Tuenti(array('environment' => $environmentMock)
				+ $this->getDefaultParameters());

		$expectedCustomFields = array(
			'tuenti_user' => array(
				'locale' => 'es_ES'
			),
			'tuenti_page' => array(
				'id' => '12345',
				'follower' => TRUE,
			)
		);
		$this->assertEquals($expectedCustomFields, $tuenti->getContextFields());
		$this->assertEquals($expectedCustomFields['tuenti_user'],
				$tuenti->getContextField('tuenti_user'));
		$this->assertEquals($expectedCustomFields['tuenti_page'],
				$tuenti->getContextField('tuenti_page'));
		$this->assertNull($tuenti->getContextField('foo'));
	}

	/**
	 * @test
	 */
	public function shouldSetAccessTokenFromValidAuthorizationResponse() {
		$authorizationCode = '1231241234';
		$token = 'thisisatoken';
		$environmentVariables = array(
			TuentiEnvironment::VARIABLE_GET => array(
				'code' => $authorizationCode,
			),
			TuentiEnvironment::VARIABLE_SERVER => array(
				'HTTP_HOST' => 'hostname',
				'HTTP_PORT' => '443',
				'REQUEST_URI' => '/request/uri'
			)
		);

		$tuentiApiRequestMock = $this->getMock('TuentiApiRequest');
		$tuentiApiRequestMock->expects($this->once())
			->method('withClientCredentials')
			->will($this->returnValue($tuentiApiRequestMock));
		$tuentiApiRequestMock->expects($this->once())
			->method('post')
			->with(array(
				'client_id' => self::CLIENT_ID,
				'code' => $authorizationCode,
				'grant_type' => 'authorization_code',
				'redirect_uri' => 'https://hostname/request/uri'
			))
			->will($this->returnValue(array(
				'access_token' => $token,
			)));

		$initParameters = $this->getDefaultParameters();
		$initParameters['environment'] = $this->mockEnvironment($environmentVariables);
		$tuenti = $this->createTuentiWithMockApiRequest($initParameters, $tuentiApiRequestMock);
		$this->assertNull($tuenti->getAccessToken(), 'Access token is already set');
		$this->assertFalse($tuenti->hasAccessToken(), 'Access token is already present');

		$tuenti->setAccessTokenFromAuthorizationResponse();
		$this->assertEquals($token, $tuenti->getAccessToken(), 'Access token doesn\'t have expected value');
		$this->assertTrue($tuenti->hasAccessToken(), 'Access token is not present');
	}

	/**
	 * @test
	 */
	public function shouldSetAccessTokenFromValidAuthorizationCode() {
		$authorizationCode = '1231241234';
		$redirectUri = 'https://hostname/request/uri';
		$token = 'thisisatoken';

		$tuentiApiRequestMock = $this->getMock('TuentiApiRequest');
		$tuentiApiRequestMock->expects($this->once())
			->method('withClientCredentials')
			->will($this->returnValue($tuentiApiRequestMock));
		$tuentiApiRequestMock->expects($this->once())
			->method('post')
			->with(array(
				'client_id' => self::CLIENT_ID,
				'code' => $authorizationCode,
				'grant_type' => 'authorization_code',
				'redirect_uri' => $redirectUri
			))
			->will($this->returnValue(array(
				'access_token' => $token,
			)));

		$tuenti = $this->createTuentiWithMockApiRequest($this->getDefaultParameters(), $tuentiApiRequestMock);
		$this->assertNull($tuenti->getAccessToken(), 'Access token is already set');
		$this->assertFalse($tuenti->hasAccessToken(), 'Access token is already present');

		$tuenti->setAccessTokenFromAuthorizationCode($authorizationCode, $redirectUri);
		$this->assertEquals($token, $tuenti->getAccessToken(), 'Access token doesn\'t have expected value');
		$this->assertTrue($tuenti->hasAccessToken(), 'Access token is not present');
	}

	/**
	 * @test
	 */
	public function shouldFailSettingAccessTokenFromAuthorizationCodeOnApiError() {
		$code = '1231241234';
		$hostname = 'hostname:8080';
		$requestUriBasePath = '/request/uri';
		$requestUriQueryString = '?parameter1=value1&parameter2=value2';
		$requestUri = $requestUriBasePath . $requestUriQueryString;
		$environmentVariables = array(
			TuentiEnvironment::VARIABLE_GET => array(
				'code' => $code,
			),
			TuentiEnvironment::VARIABLE_SERVER => array(
				'HTTP_HOST' => $hostname,
				'REQUEST_URI' => $requestUri
			)
		);

		$statusCode = 403;
		$errorType = 'error_type';
		$errorDescription = 'This is the error description';

		$postParameters = array(
			'grant_type' => 'authorization_code',
			'code' => $code,
			'client_id' => self::CLIENT_ID,
			'redirect_uri' => 'https://' . $hostname . $requestUriBasePath
		);

		$tuentiApiRequestMock = $this->getMock('TuentiApiRequest');
		$tuentiApiRequestMock->expects($this->once())
			->method('withClientCredentials')
			->will($this->returnValue($tuentiApiRequestMock));
		$tuentiApiRequestMock->expects($this->once())
			->method('post')
			->with($postParameters)
			->will($this->throwException(new TuentiApiClientError($statusCode, $errorType, $errorDescription)));

		$initParameters = $this->getDefaultParameters();
		$initParameters['environment'] = $this->mockEnvironment($environmentVariables);
		$tuenti = $this->createTuentiWithMockApiRequest($initParameters, $tuentiApiRequestMock);

		try {
			$tuenti->setAccessTokenFromAuthorizationResponse();
			$this->fail('TuentiAuthorizationCodeExchangeFailed should have been thrown.');
		} catch (TuentiAuthorizationCodeExchangeFailed $e) {
			$this->assertEquals($statusCode, $e->getStatusCode());
			$this->assertEquals($errorType, $e->getType());
			$this->assertEquals($errorDescription, $e->getDescription());
		}
	}

	/**
	 * @test
	 */
	public function shouldFailSettingAccessTokenFromNonExistingAuthorizationCode() {
		$environmentVariables = array(
			TuentiEnvironment::VARIABLE_GET => array()
		);

		$initParameters = $this->getDefaultParameters();
		$initParameters['environment'] = $this->mockEnvironment($environmentVariables);
		$tuenti = new Tuenti($initParameters);

		try {
			$tuenti->setAccessTokenFromAuthorizationResponse();
			$this->fail('TuentiAuthorizationCodeNotFound not thrown');
		} catch (TuentiAuthorizationCodeNotFound $e) {
			// Expected
		}
	}

	/**
	 * @test
	 */
	public function shouldFailSettingAccessTokenFromErroneousAuthorizationResponse() {
		$errorType = 'error_type';
		$errorDescription = 'Error Description';
		$environmentVariables = array(
			TuentiEnvironment::VARIABLE_GET => array(
				'error' => $errorType,
				'error_description' => $errorDescription
			)
		);

		$initParameters = $this->getDefaultParameters();
		$initParameters['environment'] = $this->mockEnvironment($environmentVariables);
		$tuenti = new Tuenti($initParameters);

		try {
			$tuenti->setAccessTokenFromAuthorizationResponse();
			$this->fail('TuentiAuthorizationCodeNotFound not thrown');
		} catch (TuentiAuthorizationRequestFailed $e) {
			$this->assertEquals($errorType, $e->getType());
			$this->assertEquals($errorDescription, $e->getDescription());
		}
	}

	/**
	 * @dataProvider apiRequestPathProvider
	 * @test
	 */
	public function apiShouldReceiveAValidPath($requestPath, $shouldPass, $assertMessage) {
		$tuenti = new Tuenti($this->getDefaultParameters());
		try {
			$tuenti->api($requestPath);
			if (!$shouldPass)  {
				$this->fail($assertMessage);
			}
		} catch (TuentiInvalidRequestPath $e) {
			if ($shouldPass) {
				$this->fail($assertMessage);
			}
		}
	}

	/**
	 * Provider for apiShouldReceiveAValidPath
	 * @return array
	 */
	public function apiRequestPathProvider() {
		return array(
			array('', FALSE, 'Request path must not be empty'),
			array('foo/bar', FALSE, 'Request path must start with a slash'),
			array('/foo/bar/', FALSE, 'Request path must not end with a slash'),
			array('/foo/current/bar/current/', FALSE, 'Request path must not end with a slash'),
			array('/foo-bar/current/', FALSE, 'Request path must not end with a slash'),
			array('/foo_bar/current/bar-foo', TRUE, 'Request path should be valid'),
			array('/foo/current/bar/current', TRUE, 'Request path should be valid'),
			array('/foo/1234/bar/5678/ff', TRUE, 'Request path should be valid'),
		);
	}

	/**
	 * @test
	 */
	public function apiShouldReturnARequestObject() {
		$tuenti = new Tuenti($this->getDefaultParameters());
		$this->assertTrue($tuenti->api('/foo/bar') instanceof TuentiApiRequest, 'It should return a TuentiApiRequest object');
	}

	private function getDefaultParameters() {
		return array(
			'restApiBaseUrl' => self::REST_API_BASE_URL,
			'clientId' => self::CLIENT_ID,
			'clientSecret' => self::CLIENT_SECRET,
			'storage' => new Internal_TuentiTransientStorage()
		);
	}

	private function createTuentiWithMockApiRequest(array $parameters = array(),
			TuentiApiRequest $tuentiApiRequestMock = NULL) {
		$parameters += $this->getDefaultParameters();
		return new TuentiStub($parameters, $tuentiApiRequestMock);
	}

	/**
	 * @param array $environmentVariables environment variable values indexed by
	 *        environment variable name
	 * @return PHPUnit_Framework_MockObject_MockObject
	 */
	private function mockEnvironment(array $environmentVariables) {
		$environmentMock = $this->getMock('TuentiEnvironment');
		$environmentMock->expects($this->any())
			->method('getEnvironmentVariable')
			->will($this->returnCallback(function($variableName) use ($environmentVariables) {
				return $environmentVariables[$variableName];
			}));
		return $environmentMock;
	}

	private function mockEnvironmentWithContext($rawContext) {
		$environmentMock = $this->getMock('TuentiEnvironment');
		$environmentMock->expects($this->any())
				->method('getCurrentTime')
				->with()
				->will($this->returnValue(self::TIME));
		$environmentMock->expects($this->any())
				->method('getEnvironmentVariable')
				->with(TuentiEnvironment::VARIABLE_POST)
				->will($this->returnValue(array(
						'tuenti_context' => $rawContext
				)));
		return $environmentMock;
	}

}

/**
 * A Tuenti sub-class that enables injecting a mock of the
 * Internal_TuentiApiRequest class for testing API calls.
 */
class TuentiStub extends Tuenti {

	/**
	 * @var PHPUnit_Framework_MockObject_MockObject the API request mock
	 */
	private $tuentiApiRequestMock;

	public function __construct($parameters, $tuentiApiRequestMock = NULL) {
		parent::__construct($parameters);
		$this->tuentiApiRequestMock = $tuentiApiRequestMock;
	}

	protected function createTuentiApiRequest(Tuenti $tuenti, $path, $curlClient = NULL) {
		return $this->tuentiApiRequestMock === NULL
				? parent::createTuentiApiRequest($tuenti, $path)
				: $this->tuentiApiRequestMock;
	}
}

/**
 * A transient storage implementation for the SDK.
 */
class Internal_TuentiTransientStorage implements TuentiStorage {

	private $properties = array();

	public function __construct() {}

	public function getPersistentProperty($key) {
		return isset($this->properties[$key]) ? $this->properties[$key] : NULL;
	}

	public function setPersistentProperty($key, $value) {
		$this->properties[$key] = $value;
	}
}
