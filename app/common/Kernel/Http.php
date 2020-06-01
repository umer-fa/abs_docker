<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use App\Common\Kernel;
use App\Common\Kernel\Traits\NoDumpTrait;
use App\Common\Kernel\Traits\NotCloneableTrait;
use App\Common\Kernel\Traits\NotSerializableTrait;
use Comely\Http\Router;

/**
 * Class Http
 * @package App\Common\Kernel
 */
class Http
{
    /** @var Kernel */
    private Kernel $kernel;
    /** @var Router */
    private Router $router;
    /** @var Http\Remote */
    private Kernel\Http\Remote $remote;
    /** @var Http\Cookies */
    private Kernel\Http\Cookies $cookies;

    use NoDumpTrait;
    use NotCloneableTrait;
    use NotSerializableTrait;

    /**
     * Http constructor.
     * @param Kernel $k
     */
    public function __construct(Kernel $k)
    {
        $this->kernel = $k;
        $this->router = new Router();
        $this->remote = new Kernel\Http\Remote();
        $this->cookies = new Kernel\Http\Cookies();
    }

    /**
     * @return int|null
     */
    public function port(): ?int
    {
        $port = $_SERVER["SERVER_PORT"] ?? null;
        if (!is_null($port)) {
            $port = intval($port);
        }

        return $port;
    }

    /**
     * @return bool
     */
    public function https(): bool
    {
        return (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"]);
    }

    /**
     * @return Router
     */
    public function router(): Router
    {
        return $this->router;
    }

    /**
     * @return Kernel\Http\Remote
     */
    public function remote(): Kernel\Http\Remote
    {
        return $this->remote;
    }

    /**
     * @return Http\Cookies
     */
    public function cookies(): Kernel\Http\Cookies
    {
        return $this->cookies;
    }
}
