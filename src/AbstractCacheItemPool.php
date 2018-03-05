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

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;

/**
 * Implementation of CacheItemPoolInterface.
 * It is the base class for cache classes.
 */
abstract class AbstractCacheItemPool implements CacheItemPoolInterface
{
    /**
     * Default cache options
     *
     * @var array
     */
    protected $defaultOptions = [
        'prefix'    => 'tlumxframework_',
        'ttl'       => 3600
    ];

    /**
     * Cache prefix
     *
     * @var string
     */
    protected $prefix = '';

    /**
     * Ttl
     *
     * @var int
     */
    protected $ttl = 3600;

    /**
     * Deferred cache items
     *
     * @var CacheItemInterface[]
     */
    protected $deferred = [];

    /**
     * Constructor
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $options += $this->defaultOptions;
        $this->prefix = (string) $options['prefix'];
        $this->ttl = (int) $options['ttl'];
    }

    /**
     * Destructor
     *
     * Save deferred cache items
     */
    public function __destruct()
    {
        if ($this->deferred) {
            $this->commit();
        }
    }

    /**
     * Set cache prefix
     *
     * @param string $prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = (string) $prefix;
    }

    /**
     * Get cache prefix
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Set the time to live
     *
     * @param int $ttl
     */
    public function setTtl($ttl)
    {
        $this->ttl = (int) $ttl;
    }

    /**
     * Get ttl
     *
     * @return int
     */
    public function getTtl()
    {
        return $this->ttl;
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        $storageKey = $this->createKey($key);

        if (isset($this->deferred[$storageKey])) {
            $item = $this->deferred[$storageKey];
            $item2 = new CacheItem($key, $this->ttl);
            $item2->set($item->get());
            return $item2->setHit(true);
        }

        $item = new CacheItem($key, $this->ttl);
        $value = $this->getDataFromStorage($storageKey);
        if ($value === false) {
            $item->setHit(false);
        } else {
            $item->setHit(true);
            $item->set($value[0]);
        }

        return $item;
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = [])
    {
        if ($this->deferred) {
            $this->commit();
        }

        $dataKeys = [];
        foreach ($keys as $key) {
            $dataKeys[] = $this->createKey($key);
        }

        $values = $this->getArrayDataFromStorage($dataKeys);
        $items = [];
        foreach ($keys as $key) {
            $item = new CacheItem($key, $this->ttl);
            $storageKey = $this->createKey($key);
            if (isset($values[$storageKey])) {
                $item->setHit(true);
                $item->set($values[$storageKey]);
            } else {
                $item->setHit(false);
            }
            $items[$key] = $item;
        }

        return $items;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        $storageKey = $this->createKey($key);

        if (isset($this->deferred[$storageKey])) {
            $item = $this->deferred[$storageKey];
            $expiration = $item->getExpiration()->getTimestamp();
            $ttl = $expiration - time();
            if ($ttl > 0) {
                return true;
            }
            return false;
        }

        return $this->isHavDataInStorage($storageKey);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->deferred = [];

        return $this->clearAllDataFromStorage();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        $storageKey = $this->createKey($key);

        if (isset($this->deferred[$storageKey])) {
            unset($this->deferred[$storageKey]);
        }

        return $this->deleteDataFromStorage($storageKey);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        $dataKeys = [];
        foreach ($keys as $key) {
            $dataKeys[] = $this->createKey($key);
        }

        return $this->deleteArrayDataFromStorage($dataKeys);
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        $storageKey = $this->createKey($item->getKey());

        $expiration = $item->getExpiration()->getTimestamp();

        $ttl = $expiration - time();
        $ttl = (int) $ttl;
        if ($ttl > 0) {
            return $this->setDataToStorage($storageKey, $item->get(), $ttl);
        }

        $this->deleteDataFromStorage($storageKey);

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        $expiration = $item->getExpiration()->getTimestamp();
        $ttl = $expiration - time();
        if ($ttl > 0) {
            $this->deferred[$this->createKey($item->getKey())] = $item;

            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $saved = true;
        foreach ($this->deferred as $item) {
            if (!$this->save($item)) {
                $saved = false;
            }
        }
        $this->deferred = [];

        return $saved;
    }

    /**
     * Validate and create the storage key
     *
     * @param string $key
     * @return string
     * @throws Exception\InvalidArgumentException
     */
    protected function createKey($key)
    {
        if (!is_string($key) || $key === '') {
            throw new Exception\InvalidArgumentException('Cache key must be a not empty string.');
        }

        if (preg_match('/['.preg_quote('{}()/\@:', '/').']/', $key)) {
            throw new Exception\InvalidArgumentException('Cache key could not contains reserved characters.');
        }

        return $this->prefix . sha1($key);
    }

    /**
     * Stores content in data store
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool
     */
    abstract protected function setDataToStorage($key, $value, $ttl);

    /**
     * Return cached content by its key
     *
     * @param string $key
     * @return mixed|false
     */
    abstract protected function getDataFromStorage($key);

    /**
     * Return cached contents by its keys
     *
     * @param array $keys
     * @return array
     */
    abstract protected function getArrayDataFromStorage(array $keys);

    /**
     * Checks whether a specified key exists in the cache data store
     *
     * @param string $key
     * @return bool
     */
    abstract protected function isHavDataInStorage($key);

    /**
     * Removes content from cache data store by key
     *
     * @param string $key
     * @return bool
     */
    abstract protected function deleteDataFromStorage($key);

    /**
     * Removes contents from cache data store by keys
     *
     * @param array $keys
     * @return bool
     */
    abstract protected function deleteArrayDataFromStorage(array $keys);

    /**
     * Removes all contents from cache data store
     *
     * @return bool
     */
    abstract protected function clearAllDataFromStorage();
}
