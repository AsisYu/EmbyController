<?php
return [
    // 默认缓存驱动
    'default' => env('CACHE_TYPE', '') == 'redis' ? 'redis' : 'sync',

    // 缓存连接方式配置
    'connections' => [
        'sync' => [
            // 驱动方式
            'type'       => 'Sync',
            'queue'      => 'main'
        ],
        // 配置Redis
        'redis'    =>    [
            'type'     => 'redis',
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'port'     => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASS', ''),
            'select'   => '0',
            'expire'   => 0,
            'prefix'   => '',
            'timeout'  => 0,
            'persistent' => false,
            'queue'    => 'main',
            'db'       => env('REDIS_DB', 0),
        ],
    ],
]; 