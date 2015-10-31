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

interface LockInterface {

    /**
     * Acquire the lock
     * @param int $lockTime
     * @param int $waitTime
     * @return boolean
     */
    public function acquire($lockTime, $waitTime = 0);

    /**
     * Release the lock
     * @return boolean
     */
    public function release();

    /**
     * Set a new time for acquired lock
     * @param int|float|string $lockTime
     * @return boolean
     */
    public function update($lockTime);


    /**
     * Check this lock for acquired and not expired
     * @return boolean
     */
    public function isLocked();

    /**
     * Does lock exists or acquired anywhere else?
     * @return boolean
     */
    public function isExists();

}
