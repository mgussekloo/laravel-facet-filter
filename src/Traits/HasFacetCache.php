<?php

namespace Mgussekloo\FacetFilter\Traits;

use Cache;

trait HasFacetCache
{

	public $cache;
    public $cacheKey;
    public $cacheExpirationTime;

    public function __construct() {
		$this->cacheExpirationTime = config('facet-filter.cache.expiration_time') ?: \DateInterval::createFromDateString('24 hours');
        $this->cacheKey = config('facet-filter.cache.key');
       	$this->cache = $this->getCacheStoreFromConfig();
    }

    public function getCacheStoreFromConfig() {
        // the 'default' fallback here is from the permission.php config file,
        // where 'default' means to use config(cache.default)
        $cacheDriver = config('permission.cache.store', 'default');

        // when 'default' is specified, no action is required since we already have the default instance
        if ($cacheDriver === 'default') {
            return Cache::store();
        }

        // if an undefined cache store is specified, fallback to 'array' which is Laravel's closest equiv to 'none'
        if (! \array_key_exists($cacheDriver, config('cache.stores'))) {
            $cacheDriver = 'array';
        }

        return Cache::store($cacheDriver);
    }

    public function cache($key, $subkey, $toRemember = null, $expiration = null) {
		$expiration = $expiration ?? $this->cacheExpirationTime;

    	$cacheKey = $this->cacheKey . '.' . $key;

    	$arr = [];

    	if ($this->cache->has($cacheKey)) {
			$arr = $this->cache->get($cacheKey);
		}

    	if (!is_null($toRemember)) {
    		$arr[$subkey] = $toRemember;
    		$this->cache->put($cacheKey, $arr, $expiration);
		}

		return isset($arr[$subkey]) ? $arr[$subkey] : false;
	}

    public function forgetCache($key = null)
    {
    	$keys = [];

		if (is_null($key)) {
			$keys = ['facetRows', 'idsInFilteredQuery'];
    	}

    	if (is_string($key)) {
    		$keys = [$key];
    	}

    	foreach ($keys as $key) {
    		$cacheKey = $this->cacheKey . '.' . $key;
        	$this->cache->forget($cacheKey);
        }

        return;
    }

}