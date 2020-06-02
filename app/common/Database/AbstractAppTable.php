<?php
declare(strict_types=1);

namespace App\Common\Database;

use App\Common\Kernel;
use Comely\Database\Schema\AbstractDbTable;

/**
 * Class AbstractAppTable
 * @package App\Common\Database
 */
abstract class AbstractAppTable extends AbstractDbTable
{
    /** @var Kernel */
    protected Kernel $app;

    /**
     * @return void
     */
    public function onConstruct()
    {
        $this->app = Kernel::getInstance();
    }
}
