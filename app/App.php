<?php
/**
 * GSC Tesseract
 *
 * @category Framework
 * @author   Fred Brooker <oscadal@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://lasagna.gscloud.cz
 */

namespace GSC;

use Cake\Cache\Cache;
use Google\Cloud\Logging\LoggingClient;
use Monolog\Logger;
use Nette\Neon\Neon;

// USER-DEFINED ERROR HANDLER
function exception_error_handler($severity, $message, $file, $line)
{
    if (!(error_reporting() & $severity)) { // this error code is not included in error_reporting
        return;
    }
    throw new \Exception("ERROR: $message FILE: $file LINE: $line");
}
set_error_handler("\\GSC\\exception_error_handler");

// SANITY CHECK
foreach (["APP", "CACHE", "DATA", "DS", "LOGS", "ROOT", "TEMP"] as $x) {
    if (!defined($x)) {
        die("FATAL ERROR: Sanity check for '$x' failed!");
    }
}
foreach (["cfg"] as $x) {
    if (!isset($x)) {
        die("FATAL ERROR: Sanity check for '$x' failed!");
    }
}

// DATA POPULATION
$base58 = new \Tuupola\Base58;
$data = $cfg;
$data["cfg"] = $cfg; // backup
$data["GET"] = array_map("htmlspecialchars", $_GET);
$data["POST"] = array_map("htmlspecialchars", $_POST);
$data["VERSION"] = $version = trim(@file_get_contents(ROOT . DS . "VERSION") ?? "", "\r\n");
$data["VERSION_DATE"] = date("j. n. Y", @filemtime(ROOT . DS . "VERSION") ?? time());
$data["VERSION_TIMESTAMP"] = @filemtime(ROOT . DS . "VERSION") ?? time();
$data["REVISIONS"] = (int) trim(@file_get_contents(ROOT . DS . "REVISIONS") ?? "0", "\r\n");
$data["PHP_VERSION"] = PHP_VERSION_ID;
$data["DATA_VERSION"] = null;
$data["cdn"] = $data["CDN"] = DS . "cdn-assets" . DS . $version;
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

/** @const Cache prefix */
$x = $cfg["app"] ?? $cfg["canonical_url"] ?? $cfg["goauth_origin"] ?? "";
defined("CACHEPREFIX") || define("CACHEPREFIX",
    "cache_" . hash("sha256", $x) . "_");

/** @const Domain name, extracted from $_SERVER */
defined("DOMAIN") || define("DOMAIN", strtolower(preg_replace("/[^A-Za-z0-9.-]/", "", $_SERVER["SERVER_NAME"] ?? "localhost")));

/** @const Server name, extracted from $_SERVER */
defined("SERVER") || define("SERVER", strtolower(preg_replace("/[^A-Za-z0-9]/", "", $_SERVER["SERVER_NAME"] ?? "localhost")));

/** @const Project name, default "LASAGNA" */
defined("PROJECT") || define("PROJECT", (string) ($cfg["project"] ?? "LASAGNA"));

/** @const Application name, default "app" */
defined("APPNAME") || define("APPNAME", (string) ($cfg["app"] ?? "app"));

/** @const Monolog log filename, full path */
defined("MONOLOG") || define("MONOLOG", LOGS . DS . "MONOLOG_" . SERVER . "_" . PROJECT . ".log");

/** @const GCP Project ID */
defined("GCP_PROJECTID") || define("GCP_PROJECTID", $cfg["gcp_project_id"] ?? null);

/** @const Google Cloud Platform JSON auth keys */
defined("GCP_KEYS") || define("GCP_KEYS", $cfg["gcp_keys"] ?? null);

// set GCP_KEYS ENV variable
if (GCP_KEYS && file_exists(APP . DS . GCP_KEYS)) {
    putenv("GOOGLE_APPLICATION_CREDENTIALS=" . APP . DS . GCP_KEYS);
}

/**
 * Stackdriver logger
 *
 * @param string $message
 * @param mixed $severity (optional)
 * @return void
 */
function logger($message, $severity = Logger::INFO)
{
    if (empty($message) || is_null(GCP_PROJECTID) || is_null(GCP_KEYS)) {
        return false;
    }
    if (ob_get_level()) {
        ob_end_clean();
    }
    try {
        $logging = new LoggingClient([
            "projectId" => GCP_PROJECTID,
            "keyFilePath" => APP . DS . GCP_KEYS,
        ]);
        $stack = $logging->logger(PROJECT);
        $stack->write(DOMAIN . " " . $stack->entry($message), [
            "severity" => $severity,
        ]);
    } finally {}
    return true;
}

