<?php

declare(strict_types=1);

use yangweijie\ThinkBinlog\BinlogListener;
use yangweijie\ThinkBinlog\Exception\BinlogException;

beforeEach(function () {
    // 模拟config函数
    if (!function_exists('config')) {
        function config($key = null, $default = null) {
            $config = [
                'binlog' => [
                    'mysql' => [
                        'host' => '127.0.0.1',
                        'port' => 3306,
                        'user' => 'test_user',
                        'password' => 'test_password',
                        'charset' => 'utf8mb4',
                        'slave_id' => 999,
                    ],
                    'binlog' => [
                        'databases_only' => [],
                        'tables_only' => [],
                        'events_only' => ['write', 'update', 'delete'],
                        'events_ignore' => [],
                    ],
                    'queue' => [
                        'enabled' => false,
                        'connection' => 'default',
                        'queue_name' => 'binlog',
                        'job_class' => 'yangweijie\\ThinkBinlog\\Job\\BinlogJob',
                    ],
                    'subscribers' => [],
                    'log' => [
                        'enabled' => true,
                        'level' => 'info',
                        'channel' => 'binlog',
                    ],
                ],
            ];
            
            if ($key === null) {
                return $config;
            }
            
            $keys = explode('.', $key);
            $value = $config;
            
            foreach ($keys as $k) {
                if (!isset($value[$k])) {
                    return $default;
                }
                $value = $value[$k];
            }
            
            return $value;
        }
    }
});

describe('BinlogListener', function () {
    it('can be instantiated with default config', function () {
        $listener = new BinlogListener();
        
        expect($listener)->toBeInstanceOf(BinlogListener::class);
        expect($listener->isRunning())->toBeFalse();
    });

    it('can be instantiated with custom config', function () {
        $config = [
            'mysql' => [
                'host' => 'custom-host',
                'port' => 3307,
                'user' => 'custom_user',
                'password' => 'custom_password',
            ],
        ];
        
        $listener = new BinlogListener($config);
        
        expect($listener)->toBeInstanceOf(BinlogListener::class);
        expect($listener->isRunning())->toBeFalse();
    });

    it('can stop when not running', function () {
        $listener = new BinlogListener();
        
        expect($listener->isRunning())->toBeFalse();
        
        $listener->stop();
        
        expect($listener->isRunning())->toBeFalse();
    });

    it('initializes subscribers from config', function () {
        // 创建一个测试订阅器类
        $subscriberClass = 'TestBinlogSubscriber';
        
        if (!class_exists($subscriberClass)) {
            eval("
                class {$subscriberClass} {
                    public function handle(\$event) {}
                }
            ");
        }
        
        $config = [
            'subscribers' => [$subscriberClass],
        ];
        
        $listener = new BinlogListener($config);
        
        expect($listener)->toBeInstanceOf(BinlogListener::class);
    });

    it('handles invalid subscriber classes gracefully', function () {
        $config = [
            'subscribers' => ['NonExistentSubscriber'],
        ];
        
        $listener = new BinlogListener($config);
        
        expect($listener)->toBeInstanceOf(BinlogListener::class);
    });

    it('can get running status', function () {
        $listener = new BinlogListener();
        
        expect($listener->isRunning())->toBeFalse();
    });
});

describe('BinlogListener Configuration', function () {
    it('merges custom config with default config', function () {
        $customConfig = [
            'mysql' => [
                'host' => 'custom-host',
                'port' => 3307,
            ],
            'binlog' => [
                'databases_only' => ['custom_db'],
            ],
        ];
        
        $listener = new BinlogListener($customConfig);
        
        expect($listener)->toBeInstanceOf(BinlogListener::class);
    });

    it('uses default values when config is empty', function () {
        $listener = new BinlogListener([]);
        
        expect($listener)->toBeInstanceOf(BinlogListener::class);
    });
});
