<?php
declare(strict_types=1);

namespace App\Common\Kernel\Http\Response;

/**
 * Class Messages
 * @package App\Common\Kernel\Http\Response
 */
class Messages
{
    /** @var array */
    private array $messages;

    /**
     * Messages constructor.
     */
    public function __construct()
    {
        $this->messages = [];
    }

    /**
     * @return array
     */
    public function array(): array
    {
        return $this->messages;
    }

    /**
     * @param string $type
     * @param string $message
     * @param string|null $param
     * @return $this
     */
    public function append(string $type, string $message, ?string $param = null): self
    {
        $this->messages[] = [
            "type" => $type,
            "message" => $message,
            "param" => $param
        ];

        return $this;
    }

    /**
     * @param string $message
     * @param string|null $param
     * @return $this
     */
    public function info(string $message, ?string $param = null): self
    {
        return $this->append("info", $message, $param);
    }

    /**
     * @param string $message
     * @param string|null $param
     * @return $this
     */
    public function warning(string $message, ?string $param = null): self
    {
        return $this->append("warning", $message, $param);
    }

    /**
     * @param string $message
     * @param string|null $param
     * @return $this
     */
    public function danger(string $message, ?string $param = null): self
    {
        return $this->append("danger", $message, $param);
    }

    /**
     * @param string $message
     * @param string|null $param
     * @return $this
     */
    public function success(string $message, ?string $param = null): self
    {
        return $this->append("success", $message, $param);
    }

    /**
     * @param string $message
     * @param string|null $param
     * @return $this
     */
    public function primary(string $message, ?string $param = null): self
    {
        return $this->append("primary", $message, $param);
    }

    /**
     * @param string $message
     * @param string|null $param
     * @return $this
     */
    public function secondary(string $message, ?string $param = null): self
    {
        return $this->append("secondary", $message, $param);
    }
}
