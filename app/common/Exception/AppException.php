<?php
declare(strict_types=1);

namespace App\Common\Exception;

/**
 * Class AppException
 * @package App\Common\Exception
 */
class AppException extends \Exception
{
    /** @var string|null */
    private ?string $param = null;

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
