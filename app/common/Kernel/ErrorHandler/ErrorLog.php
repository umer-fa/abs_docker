<?php
declare(strict_types=1);

namespace App\Common\Kernel\ErrorHandler;

/**
 * Class ErrorLog
 * @package App\Common\Kernel\ErrorHandler
 */
class ErrorLog implements \Iterator, \Countable
{
    /** @var array */
    private array $errs = [];
    /** @var int */
    private int $count = 0;
    /** @var int */
    private int $pos = 0;

    /**
     * @param ErrorMsg $err
     */
    public function append(ErrorMsg $err): void
    {
        $this->errs[] = $err;
        $this->count++;
    }

    /**
     * @return array
     */
    public function array(): array
    {
        return $this->errs;
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        $this->errs = [];
        $this->count = 0;
    }

    /**
     * @return ErrorMsg
     */
    public function current(): ErrorMsg
    {
        return $this->errs[$this->pos];
    }

    /**
     * @return int
     */
    public function key(): int
    {
        return $this->pos;
    }

    /**
     * @return bool
     */
    public function valid(): bool
    {
        return isset($this->errs[$this->pos]);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * @return void
     */
    public function next(): void
    {
        ++$this->pos;
    }

    /**
     * @return void
     */
    public function rewind(): void
    {
        $this->pos = 0;
    }
}
