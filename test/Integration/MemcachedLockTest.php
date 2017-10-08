<?php
/**
 * This file is part of RedisClient.
 * git: https://github.com/cheprasov/php-memcached-lock
 *
 * (C) Alexander Cheprasov <acheprasov84@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Test\Integration;

use MemcachedLock\Exception\LockException;
use MemcachedLock\Exception\LockHasAcquiredAlreadyException;
use MemcachedLock\Exception\LostLockException;
use MemcachedLock\MemcachedLock;

class MemcachedLockTest extends \PHPUnit_Framework_TestCase {

    const TEST_KEY = 'memcachedLockTestKey';

    /**
     * @var \Memcached
     */
    protected static $Memcached;

    public static function setUpBeforeClass() {
        static::$Memcached = new \Memcached();
        // MEMCACHED_TEST_SERVER defined in phpunit.xml
        $server = explode(':', MEMCACHED_TEST_SERVER);
        static::$Memcached->addServer($server[0], $server[1]);
    }

    public function testMemcached() {
        $Memcached = static::$Memcached;
        $this->assertInstanceOf(\Memcached::class, $Memcached);
    }

    public function setUp() {
        $Memcached = static::$Memcached;
        $this->assertSame(true, $Memcached->flush());
    }

    public function test_MemcachedLock() {
        $key = static::TEST_KEY;
        $MemcachedLock = new MemcachedLock(static::$Memcached, $key);

        try {
            $MemcachedLock->acquire(0.9);
            $this->assertFalse("Expect Exception " . \InvalidArgumentException::class);
        } catch (\Exception $Exception) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $Exception);
        }

        try {
            $MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * 0.9);
            $this->assertFalse("Expect Exception " . \InvalidArgumentException::class);
        } catch (\Exception $Exception) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $Exception);
        }

        $this->assertTrue($MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * 2));

        try {
            $MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * 2);
            $this->assertFalse("Expect Exception " . LockHasAcquiredAlreadyException::class);
        } catch (\Exception $Exception) {
            $this->assertInstanceOf(LockHasAcquiredAlreadyException::class, $Exception);
        }

        try {
            $MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * 0.9);
            $this->assertFalse("Expect Exception " . \InvalidArgumentException::class);
        } catch (\Exception $Exception) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $Exception);
        }

        $this->assertTrue($MemcachedLock->update(MemcachedLock::LOCK_MIN_TIME * 4));
        $this->assertTrue($MemcachedLock->update(MemcachedLock::LOCK_MIN_TIME * 3));
        $this->assertTrue($MemcachedLock->update(MemcachedLock::LOCK_MIN_TIME * 2));

        $this->assertTrue($MemcachedLock->isLocked());
        $this->assertTrue($MemcachedLock->isExists());

        $this->assertTrue($MemcachedLock->release());

        $this->assertFalse($MemcachedLock->isLocked());
        $this->assertFalse($MemcachedLock->isExists());

        try {
            $MemcachedLock->release();
            $this->assertFalse("Expect " . LockException::class);
        } catch (\Exception $Exception) {
            $this->assertInstanceOf(LockException::class, $Exception);
        }

        try {
            $MemcachedLock->update(MemcachedLock::LOCK_MIN_TIME * 2);
            $this->assertFalse("Expect " . LockException::class);
        } catch (\Exception $Exception) {
            $this->assertInstanceOf(LockException::class, $Exception);
        }

        $this->assertFalse($MemcachedLock->isLocked());
        $this->assertFalse($MemcachedLock->isExists());
        $this->assertTrue($MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * 2));
        $this->assertTrue($MemcachedLock->update(MemcachedLock::LOCK_MIN_TIME * 2));
        $this->assertTrue($MemcachedLock->isLocked());
        $this->assertTrue($MemcachedLock->update(MemcachedLock::LOCK_MIN_TIME * 2));
        $this->assertTrue($MemcachedLock->isExists());
        $this->assertTrue($MemcachedLock->update(MemcachedLock::LOCK_MIN_TIME * 2));
        $this->assertTrue($MemcachedLock->isLocked());
        $this->assertTrue($MemcachedLock->release());
        $this->assertFalse($MemcachedLock->isLocked());
        $this->assertFalse($MemcachedLock->isExists());
    }

    public function test_MemcachedLock_WithoutExceptions() {
        $key = static::TEST_KEY;
        $MemcachedLock = new MemcachedLock(static::$Memcached, $key, MemcachedLock::FLAG_CATCH_EXCEPTIONS);

        $this->assertFalse($MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * 0.9));
        $this->assertTrue($MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * 2));
        $this->assertFalse($MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * 2));
        $this->assertFalse($MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * 0.9));

        $this->assertTrue($MemcachedLock->update(MemcachedLock::LOCK_MIN_TIME * 4));
        $this->assertTrue($MemcachedLock->update(MemcachedLock::LOCK_MIN_TIME * 3));
        $this->assertTrue($MemcachedLock->update(MemcachedLock::LOCK_MIN_TIME * 2));

        $this->assertTrue($MemcachedLock->isLocked());
        $this->assertTrue($MemcachedLock->isExists());

        $this->assertTrue($MemcachedLock->release());

        $this->assertFalse($MemcachedLock->isLocked());
        $this->assertFalse($MemcachedLock->isExists());

        $this->assertFalse($MemcachedLock->release());

        $this->assertFalse($MemcachedLock->update(MemcachedLock::LOCK_MIN_TIME * 2));

        $this->assertFalse($MemcachedLock->isLocked());
        $this->assertFalse($MemcachedLock->isExists());
        $this->assertTrue($MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * 2));
        $this->assertTrue($MemcachedLock->update(MemcachedLock::LOCK_MIN_TIME * 2));
        $this->assertTrue($MemcachedLock->isLocked());
        $this->assertTrue($MemcachedLock->update(MemcachedLock::LOCK_MIN_TIME * 2));
        $this->assertTrue($MemcachedLock->isExists());
        $this->assertTrue($MemcachedLock->update(MemcachedLock::LOCK_MIN_TIME * 2));
        $this->assertTrue($MemcachedLock->isLocked());
        $this->assertTrue($MemcachedLock->release());
        $this->assertFalse($MemcachedLock->isLocked());
        $this->assertFalse($MemcachedLock->isExists());
    }

    public function test_MemcachedLock_LockTime() {
        $key = static::TEST_KEY;

        $MemcachedLock = new MemcachedLock(static::$Memcached, $key);
        $MemcachedLock2 = new MemcachedLock(static::$Memcached, $key);

        for ($i = 1; $i <= 5; $i++) {
            $microtime = microtime(true);

            $this->assertTrue($MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * $i));

            $this->assertTrue($MemcachedLock->isLocked());
            $this->assertTrue($MemcachedLock->isExists());

            $this->assertFalse($MemcachedLock2->isLocked());
            $this->assertTrue($MemcachedLock2->isExists());

            //$microtime = microtime(true);
            $this->assertTrue($MemcachedLock2->acquire(MemcachedLock::LOCK_MIN_TIME * $i, $i + 1));
            $waitTime = microtime(true) - $microtime;

            $this->assertTrue($MemcachedLock2->update(1));

            $this->assertGreaterThan(MemcachedLock::LOCK_MIN_TIME * $i - 1, $waitTime);
            $this->assertLessThanOrEqual(MemcachedLock::LOCK_MIN_TIME * $i + 1, $waitTime);

            try {
                $MemcachedLock->isLocked();
                $this->assertFalse('Expect LostLockException');
            } catch (\Exception $Ex) {
                $this->assertInstanceOf(LostLockException::class, $Ex);
            }

            $this->assertTrue($MemcachedLock->isExists());

            $this->assertTrue($MemcachedLock2->isLocked());
            $this->assertTrue($MemcachedLock2->isExists());

            $this->assertTrue($MemcachedLock2->release());
        }
    }

    public function test_MemcachedLock_WaitTime() {
        $key = static::TEST_KEY;
        $MemcachedLock = new MemcachedLock(static::$Memcached, $key);
        $MemcachedLock2 = new MemcachedLock(static::$Memcached, $key);

        for ($i = 1; $i <= 5; $i++) {
            $this->assertTrue($MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * $i));
            $this->assertFalse($MemcachedLock2->acquire(
                MemcachedLock::LOCK_MIN_TIME * $i,
                MemcachedLock::LOCK_MIN_TIME * ($i - 1))
            );
            $this->assertTrue($MemcachedLock->release());
        }

        for ($i = 1; $i <= 5; $i++) {
            $this->assertTrue($MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * $i));
            $this->assertTrue($MemcachedLock2->acquire(
                MemcachedLock::LOCK_MIN_TIME * $i,
                MemcachedLock::LOCK_MIN_TIME * ($i + 1)
            ));
            $this->assertTrue($MemcachedLock2->release());
            try {
                $this->assertTrue($MemcachedLock->release());
                $this->assertFalse('Expect LostLockException');
            } catch (\Exception $Ex) {
                $this->assertInstanceOf(LostLockException::class, $Ex);
            }
        }
    }

    public function test_MemcachedLock_Exceptions() {
        $key = static::TEST_KEY;
        $MemcachedLock = new MemcachedLock(static::$Memcached, $key);

        $this->assertSame(true, $MemcachedLock->acquire(2));
        $this->assertSame(true, $MemcachedLock->isLocked());

        static::$Memcached->delete($key);

        try {
            $MemcachedLock->release();
            $this->assertFalse('Expect LostLockException');
        } catch (\Exception $Ex) {
            $this->assertInstanceOf(LostLockException::class, $Ex);
        }

        $this->assertSame(false, $MemcachedLock->isLocked());

        $this->assertSame(true, $MemcachedLock->acquire(2));
        $this->assertSame(true, $MemcachedLock->isLocked());

        static::$Memcached->delete($key);

        $this->assertSame(false, $MemcachedLock->isExists());
        try {
            $MemcachedLock->isLocked();
            $this->assertFalse('Expect LostLockException');
        } catch (\Exception $Ex) {
            $this->assertInstanceOf(LostLockException::class, $Ex);
        }
    }

}
