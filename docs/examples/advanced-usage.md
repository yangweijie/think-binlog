# 高级使用示例

## 1. 自定义事件订阅器

### 创建订阅器

```php
<?php

namespace app\listener;

use yangweijie\ThinkBinlog\Contract\BinlogSubscriberInterface;
use yangweijie\ThinkBinlog\Event\BinlogEvent;
use think\facade\Log;
use think\facade\Cache;

class UserBinlogSubscriber implements BinlogSubscriberInterface
{
    /**
     * 处理Binlog事件
     */
    public function handle(BinlogEvent $event): void
    {
        $eventType = $event->getType();
        $table = $event->getTable();
        
        // 只处理用户相关的表
        if (!in_array($table, ['users', 'user_profiles', 'user_settings'])) {
            return;
        }
        
        switch ($eventType) {
            case 'insert':
                $this->handleUserInsert($event);
                break;
            case 'update':
                $this->handleUserUpdate($event);
                break;
            case 'delete':
                $this->handleUserDelete($event);
                break;
        }
    }
    
    /**
     * 处理用户插入
     */
    private function handleUserInsert(BinlogEvent $event): void
    {
        $rows = $event->getChangedRows();
        
        foreach ($rows as $row) {
            $userId = $row['id'];
            
            // 发送欢迎邮件
            $this->sendWelcomeEmail($userId, $row['email']);
            
            // 初始化用户缓存
            $this->initUserCache($userId, $row);
            
            // 记录用户注册统计
            $this->updateRegistrationStats();
            
            Log::info('新用户注册', ['user_id' => $userId, 'email' => $row['email']]);
        }
    }
    
    /**
     * 处理用户更新
     */
    private function handleUserUpdate(BinlogEvent $event): void
    {
        $rows = $event->getChangedRows();
        
        foreach ($rows as $row) {
            $before = $row['before'];
            $after = $row['after'];
            $userId = $after['id'];
            
            // 检查邮箱是否变更
            if ($before['email'] !== $after['email']) {
                $this->handleEmailChange($userId, $before['email'], $after['email']);
            }
            
            // 检查状态是否变更
            if ($before['status'] !== $after['status']) {
                $this->handleStatusChange($userId, $before['status'], $after['status']);
            }
            
            // 更新用户缓存
            $this->updateUserCache($userId, $after);
            
            Log::info('用户信息更新', [
                'user_id' => $userId,
                'changes' => $this->getChangedFields($before, $after)
            ]);
        }
    }
    
    /**
     * 处理用户删除
     */
    private function handleUserDelete(BinlogEvent $event): void
    {
        $rows = $event->getChangedRows();
        
        foreach ($rows as $row) {
            $userId = $row['id'];
            
            // 清理用户缓存
            $this->clearUserCache($userId);
            
            // 清理用户相关数据
            $this->cleanupUserData($userId);
            
            // 记录用户注销统计
            $this->updateUnregistrationStats();
            
            Log::info('用户账户删除', ['user_id' => $userId]);
        }
    }
    
    /**
     * 获取订阅的数据库列表
     */
    public function getDatabases(): array
    {
        return ['main_db']; // 只监听主数据库
    }
    
    /**
     * 获取订阅的表列表
     */
    public function getTables(): array
    {
        return ['users', 'user_profiles', 'user_settings'];
    }
    
    /**
     * 获取订阅的事件类型列表
     */
    public function getEventTypes(): array
    {
        return ['insert', 'update', 'delete'];
    }
    
    // 私有方法实现...
    private function sendWelcomeEmail(int $userId, string $email): void
    {
        // 发送欢迎邮件的实现
    }
    
    private function initUserCache(int $userId, array $userData): void
    {
        Cache::set("user:{$userId}", $userData, 3600);
    }
    
    private function updateUserCache(int $userId, array $userData): void
    {
        Cache::set("user:{$userId}", $userData, 3600);
    }
    
    private function clearUserCache(int $userId): void
    {
        Cache::delete("user:{$userId}");
    }
    
    private function getChangedFields(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $field => $value) {
            if (isset($before[$field]) && $before[$field] !== $value) {
                $changes[$field] = [
                    'from' => $before[$field],
                    'to' => $value
                ];
            }
        }
        return $changes;
    }
    
    private function handleEmailChange(int $userId, string $oldEmail, string $newEmail): void
    {
        // 处理邮箱变更逻辑
    }
    
    private function handleStatusChange(int $userId, string $oldStatus, string $newStatus): void
    {
        // 处理状态变更逻辑
    }
    
    private function cleanupUserData(int $userId): void
    {
        // 清理用户相关数据
    }
    
    private function updateRegistrationStats(): void
    {
        // 更新注册统计
    }
    
    private function updateUnregistrationStats(): void
    {
        // 更新注销统计
    }
}
```

