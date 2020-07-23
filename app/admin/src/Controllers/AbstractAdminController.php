<?php
declare(strict_types=1);

namespace App\Admin\Controllers;

use App\Admin\AppAdmin;
use App\Admin\Exception\TOTPAuthException;
use App\Common\Admin\Administrator;
use App\Common\Database\Primary\Administrators;
use App\Common\Exception\AppControllerException;
use App\Common\Exception\AppException;
use App\Common\Exception\XSRF_Exception;
use App\Common\Kernel\ErrorHandler\Errors;
use App\Common\Kernel\Http\Controllers\GenericHttpController;
use Comely\Database\Queries\Query;
use Comely\Database\Schema;
use Comely\DataTypes\Buffer\Base16;
use Comely\Filesystem\Exception\FilesystemException;
use Comely\Knit\Exception\KnitException;
use Comely\Knit\Knit;
use Comely\Knit\Template;
use Comely\Utils\Time\Time;

/**
 * Class AbstractAdminController
 * @package App\Admin\Controllers
 */
abstract class AbstractAdminController extends GenericHttpController
{
    /** @var null|Administrator */
    protected ?Administrator $authAdmin = null;
    /** @var null|string */
    protected ?string $authToken = null;
    /** @var null|string */
    protected ?string $authRoot = null;
    /** @var array|null */
    private ?array $breadcrumbs = null;

    /**
     * @throws \Exception
     */
    public function callback(): void
    {
        $this->app = AppAdmin::getInstance();
        parent::callback();
    }

    /**
     * @throws XSRF_Exception
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Sessions\Exception\ComelySessionException
     * @throws \Comely\Utils\Security\Exception\PRNG_Exception
     */
    public function onLoad(): void
    {
        // Configure HTTP Cookies Domain
        $this->app->http()->cookies()->domain($this->app->config()->adminHost());

        // Instantiate Session
        $this->initSession();
        $this->page()->set_XSRF_Token();

        // ORM Tables
        $db = $this->app->db()->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Administrators');
        Schema::Bind($db, 'App\Common\Database\Primary\Administrators\Logs');

        // Schema Events
        Schema::Events()->on_ORM_ModelQueryFail()->listen(function (Query $query) {
            $app = AppAdmin::getInstance();
            if ($query->error()) {
                $app->errors()->triggerIfDebug(
                    sprintf('[SQL[%s]][%s] %s', $query->error()->sqlState, $query->error()->code, $query->error()->info),
                    E_USER_WARNING
                );
            }
        });

        // Authentication?
        $authenticate = true;
        if (get_called_class() === 'App\Admin\Controllers\Login') {
            $authenticate = false;
        }

        if ($authenticate) {
            $this->authenticate();
        }

        // Administration Panel callback
        $this->adminCallback();
    }

    /**
     * @return void
     */
    private function authenticate(): void
    {
        $this->authToken = $this->request()->url()->path(0);
        $this->authRoot = $this->request()->url()->root($this->authToken) . "/";

        try {
            // Session Bag
            $sessionBag = $this->session()->bags()->bag("App")->bag("Administration");
            if (!$sessionBag->has("id", "checksum", "timeStamp")) {
                throw new AppControllerException('No administration session found; You are not logged in!');
            }

            // Administrator Account
            $admin = Administrators::get(intval($sessionBag->get("id")));
            if ($admin->status !== 1) {
                throw new AppControllerException('Your account has been DISABLED');
            }

            $admin->validate(); // Verify checksum

            // Session Checksum
            if (!hash_equals(md5($admin->private("checksum")), $sessionBag->get("checksum"))) {
                throw new AppControllerException('Administrator session checksum fail');
            }

            // Auth. Token
            if (!$this->authToken) {
                throw new AppControllerException('Authentication token not found');
            } elseif (!hash_equals(bin2hex($admin->private("authToken")), $this->authToken)) {
                throw new AppControllerException('Invalid authentication token');
            }

            // Timeout
            if (Time::minutesDifference(intval($sessionBag->get("timeStamp"))) >= 240) {
                $admin->log('Session timed out', get_called_class(), 0, ["auth"]);
                throw new AppControllerException('Your session has timed out; Please login again!');
            }
        } catch (\Exception $e) {
            // Delete Session Bag
            $this->session()->bags()->bag("App")->delete("Administration");

            if ($e instanceof AppControllerException) {
                $this->flash()->info($e->getMessage());
            } else {
                if ($this->app->isDebug()) {
                    $this->flash()->warning(Errors::Exception2String($e));
                }
            }

            $this->redirect($this->request()->url()->root("login"), 401, true); // 401 Unauthorized
            exit;
        }

        $sessionBag->set("timeStamp", time()); // Prolong session
        $this->authAdmin = $admin;
    }

