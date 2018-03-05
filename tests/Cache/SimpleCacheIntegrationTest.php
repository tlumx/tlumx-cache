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

use Cache\IntegrationTests\SimpleCacheTest;
use Tlumx\Cache\SimpleCache;
use Tlumx\Cache\FileCachePool;

class SimpleCacheIntegrationTest extends SimpleCacheTest
{
    protected static $cacheDir;

    public function createSimpleCache()
    {
        self::$cacheDir = @tempnam(sys_get_temp_dir(), 'tlumxframework_tmp_cache');
        if (!self::$cacheDir) {
            $e = error_get_last();
            $this->fail("Can't create temporary cache directory-file: {$e['message']}");
        } elseif (!@unlink(self::$cacheDir)) {
            $e = error_get_last();
            $this->fail("Can't remove temporary cache directory-file: {$e['message']}");
        } elseif (!@mkdir(self::$cacheDir, 0777)) {
            $e = error_get_last();
            $this->fail("Can't create temporary cache directory: {$e['message']}");
        }

        $options = [
            'directory' => self::$cacheDir
        ];
        $cachePool = new FileCachePool($options);

        return new SimpleCache($cachePool);
    }

    public static function tearDownAfterClass()
    {
        testRemoveDirTree(self::$cacheDir);
    }

    /**
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     * @dataProvider invalidKeysForSetMultiple
     */
    public function testSetMultipleInvalidKeys($key)
    {
        parent::testSetMultipleInvalidKeys($key);
    }

    /**
     * Data provider for invalid keys.
     *
     * @return array
     */
    public static function invalidKeysForSetMultiple()
    {
        return [
            [''],
            [true],
            [false],
            [null],
            [2.5],
            ['{str'],
            ['rand{'],
            ['rand{str'],
            ['rand}str'],
            ['rand(str'],
            ['rand)str'],
            ['rand/str'],
            ['rand\\str'],
            ['rand@str'],
            ['rand:str'],
            [new \stdClass()],
            [['array']],
        ];
    }
}
