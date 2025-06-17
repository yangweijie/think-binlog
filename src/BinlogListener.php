<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog;

use MySQLReplication\Config\ConfigBuilder;
use MySQLReplication\MySQLReplicationFactory;
use MySQLReplication\Event\DTO\EventDTO;
use MySQLReplication\Event\EventSubscribers;
use think\facade\Log;
use think\facade\Queue;
use yangweijie\ThinkBinlog\Event\BinlogEvent;
use yangweijie\ThinkBinlog\Exception\BinlogException;

/**
 * MySQL Binlog监听器
 */
class BinlogListener
{
    /**
     * 配置信息
     */
    protected array $config;

    /**
     * MySQL复制工厂
     */
    protected ?MySQLReplicationFactory $factory = null;

    /**
     * 事件订阅器
     */
    protected array $subscribers = [];

    /**
     * 是否正在运行
     */
    protected bool $running = false;

    /**
     * 构造函数
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge(config('binlog', []), $config);
        $this->initSubscribers();
    }

    /**
     * 初始化事件订阅器
     */
    protected function initSubscribers(): void
    {
        $subscribers = $this->config['subscribers'] ?? [];
        foreach ($subscribers as $subscriber) {
            if (class_exists($subscriber)) {
                $this->subscribers[] = new $subscriber();
            }
        }
    }

    /**
     * 启动监听
     */
    public function start(): void
    {
        try {
            $this->running = true;
            $this->log('info', 'Binlog监听器启动');

            // 创建配置
            $configBuilder = new ConfigBuilder();
            $this->buildConfig($configBuilder);
            
            // 创建工厂实例
            $this->factory = new MySQLReplicationFactory($configBuilder->build());

            // 注册事件监听器
            $this->registerEventListeners();

            // 开始监听
            $this->factory->run();

        } catch (\Exception $e) {
            $this->log('error', 'Binlog监听器启动失败: ' . $e->getMessage());
            throw new BinlogException('Binlog监听器启动失败: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 停止监听
     */
    public function stop(): void
    {
        $this->running = false;
        $this->log('info', 'Binlog监听器停止');
    }

    /**
     * 检查是否正在运行
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * 构建配置
     */
    protected function buildConfig(ConfigBuilder $configBuilder): void
    {
        $mysql = $this->config['mysql'] ?? [];
        $binlog = $this->config['binlog'] ?? [];

        $configBuilder
            ->withUser($mysql['user'] ?? 'root')
            ->withHost($mysql['host'] ?? '127.0.0.1')
            ->withPassword($mysql['password'] ?? '')
            ->withPort($mysql['port'] ?? 3306)
            ->withCharset($mysql['charset'] ?? 'utf8mb4')
            ->withSlaveId($mysql['slave_id'] ?? 666);

        // 设置监听的数据库
        if (!empty($binlog['databases_only'])) {
            $configBuilder->withDatabasesOnly($binlog['databases_only']);
        }

        // 设置监听的表
        if (!empty($binlog['tables_only'])) {
            $configBuilder->withTablesOnly($binlog['tables_only']);
        }

        // 设置监听的事件
        if (!empty($binlog['events_only'])) {
            $configBuilder->withEventsOnly($binlog['events_only']);
        }

        // 设置忽略的事件
        if (!empty($binlog['events_ignore'])) {
            $configBuilder->withEventsIgnore($binlog['events_ignore']);
        }

        // 设置binlog位置
        if (!empty($binlog['bin_log_file_name'])) {
            $configBuilder->withBinLogFileName($binlog['bin_log_file_name']);
        }

        if (!empty($binlog['bin_log_position'])) {
            $configBuilder->withBinLogPosition($binlog['bin_log_position']);
        }

        // 设置GTID
        if (!empty($binlog['gtid'])) {
            $configBuilder->withGtid($binlog['gtid']);
        }

        if (!empty($binlog['maria_db_gtid'])) {
            $configBuilder->withMariaDbGtid($binlog['maria_db_gtid']);
        }

        // 设置心跳间隔
        if (!empty($binlog['heartbeat_period'])) {
            $configBuilder->withHeartbeatPeriod($binlog['heartbeat_period']);
        }
    }

    /**
     * 注册事件监听器
     */
    protected function registerEventListeners(): void
    {
        $eventSubscribers = new EventSubscribers();
        
        // 注册所有事件的通用处理器
        $eventSubscribers->onEvent(function (EventDTO $event) {
            $this->handleEvent($event);
        });

        $this->factory->registerSubscriber($eventSubscribers);
    }

    /**
     * 处理事件
     */
    protected function handleEvent(EventDTO $event): void
    {
        try {
            $binlogEvent = new BinlogEvent($event);
            
            // 记录日志
            $this->log('debug', sprintf(
                '接收到Binlog事件: %s.%s [%s]',
                $binlogEvent->getDatabase(),
                $binlogEvent->getTable(),
                $binlogEvent->getType()
            ));

            // 转发到队列
            $this->forwardToQueue($binlogEvent);

            // 通知订阅器
            $this->notifySubscribers($binlogEvent);

        } catch (\Exception $e) {
            $this->log('error', '处理Binlog事件失败: ' . $e->getMessage());
        }
    }

    /**
     * 转发事件到队列
     */
    protected function forwardToQueue(BinlogEvent $event): void
    {
        $queueConfig = $this->config['queue'] ?? [];
        
        if (!($queueConfig['enabled'] ?? false)) {
            return;
        }

        try {
            $jobClass = $queueConfig['job_class'] ?? 'yangweijie\\ThinkBinlog\\Job\\BinlogJob';
            $queueName = $queueConfig['queue_name'] ?? 'binlog';
            $connection = $queueConfig['connection'] ?? 'default';

            Queue::connection($connection)->push($jobClass, $event->toArray(), $queueName);
            
            $this->log('debug', '事件已转发到队列: ' . $queueName);
        } catch (\Exception $e) {
            $this->log('error', '转发事件到队列失败: ' . $e->getMessage());
        }
    }

    /**
     * 通知订阅器
     */
    protected function notifySubscribers(BinlogEvent $event): void
    {
        foreach ($this->subscribers as $subscriber) {
            try {
                if (method_exists($subscriber, 'handle')) {
                    $subscriber->handle($event);
                }
            } catch (\Exception $e) {
                $this->log('error', '订阅器处理事件失败: ' . $e->getMessage());
            }
        }
    }

    /**
     * 记录日志
     */
    protected function log(string $level, string $message): void
    {
        $logConfig = $this->config['log'] ?? [];
        
        if (!($logConfig['enabled'] ?? true)) {
            return;
        }

        $channel = $logConfig['channel'] ?? 'binlog';
        $logLevel = $logConfig['level'] ?? 'info';

        // 检查日志级别
        $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        if (($levels[$level] ?? 0) < ($levels[$logLevel] ?? 1)) {
            return;
        }

        Log::channel($channel)->$level($message);
    }
}
