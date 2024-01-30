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

use RiotAPI\Tests\RiotAPITestCase;
use RiotAPI\Base\Objects;


/**
 *   Class BaseObjectIterable
 * @iterable $data
 */
class DtoIterable extends Objects\ApiObjectIterable
{
	/** @var array $data */
	public $data;
}


class ApiObjectIterableTest extends RiotAPITestCase
{
	public static array $data = [
		'data' => [
			'd', 'u', 'm', 'm', 'y', '_', 'd', 'a', 't', 'a'
		],
	];

	public function testRewind()
	{
		$obj = new DtoIterable(self::$data, null);

		$this->assertSame('d', $obj->current());
		$this->assertSame(0, $obj->key());
		$obj->rewind();
		$obj->next();
		$this->assertSame('u', $obj->current());
		$this->assertSame(1, $obj->key());
	}

	public function testCurrent()
	{
		$obj = new DtoIterable(self::$data, null);

		$this->assertSame('d', $obj->current());
		$obj->next();
		$this->assertSame('u', $obj->current());
	}

	public function testKey()
	{
		$obj = new DtoIterable(self::$data, null);

		$this->assertSame(0, $obj->key());
		$obj->next();
		$this->assertSame(1, $obj->key());
	}

	public function testNext()
	{
		$obj = new DtoIterable(self::$data, null);

		$obj->next();
		$this->assertSame('u', $obj->current());
		$obj->next();
		$this->assertSame('m', $obj->current());
	}

	public function testValid()
	{
		$obj = new DtoIterable(self::$data, null);

		$this->assertTrue($obj->valid());
		while ($obj->valid())
			$obj->next();
		$this->assertFalse($obj->valid());
	}
}
