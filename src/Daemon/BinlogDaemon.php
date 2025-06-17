<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog\Daemon;

use yangweijie\ThinkBinlog\BinlogListener;
use yangweijie\ThinkBinlog\Exception\BinlogException;
use think\facade\Log;

/**
 * Binlog守护进程
 */
class BinlogDaemon
{
    /**
     * 配置信息
     */
    protected array $config;

    /**
     * PID文件路径
     */
    protected string $pidFile;

    /**
     * 日志文件路径
     */
    protected string $logFile;

    /**
     * 监听器实例
     */
    protected ?BinlogListener $listener = null;

    /**
     * 是否正在运行
     */
    protected bool $running = false;

    /**
     * 启动时间
     */
    protected int $startTime;

    /**
     * 构造函数
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge(config('binlog.daemon', []), $config);
        $this->pidFile = $this->config['pid_file'] ?? runtime_path() . 'binlog.pid';
        $this->logFile = $this->config['log_file'] ?? runtime_path() . 'log/binlog.log';
        $this->startTime = time();
    }

    /**
     * 启动守护进程
     */
    public function start(): void
    {
        // 检查是否已经在运行
        if ($this->isRunning()) {
            throw new BinlogException('Binlog守护进程已经在运行');
        }

        // 创建守护进程
        $this->daemonize();

        // 写入PID文件
        $this->writePidFile();

        // 注册信号处理器
        $this->registerSignalHandlers();

        // 启动监听器
        $this->startListener();
    }

    /**
     * 停止守护进程
     */
    public function stop(): void
    {
        $pid = $this->getPid();
        if (!$pid) {
            throw new BinlogException('Binlog守护进程未运行');
        }

        // 发送终止信号
        if (!posix_kill($pid, SIGTERM)) {
            throw new BinlogException('无法停止Binlog守护进程');
        }

        // 等待进程结束
        $timeout = 10;
        while ($timeout > 0 && $this->isRunning()) {
            sleep(1);
            $timeout--;
        }

        // 强制杀死进程
        if ($this->isRunning()) {
            posix_kill($pid, SIGKILL);
        }

        // 清理PID文件
        $this->removePidFile();
    }

    /**
     * 重启守护进程
     */
    public function restart(): void
    {
        if ($this->isRunning()) {
            $this->stop();
        }
        $this->start();
    }

    /**
     * 获取守护进程状态
     */
    public function status(): array
    {
        $pid = $this->getPid();
        $running = $this->isRunning();

        $status = [
            'running' => $running,
            'pid' => $pid,
            'pid_file' => $this->pidFile,
            'log_file' => $this->logFile,
        ];

        if ($running && $pid) {
            $status['uptime'] = time() - $this->getStartTime($pid);
            $status['memory'] = $this->getMemoryUsage($pid);
        }

        return $status;
    }

    /**
     * 检查是否正在运行
     */
    public function isRunning(): bool
    {
        $pid = $this->getPid();
        return $pid && posix_kill($pid, 0);
    }

    /**
     * 创建守护进程
     */
    protected function daemonize(): void
    {
        // 第一次fork
        $pid = pcntl_fork();
        if ($pid < 0) {
            throw new BinlogException('无法创建子进程');
        } elseif ($pid > 0) {
            exit(0); // 父进程退出
        }

        // 设置新会话
        if (posix_setsid() < 0) {
            throw new BinlogException('无法设置新会话');
        }

        // 第二次fork
        $pid = pcntl_fork();
        if ($pid < 0) {
            throw new BinlogException('无法创建子进程');
        } elseif ($pid > 0) {
            exit(0); // 父进程退出
        }

        // 设置文件权限掩码
        umask(0);

        // 改变工作目录
        chdir('/');

        // 关闭标准输入输出
        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);

