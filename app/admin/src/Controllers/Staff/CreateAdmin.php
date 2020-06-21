<?php
declare(strict_types=1);

namespace App\Admin\Controllers\Staff;

use App\Admin\Controllers\AbstractAdminController;
use App\Common\Admin\Administrator;
use App\Common\Admin\Credentials;
use App\Common\Admin\Privileges;
use App\Common\Database\Primary\Administrators;
use App\Common\Exception\AppControllerException;
use App\Common\Exception\AppException;
use App\Common\Validator;
use Comely\Utils\Security\Passwords;

/**
 * Class CreateAdmin
 * @package App\Admin\Controllers\Staff
 */
class CreateAdmin extends AbstractAdminController
{
    /**
     * @throws \App\Common\Exception\AppException
     */
    public function adminCallback(): void
    {
        if (!$this->authAdmin->privileges()->root()) {
            $this->flash()->danger('Only root administrators can create new admin accounts');
            $this->redirect($this->authRoot . "dashboard");
            exit;
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

        if (!$this->authAdmin->privileges()->root()) {
            throw new AppControllerException('Only root administrators can create new administrative accounts');
        }

        $db = $this->app->db()->primary();

        // E-mail address
        try {
            $email = trim(strval($this->input()->get("email")));
            if (!$email) {
                throw new AppControllerException('E-mail address is required');
            } elseif (strlen($email) > 64) {
                throw new AppControllerException('E-mail address is too long');
            } elseif (!Validator::isValidEmailAddress($email)) {
                throw new AppControllerException('Invalid e-mail address');
            }

            // Duplicate Check
            $dup = $db->query()->table(Administrators::NAME)
                ->where('`email`=?', [$email])
                ->fetch();
            if ($dup->count()) {
                throw new AppControllerException('E-mail address is already in use!');
            }
        } catch (AppControllerException $e) {
            $e->setParam("email");
            throw $e;
        }

        // Password
        try {
            $password = trim(strval($this->input()->get("newAdminPass")));
            $passwordLen = strlen($password);
            if (!$password) {
                throw new AppControllerException('Password is required');
            } elseif ($passwordLen <= 5) {
                throw new AppControllerException('Password is too short');
            } elseif ($passwordLen > 32) {
                throw new AppControllerException('Password is too long');
            } elseif (Passwords::Strength($password) < 4) {
                throw new AppControllerException('Password is too weak!');
            }
        } catch (AppControllerException $e) {
            $e->setParam("newAdminPass");
            throw $e;
        }

        // Insert Administrator
        try {
            $db->beginTransaction();

            $admin = new Administrator();
            $admin->id = 0;
            $admin->set("checksum", "tba");
            $admin->status = 0;
            $admin->email = $email;
            $admin->phone = null;
            $admin->set("credentials", "tba");
            $admin->timeStamp = time();

            $admin->query()->insert(function () {
                throw new AppControllerException('Failed to insert admin row');
            });

            $admin->id = Validator::UInt($db->lastInsertId()) ?? 0;
            if (!$admin->id) {
                throw new AppControllerException('Invalid administrator last insert ID');
            }

            // Credentials & Privileges
            $credentials = new Credentials($admin);
            $credentials->setPassword($password);
            $privileges = new Privileges($admin);

            $adminCipher = $admin->cipher();
            $admin->set("checksum", $admin->checksum()->raw());
            $admin->set("credentials", $adminCipher->encrypt(clone $credentials)->raw());
            $admin->set("privileges", $adminCipher->encrypt(clone $privileges)->raw());

            $admin->query()->where('id', $admin->id)->update(function () {
                throw new AppControllerException('Failed to finalise administrator row');
            });

            // Create Log
            $this->authAdmin->log(
                sprintf('Administrator [#%d] "%s" created', $admin->id, $admin->email),
                __CLASS__,
                __LINE__,
                [sprintf("admins:%d", $this->authAdmin->id)]
            );

            $db->commit();
        } catch (AppException $e) {
            $db->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $db->rollBack();
            $this->app->errors()->trigger($e, E_USER_WARNING);
            throw new AppControllerException('Failed to insert administrator row');
        }

        $this->response()->set("status", true);
        $this->messages()->success("New administrator account created");
        $this->messages()->info("Redirecting...");
        $this->response()->set("disabled", true);
        $this->response()->set("redirect", $this->authRoot . "staff/edit?" . $admin->id);
    }

    /**
     * @throws \Comely\Knit\Exception\KnitException
     * @throws \Comely\Knit\Exception\TemplateException
     */
    public function get(): void
    {
        $this->page()->title('Create Administrator')->index(200, 40)
            ->prop("icon", "mdi mdi-shield-plus");

        $this->breadcrumbs("Staff Management", null, "mdi mdi-shield-account");

        $template = $this->template("staff/create_admin.knit");
        $this->body($template);
    }
}
