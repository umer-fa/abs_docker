<?php
declare(strict_types=1);

namespace App\Common\Users;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\Users;

/**
 * Class User
 * @package App\Common\Users
 */
class User extends AbstractAppModel
{
    public const TABLE = Users::NAME;
    public const SERIALIZABLE = true;

    public const CACHE_KEY = 'user_%d';
    public const CACHE_KEY_EMAIL = 'user_em_%s';
    public const CACHE_TTL = 3600;

    /** @var int */
    public int $id;
    /** @var string */
    public string $status;
    /** @var string */
    public string $firstName;
    /** @var string */
    public string $lastName;
    /** @var string */
    public string $email;
    /** @var int */
    public int $isEmailVerified;
    /** @var string */
    public string $country;
    /** @var null|string */
    public ?string $phoneSms = null;
    /** @var int */
    public int $joinStamp;
    /** @var int */
    public int $timeStamp;
}