### 注册订阅器

```php
// config/binlog.php
return [
    // ... 其他配置
    'subscribers' => [
        'app\\listener\\UserBinlogSubscriber',
        'app\\listener\\OrderBinlogSubscriber',
        'app\\listener\\ProductBinlogSubscriber',
    ],
];
```

## 2. 复杂的队列处理

### 自定义队列任务

```php
<?php

namespace app\job;

use think\queue\Job;
use think\facade\Log;
use think\facade\Db;

class CustomBinlogJob
{
    /**
     * 处理特定的Binlog事件
     */
    public function fire(Job $job, array $data): void
    {
        try {
            $eventInfo = $data['event_info'];
            $eventData = $data['data'];
            
            // 根据表名分发到不同的处理器
            $processor = $this->getProcessor($eventInfo['table']);
            if ($processor) {
                $processor->process($eventInfo, $eventData);
            }
            
            $job->delete();
            
        } catch (\Exception $e) {
            Log::error('自定义Binlog任务处理失败', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            if ($job->attempts() < 3) {
                $job->release(60);
            } else {
                $job->delete();
                $this->handleFailedJob($data);
            }
        }
    }
    
    /**
     * 获取处理器
     */
    private function getProcessor(string $table): ?object
    {
        $processors = [
            'users' => new UserProcessor(),
            'orders' => new OrderProcessor(),
            'products' => new ProductProcessor(),
        ];
        
        return $processors[$table] ?? null;
    }
    
    /**
     * 处理失败的任务
     */
    private function handleFailedJob(array $data): void
    {
        // 将失败的任务存储到数据库
        Db::table('failed_binlog_jobs')->insert([
            'data' => json_encode($data),
            'failed_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
```

### 配置自定义队列任务

```php
// config/binlog.php
return [
    // ... 其他配置
    'queue' => [
        'enabled' => true,
        'connection' => 'redis',
        'queue_name' => 'binlog',
        'job_class' => 'app\\job\\CustomBinlogJob', // 使用自定义任务类
    ],
];
```

## 3. 高可用部署

### 多实例部署

```php
<?php

// 实例1配置 - 处理用户相关表
return [
    'mysql' => [
        'host' => '127.0.0.1',
        'user' => 'binlog_user',
        'password' => 'password',
        'slave_id' => 1001, // 不同的slave_id
    ],
    'binlog' => [
        'databases_only' => ['main_db'],
        'tables_only' => ['users', 'user_profiles', 'user_settings'],
    ],
    'daemon' => [
        'pid_file' => '/var/run/binlog_users.pid',
        'log_file' => '/var/log/binlog_users.log',
    ],
];

// 实例2配置 - 处理订单相关表
return [
    'mysql' => [
        'host' => '127.0.0.1',
        'user' => 'binlog_user',
        'password' => 'password',
        'slave_id' => 1002, // 不同的slave_id
    ],
    'binlog' => [
        'databases_only' => ['main_db'],
        'tables_only' => ['orders', 'order_items', 'payments'],
    ],
    'daemon' => [
        'pid_file' => '/var/run/binlog_orders.pid',
        'log_file' => '/var/log/binlog_orders.log',
    ],
];
```

### 启动多个实例

