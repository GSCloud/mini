<?php
/**
 * GSC Tesseract MINI
 *
 * @category Framework
 * @author   Fred Brooker <oscadal@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 */

namespace GSC;

use Cake\Cache\Cache;
use Exception;
use Google\Cloud\Logging\LoggingClient;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\BrowserConsoleHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\GitProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\WebProcessor;
use ParagonIE\Halite\Cookie;
use ParagonIE\Halite\KeyFactory;

interface IPresenter
{
    /** messages */
    public function addCritical($message);
    public function addError($message);
    public function addMessage($message);
    public function getCriticals();
    public function getErrors();
    public function getMessages();

    /** getters */
    public function getCfg($key);
    public function getCookie($name);
    public function getCurrentUser();
    public function getData($key);
    public function getIdentity();
    public function getMatch();
    public function getPresenter();
    public function getRouter();
    public function getUID();
    public function getUIDstring();
    public function getUserGroup();
    public function getView();

    /** checks */
    public function checkPermission($role);
    public function checkRateLimit($maximum);

    /** setters */
    public function setCookie($name, $data);
    public function setData($data, $value);
    public function setHeaderCsv();
    public function setHeaderFile();
    public function setHeaderHtml();
    public function setHeaderJavaScript();
    public function setHeaderJson();
    public function setHeaderPdf();
    public function setHeaderText();
    public function setIdentity($identity);
    public function setLocation($location, $code);

    /** tools */
    public function clearCookie($name);
    public function cloudflarePurgeCache($cf);
    public function dataExpander(&$data);
    public function logout();
    public function renderHTML($template);
    public function writeJsonData($data, $headers);

    /** abstract */
    public function process();

    /** singleton */
    public static function getInstance();
    public static function getTestInstance(); // Unit Tests
}

abstract class APresenter implements IPresenter
{
    /** @var integer Octal file mode for logs */
    const LOG_FILEMODE = 0664;

    /** @var integer Octal file mode for cookie secret */
    const COOKIE_KEY_FILEMODE = 0600;

    /** @var integer Cookie time to live */
    const COOKIE_TTL = 86400 * 15;

    /** @var integer Access limiter maximum hits */
    const LIMITER_MAXIMUM = 50;

    /** @var string Identity nonce filename */
    const IDENTITY_NONCE = "identity_nonce.key";

    // PRIVATE VARS

    /** @var array $data Model array */
    private $data = [];

    /** @var array $messages Array of messages */
    private $messages = [];

    /** @var array $errors Array of errors */
    private $errors = [];

    /** @var array $criticals Array of critical errors */
    private $criticals = [];

    /** @var array $identity Identity associative array */
    private $identity = [];

    /** @var array $cookies Array of saved cookies */
    private $cookies = [];

    /** @var array $instances Array of singleton instances */
    private static $instances = [];

    /**
     * Abstract processor
     *
     * @abstract
     * @return void
     */
    abstract public function process();

    /**
     * Class constructor
     */
    final private function __construct()
    {
        $class = get_called_class();
        if (array_key_exists($class, self::$instances)) {
            throw new \Exception("INSTANCE OF [" . $class . "] ALREADY EXISTS!");
        }
    }

    /**
     * Magic clone
     *
     * @return void
     */
    final private function __clone()
    {}

    /**
     * Magic sleep
     *
     * @return void
     */
    final private function __sleep()
    {}

    /**
     * Magic wakeup
     *
     * @return void
     */
    final private function __wakeup()
    {}

    /**
     * Magic call
     *
     * @param string $name
     * @param mixed $parameter
     * @return void
     */
    final public function __call($name, $parameter)
    {}

    /**
     * Magic call static
     *
     * @param string $name
     * @param mixed $parameter
     * @return void
     */
    final public static function __callStatic($name, $parameter)
    {}

    /**
     * Object to string
     *
     * @return string Serialized JSON model
     */
    final public function __toString()
    {
        return (string) json_encode($this->getData(), JSON_PRETTY_PRINT);
    }

