<?php
declare(strict_types=1);

namespace App\Common\Kernel\Http;

use Comely\Http\Request;

/**
 * Class Remote
 * @package App\Common\Kernel\Http
 */
class Remote
{
    /** @var null|string */
    public ?string $ipAddress = null;
    /** @var null|int */
    public ?int $port = null;
    /** @var null|string */
    public ?string $origin = null;
    /** @var null|string */
    public ?string $agent = null;

    /**
     * Remote constructor.
     */
    public function __construct()
    {
        $this->set(null);
    }

    /**
     * @param string $method
     * @param $args
     */
    public function __call(string $method, $args)
    {
        switch ($method) {
            case "set":
                $this->set($args[0]);
                return;
        }

        throw new \DomainException('Cannot call inaccessible method');
    }

    /**
     * @param Request $req
     */
    private function set(?Request $req = null): void
    {
        $this->ipAddress = null;
        $this->origin = null;
        $this->agent = null;

        if ($req) {
            // CF IP Address
            if ($req->headers()->has("cf-connecting-ip")) {
                $this->ipAddress = $req->headers()->get("cf-connecting_-ip");
            }

            // XFF
            if (!$this->ipAddress && $req->headers()->has("x-forwarded-for")) {
                $xff = explode(",", $req->headers()->get("x-forwarded-for"));
                $xff = preg_replace('/[^a-f0-9.:]/', '', strtolower($xff[0]));
                $this->ipAddress = trim($xff);
            }

            // Other Headers
            $this->origin = $req->headers()->get("referer");
            $this->agent = $req->headers()->get("user-agent");
        }

        // IP Address
        if (!$this->ipAddress) {
            $this->ipAddress = $_SERVER["REMOTE_ADDR"] ?? null;
        }

        // Port
        $port = $_SERVER["REMOTE_PORT"] ?? null;
        if (!is_null($port)) {
            $this->port = intval($port);
        }
    }
}
