[![MIT license](http://img.shields.io/badge/license-MIT-brightgreen.svg)](http://opensource.org/licenses/MIT)
[![Latest Stable Version](https://poser.pugx.org/cheprasov/php-memcached-lock/v/stable)](https://packagist.org/packages/cheprasov/php-memcached-lock)
[![Total Downloads](https://poser.pugx.org/cheprasov/php-memcached-lock/downloads)](https://packagist.org/packages/cheprasov/php-memcached-lock)

# MemcachedLock v1.0.3 for PHP >= 5.5

## About
MemcachedLock for PHP is a synchronization mechanism for enforcing limits on access to a resource in an environment where there are many threads of execution. A lock is designed to enforce a mutual exclusion concurrency control policy. Based on [Memcached](http://php.net/manual/en/book.memcached.php).

## Usage

### Create a new instance of MemcachedLock

```php
<?php
require 'vendor/autoload.php';

use MemcachedLock\MemcachedLock;

$Memcached = new \Memcached();
$Memcached->addServer('127.0.0.1', '11211');

$Lock = new MemcachedLock(
    $Memcached, // Instance of Memcached,
    'key', // Key in storage,
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

//...

/**
 * Safe update json in Memcached storage
 * @param Memcached $Memcached
 * @param string $key
 * @param array $array
 * @throws Exception
 */
function updateJsonInMemcached(\Memcached $Memcached, $key, array $array) {
    // Create new Lock instance
    $Lock = new MemcachedLock($Memcached, 'Lock_'.$key, MemcachedLock::FLAG_CATCH_EXCEPTIONS);

    // Acquire lock for 2 sec.
    // If lock has acquired in another thread then we will wait 3 second,
    // until another thread release the lock. Otherwise it throws a exception.
    if (!$Lock->acquire(2, 3)) {
        throw new Exception('Can\'t get a Lock');
    }

    // Get value from storage
    $json = $Memcached->get($key);
    if (!$json) {
        $jsonArray = [];
    } else {
        $jsonArray = json_decode($json, true);
    }

    // Some operations with json
    $jsonArray = array_merge($jsonArray, $array);

    $json = json_encode($jsonArray);
    // Update key in storage
    $Memcached->set($key, $json);

    // Release the lock
    // After $lock->release() another waiting thread (Lock) will be able to update json in storage
    $Lock->release();
}

```

## Methods

#### MemcachedLock :: __construct ( `\Memcached` **$Memcached** , `string` **$key** [, `int` **$flags** = 0 ] )
---
Create a new instance of MemcachedLock.

##### Method Pameters

1. \Memcached **$Memcached** - Instanse of [Memcached](http://php.net/manual/en/book.memcached.php) 
2. string **$key** - name of key in Memcached storage. Only locks with the same name will compete with each other for lock.
3. int **$flags**, default = 0
   * `MemcachedLock::FLAG_CATCH_EXCEPTIONS` - use this flag, if you don't want catch exceptions by yourself. Do not use this flag, if you want have a full control on situation with locks. Default behavior without this flag - all Exceptions will be thrown.
   * `MemcachedLock::FLAG_USE_SELF_EXPIRE_SYNC` - without this flag, MemcachedLock uses Memcached for sync of expire time of locks. It is very useful if you have multiple servers that are not synchronized in time. It is recommended to use this flag when lock works on single server. Can be used for multiple servers on a strict time synchronisation of multiple servers.

##### Example

```php
$Lock = new MemcachedLock($Memcached, 'lockName');
// or
$Lock = new MemcachedLock($Memcached, 'lockName', MemcachedLock::FLAG_USE_SELF_EXPIRE_SYNC);
// or
$Lock = new MemcachedLock($Memcached, 'lockName',
    MemcachedLock::FLAG_CATCH_EXCEPTIONS | MemcachedLock::FLAG_USE_SELF_EXPIRE_SYNC
);

```

#### `bool` MemcachedLock :: acquire ( `int|float` **$lockTime** , [ `float` **$waitTime** = 0 [, `float` **$sleep** = 0.005 ] ] )
---
Try to acquire lock for `$lockTime` seconds.
If lock has acquired in another thread then we will wait `$waitTime` seconds, until another thread release the lock.
Otherwise method throws a exception (if `FLAG_CATCH_EXCEPTIONS` is not set) or result.
Returns `true` on success or `false` on failure.

##### Method Pameters

1. int|float **$lockTime** - The time for lock in seconds. By default, the value is cast to a integer with round fractions up (`(int) ceil($lockTime)`). The value must be `>= 1`. **Note**, because of the behavior of the Memcached, the lock can be acquired less than a specified time about from 0.1 to 0.8 seconds. Otherwise, if  flag `FLAG_USE_SELF_EXPIRE_SYNC` is set, the value must be `>= 0.01`, in this case we have not any inaccuracies with time. 
2. float **$waitTime**, default = 0 - The time for waiting lock in seconds. Use `0` if you don't wait until lock release.
3. float **$sleep**, default = 0.005 - The wait time between iterations to check the availability of the lock.

##### Example

```php
$Lock = new MemcachedLock($Memcached, 'lockName');
$Lock->acquire(3, 4);
// ... do something
$Lock->release();
```

#### `bool` MemcachedLock :: update ( `int|float` **$lockTime** )
---
Set a new time for lock if it is acquired already. Returns `true` on success or `false` on failure. Method can throw Exceptions.

##### Method Pameters
1. int|float **$lockTime** - Please, see description for method `MemcachedLock :: acquire`

##### Example

```php
$Lock = new MemcachedLock($Memcached, 'lockName');
$Lock->acquire(3, 4);
// ... do something
$Lock->update(3);
// ... do something
$Lock->release();
```

#### `bool` MemcachedLock :: isAcquired ( )
---
Check this lock for acquired. Returns `true` on success or `false` on failure.

#### `bool` MemcachedLock :: isLocked ( )
---
Check this lock for acquired and not expired, and active yet. Returns `true` on success or `false` on failure. Method can throw Exceptions.

#### `bool` MemcachedLock :: isExists ()
---
Does lock exists or acquired anywhere? Returns `true` if lock is exists or `false` if is not.

## Installation

### Composer

Download composer:

    wget -nc http://getcomposer.org/composer.phar

and add dependency to your project:

    php composer.phar require cheprasov/php-memcached-lock

## Running tests

To run tests type in console:

    ./vendor/bin/phpunit ./test/

## Something doesn't work

Feel free to fork project, fix bugs and finally request for pull
