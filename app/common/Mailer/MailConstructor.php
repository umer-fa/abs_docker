<?php
declare(strict_types=1);

namespace App\Common\Mailer;

use App\Common\Database\Primary\MailsQueue;
use App\Common\Exception\MailConstructException;
use App\Common\Kernel;
use App\Common\Validator;
use Comely\Filesystem\Exception\PathException;
use Comely\Filesystem\File;

/**
 * Class MailConstructor
 * @package App\Common\Mailer
 */
class MailConstructor
{
    /** @var Kernel */
    private Kernel $app;
    /** @var Mailer */
    private Mailer $mailer;
    /** @var string */
    private string $to;
    /** @var string */
    private string $subject;
    /** @var string */
    private string $lang;
    /** @var string */
    private string $type;
    /** @var string */
    private string $body;
    /** @var string|null */
    private ?string $preHeader;
    /** @var bool */
    private bool $deleteOnSent;

    /**
     * MailConstructor constructor.
     * @param string $to
     * @param string $subject
     * @param string|null $lang
     * @throws MailConstructException
     */
    public function __construct(string $to, string $subject, string $lang = "en-us")
    {
        $this->app = Kernel::getInstance();
        $this->mailer = Mailer::getInstance();

        // E-mail address
        if (!Validator::isValidEmailAddress($to)) {
            throw new MailConstructException('Invalid e-mail address');
        } elseif (strlen($to) > 64) {
            throw new MailConstructException('E-mail address cannot exceed 64 bytes');
        }

        // Subject
        $subject = trim($subject);
        if (!Validator::isASCII($subject, "!-_+=@#$?<>|][}{\\/*.&%~`\"'")) {
            throw new MailConstructException('Subject contains an illegal character');
        } elseif (strlen($subject) > 64) {
            throw new MailConstructException('Subject cannot exceed 64 bytes');
        }

        // Lang
        $lang = strtolower($lang);
        if (!preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $lang)) {
            throw new MailConstructException('Invalid language');
        }

        $this->to = $to;
        $this->subject = $subject;
        $this->lang = $lang;
        $this->deleteOnSent = false;
    }

    /**
     * @throws MailConstructException
     * @throws \App\Common\Exception\AppConfigException
     * @throws \Comely\Database\Exception\ORM_Exception
     */
    public function addToQueue(): void
    {
        if (!isset($this->body)) {
            throw new MailConstructException('E-mail body is not set');
        }

        $mail = new QueuedMail();
        $mail->id = 0;
        $mail->lang = $this->lang;
        $mail->type = $this->type;
        $mail->status = "pending";
        $mail->attempts = 0;
        $mail->email = $this->to;
        $mail->subject = $this->subject;
        $mail->preHeader = $this->preHeader;
        $mail->set("compiled", $this->body);
        $mail->deleteOnSent = $this->deleteOnSent ? 1 : 0;
        $mail->timeStamp = time();
        $mail->set("checksum", $mail->checksum()->raw());

        $mail->query()->insert(function () {
            throw new MailConstructException('Failed to add e-mail message to queue');
        });
    }

    /**
     * @param string $msg
     * @return $this
     * @throws MailConstructException
     */
    public function preHeader(string $msg): self
    {
        if (strlen($msg) > MailsQueue::MAX_PRE_HEADER) {
            throw new MailConstructException(
                sprintf('Pre-header cannot exceed limit of %d bytes', MailsQueue::MAX_PRE_HEADER)
            );
        }

        if (!Validator::isASCII($msg, "!-_+=@#$?<>|][}{\\/*;,.&%~`\"'")) {
            throw new MailConstructException('Pre-header contains an illegal character');
        }

        $this->preHeader = $msg;
        return $this;
    }

    /**
     * @param string $message
     * @return $this
     * @throws MailConstructException
     */
    public function plainMessageFromText(string $message): self
    {
        $message = trim($message);
        $this->checkBody($message);
        $this->type = "text";
        $this->body = $message;
        return $this;
    }

    /**
     * @param string $filePath
     * @return $this
     * @throws MailConstructException
     */
    public function plainMessageFromFile(string $filePath): self
    {
        try {
            $textFile = new File($this->app->dirs()->root()->suffix($filePath));
            $message = $textFile->read();
        } catch (PathException $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new MailConstructException('Could not load text message file');
        }

        return $this->plainMessageFromText($message);
    }

    /**
     * @param string $html
     * @return $this
     * @throws MailConstructException
     */
    public function htmlMessageFromText(string $html): self
    {
        $html = trim($html);
        $this->checkBody($html);
        $this->type = "html";
        $this->body = $html;
        return $this;
    }

    /**
     * @param string $knitFile
     * @param array|null $data
     * @return $this
     * @throws MailConstructException
     * @throws \Comely\Knit\Exception\KnitException
     */
    public function htmlMessageFromTemplate(string $knitFile, ?array $data = null): self
    {
        $template = $this->mailer->knit()->template($knitFile);
        $template->assign("subject", $this->subject);
        $template->assign("preHeader", $this->preHeader);
        $template->assign("config", [
            "public" => $this->app->config()->public()->array()
        ]);

        if ($data) {
            foreach ($data as $key => $value) {
                $template->assign($key, $value);
            }
        }

        $this->htmlMessageFromText($template->knit());
        return $this;
    }

    /**
     * @param string $body
     * @throws MailConstructException
     */
    private function checkBody(string $body): void
    {
        if (strlen($body) > MailsQueue::MAX_COMPILED_BODY) {
            throw new MailConstructException(sprintf('Body cannot exceed %d bytes', MailsQueue::MAX_COMPILED_BODY));
        }
    }
}
