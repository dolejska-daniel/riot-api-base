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

namespace RiotAPI\Base;

use RiotAPI\Base\Definitions\AsyncRequest;
use RiotAPI\Base\Definitions\CallCacheControl;
use RiotAPI\Base\Definitions\ICallCacheControl;
use RiotAPI\Base\Definitions\IPlatform;
use RiotAPI\Base\Definitions\Platform;
use RiotAPI\Base\Definitions\IRegion;
use RiotAPI\Base\Definitions\Region;
use RiotAPI\Base\Definitions\IRateLimitControl;
use RiotAPI\Base\Definitions\RateLimitControl;
use RiotAPI\Base\Exceptions\GeneralException;
use RiotAPI\Base\Exceptions\RequestException;
use RiotAPI\Base\Exceptions\ServerException;
use RiotAPI\Base\Exceptions\ServerLimitException;
use RiotAPI\Base\Exceptions\SettingsException;
use RiotAPI\Base\Exceptions\DataNotFoundException;
use RiotAPI\Base\Exceptions\ForbiddenException;
use RiotAPI\Base\Exceptions\UnauthorizedException;
use RiotAPI\Base\Exceptions\UnsupportedMediaTypeException;
use RiotAPI\Base\Objects\IApiObjectExtension;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Exception as GuzzleHttpExceptions;
use function GuzzleHttp\Promise\settle;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 *   Class BaseAPI
 *
 * @package BaseAPI
 */
abstract class BaseAPI
{
	/**
	 * Constants for cURL requests.
	 */
	const
		METHOD_GET    = 'GET',
		METHOD_POST   = 'POST',
		METHOD_PUT    = 'PUT',
		METHOD_DELETE = 'DELETE';

	/**
	 * Settings constants.
	 */
	const
		SET_REGION                   = 'SET_REGION',
		SET_ORIG_REGION              = 'SET_ORIG_REGION',
		SET_PLATFORM                 = 'SET_PLATFORM',              /** Set internally by setting region **/
		SET_VERIFY_SSL               = 'SET_VERIFY_SSL',            /** Specifies whether or not to verify SSL (verification often fails on localhost) **/
		SET_KEY                      = 'SET_KEY',                   /** API key used by default **/
		SET_KEY_INCLUDE_TYPE         = 'SET_KEY_INCLUDE_TYPE',      /** API key request include type (header, query) **/
		SET_CACHE_PROVIDER           = 'SET_CACHE_PROVIDER',        /** Specifies CacheProvider class name **/
		SET_CACHE_PROVIDER_PARAMS    = 'SET_CACHE_PROVIDER_PARAMS', /** Specifies parameters passed to CacheProvider class when initializing **/
		SET_CACHE_RATELIMIT          = 'SET_CACHE_RATELIMIT',       /** Used to set whether or not to saveCallData and check API key's rate limit **/
		SET_CACHE_CALLS              = 'SET_CACHE_CALLS',           /** Used to set whether or not to temporary saveCallData API call's results **/
		SET_CACHE_CALLS_LENGTH       = 'SET_CACHE_CALLS_LENGTH',    /** Specifies for how long are call results saved **/
		SET_EXTENSIONS               = 'SET_EXTENSIONS',            /** Specifies ApiObject's extensions **/
		SET_GUZZLE_CLIENT_CFG        = 'SET_GUZZLE_CLIENT_CFG',     /** Specifies configuration passed to Guzzle library client. */
		SET_GUZZLE_REQ_CFG           = 'SET_GUZZLE_REQ_CFG',        /** Specifies configuration passed to Guzzle request. */
		SET_CALLBACKS_BEFORE         = 'SET_CALLBACKS_BEFORE',
		SET_CALLBACKS_AFTER          = 'SET_CALLBACKS_AFTER',
		SET_API_BASEURL              = 'SET_API_BASEURL',
		SET_USE_DUMMY_DATA           = 'SET_USE_DUMMY_DATA',
		SET_SAVE_DUMMY_DATA          = 'SET_SAVE_DUMMY_DATA',
		SET_DEBUG                    = 'SET_DEBUG';

	/**
	 * Available API key inclusion options.
	 */
	const
		KEY_AS_QUERY_PARAM = 'keyInclude:query',
		KEY_AS_HEADER      = 'keyInclude:header';

	/**
	 * Cache constants used to identify cache target.
	 */
	const
		CACHE_KEY_RLC = 'rate-limit.cache',
		CACHE_KEY_CCC = 'api-calls.cache';

	/**
	 * Available API headers.
	 */
	const
		HEADER_API_KEY                = 'X-Riot-Token',
		HEADER_RATELIMIT_TYPE         = 'X-Rate-Limit-Type',
		HEADER_METHOD_RATELIMIT       = 'X-Method-Rate-Limit',
		HEADER_METHOD_RATELIMIT_COUNT = 'X-Method-Rate-Limit-Count',
		HEADER_APP_RATELIMIT          = 'X-App-Rate-Limit',
		HEADER_APP_RATELIMIT_COUNT    = 'X-App-Rate-Limit-Count',
		HEADER_DEPRECATION            = 'X-Riot-Deprecated';

