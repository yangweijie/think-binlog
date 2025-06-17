<?php

declare(strict_types=1);

/**
 * Binlog性能测试
 * 
 * 运行方式：
 * php tests/Performance/BinlogPerformanceTest.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use yangweijie\ThinkBinlog\Event\BinlogEvent;
use yangweijie\ThinkBinlog\Job\BinlogJob;
use yangweijie\ThinkBinlog\Subscriber\ExampleBinlogSubscriber;

class BinlogPerformanceTest
{
    private int $iterations = 10000;
    private array $results = [];

    public function run(): void
    {
        echo "开始Binlog性能测试...\n";
        echo "测试迭代次数: {$this->iterations}\n\n";

        $this->testEventCreation();
        $this->testEventSerialization();
        $this->testJobProcessing();
        $this->testSubscriberHandling();
        $this->testMemoryUsage();

        $this->printResults();
    }

    /**
     * 测试事件创建性能
     */
    private function testEventCreation(): void
    {
        echo "测试事件创建性能...\n";
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        for ($i = 0; $i < $this->iterations; $i++) {
            $mockEvent = $this->createMockWriteEvent();
            $binlogEvent = new BinlogEvent($mockEvent);
            unset($binlogEvent);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $this->results['event_creation'] = [
            'time' => $endTime - $startTime,
            'memory' => $endMemory - $startMemory,
            'ops_per_second' => $this->iterations / ($endTime - $startTime),
        ];
    }

    /**
     * 测试事件序列化性能
     */
    private function testEventSerialization(): void
    {
        echo "测试事件序列化性能...\n";
        
        $mockEvent = $this->createMockWriteEvent();
        $binlogEvent = new BinlogEvent($mockEvent);

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        for ($i = 0; $i < $this->iterations; $i++) {
            $json = $binlogEvent->toJson();
            $array = $binlogEvent->toArray();
            unset($json, $array);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $this->results['serialization'] = [
            'time' => $endTime - $startTime,
            'memory' => $endMemory - $startMemory,
            'ops_per_second' => $this->iterations / ($endTime - $startTime),
        ];
    }

    /**
     * 测试队列任务处理性能
     */
    private function testJobProcessing(): void
    {
        echo "测试队列任务处理性能...\n";
        
        $job = new BinlogJob();
        $mockJob = $this->createMockJob();
        $data = $this->createTestEventData();

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        for ($i = 0; $i < $this->iterations; $i++) {
            try {
                $job->fire($mockJob, $data);
            } catch (Exception $e) {
                // 忽略模拟环境中的异常
            }
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $this->results['job_processing'] = [
            'time' => $endTime - $startTime,
            'memory' => $endMemory - $startMemory,
            'ops_per_second' => $this->iterations / ($endTime - $startTime),
        ];
    }

    /**
     * 测试订阅器处理性能
     */
    private function testSubscriberHandling(): void
    {
        echo "测试订阅器处理性能...\n";
        
        $subscriber = new ExampleBinlogSubscriber();
        $mockEvent = $this->createMockWriteEvent();
        $binlogEvent = new BinlogEvent($mockEvent);

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        for ($i = 0; $i < $this->iterations; $i++) {
            $subscriber->handle($binlogEvent);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $this->results['subscriber_handling'] = [
            'time' => $endTime - $startTime,
            'memory' => $endMemory - $startMemory,
            'ops_per_second' => $this->iterations / ($endTime - $startTime),
        ];
    }

    /**
     * 测试内存使用情况
     */
    private function testMemoryUsage(): void
    {
        echo "测试内存使用情况...\n";
        
        $events = [];
        $startMemory = memory_get_usage();

        // 创建大量事件对象
        for ($i = 0; $i < 1000; $i++) {
            $mockEvent = $this->createMockWriteEvent();
            $events[] = new BinlogEvent($mockEvent);
        }

        $peakMemory = memory_get_peak_usage();
        $currentMemory = memory_get_usage();

        $this->results['memory_usage'] = [
            'start_memory' => $startMemory,
            'current_memory' => $currentMemory,
            'peak_memory' => $peakMemory,
            'memory_per_event' => ($currentMemory - $startMemory) / 1000,
        ];

        // 清理内存
        unset($events);
        gc_collect_cycles();
    }

    /**
     * 创建模拟的WriteRowsDTO事件
     */
    private function createMockWriteEvent()
    {
        return new class {
            public function getEventInfo() {
                return new class {
                    public function getDateTime() {
                        return new DateTime();
                    }
                    public function getPos() {
                        return 1234;
                    }
                    public function getSize() {
                        return 56;
                    }
                };
            }
            
            public function getTableMap() {
                return new class {
                    public function getDatabase() {
                        return 'test_db';
                    }
                    public function getTable() {
                        return 'users';
                    }
                    public function getColumnsArray() {
                        return [
                            ['name' => 'id', 'type' => 'int'],
                            ['name' => 'name', 'type' => 'varchar'],
                            ['name' => 'email', 'type' => 'varchar'],
                        ];
                    }
                };
            }
            
            public function getValues() {
                return [
                    ['id' => 1, 'name' => 'John', 'email' => 'john@example.com']
                ];
            }
        };
    }

    /**
     * 创建模拟的Job对象
     */
    private function createMockJob()
    {
        return new class {
            public function delete() {}
            public function attempts() { return 1; }
            public function release($delay) {}
        };
    }

    /**
     * 创建测试事件数据
     */
    private function createTestEventData(): array
    {
        return [
            'event_info' => [
                'type' => 'insert',
                'database' => 'test_db',
                'table' => 'users',
                'datetime' => '2023-01-01 12:00:00',
                'timestamp' => 1672574400,
                'log_position' => 1234,
                'event_size' => 56,
            ],
            'data' => [
                'rows' => [
                    ['id' => 1, 'name' => 'John', 'email' => 'john@example.com']
                ],
                'columns' => [
                    ['name' => 'id', 'type' => 'int'],
                    ['name' => 'name', 'type' => 'varchar'],
                    ['name' => 'email', 'type' => 'varchar'],
                ],
            ],
        ];
    }

    /**
     * 打印测试结果
     */
    private function printResults(): void
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "性能测试结果\n";
        echo str_repeat("=", 60) . "\n";

        foreach ($this->results as $testName => $result) {
            echo "\n{$testName}:\n";
            echo str_repeat("-", 40) . "\n";
            
            if (isset($result['time'])) {
                echo sprintf("执行时间: %.4f 秒\n", $result['time']);
                echo sprintf("每秒操作数: %.0f ops/s\n", $result['ops_per_second']);
                echo sprintf("内存使用: %s\n", $this->formatBytes($result['memory']));
            }
            
            if (isset($result['memory_per_event'])) {
                echo sprintf("起始内存: %s\n", $this->formatBytes($result['start_memory']));
                echo sprintf("当前内存: %s\n", $this->formatBytes($result['current_memory']));
                echo sprintf("峰值内存: %s\n", $this->formatBytes($result['peak_memory']));
                echo sprintf("每个事件内存: %s\n", $this->formatBytes($result['memory_per_event']));
            }
        }

        echo "\n" . str_repeat("=", 60) . "\n";
    }

    /**
     * 格式化字节数
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// 运行性能测试
if (php_sapi_name() === 'cli') {
    $test = new BinlogPerformanceTest();
    $test->run();
}
