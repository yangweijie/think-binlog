<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog\Compression;

use yangweijie\ThinkBinlog\Exception\BinlogException;

/**
 * 压缩管理器
 */
class CompressionManager
{
    /**
     * 注册的压缩器
     */
    private array $compressors = [];

    /**
     * 默认压缩器
     */
    private ?CompressionInterface $defaultCompressor = null;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->registerDefaultCompressors();
    }

    /**
     * 注册默认压缩器
     */
    private function registerDefaultCompressors(): void
    {
        $this->register(new GzipCompression());
        $this->register(new Lz4Compression());
        
        // 设置默认压缩器
        if ($this->isSupported('lz4')) {
            $this->setDefault('lz4');
        } elseif ($this->isSupported('gzip')) {
            $this->setDefault('gzip');
        }
    }

    /**
     * 注册压缩器
     */
    public function register(CompressionInterface $compressor): void
    {
        $this->compressors[$compressor->getName()] = $compressor;
    }

    /**
     * 获取压缩器
     */
    public function get(string $name): CompressionInterface
    {
        if (!isset($this->compressors[$name])) {
            throw new BinlogException("压缩器 '{$name}' 未注册");
        }

        return $this->compressors[$name];
    }

    /**
     * 设置默认压缩器
     */
    public function setDefault(string $name): void
    {
        $this->defaultCompressor = $this->get($name);
    }

    /**
     * 获取默认压缩器
     */
    public function getDefault(): ?CompressionInterface
    {
        return $this->defaultCompressor;
    }

    /**
     * 检查是否支持指定压缩算法
     */
    public function isSupported(string $name): bool
    {
        try {
            $compressor = $this->get($name);
            return $compressor->isSupported();
        } catch (BinlogException $e) {
            return false;
        }
    }

    /**
     * 获取所有支持的压缩算法
     */
    public function getSupportedCompressors(): array
    {
        $supported = [];
        foreach ($this->compressors as $name => $compressor) {
            if ($compressor->isSupported()) {
                $supported[] = $name;
            }
        }
        return $supported;
    }

    /**
     * 压缩数据
     */
    public function compress(string $data, ?string $algorithm = null): array
    {
        $compressor = $algorithm ? $this->get($algorithm) : $this->getDefault();
        
        if (!$compressor) {
            throw new BinlogException('没有可用的压缩器');
        }

        if (!$compressor->isSupported()) {
            throw new BinlogException("压缩算法 '{$compressor->getName()}' 不支持");
        }

        $originalSize = strlen($data);
        $compressedData = $compressor->compress($data);
        $compressedSize = strlen($compressedData);

        return [
            'algorithm' => $compressor->getName(),
            'level' => $compressor->getLevel(),
            'original_size' => $originalSize,
            'compressed_size' => $compressedSize,
            'compression_ratio' => $originalSize > 0 ? round($compressedSize / $originalSize, 4) : 0,
            'data' => $compressedData,
        ];
    }

    /**
     * 解压数据
     */
    public function decompress(string $data, string $algorithm): string
    {
        $compressor = $this->get($algorithm);
        
        if (!$compressor->isSupported()) {
            throw new BinlogException("压缩算法 '{$algorithm}' 不支持");
        }

        return $compressor->decompress($data);
    }

    /**
     * 自动选择最佳压缩算法
     */
    public function getBestCompressor(string $data): CompressionInterface
    {
        $bestCompressor = null;
        $bestRatio = 1.0;
        $testSize = min(1024, strlen($data)); // 只测试前1KB数据
        $testData = substr($data, 0, $testSize);

        foreach ($this->compressors as $compressor) {
            if (!$compressor->isSupported()) {
                continue;
            }

            try {
                $compressed = $compressor->compress($testData);
                $ratio = strlen($compressed) / strlen($testData);
                
                if ($ratio < $bestRatio) {
                    $bestRatio = $ratio;
                    $bestCompressor = $compressor;
                }
            } catch (\Exception $e) {
                // 忽略压缩失败的算法
                continue;
            }
        }

        return $bestCompressor ?: $this->getDefault();
    }

    /**
     * 获取压缩统计信息
     */
    public function getStats(): array
    {
        $stats = [];
        foreach ($this->compressors as $name => $compressor) {
            $stats[$name] = [
                'supported' => $compressor->isSupported(),
                'level' => $compressor->getLevel(),
            ];
        }
        return $stats;
    }
}
