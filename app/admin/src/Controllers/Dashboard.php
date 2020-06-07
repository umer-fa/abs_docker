<?php
declare(strict_types=1);

namespace App\Admin\Controllers;

/**
 * Class Dashboard
 * @package App\Admin\Controllers
 */
class Dashboard extends AbstractAdminController
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
        $this->page()->title('Dashboard')->index(100);

        $template = $this->template("dashboard.knit");
        $this->body($template);
    }
}
