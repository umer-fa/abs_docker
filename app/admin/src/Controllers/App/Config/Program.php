<?php
declare(strict_types=1);

namespace App\Admin\Controllers\App\Config;

use App\Common\Config\ProgramConfig;
use App\Common\Exception\AppControllerException;
use App\Common\Exception\AppException;
use App\Common\Kernel\KnitModifiers;
use App\Common\Validator;
use Comely\Utils\Time\Time;
use Comely\Utils\Time\TimeUnits;
use Comely\Utils\Validator\Exception\LengthException;
use Comely\Utils\Validator\Exception\ValidationException;

/**
 * Class Program
 * @package App\Admin\Controllers\App\Config
 */
class Program extends AbstractAdminConfigController
{
    /** @var ProgramConfig */
    private ProgramConfig $programConfig;

    /**
     * @return void
     */
    public function configCallback(): void
    {
        try {
            $this->programConfig = ProgramConfig::getInstance(true);
        } catch (AppException $e) {
            $this->app->errors()->trigger($e, E_USER_WARNING);
            $this->programConfig = new ProgramConfig();
        }
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \App\Common\Exception\XSRF_Exception
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function postRecaptcha(): void
    {
        $this->verifyXSRF();
        $this->totpSessionCheck();

        $programConfig = $this->programConfig;
        $changes = 0;

        // Status
        $status = Validator::getBool(trim(strval($this->input()->get("reCaptchaStatus"))));
        if ($programConfig->setValue("reCaptcha", $status)) {
            $changes++;
        }

        // Public Key
        try {
            $publicKey = trim(strval($this->input()->get("reCaptchaPublic")));
            if (!$publicKey && $status) {
                throw new AppControllerException('ReCaptcha public key is required');
            }

            if ($publicKey) {
                try {
                    $publicKey = \Comely\Utils\Validator\Validator::String($publicKey)
                        ->match('/^[\w\-\:]+$/')
                        ->len(8, 128)
                        ->validate();
                } catch (LengthException $e) {
                    throw new AppControllerException('Public key length error');
                } catch (ValidationException $e) {
                    throw new AppControllerException('Public key contains an illegal character');
                }
            }
        } catch (AppControllerException $e) {
            $e->setParam("reCaptchaPublic");
            throw $e;
        }

        if (!$publicKey) {
            $publicKey = null;
        }

        if ($programConfig->setValue("reCaptchaPub", $publicKey)) {
            $changes++;
        }

        // Private Key
        try {
            $privateKey = trim(strval($this->input()->get("reCaptchaPrivate")));
            if (!$privateKey && $status) {
                throw new AppControllerException('ReCaptcha secret key is required');
            }

            if ($privateKey) {
                try {
                    $privateKey = \Comely\Utils\Validator\Validator::String($privateKey)
                        ->match('/^[\w\-\:]+$/')
                        ->len(8, 128)
                        ->validate();
                } catch (LengthException $e) {
                    throw new AppControllerException('Secret key length error');
                } catch (ValidationException $e) {
                    throw new AppControllerException('Secret key contains an illegal character');
                }
            }
        } catch (AppControllerException $e) {
            $e->setParam("reCaptchaPrivate");
            throw $e;
        }

        if (!$privateKey) {
            $privateKey = null;
        }

        if ($programConfig->setValue("reCaptchaPrv", $privateKey)) {
            $changes++;
        }

        if (!$changes) {
            throw new AppControllerException('There are no changes to be saved');
        }

        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            // Save configuration in DB
            $programConfig->save();

            // Admin Log
            $this->authAdmin->log('ReCaptcha Configuration Updated', null, null, ["config"]);

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('Failed to update ReCaptcha configuration');
        }

        // Delete Cached
        try {
            $this->app->cache()->delete(ProgramConfig::CACHE_KEY);
        } catch (\Exception $e) {
        }

        $this->response()->set("status", true);
        $this->messages()->success('ReCaptcha configuration has been updated!');
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \App\Common\Exception\XSRF_Exception
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function postOauth(): void
    {
        $this->verifyXSRF();
        $this->totpSessionCheck();

        $programConfig = $this->programConfig;
        $changes = 0;

        if ($programConfig->setValue("oAuthStatus", Validator::getBool(trim(strval($this->input()->get("oAuthStatus")))))) {
            $changes++;
        }

        $updatedOAuthMethods = [];
        $oAuthMethods = ["Google", "Facebook", "LinkedIn"];
        foreach ($oAuthMethods as $oAuthMethod) {
            $newChanges = 0;
            $statusParam = "oAuth" . $oAuthMethod;
            $appIdParam = "oAuth" . $oAuthMethod . "AppId";
            $appKeyParam = "oAuth" . $oAuthMethod . "AppKey";

            $newStatus = Validator::getBool(trim(strval($this->input()->get($statusParam))));
            if ($programConfig->setValue($statusParam, $newStatus)) {
                $newChanges++;
            }

            // oAuth App ID
            try {
                $appId = trim(strval($this->input()->get($appIdParam)));
                if (!$appId && $newStatus) {
                    throw new AppControllerException(sprintf('OAuth "%s" App ID is required', $oAuthMethod));
                }

                if ($appId) {
                    try {
                        $appId = \Comely\Utils\Validator\Validator::String($appId)
                            ->match('/^[\w\-\:\@\#\.]+$/')
                            ->len(8, 128)
                            ->validate();
                    } catch (LengthException $e) {
                        throw new AppControllerException(sprintf('Oauth "%s" App ID length error', $oAuthMethod));
                    } catch (ValidationException $e) {
                        throw new AppControllerException(sprintf('Oauth "%s" App ID contains illegal character', $oAuthMethod));
                    }
                }
            } catch (AppControllerException $e) {
                $e->setParam($appIdParam);
                throw $e;
            }

            if (!$appId) {
                $appId = null;
            }

            if ($programConfig->setValue($appIdParam, $appId)) {
                $newChanges++;
            }

            // oAuth App Secret
            try {
                $appKey = trim(strval($this->input()->get($appKeyParam)));
                if (!$appKey && $newStatus) {
                    throw new AppControllerException(sprintf('OAuth "%s" App Secret/Key is required', $oAuthMethod));
                }

                if ($appKey) {
                    try {
                        $appKey = \Comely\Utils\Validator\Validator::String($appKey)
                            ->match('/^[\w\-\:\@\#]+$/')
                            ->len(8, 128)
                            ->validate();
                    } catch (LengthException $e) {
                        throw new AppControllerException(sprintf('Oauth "%s" App Secret/Key length error', $oAuthMethod));
                    } catch (ValidationException $e) {
                        throw new AppControllerException(sprintf('Oauth "%s" App Secret/Key contains illegal character', $oAuthMethod));
                    }
                }
            } catch (AppControllerException $e) {
                $e->setParam($appKeyParam);
                throw $e;
            }

            if (!$appKey) {
                $appKey = null;
            }

            if ($programConfig->setValue($appKeyParam, $appKey)) {
                $newChanges++;
            }

            // Required App ID and Keys?
            if ($newStatus) {
                if (!$appId || !$appKey) {
                    throw new AppControllerException('Oauth "%s" App ID and secret key is required!');
                }
            }

            // Total Changes
            $changes += $newChanges;
            if ($newChanges) {
                // Set OAuth Params
                $updatedOAuthMethods[] = $oAuthMethod;
            }

            unset($newStatus, $appId, $appKey);
        }

        if (!$changes) {
            throw new AppControllerException('There are no changes to be saved');
        }

        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            // Save configuration in DB
            $programConfig->save();

            // Admin Log
            $this->authAdmin->log('OAuth Configuration Updated', null, null, ["config"]);

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('Failed to update OAuth configuration');
        }

        // Delete Cached
        try {
            $this->app->cache()->delete(ProgramConfig::CACHE_KEY);
        } catch (\Exception $e) {
        }

        $this->response()->set("status", true);
        $this->messages()->success('OAuth configuration has been updated!');
    }

    /**
     * @throws AppException
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('Program Config')->index(310, 0, 1)
            ->prop("icon", "mdi mdi-cog-box");

        $this->breadcrumbs("Application", null, "mdi mdi-server-network");
        $this->breadcrumbs("Configuration", null, "ion ion-ios-settings-strong");

        // Last Cached On
        $lastCachedOn = null;
        if (isset($this->programConfig->cachedOn)) {
            $lastCachedTimeDiff = Time::difference($this->programConfig->cachedOn);
            if ($lastCachedTimeDiff) {
                $timeUnits = new TimeUnits();
                $lastCachedOn = $timeUnits->timeToString($lastCachedTimeDiff);
            }
        }

        KnitModifiers::Null($this->knit());

        $template = $this->template("app/config/program.knit")
            ->assign("lastCachedOn", $lastCachedOn)
            ->assign("programConfig", $this->programConfig);
        $this->body($template);
    }
}