    /**
     * Class destructor
     */
    public function __destruct()
    {
        if (ob_get_level()) {
            ob_flush();
        }
        ob_start();

        $monolog = new Logger("Tesseract log");
        $streamhandler = new StreamHandler(MONOLOG, Logger::INFO, true, self::LOG_FILEMODE);
        $streamhandler->setFormatter(new LineFormatter);
        $consolehandler = new BrowserConsoleHandler(Logger::INFO);
        $monolog->pushHandler($consolehandler);
        $monolog->pushHandler($streamhandler);
        $monolog->pushProcessor(new GitProcessor);
        $monolog->pushProcessor(new MemoryUsageProcessor);
        $monolog->pushProcessor(new WebProcessor);

        $criticals = $this->getCriticals();
        $errors = $this->getErrors();
        $messages = $this->getMessages();

        list($usec, $sec) = explode(" ", microtime());
        defined("TESSERACT_STOP") || define("TESSERACT_STOP", ((float) $usec + (float) $sec));
        $add = "| processing: " . round(((float) TESSERACT_STOP - (float) TESSERACT_START) * 1000, 2) . " msec."
            . "| request_uri: " . ($_SERVER["REQUEST_URI"] ?? "N/A");

        try {
            if (count($criticals) + count($errors) + count($messages)) {
                if (GCP_PROJECTID && GCP_KEYS) {
                    $logging = new LoggingClient([
                        "projectId" => GCP_PROJECTID,
                        "keyFilePath" => APP . GCP_KEYS,
                    ]);
                    $google_logger = $logging->logger(PROJECT);
                } else {
                    $google_logger = null;
                }
            }
            if (count($criticals)) {
                $monolog->critical(DOMAIN . " FATAL: " . json_encode($criticals) . $add);
                if ($google_logger) {
                    $google_logger->write($google_logger->entry(DOMAIN . " ERR: " . json_encode($criticals) . $add, [
                        "severity" => Logger::CRITICAL,
                    ]));
                }

            }
            if (count($errors)) {
                $monolog->error(DOMAIN . " ERROR: " . json_encode($errors) . $add);
                if ($google_logger) {
                    $google_logger->write($google_logger->entry(DOMAIN . " ERR: " . json_encode($errors) . $add, [
                        "severity" => Logger::ERROR,
                    ]));
                }

            }
            if (count($messages)) {
                $monolog->info(DOMAIN . " INFO: " . json_encode($messages) . $add);
                if ($google_logger) {
                    $google_logger->write($google_logger->entry(DOMAIN . " MSG: " . json_encode($messages) . $add, [
                        "severity" => Logger::INFO,
                    ]));
                }

            }
        } finally {}
    }

    /**
     * Get singleton object
     *
     * @static
     * @final
     * @return object Singleton instance
     */
    final public static function getInstance()
    {
        $class = get_called_class();
        if (array_key_exists($class, self::$instances) === false) {
            self::$instances[$class] = new $class();
        }
        return self::$instances[$class];
    }

    /**
     * Get instance for unit testing - DO NOT USE IN PRODUCTION!!!
     *
     * @static
     * @final
     * @return object Class instance
     */
    final public static function getTestInstance()
    {
        $class = get_called_class();
        return new $class();
    }

    /**
     * Render HTML content from given template
     *
     * @param string $template Template name
     * @return string HTML output
     */
    public function renderHTML($template = "index")
    {
        if (empty($template)) {
            return "";
        }
        $type = (file_exists(TEMPLATES . "/${template}.mustache")) ? true : false;
        $renderer = new \Mustache_Engine(array(
            "template_class_prefix" => "__" . SERVER . "_" . PROJECT . "_",
            "cache" => CACHE,
            "cache_file_mode" => 0666,
            "cache_lambda_templates" => true,
            "loader" => $type ? new \Mustache_Loader_FilesystemLoader(TEMPLATES) : new \Mustache_Loader_StringLoader,
            "partials_loader" => new \Mustache_Loader_FilesystemLoader(PARTIALS),
            "helpers" => [
                "unix_timestamp" => function () {
                    return (string) time();
                },
                "sha256_nonce" => function () {
                    return substr(hash("sha256", random_bytes(8) . (string) time()), 0, 8);
                },
            ],
            "charset" => "UTF-8",
            "escape" => function ($value) {
                return $value;
            },
        ));
        if ($type) {
            return $renderer->loadTemplate($template)->render($this->getData());
        } else {
            return $renderer->render($template, $this->getData());
        }
    }

