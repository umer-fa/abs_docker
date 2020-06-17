<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Staff;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Exception\AppControllerException;
use App\Common\Exception\AppException;
use App\Common\Packages\GoogleAuth\GoogleAuthenticator;
use App\Common\Validator;
use Comely\DataTypes\Integers;
use Comely\Utils\Security\Passwords;

/**
 * Class Me
 * @package App\Admin\Controllers\Staff
 */
class Me extends AbstractAdminController
{
    public function adminCallback(): void
    {
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \App\Common\Exception\XSRF_Exception
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function postProfile(): void
    {
        $this->verifyXSRF();
        $this->totpSessionCheck(60);

        try {
            $phone = $this->input()->get("phone");
            if (!is_string($phone) || !$phone) {
                throw new AppControllerException('Phone number is required');
            } elseif (!Validator::isValidPhone($phone)) {
                throw new AppControllerException('Invalid phone number; Use correct format');
            }

            if ($this->authAdmin->phone !== $phone) {
                $this->authAdmin->phone = $phone;
            }
        } catch (AppControllerException $e) {
            $e->setParam("phone");
            throw $e;
        }

        if (!$this->authAdmin->changes()) {
            throw new AppControllerException('There are no changes to be saved!');
        }

        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            $this->authAdmin->set("checksum", $this->authAdmin->checksum()->raw());
            $this->authAdmin->timeStamp = time();
            $this->authAdmin->query()->update();
            $this->authAdmin->log('Account profile updated', null, null, ["account"]);

            $adminBag = $this->session()->bags()->bag("App")->bag("Administration");
            $adminBag->set("checksum", md5($this->authAdmin->checksum()->raw()));

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('Failed to update account profile');
        }

        $this->response()->set("status", true);
        $this->messages()->success('Your profile has been updated!');
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \App\Common\Exception\XSRF_Exception
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function postTotp(): void
    {
        $this->verifyXSRF();

        $credentials = $this->authAdmin->credentials();
        if ($credentials->getGoogleAuthSeed()) {
            $this->totpSessionCheck(60);
        }

        $adminBag = $this->session()->bags()->bag("App")->bag("Administration");
        $suggestedSeed = $adminBag->get("suggestedSeed");
        if (!is_string($suggestedSeed) || !$suggestedSeed) {
            throw new AppControllerException('Suggested seed not found');
        }

        $googleAuth = new GoogleAuthenticator($suggestedSeed);

        try {
            $newTOTP = $this->input()->get("new_totp");
            if (!is_string($newTOTP) || !$newTOTP) {
                throw new AppControllerException('Enter new TOTP code');
            } elseif (!preg_match('/^[0-9]{6}$/', $newTOTP)) {
                throw new AppControllerException('Invalid TOTP code');
            }

            if (!$googleAuth->verify($newTOTP)) {
                throw new AppControllerException('Incorrect new TOTP code');
            }
        } catch (AppControllerException $e) {
            $e->setParam("new_totp");
            throw $e;
        }

        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            $credentials->setGoogleAuthSeed($suggestedSeed);
            $encryptedCredentials = $this->authAdmin->cipher()->encrypt(clone $credentials);

            $this->authAdmin->set("credentials", $encryptedCredentials->raw());
            $this->authAdmin->timeStamp = time();
            $this->authAdmin->query()->update();

            $this->authAdmin->log('Google authenticator seed updated', null, null, ["account"]);

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('Failed to change account password');
        }

        $this->response()->set("status", true);
        $this->response()->set("disabled", true);
        $this->messages()->success('Your Google2FA seed has been updated!');
    }

    /**
     * @throws AppControllerException
     * @throws AppException
     * @throws \App\Common\Exception\XSRF_Exception
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function postPassword(): void
    {
        $this->verifyXSRF();
        $this->totpSessionCheck(60);

        $credentials = $this->authAdmin->credentials();

        // New Password
        try {
            $newPassword = $this->input()->get("new_password");
            if (!is_string($newPassword) || !$newPassword) {
                throw new AppControllerException('Enter a desired new password');
            }

            $newPassword = trim($newPassword);
            if (Passwords::Strength($newPassword) < 4) {
                throw new AppControllerException('This password is not secure enough');
            } elseif (!Integers::Range(mb_strlen($newPassword), 8, 32)) {
                throw new AppControllerException('New password must be between 8 and 32 chars long');
            }

            if ($credentials->verifyPassword($newPassword)) {
                throw new AppControllerException('This is already your password');
            }
        } catch (AppControllerException $e) {
            $e->setParam("new_password");
            throw $e;
        }

        // Confirm new password
        try {
            $confirmNewPassword = $this->input()->get("confirm_new_password");
            if (!is_string($confirmNewPassword) || !$confirmNewPassword) {
                throw new AppControllerException('You must retype password');
            } elseif ($newPassword !== $confirmNewPassword) {
                throw new AppControllerException('You must retype the same password');
            }
        } catch (AppControllerException $e) {
            $e->setParam("confirm_new_password");
            throw $e;
        }

        $db = $this->app->db()->primary();
        try {
            $db->beginTransaction();

            $credentials->setPassword($newPassword);
            $encryptedCredentials = $this->authAdmin->cipher()->encrypt(clone $credentials);

            $this->authAdmin->set("credentials", $encryptedCredentials->raw());
            $this->authAdmin->timeStamp = time();
            $this->authAdmin->query()->update();

            $this->authAdmin->log('Your password has been updated', null, null, ["account"]);

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('Failed to change account password');
        }

        $this->response()->set("status", true);
        $this->response()->set("disabled", true);
        $this->messages()->success('Your administration account password has been updated!');
    }

    /**
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('My Account')->index(200, 30)
            ->prop("icon", "ion ion-ios-people-outline");

        $this->breadcrumbs("Staff Management", null, "ion ion-ios-people-outline");

        // Google 2FA
        $suggestedSeed = GoogleAuthenticator::generateSecret();
        $suggestedSeedQR = GoogleAuthenticator::getImageUrl(
            $this->authAdmin->email,
            $this->app->config()->public()->title(),
            $suggestedSeed
        );

        $adminBag = $this->session()->bags()->bag("App")->bag("Administration");
        $adminBag->set("suggestedSeed", $suggestedSeed);

        $template = $this->template("staff/me.knit")
            ->assign("suggestedSeed", $suggestedSeed)
            ->assign("suggestedSeedQR", $suggestedSeedQR);
        $this->body($template);
    }
}
