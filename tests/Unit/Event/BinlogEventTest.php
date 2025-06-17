<?php

declare(strict_types=1);

use yangweijie\ThinkBinlog\Event\BinlogEvent;
use MySQLReplication\Event\DTO\WriteRowsDTO;
use MySQLReplication\Event\DTO\UpdateRowsDTO;
use MySQLReplication\Event\DTO\DeleteRowsDTO;
use MySQLReplication\Event\DTO\QueryDTO;
use MySQLReplication\Event\DTO\EventInfo;
use MySQLReplication\Event\DTO\TableMap;

beforeEach(function () {
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

describe('BinlogEvent', function () {
    it('can handle WriteRowsDTO (INSERT) events', function () {
        $writeEvent = Mockery::mock(WriteRowsDTO::class);
        $writeEvent->shouldReceive('getEventInfo')->andReturn($this->eventInfo);
        $writeEvent->shouldReceive('getTableMap')->andReturn($this->tableMap);
        $writeEvent->shouldReceive('getValues')->andReturn([
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com']
        ]);

        $binlogEvent = new BinlogEvent($writeEvent);

        expect($binlogEvent->getType())->toBe('insert');
        expect($binlogEvent->getDatabase())->toBe('test_db');
        expect($binlogEvent->getTable())->toBe('users');
        expect($binlogEvent->isDataChangeEvent())->toBeTrue();
        expect($binlogEvent->isQueryEvent())->toBeFalse();
        
        $changedRows = $binlogEvent->getChangedRows();
        expect($changedRows)->toHaveCount(1);
        expect($changedRows[0]['name'])->toBe('John');
    });

    it('can handle UpdateRowsDTO (UPDATE) events', function () {
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

        expect($binlogEvent->getType())->toBe('update');
        expect($binlogEvent->getDatabase())->toBe('test_db');
        expect($binlogEvent->getTable())->toBe('users');
        expect($binlogEvent->isDataChangeEvent())->toBeTrue();
        
        $changedRows = $binlogEvent->getChangedRows();
        expect($changedRows)->toHaveCount(1);
        expect($changedRows[0]['before']['name'])->toBe('John');
        expect($changedRows[0]['after']['name'])->toBe('Jane');
    });

    it('can handle DeleteRowsDTO (DELETE) events', function () {
        $deleteEvent = Mockery::mock(DeleteRowsDTO::class);
        $deleteEvent->shouldReceive('getEventInfo')->andReturn($this->eventInfo);
        $deleteEvent->shouldReceive('getTableMap')->andReturn($this->tableMap);
        $deleteEvent->shouldReceive('getValues')->andReturn([
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com']
        ]);

        $binlogEvent = new BinlogEvent($deleteEvent);

        expect($binlogEvent->getType())->toBe('delete');
        expect($binlogEvent->getDatabase())->toBe('test_db');
        expect($binlogEvent->getTable())->toBe('users');
        expect($binlogEvent->isDataChangeEvent())->toBeTrue();
        
        $changedRows = $binlogEvent->getChangedRows();
        expect($changedRows)->toHaveCount(1);
        expect($changedRows[0]['name'])->toBe('John');
    });

    it('can handle QueryDTO (QUERY) events', function () {
        $queryEvent = Mockery::mock(QueryDTO::class);
        $queryEvent->shouldReceive('getEventInfo')->andReturn($this->eventInfo);
        $queryEvent->shouldReceive('getDatabase')->andReturn('test_db');
        $queryEvent->shouldReceive('getQuery')->andReturn('CREATE TABLE test (id INT)');
        $queryEvent->shouldReceive('getExecutionTime')->andReturn(0.001);

        $binlogEvent = new BinlogEvent($queryEvent);

        expect($binlogEvent->getType())->toBe('query');
        expect($binlogEvent->getDatabase())->toBe('test_db');
        expect($binlogEvent->getTable())->toBe('');
        expect($binlogEvent->isQueryEvent())->toBeTrue();
        expect($binlogEvent->isDataChangeEvent())->toBeFalse();
        expect($binlogEvent->getQuery())->toBe('CREATE TABLE test (id INT)');
    });

    it('can get event info', function () {
        $writeEvent = Mockery::mock(WriteRowsDTO::class);
        $writeEvent->shouldReceive('getEventInfo')->andReturn($this->eventInfo);
        $writeEvent->shouldReceive('getTableMap')->andReturn($this->tableMap);
        $writeEvent->shouldReceive('getValues')->andReturn([]);

        $binlogEvent = new BinlogEvent($writeEvent);
        $eventInfo = $binlogEvent->getEventInfo();

        expect($eventInfo)->toHaveKey('type');
        expect($eventInfo)->toHaveKey('database');
        expect($eventInfo)->toHaveKey('table');
        expect($eventInfo)->toHaveKey('datetime');
        expect($eventInfo)->toHaveKey('timestamp');
        expect($eventInfo)->toHaveKey('log_position');
        expect($eventInfo)->toHaveKey('event_size');
        
        expect($eventInfo['type'])->toBe('insert');
        expect($eventInfo['database'])->toBe('test_db');
        expect($eventInfo['table'])->toBe('users');
        expect($eventInfo['log_position'])->toBe(1234);
        expect($eventInfo['event_size'])->toBe(56);
    });

    it('can convert to array', function () {
        $writeEvent = Mockery::mock(WriteRowsDTO::class);
        $writeEvent->shouldReceive('getEventInfo')->andReturn($this->eventInfo);
        $writeEvent->shouldReceive('getTableMap')->andReturn($this->tableMap);
        $writeEvent->shouldReceive('getValues')->andReturn([
            ['id' => 1, 'name' => 'John']
        ]);

        $binlogEvent = new BinlogEvent($writeEvent);
        $array = $binlogEvent->toArray();

        expect($array)->toHaveKey('event_info');
        expect($array)->toHaveKey('data');
        expect($array['event_info']['type'])->toBe('insert');
        expect($array['data']['rows'])->toHaveCount(1);
    });

    it('can convert to JSON', function () {
        $writeEvent = Mockery::mock(WriteRowsDTO::class);
        $writeEvent->shouldReceive('getEventInfo')->andReturn($this->eventInfo);
        $writeEvent->shouldReceive('getTableMap')->andReturn($this->tableMap);
        $writeEvent->shouldReceive('getValues')->andReturn([
            ['id' => 1, 'name' => 'John']
        ]);

        $binlogEvent = new BinlogEvent($writeEvent);
        $json = $binlogEvent->toJson();

        expect($json)->toBeString();
        $decoded = json_decode($json, true);
        expect($decoded)->toHaveKey('event_info');
        expect($decoded)->toHaveKey('data');
    });

    it('can convert to string', function () {
        $writeEvent = Mockery::mock(WriteRowsDTO::class);
        $writeEvent->shouldReceive('getEventInfo')->andReturn($this->eventInfo);
        $writeEvent->shouldReceive('getTableMap')->andReturn($this->tableMap);
        $writeEvent->shouldReceive('getValues')->andReturn([]);

        $binlogEvent = new BinlogEvent($writeEvent);
        $string = (string) $binlogEvent;

        expect($string)->toBeString();
        expect(json_decode($string))->not->toBeNull();
    });
});
