<?php
/**
 * GSC Tesseract LASAGNA
 *
 * @category Framework
 * @author   Fred Brooker <oscadal@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 */

namespace GSC;

use Cake\Cache\Cache;
use Exception;
use Google\Cloud\Logging\LoggingClient;
use League\Csv\Reader;
use League\Csv\Statement;
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
    public function addAuditMessage($message);
    public function getCriticals();
    public function getErrors();
    public function getMessages();

    /** getters */
    public function getCfg($key);
    public function getCookie($name);
    public function getCurrentUser();
    public function getData($key);
    public function getIP();
    public function getIdentity();
    public function getLocale($locale);
    public function getMatch();
    public function getPresenter();
    public function getRateLimit();
    public function getRouter();
    public function getUID();
    public function getUIDstring();
    public function getUserGroup();
    public function getView();

    /** checks */
    public function checkLocales($force);
    public function checkPermission($role);
    public function checkRateLimit($maximum);

    /** setters */
    public function setCookie($name, $data);
    public function setData($data, $value);
    public function setForceCsvCheck();
    public function setHeaderCsv();
    public function setHeaderFile();
    public function setHeaderHtml();
    public function setHeaderJavaScript();
    public function setHeaderJson();
    public function setHeaderPdf();
    public function setHeaderText();
    public function setHeaderXML();
    public function setIdentity($identity);
    public function setLocation($locationm, $code);

    /** tools */
    public function clearCookie($name);
    public function cloudflarePurgeCache($cf);
    public function dataExpander(&$data);
    public function logout();
    public function postloadAppData($key);
    public function preloadAppData($key, $force);
    public function readAppData($name);
    public function renderHTML($template);
    public function writeJsonData($data, $headers = [], $switches = null);

    /** abstract */
    public function process();

    /** singleton */
    public static function getInstance();
    public static function getTestInstance(); // for Unit testing
}

abstract class APresenter implements IPresenter
{
    /** @var integer Octal file mode for logs */
    const LOG_FILEMODE = 0666;

    /** @var integer Octal file mode for CSV */
    const CSV_FILEMODE = 0666;

    /** @var integer CSV min. file size */
    const CSV_MIN_SIZE = 42;

    /** @var integer Octal file mode for cookie secret */
    const COOKIE_KEY_FILEMODE = 0600;

    /** @var integer Cookie time to live */
    const COOKIE_TTL = 86400 * 15;

    /** @var string Google CSV URL prefix */
    const GS_CSV_PREFIX = "https://docs.google.com/spreadsheets/d/e/";

    /** @var string Google CSV URL postfix */
    //const GS_CSV_POSTFIX = "/pub?output=csv";
    const GS_CSV_POSTFIX = "/pub?gid=0&single=true&output=csv";

    /** @var string Google Sheet URL prefix */
    const GS_SHEET_PREFIX = "https://docs.google.com/spreadsheets/d/";

    /** @var string Google Sheet URL postfix */
    const GS_SHEET_POSTFIX = "/edit#gid=0";

    /** @var integer Access limiter max.  hits */
    const LIMITER_MAXIMUM = 50;

    /** @var string Identity nonce filename */
    const IDENTITY_NONCE = "identity_nonce.key";

    // GOOGLE DRIVE TEMPLATES

    /** @var string */
    const GOOGLE_SHEET_EDIT =
        "https://docs.google.com/spreadsheets/d/FILEID/edit#gid=0";

    /** @var string */
    const GOOGLE_SHEET_VIEW =
        "https://docs.google.com/spreadsheets/d/FILEID/view#gid=0";

    /** @var string */
    const GOOGLE_DOCUMENT_EXPORT_DOC =
        "https://docs.google.com/document/d/FILEID/export?format=doc";

    /** @var string */
    const GOOGLE_DOCUMENT_EXPORT_PDF =
        "https://docs.google.com/document/d/FILEID/export?format=pdf";

    /** @var string */
    const GOOGLE_SHEET_EXPORT_DOCX =
        "https://docs.google.com/spreadsheets/d/FILEID/export?format=docx";

    /** @var string */
    const GOOGLE_SHEET_EXPORT_PDF =
        "https://docs.google.com/spreadsheets/d/FILEID/export?format=pdf";

    /** @var string */
    const GOOGLE_SHEET_EXPORT_XLSX =
        "https://docs.google.com/spreadsheets/d/FILEID/export?format=xlsx";

    /** @var string */
    const GOOGLE_SHEET_EXPORT_CSV =
        "https://docs.google.com/spreadsheets/d/e/FILEID/pub?output=csv";

