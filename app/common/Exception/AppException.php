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
        return new self($message, self::MODEL_NOT_FOUND);
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
