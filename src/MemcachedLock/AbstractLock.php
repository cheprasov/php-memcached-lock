<?php
/**
 * This file is part of MemcacheLock.
 * git: https://github.com/cheprasov/php-memcached-lock
 *
 * (C) Alexander Cheprasov <cheprasov.84@ya.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MemcachedLock;

abstract class AbstractLock implements LockInterface {

    /**
     * Min lock time in seconds
     */
    const LOCK_MIN_TIME = 0.01; // sec

    /**
     * Expire lock time
     */
    const LOCK_EXPIRE = 846400; // 1 day

    /**
     *
     */
    const LOCK_WAIT_TIME = 0.005; // sec

    /**
     * Storage key
     * @var string
     */
    protected $key;

    /**
     * @var int
     */
    protected $unlockTime = 0;

    /**
     * @var boolean
     */
    protected $isAcquired = false;

    /**
     * @param string $key
     */
    public function __construct($key) {
        if (!strlen($key)) {
            throw new \InvalidArgumentException('Please, use correct name for Lock key');
        }
        $this->key = $key;
    }

    /**
     *
     */
    public function __destruct() {
        if ($this->isAcquired()) {
            $this->release();
        }
    }

    /**
     * @return boolean
     */
    protected function isAcquired() {
        if ($this->isAcquired && $this->unlockTime > $this->getMilliseconds()) {
            return true;
        }
        return false;
    }
    /**
     * @param float|int $time
     * @return int
     */
    protected function getMilliseconds($time = null) {
        if (is_null($time)) {
            $time = microtime(true);
        }
        return round($time * 1000);
    }

}