    /**
     * Data getter
     *
     * @param string $key array key, dot notation (optional)
     * @return mixed value / whole array
     */
    public function getData($key = null)
    {
        $dot = new \Adbar\Dot((array) $this->data);

        // global constants
        $dot->set(
            [
                "CONST.APP" => APP,
                "CONST.CACHE" => CACHE,
                "CONST.CACHEPREFIX" => CACHEPREFIX,
                "CONST.CLI" => CLI,
                "CONST.DATA" => DATA,
                "CONST.DOMAIN" => DOMAIN,
                "CONST.DOWNLOAD" => DOWNLOAD,
                "CONST.MONOLOG" => MONOLOG,
                "CONST.PARTIALS" => PARTIALS,
                "CONST.PROJECT" => PROJECT,
                "CONST.ROOT" => ROOT,
                "CONST.SERVER" => SERVER,
                "CONST.TEMP" => TEMP,
                "CONST.TEMPLATES" => TEMPLATES,
                "CONST.UPLOAD" => UPLOAD,
                "CONST.WWW" => WWW,
            ]
        );

        // class constants
        $dot->set(
            [
                "CONST.COOKIE_KEY_FILEMODE" => self::COOKIE_KEY_FILEMODE,
                "CONST.COOKIE_TTL" => self::COOKIE_TTL,
                "CONST.LIMITER_MAXIMUM" => self::LIMITER_MAXIMUM,
                "CONST.LOG_FILEMODE" => self::LOG_FILEMODE,
            ]
        );

        if (is_string($key)) {
            return $dot->get($key);
        }
        $this->data = (array) $dot->all();
        return $this->data;
    }

    /**
     * Data setter
     *
     * @param mixed $data array / key
     * @param mixed $value
     * @return object Singleton instance
     */
    public function setData($data = null, $value = null)
    {
        if (is_array($data)) {
            // $data is the new model = replace it
            $this->data = $data;
        } else {
            // $data is the index to current model
            $key = $data;
            if (is_string($key) && !empty($key)) {
                $dot = new \Adbar\Dot($this->data);
                $dot->set($key, $value);
                $this->data = (array) $dot->all();
            }
        }
        return $this;
    }

    /**
     * Messages getter
     *
     * @return array Array of messages
     */
    public function getMessages()
    {
        return (array) $this->messages;
    }

    /**
     * Errors getter
     *
     * @return array Array of errors
     */
    public function getErrors()
    {
        return (array) $this->errors;
    }

    /**
     * Criticals getter
     *
     * @return array Array of critical messages
     */
    public function getCriticals()
    {
        return (array) $this->criticals;
    }

    /**
     * Add info message
     *
     * @param string $message message
     * @return object Singleton instance
     */
    public function addMessage($message = null)
    {
        if (!is_null($message) || !empty($message)) {
            $this->messages[] = (string) $message;
        }
        return $this;
    }

    /**
     * Add error message
     *
     * @param string $message message
     * @return object Singleton instance
     */
    public function addError($message = null)
    {
        if (!is_null($message) || !empty($message)) {
            $this->errors[] = (string) $message;
        }
        return $this;
    }

    /**
     * Add critical message
     *
     * @param string $message message
     * @return object Singleton instance
     */
    public function addCritical($message = null)
    {
        if (!is_null($message) || !empty($message)) {
            $this->criticals[] = (string) $message;
        }
        return $this;
    }

    /**
     * Get universal ID string
     *
     * @return string Universal ID string
     */
    public function getUIDstring()
    {
        return strtr(implode("_",
            [
                $_SERVER["HTTP_ACCEPT"] ?? "NA",
                $_SERVER["HTTP_ACCEPT_CHARSET"] ?? "NA",
                $_SERVER["HTTP_ACCEPT_ENCODING"] ?? "NA",
                $_SERVER["HTTP_ACCEPT_LANGUAGE"] ?? "NA",
                $_SERVER["HTTP_USER_AGENT"] ?? "UA",
                $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["REMOTE_ADDR"] ?? "NA",
            ]),
            " ", "_");
    }

    /**
     * Get universal ID hash
     *
     * @return string Universal ID SHA256 hash
     */
    public function getUID()
    {
        return hash("sha256", $this->getUIDstring());
    }

