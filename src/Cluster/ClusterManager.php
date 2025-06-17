<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog\Cluster;

use yangweijie\ThinkBinlog\Exception\BinlogException;
use think\facade\Cache;
use think\facade\Log;

/**
 * 集群管理器
 */
class ClusterManager
{
    /**
     * 节点信息
     */
    private array $nodeInfo;

    /**
     * 集群配置
     */
    private array $config;

    /**
     * 心跳间隔（秒）
     */
    private int $heartbeatInterval = 30;

    /**
     * 节点超时时间（秒）
     */
    private int $nodeTimeout = 90;

    /**
     * 构造函数
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'node_id' => $this->generateNodeId(),
            'node_name' => gethostname(),
            'node_ip' => $this->getLocalIp(),
            'node_port' => 9501,
            'redis_key_prefix' => 'binlog_cluster:',
            'heartbeat_interval' => 30,
            'node_timeout' => 90,
            'max_nodes' => 10,
        ], $config);

        $this->heartbeatInterval = $this->config['heartbeat_interval'];
        $this->nodeTimeout = $this->config['node_timeout'];

        $this->nodeInfo = [
            'id' => $this->config['node_id'],
            'name' => $this->config['node_name'],
            'ip' => $this->config['node_ip'],
            'port' => $this->config['node_port'],
            'status' => 'starting',
            'started_at' => time(),
            'last_heartbeat' => time(),
            'processed_events' => 0,
            'memory_usage' => 0,
            'cpu_usage' => 0,
        ];
    }

    /**
     * 加入集群
     */
    public function join(): void
    {
        // 检查集群节点数量限制
        $activeNodes = $this->getActiveNodes();
        if (count($activeNodes) >= $this->config['max_nodes']) {
            throw new BinlogException('集群节点数量已达上限');
        }

        // 注册节点
        $this->nodeInfo['status'] = 'active';
        $this->updateNodeInfo();

        Log::info('节点加入集群', $this->nodeInfo);
    }

    /**
     * 离开集群
     */
    public function leave(): void
    {
        $this->nodeInfo['status'] = 'leaving';
        $this->updateNodeInfo();

        // 等待一段时间确保其他节点感知到状态变化
        sleep(2);

        // 删除节点信息
        $this->removeNodeInfo();

        Log::info('节点离开集群', ['node_id' => $this->nodeInfo['id']]);
    }

    /**
     * 发送心跳
     */
    public function heartbeat(): void
    {
        $this->nodeInfo['last_heartbeat'] = time();
        $this->nodeInfo['memory_usage'] = memory_get_usage(true);
        $this->nodeInfo['cpu_usage'] = $this->getCpuUsage();
        
        $this->updateNodeInfo();
    }

    /**
     * 获取活跃节点列表
     */
    public function getActiveNodes(): array
    {
        $nodes = [];
        $pattern = $this->config['redis_key_prefix'] . 'node:*';
        $keys = Cache::store('redis')->keys($pattern);

        foreach ($keys as $key) {
            $nodeInfo = Cache::get($key);
            if ($nodeInfo && $this->isNodeActive($nodeInfo)) {
                $nodes[$nodeInfo['id']] = $nodeInfo;
            }
        }

        return $nodes;
    }

    /**
     * 获取主节点
     */
    public function getMasterNode(): ?array
    {
        $activeNodes = $this->getActiveNodes();
        if (empty($activeNodes)) {
            return null;
        }

        // 按启动时间排序，最早启动的为主节点
        uasort($activeNodes, function ($a, $b) {
            return $a['started_at'] <=> $b['started_at'];
        });

        return reset($activeNodes);
    }

    /**
     * 检查当前节点是否为主节点
     */
    public function isMaster(): bool
    {
        $master = $this->getMasterNode();
        return $master && $master['id'] === $this->nodeInfo['id'];
    }

    /**
     * 分配表监听任务
     */
    public function assignTables(array $tables): array
    {
        $activeNodes = $this->getActiveNodes();
        $nodeCount = count($activeNodes);
        
        if ($nodeCount === 0) {
            return [];
        }

        $assignment = [];
        $nodeIds = array_keys($activeNodes);
        
        foreach ($tables as $index => $table) {
            $nodeIndex = $index % $nodeCount;
            $nodeId = $nodeIds[$nodeIndex];
            
            if (!isset($assignment[$nodeId])) {
                $assignment[$nodeId] = [];
            }
            $assignment[$nodeId][] = $table;
        }

        return $assignment;
    }

