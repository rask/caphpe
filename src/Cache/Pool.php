<?php declare(strict_types = 1);

namespace Caphpe\Cache;

/**
 * Class Pool
 *
 * A pool of cached values (keys).
 *
 * @since 0.1.0
 * @package Caphpe\Cache
 */
class Pool
{
    /**
     * Cached items (key->value pairs).
     *
     * @since 0.1.0
     * @access protected
     * @var mixed[]
     */
    protected $items = [];

    /**
     * Last used timestamps for items. Mods and reads.
     *
     * @since 0.1.0
     * @access protected
     * @var array
     */
    protected $lastUse = [];

    /**
     * Expiration timeout values for items.
     *
     * @since 0.1.0
     * @access protected
     * @var integer[]
     */
    protected $timeouts = [];

    /**
     * Add a new value. If key exists do nothing.
     *
     * @since 0.1.0
     *
     * @param string $key Key to cache with.
     * @param mixed $value Value to cache.
     * @param integer $timeout How many seconds the value should be cached from now.
     *
     * @return bool
     */
    public function add(string $key, $value, int $timeout = 0) : bool
    {
        $key = $this->parseKey($key);
        $value = $this->prepareValue($value);

        if ($this->has($key)) {
            return false;
        }

        $this->items[$key] = $value;
        $this->lastUse[$key] = time();
        $this->timeouts[$key] = $this->calculateTimeout($timeout);

        return true;
    }

    /**
     * Set a value. Create if doesn't exist.
     *
     * @since 0.1.0
     *
     * @param string $key Key to cache with.
     * @param mixed $value Value to cache.
     * @param integer $timeout How many seconds the value should be cached from now.
     *
     * @return bool
     */
    public function set(string $key, $value, int $timeout = 0) : bool
    {
        $key = $this->parseKey($key);
        $value = $this->prepareValue($value);

        $this->items[$key] = $value;
        $this->lastUse[$key] = time();
        $this->timeouts[$key] = $this->calculateTimeout($timeout);

        return true;
    }

    /**
     * Replace a value. If the key does not exist then do nothing.
     *
     * @since 0.1.0
     *
     * @param string $key Key to cache with.
     * @param mixed $value Value to cache.
     * @param integer $timeout How many seconds the value should be cached from now.
     *
     * @return bool
     */
    public function replace(string $key, $value, int $timeout = 0) : bool
    {
        $key = $this->parseKey($key);
        $value = $this->prepareValue($value);

        if (!$this->has($key)) {
            return false;
        }

        $this->items[$key] = $value;
        $this->lastUse[$key] = time();
        $this->timeouts[$key] = $this->calculateTimeout($timeout);

        return true;
    }

    /**
     * Delete a value.
     *
     * @since 0.1.0
     *
     * @param string $key Key to delete value for.
     *
     * @return bool
     */
    public function delete(string $key) : bool
    {
        $key = $this->parseKey($key);

        unset($this->items[$key]);
        unset($this->lastUse[$key]);
        unset($this->timeouts[$key]);

        return true;
    }

    /**
     * Increment a numeric cached value.
     *
     * @since 0.1.0
     *
     * @param string $key Key of value to increment.
     * @param integer $timeout How many seconds the value should be cached from now.
     *
     * @return bool
     */
    public function increment(string $key, int $timeout = 0) : bool
    {
        $key = $this->parseKey($key);

        if (!$this->has($key) || !is_numeric($this->items[$key])) {
            return false;
        }

        $this->items[$key] = (int) $this->get($key) + 1;
        $this->lastUse[$key] = time();
        $this->timeouts[$key] = $this->calculateTimeout($timeout);

        return true;
    }

    /**
     * Decrement a numeric cached value.
     *
     * @since 0.1.0
     *
     * @param string $key Key of value to decrement.
     * @param integer $timeout How many seconds the value should be cached from now.
     *
     * @return bool
     */
    public function decrement(string $key, int $timeout = 0) : bool
    {
        $key = $this->parseKey($key);

        if (!$this->has($key) || !is_numeric($this->items[$key])) {
            return false;
        }

        $this->items[$key] = (int) $this->get($key) - 1;
        $this->lastUse[$key] = time();
        $this->timeouts[$key] = $this->calculateTimeout($timeout);

        return true;
    }