    /**
     * Set user identity
     *
     * @param array $identity Identity associative array
     * @return object Singleton instance
     */
    public function setIdentity($identity = [])
    {
        if (!is_array($identity)) {
            $identity = [];
        }
        $i = [
            "avatar" => "",
            "country" => "",
            "email" => "",
            "id" => 0,
            "ip" => "",
            "name" => "",
        ];
        $file = DATA . "/" . self::IDENTITY_NONCE;

        // random nonce
        if (!file_exists($file)) {
            try {
                $nonce = hash("sha256", random_bytes(256) . time());
                file_put_contents($file, $nonce);
                @chmod($file, 0660);
                $this->addMessage("ADMIN: nonce file created");
            } catch (Exception $e) {
                $this->addError("500: Internal Server Error -> cannot create nonce file");
                $this->setLocation("/err/500");
                exit;
            }
        }
        $nonce = @file_get_contents($file);
        $i["nonce"] = substr(trim($nonce), 0, 8);

        // check all keys
        if (array_key_exists("avatar", $identity)) {
            $i["avatar"] = (string) $identity["avatar"];
        }
        if (array_key_exists("email", $identity)) {
            $i["email"] = (string) $identity["email"];
        }
        if (array_key_exists("id", $identity)) {
            $i["id"] = (int) $identity["id"];
        }
        if (array_key_exists("name", $identity)) {
            $i["name"] = (string) $identity["name"];
        }

        // set remaining keys
        $i["timestamp"] = time();
        $i["country"] = $_SERVER["HTTP_CF_IPCOUNTRY"] ?? "XX";
        $i["ip"] = $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["REMOTE_ADDR"] ?? "127.0.0.1";

        // shuffle data
        $out = [];
        $keys = array_keys($i);
        shuffle($keys);
        foreach ($keys as $k) {
            $out[$k] = $i[$k];
        }

        // new identity
        $this->identity = $out;
        if ($i["id"]) {
            $this->setCookie("identity", json_encode($out));
        } else {
            // no user id = no cookie
            $this->clearCookie("identity");
        }
        return $this;
    }

    /**
     * Get user identity
     *
     * @return array Identity array
     */
    public function getIdentity()
    {
        // check current identity
        $id = $this->identity["id"] ?? null;
        $email = $this->identity["email"] ?? null;
        $name = $this->identity["name"] ?? null;
        if ($id && $email && $name) {
            return $this->identity;
        }

        // get nonce
        $file = DATA . "/" . self::IDENTITY_NONCE;
        if (!file_exists($file)) {
            // initialize nonce
            $this->setIdentity();
            return $this->identity;
        }
        $nonce = @file_get_contents($file);
        $nonce = substr(trim($nonce), 0, 8);

        // empty identity
        $i = [
            "avatar" => "",
            "email" => "",
            "id" => 0,
            "name" => "",
        ];

        // mock identity
        if (CLI) {
            $i = [
                "avatar" => "",
                "email" => "f@mxd.cz",
                "id" => 666,
                "name" => "Mr. Robot",
            ];
        }

        do {
            // URL identity
            if (isset($_GET["identity"])) {
                // set cookie and reload
                $tls = "";
                if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
                    $tls = "s";
                }
                $this->setCookie("identity", $_GET["identity"]);
                $this->setLocation("http{$tls}://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
                exit;
            }

            // COOKIE identity
            if (isset($_COOKIE["identity"])) {
                $x = 0;
                $q = json_decode($this->getCookie("identity"), true);
                if (!is_array($q)) {
                    $x++;
                } else {
                    if (!array_key_exists("avatar", $q)) {
                        $x++;
                    }
                    if (!array_key_exists("email", $q)) {
                        $x++;
                    }
                    if (!array_key_exists("id", $q)) {
                        $x++;
                    }
                    if (!array_key_exists("name", $q)) {
                        $x++;
                    }
                    if (!array_key_exists("nonce", $q)) {
                        $x++;
                    }
                }
                if ($x) {
                    // something is wrong!
                    $this->logout();
                    break;
                }
                if ($q["nonce"] == $nonce) {
                    $this->setIdentity($q);
                    break;
                }
            }
            // empty / mock identity
            $this->setIdentity($i);
            break;
        } while (true);
        return $this->identity;
    }

