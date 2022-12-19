<?php
/**
 * GSC Tesseract
 *
 * @author   Fred Brooker <git@gscloud.cz>
 * @category Framework
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://app.gscloud.cz
 */

namespace GSC;

/**
 * CLI Version class
 *
 * @package GSC
 */
class CliVersion extends APresenter
{
    /**
     * Controller constructor
     */
    public function __construct()
    {}

    /**
     * Controller processor
     * 
     * Show version information as a JSON formatted string.
     *
     * @return void
     */
    public function process()
    {
        $data = $this->getData();
        $out = [
            "TESSERACT" => "Tesseract 2.0 beta",
            "ARGUMENTS" => $data["ARGV"],
            "REVISIONS" => $data["REVISIONS"],
            "VERSION" => $data["VERSION"],
            "VERSION_SHORT" => $data["VERSION_SHORT"],
            "VERSION_DATE" => $data["VERSION_DATE"],
            "VERSION_TIMESTAMP" => $data["VERSION_TIMESTAMP"],
        ];
        echo \json_encode($out, JSON_PRETTY_PRINT) . "\n";
        exit(0);
    }
}
