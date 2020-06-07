<?php
declare(strict_types=1);

namespace App\Admin\Controllers;

use App\Common\Database\Primary\Administrators;
use App\Common\Exception\AppException;
use Comely\Utils\Security\PRNG;

/**
 * Class Login
 * @package App\Admin\Controllers
 */
class Login extends AbstractAdminController
{
    /**
     * @return void
     */
    public function adminCallback(): void
    {
        $sessionBag = $this->session()->bags()->bag("App");
        if ($sessionBag->hasBag("Administration")) {
            $this->redirect($this->request()->url()->root("dashboard"));
            exit;
        }
    }

    /**
     * @throws AppException
     * @throws \App\Common\Exception\ObfuscatedFormsException
     * @throws \App\Common\Exception\XSRF_Exception
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\PDO_Exception
     */
    public function post(): void
    {
        $form = $this->getObfuscatedForm("adminLogin");
        $this->verifyXSRF($form->key("xsrf"));

        // E-mail address
        try {
            $email = $form->value("email");
            if (!$email) {
                throw new AppException('Enter your e-mail address');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new AppException('Invalid e-mail address');
            }

            $admin = Administrators::email($email);
            if ($admin->status !== 1) {
                throw new AppException('This account has been disabled!');
            }

            $admin->validate(); // Verify row checksum, etc..
            $admin->credentials(); // Load credentials object
            $admin->privileges(); // Load privileges object
        } catch (AppException $e) {
            $e->setParam($form->key("email"));
            throw $e;
        }

        // Password
        try {
            $password = $form->value("password");
            if (!$password) {
                throw new AppException('Enter a password');
            } elseif (!$admin->credentials()->verifyPassword($password)) {
                throw new AppException('Incorrect password');
            }
        } catch (AppException $e) {
            $e->setParam($form->key("password"));
            throw $e;
        }

        // TOTP
        try {
            $totp = $form->value("totp");
            if ($admin->credentials()->getGoogleAuthSeed()) {
                if (!$totp || !preg_match('/^[0-9]{6}$/', $totp)) {
                    throw new AppException('Invalid TOTP code');
                } elseif (!$admin->credentials()->verifyTotp($totp)) {
                    throw new AppException('Incorrect TOTP code');
                }
            }
        } catch (AppException $e) {
            $e->setParam($form->key("totp"));
            throw $e;
        }

        // Log Administrator In
        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            $authToken = PRNG::randomBytes(10);
            $admin->set("authToken", $authToken->raw());
            $admin->timeStamp = time();
            $admin->query()->update(function () {
                throw new AppException('Failed to save new authentication token');
            });

            $admin->log('Logged in', __CLASS__, null, ["auth"]);

            // Set session bag
            $this->session()->bags()->bag("App")->bag("Administration")
                ->set("id", $admin->id)
                ->set("checksum", md5($admin->checksum()->raw()))
                ->set("timeStamp", time());

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('Authentication logic failure');
        }

        // Purge all obfuscated forms
        $this->obfuscatedForms()->flush();

        // Response
        $this->response()->set("status", true);
        $this->response()->set("disabled", true);
        $this->messages()->success('You have been logged in!');
        $this->messages()->info('Please wait...');
        $this->response()->set("redirect", $this->request()->url()->root($authToken->base16()->hexits() . "/dashboard"));
        return;
    }

    /**
     * @throws \App\Common\Exception\ObfuscatedFormsException
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('Administrator Login')->index(0, 0, 1);

        $form = $this->obfuscatedForms()->get("adminLogin", [
            "xsrf",
            "form",
            "email",
            "password",
            "totp",
            "submit"
        ]);

        $template = $this->template("login.knit")
            ->assign("form", $form->array());
        $this->body($template);
    }
}