    /**
     * Get a cached value.
     *
     * @since 0.1.0
     *
     * @param string $key Key of value to get.
     *
     * @return mixed
     */
    public function get(string $key)
    {
        $key = $this->parseKey($key);

        if ($this->has($key)) {
            if ($this->itemIsStale($key)) {
                $this->delete($key);

                return null;
            }

            return $this->items[$key];
        }

        return null;
    }

    /**
     * Check whether a value is cached.
     *
     * @since 0.1.0
     *
     * @param string $key Key of value to check.
     *
     * @return bool
     */
    public function has(string $key) : bool
    {
        $key = $this->parseKey($key);

        if ($this->itemIsStale($key)) {
            $this->delete($key);

            return false;
        }

        return array_key_exists($key, $this->items);
    }

    /**
     * Flush the pool cache.
     *
     * @since 0.1.0
     * @return bool
     */
    public function flush() : bool
    {
        $this->items = [];
        $this->lastUse = [];
        $this->timeouts = [];

        return true;
    }

    /**
     * Get the status of this cache pool.
     *
     * @since 0.1.0
     * @return string
     */
    public function getStatus() : string
    {
        $memoryUsageMBytes = memory_get_usage() / 1024 / 1024;
        $itemCount = $this->getItemCount();
        $itemMemorySizesKBytes = $this->getItemSizes();

        $msgHeader = sprintf(
            "%s\t%s\t%s\t%s\t%s",
            'Memory usage (MB)',
            'Item count',
            'Smallest item (KB)',
            'Largest item (KB)',
            'Average item (KB)'
        );

        $msg = sprintf(
            "%s\t%s\t%s\t%s\t%s",
            $memoryUsageMBytes,
            $itemCount,
            $itemMemorySizesKBytes['minimum'],
            $itemMemorySizesKBytes['maximum'],
            $itemMemorySizesKBytes['average']
        );

        return $msgHeader . PHP_EOL . $msg;
    }

    /**
     * Get the total count of items in the cache.
     *
     * @since 0.1.0
     * @access protected
     * @return int
     */
    protected function getItemCount() : int
    {
        return count($this->items);
    }

    /**
     * Get item memory sizes with various types.
     *
     * Returns an array containint [min, max, avg] in kilobytes.
     *
     * We are only concerned about integers, booleans and strings as those are the
     * only datatypes Caphpe currently supports.
     *
     * @since 0.1.0
     * @access protected
     *
     * @return array
     */
    protected function getItemSizes() : array
    {
        $smallest = null;
        $largest = null;
        $sum = 0;
        $itemCount = $this->getItemCount();

        // No items, return early.
        if ($itemCount === 0) {
            return [
                'minimum' => 0,
                'maximum' => 0,
                'average' => 0
            ];
        }

        array_walk($this->items, function ($item) use (&$smallest, &$largest, &$sum) {
            if (is_int($item)) {
                $size = $this->getIntegerSizeBytes();
            } elseif (is_bool($item)) {
                $size = $this->getBooleanSizeBytes();
            } else {
                $size = $this->getStringSizeBytes($item);
            }

            if ($smallest === null || $smallest > $size) {
                $smallest = $size;
            }

            if ($largest === null || $largest < $size) {
                $largest = $size;
            }

            $sum += $size;
        });

        $avgSize = $sum / $itemCount;

        // Return KBytes.
        return [
            'minimum' => $smallest ?? 0 / 1024,
            'maximum' => $largest ?? 0 / 1024,
            'average' => $avgSize ?? 0 / 1024
        ];
    }

    /**
     * Calculate an integer value's byte memory size.
     *
     * @since 0.1.0
     * @access protected
     *
     * @return int
     */
    protected function getIntegerSizeBytes() : int
    {
        return $this->getDefaultValueSizeBytes();
    }

