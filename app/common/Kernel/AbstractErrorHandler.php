<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use App\Common\Kernel;
use App\Common\Kernel\ErrorHandler\ErrorMsg;

/**
 * Class AbstractErrorHandler
 * @package App\Common\Kernel
 */
abstract class AbstractErrorHandler
{
    /** @var Kernel */
    protected Kernel $kernel;
    /** @var int */
    protected int $pathOffset;
    /** @var int */
    protected int $traceLevel;

    /**
     * AbstractErrorHandler constructor.
     * @param Kernel $k
     */
    final public function __construct(Kernel $k)
    {
        $this->kernel = $k;
        $this->pathOffset = strlen($k->dirs()->root()->path());
        $this->setTraceLevel(E_WARNING);

        set_error_handler([$this, "errorHandler"]);
        set_exception_handler([$this, "handleThrowable"]);
    }

    /**
     * @param int $lvl
     */
    final public function setTraceLevel(int $lvl): void
    {
        if ($lvl < 0 || $lvl > 0xffff) {
            throw new \InvalidArgumentException('Invalid trace level');
        }

        $this->traceLevel = $lvl;
    }

    /**
     * @return int
     */
    public function traceLevel(): int
    {
        return $this->traceLevel;
    }

    abstract public function handleError(ErrorMsg $err): bool;

    abstract public function handleThrowable(\Throwable $t): void;

    /**
     * @param int $type
     * @param string $message
     * @param string $file
     * @param int $line
     * @return bool
     */
    final public function errorHandler(int $type, string $message, string $file, int $line): bool
    {
        if (error_reporting() === 0) return false;

        $err = new ErrorMsg();
        $err->type = $type;
        $err->typeStr = $this->errorTypeStr($type);
        $err->message = $message;
        $err->file = $this->filePath($file);
        $err->line = $line;
        $err->triggered = true;

        return $this->handleError($err);
    }

    /**
     * @param string $path
     * @return string
     */
    final public function filePath(string $path): string
    {
        return trim(substr($path, $this->pathOffset), DIRECTORY_SEPARATOR);
    }

    /**
     * @param int $type
     * @return string
     */
    final public function errorTypeStr(int $type): string
    {
        switch ($type) {
            case 1:
                return "Fatal Error";
            case 2:
            case 512:
                return "Warning";
            case 4:
                return "Parse Error";
            case 8:
            case 1024:
                return "Notice";
            case 16:
                return "Core Error";
            case 32:
                return "Core Warning";
            case 64:
                return "Compile Error";
            case 128:
                return "Compile Warning";
            case 256:
                return "Error";
            case 2048:
                return "Strict";
            case 4096:
                return "Recoverable";
            case 8192:
            case 16384:
                return "Deprecated";
            default:
                return "Unknown";
        }
    }
}
