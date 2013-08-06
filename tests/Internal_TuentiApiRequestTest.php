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
 * Unit tests for the Internal_TuentiApiRequest class.
 *
 * @see Internal_TuentiApiRequest
 */
class Internal_TuentiApiRequestTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var string the base request URL to use in requests
	 */
	const BASE_REQUEST_URL = 'https://tuenti.test/foo/bar';

	/**
	 * @var string the access token to use in requests
	 */
	const ACCESS_TOKEN = 'Mwn6qi3v0DJADs7Njw1bqG8OnwiYwcFRTks';

	/**
	 * @var string the client identifier to use in requests
	 */
	const CLIENT_ID = 'KlLsu4GGWqMj0n6w';

	/**
	 * @var string the client secret to use in requests
	 */
	const CLIENT_SECRET = 'VYA7e1CVZVBzEhodskoXeBlwjvuc';

	/**
	 * @var string the expected response for successful requests
	 */
	const SUCCESSFUL_RESPONSE = '{"total": 100, "items":[{"id":1,"title":"Foo"}]}';

	/**
	 * @test
	 */
	public function shouldDoAGetRequest() {
		$tuenti = $this->getMockForTuenti('accessToken');
		$curlOptions = array(
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer ' . self::ACCESS_TOKEN
			),
			CURLOPT_HTTPGET => TRUE
		);
		$curlClientMock = $this->getCurlClientMockForRequest(
				self::SUCCESSFUL_RESPONSE, 200, $curlOptions, 'state=acKhf');

		$apiRequest = new Internal_TuentiApiRequest($tuenti, self::BASE_REQUEST_URL, array(), $curlClientMock);
		$result = $apiRequest->get(array(
			'state' => 'acKhf',
		));
		$this->assertEquals(json_decode(self::SUCCESSFUL_RESPONSE, TRUE), $result, 'Wrong result obtained');
	}

	/**
	 * @test
	 */
	public function shouldDoAPostRequest() {
		$tuenti = $this->getMockForTuenti('accessToken');
		$curlOptions = array(
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer ' . self::ACCESS_TOKEN
			),
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => 'state=09231'
		);
		$curlClientMock = $this->getCurlClientMockForRequest(
				self::SUCCESSFUL_RESPONSE, 200, $curlOptions);

		$apiRequest = new Internal_TuentiApiRequest($tuenti, self::BASE_REQUEST_URL, array(), $curlClientMock);
		$result = $apiRequest->post(array(
			'state' => '09231',
		));
		$this->assertEquals(json_decode(self::SUCCESSFUL_RESPONSE, TRUE), $result, 'Wrong result obtained');
	}

	/**
	 * @test
	 */
	public function shouldDoAPutRequest() {
		$tuenti = $this->getMockForTuenti('accessToken');
		$curlClientMock = $this->getCurlClientMockForRequest('', 200, array(
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer ' . self::ACCESS_TOKEN
			),
			CURLOPT_PUT => TRUE,
			CURLOPT_RETURNTRANSFER => TRUE
		));

		$apiRequest = new Internal_TuentiApiRequest($tuenti, self::BASE_REQUEST_URL, array(), $curlClientMock);
		$apiRequest->put();
	}


	/**
	 * @test
	 */
	public function shouldDoADeleteRequest() {
		$tuenti = $this->getMockForTuenti('accessToken');
		$curlClientMock = $this->getCurlClientMockForRequest('', 200, array(
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer ' . self::ACCESS_TOKEN
			),
			CURLOPT_CUSTOMREQUEST => 'DELETE',
		));

		$apiRequest = new Internal_TuentiApiRequest($tuenti, self::BASE_REQUEST_URL, array(), $curlClientMock);
		$apiRequest->delete();
	}

	/**
	 * @test
	 */
	public function shouldSendClientIdentifier() {
		$tuenti = $this->getMockForTuenti('clientIdentifier');
		$curlOptions = array(
				CURLOPT_POST => 1,
		);
		$curlClientMock = $this->getCurlClientMockForRequest('', 200, $curlOptions,
				'client_id=' . self::CLIENT_ID);

		$apiRequest = new Internal_TuentiApiRequest($tuenti, self::BASE_REQUEST_URL, array(), $curlClientMock);
		$apiRequest->withClientIdentifier()->post();
	}

	/**
	 * @test
	 */
	public function shouldSendClientCredentials() {
		$tuenti = $this->getMockForTuenti('clientCredentials');
		$curlClientMock = $this->getCurlClientMockForRequest(1, 200, array(
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
			CURLOPT_USERPWD => self::CLIENT_ID . ':' . self::CLIENT_SECRET,
			CURLOPT_HTTPGET => TRUE
		));

		$apiRequest = new Internal_TuentiApiRequest($tuenti, self::BASE_REQUEST_URL, array(), $curlClientMock);
		$apiRequest->withClientCredentials()->get();
	}

	/**
	 * @test
	 */
	public function shouldSendAccessToken() {
		$tuenti = $this->getMockForTuenti('accessToken');
		$curlClientMock = $this->getCurlClientMockForRequest('', 200, array(
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer ' . self::ACCESS_TOKEN
			),
			CURLOPT_POST => 1
		));

		$apiRequest = new Internal_TuentiApiRequest($tuenti, self::BASE_REQUEST_URL, array(), $curlClientMock);
		$apiRequest->withAccessToken()->post();
	}

	/**
	 * @test
	 */
	public function shouldUseAccessTokenAsDefaultAuthentication() {
		$tuenti = $this->getMockForTuenti('accessToken');
		$curlClientMock = $this->getCurlClientMockForRequest('', 200, array(
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer ' . self::ACCESS_TOKEN
			),
			CURLOPT_POST => 1
		));

		$apiRequest = new Internal_TuentiApiRequest($tuenti, self::BASE_REQUEST_URL, array(), $curlClientMock);
		$apiRequest->post();
	}

	/**
	 * @test
	 * @dataProvider requestMethodDataProvider
	 * @param string $requestMethod the HTTP request method
	 * @expectedException TuentiAccessTokenNotFound
	 */
	public function shouldFailIfAccessTokenNotFound($requestMethod) {
		$tuenti = $this->getMockBuilder('Tuenti')->disableOriginalConstructor()->getMock();
		$tuenti->expects($this->once())
			->method('hasAccessToken')
			->will($this->returnValue(FALSE));

		$apiRequest = new Internal_TuentiApiRequest($tuenti, '/foo/bar', array());
		$apiRequest->$requestMethod();
	}

	/**
	 * A data provider of HTTP request methods.
	 */
	public static function requestMethodDataProvider() {
		return array(
			array('get'),
			array('post'),
			array('put'),
			array('delete'),
		);
	}

	/**
	 * @test
	 */
	public function shouldDoAGetRequestWithClientCredentialsAndAccessToken() {
		$authMethods = array('accessToken', 'clientCredentials', 'clientIdentifier');
		$tuenti = $this->getMockForTuenti($authMethods);

		$curlOptions = array(
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
			CURLOPT_USERPWD => self::CLIENT_ID . ':' . self::CLIENT_SECRET,
			CURLOPT_HTTPGET => TRUE,
		);
		$queryString = 'client_id=' . self::CLIENT_ID
						. '&access_token=' . self::ACCESS_TOKEN;
		$curlClientMock = $this->getCurlClientMockForRequest('', 200,
				$curlOptions, $queryString);

		$apiRequest = new Internal_TuentiApiRequest($tuenti, self::BASE_REQUEST_URL, array(), $curlClientMock);
		$apiRequest->withClientCredentials()->withClientIdentifier()->withAccessToken()->get();
	}

	/**
	 * @test
	 */
	public function shouldDoAPostRequestWithClientCredentialsAndAccessToken() {
		$authMethods = array('accessToken', 'clientCredentials', 'clientIdentifier');
		$tuenti = $this->getMockForTuenti($authMethods);
		$curlOptions = array(
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
			CURLOPT_USERPWD => self::CLIENT_ID . ':' . self::CLIENT_SECRET,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => 'access_token=' . self::ACCESS_TOKEN
		);
		$curlClientMock = $this->getCurlClientMockForRequest('', 200,
				$curlOptions, 'client_id=' . self::CLIENT_ID);

		$apiRequest = new Internal_TuentiApiRequest($tuenti, self::BASE_REQUEST_URL, array(), $curlClientMock);
		$apiRequest->withClientCredentials()->withClientIdentifier()->withAccessToken()->post();
	}

	/**
	 * @test
	 */
	public function shouldAllowCustomCurlOptions() {
		$tuenti = $this->getMockForTuenti('accessToken');
		$curlClientMock = $this->getCurlClientMockForRequest('', 200, array(
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer ' . self::ACCESS_TOKEN
			),
			CURLOPT_POST => 0,
			CURLOPT_AUTOREFERER => TRUE
		));

		$customCurlOptions = array(
			CURLOPT_POST => 0,
			CURLOPT_AUTOREFERER => TRUE
		);

		$apiRequest = new Internal_TuentiApiRequest($tuenti, self::BASE_REQUEST_URL, $customCurlOptions, $curlClientMock);
		$apiRequest->post();
	}

	/**
	 * @test
	 * @expectedException TuentiApiRequestAlreadySent
	 */
	public function shouldThrowARequestAlreadySent() {
		$tuenti = $this->getMockForTuenti('accessToken');
		$curlClientMock = $this->getCurlClientMockForRequest('', 200, array(
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer ' . self::ACCESS_TOKEN
			),
			CURLOPT_POST => 1,
		));

		$apiRequest = new Internal_TuentiApiRequest($tuenti, self::BASE_REQUEST_URL, array(), $curlClientMock);

		$apiRequest->post();
		$apiRequest->post();
	}

	/**
	 * @test
	 * @dataProvider apiErrorDataProvider
	 */
	public function shouldThrowApiClientError($statusCode, $error,
			$errorDescription, $exceptionClassName) {
		$response = json_encode(array(
			'error' => $error,
			'error_description' => $errorDescription
		));

		$tuenti = $this->getMockForTuenti('accessToken');
		$curlOptions = array(
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer ' . self::ACCESS_TOKEN
			),
			CURLOPT_POST => 1
		);
		$curlClientMock = $this->getCurlClientMockForRequest($response,
				(string)$statusCode, $curlOptions);

		$apiRequest = new Internal_TuentiApiRequest($tuenti, self::BASE_REQUEST_URL, array(), $curlClientMock);

		try {
			$apiRequest->post();
			$this->fail('Expected ' . $exceptionClassName . ' was not thrown');
		} catch (TuentiApiRequestException $e) {
			$this->assertEquals($exceptionClassName, get_class($e));
			$this->assertEquals($error, $e->getType());
			$this->assertEquals($errorDescription, $e->getDescription());
		}
	}

	/**
	 * A data provider of API errors. 
	 */
	public static function apiErrorDataProvider() {
		return array(
			array(400, 'error_foo', 'Client Foo', 'TuentiApiClientError'),
			array(499, 'error_bar', 'Client Bar', 'TuentiApiClientError'),
			array(400, 'invalid_request', 'Invalid request', 'TuentiApiInvalidRequest'),
			array(401, 'invalid_token', 'Invalid access token', 'TuentiApiInvalidToken'),
			array(403, 'insufficient_scope', 'Insufficient access scopes', 'TuentiApiInsufficientScope'),
			array(429, 'error_foo', 'Rate limit exceeded', 'TuentiApiRateLimitExceeded'),
			array(500, 'error_foo', 'Server Foo', 'TuentiApiServerError'),
			array(599, 'error_bar', 'Server Bar', 'TuentiApiServerError'),
			array(503, 'error_foo', 'Service unavailable', 'TuentiApiUnavailable'),
		);
	}

	/**
	 * @test
	 * @dataProvider wwwAuthenticateHeaderDataProvider
	 */
	public function shouldThrowAuthenticationRequired($responseHeaders,
			$exceptionClassName) {
		$tuenti = $this->getMockForTuenti('accessToken');
		$curlOptions = array(
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer ' . self::ACCESS_TOKEN
			),
			CURLOPT_POST => 1
		);
		$curlClientMock = $this->getCurlClientMockForRequest(NULL,
				(string)401, $curlOptions, NULL, $responseHeaders);

		$apiRequest = new Internal_TuentiApiRequest($tuenti, self::BASE_REQUEST_URL, array(), $curlClientMock);

		try {
			$apiRequest->post();
			$this->fail('Expected ' . $exceptionClassName . ' was not thrown');
		} catch (TuentiApiRequestException $e) {
			$this->assertEquals($exceptionClassName, get_class($e));
		}
	}

	/**
	 * A data provider of WWW-Authenticate response headers. 
	 */
	public static function wwwAuthenticateHeaderDataProvider() {
		return array(
			array(array('WWW-Authenticate' => 'Bearer realm="foo"'), 'TuentiApiAccessTokenRequired'),
			array(array('WWW-Authenticate' => 'Basic realm="bar"'), 'TuentiApiClientCredentialsRequired'),
			array(array('WWW-Authenticate' => 'Foo realm="test"'), 'TuentiApiClientError'),
		);
	}

	/**
	 * @test
	 * @expectedException TuentiCurlError
	 * @expectedExceptionMessage Curl Error [45]: Error setting port
	 */
	public function shouldThrowCurlError() {
		$tuenti = $this->getMockForTuenti('accessToken');

		$curlOptions = array(
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer ' . self::ACCESS_TOKEN
			),
			CURLOPT_POST => 1
		);
		$curlClientMock = $this->getCurlClientMock(NULL, NULL, $curlOptions, TRUE);
		$curlClientMock->expects($this->once())
			->method('getErrorNumber')
			->will($this->returnValue(CURLE_HTTP_PORT_FAILED));
		$curlClientMock->expects($this->once())
			->method('getErrorMessage')
			->will($this->returnValue('Error setting port'));

		$apiRequest = new Internal_TuentiApiRequest($tuenti, self::BASE_REQUEST_URL, array(), $curlClientMock);
		$apiRequest->post();
	}

	private function getCurlClientMock($response, $url, $options, $hasError) {
		if ($url === NULL) {
			$url = self::BASE_REQUEST_URL;
		}

		$curlClientMock = $this->getMock('Internal_TuentiCurlClient');
		$options += array(
			CURLOPT_USERAGENT => 'tuenti-php-sdk-1.0',
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_URL => $url,
			CURLOPT_HEADERFUNCTION => array($curlClientMock, 'responseHeaderReceived')
		);
		$curlClientMock->expects($this->once())
			->method('setOptions')
			->with($options);
		$curlClientMock->expects($this->once())
			->method('execute')
			->will($this->returnValue($response));
		$curlClientMock->expects($this->once())
			->method('hasError')
			->will($this->returnValue($hasError));
		$curlClientMock->expects($this->once())
			->method('close');
		return $curlClientMock;
	}

	private function getCurlClientMockForRequest($response, $statusCode = 200,
			$options = array(), $queryString = NULL, $responseHeaders = array()) {
		$url = self::BASE_REQUEST_URL;
		if ($queryString !== NULL) {
			$url .= '?' . $queryString;
		}

		$curlClientMock = $this->getCurlClientMock($response, $url, $options, FALSE);
		$curlClientMock->expects($this->once())
			->method('getInfo')
			->with(CURLINFO_HTTP_CODE)
			->will($this->returnValue($statusCode));
		$curlClientMock->expects($this->once())
			->method('getResponseHeaders')
			->with()
			->will($this->returnValue($responseHeaders));
		return $curlClientMock;
	}

	/**
	 * @param $authType
	 * @return PHPUnit_Framework_MockObject_MockObject
	 */
	private function getMockForTuenti($authTypes) {
		$authTypes = is_array($authTypes) ? $authTypes : array($authTypes);
		$tuenti = $this->getMockBuilder('Tuenti')->disableOriginalConstructor()->getMock();
		$clientIdAlreadySet = FALSE;
		foreach ($authTypes as $authType) {
			switch ($authType) {
				case 'accessToken':
					$tuenti->expects($this->once())
						->method('hasAccessToken')
						->will($this->returnValue(TRUE));
					$tuenti->expects($this->once())
						->method('getAccessToken')
						->will($this->returnValue(self::ACCESS_TOKEN));
					break;

				case 'clientCredentials':
					$tuenti->expects($this->once())
						->method('getClientSecret')
						->will($this->returnValue(self::CLIENT_SECRET));
					if (in_array('clientIdentifier', $authTypes)) {
						if (!$clientIdAlreadySet) {
							$tuenti->expects($this->exactly(2))
								->method('getClientId')
								->will($this->returnValue(self::CLIENT_ID));
							$clientIdAlreadySet = TRUE;
						}
					} else {
						$tuenti->expects($this->once())
							->method('getClientId')
							->will($this->returnValue(self::CLIENT_ID));
					}
					break;
				case 'clientIdentifier':
					if (in_array('clientCredentials', $authTypes)) {
						if (!$clientIdAlreadySet) {
							$tuenti->expects($this->exactly(2))
								->method('getClientId')
								->will($this->returnValue(self::CLIENT_ID));
							$clientIdAlreadySet = TRUE;
						}
					} else {
						$tuenti->expects($this->once())
							->method('getClientId')
							->will($this->returnValue(self::CLIENT_ID));
					}
					break;
			}
		}
		return $tuenti;
	}
}
