<?php
declare(strict_types=1);

namespace App\Common\Users;

use App\Common\Database\AbstractAppModel;
use App\Common\Exception\AppException;
use Comely\Database\Exception\DatabaseException;

/**
 * Class Tally
 * @package App\Common\Users
 * @property bool $isNew
 */
class Tally extends AbstractAppModel
{
    public const TABLE = \App\Common\Database\Primary\Users\Tally::NAME;
    public const SERIALIZABLE = false;

    /** @var int */
    public int $user;
    /** @var null|int */
    public ?int $lastLogin = null;
    /** @var null|int */
    public ?int $last2fa = null;
    /** @var null|string */
    public ?string $last2faCode = null;
    /** @var null|int */
    public ?int $lastReqSms = null;
    /** @var null|int */
    public ?int $lastReqRec = null;

    /**
     * @throws AppException
     */
    public function save(): void
    {
        try {
            if (!$this->changes()) {
                throw new AppException(sprintf('Cannot update user %d tally, no changes', $this->user));
            }

            $this->query()->where("user", $this->user)->save();
        } catch (AppException $e) {
            throw $e;
        } catch (DatabaseException $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException(sprintf('Failed to save user %d tally', $this->user));
        }
    }
}
