<?php
/**
 * GSC Tesseract MINI
 *
 * @category Framework
 * @author   Fred Brooker <oscadal@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://mini.gscloud.cz
 */

namespace GSC;

use League\CLImate\CLImate;

/**
 * Doctor
 */
class Doctor
{
    /**
     * Doctor constructor
     *
     * @return object Singleton instance
     */
    public function __construct()
    {
        $climate = new CLImate;

        function check_exist($f)
        {
            if (empty($f)) {
                throw new \Exception("Empty parameter!");
            }
            if (!file_exists($f) || !is_readable($f)) {
                return false;
            }
            return true;
        }

        function check_write($f)
        {
            if (empty($f)) {
                throw new \Exception("Empty parameter!");
            }
            if (!is_writable($f)) {
                return false;
            }
            return true;
        }

        /**
         * Display decorated message based on result truthfulness
         *
         * @param string $message
         * @param boolean $result
         * @return void
         */
        function validate($message, $result)
        {
            if (is_null($message)) {
                throw new \Exception("Empty \$message parameter!");
            }
            if (is_null($result)) {
                throw new \Exception("Empty \$result parameter!");
            }
            $climate = new CLImate;
            $result = (bool) $result;
            if ($result) {
                $climate->out("<green><bold>[√]</bold></green> ${message}");
            } else {
                $climate->out("<red><bold>[!]</bold></red> ${message}");
            }
        }

        $climate->out("\n<blue><bold>File System");
        validate("file\t<bold>CONFIG</bold>\tas " . CONFIG, check_exist(CONFIG));
        validate("file\t<bold>CONFIG_PRIVATE</bold>\tas " . CONFIG_PRIVATE, check_exist(CONFIG_PRIVATE));
        validate("directory\t<bold>APP</bold>\tas " . APP, check_exist(APP));
        validate("file\t<bold>router_defaults.neon</bold>\tin APP", check_exist(APP . "/router_defaults.neon"));
        validate("file\t<bold>router_admin.neon</bold>\tin APP", check_exist(APP . "/router_admin.neon"));
        validate("file\t<bold>router.neon</bold>\tin APP", check_exist(APP . "/router.neon"));
        validate("file\t<bold>VERSION</bold>\tas " . ROOT . "/VERSION", check_exist(ROOT . "/VERSION"));
        validate("directory\t<bold>CACHE</bold>\tas " . CACHE, check_exist(CACHE));
        validate("writable\t<bold>CACHE</bold>", check_write(CACHE));
        validate("directory\t<bold>DATA</bold>\tas " . DATA, check_exist(DATA));
        validate("writable\t<bold>DATA</bold>", check_write(DATA));
        validate("directory\t<bold>WWW</bold>\tas " . WWW, check_exist(WWW));
        validate("directory\t<bold>TEMPLATES</bold>\tas " . TEMPLATES, check_exist(TEMPLATES));
        validate("directory\t<bold>PARTIALS</bold>\tas " . PARTIALS, check_exist(PARTIALS));
        validate("directory\t<bold>TEMP</bold>\tas " . TEMP, check_exist(TEMP));
        validate("writable\t<bold>TEMP</bold>", check_write(TEMP));

        $climate->out("\n<blue><bold>PHP");
        validate("version <bold>7.3+", (PHP_VERSION_ID >= 70300));
        validate("lib <bold>curl", (in_array("curl", get_loaded_extensions())));
        validate("lib <bold>json", (in_array("json", get_loaded_extensions())));
        validate("lib <bold>mbstring", (in_array("mbstring", get_loaded_extensions())));
        validate("lib <bold>sodium", (in_array("sodium", get_loaded_extensions())));

        echo "\n";
        return $this;
    }
}
