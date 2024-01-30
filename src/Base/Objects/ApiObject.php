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

use Exception;
use ReflectionClass;
use ReflectionException;
use RiotAPI\Base\BaseAPI;
use RiotAPI\Base\Exceptions\GeneralException;
use stdClass;


/**
 *   Class ApiObject
 *
 * @package RiotAPI\LeagueAPI\Objects
 */
abstract class ApiObject implements IApiObject
{
	/**
	 *   ApiObject constructor.
	 *
	 * @param array $data
	 * @param BaseAPI|null $api
	 * @throws ReflectionException
	 */
	public function __construct(array $data, BaseAPI $api = null)
	{
		// Tries to assigns data to class properties
		$selfRef = new ReflectionClass($this);
		$namespace = $selfRef->getNamespaceName();
		$iterableProp = $this instanceof ApiObjectIterable
			? self::getIterablePropertyName($selfRef->getDocComment())
			: false;

		foreach ($data as $property => $value)
		{
			try
			{
				if ($propRef = $selfRef->getProperty($property))
				{
					//  Object has required property, time to discover if it's
					$dataType = self::getPropertyDataType($propRef);
					if ($dataType != false && is_array($value))
					{
						//  Property is special DataType
						$newRef = new ReflectionClass("$namespace\\$dataType->class");
						if ($dataType->isArray)
						{
							//  Assign initial array
							$this->$property = [];
							//  Property is array of special DataType (another API object)
							foreach ($value as $identifier => $d)
								$this->$property[$identifier] = $newRef->newInstance($d, $api);
						}
						else
						{
							//  Property is special DataType (another API object)
							$this->$property = $newRef->newInstance($value, $api);
						}
					}
					else
					{
						//  Property is general value
						$this->$property = $value;
					}
				}

				if ($this instanceof ApiObjectIterable && $iterableProp == $property)
					$this->_iterable = $this->$property;
			}
				//  If property does not exist
			catch (ReflectionException $exception)
			{
				if (getenv("IS_UNIT_TESTING"))
				{
					throw new GeneralException("Failed processing property $property of $selfRef->name.", previous: $exception);
				}
				else
				{
					trigger_error($exception->getMessage(), E_USER_WARNING);
				}
			}
		}

		$this->_data = $data;

		//  Is API reference passed?
		if ($api)
		{
			//  Gets declared extensions
			$objectExtensions = $api->getSetting($api::SET_EXTENSIONS);
			//  Is there extension for this class?
			if (isset($objectExtensions[$selfRef->getName()]) && $extension = $objectExtensions[$selfRef->getName()])
			{
				$extension = new ReflectionClass($extension);
				$this->_extension = @$extension->newInstanceArgs([ &$this, &$api ]);
			}
		}
	}

	/**
	 *   Returns name of iterable property specified in PHPDoc comment.
	 *
	 * @param string $phpDocComment
	 *
	 * @return string|null
	 */
	public static function getIterablePropertyName( string $phpDocComment ): ?string
	{
		preg_match('/@iterable\s\$([\w]+)/', $phpDocComment, $matches);
		if (isset($matches[1]))
			return $matches[1];

		return null;
	}

	/**
	 *   Returns data of linkable property specified in PHPDoc comment.
	 *
	 * @param string $phpDocComment
	 *
	 * @return array|null
	 */
	public static function getLinkablePropertyData( string $phpDocComment ): ?array
	{
		preg_match('/@linkable\s(?<function>[\w]+)(?:\(\$(?<parameter>[\w]+)+?\))?/', $phpDocComment, $matches);

		// Filter only named capture groups
		$matches = array_filter($matches, function ($v, $k) { return is_string($k); }, ARRAY_FILTER_USE_BOTH);
		if (@$matches['function'] && @$matches['parameter'])
			return $matches;

		return null;
	}

    /**
     *   Returns DataType details based on reflected property.
     *
     * @param \ReflectionProperty $property
     *
     * @return stdClass|null
     */
	public static function getPropertyDataType(\ReflectionProperty $property): ?stdClass
	{
		$o = new stdClass();

		preg_match('/@var\s+(\w+)(\[])?/', $property->getDocComment(), $matches);

		$o->class = isset($matches[1]) ? $matches[1] : null;
		$o->isArray = isset($matches[2]);

        if ($o->class == null)
        {
            $nameParts = explode("\\", $property->getType()->getName());
            $o->class = end($nameParts);
        }

		if (in_array($o->class, [ 'integer', 'int', 'string', 'bool', 'boolean', 'double', 'float', 'array' ]))
			return null;

		return $o;
	}


	/**
	 *   This variable contains all the data in an array.
	 *
	 * @var array
	 * @internal
	 */
	protected array $_data = [];

	/**
	 *   Gets all the original data fetched from LeagueAPI.
	 *
	 * @return array
	 */
	public function getData(): array
	{
		return $this->_data;
	}


	/**
	 *   Object extender.
	 *
	 * @var IApiObjectExtension|null
	 * @internal
	 */
	protected ?IApiObjectExtension $_extension = null;

	/**
	 *   Magic call method used for calling ObjectExtender methods.
	 *
	 * @param $name
	 * @param $arguments
	 *
	 * @return mixed
	 * @throws GeneralException
	 */
	public function __call( $name, $arguments )
	{
		if (!$this->_extension)
			throw new GeneralException("Method '$name' not found, no extension exists for this ApiObject.");

		try
		{
			$r = new ReflectionClass($this->_extension);
			return $r->getMethod($name)->invokeArgs($this->_extension, $arguments);
		}
		catch (Exception $ex)
		{
			throw new GeneralException("Method '$name' failed to be executed: " . $ex->getMessage(), 0, $ex);
		}
	}
}
