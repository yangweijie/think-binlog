<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use think\App;
use think\Config;
use think\Container;

/**
 * 测试基类
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * ThinkPHP应用实例
     */
    protected App $app;

    /**
     * 设置测试环境
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->createApplication();
    }

    /**
     * 清理测试环境
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        Container::getInstance()->flush();
    }

    /**
     * 创建应用实例
     */
    protected function createApplication(): void
    {
        $this->app = new App();
        Container::setInstance($this->app);
        
        // 设置测试配置
        $this->app->bind('config', function () {
            $config = new Config();
            $config->set([
                'binlog' => $this->getBinlogConfig(),
                'queue' => $this->getQueueConfig(),
                'log' => $this->getLogConfig(),
            ]);
            return $config;
        });
    }

    /**
     * 获取Binlog测试配置
     */
    protected function getBinlogConfig(): array
    {
        return [
            'mysql' => [
                'host' => '127.0.0.1',
                'port' => 3306,
                'user' => 'test_user',
                'password' => 'test_password',
                'charset' => 'utf8mb4',
                'slave_id' => 999,
            ],
            'binlog' => [
                'databases_only' => ['test_db'],
                'tables_only' => ['test_table'],
                'events_only' => ['write', 'update', 'delete'],
                'events_ignore' => [],
                'bin_log_file_name' => '',
                'bin_log_position' => 0,
                'gtid' => '',
                'maria_db_gtid' => '',
                'heartbeat_period' => 30,
            ],
            'queue' => [
                'enabled' => true,
                'connection' => 'test',
                'queue_name' => 'test_binlog',
                'job_class' => 'yangweijie\\ThinkBinlog\\Job\\BinlogJob',
            ],
            'daemon' => [
                'enabled' => false,
                'pid_file' => '/tmp/test_binlog.pid',
                'log_file' => '/tmp/test_binlog.log',
                'memory_limit' => 64,
                'restart_interval' => 1800,
            ],
            'subscribers' => [],
            'log' => [
                'enabled' => true,
                'level' => 'debug',
                'channel' => 'test',
            ],
        ];
    }

    /**
     * 获取队列测试配置
     */
    protected function getQueueConfig(): array
    {
        return [
            'default' => 'test',
            'connections' => [
                'test' => [
                    'type' => 'sync',
                ],
            ],
        ];
    }

    /**
     * 获取日志测试配置
     */
    protected function getLogConfig(): array
    {
        return [
            'default' => 'test',
            'channels' => [
                'test' => [
                    'type' => 'test',
                ],
            ],
        ];
    }

    /**
     * 模拟配置函数
     */
    protected function mockConfig(string $key, $default = null)
    {
        return $this->app->config->get($key, $default);
    }
}
