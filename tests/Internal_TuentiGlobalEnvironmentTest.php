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
 * Unit tests for the Internal_TuentiGlobalEnvironment class.
 *
 * @see Internal_TuentiGlobalEnvironment
 */
class Internal_TuentiGlobalEnvironmentTest extends PHPUnit_Framework_TestCase {

	/**
	 * @test
	 * @dataProvider globalEnvironmentVariableDataProvider
	 */
	public function shouldReturnGlobalEnvironmentVariable($name, $variable) {
		$environment = new Internal_TuentiGlobalEnvironment();
		$this->assertEquals($variable, $environment->getEnvironmentVariable($name));
	}

	/**
	 * A data provider for shouldReturnGlobalEnvironmentVariable.
	 *
	 * @return array(TuentiEnvironment::VARIABLE_XXX, mixed)
	 */
	public static function globalEnvironmentVariableDataProvider() {
		return array(
			array(TuentiEnvironment::VARIABLE_SERVER, $_SERVER),
			array(TuentiEnvironment::VARIABLE_GET, $_GET),
			array(TuentiEnvironment::VARIABLE_POST, $_POST),
			array(TuentiEnvironment::VARIABLE_FILES, $_FILES),
			array(TuentiEnvironment::VARIABLE_COOKIE, $_COOKIE),
			array(TuentiEnvironment::VARIABLE_REQUEST, $_REQUEST),
			array(TuentiEnvironment::VARIABLE_ENV, $_ENV)
		);
	}

	/**
	 * @test
	 */
	public function shouldReturnCurrentRequestTime() {
		$timeBefore = time();
		$environment = new Internal_TuentiGlobalEnvironment();
		$requestTime = $environment->getCurrentTime();
		$timeAfter = time();
		$this->assertGreaterThanOrEqual($timeBefore, $requestTime);
		$this->assertLessThanOrEqual($timeAfter, $requestTime);
	}
}
