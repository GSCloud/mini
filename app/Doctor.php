<?php
/**
 * GSC Tesseract
 *
 * @author   Fred Brooker <git@gscloud.cz>
 * @category Framework
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://lasagna.gscloud.cz
 */

namespace GSC;

use League\CLImate\CLImate;

/**
 * Doctor CLI class
 * 
 * @package GSC
 */
class Doctor
{
    /** @var int $errors error counter */
    private int $errors = 0;

    /**
     * Doctor CLI Constructor
     * 
     * @return void
     */
    public function __construct()
    {
        $climate = new CLImate;
        $climate->out("<bold><green>Tesseract CLI</green></bold>");

        $climate->out("\n<blue><bold>File System - FOLDERS");

        $this->validate("<bold>APP</bold> » " . APP, $this->isreadable(APP));
        $this->validate("<bold>CACHE</bold> » " . CACHE, $this->isreadable(CACHE));
        $this->validate("<bold>DATA</bold> » " . DATA, $this->iswritable(DATA));
        $this->validate("<bold>PARTIALS</bold> » " . PARTIALS, $this->isreadable(PARTIALS));
        $this->validate("<bold>TEMP</bold> » " . TEMP, $this->iswritable(TEMP));
        $this->validate("<bold>TEMPLATES</bold> » " . TEMPLATES, $this->isreadable(TEMPLATES));
        $this->validate("<bold>WWW</bold> » " . WWW, $this->isreadable(WWW));

        $climate->out("\n<blue><bold>File System - FILES");

        $this->validate("<bold>CONFIG</bold> » " . CONFIG, $this->isreadable(CONFIG));
        $this->validate("<bold>REVISIONS</bold> in ROOT", $this->isreadable(ROOT . DS . "REVISIONS"));
        $this->validate("<bold>VERSION</bold> in ROOT", $this->isreadable(ROOT . DS . "VERSION"));
        $this->validate("<bold>.env</bold> in ROOT", $this->isreadable(".env"));
        $this->validate("<bold>router.neon</bold> in APP", $this->isreadable(APP . DS . "router.neon"));
        $this->validate("<bold>router_admin.neon</bold> in APP", $this->isreadable(APP . DS . "router_admin.neon"));
        $this->validate("<bold>router_defaults.neon</bold> in APP", $this->isreadable(APP . DS . "router_defaults.neon"));

        $climate->out("\n<blue><bold>File System - WRITABLE");

        $this->validate("<bold>ci</bold>", $this->iswritable(ROOT . DS . "ci"));
        $this->validate("<bold>CACHE</bold>", $this->iswritable(CACHE));
        $this->validate("<bold>DATA</bold>", $this->iswritable(DATA));
        $this->validate("<bold>LOGS</bold>", $this->iswritable(LOGS));
        $this->validate("<bold>TEMP</bold>", $this->iswritable(TEMP));

        $climate->out("\n<blue><bold>PHP Core");

        $this->validate("lib <bold>curl", $this->isloaded("curl"));
        $this->validate("lib <bold>gd", $this->isloaded("gd"));
        $this->validate("lib <bold>imagick", $this->isloaded("imagick"));
        $this->validate("lib <bold>json", $this->isloaded("json"));
        $this->validate("lib <bold>mbstring", $this->isloaded("mbstring"));
        $this->validate("lib <bold>redis",$this->isloaded ("redis"));
        $this->validate("lib <bold>sodium", $this->isloaded("sodium"));
        echo "\n";

        if ($this->errors) {
            $climate->out("Errors: <bold><red>" . $this->errors . "\007\n");
        }
        exit($this->errors);
    }

    /**
     * Check whether file is readable
     *
     * @param string $f filename
     * @return bool result
     */
    private function isreadable($f)
    {
        if (!file_exists($f) || !is_readable($f)) {
            $this->errors++;
            return false;
        }
        return true;
    }

    /**
     * Check whether file is writable
     *
     * @param string $f filename
     * @return bool result
     */
    private function iswritable($f)
    {
        if (!is_writable($f)) {
            $this->errors++;
            return false;
        }
        return true;
    }

    /**
     * Check whether extension is loaded
     *
     * @param string $f extension name
     * @return bool result
     */
    private function isloaded($f)
    {
        if (!in_array($f, get_loaded_extensions())) {
            $this->errors++;
            return false;
        }
        return true;
    }

    /**
     * Validate something
     *
     * @param string $message status message
     * @param bool $result testing result outcome
     * @return void
     */
    function validate($message, $result)
    {
        $climate = new CLImate;
        (bool) $result ? $climate->out("<green><bold>[√]</bold></green> ${message}") : $climate->out("<red><bold>[!]</bold></red> ${message}");
    }
}
