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
use RiotAPI\Base\Definitions\Platform;
use RiotAPI\Base\Exceptions\RequestException;
use RiotAPI\Base\Exceptions\ServerException;
use RiotAPI\Base\Exceptions\ServerLimitException;
use RiotAPI\Base\Exceptions\SettingsException;
use RiotAPI\Tests\TestBaseAPI;
use RiotAPI\Tests\RiotAPITestCase;

use Symfony\Component\Cache\Adapter\MemcachedAdapter;


class LibraryTest extends RiotAPITestCase
{
	public function testInit()
	{
		$api = new TestBaseAPI([
			TestBaseAPI::SET_KEY            => RiotAPITestCase::getApiKey(),
			TestBaseAPI::SET_REGION         => Region::EUROPE_EAST,
			TestBaseAPI::SET_USE_DUMMY_DATA => true,
		]);

		$this->assertInstanceOf(TestBaseAPI::class, $api);

		return $api;
	}

	public function testInit_cachingDefaults()
	{
		$api = new TestBaseAPI([
			TestBaseAPI::SET_KEY             => RiotAPITestCase::getApiKey(),
			TestBaseAPI::SET_REGION          => Region::EUROPE_EAST,
			TestBaseAPI::SET_CACHE_RATELIMIT => true,
			TestBaseAPI::SET_CACHE_CALLS     => true,
		]);

		$this->assertInstanceOf(TestBaseAPI::class, $api);
	}

	public function testInit_customDataProviders()
	{
		$api = new TestBaseAPI([
			TestBaseAPI::SET_KEY             => RiotAPITestCase::getApiKey(),
			TestBaseAPI::SET_REGION          => Region::EUROPE_EAST,
		], new Region(), new Platform());

		$this->assertInstanceOf(TestBaseAPI::class, $api);
	}

	public function testInit_settings_invalid_missingRequired()
	{
		$this->expectException(SettingsException::class);
		$this->expectExceptionMessage("is missing!");

		new TestBaseAPI([]);
	}

	public function testInit_settings_invalid_keyIncludeType()
	{
		$this->expectException(SettingsException::class);
		$this->expectExceptionMessage("is not valid.");

		new TestBaseAPI([
			TestBaseAPI::SET_KEY              => RiotAPITestCase::getApiKey(),
			TestBaseAPI::SET_REGION           => Region::EUROPE_EAST,
			TestBaseAPI::SET_KEY_INCLUDE_TYPE => 'THIS_IS_INVALID_INCLUDE_TYPE',
		]);
	}

	public function testInit_settings_invalid_cacheProvider()
	{
		$this->expectException(SettingsException::class);
		$this->expectExceptionMessage("Provided CacheProvider does not implement Psr\Cache\CacheItemPoolInterface (PSR-6)");

		new TestBaseAPI([
			TestBaseAPI::SET_KEY             => RiotAPITestCase::getApiKey(),
			TestBaseAPI::SET_REGION          => Region::EUROPE_EAST,
			TestBaseAPI::SET_CACHE_RATELIMIT => true,
			TestBaseAPI::SET_CACHE_PROVIDER  => new stdClass(),
		]);
	}

	public function testInit_settings_invalid_cacheProvider_uninstantiable()
	{
		$this->expectException(SettingsException::class);
		$this->expectExceptionMessage("Failed to initialize CacheProvider class:");

		new TestBaseAPI([
			TestBaseAPI::SET_KEY             => RiotAPITestCase::getApiKey(),
			TestBaseAPI::SET_REGION          => Region::EUROPE_EAST,
			TestBaseAPI::SET_CACHE_RATELIMIT => true,
			TestBaseAPI::SET_CACHE_PROVIDER  => "Orianna",
		]);
	}

