<?php
include __DIR__.'/vendor/autoload.php';

use \MemcachedLock\MemcachedLock;

$M = new Memcached();
$M->addServer('127.0.0.1', '11211');
$result = $M->get('test', null, $cas);

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
    $jsonArray = json_decode($json, true);
    $jsonArray = array_merge($jsonArray, $array);
    $json = json_encode($jsonArray);
    // Update key in storage
    $Memcached->set($key, $json);

    // Release the lock
    // After release lock another waiting thread will be able to update json in storage
    $Lock->release();
}
