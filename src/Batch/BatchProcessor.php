<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog\Batch;

use yangweijie\ThinkBinlog\Event\BinlogEvent;
use yangweijie\ThinkBinlog\Compression\CompressionManager;
use think\facade\Queue;
use think\facade\Log;
use think\facade\Event;

/**
 * 批处理器
 */
class BatchProcessor
{
    /**
     * 当前批次
     */
    private ?EventBatch $currentBatch = null;

    /**
     * 批处理配置
     */
    private array $config;

    /**
     * 压缩管理器
     */
    private ?CompressionManager $compressionManager = null;

    /**
     * 处理统计
     */
    private array $stats = [
        'total_batches' => 0,
        'total_events' => 0,
        'total_compressed_size' => 0,
        'total_original_size' => 0,
    ];

    /**
     * 构造函数
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'batch_size' => 100,
            'batch_memory' => 1048576, // 1MB
            'batch_timeout' => 5,
            'compression_enabled' => true,
            'compression_algorithm' => 'auto',
            'compression_threshold' => 1024, // 1KB
            'queue_enabled' => true,
            'queue_connection' => 'default',
            'queue_name' => 'binlog_batch',
            'job_class' => 'yangweijie\\ThinkBinlog\\Job\\BatchJob',
        ], $config);

        if ($this->config['compression_enabled']) {
            $this->compressionManager = new CompressionManager();
        }

        $this->createNewBatch();
    }

    /**
     * 处理事件
     */
    public function processEvent(BinlogEvent $event): void
    {
        // 尝试添加事件到当前批次
        if (!$this->currentBatch->addEvent($event)) {
            // 当前批次已满，处理并创建新批次
            $this->processBatch();
            $this->createNewBatch();
            
            // 添加事件到新批次
            if (!$this->currentBatch->addEvent($event)) {
                // 单个事件太大，直接处理
                $this->processSingleEvent($event);
                return;
            }
        }

        // 检查是否应该处理批次
        if ($this->currentBatch->shouldProcess()) {
            $this->processBatch();
            $this->createNewBatch();
        }
    }

    /**
     * 强制处理当前批次
     */
    public function flush(): void
    {
        if ($this->currentBatch && !$this->currentBatch->isEmpty()) {
            $this->processBatch();
            $this->createNewBatch();
        }
    }

    /**
     * 处理批次
     */
    private function processBatch(): void
    {
        if ($this->currentBatch->isEmpty()) {
            return;
        }

        try {
            $batchData = $this->prepareBatchData();
            
            // 触发批处理事件
            Event::trigger('binlog.batch.processing', [$batchData]);

            if ($this->config['queue_enabled']) {
                $this->sendToQueue($batchData);
            }

            // 更新统计信息
            $this->updateStats($batchData);

            Log::info('批次处理完成', [
                'batch_id' => $batchData['batch_id'],
                'event_count' => $this->currentBatch->getEventCount(),
                'memory_usage' => $this->currentBatch->getMemoryUsage(),
                'age' => $this->currentBatch->getAge(),
            ]);

        } catch (\Exception $e) {
            Log::error('批次处理失败', [
                'error' => $e->getMessage(),
                'event_count' => $this->currentBatch->getEventCount(),
            ]);
            
            // 触发批处理失败事件
            Event::trigger('binlog.batch.failed', [$this->currentBatch, $e]);
        }
    }

    /**
     * 处理单个事件
     */
    private function processSingleEvent(BinlogEvent $event): void
    {
        try {
            $eventData = [
                'batch_id' => uniqid('single_', true),
                'events' => [$event->toArray()],
                'stats' => [
                    'total_events' => 1,
                    'memory_usage' => strlen($event->toJson()),
                    'age_seconds' => 0,
                ],
                'compression' => null,
                'created_at' => time(),
            ];

            if ($this->config['queue_enabled']) {
                $this->sendToQueue($eventData);
            }

            Log::info('单个事件处理完成', [
                'event_type' => $event->getType(),
                'database' => $event->getDatabase(),
                'table' => $event->getTable(),
            ]);

        } catch (\Exception $e) {
            Log::error('单个事件处理失败', [
                'error' => $e->getMessage(),
                'event_type' => $event->getType(),
            ]);
        }
    }

