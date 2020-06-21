<?php
declare(strict_types=1);

namespace App\Common\Admin;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\Administrators\Logs;

/**
 * Class Log
 * @package App\Common\Admin
 */
class Log extends AbstractAppModel
{
    public const TABLE = Logs::NAME;
    public const SERIALIZABLE = false;

    /** @var int */
    public int $id;
    /** @var int */
    public int $admin;
    /** @var string|null */
    public ?string $flags = null;
    /** @var string|null */
    public ?string $controller = null;
    /** @var string */
    public string $log;
    /** @var string */
    public string $ipAddress;
    /** @var int */
    public int $timeStamp;
}
