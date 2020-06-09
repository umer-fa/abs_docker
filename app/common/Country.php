<?php
declare(strict_types=1);

namespace App\Common;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\Countries;

/**
 * Class Country
 * @package App\Common
 */
class Country extends AbstractAppModel
{
    public const TABLE = Countries::NAME;

    /** @var string */
    public string $name;
    /** @var int */
    public int $status;
    /** @var string */
    public string $code;
    /** @var string */
    public string $codeShort;
    /** @var int */
    public int $dialCode;
}
