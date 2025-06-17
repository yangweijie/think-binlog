<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog\Compression;

use yangweijie\ThinkBinlog\Exception\BinlogException;

/**
 * Gzip压缩实现
 */
class GzipCompression implements CompressionInterface
{
    /**
     * 压缩级别 (1-9)
     */
    private int $level = 6;

    /**
     * 构造函数
     */
    public function __construct(int $level = 6)
    {
        $this->setLevel($level);
    }

    /**
     * 压缩数据
     */
    public function compress(string $data): string
    {
        if (!$this->isSupported()) {
            throw new BinlogException('Gzip扩展未安装');
        }

        $compressed = gzcompress($data, $this->level);
        if ($compressed === false) {
            throw new BinlogException('Gzip压缩失败');
        }

        return $compressed;
    }

    /**
     * 解压数据
     */
    public function decompress(string $data): string
    {
        if (!$this->isSupported()) {
            throw new BinlogException('Gzip扩展未安装');
        }

        $decompressed = gzuncompress($data);
        if ($decompressed === false) {
            throw new BinlogException('Gzip解压失败');
        }

        return $decompressed;
    }

    /**
     * 获取压缩算法名称
     */
    public function getName(): string
    {
        return 'gzip';
    }

    /**
     * 获取压缩级别
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * 设置压缩级别
     */
    public function setLevel(int $level): void
    {
        if ($level < 1 || $level > 9) {
            throw new BinlogException('Gzip压缩级别必须在1-9之间');
        }
        $this->level = $level;
    }

    /**
     * 检查是否支持该压缩算法
     */
    public function isSupported(): bool
    {
        return extension_loaded('zlib');
    }
}
