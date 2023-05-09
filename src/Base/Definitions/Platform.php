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

namespace RiotAPI\Base\Definitions;

use RiotAPI\Base\Exceptions\GeneralException;


/**
 *   Class Platform
 *
 * @package RiotAPI\LeagueAPI\Definitions
 */
class Platform implements IPlatform
{
	// ==================================================================dd=
	//     Standard regional platforms
	// ==================================================================dd=

	const NORTH_AMERICA = 'na1';
	const EUROPE_WEST = 'euw1';
	const EUROPE_EAST = 'eun1';
	const LAMERICA_SOUTH = 'la2';
	const LAMERICA_NORTH = 'la1';
	const BRASIL = 'br1';
	const RUSSIA = 'ru';
	const TURKEY = 'tr1';
	const OCEANIA = 'oc1';
	const KOREA = 'kr';
	const JAPAN = 'jp1';
	const TAIWAN = 'tw2';

	const AMERICAS = 'americas';
	const EUROPE = 'europe';
	const ASIA = 'asia';

	public static $list = array(
		Region::NORTH_AMERICA   => self::NORTH_AMERICA,
		Region::EUROPE          => self::EUROPE,
		Region::EUROPE_WEST     => self::EUROPE_WEST,
		Region::EUROPE_EAST     => self::EUROPE_EAST,
		Region::LAMERICA_SOUTH  => self::LAMERICA_SOUTH,
		Region::LAMERICA_NORTH  => self::LAMERICA_NORTH,
		Region::BRASIL          => self::BRASIL,
		Region::RUSSIA          => self::RUSSIA,
		Region::TURKEY          => self::TURKEY,
		Region::OCEANIA         => self::OCEANIA,
		Region::KOREA           => self::KOREA,
		Region::JAPAN           => self::JAPAN,
		Region::TAIWAN           => self::TAIWAN,
		Region::AMERICAS        => self::AMERICAS,
		Region::ASIA            => self::ASIA,
	);

	public static $continentalRegions = [
		self::AMERICAS,
		self::EUROPE,
		self::ASIA,
	];


	// ==================================================================dd=
	//     Control functions
	// ==================================================================dd=

	public function getList(): array
	{
		return $this::$list;
	}

	public function getPlatformNameOfRegion($region): string
	{
		if (!isset($this::$list[$region]))
			throw new GeneralException('Invalid region provided. Can not find requested platform.');

		return $this::$list[$region];
	}

	public function getCorrespondingContinentRegion($region): string
	{
		switch ($this->getPlatformNameOfRegion($region))
		{
			case Platform::EUROPE_WEST:
			case Platform::EUROPE_EAST:
			case Platform::TURKEY:
			case Platform::RUSSIA:
				return Region::EUROPE;

			case Platform::NORTH_AMERICA:
			case Platform::LAMERICA_NORTH:
			case Platform::LAMERICA_SOUTH:
			case Platform::BRASIL:
			case Platform::OCEANIA:
				return Region::AMERICAS;

			case Platform::KOREA:
			case Platform::JAPAN:
			case Platform::TAIWAN:
				return Region::ASIA;

			default:
				throw new GeneralException("Unable to convert '$region' platform ID to corresponding continent region.");
		}
	}
}