<?php
declare(strict_types=1);

namespace App\Common\Users;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\Users\Logs;

/**
 * Class Log
 * @package App\Common\Users
 * @property null|string $dateTime
 */
class Log extends AbstractAppModel
{
    public const TABLE = Logs::NAME;
    public const SERIALIZABLE = false;

    /** @var int */
    public int $id;
    /** @var int */
    public int $user;
    /** @var null|string */
    public ?string $flags = null;
    /** @var null|string */
    public ?string $controller = null;
    /** @var string */
    public string $log;
    /** @var null|string|array */
    public $data;
    /** @var string */
    public string $ipAddress;
    /** @var int */
    public int $timeStamp;

    /**
     * @return void
     */
    public function onLoad(): void
    {
        parent::onLoad();
        $this->dateTime = date("jS M Y H:i", $this->timeStamp);

        if (is_string($this->data)) {
            $this->data = json_decode(base64_decode($this->data), true);
        }
    }
}
