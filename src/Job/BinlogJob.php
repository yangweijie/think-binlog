<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog\Job;

use think\queue\Job;
use think\facade\Log;
use think\facade\Event;

/**
 * Binlog队列任务
 */
class BinlogJob
{
    /**
     * 执行任务
     */
    public function fire(Job $job, array $data): void
    {
        try {
            // 记录任务开始
            Log::info('开始处理Binlog队列任务', $data);

            // 触发事件，让应用层可以监听处理
            Event::trigger('binlog.queue.process', $data);

            // 根据事件类型分发处理
            $this->dispatchByEventType($data);

            // 删除任务
            $job->delete();

            Log::info('Binlog队列任务处理完成');

        } catch (\Exception $e) {
            Log::error('Binlog队列任务处理失败: ' . $e->getMessage(), [
                'data' => $data,
                'exception' => $e->getTraceAsString()
            ]);

            // 重试机制
            if ($job->attempts() < 3) {
                $job->release(60); // 60秒后重试
            } else {
                $job->delete(); // 超过重试次数，删除任务
                Log::error('Binlog队列任务重试次数超限，已删除', $data);
            }
        }
    }

    /**
     * 根据事件类型分发处理
     */
    protected function dispatchByEventType(array $data): void
    {
        $eventInfo = $data['event_info'] ?? [];
        $eventType = $eventInfo['type'] ?? '';
        $database = $eventInfo['database'] ?? '';
        $table = $eventInfo['table'] ?? '';

        // 触发具体的事件类型
        switch ($eventType) {
            case 'insert':
                Event::trigger('binlog.insert', [$database, $table, $data]);
                break;
            case 'update':
                Event::trigger('binlog.update', [$database, $table, $data]);
                break;
            case 'delete':
                Event::trigger('binlog.delete', [$database, $table, $data]);
                break;
            case 'query':
                Event::trigger('binlog.query', [$database, $data]);
                break;
            default:
                Event::trigger('binlog.unknown', [$data]);
                break;
        }

        // 触发表级别的事件
        if ($database && $table) {
            Event::trigger("binlog.{$database}.{$table}", [$eventType, $data]);
        }
    }

    /**
     * 任务失败处理
     */
    public function failed(array $data): void
    {
        Log::error('Binlog队列任务最终失败', $data);
        Event::trigger('binlog.queue.failed', $data);
    }
}
