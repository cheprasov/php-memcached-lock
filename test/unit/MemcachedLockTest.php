<?php
namespace Test\Unit;

use MemcachedLock\MemcachedLock;

class MemcachedLockTest extends \PHPUnit_Framework_TestCase {

    const TEST_KEY = 'testKey';

    const TEST_TOKEN = 'testToken';

    /**
     * @return \Memcached
     */
    protected function getMemcachedMock() {
        return $this->getMockBuilder(\Memcached::class)
            ->disableOriginalConstructor()
            ->setMethods(['add', 'cas', 'get', 'getResultCode'])
            ->getMock();
    }

    protected function getMemcachedLockMock() {
        $MemcachedLockMock = $this->getMockBuilder(MemcachedLock::class)
            ->setMethods([
                'createToken',
                'getExpireTimeFromLockToken',
                'resetLockData',
                'isFlagExist',
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

    /**
     * @see MemcachedLock::createToken
     */
    public function testMethod_createToken() {
        $key = static::TEST_KEY;
        $time = time();

        $Method = new \ReflectionMethod('\MemcachedLock\MemcachedLock', 'createToken');
        $Method->setAccessible(true);

        $result = $Method->invoke(new MemcachedLock($this->getMemcachedMock(), $key));
        $this->assertTrue(is_string($result));
        $this->assertEquals(1, preg_match('/^(\d+):(0\.\d+ \d+):(\d+)$/', $result, $matches));
        $this->assertEquals(posix_getpid(), (int) $matches[1]);
    }

    /**
     * @see MemcachedLock::getLockToken
     */
    public function testMethod_getLockToken() {
        $key = static::TEST_KEY;
        $time = time();

        $Method = new \ReflectionMethod('\MemcachedLock\MemcachedLock', 'getLockToken');
        $Method->setAccessible(true);

        $result = $Method->invoke(new MemcachedLock($this->getMemcachedMock(), $key), $time);
        $this->assertTrue(is_string($result));
        $this->assertEquals(1, preg_match('/^(\d+):(\d+):(0\.\d+ \d+):(\d+)$/', $result, $matches));
        $this->assertEquals($time, (int)$matches[1]);
        $this->assertEquals(posix_getpid(), (int)$matches[2]);
    }

    /**
     * @see MemcachedLock::getUnlockTimeFromLockToken
     */
    public function testMethod_getUnlockTimeFromLockToken() {
        $key = static::TEST_KEY;
        $Method = new \ReflectionMethod(MemcachedLock::class, 'getExpireTimeFromLockToken');
        $Method->setAccessible(true);

        for ($i = 0, $time = time(); $i < 9; $i++) {
            $time += mt_rand(1, 10);
            $token = posix_getpid() .':'. microtime(true) .':'. mt_rand(1, 9999);
            $lockToken = implode(':', [$time, $token]);
            $result = $Method->invoke(new MemcachedLock($this->getMemcachedMock(), $key), $lockToken);
            $this->assertEquals($time, $result);
        }
    }

    /**
     * @see MemcachedLock::isFlagExist
     */
    public function testMethod_isFlagExist() {
        $key = static::TEST_KEY;
        $Method = new \ReflectionMethod(MemcachedLock::class, 'isFlagExist');
        $Method->setAccessible(true);

        $MemcachedLock = new MemcachedLock($this->getMemcachedMock(), $key);
        $this->assertSame(false, $Method->invoke(
            $MemcachedLock,
            MemcachedLock::FLAG_USE_SELF_EXPIRE_SYNC
        ));
        $this->assertSame(false, $Method->invoke(
            $MemcachedLock,
            MemcachedLock::FLAG_CATCH_EXCEPTIONS
        ));

        $MemcachedLock = new MemcachedLock(
            $this->getMemcachedMock(), $key,
            MemcachedLock::FLAG_CATCH_EXCEPTIONS | MemcachedLock::FLAG_USE_SELF_EXPIRE_SYNC
        );
        $this->assertSame(true, $Method->invoke(
            $MemcachedLock,
            MemcachedLock::FLAG_CATCH_EXCEPTIONS
        ));
        $this->assertSame(true, $Method->invoke(
            $MemcachedLock,
            MemcachedLock::FLAG_USE_SELF_EXPIRE_SYNC
        ));

        $MemcachedLock = new MemcachedLock(
            $this->getMemcachedMock(), $key,
            MemcachedLock::FLAG_CATCH_EXCEPTIONS
        );
        $this->assertSame(true, $Method->invoke(
            $MemcachedLock,
            MemcachedLock::FLAG_CATCH_EXCEPTIONS
        ));
        $this->assertSame(false, $Method->invoke(
            $MemcachedLock,
            MemcachedLock::FLAG_USE_SELF_EXPIRE_SYNC
        ));

        $MemcachedLock = new MemcachedLock(
            $this->getMemcachedMock(), $key,
            MemcachedLock::FLAG_USE_SELF_EXPIRE_SYNC
        );
        $this->assertSame(false, $Method->invoke(
            $MemcachedLock,
            MemcachedLock::FLAG_CATCH_EXCEPTIONS
        ));
        $this->assertSame(true, $Method->invoke(
            $MemcachedLock,
            MemcachedLock::FLAG_USE_SELF_EXPIRE_SYNC
        ));

    }

}
