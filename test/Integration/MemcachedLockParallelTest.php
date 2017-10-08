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

use MemcachedLock\MemcachedLock;
use Parallel\Parallel;
use Parallel\Storage\MemcachedStorage;

class MemcachedLockParallelTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var \Memcached
     */
    protected static $Memcached;

    protected function getMemcached() {
        $MC = new \Memcached();
        // MEMCACHED_TEST_SERVER defined in phpunit.xml
        $server = explode(':', MEMCACHED_TEST_SERVER);
        $MC->addServer($server[0], $server[1]);
        return $MC;
    }

    public function test_parallel() {
        $MC = $this->getMemcached();
        $MC->flush();
        $this->assertSame(true, $MC->set('testcount', '1000000'));
        unset($MC);

        $Storage = new MemcachedStorage(
            ['servers'=>[explode(':', MEMCACHED_TEST_SERVER)]]
        );
        $Parallel = new Parallel($Storage);

        $start = microtime(true) + 2;

        // 1st operation
        $Parallel->run('foo', function() use ($start) {
            $MemcachedLock = new MemcachedLock($MC = $this->getMemcached(), 'lock_');
            while (microtime(true) < $start) {
                // wait for start
            }
            $c = 0;
            for ($i = 1; $i <= 10000; ++$i) {
                if ($MemcachedLock->acquire(2, 3)) {
                    $count = (int) $MC->get('testcount');
                    ++$count;
                    $MC->set('testcount', $count);
                    $MemcachedLock->release();
                    ++$c;
                }
            }
            return $c;
        });

        // 2st operation
        $Parallel->run('bar', function() use ($start) {
            $MemcachedLock = new MemcachedLock($MC = $this->getMemcached(), 'lock_');
            while (microtime(true) < $start) {
                // wait for start
            }
            $c = 0;
            for ($i = 1; $i <= 10000; ++$i) {
                if ($MemcachedLock->acquire(2, 3)) {
                    $count = (int) $MC->get('testcount');
                    ++$count;
                    $MC->set('testcount', $count);
                    $MemcachedLock->release();
                    ++$c;
                }
            }
            return $c;
        });

        $MemcachedLock = new MemcachedLock($MC = $this->getMemcached(), 'lock_');
        while (microtime(true) < $start) {
            // wait for start
        }
        $c = 0;
        for ($i = 1; $i <= 10000; ++$i) {
            if ($MemcachedLock->acquire(2, 3)) {
                $count = (int) $MC->get('testcount');
                ++$count;
                $MC->set('testcount', $count);
                $MemcachedLock->release();
                ++$c;
            }
        }

        $result = $Parallel->wait(['foo', 'bar']);

        $this->assertSame(10000, (int) $result['foo']);
        $this->assertSame(10000, (int) $result['bar']);
        $this->assertSame(10000, $c);
        $this->assertSame(1030000, (int) $MC->get('testcount'));

        $MC->flush();
    }

}
