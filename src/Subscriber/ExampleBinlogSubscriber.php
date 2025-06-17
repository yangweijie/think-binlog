<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog\Subscriber;

use yangweijie\ThinkBinlog\Contract\BinlogSubscriberInterface;
use yangweijie\ThinkBinlog\Event\BinlogEvent;
use think\facade\Log;

/**
 * 示例Binlog事件订阅器
 */
class ExampleBinlogSubscriber implements BinlogSubscriberInterface
{
    /**
     * 处理Binlog事件
     */
    public function handle(BinlogEvent $event): void
    {
        // 检查是否为关注的事件
        if (!$this->shouldHandle($event)) {
            return;
        }

        $eventInfo = $event->getEventInfo();
        $data = $event->getData();

        Log::info('处理Binlog事件', [
            'type' => $eventInfo['type'],
            'database' => $eventInfo['database'],
            'table' => $eventInfo['table'],
            'datetime' => $eventInfo['datetime'],
            'data' => $data,
        ]);

        // 根据事件类型进行不同的处理
        switch ($event->getType()) {
            case 'insert':
                $this->handleInsert($event);
                break;
            case 'update':
                $this->handleUpdate($event);
                break;
            case 'delete':
                $this->handleDelete($event);
                break;
            case 'query':
                $this->handleQuery($event);
                break;
        }
    }

    /**
     * 处理插入事件
     */
    protected function handleInsert(BinlogEvent $event): void
    {
        $rows = $event->getChangedRows();
        foreach ($rows as $row) {
            Log::info('数据插入', [
                'table' => $event->getTable(),
                'data' => $row,
            ]);
        }
    }

    /**
     * 处理更新事件
     */
    protected function handleUpdate(BinlogEvent $event): void
    {
        $rows = $event->getChangedRows();
        foreach ($rows as $row) {
            Log::info('数据更新', [
                'table' => $event->getTable(),
                'before' => $row['before'] ?? [],
                'after' => $row['after'] ?? [],
            ]);
        }
    }

    /**
     * 处理删除事件
     */
    protected function handleDelete(BinlogEvent $event): void
    {
        $rows = $event->getChangedRows();
        foreach ($rows as $row) {
            Log::info('数据删除', [
                'table' => $event->getTable(),
                'data' => $row,
            ]);
        }
    }

    /**
     * 处理查询事件
     */
    protected function handleQuery(BinlogEvent $event): void
    {
        Log::info('SQL查询', [
            'database' => $event->getDatabase(),
            'query' => $event->getQuery(),
        ]);
    }

    /**
     * 检查是否应该处理该事件
     */
    protected function shouldHandle(BinlogEvent $event): bool
    {
        // 检查数据库
        $databases = $this->getDatabases();
        if (!empty($databases) && !in_array($event->getDatabase(), $databases)) {
            return false;
        }

        // 检查表
        $tables = $this->getTables();
        if (!empty($tables) && !in_array($event->getTable(), $tables)) {
            return false;
        }

        // 检查事件类型
        $eventTypes = $this->getEventTypes();
        if (!empty($eventTypes) && !in_array($event->getType(), $eventTypes)) {
            return false;
        }

        return true;
    }

    /**
     * 获取订阅的数据库列表
     */
    public function getDatabases(): array
    {
        // 返回空数组表示订阅所有数据库
        return [];
    }

    /**
     * 获取订阅的表列表
     */
    public function getTables(): array
    {
        // 返回空数组表示订阅所有表
        return [];
    }

    /**
     * 获取订阅的事件类型列表
     */
    public function getEventTypes(): array
    {
        // 只订阅数据变更事件
        return ['insert', 'update', 'delete'];
    }
}
