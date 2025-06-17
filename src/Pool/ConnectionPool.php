<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog\Pool;

use yangweijie\ThinkBinlog\Exception\BinlogException;
use MySQLReplication\MySQLReplicationFactory;
use MySQLReplication\Config\Config;
use think\facade\Log;

/**
 * MySQL连接池
 */
class ConnectionPool
{
    /**
     * 连接池
     */
    private array $pool = [];

    /**
     * 活跃连接
     */
    private array $activeConnections = [];

    /**
     * 连接配置
     */
    private array $config;

    /**
     * 最大连接数
     */
    private int $maxConnections;

    /**
     * 最小连接数
     */
    private int $minConnections;

    /**
     * 连接超时时间（秒）
     */
    private int $connectionTimeout;

    /**
     * 空闲超时时间（秒）
     */
    private int $idleTimeout;

    /**
     * 连接统计
     */
    private array $stats = [
        'created' => 0,
        'destroyed' => 0,
        'borrowed' => 0,
        'returned' => 0,
        'timeouts' => 0,
        'errors' => 0,
    ];

    /**
     * 构造函数
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'max_connections' => 10,
            'min_connections' => 2,
            'connection_timeout' => 30,
            'idle_timeout' => 300,
            'validation_query' => 'SELECT 1',
            'validation_interval' => 60,
        ], $config);

        $this->maxConnections = $this->config['max_connections'];
        $this->minConnections = $this->config['min_connections'];
        $this->connectionTimeout = $this->config['connection_timeout'];
        $this->idleTimeout = $this->config['idle_timeout'];

        // 初始化最小连接数
        $this->initializePool();
    }

    /**
     * 初始化连接池
     */
    private function initializePool(): void
    {
        for ($i = 0; $i < $this->minConnections; $i++) {
            try {
                $connection = $this->createConnection();
                $this->pool[] = $connection;
            } catch (\Exception $e) {
                Log::error('初始化连接池失败', ['error' => $e->getMessage()]);
                break;
            }
        }
    }

    /**
     * 获取连接
     */
    public function getConnection(): PooledConnection
    {
        $startTime = microtime(true);
        
        try {
            // 从池中获取可用连接
            $connection = $this->borrowConnection();
            
            if (!$connection) {
                throw new BinlogException('无法获取数据库连接');
            }

            $this->stats['borrowed']++;
            return $connection;

        } catch (\Exception $e) {
            $this->stats['errors']++;
            
            // 检查是否超时
            if ((microtime(true) - $startTime) >= $this->connectionTimeout) {
                $this->stats['timeouts']++;
            }
            
            throw $e;
        }
    }

    /**
     * 归还连接
     */
    public function returnConnection(PooledConnection $connection): void
    {
        $connectionId = $connection->getId();
        
        if (!isset($this->activeConnections[$connectionId])) {
            Log::warning('尝试归还未知连接', ['connection_id' => $connectionId]);
            return;
        }

        // 验证连接是否仍然有效
        if ($this->validateConnection($connection)) {
            // 连接有效，放回池中
            $connection->setLastUsed(time());
            $this->pool[] = $connection;
        } else {
            // 连接无效，销毁并创建新连接
            $this->destroyConnection($connection);
            
            // 如果池中连接数少于最小值，创建新连接
            if (count($this->pool) < $this->minConnections) {
                try {
                    $newConnection = $this->createConnection();
                    $this->pool[] = $newConnection;
                } catch (\Exception $e) {
                    Log::error('创建替换连接失败', ['error' => $e->getMessage()]);
                }
            }
        }

        unset($this->activeConnections[$connectionId]);
        $this->stats['returned']++;
    }

