<?php
declare(strict_types=1);

namespace App\Common\Kernel\CLI;

use App\Common\Kernel;
use Comely\CLI\Abstract_CLI_Script;
use Comely\CLI\CLI;

/**
 * Class AbstractCLIScript
 * @package App\Common\Kernel\CLI
 */
abstract class AbstractCLIScript extends Abstract_CLI_Script
{
    /** @var bool */
    public const DISPLAY_HEADER = true;
    /** @var bool */
    public const DISPLAY_LOADED_NAME = true;
    /** @var bool */
    public const DISPLAY_TRIGGERED_ERRORS = true;

    /** @var Kernel */
    protected Kernel $app;

    /**
     * AbstractCLIScript constructor.
     * @param CLI $cli
     */
    public function __construct(CLI $cli)
    {
        $this->app = Kernel::getInstance();
        parent::__construct($cli);
    }

    /**
     * @param \Throwable $t
     * @param int $tabIndex
     * @return string
     */
    protected function exceptionMsg2Str(\Throwable $t, int $tabIndex = 0): string
    {
        $tabs = str_repeat("\t", $tabIndex);
        return $tabs . "{red}[{/}{yellow}" . get_class($t) . "{/}{red}][{yellow}" . $t->getCode() . "{/}{red}] " .
            $t->getMessage();
    }

    /**
     * @return Kernel\ErrorHandler\Errors
     */
    protected function errors(): Kernel\ErrorHandler\Errors
    {
        return $this->app->errors();
    }

    /**
     * @param int $tabIndex
     */
    protected function printErrors(int $tabIndex = 0): void
    {
        $tabs = str_repeat("\t", $tabIndex);
        $errorLog = $this->app->errors();
        if ($errorLog->count()) {
            $this->print("");
            $this->print($tabs . "Caught triggered errors:");
            /** @var Kernel\ErrorHandler\ErrorMsg $errorMsg */
            foreach ($errorLog->all() as $errorMsg) {
                $this->print($tabs . sprintf('{red}[{b}%s{/}]{red} %s{/}', $errorMsg->typeStr, $errorMsg->message));
                $this->print($tabs . sprintf('⮤ in {magenta}%s{/} on line {magenta}%d{/}', $errorMsg->file, $errorMsg->line));
            }

            $this->print("");
        }
    }
}
