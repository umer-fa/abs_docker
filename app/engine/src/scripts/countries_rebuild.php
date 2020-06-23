<?php
declare(strict_types=1);

namespace bin;

use App\Common\Database\Primary\Countries;
use App\Common\Exception\AppException;
use App\Common\Kernel\CLI\AbstractCLIScript;

/**
 * Class countries_rebuild
 * @package bin
 */
class countries_rebuild extends AbstractCLIScript
{
    public const DISPLAY_HEADER = false;
    public const DISPLAY_LOADED_NAME = true;
    public const DISPLAY_TRIGGERED_ERRORS = true;

    /**
     * @throws AppException
     * @throws \App\Common\Exception\AppDirException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\QueryBuildException
     * @throws \Comely\Database\Exception\QueryExecuteException
     */
    public function exec(): void
    {
        // Read Data File
        $this->print('Looking for {yellow}{b}Countries TSV{/} file...');
        $countriesTSVPath = $this->app->dirs()->storage()->suffix("data/countries.tsv");
        $this->inline(sprintf('Path: {cyan}%s{/} ... ', $countriesTSVPath));
        if (!@is_file($countriesTSVPath)) {
            $this->print('{red}Not Found{/}');
            throw new AppException('Countries TSV files not found in path');
        }

        if (!@is_readable($countriesTSVPath)) {
            $this->print('{red}Not Readable{/}');
            throw new AppException('Countries TSV file is not readable');
        }

        $this->print('{green}OK{/}');

        $countriesTSV = file_get_contents($countriesTSVPath);
        if (!$countriesTSVPath) {
            throw new AppException('Failed to read countries TSV file');
        }

        $countriesTSV = preg_split('(\r\n|\n|\r)', trim($countriesTSV));
        $this->print("");
        $this->print(sprintf("Total Countries Found: {green}{invert}%s{/}", count($countriesTSV)));

        $db = $this->app->db()->primary();
        foreach ($countriesTSV as $country) {
            $country = explode("\t", $country);
            if (!$country) {
                throw new AppException('Failed to read a country line');
            }

            $saveCountryQuery = 'INSERT ' . 'INTO `%s` (`name`, `status`, `code`, `code_short`, `dial_code`) ' .
                'VALUES (:name, :status, :code, :codeShort, :dialCode) ON DUPLICATE KEY UPDATE `name`=:name, ' .
                '`code`=:code, `code_short`=:codeShort, `dial_code`=:dialCode';
            $saveCountryData = [
                "name" => $country[0],
                "status" => 1,
                "code" => $country[2],
                "codeShort" => $country[1],
                "dialCode" => $country[3]
            ];

            $this->inline(sprintf('%s {cyan}%s{/} ... ', $saveCountryData["name"], $saveCountryData["code"]));
            $saveCountryQuery = $db->exec(sprintf($saveCountryQuery, Countries::NAME), $saveCountryData);
            if ($saveCountryQuery->isSuccess(false)) {
                $this->print('{green}SUCCESS{/}');
            } else {
                $this->print('{red}FAIL{/}');
            }

            unset($country, $saveCountryQuery, $saveCountryData);
        }
    }
}
