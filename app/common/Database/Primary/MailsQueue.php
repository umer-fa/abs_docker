<?php
declare(strict_types=1);

namespace App\Common\Database\Primary;

use App\Common\Database\AbstractAppTable;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class MailsQueue
 * @package App\Common\Database\Primary
 */
class MailsQueue extends AbstractAppTable
{
    public const NAME = 'mails_queue';
    public const MODEL = 'App\Common\Mailer\QueuedMail';

    public const MAX_PRE_HEADER = 1024;
    public const MAX_COMPILED_BODY = 24576;

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("id")->bytes(8)->unSigned()->autoIncrement();
        $cols->binary("checksum")->fixed(20);
        $cols->string("lang")->length(5);
        $cols->enum("type")->options("text", "html")->default("text");
        $cols->enum("status")->options("pending", "sent", "failed")->default("pending");
        $cols->string("last_error")->length(255)->nullable();
        $cols->int("attempts")->bytes(1)->unSigned()->default(0);
        $cols->string("email")->length(64);
        $cols->string("subject")->length(64);
        $cols->binary("compiled")->length(self::MAX_COMPILED_BODY);
        $cols->int("delete_on_sent")->bytes(1)->unSigned()->default(0);
        $cols->int("time_stamp")->bytes(4)->unSigned();
        $cols->int("last_attempt")->bytes(4)->unSigned()->nullable();
        $cols->primaryKey("id");
    }
}
