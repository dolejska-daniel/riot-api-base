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
use RiotAPI\Tests\TestBaseAPI;
use RiotAPI\Tests\RiotAPITestCase;


class CacheTest extends TestCase
{
	public function testInit()
	{
		$api = new TestBaseAPI([
			TestBaseAPI::SET_KEY             => RiotAPITestCase::getApiKey(),
			TestBaseAPI::SET_REGION          => Region::EUROPE_EAST,
			TestBaseAPI::SET_CACHE_CALLS     => true,
			TestBaseAPI::SET_CACHE_RATELIMIT => true,
			TestBaseAPI::SET_USE_DUMMY_DATA  => true,
			TestBaseAPI::SET_SAVE_DUMMY_DATA => getenv('SAVE_DUMMY_DATA') ?? false,
		]);

		$this->assertInstanceOf(TestBaseAPI::class, $api);
		$this->assertNotNull($api->getCCC());
		$this->assertNotNull($api->getRLC());
		$api->clearCache();

		$hash = md5(random_bytes(16));
		return [$api, $hash];
	}

	/**
	 * @depends testInit
	 *
	 * @param array $args
	 * @return array
	 */
	public function testCreateAndSaveData( array $args )
	{
		/**
		 * @var CacheTestCustomTestBaseAPI $api
		 * @var string $hash
		 */
		list($api, $hash) = $args;
		$data = random_bytes(128);

		$this->assertFalse($api->getCCC()->isCallCached($hash), "The call is already cached for some reason.");
		$this->assertTrue($api->getCCC()->saveCallData($hash, $data, 2), "Failed to register call data.");
		$this->assertTrue($api->getCCC()->isCallCached($hash), "Failed to detect that the call is already cached.");
		$this->assertSame($data, $api->getCCC()->loadCallData($hash), "The cached data are not the same.");
		$this->assertTrue($api->saveCache(), "Failed to save cache.");

		return [$api, $hash, $data];
	}

	/**
	 * @depends testCreateAndSaveData
	 */
	public function testLoadAndValidateData( array $args )
	{
		/**
		 * @var CustomTestBaseAPI $api
		 * @var string $hash
		 */
		list($api, $hash, $data) = $args;

		$api = new TestBaseAPI([
			TestBaseAPI::SET_KEY             => "INVALID_KEY",
			TestBaseAPI::SET_REGION          => Region::EUROPE_EAST,
			TestBaseAPI::SET_CACHE_CALLS     => true,
			TestBaseAPI::SET_CACHE_RATELIMIT => true,
		]);

		$this->assertTrue($api->getCCC()->isCallCached($hash), "Failed to detect that the call is already cached from previous instance.");
		$this->assertSame($data, $api->getCCC()->loadCallData($hash), "The cached data from previous instance are not the same.");
		$this->assertTrue($api->clearCache(), "Failed to clear cache.");
		$this->assertFalse($api->getCCC()->isCallCached($hash), "Failed to detect that cache has already been cleared.");
	}
}
