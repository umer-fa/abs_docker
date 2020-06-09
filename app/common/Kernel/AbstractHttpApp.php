<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use App\Common\Kernel;
use Comely\Http\Router;

/**
 * Class AbstractHttpApp
 * @package App\Common\Kernel
 */
abstract class AbstractHttpApp extends Kernel
{
    /** @var Kernel\Http */
    private Kernel\Http $http;

    /**
     * AbstractHttpApp constructor.
     * @throws \App\Common\Exception\AppConfigException
     * @throws \App\Common\Exception\AppDirException
     * @throws \Comely\Yaml\Exception\ParserException
     */
    protected function __construct()
    {
        parent::__construct();
        $this->http = new Kernel\Http($this);
    }

    /**
     * @return Router
     */
    public function router(): Router
    {
        return $this->http->router();
    }

    /**
     * @return Kernel\Http
     */
    public function http(): Kernel\Http
    {
        return $this->http;
    }
}
