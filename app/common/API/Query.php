<?php
declare(strict_types=1);

namespace App\Common\API;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\API\Queries;
use App\Common\Database\API\QueriesPayload;
use App\Common\Exception\AppException;
use App\Common\Validator;
use Comely\DataTypes\Buffer\Binary;

/**
 * Class Query
 * @package App\Common\API
 */
class Query extends AbstractAppModel
{
    public const TABLE = Queries::NAME;
    public const SERIALIZABLE = false;

    /** @var int */
    public int $id;
    /** @var string */
    public string $ipAddress;
    /** @var string */
    public string $method;
    /** @var string */
    public string $endpoint;
    /** @var float */
    public float $startOn;
    /** @var float */
    public float $endOn;
    /** @var null|int */
    public ?int $resCode = null;
    /** @var null|int */
    public ?int $resLen = null;
    /** @var null|int */
    public ?int $flagUserId = null;

    /** @var string */
    public string $_hexId;
    /** @var null|QueryPayload */
    private ?QueryPayload $_payload = null;
    /** @var null|bool */
    public ?bool $_checksumVerified = null;
    /** @var int */
    public int $_timeStamp;
    /** @var string */
    public string $_timeElapsed;

    /**
     * @return void
     */
    public function onLoad(): void
    {
        parent::onLoad();
        $this->_hexId = dechex($this->id);
        $this->_timeStamp = intval(explode(".", strval($this->startOn))[0]);
        $this->_timeElapsed = number_format($this->endOn - $this->startOn, 4, ".", "");
    }

    /**
     * @return void
     */
    public function beforeQuery(): void
    {
        if (strlen($this->endpoint) > 512) {
            $this->endpoint = substr($this->endpoint, 0, 512);
        }
    }

    /**
     * @throws AppException
     * @throws \App\Common\Exception\AppConfigException
     */
    public function validateChecksum(): void
    {
        if ($this->checksum()->raw() !== $this->private("checksum")) {
            throw new AppException(sprintf('Invalid checksum of API query ray # %d', $this->id));
        }

        $this->_checksumVerified = true;
    }

    /**
     * @return Binary
     * @throws \App\Common\Exception\AppConfigException
     */
    public function checksum(): Binary
    {
        $raw = sprintf(
            '%d:%s:%s:%s:%s:%s:%s:%s:%s:%d',
            $this->id,
            $this->ipAddress,
            strtolower(trim($this->method)),
            strtolower(trim($this->endpoint)),
            $this->startOn,
            $this->endOn,
            $this->resCode ?? 0,
            $this->resLen ?? 0,
            $this->private("flagApiSess") ?? "",
            $this->flagUserId ?? 0
        );

        return $this->app->ciphers()->secondary()->pbkdf2("sha1", $raw, 1000);
    }

    /**
     * @return QueryPayload
     * @throws AppException
     */
    public function payload(): QueryPayload
    {
        if ($this->_payload) {
            return $this->_payload;
        }

        try {
            $apiLogsDb = $this->app->db()->apiLogs();
            $row = $apiLogsDb->query()->table(QueriesPayload::NAME)
                ->where("`query`=?", [$this->id])
                ->fetch();
            if ($row->count() !== 1) {
                throw new AppException('API query payload row not found');
            }

            $encrypted = $row->first()["encrypted"];
            if (!$encrypted || !is_string($encrypted)) {
                throw new AppException('Failed to retrieve encrypted payload blob');
            }

            $payload = $this->app->ciphers()->secondary()->decrypt(new Binary($encrypted));
            if (!$payload instanceof QueryPayload) {
                throw new AppException('Failed to decrypt API query payload');
            }
        } catch (AppException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->app->errors()->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('Failed to retrieve query payload');
        }

        $this->_payload = $payload;
        return $this->_payload;
    }

    /**
     * @return array
     * @throws AppException
     */
    public function array(): array
    {
        $jsonFiltered = Validator::JSON_Filter($this, sprintf("api.Query[%d]", $this->id));
        $jsonFiltered["payload"] = null;
        if ($this->_payload) {
            $jsonFiltered["payload"] = $this->_payload->array();
        }

        return $jsonFiltered;
    }
}
