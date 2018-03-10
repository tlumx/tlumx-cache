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

    public function testPrefix()
    {
        $pool = $this->createCachePool();
        $this->assertEquals('tlumxframework_tmp_cache', $pool->getPrefix());
        $pool->setPrefix('tlumxframework_tmp_cache1');
        $this->assertEquals('tlumxframework_tmp_cache1', $pool->getPrefix());
    }

    public function testTtl()
    {
        $pool = $this->createCachePool();
        $this->assertEquals(3600, $pool->getTtl());
        $pool->setTtl(300);
        $this->assertEquals(300, $pool->getTtl());
    }

    public function testGetItemsDeferredSave()
    {
        $pool = $this->createCachePool();
        $item = $pool->getItem('key');
        $item->set('4711');
        $return = $pool->saveDeferred($item);
        $this->assertTrue($return);

        $items = $pool->getItems(['key']);
        $item1 = $items['key'];
        $this->assertEquals('4711', $item1->get());
    }
}