	/**
	 * @requires extension memcached
	 */
	public function testInit_settings_invalid_cacheProviderSettings()
	{
		$this->expectException(SettingsException::class);
		$this->expectExceptionMessage("CacheProvider class failed to be initialized:");

		new TestBaseAPI([
			TestBaseAPI::SET_KEY                   => RiotAPITestCase::getApiKey(),
			TestBaseAPI::SET_REGION                => Region::EUROPE_EAST,
			TestBaseAPI::SET_CACHE_RATELIMIT       => true,
			TestBaseAPI::SET_CACHE_PROVIDER        => MemcachedAdapter::class,
			TestBaseAPI::SET_CACHE_PROVIDER_PARAMS => [ '' ],
		]);
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testChangeRegion(TestBaseAPI $api )
	{
		$this->assertSame(Region::EUROPE_EAST, $api->getSetting(TestBaseAPI::SET_REGION));
		$api->setRegion(Region::EUROPE_WEST);
		$this->assertSame(Region::EUROPE_WEST, $api->getSetting(TestBaseAPI::SET_REGION));
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testChangeSettings_single(TestBaseAPI $api )
	{
		$this->assertSame(RiotAPITestCase::getApiKey(), $api->getSetting(TestBaseAPI::SET_KEY));
		$api->setSetting(TestBaseAPI::SET_KEY, "NOT_REALLY_A_API_KEY");
		$this->assertSame("NOT_REALLY_A_API_KEY", $api->getSetting(TestBaseAPI::SET_KEY));
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testChangeSettings_array(TestBaseAPI $api )
	{
		$api->setSettings([
			TestBaseAPI::SET_KEY    => RiotAPITestCase::getApiKey(),
			TestBaseAPI::SET_REGION => Region::EUROPE_EAST,
		]);
		$this->assertSame(RiotAPITestCase::getApiKey(), $api->getSetting(TestBaseAPI::SET_KEY));
		$this->assertSame(Region::EUROPE_EAST, $api->getSetting(TestBaseAPI::SET_REGION));
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testChangeSettings_initOnly(TestBaseAPI $api )
	{
		$this->expectException(SettingsException::class);
		$this->expectExceptionMessage("can only be set on initialization of the library");

		$api->setSetting(TestBaseAPI::SET_API_BASEURL, "http://google.com");
	}

	public function testCustomRegionDataProvider()
	{
		//  TODO
		$this->markTestIncomplete('This test has not been implemented yet.');
	}

	public function testCustomPlatformDataProvider()
	{
		//  TODO
		$this->markTestIncomplete('This test has not been implemented yet.');
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testMakeCall_503(TestBaseAPI $api )
	{
		$this->expectException(ServerException::class);
		$this->expectExceptionMessage("LeagueAPI: Service is temporarily unavailable.");

		$api->makeTestEndpointCall(503);
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testMakeCall_500(TestBaseAPI $api )
	{
		$this->expectException(ServerException::class);
		$this->expectExceptionMessage("LeagueAPI: Internal server error occured.");

		$api->makeTestEndpointCall(500);
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testMakeCall_429(TestBaseAPI $api )
	{
		$this->expectException(ServerLimitException::class);
		$this->expectExceptionMessage("LeagueAPI: Rate limit for this API key was exceeded.");

		$api->makeTestEndpointCall(429);
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testMakeCall_415(TestBaseAPI $api )
	{
		$this->expectException(RequestException::class);
		$this->expectExceptionMessage("LeagueAPI: Unsupported media type.");

		$api->makeTestEndpointCall(415);
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testMakeCall_404(TestBaseAPI $api )
	{
		$this->expectException(RequestException::class);
		$this->expectExceptionMessage("LeagueAPI: Not Found.");

		$api->makeTestEndpointCall(404);
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testMakeCall_403(TestBaseAPI $api )
	{
		$this->expectException(RequestException::class);
		$this->expectExceptionMessage("LeagueAPI: Forbidden.");

		$api->makeTestEndpointCall(403);
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testMakeCall_401(TestBaseAPI $api )
	{
		$this->expectException(RequestException::class);
		$this->expectExceptionMessage("LeagueAPI: Unauthorized.");

		$api->makeTestEndpointCall(401);
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testMakeCall_400(TestBaseAPI $api )
	{
		$this->expectException(RequestException::class);
		$this->expectExceptionMessage("LeagueAPI: Request is invalid.");

		$api->makeTestEndpointCall(400);
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testMakeCall_4xx(TestBaseAPI $api )
	{
		$this->expectException(RequestException::class);
		$this->expectExceptionMessage("LeagueAPI: Unspecified error occured");

		$api->makeTestEndpointCall(498);
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testMakeCall_test_Versions(TestBaseAPI $api )
	{
		$data = $api->makeTestEndpointCall('versions');

		$this->assertSame($data, $api->getResult());
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testMakeCall_test_PUT(TestBaseAPI $api )
	{
		$data = $api->makeTestEndpointCall('put', null, TestBaseAPI::METHOD_PUT);

		$this->assertSame($data, $api->getResult());
	}

	public function testCurlException()
	{
		$this->expectException(RequestException::class);

		$api = new TestBaseAPI([
			TestBaseAPI::SET_KEY         => RiotAPITestCase::getApiKey(),
			TestBaseAPI::SET_REGION      => Region::EUROPE_EAST,
			TestBaseAPI::SET_API_BASEURL => '.invalid.api.url.riotgames.com',
		]);

		$api->makeTestEndpointCall('versions');
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testMakeCall_NoDummyData(TestBaseAPI $api )
	{
		$this->expectException(RequestException::class);
		$this->expectExceptionMessage("No DummyData available for call.");

		$api->makeTestEndpointCall("no-dummy-data");
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testMakeCall_DummyDataEmpty(TestBaseAPI $api )
	{
		$this->expectException(RequestException::class);
		$this->expectExceptionMessage("No DummyData available for call.");

		$api->makeTestEndpointCall("empty");
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testSaveDummyData(TestBaseAPI $api )
	{
		$api->setSetting(TestBaseAPI::SET_SAVE_DUMMY_DATA, false);

		try
		{
			$api->makeTestEndpointCall("save");
		}
		catch (RequestException $ex) {}

		$this->assertFileDoesNotExist($api->_getDummyDataFileName());
		$api->_saveDummyData();
		$this->assertFileExists($api->_getDummyDataFileName(), "DummyData file was not created correctly.");

		// Removes the dummy data file on subsequent runs
		if (file_exists($api->_getDummyDataFileName()))
			unlink($api->_getDummyDataFileName());
	}

	/**
	 * @depends testInit
	 *
	 * @param TestBaseAPI $api
	 */
	public function testDestruct(TestBaseAPI $api )
	{
		$api->__destruct();
		$this->assertTrue(true);
	}
}
