<?php
declare(strict_types=1);

namespace App\Common\Admin;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\Administrators;

/**
 * Class Administrator
 * @package App\Common\Admin
 */
class Administrator extends AbstractAppModel
{
    public const TABLE = Administrators::NAME;
    public const SERIALIZABLE = false;

    /** @var int */
    public int $id;
    /** @var string */
    public string $checksum;
    /** @var int */
    public int $status;
    /** @var string */
    public string $email;
    /** @var string|null */
    public ?string $phone = null;
    /** @var string|null */
    public ?string $authToken = null;
    /** @var int */
    public int $timeStamp;
}
