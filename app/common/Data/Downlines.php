<?php
declare(strict_types=1);

namespace App\Common\Data;

use App\Common\Database\Primary\Users;
use App\Common\Exception\AppException;
use App\Common\Kernel;
use App\Common\Users\User;
use Comely\Database\Exception\ORM_ModelNotFoundException;

/**
 * Class Downlines
 * @package App\Common\Data
 */
class Downlines extends AbstractCachedObj
{
    /** @var int Cached for 0.5 hours */
    protected const CACHE_TTL = 1800;

    /** @var array */
    private array $tier1;
    /** @var array */
    private array $tier2 = [];
    /** @var array */
    private array $tier3 = [];

    /**
     * @param User $user
     * @param bool $useCache
     * @return Downlines
     */
    public static function getInstance(User $user, bool $useCache = true): Downlines
    {
        $instanceId = sprintf('u_%d_downlines', $user->id);
        return static::retrieveInstance($instanceId, $useCache) ?? static::createInstance($instanceId, $useCache, [$user]);
    }

    /**
     * Downlines constructor.
     * @param User $user
     * @throws AppException
     */
    public function __construct(User $user)
    {
        // Get all tier 1 downlines
        $this->tier1 = $this->getUserReferrals(1, $user->id, $user->username, false);
        foreach ($this->tier1 as $directRef) {
            $this->tier2 = array_merge($this->tier2, $this->getUserReferrals(2, $directRef["id"], $directRef["username"], true));
            foreach ($this->tier2 as $indirectRef) {
                $this->tier3 = array_merge($this->tier3, $this->getUserReferrals(3, $indirectRef["id"], $indirectRef["username"], true));
            }
        }
    }

    /**
     * @return array
     */
    public function getTree(): array
    {
        $tree = [];
        foreach ($this->tier1 as $tier1Ref) {
            $tier1Ref["referrals"] = [];
            foreach ($this->tier2 as $tier2Ref) {
                $tier2Ref["referrals"] = [];
                foreach ($this->tier3 as $tier3Ref) {
                    if ($tier3Ref["referrer"] === $tier2Ref["username"]) {
                        $tier2Ref["referrals"][] = $tier3Ref;
                    }
                }

                if ($tier2Ref["referrer"] === $tier1Ref["username"]) {
                    $tier1Ref["referrals"][] = $tier2Ref;
                }
            }

            $tree[] = $tier1Ref;
        }

        return $tree;
    }

    /**
     * @return array
     */
    public function getTiers(): array
    {
        return [
            "tier1" => $this->tier1,
            "tier2" => $this->tier2,
            "tier3" => $this->tier3,
        ];
    }

    /**
     * @param int $tier
     * @param int $userId
     * @param string $username
     * @param bool $hideEmails
     * @return array
     * @throws AppException
     */
    private function getUserReferrals(int $tier, int $userId, string $username, bool $hideEmails): array
    {
        $app = Kernel::getInstance();
        $userReferrals = [];

        try {
            $referrals = Users::Find()->query('WHERE `referrer`=? ORDER BY `id` DESC', [$userId])->all();
        } catch (ORM_ModelNotFoundException $e) {
            $referrals = [];
        } catch (\Exception $e) {
            $app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException(
                sprintf('Failed to retrieve tier %d downlines from user "%s"', $tier, $username)
            );
        }

        /** @var User $referral */
        foreach ($referrals as $referral) {
            try {
                $referral->validate();
            } catch (AppException $e) {
                continue; // Ignore if checksum is not valid
            }

            $userReferrals[] = [
                "tier" => $tier,
                "id" => $referral->id,
                "referrer" => $username,
                "username" => $referral->username,
                "country" => $referral->country,
                "email" => $hideEmails ? $this->hideEmailAddress($referral->email) : $referral->email,
                "joinedDate" => date("d-m-Y", $referral->joinStamp),
            ];
        }

        return $userReferrals;
    }

    /**
     * @param string $email
     * @return string
     */
    private function hideEmailAddress(string $email): string
    {
        return preg_replace_callback('/^.*@/i', function (array $part) {
            return str_repeat("*", strlen(strval($part[0])) - 1) . "@";
        }, $email);
    }
}
