<?php
namespace Test;

include(__DIR__ . '/Mock/Memcached.php');

use MemcachedLock\Exception\LockException;
use MemcachedLock\Exception\LockIsActiveException;
use MemcachedLock\MemcachedLock;
use Test\Mock\Memcached;

class testMemcacheLock extends \PHPUnit_Framework_TestCase {

    const TEST_KEY = 'testKey';

    const TEST_TOKEN = 'testToken';

    const LOCK_MIN_TIME = 0.05;

    protected $testMethods = [
        'incTestMemcachedLock',
        'incTestMemcachedLockWithoutExceptions',
        'incTestMemcachedLockLockTime',
        'incTestMemcachedLockWaitTime',
    ];

    protected function getMemcachedMock() {
        return $this->getMockBuilder(\Memcached::class)
            ->disableOriginalConstructor()
            ->setMethods(['add', 'cas', 'get', 'getResultCode'])
            ->getMock();
    }

    protected function getMemcachedLockMock() {
        $MemcachedLockMock = $this->getMockBuilder(MemcachedLock::class)
            ->setMethods([
                'getUnlockTimeFromLockToken',
                'clear',
                '_getMockMemcached'
            ])
            ->setConstructorArgs([
                $Memcached = $this->getMemcachedMock(),
                static::TEST_KEY
            ])
            ->getMock();

        $MemcachedLockMock->method('_getMockMemcached')
            ->will($this->returnValue($Memcached));

        return $MemcachedLockMock;
    }

    public function testMethod_getLockToken() {
        $key = static::TEST_KEY;
        $time = time();

        $Method = new \ReflectionMethod(MemcachedLock::class, 'getLockToken');
        $Method->setAccessible(true);

        $result = $Method->invoke(new MemcachedLock($this->getMemcachedMock(), $key), $time);
        $this->assertTrue(is_string($result));
        $this->assertEquals(1, preg_match('/^(\d+):(\d+):([a-z0-9]{32}):(\d+)$/', $result, $matches));
        $this->assertEquals($time, (int)$matches[1]);
        $this->assertEquals(posix_getpid(), (int)$matches[2]);
    }

    public function testMethod_getUnlockTimeFromLockToken() {
        $key = static::TEST_KEY;
        $Method = new \ReflectionMethod(MemcachedLock::class, 'getUnlockTimeFromLockToken');
        $Method->setAccessible(true);

        for ($i = 0, $time = time(); $i < 9; $i++) {
            $time += mt_rand(1, 10);
            $token = posix_getpid() . ':' . md5(microtime(true)) . ':' . mt_rand(1, 9999);
            $lockToken = implode(':', [$time, $token]);
            $result = $Method->invoke(new MemcachedLock($this->getMemcachedMock(), $key), $lockToken);
            $this->assertEquals($time, $result);
        }
    }


    public function testMemcachedMock() {
        foreach ($this->testMethods as $method) {
            $Memcached = new Memcached();
            $this->assertTrue($Memcached instanceof \Memcached);
            $this->$method($Memcached);
        }
    }

    public function testMemcached() {
        try {
            $Memcached = new \Memcached();
            $this->assertTrue($Memcached instanceof \Memcached);
            $Memcached->addServer('127.0.0.1', '11211');
        } catch (\Exception $Exception) {
            return;
        }

        foreach ($this->testMethods as $method) {
            $Memcached->delete(static::TEST_KEY);
            $this->$method($Memcached);
        }
    }

