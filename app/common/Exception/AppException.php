<?php
declare(strict_types=1);

namespace App\Common\Exception;

/**
 * Class AppException
 * @package App\Common\Exception
 */
class AppException extends \Exception
{
    public const MODEL_NOT_FOUND = 0x2710;

    /** @var string|null */
    private ?string $param = null;

    /**
     * @param string $message
     * @return AppException
     */
    public static function ModelNotFound(string $message): AppException
    {
        return new static($message, self::MODEL_NOT_FOUND);
    }

    /**
     * @param string $param
     * @param string $message
     * @param int|null $code
     * @return static
     */
    public static function Param(string $param, string $message, ?int $code = null): self
    {
        return (new static($message, (int)$code))->setParam($param);
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setParam(string $name): self
    {
        $this->param = $name;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getParam(): ?string
    {
        return $this->param;
    }
}