    /**
     * Calculate a string value's byte memory size.
     *
     * `strlen` calculates byte size, `mb_strlen` calculates char length. Some
     * systems might have a config that casts `strlen`->`mb_strlen`, need to validate
     * some better way if needed.
     *
     * @since 0.1.0
     * @access protected
     *
     * @param string $value String to get byte value for.
     *
     * @return int
     */
    protected function getStringSizeBytes(string $value) : int
    {
        $size = PHP_INT_SIZE + strlen($value);

        // Return either str size, or the minimum default.
        return $size < $this->getDefaultValueSizeBytes()
            ? $this->getDefaultValueSizeBytes()
            : $size;
    }

    /**
     * Get a boolean value's byte memory size.
     *
     * @since 0.1.0
     * @access protected
     *
     * @return int
     */
    protected function getBooleanSizeBytes() : int
    {
        return $this->getDefaultValueSizeBytes();
    }

    /**
     * PHP adds overhead to value memory footprints. This function returns the
     * "default minimum" value byte size which PHP allocates.
     *
     * @since 0.1.0
     * @link https://nikic.github.io/2011/12/12/How-big-are-PHP-arrays-really-Hint-BIG.html
     * @access protected
     * @return int
     */
    protected function getDefaultValueSizeBytes() : int
    {
        // 32 bit = 72 bytes, 64 bit = 144 bytes.
        return PHP_INT_SIZE === 4 ? 72 : 144;
    }

    /**
     * Check whether an item is stale and should be removed.
     *
     * @since 0.1.0
     * @access protected
     *
     * @param string $key Key to check with.
     *
     * @return bool
     */
    protected function itemIsStale(string $key) : bool
    {
        if (!array_key_exists($key, $this->timeouts)) {
            return false;
        }

        $time = time();
        $timeout = $this->timeouts[$key];

        if ($timeout === 0) {
            return false;
        }

        if ($timeout < $time) {
            return true;
        }

        return false;
    }

    /**
     * Parse the key to save cache value with.
     *
     * Keys can use the following characters:
     * -   a-z
     * -   A-Z
     * -   0-9
     * -   _ and .
     *
     * Keys can be 64 characters long at maximum.
     *
     * @since 0.1.0
     * @access protected
     *
     * @param string $key Key to parse.
     *
     * @return string
     */
    public function parseKey(string $key) : string
    {
        $key = preg_replace('%[^a-zA-Z0-9\_\.]%', '', $key) ?? '';

        return mb_substr($key, 0, 64);
    }

    /**
     * Prepare value for caching.
     *
     * Essentially it all _should_ be just numbers and letters, but some booleans
     * and such might appear.
     *
     * @since 0.1.0
     * @access protected
     *
     * @param mixed $value Value to prepare.
     *
     * @return mixed
     */
    public function prepareValue($value)
    {
        if ($value === 'true') {
            $value = true;
        } elseif ($value === 'false') {
            $value = false;
        }

        return $value;
    }

    /**
     * Return the amount of distinctive items in the cache pool.
     *
     * @since 0.1.0
     * @return int
     */
    public function itemCount() : int
    {
        return count($this->items);
    }

    /**
     * Clear the least recently used values from the cache.
     *
     * @since 0.1.0
     *
     * @param float $portion From 0.0 to 1.0 value how much to clear.
     *
     * @return bool
     */
    public function clearLeastRecentlyUsed(float $portion = 0.25) : bool
    {
        if ($portion >= 1.0 || count($this->items) < 1 || count($this->timeouts) < 1) {
            $this->flush();
        }

        $lru = $this->lastUse;
        $lruCount = count($lru);
        $offset = $lruCount - ceil($lruCount * $portion);

        @arsort($lru);

        $toPrune = array_slice($lru, (int) $offset, null, true);

        $keys = array_keys($toPrune);

        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * Clear cache that has stale timeout value.
     *
     * @since 0.1.0
     * @return int
     */
    public function clearStaleCache() : int
    {
        $deleted = 0;

        foreach ($this->items as $key => $val) {
            if ($this->itemIsStale($key)) {
                $this->delete($key);

                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Calculate absolute timestamp for timeout.
     *
     * @since 0.1.0
     *
     * @param integer $timeout Amount of seconds to future to set timeout to.
     *
     * @return int
     */
    public function calculateTimeout(int $timeout) : int
    {
        if ($timeout <= 0) {
            return 0;
        }

        return time() + $timeout;
    }
}
