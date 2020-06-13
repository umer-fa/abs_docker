<?php
declare(strict_types=1);

namespace App\Common\Config;

/**
 * Class SMTPConfig
 * @package App\Common\Config
 */
class SMTPConfig extends AbstractConfigObj
{
    public const DB_KEY = "app.smtpConfig";
    public const CACHE_KEY = "app.smtpConfig";
    public const CACHE_TTL = 86400;
    public const IS_ENCRYPTED = true;

    /** @var bool */
    public bool $status;
    /** @var string */
    public string $senderName;
    /** @var string */
    public string $senderEmail;
    /** @var string */
    public string $hostname;
    /** @var int */
    public int $port;
    /** @var int */
    public int $timeOut;
    /** @var bool */
    public bool $useTLS;
    /** @var string */
    public string $username;
    /** @var string */
    public string $password;
    /** @var string */
    public string $serverName;

    /**
     * SMTPConfig constructor.
     */
    public function __construct()
    {
        $this->status = false;
        $this->port = 587;
        $this->timeOut = 1;
        $this->useTLS = true;
    }
}
