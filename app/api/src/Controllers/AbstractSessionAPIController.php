<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\Common\API\API_Session;
use App\Common\Config\ProgramConfig;
use App\Common\Database\API\Sessions;
use App\Common\Exception\API_Exception;
use App\Common\Exception\AppException;
use Comely\Database\Queries\Query;
use Comely\Database\Schema;
use Comely\DataTypes\Buffer\Base16;
use FurqanSiddiqui\SemaphoreEmulator\Exception\ConcurrentRequestBlocked;
use FurqanSiddiqui\SemaphoreEmulator\Exception\ConcurrentRequestTimeout;
use FurqanSiddiqui\SemaphoreEmulator\Exception\ResourceLockException;
use FurqanSiddiqui\SemaphoreEmulator\ResourceLock;

/**
 * Class AbstractSessionAPIController
 * @package App\API\Controllers
 */
abstract class AbstractSessionAPIController extends AbstractAPIController
{
    /** @var bool */
    protected const SEMAPHORE_IP_LOCK = true;

    /** @var ResourceLock|null */
    protected ?ResourceLock $semaphoreIPLock = null;
    /** @var API_Session|null */
    protected ?API_Session $apiSession = null;
    /** @var array */
    private array $httpAuthHeader;

    /**
     * @throws API_Exception
     * @throws AppException
     * @throws \App\Common\Exception\AppConfigException
     * @throws \Comely\Database\Exception\ORM_Exception
     * @throws \Comely\Database\Exception\ORM_ModelException
     */
    final public function apiCallback(): void
    {
        $this->httpAuthHeader = explode(",", strval($this->request()->headers()->get("authorization")));
        $this->httpAuthHeader = array_map("trim", $this->httpAuthHeader);

        // Semaphore Emulator
        if (static::SEMAPHORE_IP_LOCK) {
            if ($this->request()->method() !== "GET") { // Nevermind concurrent GET requests
                try {
                    $resourceLock = $this->app->semaphoreEmulator()
                        ->obtainLock(sprintf("ip_%s", md5($this->ipAddress)));
                    $this->semaphoreIPLock = $resourceLock;
                    register_shutdown_function(function () use ($resourceLock) {
                        $resourceLock->release();
                    });
                } catch (ResourceLockException $e) {
                    if ($e instanceof ConcurrentRequestBlocked) {
                        throw new API_Exception('CONCURRENT_REQUEST_BLOCKED');
                    } elseif ($e instanceof ConcurrentRequestTimeout) {
                        throw new API_Exception('CONCURRENT_REQUEST_TIMEOUT');
                    }

                    $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
                    throw new AppException('Concurrent requests validation fail');
                }
            }
        }

        $db = $this->apiLogsDb();
        Schema::Bind($db, 'App\Common\Database\API\Sessions');

        // Validate API Session
        $validateAPISession = true;
        if (get_called_class() === 'App\API\Controllers\Session' && $this->request()->method() === "POST") {
            $validateAPISession = false;
        }

        if ($validateAPISession) {
            $sessionTokenId = $this->httpAuthHeader($this->app->constant("api_auth_header_sess_token"));
            if (!$sessionTokenId) {
                throw new API_Exception('SESSION_ID_HEADER');
            }

            if (!preg_match('/^[a-f0-9]{64}$/i', $sessionTokenId)) {
                throw new API_Exception('SESSION_ID_HEADER_INVALID');
            }

            // Retrieve API session ORM model
            $sessionTokenId = new Base16($sessionTokenId);
            $this->apiSession = Sessions::getSession($sessionTokenId->binary());
            $this->apiSession->validate(); // Validate checksum
            $this->apiSession->_lastUsedOn = $this->apiSession->lastUsedOn;

            // Update the lastUsedOn timeStamp
            $timeStamp = time();
            if ($timeStamp !== $this->apiSession->lastUsedOn) {
                try {
                    $this->apiSession->lastUsedOn = $timeStamp;
                    $this->apiSession->query()->update(function (Query $query) {
                        if (!$query->isSuccess(false)) {
                            throw new AppException('Failed to update API session time pointer');
                        }
                    });
                } catch (\Exception $e) {
                    if ($e instanceof AppException) {
                        throw $e;
                    }
                }
            }

            // Log the API token
            if ($this->queryLog) {
                $this->queryLog->set("flagApiSess", $this->apiSession->private("token"));
                if (is_int($this->apiSession->authUserId) && $this->apiSession->authUserId > 0) {
                    $this->queryLog->flagUserId = $this->apiSession->authUserId;
                }
            }

            if ($this->apiSession->archived !== 0) {
                throw new API_Exception('SESSION_IS_ARCHIVED');
            }

            $sessionIPVerified = false;
            $userIP = $this->ipAddress;
            if ($userIP && $this->apiSession->ipAddress) {
                if ($userIP === $this->apiSession->ipAddress) {
                    $sessionIPVerified = true;
                }
            }

            if (!$sessionIPVerified) {
                throw new API_Exception('SESSION_IP_ERROR');
            }
        }

        // Callback
        $this->sessionAPICallback();
    }

    /**
     * @param string $which
     * @return string|null
     */
    final public function httpAuthHeader(string $which): ?string
    {
        $value = null;
        foreach ($this->httpAuthHeader as $auth) {
            $auth = explode(" ", trim(strval($auth)));
            if (trim(strtolower(strval($auth[0]))) === strtolower($which)) {
                $value = trim(strval($auth[1] ?? ""));
            }
        }

        return $value;
    }

    /**
     * @return bool
     * @throws AppException
     */
    final public function isReCaptchaRequired(): bool
    {
        if ($this->apiSession->type !== "web") {
            return false;
        }

        if ($this->apiSession->recaptchaLast) {
            if (time() - $this->apiSession->recaptchaLast < 30) {
                return false;
            }
        }

        $programConfig = ProgramConfig::getInstance();
        if ($programConfig->reCaptcha) {
            if ($programConfig->reCaptchaPub && $programConfig->reCaptchaPrv) {
                return true;
            }
        }

        return false;
    }

    abstract public function sessionAPICallback(): void;
}
