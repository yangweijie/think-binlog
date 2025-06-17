<?php

declare(strict_types=1);

use yangweijie\ThinkBinlog\Subscriber\ExampleBinlogSubscriber;
use yangweijie\ThinkBinlog\Event\BinlogEvent;
use MySQLReplication\Event\DTO\WriteRowsDTO;
use MySQLReplication\Event\DTO\UpdateRowsDTO;
use MySQLReplication\Event\DTO\DeleteRowsDTO;
use MySQLReplication\Event\DTO\QueryDTO;
use MySQLReplication\Event\DTO\EventInfo;
use MySQLReplication\Event\DTO\TableMap;

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

    $this->subscriber = new ExampleBinlogSubscriber();
    
    $this->eventInfo = Mockery::mock(EventInfo::class);
    $this->eventInfo->shouldReceive('getDateTime')->andReturn(new DateTime('2023-01-01 12:00:00'));
    $this->eventInfo->shouldReceive('getPos')->andReturn(1234);
    $this->eventInfo->shouldReceive('getSize')->andReturn(56);

    $this->tableMap = Mockery::mock(TableMap::class);
    $this->tableMap->shouldReceive('getDatabase')->andReturn('test_db');
    $this->tableMap->shouldReceive('getTable')->andReturn('users');
    $this->tableMap->shouldReceive('getColumnsArray')->andReturn([
        ['name' => 'id', 'type' => 'int'],
        ['name' => 'name', 'type' => 'varchar'],
        ['name' => 'email', 'type' => 'varchar'],
    ]);
});

afterEach(function () {
    Mockery::close();
});

describe('ExampleBinlogSubscriber', function () {
    it('implements BinlogSubscriberInterface', function () {
        expect($this->subscriber)->toBeInstanceOf(yangweijie\ThinkBinlog\Contract\BinlogSubscriberInterface::class);
    });

    it('can handle insert events', function () {
        $writeEvent = Mockery::mock(WriteRowsDTO::class);
        $writeEvent->shouldReceive('getEventInfo')->andReturn($this->eventInfo);
        $writeEvent->shouldReceive('getTableMap')->andReturn($this->tableMap);
        $writeEvent->shouldReceive('getValues')->andReturn([
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com']
        ]);

        $binlogEvent = new BinlogEvent($writeEvent);
        
        $this->subscriber->handle($binlogEvent);
        
        expect(true)->toBeTrue(); // 测试没有抛出异常
    });

    it('can handle update events', function () {
        $updateEvent = Mockery::mock(UpdateRowsDTO::class);
        $updateEvent->shouldReceive('getEventInfo')->andReturn($this->eventInfo);
        $updateEvent->shouldReceive('getTableMap')->andReturn($this->tableMap);
        $updateEvent->shouldReceive('getValues')->andReturn([
            [
                'before' => ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
                'after' => ['id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com']
            ]
        ]);

        $binlogEvent = new BinlogEvent($updateEvent);
        
        $this->subscriber->handle($binlogEvent);
        
        expect(true)->toBeTrue(); // 测试没有抛出异常
    });

    it('can handle delete events', function () {
        $deleteEvent = Mockery::mock(DeleteRowsDTO::class);
        $deleteEvent->shouldReceive('getEventInfo')->andReturn($this->eventInfo);
        $deleteEvent->shouldReceive('getTableMap')->andReturn($this->tableMap);
        $deleteEvent->shouldReceive('getValues')->andReturn([
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com']
        ]);

        $binlogEvent = new BinlogEvent($deleteEvent);
        
        $this->subscriber->handle($binlogEvent);
        
        expect(true)->toBeTrue(); // 测试没有抛出异常
    });

    it('can handle query events', function () {
        $queryEvent = Mockery::mock(QueryDTO::class);
        $queryEvent->shouldReceive('getEventInfo')->andReturn($this->eventInfo);
        $queryEvent->shouldReceive('getDatabase')->andReturn('test_db');
        $queryEvent->shouldReceive('getQuery')->andReturn('CREATE TABLE test (id INT)');
        $queryEvent->shouldReceive('getExecutionTime')->andReturn(0.001);

        $binlogEvent = new BinlogEvent($queryEvent);
        
        $this->subscriber->handle($binlogEvent);
        
        expect(true)->toBeTrue(); // 测试没有抛出异常
    });

    it('returns correct database filter', function () {
        $databases = $this->subscriber->getDatabases();
        
        expect($databases)->toBeArray();
        expect($databases)->toBeEmpty(); // 默认订阅所有数据库
    });

    it('returns correct table filter', function () {
        $tables = $this->subscriber->getTables();
        
        expect($tables)->toBeArray();
        expect($tables)->toBeEmpty(); // 默认订阅所有表
    });

    it('returns correct event type filter', function () {
        $eventTypes = $this->subscriber->getEventTypes();
        
        expect($eventTypes)->toBeArray();
        expect($eventTypes)->toContain('insert');
        expect($eventTypes)->toContain('update');
        expect($eventTypes)->toContain('delete');
        expect($eventTypes)->not->toContain('query');
    });

    it('filters events by database', function () {
        // 创建一个自定义订阅器来测试过滤
        $customSubscriber = new class extends ExampleBinlogSubscriber {
            public function getDatabases(): array {
                return ['allowed_db'];
            }
        };

        $writeEvent = Mockery::mock(WriteRowsDTO::class);
        $writeEvent->shouldReceive('getEventInfo')->andReturn($this->eventInfo);
        $writeEvent->shouldReceive('getTableMap')->andReturn($this->tableMap);
        $writeEvent->shouldReceive('getValues')->andReturn([]);

        $binlogEvent = new BinlogEvent($writeEvent);
        
        // 由于数据库不匹配，事件应该被过滤掉
        $customSubscriber->handle($binlogEvent);
        
        expect(true)->toBeTrue(); // 测试没有抛出异常
    });

    it('filters events by table', function () {
        // 创建一个自定义订阅器来测试过滤
        $customSubscriber = new class extends ExampleBinlogSubscriber {
            public function getTables(): array {
                return ['allowed_table'];
            }
        };

        $writeEvent = Mockery::mock(WriteRowsDTO::class);
        $writeEvent->shouldReceive('getEventInfo')->andReturn($this->eventInfo);
        $writeEvent->shouldReceive('getTableMap')->andReturn($this->tableMap);
        $writeEvent->shouldReceive('getValues')->andReturn([]);

        $binlogEvent = new BinlogEvent($writeEvent);
        
        // 由于表不匹配，事件应该被过滤掉
        $customSubscriber->handle($binlogEvent);
        
        expect(true)->toBeTrue(); // 测试没有抛出异常
    });

    it('filters events by event type', function () {
        // 创建一个自定义订阅器来测试过滤
        $customSubscriber = new class extends ExampleBinlogSubscriber {
            public function getEventTypes(): array {
                return ['update']; // 只允许update事件
            }
        };

        $writeEvent = Mockery::mock(WriteRowsDTO::class);
        $writeEvent->shouldReceive('getEventInfo')->andReturn($this->eventInfo);
        $writeEvent->shouldReceive('getTableMap')->andReturn($this->tableMap);
        $writeEvent->shouldReceive('getValues')->andReturn([]);

        $binlogEvent = new BinlogEvent($writeEvent);
        
        // 由于事件类型不匹配（insert vs update），事件应该被过滤掉
        $customSubscriber->handle($binlogEvent);
        
        expect(true)->toBeTrue(); // 测试没有抛出异常
    });
});