        // 重定向标准输入输出到日志文件
        $this->redirectOutput();
    }

    /**
     * 重定向输出到日志文件
     */
    protected function redirectOutput(): void
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $stdin = fopen('/dev/null', 'r');
        $stdout = fopen($this->logFile, 'a');
        $stderr = fopen($this->logFile, 'a');
    }

    /**
     * 注册信号处理器
     */
    protected function registerSignalHandlers(): void
    {
        pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        pcntl_signal(SIGINT, [$this, 'handleSignal']);
        pcntl_signal(SIGHUP, [$this, 'handleSignal']);
        pcntl_signal(SIGUSR1, [$this, 'handleSignal']);
    }

    /**
     * 处理信号
     */
    public function handleSignal(int $signal): void
    {
        switch ($signal) {
            case SIGTERM:
            case SIGINT:
                $this->shutdown();
                break;
            case SIGHUP:
                $this->reload();
                break;
            case SIGUSR1:
                $this->logStatus();
                break;
        }
    }

    /**
     * 启动监听器
     */
    protected function startListener(): void
    {
        $this->running = true;
        $this->listener = new BinlogListener();

        try {
            $this->log('Binlog守护进程启动，PID: ' . getmypid());
            
            // 检查内存限制和重启间隔
            $this->checkLimits();
            
            $this->listener->start();
        } catch (\Exception $e) {
            $this->log('Binlog监听器启动失败: ' . $e->getMessage());
            $this->shutdown();
        }
    }

    /**
     * 检查限制条件
     */
    protected function checkLimits(): void
    {
        $memoryLimit = $this->config['memory_limit'] ?? 128;
        $restartInterval = $this->config['restart_interval'] ?? 3600;

        // 检查内存使用量
        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        if ($memoryUsage > $memoryLimit) {
            $this->log("内存使用量超限 ({$memoryUsage}MB > {$memoryLimit}MB)，重启进程");
            $this->restart();
            return;
        }

        // 检查运行时间
        $uptime = time() - $this->startTime;
        if ($uptime > $restartInterval) {
            $this->log("运行时间超限 ({$uptime}s > {$restartInterval}s)，重启进程");
            $this->restart();
            return;
        }
    }

    /**
     * 关闭守护进程
     */
    protected function shutdown(): void
    {
        $this->running = false;
        
        if ($this->listener) {
            $this->listener->stop();
        }

        $this->removePidFile();
        $this->log('Binlog守护进程关闭');
        exit(0);
    }

    /**
     * 重新加载配置
     */
    protected function reload(): void
    {
        $this->log('重新加载配置');
        // 重新创建监听器
        $this->listener = new BinlogListener();
    }

    /**
     * 记录状态日志
     */
    protected function logStatus(): void
    {
        $status = $this->status();
        $this->log('守护进程状态: ' . json_encode($status));
    }

    /**
     * 写入PID文件
     */
    protected function writePidFile(): void
    {
        $pidDir = dirname($this->pidFile);
        if (!is_dir($pidDir)) {
            mkdir($pidDir, 0755, true);
        }

        file_put_contents($this->pidFile, getmypid());
    }

    /**
     * 删除PID文件
     */
    protected function removePidFile(): void
    {
        if (file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }
    }

    /**
     * 获取PID
     */
    protected function getPid(): ?int
    {
        if (!file_exists($this->pidFile)) {
            return null;
        }

        $pid = (int) file_get_contents($this->pidFile);
        return $pid > 0 ? $pid : null;
    }

    /**
     * 获取进程启动时间
     */
    protected function getStartTime(int $pid): int
    {
        $stat = file_get_contents("/proc/{$pid}/stat");
        if ($stat) {
            $parts = explode(' ', $stat);
            return (int) ($parts[21] ?? 0) / 100; // starttime in clock ticks
        }
        return 0;
    }

    /**
     * 获取内存使用量
     */
    protected function getMemoryUsage(int $pid): array
    {
        $status = file_get_contents("/proc/{$pid}/status");
        $memory = [];
        
        if ($status) {
            preg_match('/VmRSS:\s+(\d+)\s+kB/', $status, $matches);
            $memory['rss'] = isset($matches[1]) ? (int) $matches[1] : 0;
            
            preg_match('/VmSize:\s+(\d+)\s+kB/', $status, $matches);
            $memory['size'] = isset($matches[1]) ? (int) $matches[1] : 0;
        }
        
        return $memory;
    }

    /**
     * 记录日志
     */
    protected function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}
