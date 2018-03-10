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
use Tlumx\Cache\FileCachePool;

class FilePoolIntegrationTest extends CachePoolTest
{
    protected $cacheDir;

    public function createCachePool()
    {
        if ($this->cacheDir === null) {
            $this->cacheDir = @tempnam(sys_get_temp_dir(), 'tlumxframework_tmp_cache');
            if (!$this->cacheDir) {
                $e = error_get_last();
                $this->fail("Can't create temporary cache directory-file: {$e['message']}");
            } elseif (!@unlink($this->cacheDir)) {
                $e = error_get_last();
                $this->fail("Can't remove temporary cache directory-file: {$e['message']}");
            } elseif (!@mkdir($this->cacheDir, 0777)) {
                $e = error_get_last();
                $this->fail("Can't create temporary cache directory: {$e['message']}");
            }
        }
        $options = [
            'directory' => $this->cacheDir,
            'directory_level' => 2,
            'file_suffix' => '.cache'
        ];

        return new FileCachePool($options);
    }

    public function tearDown()
    {
        parent::tearDown();
        testRemoveDirTree($this->cacheDir);
        unset($this->cacheDir);
    }

    public function testConstructNotDirectoryOption()
    {
        $this->expectException(\InvalidArgumentException::class);
        $pool = new FileCachePool([]);
    }

    public function testConstructNotIsDirectory()
    {
        $this->expectException(\InvalidArgumentException::class);
        $pool = new FileCachePool([
            'directory' => ''
        ]);
    }

    public function testConstructNotWritebleDirectory()
    {
        $this->expectException(\InvalidArgumentException::class);
        $pool = new FileCachePool([
            'directory' => '/home'
        ]);
    }

    public function testIsHavDataInStorageFalseTime()
    {
        $pool = $this->createCachePool();
        $item = $pool->getItem('key');
        $item->set('value');
        $item->expiresAfter(1);
        $pool->save($item);
        sleep(3);
        $this->assertFalse($pool->hasItem('key'));
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
