<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Mails;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Database\Primary\MailsQueue;
use App\Common\Exception\AppControllerException;
use App\Common\Exception\AppException;
use App\Common\Kernel\ErrorHandler\Errors;
use App\Common\Kernel\KnitModifiers;
use App\Common\Mailer\QueuedMail;
use App\Common\Validator;
use Comely\Database\Exception\DatabaseException;
use Comely\Database\Exception\ORM_Exception;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema;

/**
 * Class Queue
 * @package App\Admin\Controllers\Mails
 */
class Queue extends AbstractAdminController
{
    private const PER_PAGE_OPTIONS = [50, 100, 250, 500];
    private const PER_PAGE_DEFAULT = self::PER_PAGE_OPTIONS[1];

    /**
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function adminCallback(): void
    {
        $db = $this->app->db()->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\MailsQueue');
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \App\Common\Exception\AppConfigException
     * @throws \App\Common\Exception\XSRF_Exception
     */
    public function postRequeue(): void
    {
        $this->verifyXSRF();

        $mailId = Validator::UInt(trim(strval($this->input()->get("mailId"))));

        try {
            /** @var QueuedMail $mail */
            $mail = MailsQueue::Find(["id" => intval($mailId)])->first();
        } catch (ORM_ModelNotFoundException $e) {
            throw new AppControllerException('No such email exists');
        } catch (DatabaseException $e) {
            $this->app->errors()->trigger($e, E_USER_WARNING);
            throw new AppControllerException('Failed to retrieve email message');
        }

        $mail->validate();

        if ($mail->status === "pending") {
            throw new AppControllerException('This message is already pending');
        }

        $this->verifyTotp(trim(strval($this->input()->get("totp"))));

        try {
            $mail->status = "pending";
            $mail->attempts = 0;
            $mail->lastError = null;
            $mail->lastAttempt = null;
            $mail->query()->update();
        } catch (DatabaseException $e) {
            $this->app->errors()->trigger($e, E_USER_WARNING);
            throw new AppControllerException('Failed to update queued mail row');
        }

        $this->response()->set("status", true);
        $this->response()->set("totpModalClose", true);
        $this->messages()->success('E-mail message has been re-queued!');
        $this->messages()->info('Refreshing page...');
        $this->response()->set("refresh", true);
    }

    /**
     * @return void
     */
    public function getRead(): void
    {
        $mailId = Validator::UInt(trim(strval($this->input()->get("mail"))));
        if (!$mailId) {
            exit("Invalid e-mail ID");
        }

        try {
            /** @var QueuedMail $mail */
            $mail = MailsQueue::Find(["id" => intval($mailId)])->first();
            $mail->validate();
        } catch (AppException $e) {
            exit($e->getMessage());
        } catch (\Exception $e) {
            exit(Errors::Exception2String($e));
        }

        print $mail->private("compiled");
        exit;
    }