	const
		//  List of required setting keys
		SETTINGS_REQUIRED = [
			self::SET_KEY,
			self::SET_REGION,
		],
		//  List of allowed setting keys
		SETTINGS_ALLOWED = [
			self::SET_KEY,
			self::SET_REGION,
			self::SET_VERIFY_SSL,
			self::SET_KEY_INCLUDE_TYPE,
			self::SET_CACHE_PROVIDER,
			self::SET_CACHE_PROVIDER_PARAMS,
			self::SET_CACHE_RATELIMIT,
			self::SET_CACHE_CALLS,
			self::SET_CACHE_CALLS_LENGTH,
			self::SET_USE_DUMMY_DATA,
			self::SET_SAVE_DUMMY_DATA,
			self::SET_EXTENSIONS,
			self::SET_GUZZLE_CLIENT_CFG,
			self::SET_GUZZLE_REQ_CFG,
			self::SET_CALLBACKS_BEFORE,
			self::SET_CALLBACKS_AFTER,
			self::SET_API_BASEURL,
			self::SET_DEBUG,
		],
		SETTINGS_INIT_ONLY = [
			self::SET_API_BASEURL,
			self::SET_CACHE_PROVIDER,
			self::SET_CACHE_PROVIDER_PARAMS,
		];

	/**
	 *   Available resource list.
	 *
	 * @var array $resources
	 */
	protected $resources = [];

	/**
	 *   Contains current settings.
	 *
	 * @var array $settings
	 */
	protected $settings = array(
		self::SET_API_BASEURL       => '.api.riotgames.com',
		self::SET_KEY_INCLUDE_TYPE  => self::KEY_AS_HEADER,
		self::SET_USE_DUMMY_DATA    => false,
		self::SET_SAVE_DUMMY_DATA   => false,
		self::SET_VERIFY_SSL        => true,
		self::SET_DEBUG             => false,
		self::SET_GUZZLE_CLIENT_CFG => [],
		self::SET_GUZZLE_REQ_CFG    => [],
	);

	/** @var IRegion $regions */
	public $regions;

	/** @var IPlatform $platforms */
	public $platforms;


	/** @var CacheItemPoolInterface $cache */
	protected $cache;


	/** @var IRateLimitControl $rlc */
	protected $rlc;

	/** @var int $rlc_savetime */
	protected $rlc_savetime = 3600;

	/** @var ICallCacheControl $ccc */
	protected $ccc;

	/** @var int $ccc_savetime */
	protected $ccc_savetime = 60;


	/** @var string $used_key */
	protected $used_key = self::SET_KEY;

	/** @var string $used_method */
	protected $used_method;

	/** @var string $endpoint */
	protected $endpoint;

	/** @var string $resource */
	protected $resource;

	/** @var string $resource_endpoint */
	protected $resource_endpoint;


	/** @var Client $guzzle */
	protected $guzzle;

	/** @var Client[] $async_clients */
	protected $async_clients = [];

	/** @var AsyncRequest[] $async_requests */
	protected $async_requests = [];

	/** @var AsyncRequest $next_async_request */
	protected $next_async_request;


	/** @var array $query_data */
	protected $query_data = [];

	/** @var array $post_data */
	protected $post_data = [];

	/** @var array $result_data */
	protected $result_data;

	/** @var string $result_data */
	protected $result_data_raw;

	/** @var array $result_headers */
	protected $result_headers;

	/** @var int $result_code */
	protected $result_code;

	/** @var callable[] $beforeCall */
	protected $beforeCall = [];

	/** @var callable[] $afterCall */
	protected $afterCall = [];


	/**
	 *   BaseAPI constructor.
	 *
	 * @param array     $settings
	 * @param IRegion   $custom_regionDataProvider
	 * @param IPlatform $custom_platformDataProvider
	 *
	 * @throws SettingsException
	 * @throws GeneralException
	 */
	public function __construct(array $settings, IRegion $custom_regionDataProvider = null, IPlatform $custom_platformDataProvider = null)
	{
		//  Checks if required settings are present
		$settings_required = array_merge(self::SETTINGS_REQUIRED, $this::SETTINGS_REQUIRED);
		foreach ($settings_required as $key)
			if (array_search($key, array_keys($settings), true) === false)
				throw new SettingsException("Required settings parameter '$key' is missing!");

		//  Assigns allowed settings
		$settings_allowed = array_merge(self::SETTINGS_ALLOWED, $this::SETTINGS_ALLOWED);
		foreach ($settings_allowed as $key)
			if (isset($settings[$key]))
				$this->settings[$key] = $settings[$key];

		//  Checks SET_KEY_INCLUDE_TYPE value
		if (isset($settings[self::SET_KEY_INCLUDE_TYPE])
			&& in_array($settings[self::SET_KEY_INCLUDE_TYPE], [self::KEY_AS_HEADER, self::KEY_AS_QUERY_PARAM], true) == false)
		{
			throw new SettingsException("Value of settings parameter '" . self::SET_KEY_INCLUDE_TYPE . "' is not valid.");
		}

		$this->regions = $custom_regionDataProvider ?: new Region();
		$this->platforms = $custom_platformDataProvider ?: new Platform();
		$this->guzzle = new Client($this->getSetting(self::SET_GUZZLE_CLIENT_CFG));

		$this->_setupDefaultCacheProviderSettings();

		//  Setup API object extension classes
		if ($this->isSettingSet(self::SET_EXTENSIONS))
			$this->_setupExtensions();

		//  Some caching will be made, let's set up cache provider
		if ($this->isSettingSet(self::SET_CACHE_CALLS) || $this->isSettingSet(self::SET_CACHE_RATELIMIT))
			$this->_setupCacheProvider();

		//  Call data are going to be cached
		if ($this->isSettingSet(self::SET_CACHE_CALLS))
			$this->_setupCacheCalls();

		//  Set up before calls callbacks
		$this->_setupBeforeCalls();

		//  Set up afterl calls callbacks
		$this->_setupAfterCalls();

		//  Sets platform based on current region
		$this->setSetting(self::SET_PLATFORM, $this->platforms->getPlatformName($this->getSetting(self::SET_REGION)));
	}

