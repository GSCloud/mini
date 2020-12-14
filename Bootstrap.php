<?php
/**
 * GSC Tesseract
 *
 * @category Framework
 * @author   Fred Brooker <oscadal@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://lasagna.gscloud.cz
 */

use Nette\Neon\Neon;
use Tracy\Debugger;

/** @const Global timer start */
define("TESSERACT_START", microtime(true));

// START
ob_start();
error_reporting(E_ALL);
@ini_set("auto_detect_line_endings", true);
@ini_set("default_socket_timeout", 20);
@ini_set("display_errors", true);

// CONSTANTS (in SPECIFIC ORDER !!!)

/** @const DIRECTORY_SEPARATOR */
defined("DS") || define("DS", DIRECTORY_SEPARATOR);

/** @const Bootstrap root folder */
defined("ROOT") || define("ROOT", __DIR__);

/** @const Application folder */
defined("APP") || define("APP", ROOT . DS . "app");

/** @const Cache and logs folder, defaults to "temp" */
defined("CACHE") || define("CACHE", ROOT . DS . "temp");

/** @const Application data folder, defaults to "data" */
defined("DATA") || define("DATA", ROOT . DS . "data");

/** @const Website assets folder, defaults to "www" */
defined("WWW") || define("WWW", ROOT . DS . "www");

/** @const Configuration file, full path */
defined("CONFIG") || define("CONFIG", APP . DS . "config.neon");

/** @const Private configuration file, full path */
defined("CONFIG_PRIVATE") || define("CONFIG_PRIVATE", APP . DS . "config_private.neon");

/** @const Website templates folder */
defined("TEMPLATES") || define("TEMPLATES", WWW . DS . "templates");

/** @const Website templates partials folder */
defined("PARTIALS") || define("PARTIALS", WWW . DS . "partials");

/** @const Website downloads folder */
defined("DOWNLOAD") || define("DOWNLOAD", WWW . DS . "download");

/** @const Website uploads folder */
defined("UPLOAD") || define("UPLOAD", WWW . DS . "upload");

/** @const Log files folder */
defined("LOGS") || define("LOGS", ROOT . DS . "logs");

/** @const Temporary files folder */
defined("TEMP") || define("TEMP", ROOT . DS . "temp");

/** @const True if running from command line interface */
define("CLI", (bool) (PHP_SAPI == "cli"));

/** @const True if running server locally */
define("LOCALHOST", (bool) (($_SERVER["SERVER_NAME"] ?? "") == "localhost") || CLI);

// COMPOSER
require_once ROOT . DS . "vendor" . DS . "autoload.php";

// CONFIGURATION
if (file_exists(CONFIG) && is_readable(CONFIG)) {
    $cfg = @Neon::decode(file_get_contents(CONFIG));
} else {
    die("FATAL ERROR: Missing main CONFIG!");
}
if (file_exists(CONFIG_PRIVATE) && is_readable(CONFIG_PRIVATE)) {
    $cfg = array_replace_recursive($cfg, @Neon::decode(file_get_contents(CONFIG_PRIVATE)));
}

// DEFAULT TIME ZONE
date_default_timezone_set((string) ($cfg["date_default_timezone"] ?? "Europe/Prague"));

// DEBUGGER
if (($_SERVER["SERVER_NAME"] ?? "") == "localhost") {
    if (($cfg["dbg"] ?? null) === false) {
        defined("DEBUG") || define("DEBUG", false); // DISABLED via configuration
    }
    defined("DEBUG") || define("DEBUG", true); // ENABLED for localhost
}
if (CLI === true) {
    defined("DEBUG") || define("DEBUG", false); // DISABLED for CLI
}
if (isset($_SERVER["HTTP_USER_AGENT"]) && strpos($_SERVER["HTTP_USER_AGENT"], "curl") !== false) {
    defined("DEBUG") || define("DEBUG", false); // DISABLE for curl
}
defined("DEBUG") || define("DEBUG", (bool) ($cfg["dbg"] ?? false)); // set via configuration or DISABLED

if (DEBUG === true) { // https://api.nette.org/3.0/Tracy/Debugger.html
    Debugger::$logSeverity = 15; // https://www.php.net/manual/en/errorfunc.constants.php
    Debugger::$maxDepth = (int) ($cfg["DEBUG_MAX_DEPTH"] ?? 10);
    Debugger::$maxLength = (int) ($cfg["DEBUG_MAX_LENGTH"] ?? 5000);
    Debugger::$scream = (bool) ($cfg["DEBUG_SCREAM"] ?? true);
    Debugger::$showBar = (bool) ($cfg["DEBUG_SHOW_BAR"] ?? true);
    Debugger::$showFireLogger = (bool) ($cfg["DEBUG_SHOW_FIRELOGGER"] ?? false);
    Debugger::$showLocation = (bool) ($cfg["DEBUG_SHOW_LOCATION"] ?? false);
    Debugger::$strictMode = (bool) ($cfg["DEBUG_STRICT_MODE"] ?? true);
    // debug cookie name: tracy-debug
    if ($cfg["DEBUG_COOKIE"] ?? null) {
        $address = $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["REMOTE_ADDR"];
        $debug_cookie = (string) $cfg["DEBUG_COOKIE"]; // private config value
        Debugger::enable(
            "${debug_cookie}@${address}", LOGS, (string) ($cfg["DEBUG_EMAIL"] ?? "")
        );
    } else {
        Debugger::enable(Debugger::DETECT, LOGS);
    }
}

// measure performance - START
Debugger::timer("RUN"); 

require_once APP . DS . "App.php";
