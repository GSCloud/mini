<?php
/**
 * GSC Tesseract
 *
 * @author   Fred Brooker <git@gscloud.cz>
 * @category Framework
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://lasagna.gscloud.cz
 */

namespace GSC;

use Cake\Cache\Cache;
use Google\Cloud\Logging\LoggingClient;
use League\Csv\Reader;
use League\Csv\Statement;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\BrowserConsoleHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\WebProcessor;
use ParagonIE\Halite\Cookie;
use ParagonIE\Halite\KeyFactory;

// UT = Unit Tested
interface IPresenter
{
    public function addCritical($message); # UT
    public function addError($message); # UT
    public function addMessage($message); # UT
    public function addAuditMessage($message); # UT

    public function getCriticals(); # UT
    public function getErrors(); # UT
    public function getMessages(); # UT
    public function getCfg($key); # UT
    public function getCookie($name);
    public function getCurrentUser(); # UT
    public function getData($key); # UT
    public function getIP(); # UT
    public function getIdentity(); # UT
    public function getLocale($locale);
    public function getRateLimit(); # UT
    public function getUID(); # UT
    public function getUIDstring(); # UT
    public function getUserGroup(); # UT

    public function getMatch();
    public function getPresenter();
    public function getRouter();
    public function getView(); # UT

    public function checkLocales(bool $force); # UT
    public function checkPermission($roleslist); # UT
    public function checkRateLimit($maximum); # UT

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

    public function clearCookie($name);
    public function cloudflarePurgeCache($cf);
    public function dataExpander(&$data);
    public function logout();
    public function postloadAppData($key);
    public function preloadAppData($key, $force);
    public function readAppData($name);
    public function renderHTML($template); # UT
    public function writeJsonData($data, $headers = [], $switches = null);

    public function process(); // abstract method

    public static function getInstance(); # UT
    public static function getTestInstance(); // for testing purposes only
}

/**
 * Abstract Presenter class
 *
 * @package GSC
 */
abstract class APresenter implements IPresenter
{
    /** @var integer Octal file mode for logs */
    const LOG_FILEMODE = 0664;

    /** @var integer Octal file mode for CSV */
    const CSV_FILEMODE = 0664;

    /** @var integer CSV min. file size - something meaningful :) */
    const CSV_MIN_SIZE = 42;

    /** @var integer Octal file mode for cookie secret */
    const COOKIE_KEY_FILEMODE = 0600;

    /** @var integer Cookie time to live in seconds */
    const COOKIE_TTL = 86400 * 31;

    /** @var string Google CSV URL prefix */
    const GS_CSV_PREFIX = 'https://docs.google.com/spreadsheets/d/e/';

    /** @var string Google CSV URL postfix */
    const GS_CSV_POSTFIX = '/pub?gid=0&single=true&output=csv';

    /** @var string Google Sheet URL prefix */
    const GS_SHEET_PREFIX = 'https://docs.google.com/spreadsheets/d/';

    /** @var string Google Sheet URL postfix */
    const GS_SHEET_POSTFIX = '/edit#gid=0';

    /** @var integer Access limiter maximum hits */
    const LIMITER_MAXIMUM = 100;

    /** @var string Identity nonce filename */
    const IDENTITY_NONCE = 'identity_nonce.key';

    // VARIOUS GOOGLE TEMPLATES - TBD

    /** @var string */
    const GOOGLE_DOCUMENT_EXPORT_DOC =
        'https://docs.google.com/document/d/[FILEID]/export?format=doc';

    /** @var string */
    const GOOGLE_DOCUMENT_EXPORT_PDF =
        'https://docs.google.com/document/d/[FILEID]/export?format=pdf';

    /** @var string */
    const GOOGLE_SHEET_EDIT =
        'https://docs.google.com/spreadsheets/d/[FILEID]/edit#gid=0';

    /** @var string */
    const GOOGLE_SHEET_VIEW =
        'https://docs.google.com/spreadsheets/d/[FILEID]/view#gid=0';

    /** @var string */
    const GOOGLE_SHEET_EXPORT_DOCX =
        'https://docs.google.com/spreadsheets/d/[FILEID]/export?format=docx';

    /** @var string */
    const GOOGLE_SHEET_EXPORT_PDF =
        'https://docs.google.com/spreadsheets/d/[FILEID]/export?format=pdf';

    /** @var string */
    const GOOGLE_SHEET_EXPORT_XLSX =
        'https://docs.google.com/spreadsheets/d/[FILEID]/export?format=xlsx';

    /** @var string */
    const GOOGLE_SHEET_PUBLIC_EXPORT_CSV =
        'https://docs.google.com/spreadsheets/d/e/[FILEID]/pub?output=csv';

    /** @var string */
    const GOOGLE_SHEET_PUBLIC_EXPORT_HTML =
        'https://docs.google.com/spreadsheets/d/e/[FILEID]/pubhtml';

    /** @var string */
    const GOOGLE_WORKSPACE_IMAGE_THUMBNAIL =
        'https://drive.google.com/a/[DOMAIN]/thumbnail?id=[IMAGEID]';

    /** @var string */
    const GOOGLE_IMAGE_THUMBNAIL =
        'https://drive.google.com/thumbnail?id=[IMAGEID]';