    /**
     * Get current user
     *
     * @return array Get current user data
     */
    public function getCurrentUser()
    {
        $u = array_replace(
            [
                "avatar" => "",
                "email" => "",
                "id" => 0,
                "name" => "",
            ],
            $this->getIdentity()
        );
        $u["uid"] = $this->getUID();
        $u["uidstring"] = $this->getUIDstring();
        return $u;
    }

    /**
     * Cfg getter
     *
     * @param string $key Index to configuration data / null
     * @return mixed Configuration data by index / whole array
     */
    public function getCfg($key = null)
    {
        if (is_null($key)) {
            return $this->getData("cfg");
        }
        if (is_string($key)) {
            return $this->getData("cfg.${key}");
        }
        throw new \Exception("FATAL ERROR: Invalid parameter!");
    }

    /**
     * Match getter
     *
     * @return mixed Match data array
     */
    public function getMatch()
    {
        return $this->getData("match") ?? null;
    }

    /**
     * Presenter getter
     *
     * @return mixed Rresenter data array
     */
    public function getPresenter()
    {
        return $this->getData("presenter") ?? null;
    }

    /**
     * Router getter
     *
     * @return mixed Router data array
     */
    public function getRouter()
    {
        return $this->getData("router") ?? null;
    }

    /**
     * View getter
     *
     * @return mixed Router view
     */
    public function getView()
    {
        return $this->getData("view") ?? null;
    }

    /**
     * Set HTTP header for CSV content
     *
     * @return object Singleton instance
     */
    public function setHeaderCsv()
    {
        header("Content-Type: text/csv; charset=UTF-8");
        return $this;
    }

    /**
     * Set HTTP header for binary content
     *
     * @return object Singleton instance
     */
    public function setHeaderFile()
    {
        header("Content-Type: application/octet-stream");
        return $this;
    }

    /**
     * Set HTTP header for HTML content
     *
     * @return object Singleton instance
     */
    public function setHeaderHtml()
    {
        header("Content-Type: text/html; charset=UTF-8");
        return $this;
    }

    /**
     * Set HTTP header for JSON content
     *
     * @return object Singleton instance
     */
    public function setHeaderJson()
    {
        header("Content-Type: application/json; charset=UTF-8");
        return $this;
    }

    /**
     * Set HTTP header for JSON content
     *
     * @return object Singleton instance
     */
    public function setHeaderJavaScript()
    {
        header("Content-Type: application/javascript; charset=UTF-8");
        return $this;
    }

    /**
     * Set HTTP header for PDF content
     *
     * @return object Singleton instance
     */
    public function setHeaderPdf()
    {
        header("Content-Type: application/pdf");
        return $this;
    }

    /**
     * Set HTTP header for TEXT content
     *
     * @return object Singleton instance
     */
    public function setHeaderText()
    {
        header("Content-Type: text/plain; charset=UTF-8");
        return $this;
    }

    /**
     * Get encrypted cookie
     *
     * @param string $name Cookie name
     * @return mixed Cookie value
     */
    public function getCookie($name)
    {
        if (empty($name)) {
            return null;
        }
        if (is_null($name)) {
            return null;
        }
        if (CLI) {
            return $this->cookies[$name] ?? null;
        }

        // secure key
        $key = $this->getCfg("secret_cookie_key") ?? "secure.key";
        $keyfile = DATA . "/${key}";
        if (file_exists($keyfile)) {
            $enc = KeyFactory::loadEncryptionKey($keyfile);
        } else {
            $this->addError("HALITE: Missing encryption key!");
            return null;
        }
        $cookie = new Cookie($enc);
        return $cookie->fetch($name);
    }

