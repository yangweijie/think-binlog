<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog\Memory;

/**
 * 对象池
 */
class ObjectPool
{
    /**
     * 对象类型
     */
    private string $type;

    /**
     * 最大池大小
     */
    private int $maxSize;

    /**
     * 对象池
     */
    private array $pool = [];

    /**
     * 对象创建时间
     */
    private array $createdAt = [];

    /**
     * 对象使用次数
     */
    private array $useCount = [];

    /**
     * 最大空闲时间（秒）
     */
    private int $maxIdleTime = 300;

    /**
     * 统计信息
     */
    private array $stats = [
        'created' => 0,
        'borrowed' => 0,
        'returned' => 0,
        'cleaned' => 0,
        'reused' => 0,
    ];

    /**
     * 构造函数
     */
    public function __construct(string $type, int $maxSize = 100, int $maxIdleTime = 300)
    {
        $this->type = $type;
        $this->maxSize = $maxSize;
        $this->maxIdleTime = $maxIdleTime;
    }

    /**
     * 借用对象
     */
    public function borrow(callable $factory = null): mixed
    {
        $this->stats['borrowed']++;

        // 从池中获取对象
        if (!empty($this->pool)) {
            $object = array_pop($this->pool);
            $objectId = spl_object_id($object);
            
            // 更新使用统计
            $this->useCount[$objectId] = ($this->useCount[$objectId] ?? 0) + 1;
            $this->stats['reused']++;
            
            return $object;
        }

        // 池中没有对象，创建新对象
        if ($factory) {
            $object = $factory();
            $this->stats['created']++;
            
            $objectId = spl_object_id($object);
            $this->createdAt[$objectId] = time();
            $this->useCount[$objectId] = 1;
            
            return $object;
        }

        return null;
    }

    /**
     * 归还对象
     */
    public function return(mixed $object): bool
    {
        if ($object === null) {
            return false;
        }

        $this->stats['returned']++;

        // 检查池是否已满
        if (count($this->pool) >= $this->maxSize) {
            return false;
        }

        // 重置对象状态（如果可能）
        $this->resetObject($object);

        // 放入池中
        $this->pool[] = $object;
        
        return true;
    }

    /**
     * 重置对象状态
     */
    private function resetObject(mixed $object): void
    {
        // 根据对象类型进行重置
        if (is_array($object)) {
            // 数组已经在外部清空
        } elseif ($object instanceof StringBuffer) {
            $object->clear();
        } elseif (method_exists($object, 'reset')) {
            $object->reset();
        } elseif (method_exists($object, 'clear')) {
            $object->clear();
        }
    }

    /**
     * 清理过期对象
     */
    public function cleanup(): int
    {
        $now = time();
        $cleaned = 0;
        $newPool = [];

        foreach ($this->pool as $object) {
            $objectId = spl_object_id($object);
            $createdAt = $this->createdAt[$objectId] ?? $now;
            
            // 检查是否过期
            if (($now - $createdAt) > $this->maxIdleTime) {
                // 清理相关数据
                unset($this->createdAt[$objectId]);
                unset($this->useCount[$objectId]);
                $cleaned++;
            } else {
                $newPool[] = $object;
            }
        }

        $this->pool = $newPool;
        $this->stats['cleaned'] += $cleaned;

        return $cleaned;
    }

    /**
     * 清空池
     */
    public function clear(): void
    {
        $this->pool = [];
        $this->createdAt = [];
        $this->useCount = [];
    }

    /**
     * 获取池状态
     */
    public function getStatus(): array
    {
        return [
            'type' => $this->type,
            'pool_size' => count($this->pool),
            'max_size' => $this->maxSize,
            'max_idle_time' => $this->maxIdleTime,
            'stats' => $this->stats,
        ];
    }

    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        $stats = $this->stats;
        $stats['hit_rate'] = $stats['borrowed'] > 0 ? 
            round($stats['reused'] / $stats['borrowed'], 4) : 0;
        
        return $stats;
    }

    /**
     * 重置统计信息
     */
    public function resetStats(): void
    {
        $this->stats = [
            'created' => 0,
            'borrowed' => 0,
            'returned' => 0,
            'cleaned' => 0,
            'reused' => 0,
        ];
    }
}
