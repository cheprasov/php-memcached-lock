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
namespace Test;

use MemcachedLock\MemcachedLock;

class VersionTest extends \PHPUnit_Framework_TestCase {

    public function test_version() {
        chdir(__DIR__.'/../');
        $composer = json_decode(file_get_contents('./composer.json'), true);

        $this->assertSame(true, isset($composer['version']));
        $this->assertSame(
            MemcachedLock::VERSION,
            $composer['version'],
            'Please, change version in composer.json'
        );

        $readme = file('./README.md');
        $this->assertSame(
            true,
            strpos($readme[4], 'MemcachedLock v'.$composer['version']) > 0,
            'Please, change version in README.md'
        );

    }

}
