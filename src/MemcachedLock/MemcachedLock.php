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

use Memcached;
use MemcachedLock\Exception\LockException;
use MemcachedLock\Exception\LockIsActiveException;
use MemcachedLock\Exception\LostLockException;

class MemcachedLock extends AbstractLock {

    /**
     * @var Memcached
     */
    protected $Memcached;

    /**
     * @var float
     */
    protected $cas;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var boolean
     */
    protected $throwException;

    /**
     * @param Memcached $Memcached
     * @param string $key
     * @param boolean $throwException
     */
    public function __construct(Memcached $Memcached, $key, $throwException = true) {
        parent::__construct($key);
        $this->Memcached = $Memcached;
        $this->token = posix_getpid() .':'. md5(microtime(true)) .':'. mt_rand(1, 9999);
        $this->throwException = (boolean) $throwException;
    }

    /**
     * @param int|string $unlockTime
     * @return string
     */
    protected function getLockToken($unlockTime) {
        return implode(':', [$unlockTime, $this->token]);
    }

    /**
     * @param string $lockToken
     * @return float
     */
    protected function getUnlockTimeFromLockToken($lockToken) {
        return (int) explode(':', $lockToken, 2)[0];
    }

    /**
     * Cleat lock data
     */
    protected function clear() {
        $this->isAcquired = false;
        $this->cas = null;
        $this->unlockTime = null;
    }

    /**
     * @param string $lockToken
     * @return bool
     */
    protected function confirmLockToken($lockToken) {
        $cachedValue = $this->Memcached->get($this->key, null, $cas);
        if ($cachedValue === $lockToken && $this->Memcached->getResultCode() !== Memcached::RES_NOTFOUND) {
            $this->isAcquired = true;
            $this->cas = $cas;
            $this->unlockTime = $this->getUnlockTimeFromLockToken($lockToken);
            return true;
        }
        $this->clear();
        return false;
    }

    /**
     * @inheritdoc
     * @throws LockIsActiveException
     */
    public function acquire($lockTime, $waitTime = 0) {
        if ($lockTime < static::LOCK_MIN_TIME) {
            if ($this->throwException) {
                throw new \InvalidArgumentException();
            }
            return false;
        }

        if ($this->isAcquired()) {
            if ($this->throwException) {
                throw new LockIsActiveException("Lock '{$this->key}' is active yet");
            }
        }

        $time = microtime(true);
        $exitTime = $waitTime + $time;

        do {
            $unlockMilliTime = $this->getMilliseconds(microtime(true) + $lockTime);
            $lockToken = $this->getLockToken($unlockMilliTime);

            if ($this->Memcached->add($this->key, $lockToken, max($lockTime, static::LOCK_EXPIRE))) {
                if ($this->confirmLockToken($lockToken)) {
                    return true;
                }
                continue;
            }

            $cachedLockToken = $this->Memcached->get($this->key, null, $cas);
            if ($this->Memcached->getResultCode() === Memcached::RES_NOTFOUND) {
                // Ключа не сущестует, вернемся в начало и попробуем создать лок
                continue;
            }

            $cachedUnlockMilliTime = $cachedLockToken ? $this->getUnlockTimeFromLockToken($cachedLockToken) : 0;

            if ($cachedUnlockMilliTime < $this->getMilliseconds()) {
                $result = $this->Memcached->cas($cas, $this->key, $lockToken, max($lockTime, static::LOCK_EXPIRE));
                if ($result && $this->confirmLockToken($lockToken)) {
                    return true;
                }
            }

            usleep(static::LOCK_WAIT_TIME * 1000000);

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
            if ($this->throwException) {
                throw new LockException("Lock '{$this->key}' is not active");
            }
            return false;
        }
        // Такой хитрый способ освобождения лока
        $result = $this->Memcached->cas($this->cas, $this->key, 0, 1);
        $this->clear();
        if (!$result) {
            if ($this->throwException) {
                throw new LostLockException("Lock '{$this->key}' was lost: {$this->Memcached->getResultMessage()}");
            }
            return false;
        }
        return true;
    }

    /**
     * @inheritdoc
     * @throws \InvalidArgumentException
     * @throws LockException
     * @throws LostLockException
     */
    public function update($lockTime) {
        if ($lockTime < static::LOCK_MIN_TIME) {
            if ($this->throwException) {
                throw new \InvalidArgumentException();
            }
            return false;
        }
        if (!$this->isAcquired() || !$this->cas) {
            if ($this->throwException) {
                throw new LockException("Lock '{$this->key}' is not active");
            }
            return false;
        }
        $unlockMilliTime = $this->getMilliseconds(microtime(true) + $lockTime);
        $lockToken = $this->getLockToken($unlockMilliTime);
        $result = $this->Memcached->cas($this->cas, $this->key, $lockToken, max($lockTime, static::LOCK_EXPIRE));
        if ($result && $this->confirmLockToken($lockToken)) {
            return true;
        }
        if ($this->throwException) {
            throw new LostLockException("Lock '{$this->key}' {$this->Memcached->getResultMessage()}");
        }
        return false;
    }

    /**
     * @inheritdoc
     * @throws LostLockException
     */
    public function isLocked() {
        if (!parent::isAcquired()) {
            return false;
        }
        $cachedLockToken = $this->Memcached->get($this->key, null, $cas);
        if ($this->Memcached->getResultCode() === Memcached::RES_NOTFOUND) {
            if ($this->throwException) {
                throw new LostLockException("Key '{$this->key}' is not found");
            }
            return false;
        }
        if (!$cas || $cas != $this->cas) {
            if ($this->throwException) {
                throw new LostLockException("Key '{$this->key}' has wrong CAS");
            }
            return false;
        }
        if ($this->getLockToken($this->unlockTime) === $cachedLockToken) {
            return true;
        }
        $cachedUnlockMilliTime = $cachedLockToken ? $this->getUnlockTimeFromLockToken($cachedLockToken) : 0;
        if ($cachedUnlockMilliTime < $this->getMilliseconds()) {
            if ($this->throwException) {
                throw new LostLockException("Key '{$this->key}' was expired by wrong unlockTime '{$cachedUnlockMilliTime}'");
            }
            return false;
        }
        if ($this->throwException) {
            throw new LostLockException("Key '{$this->key}' undefined error");
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function isExists() {
        $cachedLockToken = $this->Memcached->get($this->key);
        if ($this->Memcached->getResultCode() === Memcached::RES_NOTFOUND) {
            return false;
        }
        $cachedUnlockTime = $cachedLockToken ? $this->getUnlockTimeFromLockToken($cachedLockToken) : 0;
        return $cachedUnlockTime > microtime(true);
    }
} 
