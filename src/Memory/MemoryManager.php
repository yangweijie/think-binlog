<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog\Memory;

use yangweijie\ThinkBinlog\Exception\BinlogException;
use think\facade\Log;

/**
 * 内存管理器
 */
class MemoryManager
{
    /**
     * 内存限制（字节）
     */
    private int $memoryLimit;

    /**
     * 警告阈值（百分比）
     */
    private float $warningThreshold;

    /**
     * 紧急阈值（百分比）
     */
    private float $emergencyThreshold;

    /**
     * 对象池
     */
    private array $objectPools = [];

    /**
     * 内存统计
     */
    private array $stats = [
        'peak_usage' => 0,
        'gc_runs' => 0,
        'objects_pooled' => 0,
        'objects_reused' => 0,
        'memory_warnings' => 0,
        'memory_emergencies' => 0,
    ];

    /**
     * 构造函数
     */
    public function __construct(array $config = [])
    {
        $this->memoryLimit = $config['memory_limit'] ?? $this->getSystemMemoryLimit();
        $this->warningThreshold = $config['warning_threshold'] ?? 0.8;
        $this->emergencyThreshold = $config['emergency_threshold'] ?? 0.9;

        // 初始化对象池
        $this->initializeObjectPools();
    }

    /**
     * 初始化对象池
     */
    private function initializeObjectPools(): void
    {
        $this->objectPools = [
            'arrays' => new ObjectPool('array', 100),
            'strings' => new ObjectPool('string', 50),
            'events' => new ObjectPool('event', 200),
        ];
    }

    /**
     * 检查内存使用情况
     */
    public function checkMemoryUsage(): array
    {
        $currentUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);
        $usagePercent = $currentUsage / $this->memoryLimit;

        // 更新统计
        if ($peakUsage > $this->stats['peak_usage']) {
            $this->stats['peak_usage'] = $peakUsage;
        }

        $status = 'normal';
        
        if ($usagePercent >= $this->emergencyThreshold) {
            $status = 'emergency';
            $this->stats['memory_emergencies']++;
            $this->handleMemoryEmergency();
        } elseif ($usagePercent >= $this->warningThreshold) {
            $status = 'warning';
            $this->stats['memory_warnings']++;
            $this->handleMemoryWarning();
        }

