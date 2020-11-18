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


class LiveTest extends TestCase
{
	public function testInit()
	{
		if (getenv("TRAVIS_PULL_REQUEST"))
			$this->markTestSkipped("Skipping live tests in PRs.");

		$api = new TestBaseAPI([
			TestBaseAPI::SET_KEY                => RiotAPITestCase::getApiKey(),
			TestBaseAPI::SET_REGION             => Region::EUROPE_EAST,
			TestBaseAPI::SET_VERIFY_SSL         => false,
			TestBaseAPI::SET_CACHE_RATELIMIT    => true,
			TestBaseAPI::SET_CACHE_CALLS        => true,
			TestBaseAPI::SET_CACHE_CALLS_LENGTH => 600,
			TestBaseAPI::SET_USE_DUMMY_DATA     => false,
			TestBaseAPI::SET_SAVE_DUMMY_DATA    => false,
		]);

		$this->assertInstanceOf(TestBaseAPI::class, $api);

		return $api;
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 *
	 * @return LeagueAPI
	 */
	public function testLiveCall(TestBaseAPI $api )
	{
		$this->markAsRisky();

		// FIXME:
		// $summoner = $api->getSummonerByName("KuliS");
		// $this->assertSame("KuliS", $summoner->name);

		return $api;
	}

	/**
	 * @depends testLiveCall
	 */
	public function testLiveCall_cached()
	{
		$this->markAsRisky();

		// FIXME:
		// $api = new TestBaseAPI([
		// 	TestBaseAPI::SET_KEY         => "INVALID_KEY",
		// 	TestBaseAPI::SET_REGION      => Region::EUROPE_EAST,
		// 	TestBaseAPI::SET_CACHE_CALLS => true,
		// 	TestBaseAPI::SET_CACHE_CALLS_LENGTH => 600,
		// ]);

		// $summoner = $api->getSummonerByName("KuliS");
		// $this->assertSame("KuliS", $summoner->name);
	}
}
