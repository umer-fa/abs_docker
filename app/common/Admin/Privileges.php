<?php
declare(strict_types=1);

namespace App\Common\Admin;

use App\Common\Kernel;

/**
 * Class Privileges
 * @package App\Common\Admin
 */
class Privileges
{
    /** @var int */
    private int $id;
    /** @var bool */
    public bool $viewConfig = false;
    /** @var bool */
    public bool $editConfig = false;
    /** @var bool */
    public bool $viewAdmins = false;
    /** @var bool */
    public bool $viewAdminsLogs = false;
    /** @var bool */
    public bool $viewUsers = false;
    /** @var bool */
    public bool $manageUsers = false;
    /** @var bool */
    public bool $viewAPIQueriesPayload = false;

    /**
     * Privileges constructor.
     * @param Administrator $admin
     */
    public function __construct(Administrator $admin)
    {
        $this->id = $admin->id;
    }

    /**
     * @return int
     */
    public function adminId(): int
    {
        return $this->id;
    }

    /**
     * @return bool
     */
    public function root(): bool
    {
        return in_array($this->id, Kernel::ROOT_ADMINISTRATORS);
    }
}
