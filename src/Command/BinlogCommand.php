<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog\Command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\console\input\Option;
use yangweijie\ThinkBinlog\BinlogListener;
use yangweijie\ThinkBinlog\Daemon\BinlogDaemon;
use yangweijie\ThinkBinlog\Exception\BinlogException;

/**
 * Binlog命令行工具
 */
class BinlogCommand extends Command
{
    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this->setName('binlog')
            ->setDescription('MySQL Binlog监听器管理工具')
            ->addArgument('action', Argument::REQUIRED, '操作类型: start|stop|restart|status|listen')
            ->addOption('daemon', 'd', Option::VALUE_NONE, '以守护进程模式运行')
            ->addOption('config', 'c', Option::VALUE_OPTIONAL, '配置文件路径')
            ->setHelp('MySQL Binlog监听器管理工具，支持启动、停止、重启和状态查看');
    }

    /**
     * 执行命令
     */
    protected function execute(Input $input, Output $output): int
    {
        $action = $input->getArgument('action');
        $daemon = $input->getOption('daemon');
        $configFile = $input->getOption('config');

        try {
            switch ($action) {
                case 'start':
                    return $this->handleStart($output, $daemon, $configFile);
                case 'stop':
                    return $this->handleStop($output);
                case 'restart':
                    return $this->handleRestart($output);
                case 'status':
                    return $this->handleStatus($output);
                case 'listen':
                    return $this->handleListen($output, $configFile);
                default:
                    $output->error("未知的操作类型: {$action}");
                    return 1;
            }
        } catch (BinlogException $e) {
            $output->error($e->getMessage());
            return 1;
        } catch (\Exception $e) {
            $output->error('执行失败: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * 处理启动命令
     */
    protected function handleStart(Output $output, bool $daemon, ?string $configFile): int
    {
        if ($daemon) {
            return $this->startDaemon($output, $configFile);
        } else {
            return $this->startListener($output, $configFile);
        }
    }

    /**
     * 启动守护进程
     */
    protected function startDaemon(Output $output, ?string $configFile): int
    {
        $config = $this->loadConfig($configFile);
        $daemon = new BinlogDaemon($config);

        if ($daemon->isRunning()) {
            $output->warning('Binlog守护进程已经在运行');
            return 0;
        }

        $output->info('正在启动Binlog守护进程...');
        $daemon->start();
        $output->success('Binlog守护进程启动成功');

        return 0;
    }

    /**
     * 启动监听器（前台运行）
     */
    protected function startListener(Output $output, ?string $configFile): int
    {
        $config = $this->loadConfig($configFile);
        $listener = new BinlogListener($config);

        $output->info('正在启动Binlog监听器...');
        $output->info('按 Ctrl+C 停止监听');

        // 注册信号处理器
        pcntl_signal(SIGINT, function () use ($listener, $output) {
            $output->info('正在停止Binlog监听器...');
            $listener->stop();
            exit(0);
        });

        $listener->start();

        return 0;
    }

    /**
     * 处理停止命令
     */
    protected function handleStop(Output $output): int
    {
        $daemon = new BinlogDaemon();

        if (!$daemon->isRunning()) {
            $output->warning('Binlog守护进程未运行');
            return 0;
        }

        $output->info('正在停止Binlog守护进程...');
        $daemon->stop();
        $output->success('Binlog守护进程已停止');

        return 0;
    }

    /**
     * 处理重启命令
     */
    protected function handleRestart(Output $output): int
    {
        $daemon = new BinlogDaemon();

        $output->info('正在重启Binlog守护进程...');
        $daemon->restart();
        $output->success('Binlog守护进程重启成功');

        return 0;
    }

    /**
     * 处理状态命令
     */
    protected function handleStatus(Output $output): int
    {
        $daemon = new BinlogDaemon();
        $status = $daemon->status();

        $output->info('Binlog守护进程状态:');
        $output->writeln('');

        if ($status['running']) {
            $output->writeln('<info>状态:</info> 运行中');
            $output->writeln('<info>PID:</info> ' . $status['pid']);
            $output->writeln('<info>运行时间:</info> ' . $this->formatUptime($status['uptime'] ?? 0));
            
            if (isset($status['memory'])) {
                $rss = round($status['memory']['rss'] / 1024, 2);
                $size = round($status['memory']['size'] / 1024, 2);
                $output->writeln('<info>内存使用:</info> RSS: ' . $rss . 'MB, SIZE: ' . $size . 'MB');
            }
        } else {
            $output->writeln('<comment>状态:</comment> 未运行');
        }

        $output->writeln('<info>PID文件:</info> ' . $status['pid_file']);
        $output->writeln('<info>日志文件:</info> ' . $status['log_file']);

        return 0;
    }

    /**
     * 处理监听命令（调试模式）
     */
    protected function handleListen(Output $output, ?string $configFile): int
    {
        $config = $this->loadConfig($configFile);
        $listener = new BinlogListener($config);

        $output->info('正在启动Binlog监听器（调试模式）...');
        $output->info('按 Ctrl+C 停止监听');

        // 注册信号处理器
        pcntl_signal(SIGINT, function () use ($listener, $output) {
            $output->info('正在停止Binlog监听器...');
            $listener->stop();
            exit(0);
        });

        // 设置调试模式
        $config['log']['level'] = 'debug';
        $listener = new BinlogListener($config);
        $listener->start();

        return 0;
    }

    /**
     * 加载配置
     */
    protected function loadConfig(?string $configFile): array
    {
        if ($configFile && file_exists($configFile)) {
            return include $configFile;
        }

        return config('binlog', []);
    }

    /**
     * 格式化运行时间
     */
    protected function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        $parts = [];
        if ($days > 0) $parts[] = $days . '天';
        if ($hours > 0) $parts[] = $hours . '小时';
        if ($minutes > 0) $parts[] = $minutes . '分钟';
        if ($seconds > 0) $parts[] = $seconds . '秒';

        return implode(' ', $parts) ?: '0秒';
    }
}
