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
}
