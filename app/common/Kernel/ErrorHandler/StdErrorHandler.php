<?php
declare(strict_types=1);

namespace App\Common\Kernel\ErrorHandler;

use App\Common\Kernel\AbstractErrorHandler;

/**
 * Class StdErrorHandler
 * @package App\Common\Kernel\ErrorHandler
 */
class StdErrorHandler extends AbstractErrorHandler
{
    /**
     * @param ErrorMsg $err
     * @return bool
     */
    public function handleError(ErrorMsg $err): bool
    {
        $buffer[] = "";
        $buffer[] = sprintf("\e[36m%s\e[0m", date("d-m-Y H:i"));
        $buffer[] = sprintf("\e[33mError:\e[0m \e[1m\e[31m%s\e[0m", $err->typeStr);
        $buffer[] = sprintf("\e[33mMessage:\e[0m \e[33m%s\e[0m", $err->message);
        $buffer[] = sprintf("\e[33mFile:\e[0m %s", $err->file);
        $buffer[] = sprintf("\e[33mLine:\e[0m \e[33m%d\e[0m", $err->line);

        $includeTrace = true;
        if (in_array($err->type, [2, 8, 512, 1024, 2048, 8192, 16384])) {
            $includeTrace = false;
            if ($this->kernel->errorHandler()->traceLevel() >= $err->type) {
                $includeTrace = true;
            }
        }

        if ($includeTrace) {
            $this->bufferTrace($buffer, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
            $buffer[] = "";
        }

        $this->writeBuffer($buffer, $includeTrace);

        return true;
    }

    /**
     * @param \Throwable $t
     */
    public function handleThrowable(\Throwable $t): void
    {
        $buffer[] = "";
        $buffer[] = str_repeat(".", 10);
        $buffer[] = "";
        $buffer[] = sprintf('\e[36m%s\e[0m', date("d-m-Y H:i"));
        $buffer[] = sprintf('\e[33mCaught:\e[0m \e[1m\e[31m%s\e[0m', get_class($t));
        $buffer[] = sprintf('\e[33mMessage:\e[0m \e[33m%s\e[0m', $t->getMessage());
        $buffer[] = sprintf('\e[33mFile:\e[0m %s', $t->getFile());
        $buffer[] = sprintf('\e[33mLine:\e[0m \e[33m%d\e[0m', $t->getLine());
        $this->bufferTrace($buffer, $t->getTrace());
        $buffer[] = "";
        $buffer[] = str_repeat(".", 10);
        $this->writeBuffer($buffer, true);
    }

    /**
     * @param array $buffer
     * @param bool $terminate
     */
    private function writeBuffer(array $buffer, bool $terminate = false): void
    {
        fwrite(STDERR, implode(PHP_EOL, $buffer));
        if ($terminate) {
            exit;
        }
    }

    /**
     * @param array $buffer
     * @param array $trace
     */
    private function bufferTrace(array &$buffer, array $trace): void
    {
        $buffer[] = "\e[33mBacktrace:\e[0m";
        $buffer[] = "┬";
        foreach ($trace as $sf) {
            $function = $sf["function"] ?? null;
            $class = $sf["class"] ?? null;
            $type = $sf["type"] ?? null;
            $file = $sf["file"] ?? null;
            $line = $sf["line"] ?? null;

            if ($file && is_string($file) && $line) {
                $method = $function;
                if ($class && $type) {
                    $method = $class . $type . $function;
                }

                $traceString = sprintf('"\e[4m\e[36m%s\e[0m" on line # \e[4m\e[33m%d\e[0m', $file, $line);
                if ($method) {
                    $traceString = sprintf('Method \e[4m\e[35m%s\e[0m in file ', $method) . $traceString;
                }

                $buffer[] = "├─ " . $traceString;
            }
        }
    }
}