	/**
	 *   Initializes library cache provider.
	 *
	 * @throws SettingsException
	 */
	protected function _setupExtensions()
	{
		if (!is_array($this->getSetting(self::SET_EXTENSIONS)))
			throw new SettingsException("Value of settings parameter '" . self::SET_EXTENSIONS . "' is not valid. Array expected.");

		foreach ($this->getSetting(self::SET_EXTENSIONS) as $api_object => $extender)
		{
			try
			{
				$ref = new \ReflectionClass($extender);
				if ($ref->implementsInterface(IApiObjectExtension::class) == false)
					throw new SettingsException("ObjectExtender '$extender' does not implement IApiObjectExtension interface.");

				if ($ref->isInstantiable() == false)
					throw new SettingsException("ObjectExtender '$extender' is not instantiable.");
			}
			catch (\ReflectionException $ex)
			{
				throw new SettingsException("Value of settings parameter '" . self::SET_EXTENSIONS . "' is not valid.", 0, $ex);
			}
		}
	}

	protected function _setupDefaultCacheProviderSettings()
	{
		//  If something should be cached
		if (!$this->isSettingSet(self::SET_CACHE_PROVIDER))
		{
			$this->settings[self::SET_CACHE_PROVIDER] = FilesystemAdapter::class;
		}

		if ($this->getSetting(self::SET_CACHE_PROVIDER) === FilesystemAdapter::class
			&& !$this->isSettingSet(self::SET_CACHE_PROVIDER_PARAMS))
		{
			$this->settings[self::SET_CACHE_PROVIDER_PARAMS] =  [
				"RiotAPI-Default",              // namespace
				0,                              // default lifetime
				sys_get_temp_dir() . "/RiotAPI" // directory
			];
		}
	}

	/**
	 *   Initializes library cache provider.
	 *
	 * @throws SettingsException
	 */
	protected function _setupCacheProvider()
	{
		$this->cache = $this->_initializeCacheProvider(
			$this->getSetting(self::SET_CACHE_PROVIDER),
			$this->getSetting(self::SET_CACHE_PROVIDER_PARAMS, [])
		);

		//  Loads existing cache or creates new storages
		$this->loadCache();
	}

	/**
	 * @param $cacheProviderClass
	 * @param array $params
	 *
	 * @return CacheItemPoolInterface
	 *
	 * @throws SettingsException
	 */
	protected function _initializeCacheProvider($cacheProviderClass, array $params): CacheItemPoolInterface
	{
		try
		{
			//  Creates reflection of specified cache provider (can be user-made)
			$cacheProvider = new \ReflectionClass($cacheProviderClass);
			//  Checks if this cache provider implements required interface
			if (!$cacheProvider->implementsInterface(CacheItemPoolInterface::class))
				throw new SettingsException("Provided CacheProvider does not implement Psr\Cache\CacheItemPoolInterface (PSR-6)");

			//  and creates new instance of this cache provider
			/** @var CacheItemPoolInterface $instance */
			$instance = $cacheProvider->newInstanceArgs($params);
			return $instance;
		}
		catch (\ReflectionException $ex)
		{
			//  probably problem when instantiating the class
			throw new SettingsException("Failed to initialize CacheProvider class: {$ex->getMessage()}.", $ex->getCode(), $ex);
		}
		catch (\Throwable $ex)
		{
			//  something went wrong when initializing the class - invalid settings, etc.
			throw new SettingsException("CacheProvider class failed to be initialized: {$ex->getMessage()}.", $ex->getCode(), $ex);
		}
	}

	/**
	 *   Initializes library call caching.
	 *
	 * @throws SettingsException
	 */
	public function _setupCacheCalls()
	{
		if ($this->isSettingSet(self::SET_CACHE_CALLS_LENGTH))
		{
			$lengths = $this->getSetting(self::SET_CACHE_CALLS_LENGTH);

			//  Resource caching lengths are specified
			if (is_array($lengths))
			{
				array_walk($lengths, function ($value, $key) {
					if ((!is_integer($value) && !is_null($value)) || strpos($key, ':') == false)
						throw new SettingsException("Value of settings parameter '" . self::SET_CACHE_CALLS_LENGTH . "' is not valid.");
				});
			}
			elseif (!is_integer($lengths))
				throw new SettingsException("Value of settings parameter '" . self::SET_CACHE_CALLS_LENGTH . "' is not valid.");

			if (is_array($lengths))
			{
				//  The value is array, let's check it
				$new_value = [];
				$resources = $this->resources;
				foreach ($resources as $resource)
				{
					if (isset($lengths[$resource]))
					{
						if ($lengths[$resource] > $this->ccc_savetime)
							$this->ccc_savetime = $lengths[$resource];

						$new_value[$resource] = $lengths[$resource];
					}
					else
						$new_value[$resource] = null;
				}

				$this->setSetting(self::SET_CACHE_CALLS_LENGTH, $new_value);
			}
			else
			{
				//  The value is numeric, lets set the same limit to all resources
				$new_value = [];
				$resources = $this->resources;
				$this->ccc_savetime = $lengths;

				foreach ($resources as $resource)
					$new_value[$resource] = $lengths;

				$this->setSetting(self::SET_CACHE_CALLS_LENGTH, $new_value);
			}
		}
	}

