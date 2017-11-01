<?php

return [
    "database" => [
        "adapter"  => "Mysql",
        "host"     => "localhost",
        "username" => "community",
        "password" => "123456",
        "dbname"   => "community",
    ],
    "cache" => [
        "engine" => 'libmemcached', // Libmemcached, Memcache, Redis, APC
        "lifetime" => 60 * 60 * 2, // cache expiration time in seconds
        "host" => "127.0.0.1",
        "port" => 11211,
        "auth" => "", // Redis
        "persistent" => false,
        "index" => 0, // Redis
        "prefix" => '', // APC & Libmemcached
        "weight" => '1' // Libmemcached only
    ],
    "options" => [
        "enable_cache" => true, // disable this to kill your DB
        "enable_announce" => true, // enable or disable the announce endpoint (allow clients create torrents)
    ],
];