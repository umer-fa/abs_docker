<?php
declare(strict_types=1);

namespace App\Admin\Controllers\App;

use Comely\Database\Database;

/**
 * Class Dashboard
 * @package App\Admin\Controllers
 */
class Umer extends AbstractAdminController
{
    public function adminCallback(): void
    {
    }

    /**
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
//        $this->app->db()->primary();
        echo 'hello';

    }
}
