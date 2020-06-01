<?php
declare(strict_types=1);

namespace App\Common\Kernel\Http\Security;

use App\Common\Exception\XSRF_Exception;
use App\Common\Kernel\AbstractHttpApp;
use Comely\DataTypes\Buffer\Base16;
use Comely\Sessions\ComelySession;
use Comely\Utils\Security\PRNG;
use Comely\Utils\Time\Time;

/**
 * Class XSRF
 * @package App\Common\Kernel\Http\Security
 */
class XSRF
{
    /** @var AbstractHttpApp */
    private AbstractHttpApp $kernel;
    /** @var ComelySession */
    private ComelySession $sess;

    /**
     * XSRF constructor.
     * @param AbstractHttpApp $kernel
     * @param ComelySession $sess
     */
    public function __construct(AbstractHttpApp $kernel, ComelySession $sess)
    {
        $this->kernel = $kernel;
        $this->sess = $sess;
    }

    /**
     * @return string|null
     */
    public function token(): ?string
    {
        $xsrfBag = $this->sess->meta()->bag("xsrf");
        return $xsrfBag->get("entropy");
    }

    /**
     * @param int|null $ttl
     * @param bool $ipSensitive
     * @return string
     * @throws \Comely\Utils\Security\Exception\PRNG_Exception
     */
    public function generate(?int $ttl = null, bool $ipSensitive = true): string
    {
        $token = PRNG::randomBytes(20);
        $xsrfBag = $this->sess->meta()->bag("xsrf");
        $xsrfBag->set("entropy", $token->raw())
            ->set("timeStamp", time());

        if (is_int($ttl) && $ttl > 0) {
            $xsrfBag->set("ttl", $ttl);
        }

        if ($ipSensitive) {
            $xsrfBag->set("ip_addr", $this->kernel->http()->remote()->ipAddress);
        }

        return $token->raw();
    }

    /**
     * @param Base16 $token
     * @throws XSRF_Exception
     */
    public function verify(Base16 $token): void
    {
        $xsrfBag = $this->sess->meta()->bag("xsrf");
        $xsrfEntropy = $xsrfBag->get("entropy");
        if (!$xsrfEntropy) {
            throw new XSRF_Exception('XSRF token was not set in session', XSRF_Exception::TOKEN_NOT_SET);
        }

        if (!hash_equals(bin2hex($xsrfEntropy), $token->hexits())) {
            throw new XSRF_Exception('XSRF token does not match; Possible breach attempt', XSRF_Exception::TOKEN_MISMATCH);
        }

        $xsrfTimeStamp = $xsrfBag->get("timeStamp");
        $xsrfTTL = $xsrfBag->get("ttl");
        if (is_int($xsrfTTL) && $xsrfTTL > 0) {
            if (is_int($xsrfTimeStamp)) {
                if (Time::difference($xsrfTimeStamp) >= $xsrfTTL) {
                    throw new XSRF_Exception('XSRF token has expired; Try refreshing the page', XSRF_Exception::TOKEN_EXPIRED);
                }
            }
        }

        // IP sensitive?
        $xsrfIP_Address = $xsrfBag->get("ip_addr");
        if ($xsrfIP_Address && $xsrfIP_Address !== $this->kernel->http()->remote()->ipAddress) {
            throw new XSRF_Exception(
                sprintf('XSRF token is IP sensitive; IP address "%s" is not authorized', $this->kernel->http()->remote()->ipAddress),
                XSRF_Exception::TOKEN_IP_MISMATCH
            );
        }
    }
}
