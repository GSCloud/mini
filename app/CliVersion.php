<?php
/**
 * GSC Tesseract
 *
 * @category Framework
 * @author   Fred Brooker <git@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 */

namespace GSC;

/**
 * CLI Version
 */
class CliVersion extends APresenter
{
    // nothing to do here
    public function __construct() {}

    /**
     * Show version information as JSON formatted string
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
        echo \json_encode($out, JSON_PRETTY_PRINT)."\n";
        exit(0);
    }
}
