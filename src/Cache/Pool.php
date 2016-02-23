<?php

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
    public function add($key, $value, $timeout = 0)
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
    public function set($key, $value, $timeout = 0)
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
    public function replace($key, $value, $timeout = 0)
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
    public function delete($key)
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
    public function increment($key, $timeout = 0)
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
    public function decrement($key, $timeout = 0)
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
    public function get($key)
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
    public function has($key)
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
    public function flush()
    {
        $this->items = [];
        $this->lastUse = [];
        $this->timeouts = [];

        return true;
    }

    /**
     * Check whether an item is stale and should be removed.
     *
     * @param string $key Key to check with.
     *
     * @return bool
     */
    protected function itemIsStale($key)
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
    public function parseKey($key)
    {
        $key = preg_replace('%[^a-zA-Z0-9\_\.]%', '', $key);

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
     * @param mixed $value Value to prepare
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
     * @return integer
     */
    public function itemCount()
    {
        return count($this->items);
    }

    /**
     * Clear the least recently used values from the cache.
     *
     * @since 0.1.0
     *
     * @param float $portion from 0.0 to 1.0 value how much to clear.
     *
     * @return bool
     */
    public function clearLeastRecentlyUsed($portion = 0.25)
    {
        if ($portion >= 1.0 || empty($this->items) || empty($this->timeouts)) {
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
     * @return integer
     */
    public function clearStaleCache()
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
     * @return integer
     */
    public function calculateTimeout($timeout)
    {
        if ((int) $timeout <= 0) {
            return 0;
        }

        return (int) time() + (int) $timeout;
    }
}
