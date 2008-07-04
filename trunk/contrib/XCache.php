<?php

/**
 * xcache wrapper to mimic pecl/memcache OOP
 * interface for use with MemProxy.  It is not
 * the goal of this object to fully implement
 * the pecl/memcached functionality.
 */

if(!function_exists("xcache_get")) {
    trigger_error("xcache is not enabled in this PHP build.", E_USER_ERROR);
}

class XCache {

    public function get($key) {
        $ret = false;
        if(xcache_isset($key)){
            $ret = xcache_get($key);
        }
        return $ret;
    }

    public function set($key, $var, $flags, $ttl) {
        return xcache_set($key, $var, $ttl);
    }

    public function delete($key) {
        return xcache_unset($key);
    }
}
