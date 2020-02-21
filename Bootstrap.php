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

// START
list($usec, $sec) = explode(" ", microtime());
ob_start();
error_reporting(E_ALL);
@ini_set("auto_detect_line_endings", true);
@ini_set("default_socket_timeout", 15);
@ini_set("display_errors", true);

// CONSTANTS

/** @const Global timer start */
define("TESSERACT_START", ((float) $usec + (float) $sec));

/** @const Bootstrap root folder */
defined("ROOT") || define("ROOT", __DIR__);

/** @const Application folder */
defined("APP") || define("APP", ROOT . "/app");

/** @const Cache and logs folder, defaults to "cache" */
defined("CACHE") || define("CACHE", ROOT . "/cache");

/** @const Application data folder, defaults to "data" */
defined("DATA") || define("DATA", ROOT . "/data");

/** @const Website assets folder, defaults to "www" */
defined("WWW") || define("WWW", ROOT . "/www");

/** @const Configuration file, full path */
defined("CONFIG") || define("CONFIG", ROOT . "/config.neon");

/** @const Private configuration file, full path */
defined("CONFIG_PRIVATE") || define("CONFIG_PRIVATE", ROOT . "/config_private.neon");

/** @const Application templates folder, defaults to "www/templates" */
defined("TEMPLATES") || define("TEMPLATES", WWW . "/templates");

/** @const Application template partials folder, defaults to "www/partials" */
defined("PARTIALS") || define("PARTIALS", WWW . "/partials");

/** @const Application downloads folder, defaults to "www/download" */
defined("DOWNLOAD") || define("DOWNLOAD", WWW . "/download");

/** @const Application uploads folder, defaults to "www/upload" */
defined("UPLOAD") || define("UPLOAD", WWW . "/upload");

/** @const Temporary folder, defaults to "/tmp" */
defined("TEMP") || define("TEMP", "/tmp");

/** @const True if running from command line interface */
define("CLI", (bool) (PHP_SAPI === "cli"));

/** @const True if running server locally */
define("LOCALHOST", (bool) (($_SERVER["SERVER_NAME"] ?? "") == "localhost") || CLI);

// COMPOSER
require_once ROOT . "/vendor/autoload.php";

// CONFIGURATION
$cfg = @Neon::decode(@file_get_contents(CONFIG));
if (file_exists(CONFIG_PRIVATE)) {
    $cfg = array_replace_recursive($cfg, @Neon::decode(@file_get_contents(CONFIG_PRIVATE)));
}
date_default_timezone_set((string) ($cfg["date_default_timezone"] ?? "Europe/Prague"));

// DEBUGGER CONFIGURATION
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
    Debugger::$maxDepth = (int) ($cfg["DEBUG_MAX_DEPTH"] ?? 5);
    Debugger::$maxLength = (int) ($cfg["DEBUG_MAX_LENGTH"] ?? 500);
    Debugger::$scream = (bool) ($cfg["DEBUG_SCREAM"] ?? true);
    Debugger::$showBar = (bool) ($cfg["DEBUG_SHOW_BAR"] ?? true);
    Debugger::$showFireLogger = (bool) ($cfg["DEBUG_SHOW_FIRELOGGER"] ?? false);
    Debugger::$showLocation = (bool) ($cfg["DEBUG_SHOW_LOCATION"] ?? false);
    Debugger::$strictMode = (bool) ($cfg["DEBUG_STRICT_MODE"] ?? false);
    // debug cookie name: tracy-debug
    if ($cfg["DEBUG_COOKIE"] ?? null) {
        $address = $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["REMOTE_ADDR"];
        $debug_cookie = (string) $cfg["DEBUG_COOKIE"]; // private config value
        Debugger::enable(
            "${debug_cookie}@${address}", CACHE, (string) ($cfg["DEBUG_EMAIL"] ?? "")
        );
    } else {
        Debugger::enable(Debugger::DETECT, CACHE);
    }
    Debugger::timer("RUNNING"); // start measuring performance
}

// DATA POPULATION
$base58 = new \Tuupola\Base58;
$data = (array) $cfg;
$data["cfg"] = $cfg;
$data["VERSION"] = $version = trim(@file_get_contents(ROOT . "/VERSION") ?? "", "\r\n");
$data["VERSION_DATE"] = date("j. n. Y", @filemtime(ROOT . "/VERSION") ?? time());
$data["REVISIONS"] = (int) trim(@file_get_contents(ROOT . "/REVISIONS") ?? "0", "\r\n");
$data["cdn"] = $data["CDN"] = "/cdn-assets/$version";
$data["host"] = $data["HOST"] = $host = $_SERVER["HTTP_HOST"] ?? "";
$data["base"] = $data["BASE"] = $host ? (($_SERVER["HTTPS"] ?? "off" == "on") ? "https://${host}/" : "http://${host}/") : "";
$data["request_uri"] = $_SERVER["REQUEST_URI"] ?? "";
$data["request_path"] = $rqp = trim(trim(strtok($_SERVER["REQUEST_URI"] ?? "", "?&"), "/"));
$data["request_path_hash"] = ($rqp == "") ? "" : hash("sha256", $rqp);
$data["LOCALHOST"] = (bool) (($_SERVER["SERVER_NAME"] ?? "") == "localhost") || CLI;
$data["VERSION_SHORT"] = $base58->encode(base_convert(substr(hash("sha256", $version), 0, 4), 16, 10));
$data["nonce"] = $data["NONCE"] = $nonce = substr(hash("sha256", random_bytes(8) . (string) time()), 0, 4);
$data["utm"] = $data["UTM"] = "?utm_source=${host}&utm_medium=website&nonce=${nonce}";
$data["ALPHA"] = (in_array($host, (array) ($cfg["alpha_hosts"] ?? [])));
$data["BETA"] = (in_array($host, (array) ($cfg["beta_hosts"] ?? [])));

require_once APP . "/App.php";