    /**
     * @param string $url
     * @param int|null $code
     * @param bool $checkIfJSON
     */
    public function redirect(string $url, ?int $code = null, bool $checkIfJSON = true): void
    {
        if ($checkIfJSON) {
            $accept = strval($this->request()->headers()->get("accept"));
            if ($accept && preg_match('/application\/json/i', $accept)) {
                $this->response()->set("redirect", $url);
                if ($code > 0) {
                    $this->response()->set("redirectCode", $code);
                }

                $this->router()->response()->send($this);
                return;
            }
        }

        parent::redirect($url, $code);
    }

    /**
     * @return Knit
     * @throws AppException
     */
    public function knit(): Knit
    {
        $knit = parent::knit();

        try {
            $templateDir = $this->app->dirs()->root()->dir("template", false);
        } catch (FilesystemException $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('Cannot load template directory');
        }

        $knit->dirs()->templates($templateDir);
        return $knit;
    }

    /**
     * @param string $name
     * @param string|null $href
     * @param string|null $icon
     * @return $this
     */
    public function breadcrumbs(string $name, ?string $href = null, ?string $icon = null): self
    {
        $this->breadcrumbs[] = [
            "name" => $name,
            "href" => $href,
            "icon" => $icon
        ];

        return $this;
    }

    /**
     * @param string|null $inputParam
     * @throws \App\Common\Exception\XSRF_Exception
     */
    public function verifyXSRF(?string $inputParam = "xsrf"): void
    {
        $userSpecifiedToken = $this->input()->get($inputParam);
        if (!is_string($userSpecifiedToken) || !preg_match('/^[a-f0-9]+$/i', $userSpecifiedToken)) {
            throw new XSRF_Exception('Invalid XSRF token');
        }

        try {
            $this->xsrf()->verify(new Base16($userSpecifiedToken));
        } catch (XSRF_Exception $e) {
            if ($e->getCode() === XSRF_Exception::TOKEN_IP_MISMATCH) {
                $this->xsrf()->purge();
            }

            throw $e;
        }
    }

    /**
     * @param int $time
     */
    public function totpSessionCheck(int $time = 600): void
    {
        try {
            $totpBag = $this->session()->bags()->bag("App")->bag("Administration")->bag("totp");
            $lastCheckedOn = $totpBag->get("lastCheckedOn");
            if (!is_int($lastCheckedOn)) {
                throw new TOTPAuthException('TOTP authentication is required');
            }

            if (Time::difference($lastCheckedOn) >= $time) {
                $diff = time() - $lastCheckedOn;
                throw new TOTPAuthException(
                    sprintf('Last TOTP check was %s min(s) ago; Need re-authentication', round($diff / 60, 1))
                );
            }
        } catch (TOTPAuthException $e) {
            $this->messages()->danger($e->getMessage());

            $this->response()->set("status", false);
            $this->response()->set("totpAuthModal", true);
            $this->response()->set("messages", $this->messages()->array());
            $this->router()->response()->send($this);
            exit;
        }
    }

    /**
     * @param string|null $code
     * @param string|null $param
     * @throws AppControllerException
     * @throws AppException
     */
    public function verifyTotp(?string $code = null, ?string $param = "totp"): void
    {
        try {
            $totpBag = $this->session()->bags()->bag("App")->bag("Administration")->bag("totp");
            if (!$code) {
                throw new AppControllerException('TOTP code is required');
            }

            $lastCode = $totpBag->get("lastCode");
            if ($lastCode && $lastCode === $code) {
                throw new AppControllerException('This TOTP code has already been used');
            }

            if (!$this->authAdmin->credentials()->verifyTotp($code)) {
                throw new AppControllerException('Incorrect TOTP code');
            }

            $totpBag->set("lastCode", $code);
        } catch (AppControllerException $e) {
            $e->setParam($param);
            throw $e;
        }
    }

    /**
     * @param Template $template
     * @throws KnitException
     */
    public function body(Template $template): void
    {
        try {
            $templatePath = $template->path();
            $this->knit()->events()->onTemplatePrepared()->listen(function (Template $tpl) use ($templatePath) {
                if ($templatePath === $tpl->path()) {
                    // What errors to display
                    $displayErrors = $this->app->isDebug() ?
                        $this->app->errors()->all() :
                        $this->app->errors()->triggered()->array();

                    // Late assign errors messages
                    $errors = new Template\Metadata\MetaTemplate(
                        "_errors.knit",
                        ["errors" => $displayErrors]
                    );
                    $tpl->metadata("errors", $errors);
                }
            });

            if ($this->authAdmin) {
                $template->assign("authAdmin", $this->authAdmin);
                $template->assign("authToken", $this->authToken);

                $this->page()->prop("authRoot", $this->authRoot);
            }

            if ($this->breadcrumbs) {
                $template->assign("breadcrumbs", $this->breadcrumbs);
            }

            parent::body($template);
        } catch (KnitException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('[%s][#%s] %s', get_class($e), $e->getCode(), $e->getMessage()));
        }
    }

    /**
     * @return void
     */
    abstract public function adminCallback(): void;

    /**
     * @return void
     */
    public function onFinish(): void
    {
    }
}
