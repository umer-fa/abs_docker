<?php
declare(strict_types=1);

namespace bin;

use App\Common\Kernel\AbstractCLIScript;

/**
 * Class deploy_db
 * @package bin
 */
class deploy_db extends AbstractCLIScript
{
    public function exec(): void
    {
        $this->print("DB Deployment Script");
    }
}