	/**
	 *   Sets up internal callbacks - before the call is made.
	 *
	 * @throws SettingsException
	 */
	protected function _setupBeforeCalls()
	{
		//  API rate limit check before call is made
		$this->beforeCall[] = function () {
			if ($this->getSetting(self::SET_CACHE_RATELIMIT) && $this->rlc != false)
				if ($this->rlc->canCall($this->getSetting($this->used_key), $this->getSetting(self::SET_REGION), $this->getResource(), $this->getResourceEndpoint()) == false)
					throw new ServerLimitException('API call rate limit would be exceeded by this call.');
		};

		$callbacks = $this->getSetting(self::SET_CALLBACKS_BEFORE, []);
		if (is_array($callbacks) == false)
			$callbacks = [$callbacks];

		foreach ($callbacks as $c)
		{
			if (is_callable($c) == false)
				throw new SettingsException("Provided value of '" . self::SET_CALLBACKS_BEFORE . "' option is not valid.");

			$this->beforeCall[] = $c;
		}
	}

	/**
	 *   Sets up internal callbacks - after the call is made.
	 *
	 * @throws SettingsException
	 */
	protected function _setupAfterCalls()
	{
		$this->afterCall[] = function () {
			if ($this->isSettingSet(self::SET_CACHE_RATELIMIT) && $this->rlc != false)
			{
				//  Save ratelimits received with this request if RateLimit cache is enabled
				$this->rlc->registerLimits($this->getSetting($this->used_key), $this->getSetting(self::SET_REGION), $this->getResourceEndpoint(), @$this->result_headers[self::HEADER_APP_RATELIMIT], @$this->result_headers[self::HEADER_METHOD_RATELIMIT]);
				//  Register, that call has been made if RateLimit cache is enabled
				$this->rlc->registerCall($this->getSetting($this->used_key), $this->getSetting(self::SET_REGION), $this->getResourceEndpoint(), @$this->result_headers[self::HEADER_APP_RATELIMIT_COUNT], @$this->result_headers[self::HEADER_METHOD_RATELIMIT_COUNT]);
			}
		};

		//  Save result data, if CallCache is enabled and when the old result has expired
		$this->afterCall[] = function () {
			$requestHash = func_get_arg(2);
			if ($this->getSetting(self::SET_CACHE_CALLS, false) && $this->ccc != false && $this->ccc->isCallCached($requestHash) == false)
			{
				//  Get information for how long to save the data
				if ($timeInterval = @$this->getSetting(self::SET_CACHE_CALLS_LENGTH)[$this->getResource()])
					$this->ccc->saveCallData($requestHash, $this->result_data_raw, $timeInterval);
			}
		};

		//  Save result data as new DummyData if enabled and if data does not already exist
		$this->afterCall[] = function () {
			$dummyData_file = func_get_arg(3);
			if ($this->getSetting(self::SET_SAVE_DUMMY_DATA, false) && file_exists($dummyData_file) == false)
				$this->_saveDummyData($dummyData_file);
		};

		//  Save newly cached data
		$this->afterCall[] = function () {
			if ($this->getSetting(self::SET_CACHE_CALLS, false) || $this->getSetting(self::SET_CACHE_RATELIMIT, false))
				$this->saveCache();
		};

		$callbacks = $this->getSetting(self::SET_CALLBACKS_AFTER, []);
		if (is_array($callbacks) == false)
			$callbacks = [$callbacks];

		foreach ($callbacks as $c)
		{
			if (is_callable($c) == false)
				throw new SettingsException("Provided value of '" . self::SET_CALLBACKS_AFTER . "' option is not valid.");

			$this->afterCall[] = $c;
		}
	}

	/**
	 *   LeagueAPI destructor.
	 *   Saves cache files (if needed) before destroying the object.
	 */
	public function __destruct()
	{
		$this->saveCache();
	}

	/**
	 *   Loads required cache objects
	 *
	 * @internal
	 */
	protected function loadCache()
	{
		if ($this->getSetting(self::SET_CACHE_RATELIMIT, false))
		{
			//  ratelimit cache enabled, try to load already existing object
			$rlc = $this->cache->getItem(self::CACHE_KEY_RLC);
			if ($rlc->isHit())
			{
				//  nothing loaded, creating new instance
				$rlc = $rlc->get();
			}
			else
			{
				//  nothing loaded, creating new instance
				$rlc = new RateLimitControl($this->regions);
			}

			$this->rlc = $rlc;
		}

		if ($this->getSetting(self::SET_CACHE_CALLS, false))
		{
			//  call cache enabled, try to load already existing object
			$ccc = $this->cache->getItem(self::CACHE_KEY_CCC);
			if ($ccc->isHit())
			{
				//  nothing loaded, creating new instance
				$ccc = $ccc->get();
			}
			else
			{
				//  nothing loaded, creating new instance
				$ccc = new CallCacheControl();
			}

			$this->ccc = $ccc;
		}
	}

