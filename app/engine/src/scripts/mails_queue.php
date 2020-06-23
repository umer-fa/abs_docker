<?php
declare(strict_types=1);

namespace bin;

use App\Common\Database\Primary\MailsQueue;
use App\Common\Exception\AppException;
use App\Common\Kernel\AbstractCLIScript;
use App\Common\Mailer\QueuedMail;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema;

/**
 * Class mails_queue
 * @package bin
 */
class mails_queue extends AbstractCLIScript
{
    public const DISPLAY_HEADER = false;
    public const DISPLAY_LOADED_NAME = false;

    private const FETCH_LIMIT = 100;
    private const TIME_LIMIT = 50;

    /** @var int */
    private int $startTimeStamp;

    public function exec(): void
    {
        $db = $this->app->db()->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\MailsQueue');

        $this->startTimeStamp = time();
        $this->inline("Getting pending queued emails... ");

        try {
            $queuedMails = MailsQueue::Find()->query('WHERE `status`=? ORDER BY `id` ASC', ["pending"])->all();
        } catch (ORM_ModelNotFoundException $e) {
            $queuedMails = [];
        }

        $queuedMailsCount = count($queuedMails);
        $this->print(sprintf("{green}{invert} %d {/}", $queuedMailsCount));
        if (!$queuedMailsCount) {
            return;
        }

        $this->print("");
        foreach ($queuedMails as $queuedMail) {

        }

    }

    private function sendEmailMessage(QueuedMail $mail): void
    {
        $this->inline(sprintf('{magenta}#%d{/} ', $mail->id));

        try {
            $mail->validate();
        } catch (AppException $e) {
        }

        if (!$mail->_checksumVerified) {
            $this->print("... {red}Checksum error!{/}");
            $this->mailStatusFailed($mail);
            return;
        }

        $this->inline(sprintf('{grey}to{/} {cyan}%s{/} {grey}(%s){/} ... ', $mail->email, $mail->subject));

        $this->print("{red}Retry{/}");
    }

    private function mailStatusFailed(QueuedMail $mail): void
    {

    }
}