    /** @var string */
    const GOOGLE_FILE_EXPORT_DOWNLOAD =
        'https://drive.google.com/uc?export=download&id=[FILEID]';

    /** @var string */
    const GOOGLE_FILE_EXPORT_VIEW =
        'https://drive.google.com/uc?export=view&id=[FILEID]';

    // PRIVATE VARIABLES

    /** @var array Data Model */
    private $data = [];

    /** @var array Messages */
    private $messages = [];

    /** @var array Errors */
    private $errors = [];

    /** @var array Critical Errors */
    private $criticals = [];

    /** @var array User Identity */
    private $identity = [];

    /** @var boolean force check locales in desctructor */
    private $force_csv_check = false;

    /** @var array CSV Keys */
    private $csv_postload = [];

    /** @var array Cookies */
    private $cookies = [];

    /** @var array Singleton Instances */
    private static $instances = [];

    /**
     * Abstract Processor
     *
     * @abstract
     * @return self
     */
    abstract public function process();

    /**
     * Class constructor
     */
    private function __construct()
    {
        $class = get_called_class();
        if (array_key_exists($class, self::$instances)) {
            // throw an exception if class is already instantiated
            throw new \Exception("FATAL ERROR: instance of class [{$class}] already exists");
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
     * @return string Serialized JSON data model
     */
    public function __toString()
    {
        return (string) \json_encode($this->getData(), JSON_PRETTY_PRINT);
    }

    /**
     * Class destructor - the home of many final tasks
     */
    public function __destruct()
    {
        // clear outbut buffering
        if (\ob_get_level()) {
            @\ob_end_flush();
        }

        // finish request
        if (\function_exists('fastcgi_finish_request')) {
            \fastcgi_finish_request();
        }

        // preload CSV definitions
        foreach ($this->csv_postload as $key) {
            $this->preloadAppData((string) $key, true);
        }
        // load actual CSV data
        $this->checkLocales((bool) $this->force_csv_check);

        // setup Monolog logger
        $monolog = new Logger('Tesseract log');
        $streamhandler = new StreamHandler(MONOLOG, Logger::INFO, true, self::LOG_FILEMODE);
        $streamhandler->setFormatter(new LineFormatter);
        $consolehandler = new BrowserConsoleHandler(Logger::INFO);
        $monolog->pushHandler($consolehandler);
        $monolog->pushHandler($streamhandler);
        $monolog->pushProcessor(new MemoryUsageProcessor);
        $monolog->pushProcessor(new WebProcessor);

        $criticals = $this->getCriticals();
        $errors = $this->getErrors();
        $messages = $this->getMessages();

        list($usec, $sec) = \explode(' ', \microtime());
        defined('TESSERACT_STOP') || define('TESSERACT_STOP', ((float) $usec + (float) $sec));
        $add = '; processing: ' . \round(((float) TESSERACT_STOP - (float) TESSERACT_START) * 1000, 2) . ' ms'
            . '; request_uri: ' . ($_SERVER['REQUEST_URI'] ?? 'N/A');

        $google_logger = null;
        try {
            if (\count($criticals)+\count($errors)+\count($messages)) {
                if (GCP_PROJECTID && GCP_KEYS && !LOCALHOST) {
                    if (file_exists(APP . DS . GCP_KEYS)) {
                        $logging = new LoggingClient([
                            'projectId' => GCP_PROJECTID,
                            'keyFilePath' => APP . DS . GCP_KEYS,
                        ]);
                        $google_logger = $logging->logger(PROJECT);
                    }
                }
            }
            if (\count($criticals)) {
                $monolog->critical(DOMAIN . ' FATAL: ' . \json_encode($criticals) . $add);
                if ($google_logger) {
                    $google_logger->write($google_logger->entry(DOMAIN . ' ERR: ' . \json_encode($criticals) . $add, [
                        'severity' => Logger::CRITICAL,
                    ]));
                }
            }
            if (count($errors)) {
                $monolog->error(DOMAIN . ' ERROR: ' . \json_encode($errors) . $add);
                if ($google_logger) {
                    $google_logger->write($google_logger->entry(DOMAIN . ' ERR: ' . \json_encode($errors) . $add, [
                        'severity' => Logger::ERROR,
                    ]));
                }
            }
            if (count($messages)) {
                $monolog->info(DOMAIN . ' INFO: ' . \json_encode($messages) . $add);
                if ($google_logger) {
                    $google_logger->write($google_logger->entry(DOMAIN . ' MSG: ' . \json_encode($messages) . $add, [
                        'severity' => Logger::INFO,
                    ]));
                }
            }
        } finally {
            exit(0);
        }
        exit(0);
    }

    /**
     * Get singleton object
     *
     * @static
     * @final
     * @return self
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
     * Get instance for testing
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
    public function renderHTML($template = null)
    {
        if (is_null($template)) {
            $template = 'index';
        }
        // $type: string = 0, template = 1
        $type = (file_exists(TEMPLATES . DS . "{$template}.mustache")) ? 1 : 0;
        $renderer = new \Mustache_Engine(array(
            'template_class_prefix' => '__' . SERVER . '_' . PROJECT . '_',
            'cache' => TEMP,
            'cache_file_mode' => 0666,
            'cache_lambda_templates' => true,
            'loader' => $type ? new \Mustache_Loader_FilesystemLoader(TEMPLATES) : new \Mustache_Loader_StringLoader,
            'partials_loader' => new \Mustache_Loader_FilesystemLoader(PARTIALS),
            'helpers' => [
                'unix_timestamp' => function () {
                    return (string) time();
                },
                'sha256_nonce' => function () {
                    return $this->getNonce();
                },
                'convert_hyperlinks' => function ($source, \Mustache_LambdaHelper $lambdaHelper) {
                    $text = $lambdaHelper->render($source);
                    $text = preg_replace(
                        '/(https)\:\/\/([a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,20})(\/[a-zA-Z0-9\-_\/]*)?/',
                        '<a rel=noopener target=_blank href="$0">$2$3</a>', $text);
                    return (string) $text;
                },
                'shuffle_lines' => function ($source, \Mustache_LambdaHelper $lambdaHelper) {
                    $text = $lambdaHelper->render($source);
                    $arr = explode("\n", $text);
                    shuffle($arr);
                    $text = join("\n", $arr);
                    return (string) $text;
                },
            ],
            'charset' => 'UTF-8',
            'escape' => function ($value) {
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
            'CONST.APP' => APP,
            'CONST.CACHE' => CACHE,
            'CONST.CACHEPREFIX' => CACHEPREFIX,
            'CONST.CLI' => CLI,
            'CONST.CONFIG' => CONFIG,
            'CONST.CONFIG_PRIVATE' => CONFIG_PRIVATE,
            'CONST.CSP' => CSP,
            'CONST.DATA' => DATA,
            'CONST.DOMAIN' => DOMAIN,
            'CONST.DOWNLOAD' => DOWNLOAD,
            'CONST.DS' => DS,
            'CONST.ENABLE_CSV_CACHE' => ENABLE_CSV_CACHE,
            'CONST.LOGS' => LOGS,
            'CONST.MONOLOG' => MONOLOG,
            'CONST.PARTIALS' => PARTIALS,
            'CONST.PROJECT' => PROJECT,
            'CONST.ROOT' => ROOT,
            'CONST.SERVER' => SERVER,
            'CONST.TEMP' => TEMP,
            'CONST.TEMPLATES' => TEMPLATES,
            'CONST.UPLOAD' => UPLOAD,
            'CONST.WWW' => WWW,
        ]);
        $dot->set([ // class constants
            'CONST.COOKIE_KEY_FILEMODE' => self::COOKIE_KEY_FILEMODE,
            'CONST.COOKIE_TTL' => self::COOKIE_TTL,
            'CONST.CSV_FILEMODE' => self::CSV_FILEMODE,
            'CONST.CSV_MIN_SIZE' => self::CSV_MIN_SIZE,
            'CONST.GS_CSV_POSTFIX' => self::GS_CSV_POSTFIX,
            'CONST.GS_CSV_PREFIX' => self::GS_CSV_PREFIX,
            'CONST.GS_SHEET_POSTFIX' => self::GS_SHEET_POSTFIX,
            'CONST.GS_SHEET_PREFIX' => self::GS_SHEET_PREFIX,
            'CONST.LIMITER_MAXIMUM' => self::LIMITER_MAXIMUM,
            'CONST.LOG_FILEMODE' => self::LOG_FILEMODE,
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
     * @return self
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
     * @return self
     */
    public function addAuditMessage($message = null)
    {
        if (is_string($message) && !empty($message)) {
            $file = DATA . DS . 'AuditLog.txt';
            $date = date('c');
            $message = \trim($message);
            $i = $this->getIdentity();
            @file_put_contents($file, "$date;$message;IP:{$i['ip']};NAME:{$i['name']};EMAIL:{$i['email']};\n",
                FILE_APPEND | LOCK_EX
            );
        }

        if (CLI) {
            return $this;
        }

        // Telegram bot support
        $chid = $this->getData('telegram.bot_ch_id') ?? null;
        $apikey = $this->getData('telegram.bot_apikey') ?? null;
        if ($this->getCurrentUser()['name'] !== '') {
            $curl = curl_init();
            $message = htmlspecialchars('🤖 ' . APPNAME . ' (' . DOMAIN . ')' . ': ' . $message . ' [' . $this->getCurrentUser()['name'] . ']');
            if ($curl && $message && $chid && $apikey) {
                $query = '?chat_id=' . $chid . "&text={$message}";
                curl_setopt($curl, CURLOPT_URL, 'https://api.telegram.org/bot' . $apikey . '/sendMessage' . $query);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_exec($curl);
                curl_close($curl);
            }
        }

        return $this;
    }

    /**
     * Add info message
     *
     * @param string $message Message string
     * @return self
     */
    public function addMessage($message = null)
    {
        if (is_string($message) && !empty($message)) {
            $this->messages[] = (string) $message;
        }
        return $this;
    }

    /**
     * Add error message
     *
     * @param string $message Error string
     * @return self
     */
    public function addError($message = null)
    {
        if (is_string($message) && !empty($message)) {
            $this->errors[] = (string) $message;
            $this->addAuditMessage($message);
        }
        return $this;
    }

    /**
     * Add critical message
     *
     * @param string $message Critical error string
     * @return self
     */
    public function addCritical($message = null)
    {
        if (is_string($message) && !empty($message)) {
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
        return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get universal ID string
     *
     * @return string Universal ID string
     */
    public function getUIDstring()
    {
        return preg_replace('/__/', '_', strtr(implode('_',
            [
                CLI ? 'CLI' : '',
                CLI ? '' : $_SERVER['HTTP_ACCEPT_ENCODING'] ?? 'N/A',
                CLI ? '' : $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'N/A',
                CLI ? '' : $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
                $this->getIP(),
            ]),
            ' ', '_'));
    }

    /**
     * Get universal ID hash
     *
     * @return string SHA-256 hash
     */
    public function getUID()
    {
        return \hash('sha256', $this->getUIDstring());
    }

    /**
     * Set user identity
     *
     * @param array $identity Identity array
     * @return self
     */
    public function setIdentity($identity = [])
    {
        if (!\is_array($identity)) {
            $identity = [];
        }
        $i = [
            'avatar' => '',
            'country' => '',
            'email' => '',
            'id' => 0,
            'ip' => '',
            'name' => '',
        ];
        $file = DATA . DS . self::IDENTITY_NONCE; // nonce file
        if (!\file_exists($file)) {
            try {
                $nonce = \hash('sha256', \random_bytes(1024) . \time());
                if (\file_put_contents($file, $nonce, LOCK_EX) === false) {
                    $this->addError('ERROR 500: cannot write nonce file');
                    $this->setLocation('/err/500');
                    exit;
                }
                @\chmod($file, 0660);
                $this->addMessage('ADMIN: nonce file created');
            } catch (\Exception $e) {
                $this->addError('ERROR 500: cannot create nonce file: ' . $e->getMessage());
                $this->setLocation('/err/500');
                exit;
            }
        }
        if (!$nonce = @\file_get_contents($file)) {
            $this->addError('ERROR 500: cannot read nonce file');
            $this->setLocation('/err/500');
            exit;
        }
        $i['nonce'] = \substr(\trim($nonce), 0, 16); // trim nonce to 16 chars
        // check all keys
        if (\array_key_exists('avatar', $identity)) {
            $i['avatar'] = (string) $identity['avatar'];
        }
        if (array_key_exists('email', $identity)) {
            $i['email'] = (string) $identity['email'];
        }
        if (array_key_exists('id', $identity)) {
            $i['id'] = (int) $identity['id'];
        }
        if (array_key_exists('name', $identity)) {
            $i['name'] = (string) $identity['name'];
        }
        // set other values
        $i['country'] = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'XX';
        $i['ip'] = $this->getIP();
        // shuffle keys
        $out = [];
        $keys = \array_keys($i);
        shuffle($keys);
        foreach ($keys as $k) {
            $out[$k] = $i[$k];
        }
        // set new identity
        $this->identity = $out;
        $app = $this->getCfg('app') ?? 'app';
        if ($out['id']) {
            $this->setCookie($app, json_encode($out)); // encrypted cookie
        } else {
            $this->clearCookie($app); // delete cookie
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
                'country' => 'XX',
                'email' => 'john.doe@example.com',
                'id' => 1,
                'ip' => '127.0.0.1',
                'name' => 'John Doe',
            ];
        }

        // check current identity
        $id = $this->identity['id'] ?? null;
        $email = $this->identity['email'] ?? null;
        $name = $this->identity['name'] ?? null;
        if ($id && $email && $name) {
            return $this->identity;
        }
        $file = DATA . DS . self::IDENTITY_NONCE;
        if (!\file_exists($file)) {
            $this->setIdentity(); // set empty identity
            return $this->identity;
        }
        if (!$nonce = @\file_get_contents($file)) {
            $this->addError('ERROR 500: cannot read nonce file');
            $this->setLocation('/err/500');
            exit;
        }
        $nonce = \substr(\trim($nonce), 0, 16); // trim nonce to 16 chars only
        $i = [ // empty identity
            'avatar' => '',
            'country' => '',
            'email' => '',
            'id' => 0,
            'ip' => '',
            'name' => '',
        ];
        do {
            if (isset($_GET['identity'])) { // URL parameter identity
                $tls = '';
                if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
                    $tls = 's';
                }
                $this->setCookie($this->getCfg('app') ?? 'app', $_GET['identity']); // set cookie
                $this->setLocation("http{$tls}://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
                exit;
            }
            if (isset($_COOKIE[$this->getCfg('app') ?? 'app'])) { // COOKIE identity
                $x = 0;
                $q = \json_decode($this->getCookie($this->getCfg('app') ?? 'app') ?? '', true);
                if (!\is_array($q)) {
                    $x++;
                } else {
                    if (!\array_key_exists('email', $q)) {
                        $x++;
                    }
                    if (!\array_key_exists('id', $q)) {
                        $x++;
                    }
                    if (!\array_key_exists('nonce', $q)) {
                        $x++;
                    }
                }
                if ($x) {
                    $this->logout(); // something is terribly wrong!!!
                    break;
                }
                if ($q['nonce'] == $nonce) { // compare nonces
                    $this->identity = $q; // set identity
                    break;
                }
            }
            $this->setIdentity($i); // set empty / mock identity
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
        $u = \array_replace(
            [
                'avatar' => '',
                'country' => '',
                'email' => '',
                'id' => 0,
                'name' => '',
            ],
            $this->getIdentity()
        );
        $u['uid'] = $this->getUID();
        $u['uidstring'] = $this->getUIDstring();
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
            return $this->getData('cfg');
        }
        if (is_string($key)) {
            return $this->getData("cfg.{$key}");
        }
        throw new \Exception('FATAL ERROR: invalid get parameter');
    }

    /**
     * Match getter (alias)
     *
     * @return mixed Match data array
     */
    public function getMatch()
    {
        return $this->getData('match') ?? null;
    }

    /**
     * Presenter getter (alias)
     *
     * @return mixed Rresenter data array
     */
    public function getPresenter()
    {
        return $this->getData('presenter') ?? null;
    }

    /**
     * Router getter (alias)
     *
     * @return mixed Router data array
     */
    public function getRouter()
    {
        return $this->getData('router') ?? null;
    }

    /**
     * View getter (alias)
     *
     * @return mixed Router view
     */
    public function getView()
    {
        return $this->getData('view') ?? null;
    }

    /**
     * Set HTTP header for CSV content
     *
     * @return self
     */
    public function setHeaderCsv()
    {
        \header('Content-Type: text/csv; charset=UTF-8');
        return $this;
    }

    /**
     * Set HTTP header for binary content
     *
     * @return self
     */
    public function setHeaderFile()
    {
        \header('Content-Type: application/octet-stream');
        return $this;
    }

    /**
     * Set HTTP header for HTML content
     *
     * @return self
     */
    public function setHeaderHtml()
    {
        \header('Content-Type: text/html; charset=UTF-8');
        return $this;
    }

    /**
     * Set HTTP header for JSON content
     *
     * @return self
     */
    public function setHeaderJson()
    {
        \header('Content-Type: application/json; charset=UTF-8');
        return $this;
    }

    /**
     * Set HTTP header for JSON content
     *
     * @return self
     */
    public function setHeaderJavaScript()
    {
        \header('Content-Type: application/javascript; charset=UTF-8');
        return $this;
    }

    /**
     * Set HTTP header for PDF content
     *
     * @return self
     */
    public function setHeaderPdf()
    {
        \header('Content-Type: application/pdf');
        return $this;
    }

    /**
     * Set HTTP header for TEXT content
     *
     * @return self
     */
    public function setHeaderText()
    {
        \header('Content-Type: text/plain; charset=UTF-8');
        return $this;
    }

    /**
     * Set HTTP header for XML content
     *
     * @return self
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
        $key = $this->getCfg('secret_cookie_key') ?? 'secure.key'; // secure key
        $key = \trim($key, "/.\\");
        $keyfile = DATA . DS . $key;
        if (\file_exists($keyfile) && is_readable($keyfile)) {
            $enc = KeyFactory::loadEncryptionKey($keyfile);
        } else {
            $this->addError('HALITE: Missing encryption key!');
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
     * @return self
     */
    public function setCookie($name, $data)
    {
        if (empty($name)) {
            return $this;
        }
        $key = $this->getCfg('secret_cookie_key') ?? 'secure.key'; // secure key
        $key = \trim($key, "/.\\");
        $keyfile = DATA . DS . $key;
        if (\file_exists($keyfile) && is_readable($keyfile)) {
            $enc = KeyFactory::loadEncryptionKey($keyfile);
        } else {
            $enc = KeyFactory::generateEncryptionKey();
            if (is_writable(DATA)) {
                KeyFactory::save($enc, $keyfile);
                @\chmod($keyfile, self::COOKIE_KEY_FILEMODE);
                $this->addMessage('HALITE: New keyfile created');
            } else {
                $this->addError('HALITE: Cannot write encryption key!');
            }
        }
        $cookie = new Cookie($enc);
        if (DOMAIN == 'localhost') {
            $httponly = true;
            $samesite = 'lax';
            $secure = false;
        } else {
            $httponly = true;
            $samesite = 'lax';
            $secure = true;
        }
        if (!CLI) {
            $cookie->store($name, (string) $data, time() + self::COOKIE_TTL, '/', DOMAIN, $secure, $httponly, $samesite);
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
        if (($this->cookies[$name] ?? null) || ($_COOKIE[$name] ?? null)) {
            unset($_COOKIE[$name]);
            unset($this->cookies[$name]);
            \setcookie($name, '', time() - 3600, '/');
        }
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
            $location = '/?nonce=' . $this->getNonce();
        }

        // audit certain messages
        if (!LOCALHOST && !CLI && $code > 303) {
            $ref = "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
            $this->addAuditMessage("ERROR: {$code}; ref. {$ref}");
        }
        \header("Location: $location", true, ($code > 300) ? $code : 303);
        exit;
    }

    /**
     * Logout
     */
    public function logout()
    {
        if (CLI) {
            exit;
        }
        $this->setIdentity();
        $this->clearCookie($this->getCfg('app') ?? 'app');
        \header('Clear-Site-Data: "cookies"');
        $this->setLocation('/?logout&nonce=' . $this->getNonce());
        exit;
    }

    /**
     * Check current user rate limits
     *
     * @param integer $max Hits per second (optional)
     * @return self
     */
    public function checkRateLimit($max = self::LIMITER_MAXIMUM)
    {
//        if (CLI) {
        //            return $this;
        //        }
        //        if (LOCALHOST) {
        //            return $this;
        //        }
        $f = "user_rate_limit_{$this->getUID()}";
        $rate = (int) (Cache::read($f, 'limiter') ?? 0);
        Cache::write($f, ++$rate, 'limiter');
        if ($rate > (int) $max) { // over limits
            $this->setLocation('/err/420');
            exit;
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
            return null;
        }
        if (LOCALHOST) {
            return null;
        }
        return Cache::read("user_rate_limit_{$this->getUID()}", 'limiter');
    }

    /**
     * Check if current user has access rights
     *
     * @param mixed $rolelist roles (optional)
     * @return self
     */
    public function checkPermission($rolelist = 'admin')
    {
        if (CLI) {
            return $this;
        }
        if (empty($rolelist)) {
            return $this;
        }
        $roles = \explode(',', \trim((string) $rolelist));
        foreach ($roles as $role) {
            $role = \strtolower(\trim($role));
            $email = $this->getIdentity()['email'] ?? '';
            $groups = $this->getCfg('admin_groups') ?? [];
            if (\strlen($role) && \strlen($email)) {
                if (\in_array($email, $groups[$role] ?? [], true)) { // email allowed
                    return $this;
                }
                if (\in_array('*', $groups[$role] ?? [], true)) { // any Google users allowed
                    return $this;
                }
            }
        }
        $this->setLocation('/err/401'); // not authorized
        exit;
    }

    /**
     * Get current user group
     *
     * @return string User group name
     */
    public function getUserGroup()
    {
        $id = $this->getIdentity()['id'] ?? null;
        $email = $this->getIdentity()['email'] ?? null;
        if (!$id) {
            return null;
        }
        $mygroup = null;
        $email = \trim((string) $email);

        // search all groups for email or asterisk
        foreach ($this->getCfg('admin_groups') ?? [] as $group => $users) {
            if (in_array($email, $users, true)) {
                $mygroup = $group;
                break;
            }
            if (in_array('*', $users, true)) {
                $mygroup = $group;
                continue;
            }
        }
        return $mygroup;
    }

    /**
     * Force CSV checking
     *
     * @param boolean $set Set true to force CSV check (optional)
     * @return self
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
     * @return self
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
    public function getLocale($language, $key = 'KEY')
    {
        if (!\is_array($this->getCfg('locales'))) {
            return null;
        }
        $locale = [];
        $language = \trim(\strtoupper((string) $language));
        $key = \trim(\strtoupper((string) $key));
        $cfg = $this->getCfg();
        $file = \strtolower("{$language}_locale");
        $locale = Cache::read($file, 'default');
        if ($locale === false || empty($locale)) {
            if (\array_key_exists('locales', $cfg)) {
                $locale = [];
                foreach ((array) $cfg['locales'] as $k => $v) {

                    // 1. read from CSV file
                    $csv = false;
                    $subfile = \strtolower($k);
                    if ($csv === false && \file_exists((DATA . DS . "{$subfile}.csv"))) {
                        $csv = @\file_get_contents(DATA . DS . "{$subfile}.csv");
                        if ($csv === false || \strlen($csv) < self::CSV_MIN_SIZE) {
                            $csv = false;
                        }
                    }

                    // 2. read from CSV file backup
                    if ($csv === false && \file_exists(DATA . DS . "{$subfile}.bak")) {
                        $csv = @\file_get_contents(DATA . DS . "{$subfile}.bak");
                        if ($csv === false || \strlen($csv) < self::CSV_MIN_SIZE) {
                            $csv = false;
                            continue; // skip this CSV
                        } else {
                            \copy(DATA . DS . "{$subfile}.bak", DATA . DS . "{$subfile}.csv");
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
                    } catch (\Exception $e) {
                        $this->addCritical("LOCALE ERROR: $language [$k] CORRUPTED");
                        $this->addAuditMessage("LOCALE ERROR: $language [$k] CORRUPTED");
                        continue;
                    }
                    $locale = \array_replace($locale, \array_combine($keys, $values));
                }

                // EXTRA locale variable = git revisions
                $locale['$revisions'] = $this->getData('REVISIONS');

                // find all $ in combined locales array
                $dolar = ['$' => '$'];
                foreach ((array) $locale as $a => $b) {
                    if (\substr($a, 0, 1) === '$') {
                        $a = \trim($a, '{}$' . "\x20\t\n\r\0\x0B");
                        if (!\strlen($a)) {
                            continue;
                        }
                        $dolar['$' . $a] = $b;
                        $dolar['{$' . $a . '}'] = $b;
                    }
                }
                // replace $ and $$
                $locale = \str_replace(\array_keys($dolar), $dolar, $locale);
                $locale = \str_replace(\array_keys($dolar), $dolar, $locale);
            }
        }
        if ($locale === false || empty($locale)) {
            if ($this->force_csv_check) {
                \header('HTTP/1.1 500 FATAL ERROR');
                $this->addCritical('ERROR: LOCALES CORRUPTED!');
                echo '<body><h1>HTTP Error 500</h1><h2>LOCALES CORRUPTED!</h2></body>';
                exit;
            } else {
                $this->checkLocales(true); // second try!
                return $this->getLocale($language, $key);
            }
        }
        Cache::write($file, $locale, 'default');
        return (array) $locale;
    }

    /**
     * Check and preload locales
     *
     * @param boolean $force force loading locales (optional)
     * @return self
     */
    public function checkLocales(bool $force = false)
    {
        $locales = $this->getCfg('locales') ?? null;
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
     * @return self
     */
    public function CloudflarePurgeCache($cf)
    {
        if (!\is_array($cf)) {
            return $this;
        }
        $email = $cf['email'] ?? null;
        $apikey = $cf['apikey'] ?? null;
        $zoneid = $cf['zoneid'] ?? null;
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
                            $this->addMessage("Cloudflare: zone {$myzone} purged");
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->addError('Cloudflare exception: ' . (string) $e->getMessage());
        }
        return $this;
    }

    /**
     * Load CSV data into cache
     *
     * @param string $name CSV nickname (foobar)
     * @param string $csvkey Google CSV token (partial or full URI to CSV export endpoint)
     * @param boolean $force force the resource refresh? (optional)
     * @return self
     */
    private function csv_preloader($name, $csvkey, $force = false)
    {
        $name = \trim((string) $name);
        $csvkey = \trim((string) $csvkey);
        $force = (bool) $force;
        $file = \strtolower($name);
        if ($name && $csvkey) {
            if (Cache::read($file, 'csv') === false || $force === true) {
                $data = false;
                if (!\file_exists(DATA . DS . "{$file}.csv")) {
                    $force = true;
                }
                if ($force) {
                    if (\strpos($csvkey, 'https') === 0) { // contains full path
                        $remote = $csvkey;
                    } else {
                        if (\strpos($csvkey, '?gid=') > 0) { // contains path incl. parameters
                            $remote = self::GS_CSV_PREFIX . $csvkey;
                        } else {
                            $remote = self::GS_CSV_PREFIX . $csvkey . self::GS_CSV_POSTFIX;
                        }
                    }
                    $this->addMessage("FILE: fetching {$remote}");
                    try {
                        $data = @\file_get_contents($remote);
                    } catch (\Exception $e) {
                        $this->addError("ERROR: fetching {$remote}");
                        $data = '';
                    }
                }
                if (\strpos($data, '!DOCTYPE html') > 0) {
                    return $this; // we got HTML document = failure
                }
                if (\strlen($data) >= self::CSV_MIN_SIZE) {
                    Cache::write($file, $data, 'csv');

                    // remove old backup
                    if (\file_exists(DATA . DS . "{$file}.bak")) {
                        if (@\unlink(DATA . DS . "{$file}.bak") === false) {
                            $this->addError("FILE: remove {$file}.bak failed!");
                        }
                    }

                    // move CSV to backup
                    if (\file_exists(DATA . DS . "{$file}.csv")) {
                        if (@\rename(DATA . DS . "{$file}.csv", DATA . DS . "{$file}.bak") === false) {
                            $this->addError("FILE: backup {$file}.csv failed!");
                        }
                    }

                    // write new CSV
                    if (\file_put_contents(DATA . DS . "{$file}.csv", $data, LOCK_EX) === false) {
                        $this->addError("FILE: save {$file}.csv failed!");
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Pre-load application CSV data
     *
     * @param string Configuration array name (optional)
     * @param boolean force load? (optional)
     * @return self
     */
    public function preloadAppData($key = 'app_data', $force = false)
    {
        if (empty($key) || !strlen($key)) {
            $key = 'app_data';
        }
        $key = \strtolower(\trim((string) $key));
        $cfg = $this->getCfg();
        if (\array_key_exists($key, $cfg)) {
            foreach ((array) $cfg[$key] as $name => $csvkey) {
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
        if (empty($name) || !strlen($name)) {
            return '';
        }
        $file = \strtolower($name);
        if (empty($file)) {
            return null; // failure
        }
        if (!$csv = Cache::read($file, 'csv')) { // read CSV from cache
            $csv = false;
            if (\file_exists(DATA . DS . "{$file}.csv")) {
                $csv = \file_get_contents(DATA . DS . "{$file}.csv");
            }
            if (\strpos($csv, '!DOCTYPE html') > 0) {
                $csv = false; // we got HTML document, try backup
            }
            if ($csv !== false || \strlen($csv) >= self::CSV_MIN_SIZE) {
                Cache::write($file, $csv, 'csv'); // store into cache
                return $csv; // CSV is OK
            }
            $csv = false;
            if (\file_exists(DATA . DS . "{$file}.bak")) {
                $csv = \file_get_contents(DATA . DS . "{$file}.bak"); // read CSV backup
            }
            if (\strpos($csv, '!DOCTYPE html') > 0) {
                return null; // we got HTML document = failure
            }
            if ($csv !== false || \strlen($csv) >= self::CSV_MIN_SIZE) {
                \copy(DATA . DS . "{$file}.bak", DATA . DS . "{$file}.csv"); // copy BAK to CSV
                Cache::write($file, $csv, 'csv'); // store into cache
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
     * @return self
     */
    public function writeJsonData($data, $headers = [], $switches = null)
    {
        $code = 200;
        $time = \time();
        $out = [
            'timestamp' => $time,
            'timestamp_RFC2822' => date(\DATE_RFC2822, $time),
            'version' => (string) ($this->getCfg('version') ?? 'v1'),
        ];
        switch (\json_last_error()) { // last decoding error
            case JSON_ERROR_NONE:
                $code = 200;
                $msg = 'DATA OK';
                break;
            case JSON_ERROR_DEPTH:
                $code = 400;
                $msg = 'Maximum stack depth exceeded.';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $code = 400;
                $msg = 'Underflow or the modes mismatch.';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $code = 400;
                $msg = 'Unexpected control character found.';
                break;
            case JSON_ERROR_SYNTAX:
                $code = 500;
                $msg = 'Syntax error, malformed JSON.';
                break;
            case JSON_ERROR_UTF8:
                $code = 400;
                $msg = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
                break;
            default:
                $code = 500;
                $msg = 'Internal server error.';
                break;
        }
        if (is_null($data)) {
            $code = 500;
            $msg = 'Data is NULL! Internal Server Error 🦄';
            \header('HTTP/1.1 500 Internal Server Error');
        }
        if (is_string($data)) {
            $data = [$data];
        }
        if (is_int($data)) {
            $code = $data;
            $data = null;
            $h = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
            $m = null;
            switch ($code) {
                case 304:
                    $m = 'Not Modified';
                    break;
                case 400:
                    $m = 'Bad request';
                    break;
                case 401:
                    $m = 'Unauthorized';
                    break;
                case 402:
                    $m = 'Payment Required';
                    break;
                case 403:
                    $m = 'Forbidden';
                    break;
                case 404:
                    $m = 'Not Found';
                    break;
                case 405:
                    $m = 'Method Not Allowed';
                    break;
                case 406:
                    $m = 'Not Acceptable';
                    break;
                case 409:
                    $m = 'Conflict';
                    break;
                case 410:
                    $m = 'Gone';
                    break;
                case 412:
                    $m = 'Precondition Failed';
                    break;
                case 415:
                    $m = 'Unsupported Media Type';
                    break;
                case 416:
                    $m = 'Requested Range Not Satisfiable';
                    break;
                case 417:
                    $m = 'Expectation Failed';
                    break;
                default:
                    $msg = 'Unknown Error 🦄';
            }
            if ($m) {
                $msg = "$m.";
                \header("$h $code $m"); // set corresponding HTTP header
            }
        }
        // output
        $this->setHeaderJson();
        $out['code'] = (int) $code;
        $out['message'] = $msg;
        $out['processing_time'] = \round((\microtime(true) - TESSERACT_START) * 1000, 2) . ' ms';

        // merge headers
        $out = \array_merge_recursive($out, $headers);

        // set data model
        $out['data'] = $data ?? null;

        // process extra switches
        if (\is_null($switches)) {
            return $this->setData('output', \json_encode($out, JSON_PRETTY_PRINT));
        }
        return $this->setData('output', \json_encode($out, JSON_PRETTY_PRINT | $switches));
    }

    /**
     * Data Expander
     *
     * @param array $data Model by reference
     * @return self
     */
    public function dataExpander(&$data)
    {
        if (empty($data)) {
            return $this;
        }
        $data['user'] = $user = $this->getCurrentUser(); // logged user
        $data['admin'] = $group = $this->getUserGroup(); // logged user group

        // solve caching
        $use_cache = true;
        if (\array_key_exists('nonce', $_GET)) { // do not cache pages with nonce
            $use_cache = false;
        }
        if (\array_key_exists('logout', $_GET)) { // do not cache pages with logout
            $use_cache = false;
        }
        if ($group) {
            $data["admin_group_{$group}"] = true;
        }
        if ($user['id']) { // do not cache anything for logged users
            $use_cache = false;
        }
        $data['use_cache'] = $use_cache;

        // set language
        $presenter = $this->getPresenter();
        $view = $this->getView();
        if ($presenter && $view) {
            $data['lang'] = $language = \strtolower($presenter[$view]['language']) ?? 'cs';
            $data["lang{$language}"] = true;
        } else {
            // something is terribly wrong!
            return $this;
        }

        // get locale
        $l = $this->getLocale($language);
        if (is_null($l)) {
            $l = [];
            $l['title'] = 'MISSING LOCALES!';
        }
        if (!\array_key_exists('l', $data)) {
            $data['l'] = $l;
        }

        // compute data hash
        $data['DATA_VERSION'] = \hash('sha256', (string) \json_encode($l));

        // extract request path slug
        if (($pos = \strpos($data['request_path'], $language)) !== false) {
            $data['request_path_slug'] = \substr_replace($data['request_path'], '', $pos, \strlen($language));
        } else {
            $data['request_path_slug'] = $data['request_path'] ?? '';
        }
        return $this;
    }

    /**
     * Nonce string generator
     *
     * @return string nonce (number used once)
     */
    public function getNonce()
    {
        return (string) \substr(\hash('sha256', \random_bytes(16) . (string) \time()), 0, 16);
    }
}