	/**
	 *   Saves required cache objects.
	 *
	 * @internal
	 */
	protected function saveCache(): bool
	{
		if (!$this->cache)
			return false;

		if ($this->getSetting(self::SET_CACHE_RATELIMIT, false))
		{
			// Save RateLimitControl
			$rlc = $this->cache->getItem(self::CACHE_KEY_RLC);
			$rlc->set($this->rlc);
			$rlc->expiresAfter($this->rlc_savetime);

			$this->cache->saveDeferred($rlc);
		}

		if ($this->getSetting(self::SET_CACHE_CALLS, false))
		{
			// Save CallCacheControl
			$ccc = $this->cache->getItem(self::CACHE_KEY_CCC);
			$ccc->set($this->ccc);
			$ccc->expiresAfter($this->ccc_savetime);

			$this->cache->saveDeferred($ccc);
		}

		return $this->cache->commit();
	}

	/**
	 *   Removes all cached data.
	 *
	 * @return bool
	 */
	public function clearCache(): bool
	{
		if ($this->rlc)
			$this->rlc->clear();

		if ($this->ccc)
			$this->ccc->clear();

		return $this->cache->clear();
	}

	/**
	 *   Returns vaue of requested key from settings.
	 *
	 * @param string     $name
	 * @param mixed|null $defaultValue
	 *
	 * @return mixed
	 */
	public function getSetting(string $name, $defaultValue = null)
	{
		return $this->isSettingSet($name)
			? $this->settings[$name]
			: $defaultValue;
	}

	/**
	 *   Sets new value for specified key in settings.
	 *
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return LeagueAPI
	 * @throws SettingsException
	 */
	public function setSetting(string $name, $value): self
	{
		if (in_array($name, self::SETTINGS_INIT_ONLY + $this::SETTINGS_INIT_ONLY))
			throw new SettingsException("Settings option '$name' can only be set on initialization of the library.");

		$this->settings[$name] = $value;
		return $this;
	}

	/**
	 *   Sets new values for specified set of keys in settings.
	 *
	 * @param array $values
	 *
	 * @return LeagueAPI
	 * @throws SettingsException
	 */
	public function setSettings(array $values): self
	{
		foreach ($values as $name => $value)
			$this->setSetting($name, $value);

		return $this;
	}

	/**
	 *   Checks if specified settings key is set.
	 *
	 * @param string $name
	 *
	 * @return bool
	 */
	public function isSettingSet(string $name): bool
	{
		return isset($this->settings[$name]) && !is_null($this->settings[$name]);
	}

	/**
	 *   Sets new region to be used on API calls.
	 *
	 * @param string $region
	 *
	 * @return LeagueAPI
	 * @throws SettingsException
	 * @throws GeneralException
	 */
	public function setRegion(string $region): self
	{
		$this->setSetting(self::SET_REGION, $this->regions->getRegionName($region));
		$this->setSetting(self::SET_PLATFORM, $this->platforms->getPlatformName($region));
		return $this;
	}

	/**
	 *   Sets temporary region to be used on API calls. Saves current region.
	 *
	 * @param string $tempRegion
	 *
	 * @return LeagueAPI
	 * @throws SettingsException
	 * @throws GeneralException
	 */
	public function setTemporaryRegion(string $tempRegion): self
	{
		$this->setSetting(self::SET_ORIG_REGION, $this->getSetting(self::SET_REGION));
		$this->setSetting(self::SET_REGION, $this->regions->getRegionName($tempRegion));
		$this->setSetting(self::SET_PLATFORM, $this->platforms->getPlatformName($tempRegion));
		return $this;
	}

	/**
	 *   Unets temporary region and returns original region.
	 *
	 * @return LeagueAPI
	 * @throws SettingsException
	 * @throws GeneralException
	 */
	public function unsetTemporaryRegion(): self
	{
		if ($this->isSettingSet(self::SET_ORIG_REGION))
		{
			$region = $this->getSetting(self::SET_ORIG_REGION);
			$this->setSetting(self::SET_REGION, $region);
			$this->setSetting(self::SET_PLATFORM, $this->platforms->getPlatformName($region));
			$this->setSetting(self::SET_ORIG_REGION, null);
		}
		return $this;
	}

	/**
	 * The AMERICAS routing value serves NA, BR, LAN, LAS, and OCE.
	 * The ASIA routing value serves KR and JP.
	 * The EUROPE routing value serves EUNE, EUW, TR, and RU.
	 *
	 * @param string $platform
	 *
	 * @throws GeneralException
	 * @throws SettingsException
	 */
	public function setTemporaryContinentRegionForPlatform(string $platform)
	{
		$current_platform = $this->getSetting(self::SET_PLATFORM);
		$continent_region = $this->platforms->getContinentRegion($current_platform);
		$this->setTemporaryRegion($continent_region);
	}

	/**
	 *   Sets API key type for next API call.
	 *
	 * @param string $keyType
	 *
	 * @return LeagueAPI
	 */
	protected function useKey(string $keyType): self
	{
		$this->used_key = $this->isSettingSet($keyType) ? $keyType : self::SET_KEY;
		return $this;
	}

