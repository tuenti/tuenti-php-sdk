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
 * Unit tests for the Internal_TuentiContext class.
 *
 * @see Internal_TuentiContext
 */
class Internal_TuentiContextTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var string the client identifier of the test application
	 */
	const CLIENT_ID = 'my-app';

	/**
	 * @var string the client secret of the test application
	 */
	const CLIENT_SECRET = 'my-secret';

	/**
	 * @var int the current time
	 */
	const TIME = 1000000001;

	/**
	 * @var Tuenti the SDK instance
	 */
	private $tuentiMock;

	/**
	 * @var TuentiEnvironment the environment of the SDK
	 */
	private $environmentMock;

	protected function setUp() {
		$this->tuentiMock = $this->getMockBuilder('Tuenti')
				->disableOriginalConstructor()->getMock();
		$this->tuentiMock->expects($this->any())
				->method('getClientId')
				->with()
				->will($this->returnValue(self::CLIENT_ID));
		$this->tuentiMock->expects($this->once())
				->method('getClientSecret')
				->with()
				->will($this->returnValue(self::CLIENT_SECRET));

		$this->environmentMock = $this->getMock('TuentiEnvironment');
		$this->environmentMock->expects($this->any())
				->method('getCurrentTime')
				->with()
				->will($this->returnValue(self::TIME));
	}

	private function createContext($rawContext) {
		$this->environmentMock->expects($this->any())
				->method('getEnvironmentVariable')
				->with(TuentiEnvironment::VARIABLE_POST)
				->will($this->returnValue(array(
						'tuenti_context' => $rawContext
				)));

		return new Internal_TuentiContext($this->tuentiMock, $this->environmentMock);
	}

	/**
	 * @test
	 * @expectedException TuentiContextNotFound
	 */
	public function shouldThrowContextNotFound() {
		$context = $this->createContext(NULL);
		$context->getCustomFields();
	}

	/**
	 * @test
	 */
	public function shouldValidateEmptyContext() {
		$rawContext = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJodHRwOlwvXC90dWVudGkuY29tIiwiYXVkIjoibXktYXBwIiwiaWF0IjoxMzY0ODE2MjMwLCJleHAiOjEzNjQ4MTk4MzB9.loFgu8lUtzHTA6ziGDTTIQfLxspxiNOLx_0qcHcttyo';
		$context = $this->createContext($rawContext);
		$this->assertEquals(array(), $context->getCustomFields());
	}

	/**
	 * @test
	 */
	public function shouldValidateContext() {
		$rawContext = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJodHRwOlwvXC90dWVudGkuY29tIiwiYXVkIjoibXktYXBwIiwiaWF0IjoxMzY0ODE2MjMwLCJleHAiOjEzNjQ4MTk4MzAsInR1ZW50aV91c2VyIjp7ImxvY2FsZSI6ImVzX0VTIn0sInR1ZW50aV9wYWdlIjp7ImlkIjoiMTIzNDUiLCJmb2xsb3dlciI6dHJ1ZX19.4AbVt-c9IW-s7x31TOa5k3n1iwMI9Ao1OUF5M7dX2fU';
		$context = $this->createContext($rawContext);
		$expectedCustomFields = array(
			'tuenti_user' => array(
				'locale' => 'es_ES'
			),
			'tuenti_page' => array(
				'id' => '12345',
				'follower' => TRUE,
			)
		);
		$this->assertEquals($expectedCustomFields, $context->getCustomFields());
	}

	/**
	 * @test
	 * @dataProvider invalidContextDataProvider
	 */
	public function shouldThrowInvalidContext($rawContext, $errorMessage) {
		$context = $this->createContext($rawContext);
		try {
			$context->getCustomFields();
			$this->fail('Expected TuentiInvalidContext to be thrown');
		} catch (TuentiInvalidContext $e) {
			$this->assertEquals($errorMessage, $e->getMessage());
		}
	}

	public static function invalidContextDataProvider() {
		return array(
			// One dot-separated components provided
			array('a', 'Expected 3 dot-separated components, found 1'),

			// Four one dot-separated components provided
			array('a.b.c.d', 'Expected 3 dot-separated components, found 4'),

			// Header could not be base64-decoded
			array('!!.Yg.Yw', 'Invalid header'),

			// Header could not be JSON-decoded
			array('YQ.Yg.Yw', 'Invalid header'),

			// typ field missing from header
			array('eyJhbGciOiJIUzI1NiJ9.Yg.Yw', 'Invalid header'),

			// Expected type not provided
			array('eyJhbGciOiJIUzI1NiIsInR5cCI6InRlc3QifQ.Yg.Yw', 'Invalid header'),

			// alg field missing from header
			array('eyJ0eXAiOiJKV1QifQ.Yg.Yw', 'Invalid header'),

			// Expected algorithm not provided
			array('eyJhbGciOiJ0ZXN0IiwidHlwIjoiSldUIn0.Yg.Yw', 'Invalid header'),

			// Payload could not be base64-decoded
			array('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.!!.Yw', 'Invalid payload'),

			// Payload could not be JSON-decoded
			array('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.Yg.Yw', 'Invalid payload'),

			// iss field missing from payload
			array('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhdWQiOiJteS1hcHAiLCJleHAiOjEwMDAwMDAwMDAzNjAwfQ.Yw', 'Invalid payload'),

			// Expected issuer not provided
			array('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJ0ZXN0IiwiYXVkIjoibXktYXBwIiwiZXhwIjoxMDAwMDAwMDAwMzYwMH0.Yw', 'Invalid payload'),

			// aud field missing from payload
			array('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJodHRwOlwvXC90dWVudGkuY29tIiwiZXhwIjoxMDAwMDAwMDAwMzYwMH0.Yw', 'Invalid payload'),

			// Expected audience not provided
			array('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJodHRwOlwvXC90dWVudGkuY29tIiwiYXVkIjoidGVzdCIsImV4cCI6MTAwMDAwMDAwMDM2MDB9.Yw', 'Invalid payload'),

			// exp field missing from payload
			array('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJodHRwOlwvXC90dWVudGkuY29tIiwiYXVkIjoibXktYXBwIn0.Yw', 'Invalid payload'),

			// Expiration time passed
			array('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJodHRwOlwvXC90dWVudGkuY29tIiwiYXVkIjoibXktYXBwIiwiZXhwIjoxMDAwMDAwMDAwMDAwMH0.Yw', 'Invalid signature'),

			// Signature could not be base64-decoded
			array('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJodHRwOlwvXC90dWVudGkuY29tIiwiYXVkIjoibXktYXBwIiwiZXhwIjoxMDAwMDAwMDAwMzYwMH0.Yw', 'Invalid signature'),

			// Signature did not match
			array('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJodHRwOlwvXC90dWVudGkuY29tIiwiYXVkIjoibXktYXBwIiwiaWF0IjoxMzY0ODE2MjMwLCJleHAiOjEzNjQ4MTk4MzAsInR1ZW50aV91c2VyIjp7ImxvY2FsZSI6ImVzX0VTIn0sInR1ZW50aV9wYWdlIjp7ImlkIjoiMTIzNDUiLCJmb2xsb3dlciI6dHJ1ZX19.4AbVt-c9IW-s7x31TOa5k3n1iwMI9Ao1OUF5M7dX2fUx', 'Invalid signature')
		);
	}
}