    /** @var string */
    const GOOGLE_SHEET_EXPORT_HTML =
        "https://docs.google.com/spreadsheets/d/e/FILEID/pubhtml";

    /** @var string */
    const GOOGLE_SUITE_IMAGE_VIEW =
        "https://drive.google.com/a/DOMAIN/thumbnail?id=IMAGEID";

    /** @var string */
    const GOOGLE_IMAGE_VIEW =
        "https://drive.google.com/thumbnail?id=IMAGEID";

    /** @var string */
    const GOOGLE_FILE_EXPORT_DOWNLOAD =
        "https://drive.google.com/uc?export=download&id=FILEID";

    /** @var string */
    const GOOGLE_FILE_EXPORT_VIEW =
        "https://drive.google.com/uc?export=view&id=FILEID";

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

    /** @var boolean $force_csv_check Recheck locales? */
    private $force_csv_check = false;

    /** @var array $csv_postload Array of CSV keys */
    private $csv_postload = [];

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
    private function __construct()
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
    private function __clone()
    {}

    /**
     * Magic sleep
     *
     * @return void
     */
    public final function __sleep()
    {}

    /**
     * Magic wakeup
     *
     * @return void
     */
    public final function __wakeup()
    {}

    /**
     * Magic call
     *
     * @param string $name
     * @param mixed $parameter
     * @return void
     */
    public function __call($name, $parameter)
    {}

    /**
     * Magic call static
     *
     * @param string $name
     * @param mixed $parameter
     * @return void
     */
    public static function __callStatic($name, $parameter)
    {}

    /**
     * Object to string
     *
     * @return string Serialized JSON model
     */
    public function __toString()
    {
        return (string) json_encode($this->getData(), JSON_PRETTY_PRINT);
    }

