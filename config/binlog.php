<?php

return [
    // MySQL连接配置
    'mysql' => [
        'host' => env('binlog.mysql.host', '127.0.0.1'),
        'port' => env('binlog.mysql.port', 3306),
        'user' => env('binlog.mysql.user', 'root'),
        'password' => env('binlog.mysql.password', ''),
        'charset' => env('binlog.mysql.charset', 'utf8mb4'),
        'slave_id' => env('binlog.mysql.slave_id', 666),
    ],

    // Binlog配置
    'binlog' => [
        // 监听的数据库，为空则监听所有
        'databases_only' => [],
        // 监听的表，为空则监听所有
        'tables_only' => [],
        // 监听的事件类型
        'events_only' => [
            'write', // INSERT
            'update', // UPDATE
            'delete', // DELETE
        ],
        // 忽略的事件类型
        'events_ignore' => [],
        // 从指定位置开始监听
        'bin_log_file_name' => '',
        'bin_log_position' => 0,
        // GTID支持
        'gtid' => '',
        'maria_db_gtid' => '',
        // 心跳间隔（秒）
        'heartbeat_period' => 30,
    ],

    // 队列配置
    'queue' => [
        // 是否启用队列转发
        'enabled' => env('binlog.queue.enabled', true),
        // 队列连接名
        'connection' => env('binlog.queue.connection', 'default'),
        // 队列名称
        'queue_name' => env('binlog.queue.name', 'binlog'),
        // 任务类
        'job_class' => 'yangweijie\\ThinkBinlog\\Job\\BinlogJob',
    ],

    // 后台运行配置
    'daemon' => [
        // 是否启用守护进程
        'enabled' => env('binlog.daemon.enabled', false),
        // PID文件路径
        'pid_file' => runtime_path() . 'binlog.pid',
        // 日志文件路径
        'log_file' => runtime_path() . 'log/binlog.log',
        // 最大内存使用量（MB）
        'memory_limit' => env('binlog.daemon.memory_limit', 128),
        // 重启间隔（秒）
        'restart_interval' => env('binlog.daemon.restart_interval', 3600),
    ],

    // 事件订阅配置
    'subscribers' => [
        // 事件订阅器列表
        // 'App\\Listener\\UserBinlogListener',
    ],

    // 日志配置
    'log' => [
        'enabled' => env('binlog.log.enabled', true),
        'level' => env('binlog.log.level', 'info'),
        'channel' => env('binlog.log.channel', 'binlog'),
    ],
];
