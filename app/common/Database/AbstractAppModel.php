<?php
declare(strict_types=1);

namespace App\Common\Database;

use App\Common\Kernel;
use Comely\Database\Schema\ORM\Abstract_ORM_Model;

/**
 * Class AbstractAppModel
 * @package App\Common\Database
 */
abstract class AbstractAppModel extends Abstract_ORM_Model
{
    /** @var Kernel|null */
    protected ?Kernel $app = null;

    /**
     * @return void
     */
    public function onConstruct()
    {
        $this->app = Kernel::getInstance();
    }

    /**
     * @return void
     */
    public function onLoad()
    {
        $this->app = Kernel::getInstance();
    }

    /**
     * @return void
     */
    public function onSerialize()
    {
        $this->app = null;
    }

    /**
     * @return void
     */
    public function onUnserialize()
    {
        $this->app = Kernel::getInstance();
    }
}
