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
 * Unit tests for the Internal_TuentiSessionStorage class.
 *
 * @see Internal_TuentiSessionStorage
 */
class Internal_TuentiSessionStorageTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var string the client identifier of the test application
	 */
	const CLIENT_ID = 'my-app';

	protected function setUp() {
		if (session_id()) {
			session_destroy();
		}
    }

	protected function tearDown() {
		session_destroy();
	}

	/**
	 * @test
	 */
	public function shouldPersistPropertiesInSession() {
		$key = 'userId';
		$value = 12345;
		$sessionFieldName = 'tuenti_my-app_userId';

		$storage = new Internal_TuentiSessionStorage(self::CLIENT_ID);
		$this->assertNull($storage->getPersistentProperty($key));

		$storage->setPersistentProperty($key, $value);
		$this->assertEquals($value, $storage->getPersistentProperty($key));
		$this->assertEquals($value, $_SESSION[$sessionFieldName]);

		// Persist and close session
		$sessionId1 = session_id();
		session_write_close();

		// Set same variable in other session
		$otherValue = 'foo';
		session_id('test');
		session_start();
		$_SESSION[$sessionFieldName] = $otherValue;
		$this->assertEquals($otherValue, $_SESSION[$sessionFieldName]);
		session_write_close();

		// Ensure variable is present as set in first session
		session_id($sessionId1);
		session_start();

		$storage = new Internal_TuentiSessionStorage(self::CLIENT_ID);
		$this->assertEquals($value, $storage->getPersistentProperty($key));

		$storage->setPersistentProperty($key, NULL);
		$this->assertNull($storage->getPersistentProperty($key));
	}
}
