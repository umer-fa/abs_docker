<?php
declare(strict_types=1);

namespace App\Admin\Controllers\App;

use App\Admin\Controllers\AbstractAdminController;

/**
 * Class Docker
 * @package App\Admin\Controllers\App
 */
class Docker extends AbstractAdminController
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
        $this->page()->title('Cache Engine')->index(310, 30)
            ->prop("icon", "mdi mdi-docker");

        $this->breadcrumbs("Application", null, "mdi mdi-server-network");

        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        var_dump(socket_connect($sock, "db", 3306));
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        var_dump(socket_connect($sock, "engine", 3306));
        var_dump(fsockopen("api", 6000, $errno, $errstr));
        var_dump($errno, $errstr);
        var_dump(fsockopen("admin", 6000, $errno, $errstr));
        var_dump($errno, $errstr);

        $template = $this->template("app/docker.knit");
        $this->body($template);
    }
}
