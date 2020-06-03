<?php
declare(strict_types=1);

namespace App\Common\Kernel\ErrorHandler;

use App\Common\Kernel;

/**
 * Class Errors
 * @package App\Common\Kernel\ErrorHandler
 */
class Errors
{
    /** @var Kernel */
    private Kernel $kernel;
    /** @var ErrorLog */
    private ErrorLog $triggered;
    /** @var ErrorLog */
    private ErrorLog $logged;

    /**
     * @param \Exception $e
     * @param int $type
     */
    public static function Exception2Error(\Exception $e, int $type = E_USER_WARNING): void
    {
        trigger_error(self::Exception2String($e), $type);
    }

    /**
     * @param \Exception $e
     * @return string
     */
    public static function Exception2String(\Exception $e): string
    {
        return sprintf('[%s][#%s] %s', get_class($e), $e->getCode(), $e->getMessage());
    }

    /**
     * Errors constructor.
     * @param Kernel $k
     */
    final public function __construct(Kernel $k)
    {
        $this->kernel = $k;
        $this->triggered = new ErrorLog();
        $this->logged = new ErrorLog();
    }

    /**
     * Flush error logs
     */
    final public function flush(): void
    {
        $this->triggered->flush();
        $this->logged->flush();
    }

    /**
     * @param $message
     * @param int $type
     * @param int $traceLevel
     */
    final public function trigger($message, int $type = E_USER_NOTICE, int $traceLevel = 1): void
    {
        $errorMsg = $this->prepareErrorMsg($message, $type, $traceLevel);
        $errorMsg->triggered = true;
        $this->kernel->errorHandler()->handleError($errorMsg);
    }

    /**
     * @param $message
     * @param int $type
     * @param int $traceLevel
     */
    final public function triggerIfDebug($message, int $type = E_USER_NOTICE, int $traceLevel = 1): void
    {
        $errorMsg = $this->prepareErrorMsg($message, $type, $traceLevel);
        $errorMsg->triggered = $this->kernel->isDebug();
        $this->kernel->errorHandler()->handleError($errorMsg);
    }

    /**
     * @param $message
     * @param int $type
     * @param int $traceLevel
     * @return ErrorMsg
     */
    final private function prepareErrorMsg($message, int $type = E_USER_NOTICE, int $traceLevel = 1): ErrorMsg
    {
        if (is_object($message) && $message instanceof \Exception) {
            $message = self::Exception2String($message);
        }

        if (!is_string($message)) {
            throw new \InvalidArgumentException(sprintf('Cannot create ErrorMsg from arg type "%s"', gettype($message)));
        }

        if (!in_array($type, [E_USER_NOTICE, E_USER_WARNING])) {
            throw new \InvalidArgumentException('Invalid triggered error type');
        }

        $errHandler = $this->kernel->errorHandler();
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $error = new ErrorMsg();
        $error->type = $type;
        $error->typeStr = $errHandler->errorTypeStr($type);
        $error->message = $message;
        $error->file = $errHandler->filePath($trace[$traceLevel]["file"] ?? "");
        $error->line = intval($trace[$traceLevel]["line"] ?? -1);
        return $error;
    }

    /**
     * @param ErrorMsg $error
     */
    final public function append(ErrorMsg $error): void
    {
        if (!is_bool($error->triggered)) {
            throw new \InvalidArgumentException('ErrorMsg object prop "triggered" must be of type boolean');
        }

        if ($error->triggered) {
            $this->triggered->append($error);
        } else {
            $this->logged->append($error);
        }
    }

    /**
     * @return ErrorLog
     */
    final public function triggered(): ErrorLog
    {
        return $this->triggered;
    }

    /**
     * @return ErrorLog
     */
    final public function logged(): ErrorLog
    {
        return $this->logged;
    }

    /**
     * @return int
     */
    final public function count(): int
    {
        return $this->triggered->count() + $this->logged->count();
    }

    /**
     * @return array
     */
    final public function all(): array
    {
        return array_merge($this->triggered->array(), $this->logged->array());
    }
}
