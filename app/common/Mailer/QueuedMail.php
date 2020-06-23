<?php
declare(strict_types=1);

namespace App\Common\Mailer;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\MailsQueue;
use App\Common\Exception\AppException;
use Comely\DataTypes\Buffer\Binary;

/**
 * Class QueuedMail
 * @package App\Common\Mailer
 */
class QueuedMail extends AbstractAppModel
{
    public const TABLE = MailsQueue::NAME;
    public const SERIALIZABLE = false;

    /** @var int */
    public int $id;
    /** @var string */
    public string $lang;
    /** @var string */
    public string $type;
    /** @var string */
    public string $status;
    /** @var string|null */
    public ?string $lastError = null;
    /** @var int */
    public int $attempts;
    /** @var string */
    public string $email;
    /** @var string */
    public string $subject;
    /** @var int */
    public int $deleteOnSent = 0;
    /** @var int */
    public int $timeStamp;
    /** @var int|null */
    public ?int $lastAttempt = null;

    /** @var bool|null */
    public ?bool $_checksumVerified = null;

    /**
     * @throws AppException
     */
    public function beforeQuery()
    {
        if (is_string($this->lastError)) {
            if (strlen($this->lastError) > 255) {
                $this->lastError = substr($this->lastError, 0, 255);
            }
        }

        if (strlen($this->private("compiled")) > MailsQueue::MAX_COMPILED_BODY) {
            throw new AppException(
                sprintf('Message complied body exceeds limit of %d bytes', MailsQueue::MAX_COMPILED_BODY)
            );
        }
    }

    /**
     * @return Binary
     * @throws \App\Common\Exception\AppConfigException
     */
    public function checksum(): Binary
    {
        $raw = sprintf(
            '%s:%s:%s:%s:%s:%d',
            strtolower($this->lang),
            strtolower($this->type),
            trim(strtolower($this->email)),
            trim(strtolower(md5(strtolower($this->subject)))),
            trim(strtolower(md5(strtolower($this->private("compiled"))))),
            $this->timeStamp
        );

        return $this->app->ciphers()->secondary()->pbkdf2("sha1", $raw, 0x1a);
    }

    /**
     * @throws AppException
     * @throws \App\Common\Exception\AppConfigException
     */
    public function validate(): void
    {
        $this->_checksumVerified = false;
        if ($this->private("checksum") !== $this->checksum()->raw()) {
            throw new AppException('Message checksum failed');
        }

        $this->_checksumVerified = true;
    }
}
