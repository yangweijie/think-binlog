<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog\Pool;

use MySQLReplication\MySQLReplicationFactory;

/**
 * 池化连接
 */
class PooledConnection
{
    /**
     * 连接ID
     */
    private string $id;

    /**
     * MySQL复制工厂
     */
    private MySQLReplicationFactory $factory;

    /**
     * 创建时间
     */
    private int $createdAt;

    /**
     * 最后使用时间
     */
    private int $lastUsed;

    /**
     * 是否已关闭
     */
    private bool $closed = false;

    /**
     * 使用次数
     */
    private int $useCount = 0;

    /**
     * 构造函数
     */
    public function __construct(string $id, MySQLReplicationFactory $factory, int $createdAt)
    {
        $this->id = $id;
        $this->factory = $factory;
        $this->createdAt = $createdAt;
        $this->lastUsed = $createdAt;
    }

    /**
     * 获取连接ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * 获取MySQL复制工厂
     */
    public function getFactory(): MySQLReplicationFactory
    {
        if ($this->closed) {
            throw new \RuntimeException('连接已关闭');
        }

        $this->lastUsed = time();
        $this->useCount++;
        
        return $this->factory;
    }

    /**
     * 获取创建时间
     */
    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    /**
     * 获取最后使用时间
     */
    public function getLastUsed(): int
    {
        return $this->lastUsed;
    }

    /**
     * 设置最后使用时间
     */
    public function setLastUsed(int $timestamp): void
    {
        $this->lastUsed = $timestamp;
    }

    /**
     * 获取使用次数
     */
    public function getUseCount(): int
    {
        return $this->useCount;
    }

    /**
     * 检查连接是否过期
     */
    public function isExpired(int $timeout): bool
    {
        return (time() - $this->lastUsed) > $timeout;
    }

    /**
     * 检查连接是否已关闭
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * Ping连接
     */
    public function ping(): bool
    {
        if ($this->closed) {
            return false;
        }

        try {
            // 这里应该实现实际的ping逻辑
            // 由于MySQLReplicationFactory没有直接的ping方法
            // 我们可以尝试获取连接状态
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 关闭连接
     */
    public function close(): void
    {
        if (!$this->closed) {
            try {
                // 这里应该关闭实际的连接
                // MySQLReplicationFactory可能需要特殊的关闭方法
            } catch (\Exception $e) {
                // 忽略关闭时的异常
            }
            
            $this->closed = true;
        }
    }

    /**
     * 获取连接信息
     */
    public function getInfo(): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->createdAt,
            'last_used' => $this->lastUsed,
            'use_count' => $this->useCount,
            'age' => time() - $this->createdAt,
            'idle_time' => time() - $this->lastUsed,
            'closed' => $this->closed,
        ];
    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        $this->close();
    }
}
