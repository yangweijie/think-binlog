# ThinkPHP MySQL Binlog监听扩展

[![Latest Stable Version](https://poser.pugx.org/yangweijie/think-binlog/v/stable)](https://packagist.org/packages/yangweijie/think-binlog)
[![Total Downloads](https://poser.pugx.org/yangweijie/think-binlog/downloads)](https://packagist.org/packages/yangweijie/think-binlog)
[![License](https://poser.pugx.org/yangweijie/think-binlog/license)](https://packagist.org/packages/yangweijie/think-binlog)

基于 [krowinski/php-mysql-replication](https://github.com/krowinski/php-mysql-replication) 开发的ThinkPHP MySQL Binlog监听扩展，支持后台运行、队列转发和事件订阅。

## 功能特性

- 🚀 **实时监听** - 监听MySQL binlog事件（INSERT、UPDATE、DELETE）
- 🔄 **队列转发** - 支持将事件转发到think-queue队列系统
- 📡 **事件订阅** - 灵活的事件订阅机制
- 🛡️ **后台运行** - 支持守护进程模式运行
- 📊 **状态监控** - 提供进程状态查看和管理
- 🎯 **精确过滤** - 支持按数据库、表、事件类型过滤
- 📝 **详细日志** - 完整的日志记录和错误处理

## 环境要求

- PHP >= 8.0
- ThinkPHP >= 8.0
- MySQL >= 5.5 (支持binlog)
- 扩展: pcntl, posix (守护进程模式需要)

## 安装

```bash
composer require yangweijie/think-binlog
```

## MySQL配置

在MySQL配置文件中启用binlog：

```ini
[mysqld]
server-id        = 1
log_bin          = /var/log/mysql/mysql-bin.log
expire_logs_days = 10
max_binlog_size  = 100M
binlog-format    = row  # 重要：必须设置为row格式
```

创建复制用户：

```sql
GRANT REPLICATION SLAVE, REPLICATION CLIENT ON *.* TO 'binlog_user'@'%';
GRANT SELECT ON `your_database`.* TO 'binlog_user'@'%';
FLUSH PRIVILEGES;
```

## 配置

发布配置文件：

```bash
php think service:discover
```

编辑 `config/binlog.php`：

```php
<?php
return [
    // MySQL连接配置
    'mysql' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'user' => 'binlog_user',
        'password' => 'password',
        'charset' => 'utf8mb4',
        'slave_id' => 666,
    ],

    // Binlog配置
    'binlog' => [
        'databases_only' => [], // 监听的数据库
        'tables_only' => [],    // 监听的表
        'events_only' => ['write', 'update', 'delete'],
    ],

    // 队列配置
    'queue' => [
        'enabled' => true,
        'connection' => 'default',
        'queue_name' => 'binlog',
    ],

    // 事件订阅器
    'subscribers' => [
        // 'App\\Listener\\UserBinlogListener',
    ],
];
```

## 使用方法

### 命令行工具

```bash
# 前台运行（调试模式）
php think binlog listen

# 启动守护进程
php think binlog start --daemon

# 停止守护进程
php think binlog stop

# 重启守护进程
php think binlog restart

# 查看状态
php think binlog status
```

### 编程方式

```php
use yangweijie\ThinkBinlog\BinlogListener;

// 创建监听器
$listener = new BinlogListener();

// 启动监听
$listener->start();
```

### 事件订阅

创建事件订阅器：

```php
<?php

namespace app\listener;

use yangweijie\ThinkBinlog\Contract\BinlogSubscriberInterface;
use yangweijie\ThinkBinlog\Event\BinlogEvent;

class UserBinlogListener implements BinlogSubscriberInterface
{
    public function handle(BinlogEvent $event): void
    {
        if ($event->getTable() === 'users') {
            // 处理用户表的变更
            $this->handleUserChange($event);
        }
    }

    public function getDatabases(): array
    {
        return ['your_database'];
    }

    public function getTables(): array
    {
        return ['users', 'orders'];
    }

    public function getEventTypes(): array
    {
        return ['insert', 'update', 'delete'];
    }

    private function handleUserChange(BinlogEvent $event): void
    {
        switch ($event->getType()) {
            case 'insert':
                // 处理用户注册
                break;
            case 'update':
                // 处理用户信息更新
                break;
            case 'delete':
                // 处理用户删除
                break;
        }
    }
}
```

在配置文件中注册订阅器：

```php
'subscribers' => [
    'app\\listener\\UserBinlogListener',
],
```

### 队列处理

监听队列事件：

```php
// 在事件监听器中
Event::listen('binlog.insert', function ($database, $table, $data) {
    // 处理插入事件
});

Event::listen('binlog.update', function ($database, $table, $data) {
    // 处理更新事件
});

Event::listen('binlog.delete', function ($database, $table, $data) {
    // 处理删除事件
});

// 监听特定表的事件
Event::listen('binlog.your_database.users', function ($eventType, $data) {
    // 处理users表的所有事件
});
```

## 事件数据结构

```php
[
    'event_info' => [
        'type' => 'insert',           // 事件类型
        'database' => 'test_db',      // 数据库名
        'table' => 'users',           // 表名
        'datetime' => '2023-01-01 12:00:00',
        'timestamp' => 1672574400,
        'log_position' => 1234,
        'event_size' => 56,
    ],
    'data' => [
        'rows' => [                   // 变更的行数据
            [
                'id' => 1,
                'name' => 'John',
                'email' => 'john@example.com',
            ]
        ],
        'columns' => [                // 表结构信息
            // ...
        ],
    ],
]
```

## 守护进程管理

### 系统服务配置

创建systemd服务文件 `/etc/systemd/system/think-binlog.service`：

```ini
[Unit]
Description=ThinkPHP Binlog Listener
After=mysql.service

[Service]
Type=forking
User=www-data
Group=www-data
WorkingDirectory=/path/to/your/project
ExecStart=/usr/bin/php think binlog start --daemon
ExecStop=/usr/bin/php think binlog stop
ExecReload=/usr/bin/php think binlog restart
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

启用服务：

```bash
sudo systemctl enable think-binlog
sudo systemctl start think-binlog
sudo systemctl status think-binlog
```

## 故障排除

### 常见问题

1. **连接失败**
   - 检查MySQL用户权限
   - 确认binlog已启用
   - 验证网络连接

2. **内存使用过高**
   - 调整 `daemon.memory_limit` 配置
   - 启用自动重启机制

3. **事件丢失**
   - 检查binlog位置设置
   - 确认事件过滤配置

### 调试模式

```bash
# 启用调试日志
php think binlog listen

# 查看日志
tail -f runtime/log/binlog.log
```

## 性能优化

- 使用队列异步处理事件
- 合理设置事件过滤条件
- 定期清理日志文件
- 监控内存使用情况

## 许可证

MIT License

## 贡献

欢迎提交Issue和Pull Request！

## 相关项目

- [krowinski/php-mysql-replication](https://github.com/krowinski/php-mysql-replication)
- [topthink/think-queue](https://github.com/top-think/think-queue)
