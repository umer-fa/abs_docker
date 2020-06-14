<?php
declare(strict_types=1);

namespace App\Admin\Controllers\App\Config;

use App\Common\Config\SMTPConfig;
use App\Common\Exception\AppControllerException;
use App\Common\Exception\AppException;
use App\Common\Kernel\KnitModifiers;
use App\Common\Validator;
use Comely\DataTypes\Integers;
use Comely\Utils\Time\Time;
use Comely\Utils\Time\TimeUnits;

/**
 * Class Smtp
 * @package App\Admin\Controllers\App\Config
 */
class Smtp extends AbstractAdminConfigController
{
    /** @var SMTPConfig */
    private SMTPConfig $smtpConfig;

    /**
     * @return void
     */
    public function configCallback(): void
    {
        try {
            $this->smtpConfig = SMTPConfig::getInstance(true);
        } catch (AppException $e) {
            $this->app->errors()->trigger($e, E_USER_WARNING);
            $this->smtpConfig = new SMTPConfig();
        }
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \App\Common\Exception\XSRF_Exception
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function post(): void
    {
        $this->verifyXSRF();
        $this->totpSessionCheck();

        $changes = 0;

        // Postmaster
        // Sender Name
        try {
            $sender = trim(strval($this->input()->get("sender")));
            $senderLen = strlen($sender);
            if (!$sender) {
                throw new AppControllerException('Sender name is required');
            } elseif ($senderLen > 20) {
                throw new AppControllerException('Sender name exceeds maximum of 20 characters');
            } elseif (!preg_match('/^[\w\-]+(\s[\w\-.!]+)*$/i', $sender)) {
                throw new AppControllerException('Sender name contains an illegal character');
            }
        } catch (AppControllerException $e) {
            $e->setParam("sender");
            throw $e;
        }

        if ($this->smtpConfig->setValue("senderName", $sender, true)) {
            $changes++;
        }

        // Sender E-mail
        try {
            $senderEm = trim(strval($this->input()->get("sender_email")));
            $senderEmLen = strlen($senderEm);
            if (!$senderEm) {
                throw new AppControllerException('Sender e-mail is required');
            } elseif ($senderEmLen > 64) {
                throw new AppControllerException('Sender e-mail address is too long');
            } elseif (!Validator::isValidEmailAddress($senderEm)) {
                throw new AppControllerException('Invalid e-mail address');
            }
        } catch (AppControllerException $e) {
            $e->setParam("sender_email");
            throw $e;
        }

        if ($this->smtpConfig->setValue("senderEmail", $senderEm, true)) {
            $changes++;
        }

        // SMTP
        $status = Validator::getBool(trim(strval($this->input()->get("status"))));
        if ($this->smtpConfig->status !== $status) {
            $this->smtpConfig->status = $status;
            $changes++;
        }

        // Local Server/Hostname
        try {
            $serverName = trim(strval($this->input()->get("server_name")));
            $serverNameLen = strlen($serverName);
            if (!$serverName) {
                throw new AppControllerException('Local server/hostname is required');
            } elseif ($serverNameLen < 6) {
                throw new AppControllerException('Local server/hostname is too short');
            } elseif ($serverNameLen > 64) {
                throw new AppControllerException('Local server/hostname is too long');
            }

            $serverName = Validator::isValidHostname($serverName);
            if (!$serverName) {
                throw new AppControllerException('Invalid local server/hostname');
            }
        } catch (AppControllerException $e) {
            $e->setParam("server_name");
            throw $e;
        }

        if ($this->smtpConfig->setValue("serverName", $serverName, true)) {
            $changes++;
        }

        // SMTP Hostname
        try {
            $hostname = trim(strval($this->input()->get("hostname")));
            $hostnameLen = strlen($hostname);
            if (!$hostname) {
                throw new AppControllerException('SMTP server hostname is required');
            } elseif ($hostnameLen < 6) {
                throw new AppControllerException('SMTP server hostname is too short');
            } elseif ($hostnameLen > 64) {
                throw new AppControllerException('SMTP server hostname is too long');
            }

            $hostname = Validator::isValidHostname($hostname);
            if (!$hostname) {
                throw new AppControllerException('Invalid SMTP server hostname');
            }
        } catch (AppControllerException $e) {
            $e->setParam("hostname");
            throw $e;
        }

        if ($this->smtpConfig->setValue("hostname", $hostname, true)) {
            $changes++;
        }

        // Port
        try {
            $port = intval(trim(strval($this->input()->get("port"))));
            if (!Validator::isValidPort($port, 25)) {
                throw new AppControllerException('Invalid port number');
            }
        } catch (AppControllerException $e) {
            $e->setParam("port");
            throw $e;
        }

        if ($this->smtpConfig->setValue("port", $port)) {
            $changes++;
        }

        // Time Out
        try {
            $timeOut = intval(trim(strval($this->input()->get("time_out"))));
            if ($timeOut < 1) {
                throw new AppControllerException('Time out value must be 1sec or more');
            } elseif ($timeOut > 30) {
                throw new AppControllerException('Time out value cannot exceed 30 seconds');
            }
        } catch (AppControllerException $e) {
            $e->setParam("time_out");
            throw $e;
        }

        if ($this->smtpConfig->setValue("timeOut", $timeOut)) {
            $changes++;
        }

        // TLS Encryption
        $useTLS = Validator::getBool(trim(strval($this->input()->get("use_tls"))));
        if ($this->smtpConfig->setValue("useTLS", $useTLS)) {
            $changes++;
        }

        // Username
        try {
            $username = trim(strval($this->input()->get("username")));
            $usernameLen = strlen($username);
            if (!$username) {
                throw new AppControllerException('SMTP username is required');
            } elseif (!Integers::Range($usernameLen, 2, 64)) {
                throw new AppControllerException('SMTP username must be 2-64 characters long');
            } elseif (!Validator::isASCII($username, "-.=+:")) {
                throw new AppControllerException('SMTP username contains an illegal character');
            }
        } catch (AppControllerException $e) {
            $e->setParam("username");
            throw $e;
        }

        if ($this->smtpConfig->setValue("username", $username, true)) {
            $changes++;
        }

        // Password
        try {
            $password = trim(strval($this->input()->get("password")));
            $passwordLen = strlen($password);
            if (!$password) {
                throw new AppControllerException('SMTP password is required');
            } elseif (!Integers::Range($passwordLen, 2, 64)) {
                throw new AppControllerException('SMTP password must be 2-64 characters long');
            } elseif (!Validator::isASCII($username, "-.=+:$%!<>;?")) {
                throw new AppControllerException('SMTP password contains an illegal character');
            }
        } catch (AppControllerException $e) {
            $e->setParam("password");
            throw $e;
        }

        if ($this->smtpConfig->setValue("password", $password, true)) {
            $changes++;
        }

        if (!$changes) {
            throw new AppControllerException('There are no changes to be saved');
        }

        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            // Save configuration in DB
            $this->smtpConfig->save();

            // Admin Log
            $this->authAdmin->log('SMTP Configuration Updated', null, null, ["config"]);

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('Failed to update SMTP configuration');
        }

        // Delete Cached
        try {
            $this->app->cache()->delete(SMTPConfig::CACHE_KEY);
        } catch (\Exception $e) {
        }

        $this->response()->set("status", true);
        $this->messages()->success('SMTP configuration has been updated!');
        $this->messages()->info('Refreshing page...');
        $this->response()->set("refresh", true);
    }

    /**
     * @throws AppException
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('SMTP Configuration')->index(310, 0, 2)
            ->prop("icon", "mdi mdi-email-edit-outline");

        $this->breadcrumbs("Application", null, "mdi mdi-server-network");
        $this->breadcrumbs("Configuration", null, "ion ion-ios-settings-strong");

        // Last Cached On
        $lastCachedOn = null;
        if (isset($this->smtpConfig->cachedOn)) {
            $lastCachedTimeDiff = Time::difference($this->smtpConfig->cachedOn);
            if ($lastCachedTimeDiff) {
                $timeUnits = new TimeUnits();
                $lastCachedOn = $timeUnits->timeToString($lastCachedTimeDiff);
            }
        }

        KnitModifiers::Null($this->knit());

        $template = $this->template("app/config/smtp.knit")
            ->assign("lastCachedOn", $lastCachedOn)
            ->assign("smtpConfig", $this->smtpConfig);
        $this->body($template);
    }
}