    /**
     * 获取当前节点应该监听的表
     */
    public function getAssignedTables(array $allTables): array
    {
        $assignment = $this->assignTables($allTables);
        return $assignment[$this->nodeInfo['id']] ?? [];
    }

    /**
     * 选举新的主节点
     */
    public function electMaster(): ?array
    {
        $activeNodes = $this->getActiveNodes();
        if (empty($activeNodes)) {
            return null;
        }

        // 移除当前主节点（如果存在且不活跃）
        $currentMaster = $this->getMasterNode();
        if ($currentMaster && !$this->isNodeActive($currentMaster)) {
            unset($activeNodes[$currentMaster['id']]);
        }

        if (empty($activeNodes)) {
            return null;
        }

        // 选择负载最低的节点作为新主节点
        uasort($activeNodes, function ($a, $b) {
            $loadA = $a['memory_usage'] + $a['cpu_usage'] * 1000000; // 简单的负载计算
            $loadB = $b['memory_usage'] + $b['cpu_usage'] * 1000000;
            return $loadA <=> $loadB;
        });

        return reset($activeNodes);
    }

    /**
     * 获取集群状态
     */
    public function getClusterStatus(): array
    {
        $activeNodes = $this->getActiveNodes();
        $master = $this->getMasterNode();

        return [
            'total_nodes' => count($activeNodes),
            'master_node' => $master ? $master['id'] : null,
            'current_node' => $this->nodeInfo['id'],
            'is_master' => $this->isMaster(),
            'nodes' => $activeNodes,
        ];
    }

    /**
     * 清理过期节点
     */
    public function cleanupExpiredNodes(): int
    {
        $cleaned = 0;
        $pattern = $this->config['redis_key_prefix'] . 'node:*';
        $keys = Cache::store('redis')->keys($pattern);

        foreach ($keys as $key) {
            $nodeInfo = Cache::get($key);
            if ($nodeInfo && !$this->isNodeActive($nodeInfo)) {
                Cache::delete($key);
                $cleaned++;
                Log::info('清理过期节点', ['node_id' => $nodeInfo['id']]);
            }
        }

        return $cleaned;
    }

    /**
     * 更新节点信息
     */
    private function updateNodeInfo(): void
    {
        $key = $this->config['redis_key_prefix'] . 'node:' . $this->nodeInfo['id'];
        Cache::set($key, $this->nodeInfo, $this->nodeTimeout + 30);
    }

    /**
     * 删除节点信息
     */
    private function removeNodeInfo(): void
    {
        $key = $this->config['redis_key_prefix'] . 'node:' . $this->nodeInfo['id'];
        Cache::delete($key);
    }

    /**
     * 检查节点是否活跃
     */
    private function isNodeActive(array $nodeInfo): bool
    {
        $lastHeartbeat = $nodeInfo['last_heartbeat'] ?? 0;
        $timeDiff = time() - $lastHeartbeat;
        
        return $timeDiff <= $this->nodeTimeout && 
               in_array($nodeInfo['status'] ?? '', ['active', 'starting']);
    }

    /**
     * 生成节点ID
     */
    private function generateNodeId(): string
    {
        return uniqid(gethostname() . '_', true);
    }

    /**
     * 获取本地IP地址
     */
    private function getLocalIp(): string
    {
        $ip = '127.0.0.1';
        
        if (function_exists('gethostbyname')) {
            $hostname = gethostname();
            $resolvedIp = gethostbyname($hostname);
            if ($resolvedIp !== $hostname) {
                $ip = $resolvedIp;
            }
        }

        return $ip;
    }

    /**
     * 获取CPU使用率
     */
    private function getCpuUsage(): float
    {
        if (!function_exists('sys_getloadavg')) {
            return 0.0;
        }

        $load = sys_getloadavg();
        return $load[0] ?? 0.0;
    }

    /**
     * 获取节点信息
     */
    public function getNodeInfo(): array
    {
        return $this->nodeInfo;
    }

    /**
     * 更新处理事件计数
     */
    public function incrementProcessedEvents(int $count = 1): void
    {
        $this->nodeInfo['processed_events'] += $count;
    }
}