// CACHING PROFILES
$cache_profiles = array_replace([
    "csv" => "+120 minutes", // CSV cold storage
    "day" => "+24 hours",
    "default" => "+5 minutes",
    "fiveminutes" => "+5 minutes",
    "hour" => "+60 minutes",
    "limiter" => "+1 seconds", // access limiter
    "minute" => "+60 seconds",
    "page" => "+7 minutes", // public web page, user not logged
    "second" => "+1 seconds",
    "tenminutes" => "+10 minutes",
    "tenseconds" => "+10 seconds",
    "thirtyminutes" => "+30 minutes",
    "thirtyseconds" => "+30 seconds",
], (array) ($cfg["cache_profiles"] ?? []));

foreach ($cache_profiles as $k => $v) {
    if ($cfg["redis"]["port"] ?? null) {
        // use REDIS
        Cache::setConfig("${k}_file", [
            "className" => "Cake\Cache\Engine\FileEngine", // fallback File engine
            "duration" => $v,
            "lock" => true,
            "path" => CACHE,
            "prefix" => SERVER . "_" . PROJECT . "_" . APPNAME . "_" . CACHEPREFIX,
        ]);
        Cache::setConfig($k, [
            "className" => "Cake\Cache\Engine\RedisEngine",
            "database" => $cfg["redis"]["database"] ?? 0,
            "duration" => $v,
            "fallback" => "${k}_file", // use fallback
            "host" => $cfg["redis"]["host"] ?? "127.0.0.1",
            "password" => $cfg["redis"]["password"] ?? "",
            "path" => CACHE,
            "persistent" => false,
            "port" => $cfg["redis"]["port"] ?? 6379,
            "prefix" => SERVER . "_" . PROJECT . "_" . APPNAME . "_" . CACHEPREFIX,
            "timeout" => $cfg["redis"]["timeout"] ?? 1,
            "unix_socket" => $cfg["redis"]["unix_socket"] ?? "",
        ]);
    } else {
        // no REDIS !!!
        Cache::setConfig("${k}_file", [
            "className" => "Cake\Cache\Engine\FileEngine", // File engine
            "duration" => $v,
            "fallback" => false,
            "lock" => true,
            "path" => CACHE,
            "prefix" => SERVER . "_" . PROJECT . "_" . APPNAME . "_" . CACHEPREFIX,
        ]);
        Cache::setConfig($k, [
            "className" => "Cake\Cache\Engine\FileEngine", // File engine
            "duration" => $v,
            "fallback" => false,
            "lock" => true,
            "path" => CACHE,
            "prefix" => SERVER . "_" . PROJECT . "_" . APPNAME . "_" . CACHEPREFIX,
        ]);
    }
}

// MULTI-SITE PROFILES
$multisite_names = [];
$multisite_profiles = array_replace([
    "default" => [strtolower(trim(str_replace("https://", "", (string) ($cfg["canonical_url"] ?? "")), "/") ?? DOMAIN)],
], (array) ($cfg["multisite_profiles"] ?? []));
foreach ($multisite_profiles as $k => $v) {
    $multisite_names[] = strtolower($k);
}
$profile_index = (string) trim(strtolower($_GET["profile"] ?? "default"));
if (!in_array($profile_index, $multisite_names)) {
    $profile_index = "default";
}
$auth_domain = strtolower(str_replace("https://", "", (string) ($cfg["goauth_origin"] ?? "")));
if (!in_array($auth_domain, $multisite_profiles["default"])) {
    $multisite_profiles["default"][] = $auth_domain;
}

// DATA POPULATION
$data["cache_profiles"] = $cache_profiles;
$data["multisite_profiles"] = $multisite_profiles;
$data["multisite_names"] = $multisite_names;
$data["multisite_profiles_json"] = json_encode($multisite_profiles);

// ROUTING CONFIGURATION
$router = [];
$routes = $cfg["routes"] ?? [ // configuration can override defaults
    "router_defaults.neon",
    "router_admin.neon",
    "router.neon",
];

foreach ($routes as $r) {
    $r = APP . DS . $r;
    if (($content = @file_get_contents($r)) === false) {
        logger("ERROR in routing table: $r", Logger::EMERGENCY);
        if (ob_get_level()) {
            ob_end_clean();
        }
        header("HTTP/1.1 500 Internal Server Error");
        echo "<h1>Internal Server Error</h1><h2>Error in routing tables</h2><h3>$r</h3>";
        exit;
    }
    $router = array_replace_recursive($router, @Neon::decode($content));
}

