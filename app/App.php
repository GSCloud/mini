<?php
/**
 * GSC Tesseract
 *
 * @category Framework
 * @author   Fred Brooker <oscadal@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 */

namespace GSC;

use Cake\Cache\Cache;
use GSC\CliPresenter;
use Google\Cloud\Logging\LoggingClient;
use Monolog\Logger;
use Nette\Neon\Neon;

// sanity check
$x = "FATAL ERROR: broken chain of trust";
defined("APP") || die($x);
defined("CACHE") || die($x);
defined("CLI") || die($x);
defined("ROOT") || die($x);

/** @const Cache prefix */
defined("CACHEPREFIX") || define("CACHEPREFIX", "cakephpcache_");
/** @const Domain name, extracted from SERVER array */
defined("DOMAIN") || define("DOMAIN", strtolower(preg_replace("/[^A-Za-z0-9.-]/", "", $_SERVER["SERVER_NAME"] ?? "localhost")));
/** @const Project name */
defined("PROJECT") || define("PROJECT", (string) ($cfg["project"] ?? "TESSERACT"));
/** @const Server name, extracted from SERVER array */
defined("SERVER") || define("SERVER", strtolower(preg_replace("/[^A-Za-z0-9]/", "", $_SERVER["SERVER_NAME"] ?? "localhost")));
/** @const Monolog filename, full path */
defined("MONOLOG") || define("MONOLOG", CACHE . "/MONOLOG_" . SERVER . "_" . PROJECT . "_" . ".log");
/** @const Google Cloud Platform project ID */
defined("GCP_PROJECTID") || define("GCP_PROJECTID", $cfg["gcp_project_id"] ?? null);
/** @const Google Cloud Platform JSON authentication keys */
defined("GCP_KEYS") || define("GCP_KEYS", $cfg["gcp_keys"] ?? null);
if (GCP_KEYS) {
    putenv("GOOGLE_APPLICATION_CREDENTIALS=" . APP . GCP_KEYS);
}

// STACKDRIVER
function logger($message, $severity = Logger::INFO)
{
    if (empty($message)) {
        return;
    }

    if (is_null(GCP_PROJECTID)) {
        return;
    }

    if (is_null(GCP_KEYS)) {
        return;
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

// CACHE
$cache_profiles = array_replace([
    "default" => "+3 minutes",
    "limiter" => "+2 seconds",
    "page" => "+10 seconds",
], (array) ($cfg["cache_profiles"] ?? []));
foreach ($cache_profiles as $k => $v) {
    Cache::setConfig($k, [
        "className" => "File",
        "duration" => $v,
        "path" => CACHE,
        "prefix" => CACHEPREFIX . SERVER . "_" . PROJECT . "_",
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

// DATA
$data["cache_profiles"] = $cache_profiles;
$data["multisite_names"] = $multisite_names;
$data["multisite_profiles"] = $multisite_profiles;
$data["multisite_profiles_json"] = json_encode($multisite_profiles);

// ROUTES
$routes = $cfg["routes"] ?? [
    APP . "/router_defaults.neon",
    APP . "/router_admin.neon",
    APP . "/router.neon",
];
$router = [];
foreach ($routes as $r) {
    if (($content = @file_get_contents($r)) === false) {
        logger("Error in routing table: $r", Logger::EMERGENCY);
        if (ob_get_level()) {
            ob_end_clean();
        }
        header("HTTP/1.1 500 Internal Server Error");
        echo "<h1>Internal Server Error</h1><h2>Error in routing table</h2><h3>Router: $r</h3>";
        exit;
    }
    $router = array_replace_recursive($router, @Neon::decode($content));
}

// DEFAULTS
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

// MAPPINGS
$alto = new \AltoRouter();
foreach ($presenter as $k => $v) {
    if (!isset($v["path"])) { // skip presenters without path
        continue;
    }
    $alto->map($v["method"], $v["path"], $k, "route_${k}");
    if (substr($v["path"], -1) != "/") { // map duplicates ending with slash
        $alto->map($v["method"], $v["path"] . "/", $k, "route_${k}_slash");
    }
}
$data["presenter"] = $presenter;
$data["router"] = $router;

// CLI
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
$data["match"] = $match;
$data["view"] = $view;

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
header(implode(" ", $cfg["csp_headers"] ?? [
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
    "cdnjs.cloudflare.com",
    "*.facebook.net",
    "*.google-analytics.com",
    "*.googleapis.com",
    "*.googletagmanager.com",
    "*.ytimg.com",
    "cdn.onesignal.com",
    "onesignal.com",
    "platform.twitter.com",
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

// SINGLETON
$data["controller"] = $p = ucfirst(strtolower($presenter[$view]["presenter"])) . "Presenter";
$controller = "\\GSC\\$p";
\Tracy\Debugger::timer("PROCESSING");   // measuring performance
$app = $controller::getInstance()->setData($data)->process();

// ANALYTICS
$events = null;
$data = $app->getData();
$data["country"] = $country = (string) ($_SERVER["HTTP_CF_IPCOUNTRY"] ?? "");
$data["running_time"] = $time1 = round((float) \Tracy\Debugger::timer("RUNNING") * 1000, 2);
$data["processing_time"] = $time2 = round((float) \Tracy\Debugger::timer("PROCESSING") * 1000, 2);

// FINAL HEADERS
header("X-Country: $country");
header("X-Runtime: $time1 msec.");
header("X-Processing: $time2 msec.");

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
    unset($data["goauth_client_id"]);
    unset($data["google_drive_backup "]);
    bdump($data, '$data');
    bdump($app->getIdentity(), "identity");
}
