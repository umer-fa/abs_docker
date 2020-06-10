<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\Common\API\API_Session;
use App\Common\Database\API\Sessions;
use App\Common\Exception\API_Exception;
use App\Common\Exception\AppException;
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

    /**
     * @throws API_Exception
     * @throws AppException
     * @throws \App\Common\Exception\AppConfigException
     */
    final public function apiCallback(): void
    {
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
            $httpAuthHeader = explode(",", $this->request()->headers()->get("authorization"));
            foreach ($httpAuthHeader as $auth) {
                if (trim(strtolower(strval($auth[0]))) === $this->app->constant("api_sess_auth_name")) {
                    $sessionTokenId = trim(strval($auth[1] ?? ""));
                }
            }

            if (!isset($sessionTokenId) || !is_string($sessionTokenId) || !$sessionTokenId) {
                throw new API_Exception('SESSION_ID_HEADER');
            }

            if (!preg_match('/^[a-f0-9]{64}$/i', $sessionTokenId)) {
                throw new API_Exception('SESSION_ID_HEADER_INVALID');
            }

            // Retrieve API session ORM model
            $sessionTokenId = new Base16($sessionTokenId);
            $this->apiSession = Sessions::getSession((new Base16($sessionTokenId))->binary());
            $this->apiSession->validate(); // Validate checksum
            $this->apiSession->_lastUsedOn = $this->apiSession->lastUsedOn;

            // Update the lastUsedOn timeStamp
            $timeStamp = time();
            if ($timeStamp !== $this->apiSession->lastUsedOn) {
                try {
                    $this->apiSession->lastUsedOn = $timeStamp;
                    $this->apiSession->query()->update();
                } catch (\Exception $e) {
                    $this->app->errors()->triggerIfDebug($e);
                    throw new AppException('Failed to update API session time pointer');
                }
            }

            // Log the API token
            if ($this->queryLog) {
                $this->queryLog->flagApiSess = $this->apiSession->token()->hexits(false);
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

    abstract public function sessionAPICallback(): void;

}
