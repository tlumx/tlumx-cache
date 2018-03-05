<?php
/**
 * Tlumx (https://tlumx.github.io/)
 *
 * @author    Yaroslav Kharitonchuk <yarik.proger@gmail.com>
 * @link      https://github.com/tlumx/tlumx-cache
 * @copyright Copyright (c) 2016-2018 Yaroslav Kharitonchuk
 * @license   https://github.com/tlumx/tlumx-cache/blob/master/LICENSE  (MIT License)
 */
namespace Tlumx\Cache;

/**
 * APCu caching is a wrapper for the APCu extension.
 */
class ApcuCachePool extends AbstractCacheItemPool
{
    /**
     * Constructor
     *
     * @param array $options
     * @throws Exception\CacheException
     */
    public function __construct(array $options = [])
    {
        if (!extension_loaded('apcu')) {
            throw new Exception\CacheException('APCu extension must be loaded.');
        }

        $enabled = ini_get('apc.enabled');
        if (PHP_SAPI == 'cli') {
            $enabled = ini_get('apc.enable_cli');
            ini_set('apc.use_request_time', 0);
        }

        if (!$enabled) {
            throw new Exception\CacheException('APCu extension is disabled.');
        }

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     */
    protected function setDataToStorage($key, $value, $ttl)
    {
        $ttl = (int) $ttl;

        return apcu_store($key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDataFromStorage($key)
    {
        $value = apcu_fetch($key);
        if ($value === false) {
            return false;
        }

        return [$value];
    }

    /**
     * {@inheritdoc}
     */
    protected function getArrayDataFromStorage(array $keys)
    {
        $result = apcu_fetch($keys);
        if ($result === false) {
            return [];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function isHavDataInStorage($key)
    {
        return apcu_exists($key);
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteDataFromStorage($key)
    {
        if (apcu_exists($key)) {
            return apcu_delete($key);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteArrayDataFromStorage(array $keys)
    {
        $deleted = true;

        foreach ($keys as $key) {
            if (apcu_delete($key) === false) {
                if (apcu_exists($key)) {
                    $deleted = false;
                }
            }
        }

        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    protected function clearAllDataFromStorage()
    {
        return apcu_clear_cache();
    }
}