    /**
     * @throws AppException
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('Mails Queue')->index(910, 10)
            ->prop("containerIsFluid", true)
            ->prop("icon", "mdi mdi-mailbox-open-outline");

        $this->breadcrumbs("Mails Queue", null, "ion ion-email");

        $this->page()->js($this->request()->url()->root(getenv("ADMIN_TEMPLATE") . '/js/app/mails-queue.min.js'));

        $errorMessage = null;
        $result = [
            "status" => false,
            "count" => 0,
            "page" => null,
            "rows" => null,
            "nav" => null
        ];

        $search = [
            "email" => null,
            "subject" => null,
            "status" => null,
            "sort" => "desc",
            "perPage" => self::PER_PAGE_DEFAULT,
            "advanced" => false,
            "link" => null
        ];

        try {
            // E-mail address
            $email = trim(strval($this->input()->get("email")));
            if ($email) {
                if (!Validator::isASCII($email, "-_@.")) {
                    throw new AppControllerException('E-mail contains an illegal character');
                } elseif (strlen($email) > 64) {
                    throw new AppControllerException('E-mail address is too long');
                }

                $search["email"] = $email;
            }

            // Subject
            $subject = trim(strval($this->input()->get("subject")));
            if ($subject) {
                if (!Validator::isASCII($subject, "!-_+=@#$?<>|][}{\\/*.&%~`\"'")) {
                    throw new AppControllerException('Subject contains an illegal character');
                } elseif (strlen($subject) > 64) {
                    throw new AppControllerException('Subject is too long');
                }

                $search["subject"] = $subject;
            }

            // Status
            $status = trim(strval($this->input()->get("status")));
            if ($status) {
                if (!in_array($status, ["pending", "sent", "failed"])) {
                    throw new AppControllerException('Invalid queued mails status');
                }

                $search["status"] = $status;
            }

            // Sort By
            $sort = $this->input()->get("sort");
            if (is_string($sort) && in_array(strtolower($sort), ["asc", "desc"])) {
                $search["sort"] = $sort;
                if ($search["sort"] === "asc") {
                    $search["advanced"] = true;
                }
            }

            // Per Page
            $perPage = Validator::UInt($this->input()->get("perPage"));
            if ($perPage) {
                if (!in_array($perPage, self::PER_PAGE_OPTIONS)) {
                    throw new AppControllerException('Invalid search results per page count');
                }
            }

            $search["perPage"] = is_int($perPage) && $perPage > 0 ? $perPage : self::PER_PAGE_DEFAULT;
            if ($search["perPage"] !== self::PER_PAGE_DEFAULT) {
                $search["advanced"] = true;
            }

            $page = Validator::UInt($this->input()->get("page")) ?? 1;
            $start = ($page * $perPage) - $perPage;

            $db = $this->app->db()->primary();
            $mailsQueue = $db->query()->table(MailsQueue::NAME)
                ->limit($search["perPage"])
                ->start($start);

            if ($search["sort"] === "asc") {
                $mailsQueue->asc("id");
            } else {
                $mailsQueue->desc("id");
            }

            $whereQuery = "`id`>0";
            $whereData = [];

            // Search email
            if (isset($search["email"])) {
                $whereQuery .= ' AND `email` LIKE ?';
                $whereData[] = sprintf('%%%s%%', $search["email"]);
            }

            // Search subject
            if (isset($search["subject"])) {
                $whereQuery .= ' AND `subject` LIKE ?';
                $whereData[] = sprintf('%%%s%%', $search["subject"]);
            }

            // Status
            if (isset($search["status"])) {
                $whereQuery .= ' AND `status`=?';
                $whereData[] = $search["status"];
            }

            $mailsQueue->where($whereQuery, $whereData);
            $mails = $mailsQueue->paginate();

            $result["page"] = $page;
            $result["count"] = $mails->totalRows();
            $result["nav"] = $mails->compactNav();
            $result["status"] = true;
        } catch (AppException $e) {
            $errorMessage = $e->getMessage();
        } catch (\Exception $e) {
            $this->app->errors()->trigger($e, E_USER_WARNING);
            $errorMessage = "An error occurred while searching users";
        }

        if (isset($mails) && $mails->count()) {
            foreach ($mails->rows() as $queuedMailRow) {
                try {
                    $mail = new QueuedMail($queuedMailRow);
                    try {
                        $mail->validate();
                    } catch (AppException $e) {
                    }

                    $result["rows"][] = $mail;
                } catch (\Exception $e) {
                    $this->app->errors()->trigger($e, E_USER_WARNING);
                }
            }
        }

        // Search Link
        $search["link"] = $this->authRoot . sprintf(
                'mails/queue?status=%s&email=%s&subject=%s&sort=%s&perPage=%d',
                $search["status"],
                $search["email"],
                $search["subject"],
                $search["sort"],
                $search["perPage"]
            );

        // Knit Modifiers
        KnitModifiers::Dated($this->knit());
        KnitModifiers::Hex($this->knit());

        $template = $this->template("mails/queue.knit")
            ->assign("errorMessage", $errorMessage)
            ->assign("search", $search)
            ->assign("result", $result)
            ->assign("perPageOpts", self::PER_PAGE_OPTIONS);
        $this->body($template);
    }
}
