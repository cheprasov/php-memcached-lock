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