        return [
            'current_usage' => $currentUsage,
            'peak_usage' => $peakUsage,
            'memory_limit' => $this->memoryLimit,
            'usage_percent' => $usagePercent,
            'status' => $status,
            'available' => $this->memoryLimit - $currentUsage,
        ];
    }

    /**
     * 处理内存警告
     */
    private function handleMemoryWarning(): void
    {
        Log::warning('内存使用率达到警告阈值', $this->checkMemoryUsage());
        
        // 执行轻量级清理
        $this->lightCleanup();
    }

    /**
     * 处理内存紧急情况
     */
    private function handleMemoryEmergency(): void
    {
        Log::error('内存使用率达到紧急阈值', $this->checkMemoryUsage());
        
        // 执行强制清理
        $this->forceCleanup();
        
        // 如果仍然超过阈值，抛出异常
        $usage = memory_get_usage(true) / $this->memoryLimit;
        if ($usage >= $this->emergencyThreshold) {
            throw new BinlogException('内存不足，无法继续处理');
        }
    }

    /**
     * 轻量级清理
     */
    private function lightCleanup(): void
    {
        // 清理对象池中的过期对象
        foreach ($this->objectPools as $pool) {
            $pool->cleanup();
        }

        // 运行垃圾回收
        $this->runGarbageCollection();
    }

    /**
     * 强制清理
     */
    private function forceCleanup(): void
    {
        // 清空所有对象池
        foreach ($this->objectPools as $pool) {
            $pool->clear();
        }

        // 强制运行垃圾回收
        $this->runGarbageCollection(true);

        // 清理全局变量
        $this->cleanupGlobals();
    }

    /**
     * 运行垃圾回收
     */
    private function runGarbageCollection(bool $force = false): int
    {
        if ($force) {
            // 强制回收所有循环引用
            $collected = gc_collect_cycles();
        } else {
            // 只有在需要时才回收
            $collected = gc_collect_cycles();
        }

        $this->stats['gc_runs']++;
        
        if ($collected > 0) {
            Log::debug('垃圾回收完成', ['collected' => $collected]);
        }

        return $collected;
    }

    /**
     * 清理全局变量
     */
    private function cleanupGlobals(): void
    {
        // 清理可能的大型全局变量
        if (isset($GLOBALS['_temp_data'])) {
            unset($GLOBALS['_temp_data']);
        }
    }

    /**
     * 获取对象池
     */
    public function getObjectPool(string $type): ObjectPool
    {
        if (!isset($this->objectPools[$type])) {
            $this->objectPools[$type] = new ObjectPool($type, 50);
        }

        return $this->objectPools[$type];
    }

    /**
     * 借用对象
     */
    public function borrowObject(string $type, callable $factory = null): mixed
    {
        $pool = $this->getObjectPool($type);
        $object = $pool->borrow($factory);
        
        if ($object !== null) {
            $this->stats['objects_reused']++;
        }

        return $object;
    }

    /**
     * 归还对象
     */
    public function returnObject(string $type, mixed $object): void
    {
        $pool = $this->getObjectPool($type);
        if ($pool->return($object)) {
            $this->stats['objects_pooled']++;
        }
    }

    /**
     * 创建内存友好的数组
     */
    public function createArray(int $size = 0): array
    {
        $array = $this->borrowObject('arrays', function () {
            return [];
        });

        if ($array === null) {
            $array = [];
        }

        // 如果需要特定大小，预分配
        if ($size > 0) {
            $array = array_fill(0, $size, null);
        }

        return $array;
    }

    /**
     * 释放数组
     */
    public function releaseArray(array &$array): void
    {
        // 清空数组但保留容量
        $array = [];
        
        // 归还到对象池
        $this->returnObject('arrays', $array);
        
        // 解除引用
        $array = null;
    }

    /**
     * 创建内存友好的字符串缓冲区
     */
    public function createStringBuffer(): StringBuffer
    {
        $buffer = $this->borrowObject('strings', function () {
            return new StringBuffer();
        });

        if ($buffer === null) {
            $buffer = new StringBuffer();
        } else {
            $buffer->clear();
        }

        return $buffer;
    }

    /**
     * 释放字符串缓冲区
     */
    public function releaseStringBuffer(StringBuffer $buffer): void
    {
        $buffer->clear();
        $this->returnObject('strings', $buffer);
    }

    /**
     * 获取系统内存限制
     */
    private function getSystemMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit === '-1') {
            // 无限制，使用系统可用内存的80%
            $systemMemory = $this->getSystemMemory();
            return (int) ($systemMemory * 0.8);
        }

        return $this->parseMemoryLimit($memoryLimit);
    }

    /**
     * 解析内存限制字符串
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $unit = strtolower(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);

        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return (int) $limit;
        }
    }

    /**
     * 获取系统内存
     */
    private function getSystemMemory(): int
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $meminfo = file_get_contents('/proc/meminfo');
            if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $matches)) {
                return (int) $matches[1] * 1024;
            }
        }

        // 默认返回1GB
        return 1024 * 1024 * 1024;
    }

    /**
     * 获取内存统计信息
     */
    public function getStats(): array
    {
        return array_merge($this->stats, $this->checkMemoryUsage());
    }

    /**
     * 重置统计信息
     */
    public function resetStats(): void
    {
        $this->stats = [
            'peak_usage' => 0,
            'gc_runs' => 0,
            'objects_pooled' => 0,
            'objects_reused' => 0,
            'memory_warnings' => 0,
            'memory_emergencies' => 0,
        ];
    }

    /**
     * 格式化字节数
     */
    public function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
