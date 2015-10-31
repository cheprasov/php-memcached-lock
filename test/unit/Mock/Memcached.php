<?php
namespace Test\Mock;

class Memcached extends \Memcached {

    protected $storage = [];

    protected $resultMessage;

    protected $cas = [];

    /**
     * @param string $key
     * @return float
     */
    protected function incrCas($key) {
        if (isset($this->cas[$key])) {
            return ++$this->cas[$key];
        }
        return $this->cas[$key] = 1.0;
    }

    public function __construct($persistent_id = null, $callback = null) {
    }

    public function getResultCode() {
    }

    public function getResultMessage() {
    }

    /**
     * @param string $key
     * @param callable|null $cache_cb
     * @param null $cas_token
     * @return mixed
     */
    public function get($key, callable $cache_cb = null, &$cas_token = null) {
        if (!isset($this->storage[$key])) {
            $this->resultMessage = static::RES_NOTFOUND;
            return false;
        }
        if ($this->storage[$key]['expire'] && $this->storage[$key]['expire'] < time()) {
            unset($this->storage[$key]);
            $this->resultMessage = static::RES_NOTFOUND;
            return false;
        }
        $cas_token = $this->storage[$key]['cas'];
        return $this->storage[$key]['value'];
    }

    public function getByKey($server_key, $key, callable $cache_cb = null, &$cas_token = null) {
    }

    public function getMulti(array $keys, array &$cas_tokens = null, $flags = null) {
    }

    public function getMultiByKey($server_key, array $keys, &$cas_tokens = null, $flags = null) {
    }

    public function getDelayed(array $keys, $with_cas = null, callable $value_cb = null) {
    }

    public function getDelayedByKey($server_key, array $keys, $with_cas = null, callable $value_cb = null) {
    }

    public function fetch() {
    }

    public function fetchAll() {
    }

    public function set($key, $value, $expiration = null) {
    }

    public function setByKey($server_key, $key, $value, $expiration = null) {
    }

    public function touch($key, $expiration) {
    }

    public function touchByKey($server_key, $key, $expiration) {
    }

    public function setMulti(array $items, $expiration = null) {
    }

    public function setMultiByKey($server_key, array $items, $expiration = null) {
    }

    public function cas($cas_token, $key, $value, $expiration = null) {
        if (!isset($this->storage[$key]['cas']) || $this->storage[$key]['cas'] != $cas_token) {
            $this->resultMessage = static::RES_DATA_EXISTS;
            return false;
        }

        if ($expiration) {
            $expiration += $expiration <= 60*60*24*30 ? time() : 0;
        }

        $this->storage[$key] = [
            'expire' => $expiration,
            'value' => $value,
            'cas' => $this->incrCas($key)
        ];
        $this->resultMessage = static::RES_SUCCESS;
        return true;

    }

    public function casByKey($cas_token, $server_key, $key, $value, $expiration = null) {
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param null $expiration
     * @return bool
     */
    public function add($key, $value, $expiration = null) {
        if (isset($this->storage[$key])) {
            $this->resultMessage = static::RES_NOTSTORED;
            return false;
        }

        if ($expiration) {
            $expiration += $expiration <= 60*60*24*30 ? time() : 0;
        }

        $this->storage[$key] = [
            'expire' => $expiration,
            'value' => $value,
            'cas' => $this->incrCas($key)
        ];
        $this->resultMessage = static::RES_SUCCESS;
        return true;
    }

    public function addByKey($server_key, $key, $value, $expiration = null) {
    }

    public function append($key, $value) {
    }

    public function appendByKey($server_key, $key, $value) {
    }

    public function prepend($key, $value) {
    }

    public function prependByKey($server_key, $key, $value) {
    }

    public function replace($key, $value, $expiration = null) {
    }

    public function replaceByKey($server_key, $key, $value, $expiration = null) {
    }

    public function delete($key, $time = 0) {
    }

    public function deleteMulti(array $keys, $time = 0) {
    }

    public function deleteByKey($server_key, $key, $time = 0) {
    }

    public function deleteMultiByKey($server_key, array $keys, $time = 0) {
    }

    public function increment($key, $offset = 1, $initial_value = 0, $expiry = 0) {
    }

    public function decrement($key, $offset = 1, $initial_value = 0, $expiry = 0) {
    }

    public function incrementByKey($server_key, $key, $offset = 1, $initial_value = 0, $expiry = 0) {
    }

    public function decrementByKey($server_key, $key, $offset = 1, $initial_value = 0, $expiry = 0) {
    }

    public function addServer($host, $port, $weight = 0) {
    }

    public function addServers(array $servers) {
    }

    public function getServerList() {
    }

    public function getServerByKey($server_key) {
    }

    public function resetServerList() {
    }

    public function quit() {
    }

    public function getStats() {
    }

    public function getVersion() {
    }

    public function getAllKeys() {
    }

    public function flush($delay = 0) {
    }

    public function getOption($option) {
    }

    public function setOption($option, $value) {
    }

    public function setOptions(array $options) {
    }

    public function isPersistent() {
    }

    public function isPristine() {
    }
}
