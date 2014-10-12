<?php
/** ---------------------------------------------------------------------
 * app/lib/core/Cache/ExternalCache.php : provides caching using external facilities
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2014 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * This source code is free and modifiable under the terms of
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * @package CollectiveAccess
 * @subpackage Cache
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 *
 * ----------------------------------------------------------------------
 */

require_once(__CA_LIB_DIR__."/core/Cache/MemoryCache.php");

class ExternalCache {
	# ------------------------------------------------
	/**
	 * @var array Doctrine\Common\Cache\CacheProvider
	 */
	private static $opa_caches = array();
	# ------------------------------------------------
	/**
	 * Initialize cache for given namespace if necessary
	 * Namespace declaration is optional
	 * @param string $ps_namespace Optional namespace
	 * @throws ExternalCacheInvalidParameterException
	 */
	private static function init($ps_namespace='default') {
		// catch invalid namespace definitions
		if(!is_string($ps_namespace)) { throw new ExternalCacheInvalidParameterException('Namespace has to be a string'); }
		if(!preg_match("/^[A-Za-z0-9_]+$/", $ps_namespace)) { throw new ExternalCacheInvalidParameterException('Caching namespace must only contain alphanumeric characters, dashes and underscores'); }

		if(self::nameSpaceExists($ps_namespace)) {
			return;
		} else {
			self::$opa_caches[$ps_namespace] = self::getCacheObject($ps_namespace);
		}
	}
	# ------------------------------------------------
	/**
	 * Does a given namespace exist?
	 * @param string $ps_namespace
	 * @return bool
	 */
	private static function nameSpaceExists($ps_namespace='default') {
		return isset(self::$opa_caches[(string)$ps_namespace]);
	}
	# ------------------------------------------------
	/**
	 * Get object for a given namespace
	 * @param string $ps_namespace
	 * @return Doctrine\Common\Cache\CacheProvider
	 */
	private static function getCacheObjectForNamespace($ps_namespace='default') {
		if(isset(self::$opa_caches[$ps_namespace])) {
			return self::$opa_caches[$ps_namespace];
		} else {
			return null;
		}
	}
	# ------------------------------------------------
	/**
	 * Get a cache item
	 * @param string $ps_key
	 * @param string $ps_namespace
	 * @return mixed
	 * @throws ExternalCacheInvalidParameterException
	 */
	public static function fetch($ps_key, $ps_namespace='default') {
		self::init($ps_namespace);
		if(!$ps_key) { throw new ExternalCacheInvalidParameterException('Key cannot be empty'); }

		return self::getCacheObjectForNamespace($ps_namespace)->fetch($ps_key);
	}
	# ------------------------------------------------
	/**
	 * Puts data into the cache. Overwrites existing items!
	 * @param string $ps_key
	 * @param mixed $pm_data
	 * @param string $ps_namespace
	 * @return bool
	 * @throws ExternalCacheInvalidParameterException
	 */
	public static function save($ps_key, $pm_data, $ps_namespace='default') {
		self::init($ps_namespace);
		if(!$ps_key) { throw new ExternalCacheInvalidParameterException('Key cannot be empty'); }

		// Cache::save() returns the lifetime of the item. We're not interested in that
		self::getCacheObjectForNamespace($ps_namespace)->save($ps_key, $pm_data);
		return true;
	}
	# ------------------------------------------------
	/**
	 * Does a given cache key exist?
	 * @param string $ps_key
	 * @param string $ps_namespace
	 * @return bool
	 * @throws ExternalCacheInvalidParameterException
	 */
	public static function contains($ps_key, $ps_namespace='default') {
		self::init($ps_namespace);
		if(!$ps_key) { throw new ExternalCacheInvalidParameterException('Key cannot be empty'); }

		return self::getCacheObjectForNamespace($ps_namespace)->contains($ps_key);
	}
	# ------------------------------------------------
	/**
	 * Remove a given key from cache
	 * @param string $ps_key
	 * @param string $ps_namespace
	 * @return bool
	 * @throws ExternalCacheInvalidParameterException
	 */
	public static function delete($ps_key, $ps_namespace='default') {
		self::init($ps_namespace);
		if(!$ps_key) { throw new ExternalCacheInvalidParameterException('Key cannot be empty'); }

		return self::getCacheObjectForNamespace($ps_namespace)->delete($ps_key);
	}
	# ------------------------------------------------
	/**
	 * Flush cache
	 * @param string|null $ps_namespace Optional namespace definition. If given, only this namespace is wiped.
	 * @throws MemoryCacheInvalidParameterException
	 */
	public static function flush($ps_namespace=null) {
		if(!$ps_namespace) {
			foreach(self::$opa_caches as $o_cache) {
				$o_cache->flushAll();
			}
		} else {
			self::init($ps_namespace);
			self::getCacheObjectForNamespace($ps_namespace)->flushAll();
		}
	}
	# ------------------------------------------------
	# Helpers
	# ------------------------------------------------
	private static function getCacheObject($ps_namespace) {

		if(MemoryCache::contains('cache_backend') && MemoryCache::contains('cache_configuration')) {
			$vs_cache_backend = MemoryCache::fetch('cache_backend');
		} else {
			$o_app_conf = Configuration::load();
			$o_cache_conf = Configuration::load($o_app_conf->get('cache_config'));
			$vs_cache_backend = $o_cache_conf->get('cache_backend');
			MemoryCache::save('cache_backend', $vs_cache_backend);
			MemoryCache::save('cache_configuration', $o_cache_conf);
		}

		switch($vs_cache_backend) {
			case 'file':
				return self::getFileCacheObject($ps_namespace);
			default:

		}
	}
	# ------------------------------------------------
	private static function getFileCacheObject($ps_namespace){
		$o_cache_conf = MemoryCache::fetch('cache_configuration');

		$vs_cache_base_dir = ($o_cache_conf->get('cache_file_path') ? $o_cache_conf->get('cache_file_path') : __CA_APP_DIR__.DIRECTORY_SEPARATOR.'tmp');
		$vs_cache_dir = $vs_cache_base_dir.DIRECTORY_SEPARATOR.__CA_APP_NAME__.'_'.$ps_namespace;

		$o_cache = new \Doctrine\Common\Cache\FilesystemCache($vs_cache_dir);

		return $o_cache;
	}
	# ------------------------------------------------
}

class ExternalCacheInvalidParameterException extends Exception {}
