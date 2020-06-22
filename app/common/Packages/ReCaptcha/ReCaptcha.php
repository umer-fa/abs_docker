<?php
declare(strict_types=1);

namespace App\Common\Packages\ReCaptcha;

use Comely\Http\Http;

/**
 * Class ReCaptcha
 * @package App\Common\Packages\ReCaptcha
 */
class ReCaptcha
{
    /**
     * @param string $secret
     * @param string $response
     * @param string|null $ipAddress
     * @param string|null $pregMatchHostname
     * @throws ReCaptchaException
     * @throws ReCaptchaFailException
     * @throws \Comely\Http\Exception\HttpException
     */
    public static function Verify(string $secret, string $response, ?string $ipAddress = null, ?string $pregMatchHostname = null): void
    {
        $req = Http::Post("https://www.google.com/recaptcha/api/siteverify");
        $req->payload()->set("secret", $secret)
            ->set("response", $response);

        if ($ipAddress) {
            $req->payload()->set("remoteip", $ipAddress);
        }

        $res = $req->curl()->expectJSON(true)
            ->send();
        if ($res->code() !== 200) {
            throw new ReCaptchaException(sprintf('Expected HTTP code 200 from reCaptcha; Got %d', $res->code()));
        }

        if ($res->payload()->get("success") !== true) {
            $errorCodes = $res->payload()->get("error-codes") ?? [];
            if (is_array($errorCodes) && $errorCodes) {
                throw new ReCaptchaFailException(implode("|", $errorCodes));
            }

            throw new ReCaptchaFailException('ReCaptcha verify response param "success" is not TRUE');
        }

        if ($pregMatchHostname) {
            $hostname = strval($res->payload()->get("hostname") ?? "");
            if (!preg_match($pregMatchHostname, $hostname)) {
                throw new ReCaptchaFailException('ReCaptcha hostname verification fail');
            }
        }
    }
}