    protected function incTestMemcachedLock(\Memcached $Memcached) {
        $key = static::TEST_KEY;
        $MemcachedLock = new MemcachedLock($Memcached, $key);

        try {
            $MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * 0.9);
            $this->assertFalse("Expect " . \InvalidArgumentException::class);
        } catch (\Exception $Exception) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $Exception);
        }

        $this->assertTrue($MemcachedLock->acquire(static::LOCK_MIN_TIME * 2));

        try {
            $MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * 2);
            $this->assertFalse("Expect " . LockIsActiveException::class);
        } catch (\Exception $Exception) {
            $this->assertInstanceOf(LockIsActiveException::class, $Exception);
        }

        try {
            $MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * 0.9);
            $this->assertFalse("Expect " . \InvalidArgumentException::class);
        } catch (\Exception $Exception) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $Exception);
        }

        $this->assertTrue($MemcachedLock->update(static::LOCK_MIN_TIME * 4));
        $this->assertTrue($MemcachedLock->update(static::LOCK_MIN_TIME * 3));
        $this->assertTrue($MemcachedLock->update(static::LOCK_MIN_TIME * 2));

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
            $MemcachedLock->update(static::LOCK_MIN_TIME * 2);
            $this->assertFalse("Expect " . LockException::class);
        } catch (\Exception $Exception) {
            $this->assertInstanceOf(LockException::class, $Exception);
        }

        $this->assertFalse($MemcachedLock->isLocked());
        $this->assertFalse($MemcachedLock->isExists());
        $this->assertTrue($MemcachedLock->acquire(static::LOCK_MIN_TIME * 2));
        $this->assertTrue($MemcachedLock->update(static::LOCK_MIN_TIME * 2));
        $this->assertTrue($MemcachedLock->isLocked());
        $this->assertTrue($MemcachedLock->update(static::LOCK_MIN_TIME * 2));
        $this->assertTrue($MemcachedLock->isExists());
        $this->assertTrue($MemcachedLock->update(static::LOCK_MIN_TIME * 2));
        $this->assertTrue($MemcachedLock->isLocked());
        $this->assertTrue($MemcachedLock->release());
        $this->assertFalse($MemcachedLock->isLocked());
        $this->assertFalse($MemcachedLock->isExists());
    }

    protected function incTestMemcachedLockWithoutExceptions(\Memcached $Memcached) {
        $key = static::TEST_KEY;
        $MemcachedLock = new MemcachedLock($Memcached, $key, false);

        $this->assertFalse($MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * 0.9));
        $this->assertTrue($MemcachedLock->acquire(static::LOCK_MIN_TIME * 2));
        $this->assertFalse($MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * 2));
        $this->assertFalse($MemcachedLock->acquire(MemcachedLock::LOCK_MIN_TIME * 0.9));

        $this->assertTrue($MemcachedLock->update(static::LOCK_MIN_TIME * 4));
        $this->assertTrue($MemcachedLock->update(static::LOCK_MIN_TIME * 3));
        $this->assertTrue($MemcachedLock->update(static::LOCK_MIN_TIME * 2));

        $this->assertTrue($MemcachedLock->isLocked());
        $this->assertTrue($MemcachedLock->isExists());

        $this->assertTrue($MemcachedLock->release());

        $this->assertFalse($MemcachedLock->isLocked());
        $this->assertFalse($MemcachedLock->isExists());

        $this->assertFalse($MemcachedLock->release());

        $this->assertFalse($MemcachedLock->update(static::LOCK_MIN_TIME * 2));

        $this->assertFalse($MemcachedLock->isLocked());
        $this->assertFalse($MemcachedLock->isExists());
        $this->assertTrue($MemcachedLock->acquire(static::LOCK_MIN_TIME * 2));
        $this->assertTrue($MemcachedLock->update(static::LOCK_MIN_TIME * 2));
        $this->assertTrue($MemcachedLock->isLocked());
        $this->assertTrue($MemcachedLock->update(static::LOCK_MIN_TIME * 2));
        $this->assertTrue($MemcachedLock->isExists());
        $this->assertTrue($MemcachedLock->update(static::LOCK_MIN_TIME * 2));
        $this->assertTrue($MemcachedLock->isLocked());
        $this->assertTrue($MemcachedLock->release());
        $this->assertFalse($MemcachedLock->isLocked());
        $this->assertFalse($MemcachedLock->isExists());
    }

    protected function incTestMemcachedLockLockTime(\Memcached $Memcached) {
        $key = static::TEST_KEY;
        $MemcachedLock = new MemcachedLock($Memcached, $key);
        $MemcachedLock2 = new MemcachedLock($Memcached, $key);

        for ($i = 1; $i <= 10; $i++) {
            $this->assertTrue($MemcachedLock->acquire(static::LOCK_MIN_TIME * $i));

            $this->assertTrue($MemcachedLock->isLocked());
            $this->assertTrue($MemcachedLock->isExists());

            $this->assertFalse($MemcachedLock2->isLocked());
            $this->assertTrue($MemcachedLock2->isExists());

            $microtime = microtime(true);
            $this->assertTrue($MemcachedLock2->acquire(static::LOCK_MIN_TIME * $i, $i));
            $waitTime = microtime(true) - $microtime;

            $this->assertTrue($MemcachedLock2->update(1));

            $this->assertGreaterThanOrEqual(static::LOCK_MIN_TIME * $i, $waitTime);
            $this->assertLessThanOrEqual(static::LOCK_MIN_TIME * $i + 2 * MemcachedLock::LOCK_WAIT_TIME, $waitTime);

            $this->assertFalse($MemcachedLock->isLocked());
            $this->assertTrue($MemcachedLock->isExists());

            $this->assertTrue($MemcachedLock2->isLocked());
            $this->assertTrue($MemcachedLock2->isExists());

            $this->assertTrue($MemcachedLock2->release());
        }

    }

    protected function incTestMemcachedLockWaitTime(\Memcached $Memcached) {
        $key = static::TEST_KEY;
        $MemcachedLock = new MemcachedLock($Memcached, $key);
        $MemcachedLock2 = new MemcachedLock($Memcached, $key);

        for ($i = 1; $i <= 10; $i++) {
            $this->assertTrue($MemcachedLock->acquire(static::LOCK_MIN_TIME * $i));
            $this->assertFalse($MemcachedLock2->acquire(static::LOCK_MIN_TIME * $i, static::LOCK_MIN_TIME * ($i - 1)));
            $this->assertTrue($MemcachedLock->release());
        }

        for ($i = 1; $i <= 10; $i++) {
            $this->assertTrue($MemcachedLock->acquire(static::LOCK_MIN_TIME * $i));
            $this->assertTrue
            ($MemcachedLock2->acquire(static::LOCK_MIN_TIME * $i, static::LOCK_MIN_TIME * ($i + 1)));
            $this->assertTrue($MemcachedLock2->release());
        }

    }


}
