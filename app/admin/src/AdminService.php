<?php
declare(strict_types=1);

namespace App\Admin;

use App\Common\Kernel;
use Comely\Http\Router;

/**
 * Class AdminService
 * @package App\Admin
 */
class AdminService extends Kernel
{
    /** @var Router */
    private Router $router;

    /**
     * AdminService constructor.
     */
    protected function __construct()
    {
        parent::__construct();
        $this->router = new Router();
    }

    /**
     * @return Router
     */
    public function router(): Router
    {
        return $this->router;
    }
}
