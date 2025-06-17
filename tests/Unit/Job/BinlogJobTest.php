<?php

declare(strict_types=1);

use yangweijie\ThinkBinlog\Job\BinlogJob;
use think\queue\Job;

beforeEach(function () {
    // 模拟Log门面
    if (!class_exists('think\facade\Log')) {
        eval('
            namespace think\facade;
            class Log {
                public static function info($message, $context = []) {}
                public static function error($message, $context = []) {}
            }
        ');
    }

    // 模拟Event门面
    if (!class_exists('think\facade\Event')) {
        eval('
            namespace think\facade;
            class Event {
                public static function trigger($event, $params = []) {}
            }
        ');
    }

    $this->job = Mockery::mock(Job::class);
    $this->binlogJob = new BinlogJob();
});

afterEach(function () {
    Mockery::close();
});

describe('BinlogJob', function () {
    it('can process insert event successfully', function () {
        $data = [
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

        $this->job->shouldReceive('delete')->once();

        $this->binlogJob->fire($this->job, $data);

        expect(true)->toBeTrue(); // 测试没有抛出异常
    });

    it('can process update event successfully', function () {
        $data = [
            'event_info' => [
                'type' => 'update',
                'database' => 'test_db',
                'table' => 'users',
                'datetime' => '2023-01-01 12:00:00',
                'timestamp' => 1672574400,
                'log_position' => 1234,
                'event_size' => 56,
            ],
            'data' => [
                'rows' => [
                    [
                        'before' => ['id' => 1, 'name' => 'John'],
                        'after' => ['id' => 1, 'name' => 'Jane']
                    ]
                ],
                'columns' => [
                    ['name' => 'id', 'type' => 'int'],
                    ['name' => 'name', 'type' => 'varchar'],
                ],
            ],
        ];

        $this->job->shouldReceive('delete')->once();

        $this->binlogJob->fire($this->job, $data);

        expect(true)->toBeTrue(); // 测试没有抛出异常
    });

    it('can process delete event successfully', function () {
        $data = [
            'event_info' => [
                'type' => 'delete',
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

        $this->job->shouldReceive('delete')->once();

        $this->binlogJob->fire($this->job, $data);

        expect(true)->toBeTrue(); // 测试没有抛出异常
    });

    it('can process query event successfully', function () {
        $data = [
            'event_info' => [
                'type' => 'query',
                'database' => 'test_db',
                'table' => '',
                'datetime' => '2023-01-01 12:00:00',
                'timestamp' => 1672574400,
                'log_position' => 1234,
                'event_size' => 56,
            ],
            'data' => [
                'query' => 'CREATE TABLE test (id INT)',
                'execution_time' => 0.001,
            ],
        ];

        $this->job->shouldReceive('delete')->once();

        $this->binlogJob->fire($this->job, $data);

        expect(true)->toBeTrue(); // 测试没有抛出异常
    });

    it('can handle unknown event type', function () {
        $data = [
            'event_info' => [
                'type' => 'unknown',
                'database' => 'test_db',
                'table' => 'users',
                'datetime' => '2023-01-01 12:00:00',
                'timestamp' => 1672574400,
                'log_position' => 1234,
                'event_size' => 56,
            ],
            'data' => [],
        ];

        $this->job->shouldReceive('delete')->once();

        $this->binlogJob->fire($this->job, $data);

        expect(true)->toBeTrue(); // 测试没有抛出异常
    });

    it('can retry job on failure', function () {
        $data = [
            'event_info' => [
                'type' => 'insert',
                'database' => 'test_db',
                'table' => 'users',
            ],
            'data' => [],
        ];

        $this->job->shouldReceive('attempts')->andReturn(1);
        $this->job->shouldReceive('release')->with(60)->once();

        // 模拟异常
        $this->job->shouldReceive('delete')->andThrow(new Exception('Test exception'));

        $this->binlogJob->fire($this->job, $data);

        expect(true)->toBeTrue(); // 测试没有抛出异常
    });

    it('can delete job after max retries', function () {
        $data = [
            'event_info' => [
                'type' => 'insert',
                'database' => 'test_db',
                'table' => 'users',
            ],
            'data' => [],
        ];

        $this->job->shouldReceive('attempts')->andReturn(3);
        $this->job->shouldReceive('delete')->twice(); // 一次在异常中，一次在重试逻辑中

        // 模拟异常
        $this->job->shouldReceive('delete')->andThrow(new Exception('Test exception'));

        $this->binlogJob->fire($this->job, $data);

        expect(true)->toBeTrue(); // 测试没有抛出异常
    });

    it('can handle failed job', function () {
        $data = [
            'event_info' => [
                'type' => 'insert',
                'database' => 'test_db',
                'table' => 'users',
            ],
            'data' => [],
        ];

        $this->binlogJob->failed($data);

        expect(true)->toBeTrue(); // 测试没有抛出异常
    });
});
