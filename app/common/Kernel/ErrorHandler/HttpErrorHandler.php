<?php
declare(strict_types=1);

namespace App\Common\Kernel\ErrorHandler;

use App\Common\Exception\FatalErrorException;
use App\Common\Kernel\AbstractErrorHandler;

/**
 * Class HttpErrorHandler
 * @package App\Common\Kernel\ErrorHandler
 */
class HttpErrorHandler extends AbstractErrorHandler
{
    /**
     * @param ErrorMsg $err
     * @return bool
     */
    public function handleError(ErrorMsg $err): bool
    {
        $this->kernel->errors()->append($err);

        if (!in_array($err->type, [2, 8, 512, 1024, 2048, 8192, 16384])) {
            try {
                throw new FatalErrorException($err->message, $err->type);
            } catch (FatalErrorException $e) {
                $this->screen($e);
            }
        }

        return true;
    }

    /**
     * @param \Throwable $t
     */
    public function handleThrowable(\Throwable $t): void
    {
        $this->screen($t);
    }

    /**
     * @param \Throwable $t
     */
    public function screen(\Throwable $t): void
    {
        $sod = new Screen($this->kernel->isDebug(), $this->kernel->errors(), $this->pathOffset);
        $sod->send($t);
        exit;
    }
}
