<?php
declare(strict_types=1);

namespace App\Admin\Controllers\App\Config;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Config\SMTPConfig;
use App\Common\Exception\AppException;
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

    public function post(): void
    {
        $this->verifyXSRF();
        $this->totpSessionCheck();


    }

    /**
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('SMTP Configuration')->index(310, 0, 2)
            ->prop("icon", "mdi mdi mdi-email-edit-outline");

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

        $template = $this->template("app/config/smtp.knit")
            ->assign("lastCachedOn", $lastCachedOn)
            ->assign("smtpConfig", $this->smtpConfig);
        $this->body($template);
    }
}
