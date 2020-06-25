<?php
/**
 * GSC Tesseract
 *
 * @category Framework
 * @author   Fred Brooker <oscadal@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 */

namespace GSC;

use League\CLImate\CLImate;

/**
 * Doctor
 */
class Doctor
{
    /** @var int $err error counter */
    private static $err = 0;

    /**
     * Increment error counter
     *
     * @return void
     */
    private static function bad()
    {
        self::$err++;
    }

    /**
     * Doctor constructor
     *
     */
    public function __construct()
    {
        $climate = new CLImate;

        function check_exist($f)
        {
            if (empty($f)) {
                throw new \Exception("Empty check_exist() parameter!");
            }
            if (!file_exists($f) || !is_readable($f)) {
                Doctor::bad();
                return false;
            }
            return true;
        }

        function check_write($f)
        {
            if (empty($f)) {
                throw new \Exception("Empty check_write() parameter!");
            }
            if (!is_writable($f)) {
                Doctor::bad();
                return false;
            }
            return true;
        }

        function validate($message, $result)
        {
            if (is_null($message)) {
                throw new \Exception("Empty validate() \$message!");
            }
            if (is_null($result)) {
                throw new \Exception("Empty validate() \$result!");
            }
            $climate = new CLImate;
            (bool) $result ? $climate->out("<green><bold>[√]</bold></green> ${message}") : $climate->out("<red><bold>[!]</bold></red> ${message}");
        }

        $climate->out("\n<blue><bold>File System");
        validate("directory\t<bold>APP</bold> as " . APP, check_exist(APP));
        validate("directory\t<bold>CACHE</bold> as " . CACHE, check_exist(CACHE));
        validate("directory\t<bold>DATA</bold> as " . DATA, check_exist(DATA));
        validate("directory\t<bold>PARTIALS</bold> as " . PARTIALS, check_exist(PARTIALS));
        validate("directory\t<bold>TEMP</bold> as " . TEMP, check_exist(TEMP));
        validate("directory\t<bold>TEMPLATES</bold> as " . TEMPLATES, check_exist(TEMPLATES));
        validate("directory\t<bold>WWW</bold> as " . WWW, check_exist(WWW));
        echo "\n";

        validate("file\t<bold>CONFIG</bold> as " . CONFIG, check_exist(CONFIG));
        validate("file\t<bold>CONFIG_PRIVATE</bold> as " . CONFIG_PRIVATE, check_exist(CONFIG_PRIVATE));
        validate("file\t<bold>REVISIONS</bold> in ROOT", check_exist(ROOT . DS . "REVISIONS"));
        validate("file\t<bold>VERSION</bold> in ROOT", check_exist(ROOT . DS . "VERSION"));
        validate("file\t<bold>_site_cfg.sh</bold> in ROOT", check_exist(ROOT . DS . "_site_cfg.sh"));
        validate("file\t<bold>router.neon</bold> in APP", check_exist(APP . DS . "router.neon"));
        validate("file\t<bold>router_admin.neon</bold> in APP", check_exist(APP . DS . "router_admin.neon"));
        validate("file\t<bold>router_defaults.neon</bold> in APP", check_exist(APP . DS . "router_defaults.neon"));
        echo "\n";

        $climate->out("\n<blue><bold>Writables");
        validate("writable\t<bold>CACHE</bold>", check_write(CACHE));
        validate("writable\t<bold>DATA</bold>", check_write(DATA));
        validate("writable\t<bold>LOGS</bold>", check_write(LOGS));
        validate("writable\t<bold>TEMP</bold>", check_write(TEMP));
        validate("writable\t<bold>ci</bold> in ROOT", check_write(ROOT . DS . "ci"));

        $climate->out("\n<blue><bold>PHP");
        validate("Zend version <bold>7.4+", (PHP_VERSION_ID >= 70400));
        validate("lib <bold>curl", (in_array("curl", get_loaded_extensions())));
        validate("lib <bold>json", (in_array("json", get_loaded_extensions())));
        validate("lib <bold>mbstring", (in_array("mbstring", get_loaded_extensions())));
        validate("lib <bold>sodium", (in_array("sodium", get_loaded_extensions())));
        validate("lib <bold>redis", (in_array("redis", get_loaded_extensions())));
        echo "\n";

        if (self::$err) {
            $climate->out("Errors: <bold><red>" . self::$err . "\007\n");
        }
        exit(self::$err);
    }
}
