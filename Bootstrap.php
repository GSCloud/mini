<?php
/**
 * GSC Tesseract
 *
 * @author   Fred Brooker <git@gscloud.cz>
 * @category Framework
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://lasagna.gscloud.cz
 */

declare(strict_types=1);

use Nette\Neon\Neon;
use Tracy\Debugger;

/** @const global timer - start */
define('TESSERACT_START', microtime(true));

// require an external include
if (PHP_SAPI == 'cli') {
    $req = getenv('CLI_REQ') ?? null;
    if ($req && file_exists($req) && is_readable($req)) {
        require_once $req;
    }
}

// PHP INI - modify default configuration
ini_set('auto_detect_line_endings', defined('AUTO_DETECT_LINE_ENDINGS') ? AUTO_DETECT_LINE_ENDINGS : 'true');
ini_set('default_socket_timeout', defined('DEFAULT_SOCKET_TIMEOUT') ? DEFAULT_SOCKET_TIMEOUT : '30');
ini_set('display_errors', defined('DISPLAY_ERRORS') ? DISPLAY_ERRORS : 'true');

ob_start();
error_reporting(E_ALL);

// CONSTANTS IN SPECIFIC ORDER *** DO NOT ADD DIRECTORY SEPARATOR TO FOLDER DEFINITIONS!

/** @const directory separator */
defined('DS') || define('DS', DIRECTORY_SEPARATOR);

/** @const root folder */
defined('ROOT') || define('ROOT', __DIR__);

/** @const application folder */
defined('APP') || define('APP', ROOT . DS . 'app');

/** @const cache folder */
defined('CACHE') || define('CACHE', ROOT . DS . 'temp');

/** @const application data folder */
defined('DATA') || define('DATA', ROOT . DS . 'data');

/** @const assets folder */
defined('WWW') || define('WWW', ROOT . DS . 'www');

/** @const configuration file */
defined('CONFIG') || define('CONFIG', APP . DS . 'config.neon');

/** @const private configuration file */
defined('CONFIG_PRIVATE') || define('CONFIG_PRIVATE', APP . DS . 'config_private.neon');

/** @const CSP HEADERS configuration file, full path */
defined('CSP') || define('CSP', APP . DS . 'csp.neon');

/** @const templates folder */
defined('TEMPLATES') || define('TEMPLATES', WWW . DS . 'templates');

/** @const templates partials folder */
defined('PARTIALS') || define('PARTIALS', WWW . DS . 'partials');

/** @const download folder */
defined('DOWNLOAD') || define('DOWNLOAD', WWW . DS . 'download');

/** @const upload folder */
defined('UPLOAD') || define('UPLOAD', WWW . DS . 'upload');

/** @const log files folder */
defined('LOGS') || define('LOGS', ROOT . DS . 'logs');

/** @const temporary files folder */
defined('TEMP') || define('TEMP', ROOT . DS . 'temp');

/** @const TRUE if running from command line interface */
defined('CLI') || define('CLI', (bool) (PHP_SAPI == 'cli'));

/** @const TRUE if running server locally */
defined('LOCALHOST') || define('LOCALHOST', (bool) (($_SERVER['SERVER_NAME'] ?? '') == 'localhost') || CLI);

/** @const TRUE = enable use of extra curl_multi cache for CSV */
defined('ENABLE_CSV_CACHE') || define('ENABLE_CSV_CACHE', true);

// load COMPOSER
require_once ROOT . DS . 'vendor' . DS . 'autoload.php';

// read CONFIGURATION
$cfg = null;
if (file_exists(CONFIG) && is_readable(CONFIG)) {
    $cfg = @Neon::decode(@file_get_contents(CONFIG));
    if (!is_array($cfg)) {
        die("FATAL ERROR: Invalid MAIN CONFIGURATION!\n");
    }
    try {
        if (file_exists(CONFIG_PRIVATE) && is_readable(CONFIG_PRIVATE)) {
            $priv = @Neon::decode(@file_get_contents(CONFIG_PRIVATE));
            if (!is_array($priv)) {
                throw new Exception('FATAL ERROR: PRIVATE CONFIG NOT AN ARRAY');
            }
            $cfg = array_replace_recursive($cfg, $priv);
        }
    } catch (Exception $e) {
        die("FATAL ERROR: Invalid PRIVATE CONFIGURATION!\n");
    }
}
if (!is_array($cfg)) {
    die("FATAL ERROR: Invalid CONFIGURATION!\n");
}

// set DEFAULT TIME ZONE
date_default_timezone_set((string) ($cfg['date_default_timezone'] ?? 'Europe/Prague'));

// set DEBUGGER
if (($_SERVER['SERVER_NAME'] ?? '') == 'localhost') { // for LOCALHOST only
    if (($cfg['dbg'] ?? null) === false) {
        defined('DEBUG') || define('DEBUG', false); // force DISABLED via configuration
    }
    defined('DEBUG') || define('DEBUG', true); // always ENABLED for localhost
}
if (CLI === true) {
    defined('DEBUG') || define('DEBUG', false); // DISABLED for CLI
}
if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'curl') !== false) {
    defined('DEBUG') || define('DEBUG', false); // DISABLED for curl
}
defined('DEBUG') || define('DEBUG', (bool) ($cfg['dbg'] ?? false)); // set via configuration or DISABLED

if (DEBUG === true) { // https://api.nette.org/3.0/Tracy/Debugger.html
    Debugger::$logSeverity = 15; // https://www.php.net/manual/en/errorfunc.constants.php
    Debugger::$maxDepth = (int) ($cfg['DEBUG_MAX_DEPTH'] ?? 10);
    Debugger::$maxLength = (int) ($cfg['DEBUG_MAX_LENGTH'] ?? 5000);
    Debugger::$scream = (bool) ($cfg['DEBUG_SCREAM'] ?? true);
    Debugger::$showBar = (bool) ($cfg['DEBUG_SHOW_BAR'] ?? true);
    Debugger::$showFireLogger = (bool) ($cfg['DEBUG_SHOW_FIRELOGGER'] ?? false);
    Debugger::$showLocation = (bool) ($cfg['DEBUG_SHOW_LOCATION'] ?? false);
    Debugger::$strictMode = (bool) ($cfg['DEBUG_STRICT_MODE'] ?? true);

    // debug cookie name: tracy-debug
    if ($cfg['DEBUG_COOKIE'] ?? null) {
        $address = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
        $debug_cookie = (string) $cfg['DEBUG_COOKIE']; // private config value
        Debugger::enable(
            "${debug_cookie}@${address}", LOGS, (string) ($cfg['DEBUG_EMAIL'] ?? '')
        );
    } else {
        // turn it ON
        Debugger::enable((bool) ($cfg['DEBUG_DEVELOPMENT_MODE'] ?? true) ? Debugger::DEVELOPMENT : Debugger::DETECT, LOGS);
    }
}

// measure runtime performance
Debugger::timer('RUN');

// load the app
require_once APP . DS . 'App.php';
