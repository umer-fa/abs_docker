<?php
declare(strict_types=1);

namespace App\Common\Kernel\ErrorHandler;

/**
 * Class ErrorMsg
 * @package App\Common\Kernel\ErrorHandler
 */
class ErrorMsg
{
    /** @var int Error type */
    public int $type;
    /** @var string Error type in string */
    public string $typeStr;
    /** @var string Error message */
    public string $message;
    /** @var string File path */
    public string $file;
    /** @var int Line no. */
    public int $line;
    /** @var bool Was it triggered or just logged? */
    public bool $triggered = false;
    /** @var float|string Timestamp of error */
    public float $timeStamp;

    /**
     * ErrorMsg constructor.
     */
    public function __construct()
    {
        $this->triggered = false;
        $this->timeStamp = microtime(true);
    }
}