    /**
     * Set encrypted cookie
     *
     * @param string $name Cookie name
     * @param string $data Cookie data
     * @return object Singleton instance
     */
    public function setCookie($name, $data)
    {
        if (empty($name)) {
            return $this;
        }
        if (is_null($name)) {
            return $this;
        }

        // secure key
        $key = $this->getCfg("secret_cookie_key") ?? "secure.key";
        $keyfile = DATA . "/$key";
        if (file_exists($keyfile)) {
            $enc = KeyFactory::loadEncryptionKey($keyfile);
        } else {
            $enc = KeyFactory::generateEncryptionKey();
            KeyFactory::save($enc, $keyfile);
            @chmod($keyfile, self::COOKIE_KEY_FILEMODE);
            $this->addMessage("HALITE: New keyfile created");
        }
        $cookie = new Cookie($enc);
        if (DOMAIN == "localhost") {
            $httponly = true;
            $samesite = "strict";
            $secure = false;
        } else {
            $httponly = true;
            $samesite = "strict";
            $secure = true;
        }
        if (!CLI) {
            $cookie->store($name, (string) $data, time() + self::COOKIE_TTL, "/", DOMAIN, $secure, $httponly, $samesite);
        }
        $this->cookies[$name] = (string) $data;
        return $this;
    }

    /**
     * Clear encrypted cookie
     *
     * @param string $name Cookie name
     * @return object Singleton instance
     */
    public function clearCookie($name)
    {
        if (empty($name)) {
            return $this;
        }
        if (!is_string($name)) {
            return $this;
        }
        \setcookie($name, "", time() - 3600, "/");
        unset($_COOKIE[$name]);
        unset($this->cookies[$name]);
        return $this;
    }

    /**
     * Set URL location and exit
     *
     * @param string $location URL address (optional)
     * @param integer $code HTTP code (optional)
     */
    public function setLocation($location = "", $code = 303)
    {
        $code = (int) $code;
        if (empty($location)) {
            $location = "/?nonce=" . substr(hash("sha256", random_bytes(8) . (string) time()), 0, 8);
        }
        header("Location: $location", true, ($code > 300) ? $code : 303);
        exit;
    }

    /**
     * Google OAuth 2.0 logout
     *
     */
    public function logout()
    {
        header('Clear-Site-Data: "cache", "cookies", "storage"');
        $nonce = "?nonce=" . substr(hash("sha256", random_bytes(8) . (string) time()), 0, 8);
        $this->clearCookie("identity");
        $this->setLocation("/${nonce}");
        exit;
    }

    /**
     * Check current user rate limits
     *
     * @param integer $maximum Maximum hits per time (optional)
     * @return object Singleton instance
     */
    public function checkRateLimit($maximum = 0)
    {
        $maximum = ((int) $maximum > 0) ? (int) $maximum : self::LIMITER_MAXIMUM;
        $uid = $this->getUID();
        $file = "${uid}_rate_limit";
        if (!$rate = Cache::read($file, "limiter")) {
            $rate = 0;
        }
        $rate++;
        if ($rate > $maximum) {
            $this->setLocation("/err/420");
        }
        Cache::write($file, $rate, "limiter");
        return $this;
    }

    /**
     * Check if current user has access rights
     *
     * @param string $perms role (optional)
     * @return object Singleton instance
     */
    public function checkPermission($role = "admin")
    {
        if (empty($role)) {
            return $this;
        }
        $role = strtolower(trim((string) $role));
        $email = $this->getIdentity()["email"] ?? "";
        $groups = $this->getCfg("admin_groups") ?? [];
        if (strlen($role) && strlen($email)) {
            // email allowed
            if (in_array($email, $groups[$role] ?? [], true)) {
                return $this;
            }
            // any Google users allowed
            if (in_array("*", $groups[$role] ?? [], true)) {
                return $this;
            }
        }
        // not authorized
        $this->setLocation("/err/401");
        exit;
    }

    /**
     * Get current user group
     *
     * @return string User group name
     */
    public function getUserGroup()
    {
        $id = $this->getIdentity()["id"] ?? null;
        $email = $this->getIdentity()["email"] ?? null;
        if (!$id) {
            return null;
        }
        $mygroup = null;
        $email = trim((string) $email);
        // search all groups for email or asterisk
        foreach ($this->getCfg("admin_groups") ?? [] as $group => $users) {
            if (in_array($email, $users, true)) {
                $mygroup = $group;
                break;
            }
            if (in_array("*", $users, true)) {
                $mygroup = $group;
                continue;
            }
        }
        return $mygroup;
    }

