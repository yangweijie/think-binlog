# 开发指南

## 开发环境搭建

### 使用Docker（推荐）

```bash
# 克隆项目
git clone https://github.com/yangweijie/think-binlog.git
cd think-binlog

# 启动开发环境
docker-compose up -d

# 安装依赖
docker-compose exec php composer install

# 运行测试
docker-compose exec php composer test

# 查看日志
docker-compose logs -f php
```

### 本地开发环境

#### 环境要求

- PHP >= 8.0
- MySQL >= 5.5 (启用binlog)
- Composer
- 扩展: pcntl, posix, pdo_mysql

#### 安装步骤

```bash
# 克隆项目
git clone https://github.com/yangweijie/think-binlog.git
cd think-binlog

# 安装依赖
composer install

# 配置MySQL
mysql -u root -p < docker/mysql/init/01-create-user.sql

# 运行测试
composer test
```

## 项目结构

```
think-binlog/
├── src/                          # 源代码
│   ├── BinlogListener.php        # 核心监听器
│   ├── Service.php               # 服务提供者
│   ├── Command/                  # 命令行工具
│   ├── Contract/                 # 接口定义
│   ├── Daemon/                   # 守护进程
│   ├── Event/                    # 事件封装
│   ├── Exception/                # 异常类
│   ├── Job/                      # 队列任务
│   └── Subscriber/               # 事件订阅器
├── tests/                        # 测试文件
│   ├── Unit/                     # 单元测试
│   ├── Feature/                  # 功能测试
│   └── Performance/              # 性能测试
├── config/                       # 配置文件
├── docs/                         # 文档
├── docker/                       # Docker配置
└── .github/                      # CI/CD配置
```

## 开发规范

### 代码风格

项目遵循PSR-12编码规范，使用PHP CS Fixer进行代码格式化：

```bash
# 检查代码风格
composer cs-check

# 自动修复代码风格
composer cs-fix
```

### 静态分析

使用PHPStan进行静态代码分析：

```bash
# 运行静态分析
composer phpstan
```

### 测试

使用Pest测试框架：

```bash
# 运行所有测试
composer test

# 运行单元测试
composer test:unit

# 运行功能测试
composer test:feature

# 生成覆盖率报告
composer test:coverage
```

### 提交规范

使用Conventional Commits规范：

```
feat: 新功能
fix: 修复bug
docs: 文档更新
style: 代码格式化
refactor: 重构
test: 测试相关
chore: 构建过程或辅助工具的变动
```

示例：
```
feat: 添加MySQL 8.0支持
fix: 修复守护进程内存泄漏问题
docs: 更新安装文档
```

## 核心组件开发

### 添加新的事件类型

1. 在`BinlogEvent`类中添加新的事件类型处理：

```php
// src/Event/BinlogEvent.php
protected function parseEvent(): void
{
    switch (true) {
        // ... 现有代码
        case $this->event instanceof NewEventDTO:
            $this->type = 'new_event';
            $this->handleNewEvent();
            break;
    }
}

private function handleNewEvent(): void
{
    // 处理新事件类型的逻辑
}
```

2. 添加相应的测试：

```php
// tests/Unit/Event/BinlogEventTest.php
it('can handle new event type', function () {
    // 测试代码
});
```

### 添加新的订阅器

1. 实现`BinlogSubscriberInterface`接口：

```php
<?php

namespace app\listener;

use yangweijie\ThinkBinlog\Contract\BinlogSubscriberInterface;
use yangweijie\ThinkBinlog\Event\BinlogEvent;

class CustomBinlogSubscriber implements BinlogSubscriberInterface
{
    public function handle(BinlogEvent $event): void
    {
        // 处理逻辑
    }

    public function getDatabases(): array
    {
        return ['specific_db'];
    }

    public function getTables(): array
    {
        return ['specific_table'];
    }

    public function getEventTypes(): array
    {
        return ['insert', 'update'];
    }
}
```

2. 在配置中注册：

```php
// config/binlog.php
'subscribers' => [
    'app\\listener\\CustomBinlogSubscriber',
],
```

### 扩展命令行工具

1. 创建新的命令：

```php
<?php

namespace yangweijie\ThinkBinlog\Command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class CustomCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('binlog:custom')
            ->setDescription('自定义命令');
    }

    protected function execute(Input $input, Output $output): int
    {
        // 命令逻辑
        return 0;
    }
}
```

2. 在服务提供者中注册：

```php
// src/Service.php
public function boot(): void
{
    $this->commands([
        BinlogCommand::class,
        CustomCommand::class, // 添加新命令
    ]);
}
```

## 性能优化

### 内存优化

1. 及时释放不需要的对象：

```php
foreach ($events as $event) {
    $this->processEvent($event);
    unset($event); // 释放内存
}
```

2. 使用生成器处理大量数据：

```php
private function getEvents(): \Generator
{
    while ($event = $this->getNextEvent()) {
        yield $event;
    }
}
```

### 性能监控

使用性能测试脚本监控关键指标：

```bash
# 运行性能测试
php tests/Performance/BinlogPerformanceTest.php
```

关注指标：
- 事件处理速度（ops/s）
- 内存使用量
- CPU使用率
- 网络延迟

## 调试技巧

### 启用调试模式

```php
// config/binlog.php
'log' => [
    'enabled' => true,
    'level' => 'debug', // 设置为debug级别
    'channel' => 'binlog',
],
```

### 使用Xdebug

在Docker环境中启用Xdebug：

```dockerfile
# docker/php/Dockerfile
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug
```

### 日志分析

查看详细的binlog事件日志：

```bash
# 实时查看日志
tail -f runtime/log/binlog.log

# 过滤特定事件
grep "insert" runtime/log/binlog.log

# 统计事件数量
grep -c "事件类型" runtime/log/binlog.log
```

## 贡献指南

### 提交Pull Request

1. Fork项目到你的GitHub账户
2. 创建功能分支：`git checkout -b feature/new-feature`
3. 提交更改：`git commit -am 'feat: 添加新功能'`
4. 推送分支：`git push origin feature/new-feature`
5. 创建Pull Request

### 代码审查清单

- [ ] 代码符合PSR-12规范
- [ ] 通过所有测试
- [ ] 添加了相应的测试用例
- [ ] 更新了相关文档
- [ ] 通过静态分析检查
- [ ] 性能测试通过

### 发布流程

1. 更新版本号和CHANGELOG
2. 创建Git标签：`git tag v1.0.0`
3. 推送标签：`git push origin v1.0.0`
4. GitHub Actions自动构建和发布

## 常见问题

### Q: 如何调试MySQL连接问题？

A: 检查以下几点：
- MySQL用户权限是否正确
- binlog是否启用
- 网络连接是否正常
- 配置参数是否正确

### Q: 如何处理大量事件？

A: 建议：
- 使用队列异步处理
- 合理设置事件过滤条件
- 监控内存使用情况
- 定期重启守护进程

### Q: 如何确保数据一致性？

A: 可以：
- 实现幂等性处理
- 使用事务保证原子性
- 定期进行一致性检查
- 实现故障恢复机制