    /**
     * 借用连接
     */
    private function borrowConnection(): ?PooledConnection
    {
        // 清理过期连接
        $this->cleanupIdleConnections();

        // 从池中获取连接
        if (!empty($this->pool)) {
            $connection = array_pop($this->pool);
            
            // 验证连接
            if ($this->validateConnection($connection)) {
                $this->activeConnections[$connection->getId()] = $connection;
                return $connection;
            } else {
                $this->destroyConnection($connection);
            }
        }

        // 池中没有可用连接，尝试创建新连接
        if (count($this->activeConnections) < $this->maxConnections) {
            try {
                $connection = $this->createConnection();
                $this->activeConnections[$connection->getId()] = $connection;
                return $connection;
            } catch (\Exception $e) {
                Log::error('创建新连接失败', ['error' => $e->getMessage()]);
            }
        }

        // 等待连接可用
        return $this->waitForConnection();
    }

    /**
     * 等待连接可用
     */
    private function waitForConnection(): ?PooledConnection
    {
        $startTime = time();
        
        while ((time() - $startTime) < $this->connectionTimeout) {
            if (!empty($this->pool)) {
                $connection = array_pop($this->pool);
                if ($this->validateConnection($connection)) {
                    $this->activeConnections[$connection->getId()] = $connection;
                    return $connection;
                }
                $this->destroyConnection($connection);
            }
            
            usleep(100000); // 等待100ms
        }

        return null;
    }

    /**
     * 创建连接
     */
    private function createConnection(): PooledConnection
    {
        $connectionId = uniqid('conn_', true);
        
        // 这里应该创建实际的MySQL连接
        // 为了示例，我们创建一个模拟连接
        $factory = new MySQLReplicationFactory($this->buildConfig());
        
        $connection = new PooledConnection($connectionId, $factory, time());
        $this->stats['created']++;
        
        Log::debug('创建新连接', ['connection_id' => $connectionId]);
        return $connection;
    }

    /**
     * 销毁连接
     */
    private function destroyConnection(PooledConnection $connection): void
    {
        $connection->close();
        $this->stats['destroyed']++;
        
        Log::debug('销毁连接', ['connection_id' => $connection->getId()]);
    }

    /**
     * 验证连接
     */
    private function validateConnection(PooledConnection $connection): bool
    {
        try {
            // 检查连接是否过期
            if ($connection->isExpired($this->idleTimeout)) {
                return false;
            }

            // 执行验证查询
            return $connection->ping();
            
        } catch (\Exception $e) {
            Log::debug('连接验证失败', [
                'connection_id' => $connection->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 清理空闲连接
     */
    private function cleanupIdleConnections(): void
    {
        $now = time();
        $cleaned = 0;
        
        $this->pool = array_filter($this->pool, function (PooledConnection $connection) use ($now, &$cleaned) {
            if ($connection->isExpired($this->idleTimeout)) {
                $this->destroyConnection($connection);
                $cleaned++;
                return false;
            }
            return true;
        });

        if ($cleaned > 0) {
            Log::debug('清理空闲连接', ['cleaned_count' => $cleaned]);
        }
    }

    /**
     * 构建MySQL配置
     */
    private function buildConfig(): Config
    {
        // 这里应该根据实际配置构建Config对象
        // 为了示例，返回一个基本配置
        return new Config();
    }

    /**
     * 关闭连接池
     */
    public function close(): void
    {
        // 关闭所有池中的连接
        foreach ($this->pool as $connection) {
            $this->destroyConnection($connection);
        }
        $this->pool = [];

        // 关闭所有活跃连接
        foreach ($this->activeConnections as $connection) {
            $this->destroyConnection($connection);
        }
        $this->activeConnections = [];

        Log::info('连接池已关闭');
    }

    /**
     * 获取连接池状态
     */
    public function getStatus(): array
    {
        return [
            'pool_size' => count($this->pool),
            'active_connections' => count($this->activeConnections),
            'max_connections' => $this->maxConnections,
            'min_connections' => $this->minConnections,
            'stats' => $this->stats,
        ];
    }

    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * 重置统计信息
     */
    public function resetStats(): void
    {
        $this->stats = [
            'created' => 0,
            'destroyed' => 0,
            'borrowed' => 0,
            'returned' => 0,
            'timeouts' => 0,
            'errors' => 0,
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
