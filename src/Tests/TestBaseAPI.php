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

namespace RiotAPI\Tests;

use GuzzleHttp\Client;
use RiotAPI\Base\BaseAPI;
use RiotAPI\Base\Definitions\AsyncRequest;

class TestBaseAPI extends BaseAPI
{   
	public array $resources = [
		"0:test",
		"1:resource1",
		"2:resource2"
	];

	/** @var ?AsyncRequest $next_async_request */
	public ?AsyncRequest $next_async_request = null;

	/** @var AsyncRequest[] $async_requests */
	public array $async_requests = [];

	/** @var Client[] $async_clients */
    public array $async_clients = [];
    
	public function getCCC()
	{
		return $this->ccc;
	}

	public function getRLC()
	{
		return $this->rlc;
	}

	public function saveCache(): bool
	{
		return parent::saveCache();
	}
}
