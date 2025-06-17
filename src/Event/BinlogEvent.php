<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog\Event;

use MySQLReplication\Event\DTO\EventDTO;
use MySQLReplication\Event\DTO\WriteRowsDTO;
use MySQLReplication\Event\DTO\UpdateRowsDTO;
use MySQLReplication\Event\DTO\DeleteRowsDTO;
use MySQLReplication\Event\DTO\QueryDTO;

/**
 * Binlog事件封装类
 */
class BinlogEvent
{
    /**
     * 原始事件对象
     */
    protected EventDTO $event;

    /**
     * 事件类型
     */
    protected string $type;

    /**
     * 数据库名
     */
    protected string $database = '';

    /**
     * 表名
     */
    protected string $table = '';

    /**
     * 事件数据
     */
    protected array $data = [];

    /**
     * 事件时间
     */
    protected \DateTime $datetime;

    /**
     * 构造函数
     */
    public function __construct(EventDTO $event)
    {
        $this->event = $event;
        $this->datetime = $event->getEventInfo()->getDateTime();
        $this->parseEvent();
    }

    /**
     * 解析事件
     */
    protected function parseEvent(): void
    {
        switch (true) {
            case $this->event instanceof WriteRowsDTO:
                $this->type = 'insert';
                $this->database = $this->event->getTableMap()->getDatabase();
                $this->table = $this->event->getTableMap()->getTable();
                $this->data = [
                    'rows' => $this->event->getValues(),
                    'columns' => $this->event->getTableMap()->getColumnsArray(),
                ];
                break;

            case $this->event instanceof UpdateRowsDTO:
                $this->type = 'update';
                $this->database = $this->event->getTableMap()->getDatabase();
                $this->table = $this->event->getTableMap()->getTable();
                $this->data = [
                    'rows' => $this->event->getValues(),
                    'columns' => $this->event->getTableMap()->getColumnsArray(),
                ];
                break;

            case $this->event instanceof DeleteRowsDTO:
                $this->type = 'delete';
                $this->database = $this->event->getTableMap()->getDatabase();
                $this->table = $this->event->getTableMap()->getTable();
                $this->data = [
                    'rows' => $this->event->getValues(),
                    'columns' => $this->event->getTableMap()->getColumnsArray(),
                ];
                break;

            case $this->event instanceof QueryDTO:
                $this->type = 'query';
                $this->database = $this->event->getDatabase();
                $this->data = [
                    'query' => $this->event->getQuery(),
                    'execution_time' => $this->event->getExecutionTime(),
                ];
                break;

            default:
                $this->type = 'unknown';
                $this->data = [];
                break;
        }
    }

    /**
     * 获取事件类型
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * 获取数据库名
     */
    public function getDatabase(): string
    {
        return $this->database;
    }

    /**
     * 获取表名
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * 获取事件数据
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 获取事件时间
     */
    public function getDatetime(): \DateTime
    {
        return $this->datetime;
    }

    /**
     * 获取原始事件对象
     */
    public function getOriginalEvent(): EventDTO
    {
        return $this->event;
    }

    /**
     * 获取事件信息
     */
    public function getEventInfo(): array
    {
        return [
            'type' => $this->type,
            'database' => $this->database,
            'table' => $this->table,
            'datetime' => $this->datetime->format('Y-m-d H:i:s'),
            'timestamp' => $this->datetime->getTimestamp(),
            'log_position' => $this->event->getEventInfo()->getPos(),
            'event_size' => $this->event->getEventInfo()->getSize(),
        ];
    }

    /**
     * 检查是否为数据变更事件
     */
    public function isDataChangeEvent(): bool
    {
        return in_array($this->type, ['insert', 'update', 'delete']);
    }

    /**
     * 检查是否为查询事件
     */
    public function isQueryEvent(): bool
    {
        return $this->type === 'query';
    }

    /**
     * 获取变更的行数据
     */
    public function getChangedRows(): array
    {
        if (!$this->isDataChangeEvent()) {
            return [];
        }

        return $this->data['rows'] ?? [];
    }

    /**
     * 获取表结构信息
     */
    public function getTableColumns(): array
    {
        if (!$this->isDataChangeEvent()) {
            return [];
        }

        return $this->data['columns'] ?? [];
    }

    /**
     * 获取SQL查询语句
     */
    public function getQuery(): string
    {
        if (!$this->isQueryEvent()) {
            return '';
        }

        return $this->data['query'] ?? '';
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'event_info' => $this->getEventInfo(),
            'data' => $this->data,
        ];
    }

    /**
     * 转换为JSON字符串
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * 魔术方法：转换为字符串
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
