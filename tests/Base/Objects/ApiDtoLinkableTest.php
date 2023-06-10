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
use RiotAPI\Base\Objects\ApiObjectLinkable;


class DtoLinkable extends ApiObjectLinkable
{
    public string $x;
}

class ApiObjectLinkableTest extends RiotAPITestCase
{
    public function testStaticDataAccessor()
    {
        $src = ["x" => "X"];
        $staticData = [
            "a" => "A",
            "b" => "B",
            "x" => "C",
        ];

        $obj = new DtoLinkable($src);
        $obj->staticData = (object) $staticData;

        $this->assertEquals($src, $obj->getData());
        $this->assertEquals($obj->x, $src["x"]);
        $this->assertEquals($obj->a, $staticData["a"]);
        $this->assertEquals($obj->b, $staticData["b"]);
    }

}
