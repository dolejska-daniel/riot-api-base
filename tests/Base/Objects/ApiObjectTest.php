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

use RiotAPI\Base\Exceptions\GeneralException;
use RiotAPI\Base\Objects;
use RiotAPI\Tests\RiotAPITestCase;

class DataTransferObject extends Objects\ApiObject
{
    public int $a;

    public string $b;

    public ?DataTransferObject $c;
}

class ApiObjectTest extends RiotAPITestCase
{
    public function testGetIterablePropertyName()
    {
        $propName = Objects\ApiObject::getIterablePropertyName('/** @iterable $property */');
        $this->assertSame('property', $propName);
    }

    public function testGetIterablePropertyName_False()
    {
        $propName = Objects\ApiObject::getIterablePropertyName('/** @no-iterable-here */');
        $this->assertNull($propName);
    }

    /** @var DataTransferObject $customDataType */
    public DataTransferObject $customDataType;

    public function testGetPropertyDataType()
    {
        $property = new ReflectionProperty($this, "customDataType");
        $dataType = Objects\ApiObject::getPropertyDataType($property);
        $this->assertEquals('DataTransferObject', $dataType->class);
        $this->assertFalse($dataType->isArray);
    }

    public DataTransferObject $customDataTypeWithoutComment;

    public function testGetPropertyDataType_WithoutComment()
    {
        $property = new ReflectionProperty($this, "customDataTypeWithoutComment");
        $dataType = Objects\ApiObject::getPropertyDataType($property);
        $this->assertEquals('DataTransferObject', $dataType->class);
        $this->assertFalse($dataType->isArray);
    }

    /** @var DataTransferObject[] $customDataTypeArray */
    public array $customDataTypeArray;

    public function testGetPropertyDataType_Array()
    {
        $property = new ReflectionProperty($this, "customDataTypeArray");
        $dataType = Objects\ApiObject::getPropertyDataType($property);
        $this->assertEquals('DataTransferObject', $dataType->class);
        $this->assertTrue($dataType->isArray);
    }

    /** @var int $simpleDataType */
    public int $simpleDataType;

    public function testGetPropertyDataType_False()
    {
        $property = new ReflectionProperty($this, "simpleDataType");
        $dataType = Objects\ApiObject::getPropertyDataType($property);
        $this->assertNull($dataType);
    }

    public function testInstantiate()
    {
        $array = [
            "a" => 1,
            "b" => "hello",
            "c" => null,
        ];
        $obj = new DataTransferObject($array, null);

        $this->assertSame($array, $obj->getData());
        $this->assertEquals($obj->a, $array["a"]);
        $this->assertEquals($obj->b, $array["b"]);
        $this->assertEquals($obj->c, $array["c"]);
    }

    public function testInstantiate_Complex()
    {
        $array = [
            "a" => 1,
            "b" => "hello",
            "c" => [
                "a" => 2,
                "b" => "greetings",
            ],
        ];
        $obj = new DataTransferObject($array, null);

        $this->assertSame($array, $obj->getData());
        $this->assertTrue($obj->c instanceof DataTransferObject);
        $this->assertEquals($obj->c->a, $array["c"]["a"]);
        $this->assertEquals($obj->c->b, $array["c"]["b"]);
    }

    public function testInstantiate_MissingProperty()
    {
        $this->expectException(GeneralException::class);
        $this->expectExceptionMessage("Failed processing property x of DataTransferObject");

        $array = [
            "x" => 1,
        ];
        new DataTransferObject($array, null);
    }

}
