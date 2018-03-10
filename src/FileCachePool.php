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
 * Filesystem caching. Stores cache data in the filesystem.
 */
class FileCachePool extends AbstractCacheItemPool
{
    /**
     * @var string Cache directory
     */
    protected $directory;

    /**
     * @var int Directory level
     */
    protected $directoryLevel = 3;

    /**
     * @var string Cache file suffix
     */
    protected $fileSuffix = '.cache';

    /**
     * Constructor
     *
     * @param array $options
     * @throws \InvalidArgumentException
     */
    public function __construct(array $options)
    {
        if (!isset($options['directory']) || !is_string($options['directory'])) {
            throw new \InvalidArgumentException('Missing option "directory"');
        }

        if (!is_dir($options['directory']) && ! @mkdir($options['directory'], 0777, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Cache directory "%s" does not exist and could not be created',
                $options['directory']
            ));
        }

        if (!is_writable($options['directory'])) {
            throw new \InvalidArgumentException(sprintf(
                'The directory "%s" is not writable',
                $options['directory']
            ));
        }

        $this->directory = rtrim($options['directory'], DIRECTORY_SEPARATOR);

        if (isset($options['directory_level'])) {
            $directoryLevel = (int) $options['directory_level'];
            if ($directoryLevel > 0) {
                $this->directoryLevel = $directoryLevel;
            }
        }

        if (isset($options['file_suffix'])) {
            $this->fileSuffix = (string) $options['file_suffix'];
        }

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     */
    protected function setDataToStorage($key, $value, $ttl)
    {
        $filename = $this->getFile($key);
        $path = pathinfo($filename, PATHINFO_DIRNAME);

        $expire = time() + $ttl;
        $value = $expire.serialize($value);

        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $res = (bool) file_put_contents($filename, $value, LOCK_EX);
        return $res;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDataFromStorage($key)
    {
        $filename = $this->getFile($key);

        if (!is_file($filename)) {
            return false;
        }

        $value = file_get_contents($filename);
        $expire = (int) substr($value, 0, 10);
        if (time() < $expire) {
            return [unserialize(substr($value, 10))];
        }

        unlink($filename);
        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function getArrayDataFromStorage(array $keys)
    {
        $result = [];

        foreach ($keys as $key) {
            $dataArr = $this->getDataFromStorage($key);
            if (is_array($dataArr)) {
                $data = $this->getDataFromStorage($key)[0];
                $result[$key] = $data;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function isHavDataInStorage($key)
    {
        $filename = $this->getFile($key);

        if (!is_file($filename)) {
            return false;
        }

        $value = file_get_contents($filename);
        $expire = (int) substr($value, 0, 10);
        if (time() < $expire) {
            return true;
        }

        unlink($filename);
        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteDataFromStorage($key)
    {
        $filename = $this->getFile($key);

        if (file_exists($filename)) {
            return unlink($filename);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteArrayDataFromStorage(array $keys)
    {
        $deleted = true;

        foreach ($keys as $key) {
            if ($this->deleteDataFromStorage($key) === false) {
                if (isHavDataInStorage($key)) {
                    $deleted = false;
                }
            }
        }

        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    protected function clearAllDataFromStorage()
    {
        if (!$handle = opendir($this->directory)) {
            return false;
        }

        while (($file = readdir($handle)) !== false) {
            if (in_array($file, ['..','.'])) {
                continue;
            }

            $this->removeDirs($this->directory . DIRECTORY_SEPARATOR . $file);
        }
        return true;
    }

    /**
     * Remove directories by path
     *
     * @param string $path
     */
    protected function removeDirs($path)
    {
        if ($dir = glob($path.'/*')) {
            foreach ($dir as $object) {
                if (is_dir($object)) {
                    $this->removeDirs($object);
                } else {
                    @chmod($object, 0777);
                    unlink($object);
                }
            }
        }
        @chmod($object, 0777);
        rmdir($path);
    }

    /**
     * Get cache file by cache key
     *
     * @param string $key
     * @return string filename
     */
    protected function getFile($key)
    {
        $key = md5($key);
        $path = $this->directory;
        if ($this->directoryLevel > 0) {
            for ($i = 0; $i < $this->directoryLevel; $i++) {
                $path .= DIRECTORY_SEPARATOR . substr($key, 0, $i + 1);
            }
        }

        return $path . DIRECTORY_SEPARATOR . $this->prefix . $key . $this->fileSuffix;
    }
}
