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

namespace RiotAPI\Base\Objects;

use Iterator;
use JetBrains\PhpStorm\Pure;

/**
 *   Class ApiObjectIterable
 *
 * @package RiotAPI\LeagueAPI\Objects
 */
abstract class ApiObjectIterable extends ApiObject implements Iterator
{
	/**
	 * @var array
	 * @internal
	 */
	protected $_iterable = [];

	public function rewind(): void
	{
		reset($this->_iterable);
	}

	public function current(): mixed
	{
		return current($this->_iterable);
	}

	public function key(): string|int|null
	{
		return key($this->_iterable);
	}

	public function next(): void
	{
		next($this->_iterable);
	}

	#[Pure] public function valid(): bool
	{
		return ($this->key() !== null && $this->key() !== false);
	}
}