```bash
# 启动用户实例
php think binlog start --daemon --config=config/binlog_users.php

# 启动订单实例
php think binlog start --daemon --config=config/binlog_orders.php

# 查看所有实例状态
ps aux | grep binlog
```

## 4. 监控和告警

### 健康检查

```php
<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use yangweijie\ThinkBinlog\Daemon\BinlogDaemon;

class BinlogHealthCheck extends Command
{
    protected function configure(): void
    {
        $this->setName('binlog:health')
            ->setDescription('检查Binlog服务健康状态');
    }
    
    protected function execute(Input $input, Output $output): int
    {
        $daemon = new BinlogDaemon();
        $status = $daemon->status();
        
        if (!$status['running']) {
            $output->error('Binlog服务未运行');
            $this->sendAlert('Binlog服务停止');
            return 1;
        }
        
        // 检查内存使用
        $memoryUsage = $status['memory']['rss'] ?? 0;
        if ($memoryUsage > 512 * 1024) { // 512MB
            $output->warning('内存使用过高: ' . round($memoryUsage / 1024, 2) . 'MB');
            $this->sendAlert('Binlog服务内存使用过高');
        }
        
        // 检查运行时间
        $uptime = $status['uptime'] ?? 0;
        if ($uptime > 24 * 3600) { // 24小时
            $output->info('服务已运行超过24小时，建议重启');
        }
        
        $output->success('Binlog服务运行正常');
        return 0;
    }
    
    private function sendAlert(string $message): void
    {
        // 发送告警通知（邮件、短信、钉钉等）
        Log::error($message);
    }
}
```

### 性能监控

```php
<?php

namespace app\listener;

use yangweijie\ThinkBinlog\Contract\BinlogSubscriberInterface;
use yangweijie\ThinkBinlog\Event\BinlogEvent;
use think\facade\Cache;

class PerformanceMonitorSubscriber implements BinlogSubscriberInterface
{
    public function handle(BinlogEvent $event): void
    {
        $key = 'binlog_stats:' . date('Y-m-d:H');
        
        // 统计事件数量
        Cache::inc($key . ':total');
        Cache::inc($key . ':' . $event->getType());
        Cache::inc($key . ':' . $event->getDatabase() . '.' . $event->getTable());
        
        // 设置过期时间（保留7天）
        Cache::expire($key . ':total', 7 * 24 * 3600);
    }
    
    public function getDatabases(): array
    {
        return [];
    }
    
    public function getTables(): array
    {
        return [];
    }
    
    public function getEventTypes(): array
    {
        return ['insert', 'update', 'delete'];
    }
}
```

## 5. 故障恢复

### 断点续传

```php
<?php

// 保存binlog位置
Event::listen('binlog.position.update', function ($position) {
    Cache::set('binlog_position', $position, 0); // 永不过期
});

// 启动时从上次位置继续
$lastPosition = Cache::get('binlog_position');
if ($lastPosition) {
    $config['binlog']['bin_log_file_name'] = $lastPosition['file'];
    $config['binlog']['bin_log_position'] = $lastPosition['position'];
}
```

### 数据一致性检查

```php
<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class BinlogConsistencyCheck extends Command
{
    protected function configure(): void
    {
        $this->setName('binlog:check')
            ->setDescription('检查数据一致性');
    }
    
    protected function execute(Input $input, Output $output): int
    {
        // 检查缓存与数据库的一致性
        $users = Db::table('users')->select();
        $inconsistencies = 0;
        
        foreach ($users as $user) {
            $cached = Cache::get("user:{$user['id']}");
            if ($cached && $cached !== $user) {
                $output->warning("用户 {$user['id']} 缓存不一致");
                $inconsistencies++;
                
                // 修复缓存
                Cache::set("user:{$user['id']}", $user, 3600);
            }
        }
        
        if ($inconsistencies > 0) {
            $output->error("发现 {$inconsistencies} 个不一致的记录");
            return 1;
        }
        
        $output->success('数据一致性检查通过');
        return 0;
    }
}
```
