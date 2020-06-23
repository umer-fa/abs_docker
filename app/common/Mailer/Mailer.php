<?php
declare(strict_types=1);

namespace App\Common\Mailer;

use App\Common\Config\SMTPConfig;
use App\Common\Exception\AppException;
use App\Common\Kernel;
use Comely\DataTypes\Integers;
use Comely\Knit\Knit;
use Comely\Mailer\Agents\SMTP;

/**
 * Class Mailer
 * @package App\Common\Mailer
 */
class Mailer
{
    /** @var Mailer|null */
    protected static ?Mailer $instance = null;

    /**
     * @return static
     */
    public static function getInstance(): self
    {
        if (!static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /** @var Kernel */
    private Kernel $app;
    /** @var Knit */
    private Knit $knit;
    /** @var \Comely\Mailer\Mailer|null */
    private ?\Comely\Mailer\Mailer $smtpMailer = null;

    /**
     * Mailer constructor.
     * @throws \App\Common\Exception\AppDirException
     * @throws \Comely\Filesystem\Exception\PathException
     */
    private function __construct()
    {
        $this->app = Kernel::getInstance();

        // Prepare knit
        $this->knit = new Knit();
        $this->knit->dirs()->templates($this->app->dirs()->emails());
        $this->knit->dirs()->compiler($this->app->dirs()->knit()->dir("emails", true));
        $this->knit->modifiers()->registerDefaultModifiers();
    }

    /**
     * @return Knit
     */
    public function knit(): Knit
    {
        return $this->knit;
    }

    /**
     * @param string $to
     * @param string $subject
     * @return MailConstructor
     * @throws \App\Common\Exception\MailConstructException
     */
    public function compose(string $to, string $subject): MailConstructor
    {
        return new MailConstructor($to, $subject);
    }

    /**
     * @return \Comely\Mailer\Mailer
     * @throws AppException
     * @throws \Comely\Mailer\Exception\MailerException
     */
    public function smtpMailer(): \Comely\Mailer\Mailer
    {
        if ($this->smtpMailer) {
            return $this->smtpMailer;
        }

        $smtpConfig = SMTPConfig::getInstance();
        if (!$smtpConfig) {
            throw new AppException('SMTP mailer is disabled');
        }

        $smtpMailer = new \Comely\Mailer\Mailer();
        $smtpMailer->sender()
            ->name($smtpConfig->senderName)
            ->email($smtpConfig->senderEmail);

        $timeOut = Integers::Range($smtpConfig->timeOut, 1, 30) ? $smtpConfig->timeOut : 0;
        $smtpAgent = new SMTP($smtpConfig->hostname, $smtpConfig->port, $timeOut);
        $smtpAgent->serverName($smtpConfig->serverName);
        $smtpAgent->useTLS($smtpConfig->useTLS);
        $smtpAgent->authCredentials($smtpConfig->username, $smtpConfig->password);

        $smtpMailer->setAgent($smtpAgent);
        $this->smtpMailer = $smtpMailer;
        return $this->smtpMailer;
    }
}
