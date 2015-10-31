[![MIT license](http://img.shields.io/badge/license-MIT-brightgreen.svg)](http://opensource.org/licenses/MIT)

Memcached
=========

## About
MemcachedLock for PHP is a synchronization mechanism for enforcing limits on access to a resource in an environment where there are many threads of execution. A lock is designed to enforce a mutual exclusion concurrency control policy.

## Usage

### Create a MemcachedLock instance

```php
<?php
require 'vendor/autoload.php';

use MemcachedLock\MemcachedLock;

$Memcached = new \Memcached();
$Memcached->addServer('127.0.0.1', '11211');

$Lock = new MemcachedLock(
    $Memcached, // class of Memcached,
    'lockName', // Name of Lock,
    false, // throw Exceptions on errors
);

```

### Usage for lock a process

```php
<?php
require 'vendor/autoload.php';

use MemcachedLock\MemcachedLock;

// Create a new Memcached instance
$Memcached = new \Memcached();
$Memcached->addServer('127.0.0.1', '11211');

...

/**
 * Safe update json in Memcache storage
 * @param Memcached $Memcached
 * @param string $key
 * @param array $array
 * @throws Exception
 */
function updateJsonInMemcached(\Memcached $Memcached, $key, array $array) {
    // Create new Lock instance
    $Lock = new MemcachedLock($Memcached, 'Lock_'.$key, false);

    // Acquire lock for 1 sec.
    // If lock has acquired in another thread then we will wait 2 second, until another thread release the lock.
    // Otherwise we throw a exception.
    if (!$Lock->acquire(1, 2)) {
        throw new Exception('Can\'t get a Lock');
    }

    // Get key from storage
    $json = $Memcached->get($key);
    if (!$json) {
        $json = [];
    }
    // Some operations with json
    $jsonArray = json_decode($json, true);
    $jsonArray = array_merge($jsonArray, $array);
    $json = json_encode($jsonArray);
    // Update key in storage
    $Memcached->set($key, $json);

    // Release the lock
    // After release lock another waiting thread will be able to update json in storage
    $Lock->release();
}

```

## Methods

#### MemcachedLock::__construct

Create new instance of Memcached Lock.

```
MemcachedLock::__construct(
    \Memcached $Memcached,
    string $key,
    [, $throwException = true]
)
```

#### MemcachedLock::acquire

Try to acquire lock for <$lockTime> seconds.
If lock has acquired in another thread then we will wait <$waitTime> seconds, until another thread release the lock.
Otherwise method throws a exception or result.

Returns TRUE on success or FALSE on failure.

```
MemcachedLock::acquire(float $lockTime, float $waitTime = 0) : boolean
```

#### MemcachedLock::update

Set a new time for lock if it is acquired

Returns TRUE on success or FALSE on failure.

```
MemcachedLock::update(float $lockTime) : boolean
```

#### MemcachedLock::isLocked

Check this acquired lock.

Returns TRUE if lock is acquired and lock time is not expected
or FALSE if lock is released.

```
MemcachedLock::isLocked() : boolean
```

#### MemcachedLock::isExists

Does lock exists or acquired anywhere else?

Returns TRUE if lock is exists or FALSE if is not.

```
MemcachedLock::isExists() : boolean
```

## Installation

### Composer

Download composer:

    wget -nc http://getcomposer.org/composer.phar

and add dependency to your project:

    php composer.phar require cheprasov/php-memcached-lock

## Running tests

To run tests type in console:

    phpunit

## Something doesn't work

Feel free to fork project, fix bugs and finally request for pull
