<?php
declare(strict_types=1);

namespace App\Common\Packages\GoogleAuth;

/**
 * Class GoogleAuthenticator
 * @package App\Common\Packages\GoogleAuth
 */
class GoogleAuthenticator
{
    /** @var float|int */
    private $pinModulo;
    /** @var string|null */
    private ?string $defaultSecret;

    /**
     * GoogleAuthenticator constructor.
     * @param null|string $secret
     */
    public function __construct(?string $secret = null)
    {
        $this->pinModulo = pow(10, 6);
        $this->defaultSecret = $secret;
    }

    /**
     * @param string $code
     * @param null|string $secret
     * @return bool
     */
    public function verify(string $code, ?string $secret = null): bool
    {
        $secret = $secret ?? $this->defaultSecret ?? "";
        $time = floor(time() / 30);

        for ($i = -1; $i <= 1; $i++) {
            if ($this->current($secret, ($time + $i)) == $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param null|string $secret
     * @param float|int|null $time
     * @return string
     */
    public function current(?string $secret = null, $time = null): string
    {
        $secret = $secret ?? $this->defaultSecret ?? "";
        if (!is_int($time)) {
            $time = floor(time() / 30);
        }

        $base32 = new FixedBitNotation(5, "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567", true, true);
        $secret = $base32->decode($secret);
        $time = pack("N", $time);
        $time = str_pad(strval($time), 8, chr(0), STR_PAD_LEFT);
        $hash = hash_hmac("sha1", $time, $secret, true);
        $offset = ord(substr($hash, -1));
        $offset = $offset & 0xF;

        $truncatedHash = $this->hash2int($hash, $offset) & 0x7FFFFFFF;
        $pinValue = $truncatedHash % $this->pinModulo;
        $pinValue = str_pad(strval($pinValue), 6, "0", STR_PAD_LEFT);

        return $pinValue;
    }

    /**
     * @param $bytes
     * @param $start
     * @return mixed
     */
    private function hash2int(string $bytes, int $start)
    {
        $input = substr($bytes, $start, (strlen($bytes) - $start));
        $val2 = unpack("N", substr($input, 0, 4));

        return $val2[1];
    }

    /**
     * @param string $user
     * @param string $hostname
     * @param null|string $secret
     * @return string
     */
    public function image(string $user, string $hostname, ?string $secret = null): string
    {
        $secret = $secret ?? $this->defaultSecret ?? "";
        return self::getImageUrl($user, $hostname, $secret ?? "");
    }

    /**
     * @param string $user
     * @param string $hostname
     * @param string $secret
     * @return string
     */
    public static function getImageUrl(string $user, string $hostname, string $secret): string
    {
        $encoder = "//chart.googleapis.com/chart?chs=177x177&cht=qr&chld=M|0&chl=";
        $data = sprintf('otpauth://totp/%2$s:%1$s?secret=%3$s&issuer=%2$s', $user, $hostname, $secret);
        return $encoder . $data;
    }

    /**
     * @return string
     */
    public static function generateSecret(): string
    {
        $secret = "";
        for ($i = 1; $i <= 20; $i++) {
            $c = rand(0, 255);
            $secret .= pack("c", $c);
        }

        $base32 = new FixedBitNotation(5, "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567", true, true);
        return $base32->encode($secret);
    }
}
