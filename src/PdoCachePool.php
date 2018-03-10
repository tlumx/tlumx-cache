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

/**
 * Pdo cache - storing cached data in a database.
 *
 * Database table must have some columns: id, value, expire.
 *
 * Example table for SQLite:
 * CREATE TABLE cache (
 *      id VARBINARY(255) NOT NULL PRIMARY KEY,
 *      value BLOB,
 *      expire TIMESTAMP,
 *      KEY expire
 * );
 */
class PdoCachePool extends AbstractCacheItemPool
{
    /**
     * @var \PDO
     */
    protected $dbh;

    /**
     * @var string
     */
    protected $table = 'cache';

    /**
     * @var int
     */
    protected $automaticClean = 10000;

    /**
     * Constructor
     *
     * @param \PDO $dbh
     * @param array $options
     */
    public function __construct(\PDO $dbh, array $options = [])
    {
        $this->dbh = $dbh;

        $this->dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

        if (isset($options['table'])) {
            $this->table = (string) $options['table'];
        }

        if (isset($options['automatic_clean'])) {
            $this->automaticClean = (int) $options['automatic_clean'];
        }

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     */
    protected function setDataToStorage($key, $value, $ttl)
    {
        $this->clearOldData();

        $expire = date('Y-m-d H:i:s', time() + (int) $ttl);

        $sql = 'SELECT COUNT(*) from '.$this->table.' WHERE id = :id';
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute([
            'id' => $key
        ]);
        if ($stmt->fetchColumn()) {
            $sql = "UPDATE ".$this->table." SET value = :value, expire = :expire WHERE id = :id";
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute([
                ':id' => $key,
                ':value' => serialize($value),
                ':expire' => $expire,
            ]);

            return $stmt->rowCount() === 1;
        }

        $sql = "INSERT INTO ".$this->table." (id, value, expire) VALUES (:id, :value, :expire)";
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute([
            ':id' => $key,
            ':value' => serialize($value),
            ':expire' => $expire,
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDataFromStorage($key)
    {
        $sql = "SELECT value FROM ".$this->table." WHERE id = :id AND expire >= :expire";
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute([
            ':id' => $key,
            ':expire' => date('Y-m-d H:i:s')
        ]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!isset($result['value'])) {
            return false;
        }

        return [unserialize($result['value'])];
    }

    /**
     * {@inheritdoc}
     */
    protected function getArrayDataFromStorage(array $keys)
    {
        $quotedKeys = [];
        foreach ($keys as $key) {
            $quotedKeys[] = $this->dbh->quote($key);
        }

        $sql = "SELECT id, value FROM ".$this->table;
        $sql .= " WHERE id IN (". implode(',', $quotedKeys).")";
        $sql .= " AND expire >= :expire";

        $stmt = $this->dbh->prepare($sql);
        $stmt->execute([
            'expire' => date('Y-m-d H:i:s')
        ]);
        $values = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($values as $value) {
            $result[$value['id']] = unserialize($value['value']);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function isHavDataInStorage($key)
    {
        $sql = 'SELECT COUNT(*) from '.$this->table.' WHERE id = :id AND expire >= :expire';
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute([
            ':id' => $key,
            ':expire' => date('Y-m-d H:i:s')
        ]);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteDataFromStorage($key)
    {
        if ($this->isHavDataInStorage($key)) {
            $stmt = $this->dbh->prepare("DELETE FROM ".$this->table." WHERE id = :key");
            $stmt->execute([':key' => $key]);
            return $stmt->rowCount() === 1;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteArrayDataFromStorage(array $keys)
    {
        $quotedKeys = [];
        foreach ($keys as $key) {
            $quotedKeys[] = $this->dbh->quote($key);
        }

        $sql = 'SELECT COUNT(*) from '.$this->table." WHERE id IN (".implode(',', $quotedKeys).")";
        $stmt = $this->dbh->query($sql);
        if ($stmt->fetchColumn()) {
            $sql = "DELETE FROM ".$this->table." WHERE id IN (".implode(',', $quotedKeys).")";
            $stmt = $this->dbh->query($sql);
            return $stmt->rowCount() !== 0;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function clearAllDataFromStorage()
    {
        return $this->dbh->exec("DELETE FROM ".$this->table) !== false;
    }

    /**
     * Clear old cache data
     */
    protected function clearOldData()
    {
        if ($this->automaticClean == 0) {
            return;
        }

        if (mt_rand(1, $this->automaticClean) !== 1) {
            return;
        }

        $sql = "DELETE FROM ".$this->table." WHERE expire < :expire";
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute([
            ':expire' => date('Y-m-d H:i:s')
        ]);
    }
}
