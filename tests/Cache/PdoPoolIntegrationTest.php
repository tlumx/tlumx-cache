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
use Tlumx\Cache\PdoCachePool;

class PdoPoolIntegrationTest extends CachePoolTest
{
    protected $cacheDriver;

    protected $dbh;

    public function createCachePool()
    {
        if ($this->cacheDriver) {
            return $this->cacheDriver;
        }

        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension must be loaded.');
        }

        $this->dbh = new \PDO('sqlite::memory:');
        $this->dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $sql = "CREATE TABLE IF NOT EXISTS cache (";
        $sql .= "id VARBINARY(255) NOT NULL PRIMARY KEY,";
        $sql .= "value BLOB,";
        $sql .= "expire TIMESTAMP,";
        $sql .= "KEY expire";
        $sql .= ")";

        $this->dbh->exec($sql);

        $this->cacheDriver = new PdoCachePool($this->dbh, [
            'table' => 'cache',
            'automatic_clean' => 10000
        ]);

        return $this->cacheDriver;
    }

    public function tearDown()
    {
        $this->cacheDriver = null;
        $this->dbh = null;
        ;
    }

    public function testUpdateWhenSetDataToStorage()
    {
        $pool = $this->createCachePool();
        $item = $pool->getItem('key1');
        $item->set('abc');
        $pool->save($item);

        $item->set('xyz');
        $pool->save($item);

        $fooItem = $pool->getItem('key1');
        $this->assertTrue($fooItem->isHit());
        $this->assertEquals('xyz', $fooItem->get());
    }

    public function testDeleteArrayEmptyDataFromStorage()
    {
        $pool = $this->createCachePool();
        $this->assertTrue($pool->deleteItems([]));
    }

    public function testClearOldData0()
    {
        $pool = $this->createCachePool();

        $reflectionClass = new \ReflectionClass('Tlumx\Cache\PdoCachePool');
        $reflectionProperty = $reflectionClass->getProperty('automaticClean');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($pool, 0);

        $reflectionMethod = $reflectionClass->getMethod('clearOldData');
        $reflectionMethod->setAccessible(true);

        $this->assertNull($reflectionMethod->invokeArgs($pool, []));
    }

    public function testClearOldData1()
    {
        $pool = $this->createCachePool();

        $reflectionClass = new \ReflectionClass('Tlumx\Cache\PdoCachePool');
        $reflectionProperty = $reflectionClass->getProperty('automaticClean');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($pool, 1);

        $reflectionMethod = $reflectionClass->getMethod('clearOldData');
        $reflectionMethod->setAccessible(true);

        $this->assertNull($reflectionMethod->invokeArgs($pool, []));
    }

    public function testPrefix()
    {
        $pool = $this->createCachePool();
        $this->assertEquals('tlumxframework_', $pool->getPrefix());
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
