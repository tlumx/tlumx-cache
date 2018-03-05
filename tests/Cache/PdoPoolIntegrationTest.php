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

        $this->cacheDriver = new PdoCachePool($this->dbh);

        return $this->cacheDriver;
    }

    public function tearDown()
    {
        $this->cacheDriver = null;
        $this->dbh = null;
        ;
    }
}
