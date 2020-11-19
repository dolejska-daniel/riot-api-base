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

use RiotAPI\Base\Definitions\ICallCacheControl;
use RiotAPI\Base\Definitions\CallCacheControl;
use RiotAPI\Tests\RiotAPITestCase;


class CallCacheControlTest extends RiotAPITestCase
{
	public static $data = [];

	/**
	 * @after serialize
	 */
	public function testInit()
	{
		self::$data = [
			[
				'key1' => "SECRET_API_KEY",
			],
			[
				'key2' => array(
					1,
					2,
					3,
					"i4" => "test",
					"i5" => array(
						"even", "more", "data"
					)
				),
			],
		];

		$obj = new CallCacheControl();

		$this->assertInstanceOf(CallCacheControl::class, $obj);

		return $obj;
	}

	/**
	 * @depends testInit
	 *
	 * @param ICallCacheControl $control
	 *
	 * @return ICallCacheControl
	 */
	public function testSaveCallData( ICallCacheControl $control )
	{
		$hash = md5("https://global.api.riotgames.com/lol/tournament-stub/v3/providers");
		$this->assertTrue($control->saveCallData($hash, self::$data, 1));

		return $control;
	}

	/**
	 * @depends testInit
	 *
	 * @param ICallCacheControl $control
	 */
	public function testLoadCallData_Invalid( ICallCacheControl $control )
	{
		$hash = md5(random_bytes(64));
		$this->assertFalse($control->loadCallData($hash));
	}

	/**
	 * @depends testSaveCallData
	 *
	 * @param ICallCacheControl $control
	 */
	public function testLoadCallData_Valid( ICallCacheControl $control )
	{
		$hash = md5("https://global.api.riotgames.com/lol/tournament-stub/v3/providers");
		$this->assertSame(self::$data, $control->loadCallData($hash));
	}

	/**
	 * @depends testInit
	 *
	 * @param ICallCacheControl $control
	 */
	public function testIsCallCached_False( ICallCacheControl $control )
	{
		$hash = md5(random_bytes(64));
		$this->assertFalse($control->isCallCached($hash));
	}

	/**
	 * @depends testSaveCallData
	 *
	 * @param ICallCacheControl $control
	 */
	public function testIsCallCached_True( ICallCacheControl $control )
	{
		$hash = md5("https://global.api.riotgames.com/lol/tournament-stub/v3/providers");
		$this->assertTrue($control->isCallCached($hash));
	}

	/**
	 * @depends testSaveCallData
	 *
	 * @runInSeparateProcess
	 * @param ICallCacheControl $control
	 */
	public function testIsCallCached_Expired( ICallCacheControl $control )
	{
		$hash = md5("https://global.api.riotgames.com/lol/tournament-stub/v3/providers");
		sleep(2);
		$this->assertFalse($control->isCallCached($hash));
	}
}
