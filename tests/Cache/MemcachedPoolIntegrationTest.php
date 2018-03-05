<?php
/**
 * Tlumx (https://tlumx.github.io/)
 *
 * @author    Yaroslav Kharitonchuk <yarik.proger@gmail.com>
 * @link      https://github.com/tlumx/tlumx-cache
 * @copyright Copyright (c) 2016-2018 Yaroslav Kharitonchuk
 * @license   https://github.com/tlumx/tlumx-cache/blob/master/LICENSE  (MIT License)
 */
namespace Tlumx\Tests\Cache;

use Cache\IntegrationTests\CachePoolTest;
use Tlumx\Cache\MemcachedCachePool;

class MemcachedPoolIntegrationTest extends CachePoolTest
{
    protected $memcached;

    public function createCachePool()
    {
        if (!extension_loaded("memcached")) {
            $this->markTestSkipped("Memcached not installed. Skipping.");
        }

        $this->memcached = new \Memcached();
        $this->memcached->addServer('localhost', 11211);
        return new MemcachedCachePool($this->memcached, [
            'prefix' => 'tlumxframework_tmp_cache'
        ]);
    }

    public function tearDown()
    {
        parent::tearDown();
    }
}
