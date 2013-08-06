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
class Internal_TuentiCurlClientTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var string an invalid request URL to use in tests 
	 */
	const INVALID_REQUEST_URL = 'test://test';

	/**
	 * @test 
	 */
	public function shouldRejectInvalidRequestUrl() {
		$client = new Internal_TuentiCurlClient();
		$client->setOptions(array(
			CURLOPT_URL => self::INVALID_REQUEST_URL,
		));
		$client->execute();
		$this->assertTrue($client->hasError());
		$this->assertEquals(CURLE_UNSUPPORTED_PROTOCOL, $client->getErrorNumber());
		$this->assertEquals('Protocol test not supported or disabled in libcurl',
				$client->getErrorMessage());
		$this->assertEquals(self::INVALID_REQUEST_URL,
				$client->getInfo(CURLINFO_EFFECTIVE_URL));
		$this->assertEquals(array(), $client->getResponseHeaders());
	}

	/**
	 * @test
	 */
	public function shouldStoreResponseHeaders() {
		$headers = array(
			'Content-Type' => 'text/json; charset=UTF-8',
			'Content-Length' => 1
		);
		$handle = $this->getMock('stdClass');
		$client = new Internal_TuentiCurlClientStub($handle);
		foreach ($headers as $name => $value) {
			$header = sprintf('%s: %s', $name, $value);
			$client->responseHeaderReceived($handle, $header);
		}
		$this->assertEquals($headers, $client->getResponseHeaders());
	}

	/**
	 * @test
	 * @expectedException PHPUnit_Framework_Error_Warning
	 * @expectedExceptionMessage is not a valid cURL handle resource
	 */
	public function shouldFailOnExecuteAfterClose() {
		$client = new Internal_TuentiCurlClient();
		$client->setOptions(array(
			CURLOPT_URL => self::INVALID_REQUEST_URL,
		));
		$client->execute();
		$client->close();
		$client->execute();
	}
}


/**
 * An Internal_TuentiCurlClient sub-class that enables injecting a mock of the
 * cURL handle.
 */
class Internal_TuentiCurlClientStub extends Internal_TuentiCurlClient {

	/**
	 * @var PHPUnit_Framework_MockObject_MockObject the cURL handle
	 */
	private $handleMock;

	public function __construct($handleMock) {
		$this->handleMock = $handleMock;
		parent::__construct();
	}

	protected function initialize() {
		return $this->handleMock;
	}
}
