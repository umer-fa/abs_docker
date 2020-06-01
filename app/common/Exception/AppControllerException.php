<?php
declare(strict_types=1);

namespace App\Common\Exception;

/**
 * Class AppControllerException
 * @package App\Common\Exception
 */
class AppControllerException extends AppException
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
