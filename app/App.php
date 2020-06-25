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

// SANITY CHECK
foreach (["APP", "CACHE", "DATA", "DS", "LOGS", "ROOT", "TEMP"] as $x) {
    if (!\defined($x)) {
        die("FATAL ERROR: sanity check failed!");
    }
}

/** @const Cache prefix */
defined("CACHEPREFIX") || define("CACHEPREFIX",
    "cache_" . (string) ($cfg["app"] ?? sha1($cfg["canonical_url"]) ?? sha1($cfg["goauth_origin"]) ?? "app") . "_");

/** @const Domain name, extracted from $_SERVER array */
defined("DOMAIN") || define("DOMAIN", strtolower(preg_replace("/[^A-Za-z0-9.-]/", "", $_SERVER["SERVER_NAME"] ?? "localhost")));

/** @const Server name, extracted from $_SERVER array */
defined("SERVER") || define("SERVER", strtolower(preg_replace("/[^A-Za-z0-9]/", "", $_SERVER["SERVER_NAME"] ?? "localhost")));

/** @const Project name, default "LASAGNA" */
defined("PROJECT") || define("PROJECT", (string) ($cfg["project"] ?? "LASAGNA"));

/** @const Application name, default "app" */
defined("APPNAME") || define("APPNAME", (string) ($cfg["app"] ?? "app"));

/** @const Monolog filename, full path */
defined("MONOLOG") || define("MONOLOG", LOGS . DS . "MONOLOG_" . SERVER . "_" . PROJECT . ".log");

/** @const Google Cloud Platform project ID */
defined("GCP_PROJECTID") || define("GCP_PROJECTID", $cfg["gcp_project_id"] ?? null);

/** @const Google Cloud Platform JSON auth keys */
defined("GCP_KEYS") || define("GCP_KEYS", $cfg["gcp_keys"] ?? null);

// include GCP keys
if (GCP_KEYS && \file_exists(APP . DS . GCP_KEYS)) {
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
        return;
    }
    if (ob_get_level()) {
        ob_end_clean();
    }
    try {
        $logging = new LoggingClient([
            "projectId" => GCP_PROJECTID,
            "keyFilePath" => APP . GCP_KEYS,
        ]);
        $stack = $logging->logger(PROJECT);
        $stack->write(DOMAIN . " " . $stack->entry($message), [
            "severity" => $severity,
        ]);
    } finally {}
}

// CACHING PROFILES
$cache_profiles = array_replace([
    "csv" => "+100 minutes", // storing CSV
    "day" => "+24 hours",
    "default" => "+5 minutes",
    "hour" => "+60 minutes",
    "limiter" => "+1 seconds", // access limiter
    "minute" => "+60 seconds",
    "page" => "+3 minutes", // public web page, user not logged
    "second" => "+1 seconds",
    "tenminutes" => "+10 minutes",
    "tenseconds" => "+10 seconds",
], (array) ($cfg["cache_profiles"] ?? []));
foreach ($cache_profiles as $k => $v) {
    Cache::setConfig("${k}_file", [
        "className" => "Cake\Cache\Engine\FileEngine", // fallback file engine
        "duration" => $v,
        "lock" => true,
        "path" => CACHE,
        "prefix" => CACHEPREFIX . SERVER . PROJECT . APPNAME . "_",
    ]);
    Cache::setConfig($k, [
        "className" => "Cake\Cache\Engine\RedisEngine",
        "database" => $cfg["redis"]["database"] ?? 0,
        "duration" => $v,
        "fallback" => "${k}_file", // fallback to file engine
        "host" => $cfg["redis"]["host"] ?? "127.0.0.1",
        "password" => $cfg["redis"]["password"] ?? "",
        "path" => CACHE,
        "persistent" => false,
        "port" => $cfg["redis"]["port"] ?? 6379,
        "prefix" => CACHEPREFIX . SERVER . PROJECT . APPNAME . "_",
        "timeout" => $cfg["redis"]["timeout"] ?? 1,
        "unix_socket" => $cfg["redis"]["unix_socket"] ?? "",
    ]);
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
        logger("Error in routing table: $r", Logger::EMERGENCY);
        if (ob_get_level()) {
            ob_end_clean();
        }
        header("HTTP/1.1 500 Internal Server Error");
        echo "<h1>Internal Server Error</h1><h2>Error in routing table</h2>Router: <b>$r</b>";
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

// sethl
if ($router[$view]["sethl"] ?? false) {
    $r = trim(strtolower($_GET["hl"] ?? $_COOKIE["hl"] ?? null));
    switch ($r) {
        case "cs":
        case "en":
            break;

        default:
            $r = null;
    }
    if ($r) {
        \setcookie("hl", $r, time() + 86400 * 31, "/");
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
    "'self';",
]));

// SINGLETON CLASS
$data["controller"] = $p = ucfirst(strtolower($presenter[$view]["presenter"])) . "Presenter";
$controller = "\\GSC\\${p}";
\Tracy\Debugger::timer("PROCESS");
$app = $controller::getInstance()->setData($data)->process();
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

// DATA OUTPUT
echo $data["output"] ?? "";

// DEBUG OUTPUT
if (DEBUG) {
    // remove private information
    unset($data["cf"]);
    unset($data["goauth_secret"]);
    unset($data["goauth_client_id"]);
    unset($data["google_drive_backup"]);
    bdump($data, '$data');
    bdump($app->getIdentity(), "identity");
}
