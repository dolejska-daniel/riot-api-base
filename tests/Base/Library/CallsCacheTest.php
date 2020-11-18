<?php

/**
 * Copyright (C) 2016-2020  Daniel Dolejška
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use RiotAPI\Base\Definitions\Region;
use RiotAPI\Base\Exceptions\SettingsException;
use RiotAPI\Tests\TestBaseAPI;
use RiotAPI\Tests\RiotAPITestCase;


class CallsCacheTest extends TestCase
{
	public function testInit_simple()
	{
		$api = new TestBaseAPI([
			TestBaseAPI::SET_KEY            => RiotAPITestCase::getApiKey(),
			TestBaseAPI::SET_REGION         => Region::EUROPE_EAST,
			TestBaseAPI::SET_USE_DUMMY_DATA => true,
			TestBaseAPI::SET_CACHE_CALLS    => true,
		]);

		$this->assertInstanceOf(TestBaseAPI::class, $api);

		return $api;
	}

	public function testInit_numeric()
	{
		$api = new TestBaseAPI([
			TestBaseAPI::SET_KEY            => RiotAPITestCase::getApiKey(),
			TestBaseAPI::SET_REGION         => Region::EUROPE_EAST,
			TestBaseAPI::SET_USE_DUMMY_DATA => true,
			TestBaseAPI::SET_CACHE_CALLS    => true,
			TestBaseAPI::SET_CACHE_CALLS_LENGTH => 1,
		]);

		$this->assertInstanceOf(TestBaseAPI::class, $api);

		return $api;
	}

	public function testInit_array()
	{
		$api = new TestBaseAPI([
			TestBaseAPI::SET_KEY            => RiotAPITestCase::getApiKey(),
			TestBaseAPI::SET_REGION         => Region::EUROPE_EAST,
			TestBaseAPI::SET_USE_DUMMY_DATA => true,
			TestBaseAPI::SET_CACHE_CALLS    => true,
			TestBaseAPI::SET_CACHE_CALLS_LENGTH => [
				"0:test" => 60,
			],
		]);

		$this->assertInstanceOf(TestBaseAPI::class, $api);

		return $api;
	}


	public function dataProvider_settings_length_invalid()
	{
		return [
			"String" => [ "INVALID PARAMETER" ],
			"Bool"   => [ false ],
			"Array #1" => [
				[
					"1:resource1" => 60,
					"2:resource2" => "INVALID PARAMETER",
				]
			],
			"Array #2" => [
				[
					"INVALID RESOURCE 1" => 60,
					"INVALID RESOURCE 2" => 360,
				]
			],
			"Array #3" => [
				[
					"1:resource1"          => 80,
					"2:resource2"          => null,
					"INVALID RESOURCE 1" => 60,
				]
			],
		];
	}

	/**
	 * @dataProvider dataProvider_settings_length_invalid
	 *
	 * @param $callsLength
	 */
	public function testInit_settings_length_invalid( $callsLength )
	{
		$this->expectException(SettingsException::class);
		$this->expectExceptionMessage("is not valid.");

		new TestBaseAPI([
			TestBaseAPI::SET_KEY            => RiotAPITestCase::getApiKey(),
			TestBaseAPI::SET_REGION         => Region::EUROPE_EAST,
			TestBaseAPI::SET_USE_DUMMY_DATA => true,
			TestBaseAPI::SET_CACHE_CALLS    => true,
			TestBaseAPI::SET_CACHE_CALLS_LENGTH => $callsLength,
		]);
	}
}
