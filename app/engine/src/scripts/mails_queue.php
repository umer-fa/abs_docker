<?php
declare(strict_types=1);

namespace bin;

use App\Common\Database\Primary\MailsQueue;
use App\Common\Exception\AppConfigException;
use App\Common\Exception\AppException;
use App\Common\Kernel\CLI\AbstractCronScript;
use App\Common\Mailer\QueuedMail;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema;
use Comely\Mailer\Agents\SMTP;
use Comely\Mailer\Exception\SMTP_Exception;
use Comely\Utils\Time\Time;

/**
 * Class mails_queue
 * @package bin
 */
class mails_queue extends AbstractCronScript
{
    /** @var string */
    public const SEMPAHORE_LOCK = "cron_mailsQueue";
    /** @var int Maximum number of attempts to send email to, 1 per each execution, before its set to "failed" */
    private const MAX_ATTEMPTS = 10;
    /** @var int Maximum number of pending/queued mails to fetch in 1 go */
    private const FETCH_LIMIT = 100;
    /** @var int Maximum number of seconds to keep execution alive for */
    private const TIME_LIMIT = 50;

    /** @var \Comely\Mailer\Mailer */
    private \Comely\Mailer\Mailer $smtpAgent;


    /**
     * @throws AppException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\ORM_Exception
     * @throws \Comely\Database\Exception\ORM_ModelQueryException
     * @throws \Comely\Database\Exception\SchemaTableException
     * @throws \Comely\Mailer\Exception\MailerException
     */
    public function execCron(): void
    {
        $db = $this->app->db()->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\MailsQueue');

        $mailer = $this->app->mailer();
        $this->smtpAgent = $mailer->smtpMailer();
        /** @var SMTP $smtpConfiguredAgent */
        $smtpConfiguredAgent = $this->smtpAgent->getAgent();
        $smtpConfiguredAgent->keepAlive(true); // Keep alive!
        $startTimeStamp = time();
        $this->inline("Getting pending queued emails... ");

        try {
            $queuedMails = MailsQueue::Find()
                ->query(sprintf("WHERE `status`='pending' ORDER BY `id` ASC LIMIT %d", self::FETCH_LIMIT))
                ->all();
        } catch (ORM_ModelNotFoundException $e) {
            $queuedMails = [];
        }

        $queuedMailsCount = count($queuedMails);
        $this->print(sprintf("{green}{invert} %d {/}", $queuedMailsCount));
        if (!$queuedMailsCount) {
            return;
        }

        $this->print("");
        /** @var QueuedMail $queuedMail */
        foreach ($queuedMails as $queuedMail) {
            // Attempt to send queued email message
            $sent = $this->sendEmailMessage($queuedMail);
            if ($sent) {
                $queuedMail->status = "sent";
                $queuedMail->lastError = null;
            } else {
                if ($queuedMail->attempts >= self::MAX_ATTEMPTS) {
                    $queuedMail->status = "failed";
                }
            }

            // Save new mail status
            $this->inline("\t{gray}Updating status in DB... ");
            if ($sent && $queuedMail->deleteOnSent) {
                $queuedMail->query()->delete();
            } else {
                $queuedMail->query()->update();
            }

            $this->print("{cyan}done{/}");

            // Check execution duration
            $age = Time::difference($startTimeStamp);
            if ($age >= self::TIME_LIMIT) {
                $this->print("");
                $this->print("Execution time expired!");
                $this->print(sprintf("Execution lasted for {magenta}%d{/} seconds...", $age));
                break;
            }
        }
    }

    /**
     * @param QueuedMail $mail
     * @return bool
     */
    private function sendEmailMessage(QueuedMail $mail): bool
    {
        $this->errors()->flush();

        $mail->attempts++; // Increase mail attempts
        $mail->lastAttempt = time(); // Set last attempt time
        $this->inline(sprintf('{magenta}[#%d]{/}{grey}[Attempt#%d] ', $mail->id, $mail->attempts));

        try {
            $mail->validate();
        } catch (AppConfigException|AppException $e) {
        }

        if (!$mail->_checksumVerified) {
            $this->print("... {red}Checksum error!{/}");
            $mail->lastError = "Internal checksum error";
            return false;
        }

        $this->inline(sprintf('{grey}to{/} {cyan}%s{/} {grey}({/}{yellow}%s{/}{grey}){/} ... ', $mail->email, $mail->subject));

        $compose = $this->smtpAgent->compose();
        $compose->subject($mail->subject);
        if ($mail->type === "html") {
            $compose->body()->html($mail->private("compiled"));
        } else {
            $compose->body()->plain($mail->private("compiled"));
        }

        try {
            $this->smtpAgent->send($compose, $mail->email);
        } catch (\Exception $e) {
            $this->print("{red}Fail{/}");
            if ($e instanceof SMTP_Exception) {
                $mail->lastError = $e->getMessage();
                $this->print(sprintf("\t{red}Error: {/}{gray}%s{/}", $e->getMessage()));
            } else {
                $this->errors()->trigger($e, E_USER_WARNING);
            }

            return false;
        }

        $this->print("{green}Sent{/}");
        $this->printErrors();
        return true;
    }
}
