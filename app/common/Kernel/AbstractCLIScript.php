<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use App\Common\Kernel;
use Comely\CLI\Abstract_CLI_Script;
use Comely\CLI\CLI;

/**
 * Class AbstractCLIScript
 * @package App\Common\Kernel
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

    public function __construct(CLI $cli)
    {
        $this->app = Kernel::getInstance();
        parent::__construct($cli);
    }
}