    /**
     * Purge Cloudflare cache
     *
     * @var array $cf Cloudflare authentication array
     * @return object Singleton instance
     */
    public function CloudflarePurgeCache($cf = null)
    {
        if (!is_array($cf)) {
            return $this;
        }
        $email = $cf["email"] ?? null;
        $apikey = $cf["apikey"] ?? null;
        $zoneid = $cf["zoneid"] ?? null;
        try {
            if ($email && $apikey && $zoneid) {
                $key = new \Cloudflare\API\Auth\APIKey($email, $apikey);
                $adapter = new \Cloudflare\API\Adapter\Guzzle($key);
                $zones = new \Cloudflare\API\Endpoints\Zones($adapter);
                if (is_array($zoneid)) {
                    $myzones = $zoneid;
                }
                if (is_string($zoneid)) {
                    $myzones = [$zoneid];
                }
                foreach ($zones->listZones()->result as $zone) {
                    foreach ($myzones as $myzone) {
                        if ($zone->id == $myzone) {
                            $zones->cachePurgeEverything($zone->id);
                            $this->addMessage("CLOUDFLARE: zoneid ${myzone} cache purged");
                        }
                    }
                }
            }
        } catch (Execption $e) {
            $this->addError("CLOUDFLARE: " . (string) $e);
        }
        return $this;
    }

    /**
     * Write JSON data to output
     *
     * @param array $d array of data / integer error code
     * @param array $headers JSON array (optional)
     * @return object Singleton instance
     */
    public function writeJsonData($data, $headers = [])
    {
        $out = [];
        $code = 200;
        $out["timestamp"] = time();
        $out["version"] = (string) ($this->getCfg("version") ?? "v1");
        // last decoding error
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                $code = 200;
                $msg = "OK";
                break;
            case JSON_ERROR_DEPTH:
                $code = 400;
                $msg = "Maximum stack depth exceeded.";
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $code = 400;
                $msg = "Underflow or the modes mismatch.";
                break;
            case JSON_ERROR_CTRL_CHAR:
                $code = 400;
                $msg = "Unexpected control character found.";
                break;
            case JSON_ERROR_SYNTAX:
                $code = 500;
                $msg = "Syntax error, malformed JSON.";
                break;
            case JSON_ERROR_UTF8:
                $code = 400;
                $msg = "Malformed UTF-8 characters, possibly incorrectly encoded.";
                break;
            default:
                $code = 500;
                $msg = "";
                break;
        }
        if (is_null($data)) {
            $code = 500;
            $msg = "";
        }
        if (is_string($data)) {
            $data = [$data];
        }
        if (is_int($data)) {
            $code = $data;
            switch ($data) {
                case 304:
                    $msg = "Not modified.";
                    break;
                case 400:
                    $msg = "Bad request.";
                    break;
                case 404:
                    $msg = "Not found.";
                    break;
                default:
                    $msg = "Unknown error.";
            }
            $data = null;
        }

        // output array
        $this->setHeaderJson();
        $out["code"] = (int) $code;
        $out["message"] = $msg;
        $out = array_merge_recursive($out, $headers);
        $out["data"] = $data ?? null;
        return $this->setData("output", json_encode($out, JSON_PRETTY_PRINT));
    }

    /**
     * Data Expander
     *
     * @param array $data Model array
     * @return void
     */
    public function dataExpander(&$data)
    {
        if (empty($data)) {
            return;
        }

        $presenter = $this->getPresenter();
        $view = $this->getView();
        $use_cache = true;
        // do not cache pages with ?nonce
        if (array_key_exists("nonce", $_GET)) {
            $use_cache = false;
        }
        // check logged user
        $data["user"] = $user = $this->getCurrentUser();
        $data["admin"] = $group = $this->getUserGroup();
        if ($group) {
            $data["admin_group_${group}"] = true;
        }
        if ($user["id"]) {
            $use_cache = false; // no cache for logged users
        }
        // set language
        $data["lang"] = $language = strtolower($presenter[$view]["language"]) ?? "cs";
        $data["lang{$language}"] = true;
        // compute data hash
        $data["DATA_VERSION"] = hash('sha256', (string) json_encode($data["l"]));
        // extract request path slug
        if (($pos = strpos($data["request_path"], $language)) !== false) {
            $data["request_path_slug"] = substr_replace($data["request_path"], "", $pos, strlen($language));
        } else {
            $data["request_path_slug"] = $data["request_path"] ?? "";
        }
        $data["use_cache"] = $use_cache;
    }

}
