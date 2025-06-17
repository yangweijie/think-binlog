<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog\Compression;

/**
 * 压缩接口
 */
interface CompressionInterface
{
    /**
     * 压缩数据
     */
    public function compress(string $data): string;

    /**
     * 解压数据
     */
    public function decompress(string $data): string;

    /**
     * 获取压缩算法名称
     */
    public function getName(): string;

    /**
     * 获取压缩级别
     */
    public function getLevel(): int;

    /**
     * 设置压缩级别
     */
    public function setLevel(int $level): void;

    /**
     * 检查是否支持该压缩算法
     */
    public function isSupported(): bool;
}