// ROUTER DEFAULTS
$presenter = [];
$defaults = $router["defaults"] ?? [];
foreach ($router as $k => $v) {
    if ($k == "defaults") {
        continue;
    }
    foreach ($defaults as $i => $j) {
        $router[$k][$i] = $v[$i] ?? $defaults[$i];
    }
    $presenter[$k] = $router[$k];
}

// ROUTER MAPPINGS
$alto = new \AltoRouter();
foreach ($presenter as $k => $v) {
    if (!isset($v["path"])) {
        continue;
    }
    if ($v["path"] == "/") {
        if ($data["request_path_hash"] == "") { // set homepage hash to default language
            $data["request_path_hash"] = hash("sha256", $v["language"]);
        }
    }
    $alto->map($v["method"], $v["path"], $k, "route_${k}");
    if (substr($v["path"], -1) != "/") { // map slash endings
        $alto->map($v["method"], $v["path"] . "/", $k, "route_${k}_x");
    }
}

// DATA POPULATION
$data["presenter"] = $presenter;
$data["router"] = $router;

// CLI HANDLER
if (CLI) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    if (isset($argv[1])) {
        CliPresenter::getInstance()->setData($data)->process()->selectModule($argv[1], $argc, $argv);
        exit;
    }
    CliPresenter::getInstance()->setData($data)->process()->help();
    exit;
}

// ROUTING
$match = $alto->match();
$view = $match ? $match["target"] : ($router["defaults"]["view"] ?? "home");

// DATA POPULATION
$data["match"] = $match;
$data["view"] = $view;

// "sethl" - set home language
if ($router[$view]["sethl"] ?? false) {
    $r = trim(strtolower($_GET["hl"] ?? $_COOKIE["hl"] ?? null));
    switch ($r) {
        case "cs":
        case "de":
        case "en":
        case "sk":
            break;

        default:
            $r = null;
    }
    if ($r) {
        setcookie("hl", $r, time() + 86400 * 31, "/");
        $presenter[$view]["language"] = $r;
        $data["presenter"] = $presenter;
    }
}

// REDIRECTS
if ($router[$view]["redirect"] ?? false) {
    $r = $router[$view]["redirect"];
    if (ob_get_level()) {
        ob_end_clean();
    }
    header("Location: " . $r, true, 303);
    exit;
}

// CSP HEADERS
switch ($presenter[$view]["template"]) {
    case "epub": // skip CSP headers for EPUB reader
        break;

    default:
        header(implode(" ", [
            "Content-Security-Policy: ",
            "default-src",
            "'unsafe-inline'",
            "'self'",
            "https://*;",
            "connect-src",
            "'self'",
            "https://*;",
            "font-src",
            "'self'",
            "'unsafe-inline'",
            "*.gstatic.com;",
            "script-src",
            "*.facebook.net",
            "*.google-analytics.com",
            "*.googleapis.com",
            "*.googletagmanager.com",
            "*.ytimg.com",
            "cdn.onesignal.com",
            "cdn.syndication.twimg.com",
            "cdnjs.cloudflare.com",
            "onesignal.com",
            "platform.twitter.com",
            "static.cloudflareinsights.com",
            "'self'",
            "'unsafe-inline'",
            "'unsafe-eval';",
            "img-src",
            "*",
            "'self'",
            "'unsafe-inline'",
            "data:;",
            "form-action",
            "https://*",
            "'self';",
        ]));
}

// SINGLETON CLASS
$data["controller"] = $p = ucfirst(strtolower($presenter[$view]["presenter"])) . "Presenter";
$controller = "\\GSC\\${p}";
\Tracy\Debugger::timer("PROCESS");
// set and process model
$app = $controller::getInstance()->setData($data)->process();
// get model back
$data = $app->getData();

// ANALYTICS DATA
$events = null;
$data = $app->getData();
$data["country"] = $country = (string) ($_SERVER["HTTP_CF_IPCOUNTRY"] ?? "XX");
$data["running_time"] = $time1 = round((float) \Tracy\Debugger::timer("RUN") * 1000, 2);
$data["processing_time"] = $time2 = round((float) \Tracy\Debugger::timer("PROCESS") * 1000, 2);

// FINAL HEADERS
header("X-Country: $country");
header("X-RunTime: $time1 ms");
header("X-Processing: $time2 ms");
header("X-RateLimiting: {$app->getRateLimit()}");

// ANALYTICS
if (method_exists($app, "SendAnalytics")) {
    $app->setData($data)->SendAnalytics();
}

// OUTPUT
echo $data["output"] ?? "";

// DEBUG
if (DEBUG) {
    // remove private information
    unset($data["cf"]);
    unset($data["goauth_secret"]);
    // dumps
    bdump($app->getIdentity(), "identity");
    bdump($data, 'model');
}
