<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog\Batch;

use yangweijie\ThinkBinlog\Event\BinlogEvent;

/**
 * 事件批处理器
 */
class EventBatch
{
    /**
     * 批次中的事件
     */
    private array $events = [];

    /**
     * 批次大小限制
     */
    private int $maxSize;

    /**
     * 批次内存限制（字节）
     */
    private int $maxMemory;

    /**
     * 批次超时时间（秒）
     */
    private int $timeout;

    /**
     * 批次创建时间
     */
    private int $createdAt;

    /**
     * 当前内存使用量
     */
    private int $currentMemory = 0;

    /**
     * 构造函数
     */
    public function __construct(int $maxSize = 100, int $maxMemory = 1048576, int $timeout = 5)
    {
        $this->maxSize = $maxSize;
        $this->maxMemory = $maxMemory;
        $this->timeout = $timeout;
        $this->createdAt = time();
    }

    /**
     * 添加事件到批次
     */
    public function addEvent(BinlogEvent $event): bool
    {
        if ($this->isFull()) {
            return false;
        }

        $eventData = $event->toArray();
        $eventSize = strlen(json_encode($eventData));

        if ($this->currentMemory + $eventSize > $this->maxMemory) {
            return false;
        }

        $this->events[] = $event;
        $this->currentMemory += $eventSize;

        return true;
    }

    /**
     * 检查批次是否已满
     */
    public function isFull(): bool
    {
        return count($this->events) >= $this->maxSize;
    }

    /**
     * 检查批次是否超时
     */
    public function isTimeout(): bool
    {
        return (time() - $this->createdAt) >= $this->timeout;
    }

    /**
     * 检查批次是否应该被处理
     */
    public function shouldProcess(): bool
    {
        return $this->isFull() || $this->isTimeout() || $this->isMemoryFull();
    }

    /**
     * 检查内存是否已满
     */
    public function isMemoryFull(): bool
    {
        return $this->currentMemory >= $this->maxMemory;
    }

    /**
     * 获取批次中的所有事件
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * 获取事件数量
     */
    public function getEventCount(): int
    {
        return count($this->events);
    }

    /**
     * 获取内存使用量
     */
    public function getMemoryUsage(): int
    {
        return $this->currentMemory;
    }

    /**
     * 获取批次年龄（秒）
     */
    public function getAge(): int
    {
        return time() - $this->createdAt;
    }

    /**
     * 检查批次是否为空
     */
    public function isEmpty(): bool
    {
        return empty($this->events);
    }

    /**
     * 清空批次
     */
    public function clear(): void
    {
        $this->events = [];
        $this->currentMemory = 0;
        $this->createdAt = time();
    }

    /**
     * 按事件类型分组
     */
    public function groupByEventType(): array
    {
        $groups = [];
        foreach ($this->events as $event) {
            $type = $event->getType();
            if (!isset($groups[$type])) {
                $groups[$type] = [];
            }
            $groups[$type][] = $event;
        }
        return $groups;
    }

    /**
     * 按数据库分组
     */
    public function groupByDatabase(): array
    {
        $groups = [];
        foreach ($this->events as $event) {
            $database = $event->getDatabase();
            if (!isset($groups[$database])) {
                $groups[$database] = [];
            }
            $groups[$database][] = $event;
        }
        return $groups;
    }

    /**
     * 按表分组
     */
    public function groupByTable(): array
    {
        $groups = [];
        foreach ($this->events as $event) {
            $key = $event->getDatabase() . '.' . $event->getTable();
            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }
            $groups[$key][] = $event;
        }
        return $groups;
    }

    /**
     * 获取批次统计信息
     */
    public function getStats(): array
    {
        $typeStats = [];
        $databaseStats = [];
        $tableStats = [];

        foreach ($this->events as $event) {
            // 事件类型统计
            $type = $event->getType();
            $typeStats[$type] = ($typeStats[$type] ?? 0) + 1;

            // 数据库统计
            $database = $event->getDatabase();
            if ($database) {
                $databaseStats[$database] = ($databaseStats[$database] ?? 0) + 1;
            }

            // 表统计
            $table = $event->getTable();
            if ($table) {
                $key = $database . '.' . $table;
                $tableStats[$key] = ($tableStats[$key] ?? 0) + 1;
            }
        }

        return [
            'total_events' => count($this->events),
            'memory_usage' => $this->currentMemory,
            'age_seconds' => $this->getAge(),
            'type_stats' => $typeStats,
            'database_stats' => $databaseStats,
            'table_stats' => $tableStats,
        ];
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'events' => array_map(function (BinlogEvent $event) {
                return $event->toArray();
            }, $this->events),
            'stats' => $this->getStats(),
            'created_at' => $this->createdAt,
        ];
    }
}