	/**
	 *   Sets call target for script.
	 *
	 * @param string $endpoint
	 *
	 * @return LeagueAPI
	 */
	protected function setEndpoint(string $endpoint): self
	{
		$this->endpoint = $endpoint;
		return $this;
	}

	/**
	 *   Sets call resource for target endpoint.
	 *
	 * @param string $resource
	 * @param string $endpoint
	 *
	 * @return LeagueAPI
	 */
	protected function setResource(string $resource, string $endpoint): self
	{
		$this->resource = $resource;
		$this->resource_endpoint = $endpoint;
		return $this;
	}

	/**
	 *   Returns call resource for last call.
	 *
	 * @return string
	 */
	protected function getResource(): string
	{
		return $this->resource;
	}

	/**
	 *   Returns call resource and endpoint for last call.
	 *
	 * @return string
	 */
	protected function getResourceEndpoint(): string
	{
		return $this->resource . $this->resource_endpoint;
	}

	/**
	 *   Adds GET parameter to called URL.
	 *
	 * @param string      $name
	 * @param string|null $value
	 *
	 * @return LeagueAPI
	 */
	protected function addQuery(string $name, $value): self
	{
		if (!is_null($value))
		{
			$this->query_data[$name] = $value;
		}

		return $this;
	}

	/**
	 *   Sets POST/PUT data.
	 *
	 * @param string $data
	 *
	 * @return LeagueAPI
	 */
	protected function setData(string $data): self
	{
		$this->post_data = $data;
		return $this;
	}

	/**
	 *   Returns raw getResult data from the last call.
	 *
	 * @return mixed
	 */
	public function getResult()
	{
		return $this->result_data;
	}

	/**
	 *   Returns HTTP response headers from the last call.
	 *
	 * @return array
	 */
	public function getResultHeaders()
	{
		return $this->result_headers;
	}

	/**
	 *   Returns current API request limits.
	 *
	 * @return array
	 */
	public function getCurrentLimits()
	{
		return $this->rlc->getCurrentStatus($this->getSetting($this->used_key), $this->getSetting(self::SET_REGION), $this->getResourceEndpoint());
	}

	/**
	 *   Adds next API call to given async request group. Sending needs to be
	 * initiated by calling commitAsync function.
	 *
	 * @param callable|null $onFulfilled
	 * @param callable|null $onRejected
	 * @param string        $group
	 *
	 * @return LeagueAPI
	 */
	public function nextAsync(callable $onFulfilled = null, callable $onRejected = null, string $group = "default"): self
	{
		$client = @$this->async_clients[$group];
		if (!$client)
			$this->async_clients[$group] = $client = new Client($this->getSetting(self::SET_GUZZLE_CLIENT_CFG));

		$this->async_requests[$group][] = $this->next_async_request = new AsyncRequest($client);
		$this->next_async_request->onFulfilled = $onFulfilled;
		$this->next_async_request->onRejected = $onRejected;

		return $this;
	}

	/**
	 *   Initiates async requests from given group. Waits until completed.
	 *
	 * @param string $group
	 */
	public function commitAsync(string $group = "default")
	{
		/** @var AsyncRequest[] $requests */
		$requests = @$this->async_requests[$group] ?: [];
		$promises = array_map(function ($r) { return $r->getPromise(); }, $requests);
		settle($promises)->wait();

		unset($this->async_clients[$group]);
		unset($this->async_requests[$group]);
	}

	/**
	 * @internal
	 *
	 * @param PromiseInterface $promise
	 * @param callable         $resultCallback
	 *
	 * @return null
	 */
	function resolveOrEnqueuePromise(PromiseInterface $promise, callable $resultCallback = null)
	{
		if ($this->next_async_request)
		{
			$promise = $promise->then(function($result) use ($resultCallback) {
				return $resultCallback ? $resultCallback($result) : null;
			});
			$this->next_async_request->setPromise($promise);
			return $this->next_async_request = null;
		}
		return $resultCallback ? $resultCallback($promise->wait()) : null;
	}