    /**
     * 准备批次数据
     */
    private function prepareBatchData(): array
    {
        $batchArray = $this->currentBatch->toArray();
        $batchData = [
            'batch_id' => uniqid('batch_', true),
            'events' => $batchArray['events'],
            'stats' => $batchArray['stats'],
            'compression' => null,
            'created_at' => $batchArray['created_at'],
        ];

        // 压缩数据
        if ($this->shouldCompress($batchData)) {
            $batchData['compression'] = $this->compressData($batchData);
        }

        return $batchData;
    }

    /**
     * 检查是否应该压缩
     */
    private function shouldCompress(array $batchData): bool
    {
        if (!$this->config['compression_enabled'] || !$this->compressionManager) {
            return false;
        }

        $dataSize = strlen(json_encode($batchData['events']));
        return $dataSize >= $this->config['compression_threshold'];
    }

    /**
     * 压缩数据
     */
    private function compressData(array &$batchData): array
    {
        $originalData = json_encode($batchData['events']);
        
        $algorithm = $this->config['compression_algorithm'];
        if ($algorithm === 'auto') {
            $compressor = $this->compressionManager->getBestCompressor($originalData);
            $algorithm = $compressor->getName();
        }

        $compressionResult = $this->compressionManager->compress($originalData, $algorithm);
        
        // 替换原始数据为压缩数据
        $batchData['events'] = base64_encode($compressionResult['data']);
        
        return [
            'algorithm' => $compressionResult['algorithm'],
            'level' => $compressionResult['level'],
            'original_size' => $compressionResult['original_size'],
            'compressed_size' => $compressionResult['compressed_size'],
            'compression_ratio' => $compressionResult['compression_ratio'],
        ];
    }

    /**
     * 发送到队列
     */
    private function sendToQueue(array $batchData): void
    {
        $jobClass = $this->config['job_class'];
        $queueName = $this->config['queue_name'];
        $connection = $this->config['queue_connection'];

        Queue::connection($connection)->push($jobClass, $batchData, $queueName);
    }

    /**
     * 更新统计信息
     */
    private function updateStats(array $batchData): void
    {
        $this->stats['total_batches']++;
        $this->stats['total_events'] += $batchData['stats']['total_events'];
        
        if (isset($batchData['compression'])) {
            $this->stats['total_compressed_size'] += $batchData['compression']['compressed_size'];
            $this->stats['total_original_size'] += $batchData['compression']['original_size'];
        }
    }

    /**
     * 创建新批次
     */
    private function createNewBatch(): void
    {
        $this->currentBatch = new EventBatch(
            $this->config['batch_size'],
            $this->config['batch_memory'],
            $this->config['batch_timeout']
        );
    }

    /**
     * 获取当前批次信息
     */
    public function getCurrentBatchInfo(): ?array
    {
        if (!$this->currentBatch) {
            return null;
        }

        return [
            'event_count' => $this->currentBatch->getEventCount(),
            'memory_usage' => $this->currentBatch->getMemoryUsage(),
            'age' => $this->currentBatch->getAge(),
            'is_full' => $this->currentBatch->isFull(),
            'is_timeout' => $this->currentBatch->isTimeout(),
            'should_process' => $this->currentBatch->shouldProcess(),
        ];
    }

    /**
     * 获取处理统计信息
     */
    public function getStats(): array
    {
        $stats = $this->stats;
        
        if ($stats['total_original_size'] > 0) {
            $stats['overall_compression_ratio'] = round(
                $stats['total_compressed_size'] / $stats['total_original_size'], 
                4
            );
        } else {
            $stats['overall_compression_ratio'] = 0;
        }

        return $stats;
    }

    /**
     * 重置统计信息
     */
    public function resetStats(): void
    {
        $this->stats = [
            'total_batches' => 0,
            'total_events' => 0,
            'total_compressed_size' => 0,
            'total_original_size' => 0,
        ];
    }
}
