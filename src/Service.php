<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog;

use think\Service as BaseService;
use yangweijie\ThinkBinlog\Command\BinlogCommand;

/**
 * Binlog服务提供者
 */
class Service extends BaseService
{
    /**
     * 注册服务
     */
    public function register(): void
    {
        // 注册Binlog监听器
        $this->app->bind('binlog.listener', function () {
            return new BinlogListener();
        });

        // 注册守护进程
        $this->app->bind('binlog.daemon', function () {
            return new Daemon\BinlogDaemon();
        });
    }

    /**
     * 启动服务
     */
    public function boot(): void
    {
        // 注册命令
        $this->commands([
            BinlogCommand::class,
        ]);

        // 发布配置文件
        $this->publishes([
            __DIR__ . '/../config/binlog.php' => $this->app->getConfigPath() . 'binlog.php',
        ]);
    }
}
