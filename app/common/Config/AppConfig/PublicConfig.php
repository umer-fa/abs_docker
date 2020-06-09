<?php
declare(strict_types=1);

namespace App\Common\Config\AppConfig;

use App\Common\Exception\AppConfigException;
use Comely\Utils\Validator\Exception\InvalidValueException;
use Comely\Utils\Validator\Exception\ValidationException;
use Comely\Utils\Validator\Validator;

/**
 * Class PublicConfig
 * @package App\Common\Config\AppConfig
 */
class PublicConfig
{
    /** @var string */
    private string $title;
    /** @var string */
    private string $domain;

    /**
     * PublicConfig constructor.
     * @param array $args
     * @throws AppConfigException
     */
    public function __construct(array $args)
    {
        try {
            /** @var string $title */
            $title = Validator::String($args["title"])->match('/^[\w\s\-]{2,16}$/')->validate();
        } catch (ValidationException $e) {
            throw new AppConfigException(sprintf('Public[title]: %s', get_class($e)));
        }

        $this->title = $title;

        try {
            /** @var string $domain */
            $domain = Validator::String($args["domain"])->validate(function (string $domain) {
                $domain = \App\Common\Validator::isValidHostname($domain);
                if (!is_string($domain)) {
                    throw new InvalidValueException();
                }

                return $domain;
            });
        } catch (ValidationException $e) {
            throw new AppConfigException(sprintf('Cache[host]: %s', get_class($e)));
        }

        $this->domain = $domain;
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function domain(): string
    {
        return $this->domain;
    }

    /**
     * @return array
     */
    public function array(): array
    {
        return [
            "title" => $this->title,
            "domain" => $this->domain
        ];
    }
}
