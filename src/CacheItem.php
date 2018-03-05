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

/**
 * Implementation of CacheItemInterface.
 */
class CacheItem implements CacheItemInterface
{
    /**
     * @var string Item key
     */
    private $key;

    /**
     * @var mixed Value
     */
    private $value;

    /**
     * @var bool Is Hit?
     */
    private $isHit;

    /**
     * @var int Expiration time in seconds
     */
    private $expire = 0;

    /**
     * @var int Default TTL in seconds
     */
    private $ttl = 3600;

    /**
     * Constructor
     *
     * @param string $key Item key
     * @param int $ttl Default TTL in seconds
     */
    public function __construct($key, $ttl = 3600)
    {
        $this->key = strval($key);
        $this->ttl = intval($ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * {@inheritdoc}
     */
    public function get()
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function isHit()
    {
        return (bool) $this->isHit;
    }

    /**
     * Set confirms if the cache item lookup resulted in a cache hit.
     *
     * @param bool $isHit
     * @return static
     */
    public function setHit($isHit)
    {
        $this->isHit = boolval($isHit);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function set($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAt($expiration)
    {
        if ($expiration instanceof \DateTimeInterface) {
            $this->expire = (int) $expiration->format('U');
        } elseif ($expiration === null) {
            $this->expire = time() + $this->ttl;
        } else {
            throw new Exception\InvalidArgumentException(
                'Invalid expiration date. It can be null or implement DateTimeInterface.'
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAfter($time)
    {
        if ($time instanceof \DateInterval) {
            $this->expire = \DateTime::createFromFormat('U', time())->add($time)->format('U');
        } elseif (is_int($time)) {
            $this->expire = time() + $time;
        } elseif ($time === null) {
            $this->expire = time() + $this->ttl;
        } else {
            throw new Exception\InvalidArgumentException(
                'Invalid time. It can be integer, null or instance of DateInterval.'
            );
        }

        return $this;
    }

    /**
     * Return the expiration time for this cache item.
     *
     * @return \DateTime
     */
    public function getExpiration()
    {
        if ($this->expire === 0) {
            $this->expire = time() + $this->ttl;
        }

        $expire = new \DateTime();
        return $expire->setTimestamp($this->expire);
    }
}
