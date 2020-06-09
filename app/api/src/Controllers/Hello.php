<?php
declare(strict_types=1);

namespace App\API\Controllers;

/**
 * Class Hello
 * @package App\API\Controllers
 */
class Hello extends AbstractAPIController
{
    /**
     * @return void
     */
    public function apiCallback(): void
    {
        $this->status(true);
        $this->response()->set("message", sprintf('Greetings; Your IP address is %s', $this->ipAddress));
    }

    /**
     * @return void
     */
    public function post(): void
    {

    }

    /**
     * @return void
     */
    public function get(): void
    {

    }
}
