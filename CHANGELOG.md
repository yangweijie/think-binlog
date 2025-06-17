# 更新日志

本项目的所有重要更改都将记录在此文件中。

格式基于 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.0.0/)，
并且本项目遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [未发布]

### 新增
- 初始版本发布
- MySQL Binlog监听功能
- 后台守护进程支持
- 队列转发功能
- 事件订阅机制
- 命令行管理工具
- Docker开发环境
- 完整的测试套件
- CI/CD自动化流程

### 功能特性
- 支持MySQL 5.5+ 和 MariaDB
- 支持PHP 8.0+
- 支持ThinkPHP 8.0+
- 实时监听INSERT、UPDATE、DELETE事件
- 支持GTID和传统binlog位置
- 灵活的事件过滤机制
- 高性能事件处理
- 完整的错误处理和重试机制
- 内存使用监控和自动重启
- 详细的日志记录

### 组件
- `BinlogListener` - 核心监听器类
- `BinlogEvent` - 事件封装类
- `BinlogJob` - 队列任务处理
- `BinlogDaemon` - 守护进程管理
- `BinlogCommand` - 命令行工具
- `BinlogSubscriberInterface` - 订阅器接口
- `ExampleBinlogSubscriber` - 示例订阅器

### 配置选项
- MySQL连接配置
- Binlog监听配置
- 队列转发配置
- 守护进程配置
- 事件订阅配置
- 日志配置

### 命令行工具
- `php think binlog start` - 启动监听器
- `php think binlog stop` - 停止守护进程
- `php think binlog restart` - 重启守护进程
- `php think binlog status` - 查看状态
- `php think binlog listen` - 前台运行（调试模式）

### 测试覆盖
- 单元测试 (Pest)
- 功能测试
- 性能测试
- 代码质量检查 (PHP CS Fixer, PHPStan)
- CI/CD自动化测试

### 文档
- 完整的README文档
- 基础使用示例
- 高级使用示例
- 开发指南
- API文档

### 部署支持
- Docker开发环境
- Docker Compose配置
- 生产环境部署指南
- 系统服务配置示例

## [1.0.0] - 2024-01-01

### 新增
- 项目初始化
- 基础架构搭建

---

## 版本说明

### 版本号格式
本项目使用语义化版本号：`主版本号.次版本号.修订号`

- **主版本号**：不兼容的API修改
- **次版本号**：向下兼容的功能性新增
- **修订号**：向下兼容的问题修正

### 更新类型
- **新增** - 新功能
- **变更** - 对现有功能的变更
- **弃用** - 即将移除的功能
- **移除** - 已移除的功能
- **修复** - 问题修复
- **安全** - 安全相关的修复

### 发布周期
- **主版本**：根据需要发布，包含重大变更
- **次版本**：每月发布，包含新功能和改进
- **修订版本**：根据需要发布，主要是bug修复

### 支持政策
- 当前主版本：完全支持
- 前一个主版本：安全更新和重要bug修复
- 更早版本：不再支持

### 升级指南
每个主版本发布时，我们会提供详细的升级指南，包括：
- 重大变更说明
- 迁移步骤
- 兼容性说明
- 常见问题解答