	/**
	 * @internal
	 *
	 *   Makes call to LeagueAPI.
	 *
	 * @param string|null $overrideRegion
	 * @param string $method
	 *
	 * @return PromiseInterface
	 * @throws RequestException
	 * @throws ServerException
	 * @throws ServerLimitException
	 * @throws SettingsException
	 * @throws GeneralException
	 */
	protected function makeCall(string $overrideRegion = null, string $method = self::METHOD_GET): PromiseInterface
	{
		if ($overrideRegion)
			$this->setTemporaryRegion($overrideRegion);

		$this->used_method = $method;

		$requestHeaders = [];
		$requestPromise = null;
		$url = $this->_getCallUrl($requestHeaders);
		$requestHash = md5($url);

		$this->_beforeCall($url, $requestHash);

		if (!$requestPromise && $this->getSetting(self::SET_USE_DUMMY_DATA, false))
		{
			// DummyData are supposed to be used
			try
			{
				// try loading the data
				$this->_loadDummyData($responseHeaders, $responseBody, $responseCode);
				$this->processCallResult($responseHeaders, $responseBody, $responseCode);
				$this->_afterCall($url, $requestHash, $this->_getDummyDataFileName());
				$requestPromise = new FulfilledPromise($this->getResult());
			}
			catch (RequestException $ex)
			{
				// loading failed, check whether an actual request should be made
				if ($this->getSetting(self::SET_SAVE_DUMMY_DATA, false) == false)
					// saving is not allowed, dummydata does not exist
					throw $ex;
			}
		}

		if (!$requestPromise && $this->getSetting(self::SET_CACHE_CALLS) && $this->ccc && $this->ccc->isCallCached($requestHash))
		{
			// calls are cached and this request is saved in cache
			$this->processCallResult([], $this->ccc->loadCallData($requestHash), 200);
			$requestPromise = new FulfilledPromise($this->getResult());
		}

		if (!$requestPromise)
		{
			// calls are not cached or this request is not cached
			// perform call to Riot API
			$guzzle = $this->guzzle;
			if ($this->next_async_request)
				$guzzle = $this->next_async_request->client;

			$options = $this->getSetting(self::SET_GUZZLE_REQ_CFG);
			$options[RequestOptions::VERIFY] = $this->getSetting(self::SET_VERIFY_SSL);
			$options[RequestOptions::HEADERS] = $requestHeaders;
			if ($this->post_data)
				$options[RequestOptions::BODY] = $this->post_data;

			if ($this->isSettingSet(self::SET_DEBUG) && $this->getSetting(self::SET_DEBUG))
				$options[RequestOptions::DEBUG] = fopen('php://stderr', 'w');

			// Create HTTP request
			$requestPromise = $guzzle->requestAsync(
				$method,
				$url,
				$options
			);

			$dummyData_file = $this->_getDummyDataFileName();
			$requestPromise = $requestPromise->then(function(ResponseInterface $response) use ($url, $requestHash, $dummyData_file) {
				$this->processCallResult($response->getHeaders(), $response->getBody(), $response->getStatusCode());
				$this->_afterCall($url, $requestHash, $dummyData_file);
				return $this->getResult();
			});
		}

		// If request fails, try to process it and raise exceptions
		$requestPromise = $requestPromise->otherwise(function($ex) {
			/** @var \Exception $ex */

			if ($ex instanceof GuzzleHttpExceptions\RequestException)
			{
				$responseHeaders = [];
				$responseBody    = null;
				$responseCode    = $ex->getCode();

				if ($response = $ex->getResponse())
				{
					$responseHeaders = $response->getHeaders();
					$responseBody    = $response->getBody();
				}

				$this->processCallResult($responseHeaders, $responseBody, $responseCode);
				throw new RequestException("LeagueAPI: Request error occured - {$ex->getMessage()}", $ex->getCode(), $ex);
			}
			elseif ($ex instanceof GuzzleHttpExceptions\ServerException)
			{
				throw new ServerException("LeagueAPI: Server error occured - {$ex->getMessage()}", $ex->getCode(), $ex);
			}

			throw new RequestException("LeagueAPI: Request could not be sent - {$ex->getMessage()}", $ex->getCode(), $ex);
		});

		if ($this->next_async_request)
			return $requestPromise;

		if ($overrideRegion)
			$this->unsetTemporaryRegion();

		$this->query_data = [];
		$this->post_data  = null;

		return $requestPromise;
	}

	/**
	 * @internal
	 *
	 * @param array $response_headers
	 * @param string $response_body
	 * @param int $response_code
	 *
	 * @throws RequestException
	 * @throws ServerException
	 * @throws ServerLimitException
	 */
	protected function processCallResult(array $response_headers = null, string $response_body = null, int $response_code = 0)
	{
		// flatten response headers array from Guzzle
		array_walk($response_headers, function ( &$value ) {
			if (is_array($value) && count($value) == 1)
				$value = $value[0];
		});

		$this->result_code     = $response_code;
		$this->result_headers  = $response_headers;
		$this->result_data_raw = $response_body;
		$this->result_data     = json_decode($response_body, true);

		if (isset($this->result_headers[self::HEADER_DEPRECATION]))
			trigger_error("Used endpoint '{$this->getResourceEndpoint()}' is being deprecated! This endpoint will stop working on " . $this->result_headers[self::HEADER_DEPRECATION] . ".", E_USER_WARNING);

		$message = isset($this->result_data['status']) ? @$this->result_data['status']['message'] : "";
		switch ($response_code)
		{
			case 503:
				throw new ServerException('LeagueAPI: Service is temporarily unavailable.', $response_code);
			case 500:
				throw new ServerException('LeagueAPI: Internal server error occured.', $response_code);
			case 429:
				throw new ServerLimitException("LeagueAPI: Rate limit for this API key was exceeded. $message", $response_code);
			case 415:
				throw new UnsupportedMediaTypeException("LeagueAPI: Unsupported media type. $message", $response_code);
			case 404:
				throw new DataNotFoundException("LeagueAPI: Not Found. $message", $response_code);
			case 403:
				throw new ForbiddenException("LeagueAPI: Forbidden. $message", $response_code);
			case 401:
				throw new UnauthorizedException("LeagueAPI: Unauthorized. $message", $response_code);
			case 400:
				throw new RequestException("LeagueAPI: Request is invalid. $message", $response_code);
			default:
				if ($response_code >= 500)
					throw new ServerException("LeagueAPI: Unspecified error occured ({$response_code}). $message", $response_code);
				if ($response_code >= 400)
					throw new RequestException("LeagueAPI: Unspecified error occured ({$response_code}). $message", $response_code);
		}
	}

