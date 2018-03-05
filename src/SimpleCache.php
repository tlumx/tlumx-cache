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

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as Psr6InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;

/**
 * Implementation of Psr SimpleCache Interface.
 * Based on on PSR-6 Cache Pool.
 */
class SimpleCache implements CacheInterface
{
    /**
     * CacheItemPool object
     *
     * @var CacheItemPoolInterface
     */
    protected $cachePool;

    public function __construct(CacheItemPoolInterface $cachePool)
    {
        $this->cachePool = $cachePool;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        try {
            $item = $this->cachePool->getItem($key);
        } catch (Psr6InvalidArgumentException $e) {
            throw new Exception\SimpleCacheInvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        if ($item->isHit()) {
            return $item->get();
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        try {
            $item = $this->cachePool->getItem($key);
            $item->expiresAfter($ttl);
        } catch (Psr6InvalidArgumentException $e) {
            throw new Exception\SimpleCacheInvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        $item->set($value);
        return $this->cachePool->save($item);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        try {
            return $this->cachePool->deleteItem($key);
        } catch (Psr6InvalidArgumentException $e) {
            throw new Exception\SimpleCacheInvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        return $this->cachePool->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null)
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        } elseif (!is_array($keys)) {
            throw new Exception\SimpleCacheInvalidArgumentException('Cache keys must be array or Traversable');
        }

        try {
            $items = $this->cachePool->getItems($keys);
        } catch (Psr6InvalidArgumentException $e) {
            throw new Exception\SimpleCacheInvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        foreach ($items as $key => $item) {
            if (!$item->isHit()) {
                $items[$key] = $default;
            } else {
                $items[$key] = $item->get();
            }
        }

        return $items;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        if (!is_array($values) && !$values instanceof \Traversable) {
            throw new Exception\SimpleCacheInvalidArgumentException('Cache values must be array or Traversable.');
        }

        $itemsValues = [];
        $keys = [];
        foreach ($values as $key => $value) {
            if (is_int($key)) {
                $key = (string) $key;
            }

            if (!is_string($key) || $key === '') {
                throw new Exception\SimpleCacheInvalidArgumentException('Cache key must be a not empty string.');
            }

            if (preg_match('/['.preg_quote('{}()/\@:', '/').']/', $key)) {
                $errMsg = 'Cache key could not contains reserved characters.';
                throw new Exception\SimpleCacheInvalidArgumentException($errMsg);
            }

            $itemsValues[$key] = $value;
            $keys[] = $key;
        }

        try {
            $items = $this->cachePool->getItems($keys);
        } catch (Psr6InvalidArgumentException $e) {
            throw new Exception\SimpleCacheInvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        $ok = true;
        foreach ($items as $key => $item) {
            $item->set($itemsValues[$key]);

            try {
                $item->expiresAfter($ttl);
            } catch (Psr6InvalidArgumentException $e) {
                throw new Exception\SimpleCacheInvalidArgumentException($e->getMessage(), $e->getCode(), $e);
            }
            if (!$this->cachePool->saveDeferred($item)) {
                $ok = false;
            }
        }

        return $ok ? $this->cachePool->commit() : false;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys)
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        } elseif (!is_array($keys)) {
            throw new Exception\SimpleCacheInvalidArgumentException('Cache keys must be array or Traversable');
        }

        try {
            return $this->cachePool->deleteItems($keys);
        } catch (Psr6InvalidArgumentException $e) {
            throw new Exception\SimpleCacheInvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        try {
            return $this->cachePool->hasItem($key);
        } catch (Psr6InvalidArgumentException $e) {
            throw new Exception\SimpleCacheInvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
