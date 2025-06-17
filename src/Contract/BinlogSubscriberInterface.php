<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog\Contract;

use yangweijie\ThinkBinlog\Event\BinlogEvent;

/**
 * Binlog事件订阅器接口
 */
interface BinlogSubscriberInterface
{
    /**
     * 处理Binlog事件
     */
    public function handle(BinlogEvent $event): void;

    /**
     * 获取订阅的数据库列表
     * 返回空数组表示订阅所有数据库
     */
    public function getDatabases(): array;

    /**
     * 获取订阅的表列表
     * 返回空数组表示订阅所有表
     */
    public function getTables(): array;

    /**
     * 获取订阅的事件类型列表
     * 返回空数组表示订阅所有事件类型
     */
    public function getEventTypes(): array;
}
