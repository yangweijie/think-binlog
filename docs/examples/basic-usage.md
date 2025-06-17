# 基础使用示例

## 1. 快速开始

### 安装扩展

```bash
composer require yangweijie/think-binlog
```

### 配置MySQL

```sql
-- 创建复制用户
CREATE USER 'binlog_user'@'%' IDENTIFIED BY 'your_password';
GRANT REPLICATION SLAVE, REPLICATION CLIENT ON *.* TO 'binlog_user'@'%';
GRANT SELECT ON `your_database`.* TO 'binlog_user'@'%';
FLUSH PRIVILEGES;
```

### 基础配置

```php
// config/binlog.php
return [
    'mysql' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'user' => 'binlog_user',
        'password' => 'your_password',
        'charset' => 'utf8mb4',
        'slave_id' => 666,
    ],
    'binlog' => [
        'databases_only' => ['your_database'],
        'tables_only' => ['users', 'orders'],
        'events_only' => ['write', 'update', 'delete'],
    ],
    'queue' => [
        'enabled' => true,
        'connection' => 'default',
        'queue_name' => 'binlog',
    ],
];
```

## 2. 命令行使用

### 前台运行（调试模式）

```bash
# 启动监听器
php think binlog listen

# 输出示例：
# [2023-01-01 12:00:00] 正在启动Binlog监听器（调试模式）...
# [2023-01-01 12:00:00] 按 Ctrl+C 停止监听
# [2023-01-01 12:00:01] 接收到Binlog事件: your_database.users [insert]
```

### 后台运行（生产模式）

```bash
# 启动守护进程
php think binlog start --daemon

# 查看状态
php think binlog status

# 停止守护进程
php think binlog stop

# 重启守护进程
php think binlog restart
```

## 3. 编程方式使用

### 直接使用监听器

```php
<?php

use yangweijie\ThinkBinlog\BinlogListener;

// 创建监听器
$listener = new BinlogListener([
    'mysql' => [
        'host' => '127.0.0.1',
        'user' => 'binlog_user',
        'password' => 'your_password',
    ],
    'binlog' => [
        'databases_only' => ['your_database'],
        'tables_only' => ['users'],
    ],
]);

// 启动监听
$listener->start();
```

### 使用容器

```php
<?php

use think\facade\App;

// 获取监听器实例
$listener = App::make('binlog.listener');

// 启动监听
$listener->start();
```

## 4. 事件处理

### 监听队列事件

```php
<?php

// 在事件监听器中处理
use think\facade\Event;

// 监听所有插入事件
Event::listen('binlog.insert', function ($database, $table, $data) {
    echo "数据插入: {$database}.{$table}\n";
    print_r($data);
});

// 监听所有更新事件
Event::listen('binlog.update', function ($database, $table, $data) {
    echo "数据更新: {$database}.{$table}\n";
    print_r($data);
});

// 监听所有删除事件
Event::listen('binlog.delete', function ($database, $table, $data) {
    echo "数据删除: {$database}.{$table}\n";
    print_r($data);
});

// 监听特定表的所有事件
Event::listen('binlog.your_database.users', function ($eventType, $data) {
    echo "用户表事件: {$eventType}\n";
    print_r($data);
});
```

### 事件数据结构

```php
// 事件数据示例
$eventData = [
    'event_info' => [
        'type' => 'insert',                    // 事件类型: insert, update, delete, query
        'database' => 'your_database',         // 数据库名
        'table' => 'users',                    // 表名
        'datetime' => '2023-01-01 12:00:00',   // 事件时间
        'timestamp' => 1672574400,             // 时间戳
        'log_position' => 1234,                // Binlog位置
        'event_size' => 56,                    // 事件大小
    ],
    'data' => [
        'rows' => [                            // 变更的行数据
            [
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'created_at' => '2023-01-01 12:00:00',
            ]
        ],
        'columns' => [                         // 表结构信息
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'name', 'type' => 'varchar'],
            ['name' => 'email', 'type' => 'varchar'],
            ['name' => 'created_at', 'type' => 'datetime'],
        ],
    ],
];
```

## 5. 常见使用场景

### 缓存失效

```php
<?php

Event::listen('binlog.update', function ($database, $table, $data) {
    if ($table === 'users') {
        foreach ($data['data']['rows'] as $row) {
            $userId = $row['after']['id'] ?? $row['before']['id'];
            // 清除用户缓存
            cache()->delete("user:{$userId}");
        }
    }
});
```

### 搜索引擎同步

```php
<?php

Event::listen('binlog.insert', function ($database, $table, $data) {
    if ($table === 'articles') {
        foreach ($data['data']['rows'] as $row) {
            // 同步到Elasticsearch
            $elasticsearch->index([
                'index' => 'articles',
                'id' => $row['id'],
                'body' => $row,
            ]);
        }
    }
});
```

### 数据统计

```php
<?php

Event::listen('binlog.insert', function ($database, $table, $data) {
    if ($table === 'orders') {
        foreach ($data['data']['rows'] as $row) {
            // 更新统计数据
            $redis = app('redis');
            $redis->incr('stats:orders:total');
            $redis->incrByFloat('stats:orders:amount', $row['amount']);
        }
    }
});
```

### 审计日志

```php
<?php

Event::listen('binlog.update', function ($database, $table, $data) {
    if ($table === 'users') {
        foreach ($data['data']['rows'] as $row) {
            $before = $row['before'];
            $after = $row['after'];
            
            // 记录审计日志
            db('audit_logs')->insert([
                'table_name' => $table,
                'record_id' => $after['id'],
                'action' => 'update',
                'before_data' => json_encode($before),
                'after_data' => json_encode($after),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
});
```

## 6. 错误处理

### 监听处理失败事件

```php
<?php

Event::listen('binlog.queue.failed', function ($data) {
    // 记录失败的事件
    Log::error('Binlog事件处理失败', $data);
    
    // 发送告警通知
    // ...
});
```

### 自定义错误处理

```php
<?php

Event::listen('binlog.insert', function ($database, $table, $data) {
    try {
        // 处理业务逻辑
        handleBusinessLogic($data);
    } catch (Exception $e) {
        // 记录错误
        Log::error('处理Binlog事件失败', [
            'database' => $database,
            'table' => $table,
            'error' => $e->getMessage(),
            'data' => $data,
        ]);
        
        // 可以选择重新抛出异常或继续处理
        // throw $e;
    }
});
```
