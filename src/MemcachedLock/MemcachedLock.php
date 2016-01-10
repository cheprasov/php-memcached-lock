<?php
/**
 * This file is part of MemcachedLock.
 * git: https://github.com/cheprasov/php-memcached-lock
 *
 * (C) Alexander Cheprasov <cheprasov.84@ya.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MemcachedLock;

use Memcached;
use MemcachedLock\Exception\LockException;
use MemcachedLock\Exception\LockHasAcquiredAlreadyException;
use MemcachedLock\Exception\LostLockException;
use InvalidArgumentException;

class MemcachedLock implements LockInterface {

    const VERSION = '1.0.0';

    /**
     * Catch Lock exceptions and return false or null as result
     */
    const FLAG_CATCH_EXCEPTIONS = 1;

    /**
     * Use self synchronization between servers
     */
    const FLAG_USE_SELF_EXPIRE_SYNC = 2;

    /**
     * Sleep time between wait iterations, in seconds
     */
    const LOCK_DEFAULT_WAIT_SLEEP = 0.005;

    /**
     * Min lock time in seconds
     * Used without FLAG_USE_SELF_EXPIRE_SYNC
     */
    const LOCK_MIN_TIME_SYNC = 0.01;

    /**
     * Min lock time in seconds
     * Used with FLAG_USE_SELF_EXPIRE_SYNC
     */
    const LOCK_MIN_TIME = 1;

    /**
     * Expire lock time in seconds
     * Used with FLAG_USE_SELF_EXPIRE_SYNC
     */
    const LOCK_SYNC_STORAGE_EXPIRE = 86400;


    /**
     * @var Memcached
     */
    protected $Memcached;

    /**
     * @var string
     */
    protected $key;

    /**
     * @var float
     */
    protected $cas;

    /**
     * @var string
     */
    protected $token;

    /**
     * Flags
     * @var int
     */
    protected $flags = 0;

    /**
     * @var int
     */
    protected $lockExpireTimeInMilliseconds = 0;

    /**
     * @var bool
     */
    protected $isAcquired = false;

    /**
     * @param Memcached $Memcached
     * @param string $key
     * @param int $flags
     */
    public function __construct(Memcached $Memcached, $key, $flags = 0) {
        if (!isset($key)) {
            throw new InvalidArgumentException('Invalid key for Lock');
        }
        $this->Memcached = $Memcached;
        $this->key = $key;
        $this->setFlags($flags);
        $this->token = $this->createToken();
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
     * @return string
     */
    protected function createToken() {
        return posix_getpid() .':'. microtime() .':'. mt_rand(1, 9999);
    }

    /**
     * @param int|float|string $unlockTime
     * @return string
     */
    protected function getLockToken($unlockTime) {
        return $unlockTime .':'. $this->token;
    }

    /**
     * @param string $lockToken
     * @return int
     */
    protected function getExpireTimeFromLockToken($lockToken) {
        if ($lockToken == 0) {
            return 0;
        }
        return (int) explode(':', $lockToken, 2)[0];
    }

    /**
     * @param int $flags
     */
    protected function setFlags($flags = 0) {
        $this->flags = $flags;
    }

    /**
     * @param int $flag
     * @return bool
     */
    protected function isFlagExist($flag) {
        return (bool) ($this->flags & $flag);
    }

    /**
     * @return bool
     */
    protected function isThrowExceptions() {
        return !$this->isFlagExist(self::FLAG_CATCH_EXCEPTIONS);
    }

    /**
     * @param string $lockToken
     * @return bool
     */
    protected function confirmLockToken($lockToken) {
        $storageValue = $this->Memcached->get($this->key, null, $cas);
        if ($storageValue === $lockToken && $this->Memcached->getResultCode() !== Memcached::RES_NOTFOUND) {
            $this->isAcquired = true;
            $this->cas = $cas;
            $this->lockExpireTimeInMilliseconds = $this->getExpireTimeFromLockToken($lockToken);
            return true;
        }
        $this->resetLockData();
        return false;
    }

    /**
     * Reset lock data
     */
    protected function resetLockData() {
        $this->isAcquired = false;
        $this->cas = null;
        $this->lockExpireTimeInMilliseconds = null;
    }

    /**
     * @param int|float $lockTime
     * @return bool
     */
    protected function isValidLockTime($lockTime) {
        if ($this->isFlagExist(self::FLAG_USE_SELF_EXPIRE_SYNC)) {
            return $lockTime >= self::LOCK_MIN_TIME_SYNC;
        } else {
            return $lockTime >= self::LOCK_MIN_TIME;
        }
    }

    /**
     * @param int|float $waitTime
     * @return bool
     */
    protected function isValidWaitTime($waitTime) {
        return $waitTime >= 0;
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

    /**
     * @param float|int $lockTime
     * @return int
     */
    protected function getStorageExpireTime($lockTime) {
        if ($this->isFlagExist(self::FLAG_USE_SELF_EXPIRE_SYNC)) {
            return (int) max(ceil($lockTime), self::LOCK_SYNC_STORAGE_EXPIRE);
        } else {
            return (int) ceil($lockTime);
        }
    }

    /**
     * @inheritdoc
     * @throws LockHasAcquiredAlreadyException
     */
    public function acquire($lockTime, $waitTime = 0, $sleep = null) {
        if (!$this->isValidLockTime($lockTime)) {
            if ($this->isThrowExceptions()) {
                throw new InvalidArgumentException('Invalid LockTime '. $lockTime);
            }
            return false;
        }
        if (!$this->isValidWaitTime($waitTime)) {
            if ($this->isThrowExceptions()) {
                throw new InvalidArgumentException('WaitTime '. $waitTime .' must be >= 0');
            }
            return false;
        }
        if ($this->isAcquired()) {
            if ($this->isThrowExceptions()) {
                throw new LockHasAcquiredAlreadyException('Lock with key "'. $this->key .'" has acquired already');
            }
            return false;
        }

        $time = microtime(true);
        $exitTime = $waitTime + $time;

        if (!isset($sleep) || $sleep <= 0) {
            $sleep = static::LOCK_DEFAULT_WAIT_SLEEP;
        }
        $sleep *= 1000000; // seconds to microseconds

        $useSelfSync = $this->isFlagExist(self::FLAG_USE_SELF_EXPIRE_SYNC);

        do {
            $unlockTimeInMilliseconds = $this->getMilliseconds(microtime(true) + $lockTime);
            $lockToken = $this->getLockToken($unlockTimeInMilliseconds);

            if ($this->Memcached->add($this->key, $lockToken, $this->getStorageExpireTime($lockTime))) {
                if ($this->confirmLockToken($lockToken)) {
                    return true;
                }
                continue;
            }

            $storageLockToken = $this->Memcached->get($this->key, null, $cas);
            if ($this->Memcached->getResultCode() === Memcached::RES_NOTFOUND) {
                // If key doesn't exist - will try to add again
                continue;
            }

            $storageExpireTimeInMilliseconds = $storageLockToken ? $this->getExpireTimeFromLockToken($storageLockToken) : 0;

            if (!$useSelfSync && !$storageExpireTimeInMilliseconds
                || $useSelfSync && $storageExpireTimeInMilliseconds < $this->getMilliseconds()) {
                $result = $this->Memcached->cas($cas, $this->key, $lockToken, $this->getStorageExpireTime($lockTime));
                if ($result && $this->confirmLockToken($lockToken)) {
                    return true;
                }
            }

            usleep($sleep);
        } while ($waitTime && microtime(true) < $exitTime);

        return false;
    }

    /**
     * @inheritdoc
     * @throws LockException
     * @throws LostLockException
     */
    public function release() {
        if (!$this->isAcquired()) {
            if ($this->isThrowExceptions()) {
                throw new LockException('Lock "'. $this->key .'" is not acquired');
            }
            return false;
        }

        // Safe way to delete a key
        $result = $this->Memcached->cas($this->cas, $this->key, 0, 1);
        $this->resetLockData();

        if ($result) {
            return true;
        }

        if ($this->isThrowExceptions()) {
            throw new LostLockException('Lock "'. $this->key .'" has lost: '. $this->Memcached->getResultMessage());
        }
        return false;
    }

    /**
     * @inheritdoc
     * @throws \InvalidArgumentException
     * @throws LockException
     * @throws LostLockException
     */
    public function update($lockTime) {
        if (!$this->isValidLockTime($lockTime)) {
            if ($this->isThrowExceptions()) {
                throw new InvalidArgumentException('Invalid LockTime '. $lockTime);
            }
            return false;
        }
        if (!$this->isAcquired()) {
            if ($this->isThrowExceptions()) {
                throw new LockException('Lock "'. $this->key .'" is not active');
            }
            return false;
        }

        $expireTimeInMilliseconds = $this->getMilliseconds(microtime(true) + $lockTime);
        $lockToken = $this->getLockToken($expireTimeInMilliseconds);

        $result = $this->Memcached->cas($this->cas, $this->key, $lockToken, $this->getStorageExpireTime($lockTime));
        if ($result && $this->confirmLockToken($lockToken)) {
            return true;
        }
        if ($this->isThrowExceptions()) {
            throw new LostLockException('Lost Lock "'. $this->key .'" on Update: '. $this->Memcached->getResultMessage());
        }
        return false;
    }

    /**
     * @return bool
     */
    public function isAcquired() {
        return $this->isAcquired;
    }

    /**
     * @inheritdoc
     * @throws LostLockException
     */
    public function isLocked() {
        if (!$this->isAcquired()) {
            return false;
        }

        $storageLockToken = $this->Memcached->get($this->key, null, $cas);
        if ($this->Memcached->getResultCode() === Memcached::RES_NOTFOUND) {
            $this->resetLockData();
            if ($this->isThrowExceptions()) {
                throw new LostLockException('Key "'. $this->key .'" is not found in storage');
            }
            return false;
        }

        if (!$cas || (string) $cas !== (string) $this->cas) {
            $this->resetLockData();
            if ($this->isThrowExceptions()) {
                throw new LostLockException('Key "'. $this->key .'" has expired CAS');
            }
            return false;
        }

        if ($this->getLockToken($this->lockExpireTimeInMilliseconds) === $storageLockToken) {
            return true;
        }

        $this->resetLockData();
        if ($this->isThrowExceptions()) {
            throw new LostLockException(
                'Key "'. $this->key .'" with token has another token in storage '. $storageLockToken
            );
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function isExists() {
        $storageLockToken = $this->Memcached->get($this->key);
        if ($this->Memcached->getResultCode() === Memcached::RES_NOTFOUND) {
            return false;
        }

        $useSelfSync = $this->isFlagExist(self::FLAG_USE_SELF_EXPIRE_SYNC);
        $storageExpireTimeInMilliseconds = $storageLockToken ? $this->getExpireTimeFromLockToken($storageLockToken) : 0;

        if (!$useSelfSync && !$storageExpireTimeInMilliseconds
            || $useSelfSync && $storageExpireTimeInMilliseconds < $this->getMilliseconds()) {
            return false;
        }
        return true;
    }
} 
