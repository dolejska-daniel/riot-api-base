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
use RiotAPI\Base\Exceptions\ServerLimitException;
use RiotAPI\Tests\TestBaseAPI;
use RiotAPI\Tests\RiotAPITestCase;


class RatelimitTest extends RiotAPITestCase
{
	public function testInit()
	{
		$api = new TestBaseAPI([
			TestBaseAPI::SET_KEY             => RiotAPITestCase::getApiKey(),
			TestBaseAPI::SET_REGION          => Region::EUROPE_EAST,
			TestBaseAPI::SET_USE_DUMMY_DATA  => true,
			TestBaseAPI::SET_CACHE_RATELIMIT => true,
		]);

		$this->assertInstanceOf(TestBaseAPI::class, $api);
		$api->clearCache();

		return $api;
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testApiCall_Valid( TestBaseAPI $api )
	{
		$data = $api->makeTestEndpointCall("slow");
		$this->assertEquals([], $data);
	}

	/**
	 * @depends testInit
	 * @depends testApiCall_Valid
	 *
	 * @param TestBaseAPI $api
	 */
	public function testApiCall_Exception( TestBaseAPI $api )
	{
		$this->expectException(ServerLimitException::class);
		$this->expectExceptionMessage("API call rate limit would be exceeded by this call.");

		$api->makeTestEndpointCall("slow");
	}

	/**
	 * @depends testInit
	 * @depends testApiCall_Exception
	 *
	 * @param TestBaseAPI $api
	 */
	public function testApiCall_ExceptionTimeout( TestBaseAPI $api )
	{
		sleep(1);

		$data = $api->makeTestEndpointCall("slow");
		$this->assertEquals([], $data);

		return $api;
	}
}