	/**
	 * @internal
	 *
	 *   Loads dummy response from file.
	 *
	 * @param $headers
	 * @param $response
	 * @param $response_code
	 *
	 * @throws RequestException
	 */
	public function _loadDummyData(&$headers, &$response, &$response_code)
	{
		$data = @file_get_contents($this->_getDummyDataFileName());
		$data = @unserialize($data);
		if (!$data)
			throw new RequestException("No DummyData available for call. File '{$this->_getDummyDataFileName()}' failed to be parsed.");

		$headers       = $data['headers'];
		$response      = $data['response'];
		$response_code = $data['code'];
	}

	/**
	 * @internal
	 *
	 *   Saves dummy response to file.
	 *
	 * @param string|null $dummyData_file
	 */
	public function _saveDummyData(string $dummyData_file = null)
	{
		file_put_contents($dummyData_file ?: $this->_getDummyDataFileName(), serialize([
			'headers'  => $this->result_headers,
			'response' => $this->result_data_raw,
			'code'     => $this->result_code,
		]));
	}

	/**
	 * @internal
	 *
	 *   Processes 'beforeCall' callbacks.
	 *
	 * @param string $url
	 * @param string $requestHash
	 *
	 * @throws RequestException
	 */
	protected function _beforeCall(string $url, string $requestHash)
	{
		foreach ($this->beforeCall as $function)
		{
			if ($function($this, $url, $requestHash) === false)
			{
				throw new RequestException("Request terminated by beforeCall function.");
			}
		}
	}

	/**
	 * @internal
	 *
	 *   Processes 'afterCall' callbacks.
	 *
	 * @param string $url
	 * @param string $requestHash
	 * @param string $dummyData_file
	 */
	protected function _afterCall(string $url, string $requestHash, string $dummyData_file)
	{
		foreach ($this->afterCall as $function)
		{
			$function($this, $url, $requestHash, $dummyData_file);
		}
	}

	/**
	 * @internal
	 *
	 *   Builds API call URL based on current settings.
	 *
	 * @param array $requestHeaders
	 *
	 * @return string
	 *
	 * @throws GeneralException
	 */
	public function _getCallUrl(&$requestHeaders = []): string
	{
		//  TODO: move logic to Guzzle?
		$requestHeaders = [];
		//  Platform against which will call be made
		$url_platformPart = $this->platforms->getPlatformName($this->getSetting(self::SET_REGION));

		//  API base url
		$url_basePart = $this->getSetting(self::SET_API_BASEURL);

		//  Query parameters
		$url_queryPart = "";
		foreach ($this->query_data as $key => $value)
		{
			if (is_array($value))
			{
				foreach ($value as $v)
					$url_queryPart.= "&$key=$v";
			}
			else
				$url_queryPart.= "&$key=$value";
		}
		$url_queryPart = substr($url_queryPart, 1);

		//  API key
		$url_keyPart = "";
		if ($this->getSetting(self::SET_KEY_INCLUDE_TYPE) === self::KEY_AS_QUERY_PARAM)
		{
			//  API key is to be included as query parameter
			$url_keyPart = "?api_key=" . $this->getSetting($this->used_key);
			if (!empty($url_queryPart))
				$url_keyPart.= '&';
		}
		elseif ($this->getSetting(self::SET_KEY_INCLUDE_TYPE) === self::KEY_AS_HEADER)
		{
			//  API key is to be included as request header
			$requestHeaders[self::HEADER_API_KEY] = $this->getSetting($this->used_key);
			if (!empty($url_queryPart))
				$url_keyPart = '?';
		}

		return "https://" . $url_platformPart . $url_basePart . $this->endpoint . $url_keyPart . $url_queryPart;
	}

	/**
	 * @internal
	 *
	 *   Returns dummy response filename based on current settings.
	 *
	 * @return string
	 */
	public function _getDummyDataFileName(): string
	{
		$method = $this->used_method;
		$endp = str_replace([ '/', '.' ], [ '-', '' ], substr($this->endpoint, 1));
		$quer = str_replace([ '&', '%26', '=', '%3D' ], [ '_', '_', '-', '-' ], http_build_query($this->query_data));
		$data = !empty($this->post_data) ? '_' . md5(http_build_query($this->query_data)) : '';
		if (strlen($quer))
			$quer = "_" . $quer;

		return __DIR__ . "/../../tests/DummyData/{$method}_$endp$quer$data.json";
	}

	/**
	 * ==================================================================dd=
	 *     Fake Endpoint for testing purposes
	 * ==================================================================dd=
	 **/

	/**
	 * @internal
	 *
	 * @param             $specs
	 * @param string|null $region
	 * @param string|null $method
	 *
	 * @return mixed
	 *
	 * @throws SettingsException
	 * @throws RequestException
	 * @throws ServerException
	 * @throws ServerLimitException
	 * @throws GeneralException
	 */
	public function makeTestEndpointCall($specs, string $region = null, string $method = null)
	{
		$resultPromise = $this->setEndpoint("/lol/test-endpoint/v0/{$specs}")
			->setResource("v0", "/lol/test-endpoint/v0/%s")
			->makeCall($region ?: null, $method ?: self::METHOD_GET);

		return $this->resolveOrEnqueuePromise($resultPromise, function($result) {
			return $result;
		});
	}
}
