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
            'directory' => $this->cacheDir
        ];

        return new FileCachePool($options);
    }

    public function tearDown()
    {
        parent::tearDown();
        testRemoveDirTree($this->cacheDir);
        unset($this->cacheDir);
    }
}
