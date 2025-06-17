<?php

declare(strict_types=1);

namespace yangweijie\ThinkBinlog\Memory;

/**
 * 内存友好的字符串缓冲区
 */
class StringBuffer
{
    /**
     * 缓冲区数据
     */
    private array $buffer = [];

    /**
     * 当前长度
     */
    private int $length = 0;

    /**
     * 添加字符串
     */
    public function append(string $str): self
    {
        $this->buffer[] = $str;
        $this->length += strlen($str);
        return $this;
    }

    /**
     * 添加行
     */
    public function appendLine(string $str = ''): self
    {
        return $this->append($str . "\n");
    }

    /**
     * 预添加字符串
     */
    public function prepend(string $str): self
    {
        array_unshift($this->buffer, $str);
        $this->length += strlen($str);
        return $this;
    }

    /**
     * 获取字符串
     */
    public function toString(): string
    {
        return implode('', $this->buffer);
    }

    /**
     * 获取长度
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * 检查是否为空
     */
    public function isEmpty(): bool
    {
        return $this->length === 0;
    }

    /**
     * 清空缓冲区
     */
    public function clear(): self
    {
        $this->buffer = [];
        $this->length = 0;
        return $this;
    }

    /**
     * 截取字符串
     */
    public function substring(int $start, ?int $length = null): string
    {
        $str = $this->toString();
        return $length === null ? substr($str, $start) : substr($str, $start, $length);
    }

    /**
     * 替换字符串
     */
    public function replace(string $search, string $replace): self
    {
        $str = $this->toString();
        $str = str_replace($search, $replace, $str);
        $this->clear();
        return $this->append($str);
    }

    /**
     * 转换为字符串
     */
    public function __toString(): string
    {
        return $this->toString();
    }
}