    /**
     * Class destructor
     */
    public function __destruct()
    {
        if (ob_get_level()) {
            ob_end_flush();
        }
        ob_start();
        foreach ($this->csv_postload as $key) {
            $this->preloadAppData((string) $key, true);
        }
        $this->checkLocales((bool) $this->force_csv_check);

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
                // log errors to GCP for valid project, keys and NOT localhost
                if (GCP_PROJECTID && GCP_KEYS && !LOCALHOST) {
                    $logging = new LoggingClient([
                        "projectId" => GCP_PROJECTID,
                        "keyFilePath" => APP . DS . GCP_KEYS,
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
     * Get instance for unit testing
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
        if (is_null($template)) {
            return "";
        }
        $type = (file_exists(TEMPLATES . DS . "${template}.mustache")) ? 1 : 0;
        $renderer = new \Mustache_Engine(array(
            "template_class_prefix" => "__" . SERVER . "_" . PROJECT . "_",
            "cache" => TEMP,
            "cache_file_mode" => 0666,
            "cache_lambda_templates" => true,
            "loader" => $type ? new \Mustache_Loader_FilesystemLoader(TEMPLATES) : new \Mustache_Loader_StringLoader,
            "partials_loader" => new \Mustache_Loader_FilesystemLoader(PARTIALS),
            "helpers" => [
                "unix_timestamp" => function () {
                    return (string) time();
                },
                "sha256_nonce" => function () {
                    return (string) \substr(\hash("sha256", \random_bytes(8) . (string) \time()), 0, 8);
                },
                "convert_hyperlinks" => function ($source, \Mustache_LambdaHelper$lambdaHelper) {
                    $text = $lambdaHelper->render($source);
                    $text = preg_replace(
                        "/(https)\:\/\/([a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,20})(\/[a-zA-Z0-9\-_\/]*)?/",
                        '<a rel=noopener target=_blank href="$0">$2$3</a>', $text);
                    return (string) $text;
                },
                "shuffle_lines" => function ($source, \Mustache_LambdaHelper$lambdaHelper) {
                    $text = $lambdaHelper->render($source);
                    $arr = explode("\n", $text);
                    shuffle($arr);
                    $text = join("\n", $arr);
                    return (string) $text;
                },
            ],
            "charset" => "UTF-8",
            "escape" => function ($value) {
                return $value;
            },
        ));
        return $type ? $renderer->loadTemplate($template)->render($this->getData()) : $renderer->render($template, $this->getData());
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
        $dot->set([ // global constants
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
        ]);
        $dot->set([ // class constants
            "CONST.COOKIE_KEY_FILEMODE" => self::COOKIE_KEY_FILEMODE,
            "CONST.COOKIE_TTL" => self::COOKIE_TTL,
            "CONST.CSV_FILEMODE" => self::CSV_FILEMODE,
            "CONST.CSV_MIN_SIZE" => self::CSV_MIN_SIZE,
            "CONST.GS_CSV_POSTFIX" => self::GS_CSV_POSTFIX,
            "CONST.GS_CSV_PREFIX" => self::GS_CSV_PREFIX,
            "CONST.GS_SHEET_POSTFIX" => self::GS_SHEET_POSTFIX,
            "CONST.GS_SHEET_PREFIX" => self::GS_SHEET_PREFIX,
            "CONST.LIMITER_MAXIMUM" => self::LIMITER_MAXIMUM,
            "CONST.LOG_FILEMODE" => self::LOG_FILEMODE,
        ]);
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
        if (\is_array($data)) {
            $this->data = (array) $data; // $data = new model, replace it
        } else {
            $key = $data; // $data = key index
            if (\is_string($key) && !empty($key)) {
                $dot = new \Adbar\Dot($this->data);
                $dot->set($key, $value); // set new value
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
     * Add audit message
     *
     * @param string $message Message string
     * @return object Singleton instance
     */
    public function addAuditMessage($message = null)
    {
        if (!\is_null($message) || !empty($message)) {
            $file = DATA . DS . "AuditLog.txt";
            $date = date("c");
            $message = \trim($message);
            $i = $this->getIdentity();
            @file_put_contents($file, "$date;$message;IP:{$i['ip']};NAME:{$i['name']};EMAIL:{$i['email']};\n",
                FILE_APPEND | LOCK_EX
            );
        }
        return $this;
    }

    /**
     * Add info message
     *
     * @param string $message Message string
     * @return object Singleton instance
     */
    public function addMessage($message = null)
    {
        if (!\is_null($message) || !empty($message)) {
            $this->messages[] = (string) $message;
        }
        return $this;
    }

    /**
     * Add error message
     *
     * @param string $message Error string
     * @return object Singleton instance
     */
    public function addError($message = null)
    {
        if (!\is_null($message) || !empty($message)) {
            $this->errors[] = (string) $message;
        }
        return $this;
    }

    /**
     * Add critical message
     *
     * @param string $message Critical error string
     * @return object Singleton instance
     */
    public function addCritical($message = null)
    {
        if (!\is_null($message) || !empty($message)) {
            $this->criticals[] = (string) $message;
        }
        return $this;
    }

    /**
     * Get IP address
     *
     * @return string IP address
     */
    public function getIP()
    {
        return $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["REMOTE_ADDR"] ?? "127.0.0.1";
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
                CLI ? "CLI_USER" : "",
                CLI ? "" : $_SERVER["HTTP_ACCEPT_ENCODING"] ?? "N/A",
                CLI ? "" : $_SERVER["HTTP_ACCEPT_LANGUAGE"] ?? "N/A",
                CLI ? "" : $_SERVER["HTTP_USER_AGENT"] ?? "N/A",
                $this->getIP(),
            ]),
            " ", "_");
    }

    /**
     * Get universal ID hash
     *
     * @return string Universal ID SHA-256 hash
     */
    public function getUID()
    {
        return \hash("sha256", $this->getUIDstring());
    }

    /**
     * Set user identity
     *
     * @param array $identity Identity array
     * @return object Singleton instance
     */
    public function setIdentity($identity = [])
    {
        if (!\is_array($identity)) {
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
        $file = DATA . DS . self::IDENTITY_NONCE; // nonce file
        if (!\file_exists($file)) {
            try {
                $nonce = \hash("sha256", \random_bytes(256) . \time());
                if (\file_put_contents($file, $nonce, LOCK_EX) === false) {
                    throw new \Exception("File write failed!");
                }
                @\chmod($file, 0660);
                $this->addMessage("ADMIN: nonce file created");
            } catch (Exception $e) {
                $this->addError("500: Internal Server Error -> cannot create nonce file: " . $e->getMessage());
                $this->setLocation("/err/500");
                exit;
            }
        }
        if (!$nonce = @\file_get_contents($file)) {
            $this->addError("500: Internal Server Error -> cannot read nonce file");
            $this->setLocation("/err/500");
            exit;
        }
        $i["nonce"] = \substr(\trim($nonce), 0, 8); // nonce
        // check all keys
        if (\array_key_exists("avatar", $identity)) {
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
        // set other values
        $i["country"] = $_SERVER["HTTP_CF_IPCOUNTRY"] ?? "XX";
        $i["ip"] = $this->getIP();
        // shuffle keys
        $out = [];
        $keys = \array_keys($i);
        shuffle($keys);
        foreach ($keys as $k) {
            $out[$k] = $i[$k];
        }
        // set new identity
        $this->identity = $out;
        if ($out["id"]) {
            $this->setCookie($this->getCfg("app") ?? "app", json_encode($out)); // encrypted cookie
        } else {
            $this->clearCookie($this->getCfg("app") ?? "app"); // clear cookie
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
        if (CLI) {
            return [
                "country" => "XX",
                "email" => "user@example.com",
                "id" => 1,
                "ip" => "127.0.0.1",
                "name" => "CLI User",
            ];
        }

        // check current identity
        $id = $this->identity["id"] ?? null;
        $email = $this->identity["email"] ?? null;
        $name = $this->identity["name"] ?? null;
        if ($id && $email && $name) {
            return $this->identity;
        }
        $file = DATA . DS . self::IDENTITY_NONCE; // nonce file
/*
        if (!\file_exists($file)) { // initialize nonce
            $this->setIdentity(); // set empty identity
            return $this->identity;
        }
*/
        if (!$nonce = @\file_get_contents($file)) {
            $this->addError("500: Internal Server Error -> cannot read nonce file");
            $this->setLocation("/err/500");
            exit;
        }
        $nonce = \substr(\trim($nonce), 0, 8); // nonce
        $i = [ // empty identity
            "avatar" => "",
            "country" => "",
            "email" => "",
            "id" => 0,
            "ip" => "",
            "name" => "",
        ];
        do {
            if (isset($_GET["identity"])) { // URL identity
                $tls = "";
                if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
                    $tls = "s";
                }
                $this->setCookie($this->getCfg("app") ?? "app", $_GET["identity"]); // set cookie
                $this->setLocation("http{$tls}://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
                exit;
            }
            if (isset($_COOKIE[$this->getCfg("app") ?? "app"])) { // COOKIE identity
                $x = 0;
                $q = json_decode($this->getCookie($this->getCfg("app") ?? "app"), true);
                if (!\is_array($q)) {
                    $x++;
                } else {
                    if (!array_key_exists("email", $q)) {
                        $x++;
                    }
                    if (!array_key_exists("id", $q)) {
                        $x++;
                    }
                    if (!array_key_exists("nonce", $q)) {
                        $x++;
                    }
                }
                if ($x) {
                    $this->logout(); // something is terribly wrong!!!
                    break;
                }
                if ($q["nonce"] == $nonce) { // compare nonces
                    //$this->setIdentity($q);
                    $this->identity = $q; // our identity
                    break;
                }
            }
            $this->setIdentity($i); // empty or mock identity
            break;
        } while (true);
        return $this->identity;
    }

    /**
     * Get current user
     *
     * @return array current user data
     */
    public function getCurrentUser()
    {
        $u = array_replace(
            [
                "avatar" => "",
                "country" => "",
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
     * @param string $key Index to configuration data / void
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
        throw new \Exception("FATAL ERROR: Invalid get parameter!");
    }

    /**
     * Match getter (alias)
     *
     * @return mixed Match data array
     */
    public function getMatch()
    {
        return $this->getData("match") ?? null;
    }

    /**
     * Presenter getter (alias)
     *
     * @return mixed Rresenter data array
     */
    public function getPresenter()
    {
        return $this->getData("presenter") ?? null;
    }

    /**
     * Router getter (alias)
     *
     * @return mixed Router data array
     */
    public function getRouter()
    {
        return $this->getData("router") ?? null;
    }

    /**
     * View getter (alias)
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
        \header("Content-Type: text/csv; charset=UTF-8");
        return $this;
    }

    /**
     * Set HTTP header for binary content
     *
     * @return object Singleton instance
     */
    public function setHeaderFile()
    {
        \header("Content-Type: application/octet-stream");
        return $this;
    }

    /**
     * Set HTTP header for HTML content
     *
     * @return object Singleton instance
     */
    public function setHeaderHtml()
    {
        \header("Content-Type: text/html; charset=UTF-8");
        return $this;
    }

    /**
     * Set HTTP header for JSON content
     *
     * @return object Singleton instance
     */
    public function setHeaderJson()
    {
        \header("Content-Type: application/json; charset=UTF-8");
        return $this;
    }

    /**
     * Set HTTP header for JSON content
     *
     * @return object Singleton instance
     */
    public function setHeaderJavaScript()
    {
        \header("Content-Type: application/javascript; charset=UTF-8");
        return $this;
    }

    /**
     * Set HTTP header for PDF content
     *
     * @return object Singleton instance
     */
    public function setHeaderPdf()
    {
        \header("Content-Type: application/pdf");
        return $this;
    }

    /**
     * Set HTTP header for TEXT content
     *
     * @return object Singleton instance
     */
    public function setHeaderText()
    {
        \header("Content-Type: text/plain; charset=UTF-8");
        return $this;
    }

    /**
     * Set HTTP header for XML content
     *
     * @return object Singleton instance
     */
    public function setHeaderXML()
    {
        \header('Content-Type: application/xml; charset=utf-8');
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
        if (CLI) {
            return $this->cookies[$name] ?? null;
        }
        $key = $this->getCfg("secret_cookie_key") ?? "secure.key"; // secure key
        $key = \trim($key, "/.\\");
        $keyfile = DATA . DS . $key;
        if (\file_exists($keyfile) && is_readable($keyfile)) {
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
        $key = $this->getCfg("secret_cookie_key") ?? "secure.key"; // secure key
        $key = \trim($key, "/.\\");
        $keyfile = DATA . DS . $key;
        if (\file_exists($keyfile) && is_readable($keyfile)) {
            $enc = KeyFactory::loadEncryptionKey($keyfile);
        } else {
            $enc = KeyFactory::generateEncryptionKey();
            if (is_writable(DATA)) {
                KeyFactory::save($enc, $keyfile);
                @\chmod($keyfile, self::COOKIE_KEY_FILEMODE);
                $this->addMessage("HALITE: New keyfile created");
            } else {
                $this->addError("HALITE: Cannot write encryption key!");
            }
        }
        $cookie = new Cookie($enc);
        if (DOMAIN == "localhost") {
            $httponly = true;
            $samesite = "lax";
            $secure = false;
        } else {
            $httponly = true;
            $samesite = "lax";
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
     * @return object  Singleton instance
     */
    public function clearCookie($name)
    {
        if (empty($name)) {
            return $this;
        }
        unset($_COOKIE[$name]);
        unset($this->cookies[$name]);
        \setcookie($name, "", time() - 3600, "/");
        return $this;
    }

    /**
     * Set URL location and exit
     *
     * @param string $location URL address (optional)
     * @param integer $code HTTP code (optional)
     */
    public function setLocation($location = null, $code = 303)
    {
        $code = (int) $code;
        if (empty($location)) {
            $location = "/?nonce=" . \substr(\hash("sha256", \random_bytes(4) . (string) \time()), 0, 4);
        }
        \header("Location: $location", true, ($code > 300) ? $code : 303);
        exit;
    }

    /**
     * Logout
     */
    public function logout()
    {
        $this->setIdentity();
        $this->clearCookie($this->getCfg("app") ?? "app");
        \header('Clear-Site-Data: "cookies"');
        $this->setLocation();
        exit;
    }

    /**
     * Check current user rate limits
     *
     * @param integer $max Hits per second (optional)
     * @return object Singleton instance
     */
    public function checkRateLimit($max = null)
    {
        if (CLI) {
            return;
        }
        $f = "user_rate_limit_{$this->getUID()}";
        $rate = (int) (Cache::read($f, "limiter") ?? 0);
        Cache::write($f, ++$rate, "limiter");
        $max??=self::LIMITER_MAXIMUM;
        if (!LOCALHOST && $rate > (int) $max) { // over limits && NOT localhost
            $this->setLocation("/err/420");
        }
        return $this;
    }

    /**
     * Get current user rate limits
     *
     * @return integer current rate limit
     */
    public function getRateLimit()
    {
        if (CLI) {
            return false;
        }
        return Cache::read("user_rate_limit_{$this->getUID()}", "limiter");
    }

    /**
     * Check if current user has access rights
     *
     * @param mixed $role role (optional)
     * @return object Singleton instance
     */
    public function checkPermission($role = "admin")
    {
        if (empty($role)) {
            return $this;
        }
        $role = \strtolower(\trim((string) $role));
        $email = $this->getIdentity()["email"] ?? "";
        $groups = $this->getCfg("admin_groups") ?? [];
        if (\strlen($role) && \strlen($email)) {
            if (\in_array($email, $groups[$role] ?? [], true)) { // email allowed
                return $this;
            }
            if (\in_array("*", $groups[$role] ?? [], true)) { // any Google users allowed
                return $this;
            }
        }
        $this->setLocation("/err/401"); // not authorized
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
        $email = \trim((string) $email);
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
     * Force CSV checking
     *
     * @param boolean $set True to force CSV check (optional)
     * @return object Singleton instance
     */
    public function setForceCsvCheck($set = true)
    {
        $this->force_csv_check = (bool) $set;
        return $this;
    }

    /**
     * Post-load CSV data
     *
     * @param mixed $key string / array to be merged
     * @return object Singleton instance
     */
    public function postloadAppData($key)
    {
        if (!empty($key)) {
            if (\is_string($key)) {
                $this->csv_postload[] = (string) $key;
                return $this;
            }
            if (\is_array($key)) {
                $this->csv_postload = array_merge($this->csv_postload, $key);
                return $this;
            }
        }
        return $this;
    }

    /**
     * Get locales from GS Sheets
     *
     * @param string $language language code
     * @param string $key index column code (optional)
     * @return array locales
     */
    public function getLocale($language, $key = "KEY")
    {
        if (!\is_array($this->getCfg("locales"))) {
            return null;
        }
        $locale = [];
        $language = \trim(\strtoupper((string) $language));
        $key = \trim(\strtoupper((string) $key));
        $cfg = $this->getCfg();
        $file = \strtolower("${language}_locale");
        $locale = Cache::read($file, "default");
        if ($locale === false || empty($locale)) {
            if (array_key_exists("locales", $cfg)) {
                $locale = [];
                foreach ((array) $cfg["locales"] as $k => $v) {

                    // 1. read from CSV file
                    $csv = false;
                    $subfile = \strtolower($k);
                    if ($csv === false && \file_exists((DATA . DS . "${subfile}.csv"))) {
                        $csv = @file_get_contents(DATA . DS . "${subfile}.csv");
                        if ($csv === false || \strlen($csv) < self::CSV_MIN_SIZE) {
                            $csv = false;
                        }
                    }

                    // 2. read from CSV file backup
                    if ($csv === false && \file_exists(DATA . DS . "${subfile}.bak")) {
                        $csv = @file_get_contents(DATA . DS . "${subfile}.bak");
                        if ($csv === false || \strlen($csv) < self::CSV_MIN_SIZE) {
                            $csv = false;
                            continue; // skip this CSV
                        } else {
                            \copy(DATA . DS . "${subfile}.bak", DATA . DS . "${subfile}.csv");
                        }
                    }

                    // parse CSV
                    $keys = [];
                    $values = [];
                    try {
                        $reader = Reader::createFromString($csv);
                        $reader->setHeaderOffset(0);
                        $records = (new Statement())->offset(1)->process($reader);
                        foreach ($records->fetchColumn($key) as $x) {
                            $keys[] = $x;
                        }
                        foreach ($records->fetchColumn($language) as $x) {
                            $values[] = $x;
                        }
                    } catch (Exception $e) {
                        $this->addCritical("ERR: $language locale $k CORRUPTED");
                    }
                    $locale = \array_replace($locale, \array_combine($keys, $values));
                }

                // git revisions
                $locale['$revisions'] = $this->getData("REVISIONS");

                // find all $ in combined locales array
                $dolar = ['$' => '$'];
                foreach ((array) $locale as $a => $b) {
                    if (\substr($a, 0, 1) === '$') {
                        $a = \trim($a, '${}' . "\x20\t\n\r\0\x0B");
                        if (!\strlen($a)) {
                            continue;
                        }
                        $dolar['$' . $a] = $b;
                        $dolar['${' . $a . "}"] = $b;
                    }
                }
                // replace $ and $$
                $locale = \str_replace(\array_keys($dolar), $dolar, $locale);
                $locale = \str_replace(\array_keys($dolar), $dolar, $locale);
            }
        }
        if ($locale === false || empty($locale)) {
            if ($this->force_csv_check) {
                \header("HTTP/1.1 500 FATAL ERROR");
                $this->addCritical("ERR: LOCALES CORRUPTED");
                echo "<body><h1>HTTP Error 500</h1><h2>LOCALES CORRUPTED</h2></body>";
                exit;
            } else {
                // second try!
                $this->setForceCsvCheck()->checkLocales(true);
                return $this->getLocale($language, $key);
            }
        }
        Cache::write($file, $locale, "default");
        return (array) $locale;
    }

    /**
     * Check and preload locales
     *
     * @param boolean $force force loading locales (optional)
     * @return object Singleton instance
     */
    public function checkLocales($force = false)
    {
        $locales = $this->getCfg("locales");
        if (\is_array($locales)) {
            foreach ($locales as $name => $csvkey) {
                $this->csv_preloader($name, $csvkey, (bool) $force);
            }
        }
        return $this;
    }

    /**
     * Purge Cloudflare cache
     *
     * @var array $cf Cloudflare authentication array
     * @return object Singleton instance
     */
    public function CloudflarePurgeCache($cf)
    {
        if (!\is_array($cf)) {
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
                if (\is_array($zoneid)) {
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
            $this->addError("CLOUDFLARE: " . (string) $e->getMessage());
        }
        return $this;
    }

    /**
     * Load CSV data into cache
     *
     * @param string $name CSV nickname (foobar)
     * @param string $csvkey Google CSV token (partial or full)
     * @param boolean $force force load? (optional)
     * @return object Singleton instance
     */
    private function csv_preloader($name, $csvkey, $force = false)
    {
        $name = \trim((string) $name);
        $csvkey = \trim((string) $csvkey);
        $force = (bool) $force;
        $file = \strtolower($name);
        if ($name && $csvkey) {
            if (Cache::read($file, "csv") === false || $force === true) {
                $data = false;
                if (!\file_exists(DATA . DS . "${file}.csv")) {
                    $force = true;
                }
                if ($force) {
                    if (\strpos($csvkey, "https") === 0) { // contains full path
                        $remote = $csvkey;
                    } else {
                        if (\strpos($csvkey, "?gid=") > 0) { // contains path incl. parameters
                            $remote = self::GS_CSV_PREFIX . $csvkey;
                        } else {
                            $remote = self::GS_CSV_PREFIX . $csvkey . self::GS_CSV_POSTFIX;
                        }
                    }
                    // fetch the remote file
                    $this->addMessage("FILE: fetching ${remote}");
                    $data = \file_get_contents($remote);
                }
                if (\strpos($data, "!DOCTYPE html") > 0) {
                    return $this; // we got HTML document = failure
                }
                if (\strlen($data) >= self::CSV_MIN_SIZE) {
                    Cache::write($file, $data, "csv");
                    // delete old backup
                    if (\file_exists(DATA . DS . "${file}.bak")) {
                        if (@\unlink(DATA . DS . "${file}.bak") === false) {
                            $this->addError("FILE: delete ${file}.bak failed!");
                        }
                    }
                    // move CSV to backup
                    if (\file_exists(DATA . DS . "${file}.csv")) {
                        if (@\rename(DATA . DS . "${file}.csv", DATA . DS . "${file}.bak") === false) {
                            $this->addError("FILE: backup ${file}.csv failed!");
                        }
                    }
                    // write new CSV
                    if (\file_put_contents(DATA . DS . "${file}.csv", $data, LOCK_EX) === false) {
                        $this->addError("FILE: save ${file}.csv failed!");
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Pre-load application CSV data
     *
     * @param string $key Configuration array name (optional)
     * @param boolean $force do force load? (optional)
     * @return object Singleton instance
     */
    public function preloadAppData($key = "app_data", $force = false)
    {
        $key = \strtolower(\trim((string) $key));
        $cfg = $this->getCfg();
        if (\array_key_exists($key, $cfg)) {
            foreach ((array) $cfg[$key] as $name => $csvkey) {
                // fetch all CSV remotes and store locally
                $this->csv_preloader($name, $csvkey, (bool) $force);
            }
        }
        return $this;
    }

    /**
     * Read application CSV data
     *
     * @param string $name CSV nickname (foobar)
     * @return string CSV data
     */
    public function readAppData($name)
    {
        $name = \trim((string) $name);
        $file = \strtolower($name);
        if (empty($file)) {
            $this->addCritical("EMPTY readAppData() filename parameter!");
            return null;
        }
        if (!$csv = Cache::read($file, "csv")) { // read CSV from cache
            $csv = false;
            if (file_exists(DATA . DS . "${file}.csv")) {
                $csv = \file_get_contents(DATA . DS . "${file}.csv");
            }
            if (\strpos($csv, "!DOCTYPE html") > 0) {
                $csv = false; // we got HTML document, try backup
            }
            if ($csv !== false || \strlen($csv) >= self::CSV_MIN_SIZE) {
                Cache::write($file, $csv, "csv"); // store into cache
                //dump($csv);exit;
                return $csv; // OK
            }
            $csv = false;
            if (\file_exists(DATA . DS . "${file}.bak")) {
                $csv = @\file_get_contents(DATA . DS . "${file}.bak"); // read CSV backup
            }
            if (\strpos($csv, "!DOCTYPE html") > 0) {
                return null; // we got HTML document = failure
            }
            if ($csv !== false || \strlen($csv) >= self::CSV_MIN_SIZE) {
                \copy(DATA . DS . "${file}.bak", DATA . DS . "${file}.csv"); // copy BAK to CSV
                Cache::write($file, $csv, "csv"); // store into cache
                return $csv; // OK
            }
            $csv = null; // failure
        }
        return $csv;
    }

    /**
     * Write JSON data to output
     *
     * @param array $data integer error code / array of data
     * @param array $headers array of extra data (optional)
     * @param mixed $switches JSON encoder switches
     * @return object Singleton instance
     */
    public function writeJsonData($data, $headers = [], $switches = null)
    {
        $out = [];
        $code = 200;
        $locale = [];
        $out["timestamp"] = \time();
        $out["version"] = (string) ($this->getCfg("version") ?? "v1");
        if (\is_array($this->getCfg("locales"))) { // locales
            $locale = $this->getLocale("en");
        }
        switch (\json_last_error()) { // last decoding error
            case JSON_ERROR_NONE:
                $code = 200;
                $msg = "DATA OK";
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
                $msg = "Internal server error.";
                break;
        }
        if (is_null($data)) {
            $code = 500;
            $msg = "Data is null! Internal server error. 🦄";
            \header("HTTP/1.1 500 Internal Server Error");
        }
        if (is_string($data)) {
            $data = [$data];
        }
        if (is_int($data)) {
            $code = $data;
            $data = null;
            $h = $_SERVER["SERVER_PROTOCOL"] ?? "HTTP/1.1";
            $m = null;
            switch ($code) {
                case 304:
                    $m = "Not modified";
                    break;
                case 400:
                    $m = "Bad request";
                    break;
                case 401:
                    $m = "Unauthorized";
                    break;
                case 402:
                    $m = "Payment Required";
                    break;
                case 403:
                    $m = "Forbidden";
                    break;
                case 404:
                    $m = "Not found";
                    break;
                case 405:
                    $m = "Method Not Allowed";
                    break;
                case 406:
                    $m = "Not Acceptable";
                    break;
                case 409:
                    $m = "Conflict";
                    break;
                case 410:
                    $m = "Gone";
                    break;
                case 412:
                    $m = "Precondition Failed";
                    break;
                case 415:
                    $m = "Unsupported Media Type";
                    break;
                case 416:
                    $m = "Requested Range Not Satisfiable";
                    break;
                case 417:
                    $m = "Expectation Failed";
                    break;
                default:
                    $msg = "Unknown error 🦄";
            }
            if ($m) {
                $msg = "$m.";
                \header("$h $code $m");
            }
        }
        // output
        $this->setHeaderJson();
        $out["code"] = (int) $code;
        $out["message"] = $msg;
        $out["processing_time"] = \round((\microtime(true) - TESSERACT_START) * 1000, 2) . " ms";
        $out = \array_merge_recursive($out, $headers);
        $out["data"] = $data ?? null;
        if (\is_null($switches)) {
            return $this->setData("output", \json_encode($out, JSON_PRETTY_PRINT));
        }
        return $this->setData("output", \json_encode($out, JSON_PRETTY_PRINT | $switches));
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
        $use_cache = true;
        if (array_key_exists("nonce", $_GET)) { // do not cache pages with ?nonce
            $use_cache = false;
        }
        $data["user"] = $user = $this->getCurrentUser(); // logged user
        $data["admin"] = $group = $this->getUserGroup(); // logged user group
        if ($group) {
            $data["admin_group_${group}"] = true;
        }
        if ($user["id"]) { // no cache for logged users
            $use_cache = false;
        }

        // language
        $presenter = $this->getPresenter();
        $view = $this->getView();
        $data["lang"] = $language = \strtolower($presenter[$view]["language"]) ?? "cs";
        $data["lang{$language}"] = true;
        $l = $this->getLocale($language);
        if (is_null($l)) {
            $l = [];
            $l["title"] = "MISSING LOCALES!";
        }
        if (!array_key_exists("l", $data)) {
            $data["l"] = $l;
        }

        // compute data hash
        $data["DATA_VERSION"] = \hash('sha256', (string) \json_encode($l));

        // extract request path slug
        if (($pos = \strpos($data["request_path"], $language)) !== false) {
            $data["request_path_slug"] = \substr_replace($data["request_path"], "", $pos, \strlen($language));
        } else {
            $data["request_path_slug"] = $data["request_path"] ?? "";
        }
        $data["use_cache"] = $use_cache;
    }

}
