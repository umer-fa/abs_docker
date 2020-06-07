<?php
declare(strict_types=1);

namespace App\Common\Kernel\Http;

use App\Common\Kernel\Http\Controllers\GenericHttpController;

/**
 * Class Page
 * @package Comely\App\Http
 */
class Page
{
    /** @var GenericHttpController */
    private GenericHttpController $cont;
    /** @var array */
    private array $props;
    /** @var array */
    private array $assets;

    /**
     * Page constructor.
     * @param GenericHttpController $controller
     */
    public function __construct(GenericHttpController $controller)
    {
        $this->cont = $controller;
        $this->assets = [];
        $this->props = [
            "title" => null,
            "language" => null,
            "index" => $this->index(0),
            "root" => $controller->request()->url()->root(),
            "token" => null
        ];
    }

    /**
     * @param int|null $ttl
     * @param bool $ipSensitive
     * @return $this
     * @throws \App\Common\Exception\XSRF_Exception
     * @throws \Comely\Utils\Security\Exception\PRNG_Exception
     */
    public function set_XSRF_Token(?int $ttl = null, bool $ipSensitive = true): self
    {
        $xsrf = $this->cont->xsrf();
        $token = $xsrf->token(true) ?? $xsrf->generate($ttl, $ipSensitive);
        $this->props["token"] = bin2hex($token);
        return $this;
    }

    /**
     * @param string $prop
     * @param $value
     * @return Page
     */
    public function prop(string $prop, $value): self
    {
        if (!preg_match('/^[\w.]{2,32}$/', $prop)) {
            throw new \InvalidArgumentException('Invalid page property name');
        }

        if (in_array(strtolower($prop), ["title", "index", "root", "token", "assets"])) {
            throw new \OutOfBoundsException(sprintf('Cannot override "%s" page property', $prop));
        }

        $valueType = gettype($value);
        switch ($valueType) {
            case "string":
            case "integer":
            case "boolean":
            case "double":
            case "NULL":
                $this->props[$prop] = $value;
                break;
            default:
                throw new \UnexpectedValueException(
                    sprintf('Value of type "%s" cannot be stored as page prop', $valueType)
                );
        }

        return $this;
    }

    /**
     * @param string $title
     * @return Page
     */
    public function title(string $title): self
    {
        $this->props["title"] = $title;
        return $this;
    }

    /**
     * @param int $a
     * @param int $b
     * @param int $c
     * @return Page
     */
    public function index(int $a, int $b = 0, int $c = 0): self
    {
        $this->props["index"] = ["a" => $a, "b" => $b, "c" => $c];
        return $this;
    }

    /**
     * @param string $uri
     * @return Page
     */
    public function css(string $uri): self
    {
        $this->assets[] = [
            "type" => "css",
            "uri" => $uri
        ];

        return $this;
    }

    /**
     * @param string $uri
     * @return Page
     */
    public function js(string $uri): self
    {
        $this->assets[] = [
            "type" => "js",
            "uri" => $uri
        ];

        return $this;
    }

    /**
     * @return array
     */
    public function array(): array
    {
        return array_merge($this->props, ["assets" => $this->assets]);
    }
}
