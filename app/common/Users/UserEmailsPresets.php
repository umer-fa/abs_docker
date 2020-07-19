<?php
declare(strict_types=1);

namespace App\Common\Users;

use App\Common\Country;
use App\Common\Kernel;

/**
 * Class UserEmailsPresets
 * @package App\Common\Users
 */
class UserEmailsPresets
{
    /**
     * @param User $user
     * @param Country $country
     * @throws \App\Common\Exception\AppConfigException
     * @throws \App\Common\Exception\MailConstructException
     * @throws \Comely\Database\Exception\ORM_Exception
     * @throws \Comely\Knit\Exception\KnitException
     */
    public static function Signup(User $user, Country $country): void
    {
        $k = Kernel::getInstance();
        $publicConfig = $k->config()->public();

        $mail = $k->mailer()->compose($user->email, "Registration Successful");
        $mail->preHeader(
            sprintf(
                'You have successfully registered at %1$s. Your username is %2$s',
                $publicConfig->title(),
                $user->username
            )
        );

        $mail->htmlMessageFromTemplate("signup.knit", [
            "user" => [
                "firstName" => $user->firstName,
                "lastName" => $user->lastName,
                "username" => $user->username,
                "email" => $user->email,
                "country" => [
                    "name" => $country->name,
                    "code" => $country->code,
                ]
            ]
        ]);

        $mail->addToQueue();
    }

    /**
     * @param User $user
     * @throws \App\Common\Exception\AppConfigException
     * @throws \App\Common\Exception\AppException
     * @throws \App\Common\Exception\MailConstructException
     * @throws \Comely\Database\Exception\ORM_Exception
     * @throws \Comely\Knit\Exception\KnitException
     */
    public static function EmailVerifyRequest(User $user): void
    {
        $k = Kernel::getInstance();
        $publicConfig = $k->config()->public();

        $mail = $k->mailer()->compose($user->email, "E-mail verification required");
        $mail->preHeader(
            sprintf(
                'E-mail verification is required for your %1$s account %2$s',
                $publicConfig->title(),
                $user->username
            )
        );

        $mail->htmlMessageFromTemplate("email_verify_request.knit", [
            "user" => [
                "firstName" => $user->firstName,
                "lastName" => $user->lastName,
                "username" => $user->username,
                "email" => $user->email,
            ],
            "emailVerificationCode" => substr($user->emailVerifyBytes()->base16()->hexits(), -16),
        ]);

        $mail->addToQueue();
    }

    /**
     * @param User $user
     * @throws \App\Common\Exception\AppConfigException
     * @throws \App\Common\Exception\MailConstructException
     * @throws \Comely\Database\Exception\ORM_Exception
     * @throws \Comely\Knit\Exception\KnitException
     */
    public static function EmailVerified(User $user): void
    {
        $k = Kernel::getInstance();
        $publicConfig = $k->config()->public();

        $mail = $k->mailer()->compose($user->email, "E-mail address VERIFIED");
        $mail->preHeader(
            sprintf(
                'E-mail address for your %1$s account %2$s is now VERIFIED',
                $publicConfig->title(),
                $user->username
            )
        );

        $mail->htmlMessageFromTemplate("email_verified.knit", [
            "user" => [
                "firstName" => $user->firstName,
                "lastName" => $user->lastName,
                "username" => $user->username,
                "email" => $user->email,
            ]
        ]);

        $mail->addToQueue();
    }

    /**
     * @param User $user
     * @param string $prev
     * @param string $current
     * @throws \App\Common\Exception\AppConfigException
     * @throws \App\Common\Exception\MailConstructException
     * @throws \Comely\Database\Exception\ORM_Exception
     * @throws \Comely\Knit\Exception\KnitException
     */
    public static function SignInIPChange(User $user, string $prev, string $current): void
    {
        $k = Kernel::getInstance();
        $publicConfig = $k->config()->public();

        $mail = $k->mailer()->compose($user->email, "Detected IP Address Change");
        $mail->preHeader(
            sprintf(
                'Warning! Your %1$s account %2$s was accessed from a different IP address!',
                $publicConfig->title(),
                $user->username
            )
        );

        $mail->htmlMessageFromTemplate("signin_ip_change.knit", [
            "user" => [
                "firstName" => $user->firstName,
                "lastName" => $user->lastName,
                "username" => $user->username,
                "email" => $user->email,
            ],
            "prevIPAddr" => $prev,
            "currIPAddr" => $current,
        ]);

        $mail->addToQueue();
    }

    /**
     * @param User $user
     * @param string $resetCode
     * @throws \App\Common\Exception\AppConfigException
     * @throws \App\Common\Exception\MailConstructException
     * @throws \Comely\Database\Exception\ORM_Exception
     * @throws \Comely\Knit\Exception\KnitException
     */
    public static function RecoveryRequest(User $user, string $resetCode): void
    {
        $k = Kernel::getInstance();
        $publicConfig = $k->config()->public();

        $mail = $k->mailer()->compose($user->email, "Account recovery requested");
        $mail->preHeader(
            sprintf(
                'Forgot your %1$s password? This email contains password recovery instructions for %2$s',
                $publicConfig->title(),
                $user->username
            )
        );

        $mail->htmlMessageFromTemplate("recover_req.knit", [
            "user" => [
                "firstName" => $user->firstName,
                "lastName" => $user->lastName,
                "username" => $user->username,
                "email" => $user->email,
            ],
            "resetCode" => $resetCode,
        ]);

        $mail->addToQueue();
    }

    /**
     * @param User $user
     * @param string $newPassword
     * @throws \App\Common\Exception\AppConfigException
     * @throws \App\Common\Exception\MailConstructException
     * @throws \Comely\Database\Exception\ORM_Exception
     * @throws \Comely\Knit\Exception\KnitException
     */
    public static function PasswordReset(User $user, string $newPassword): void
    {
        $k = Kernel::getInstance();
        $publicConfig = $k->config()->public();

        $mail = $k->mailer()->compose($user->email, "Your new account password");
        $mail->preHeader(
            sprintf(
                'Password reset was successful; This e-mail contains your new %1$s account password',
                $publicConfig->title()
            )
        );

        $mail->htmlMessageFromTemplate("password_reset.knit", [
            "user" => [
                "firstName" => $user->firstName,
                "lastName" => $user->lastName,
                "username" => $user->username,
                "email" => $user->email,
            ],
            "newPassword" => htmlspecialchars($newPassword),
        ]);

        $mail->addToQueue();
    }
}
