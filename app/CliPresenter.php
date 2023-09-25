<?php
/**
 * GSC Tesseract
 * php version 8.2
 *
 * @category CMS
 * @package  Framework
 * @author   Fred Brooker <git@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://lasagna.gscloud.cz
 */

namespace GSC;

use Cake\Cache\Cache;
use League\CLImate\CLImate;

/**
 * CLI Presenter class
 * 
 * @category CMS
 * @package  Framework
 * @author   Fred Brooker <git@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://lasagna.gscloud.cz
 */
class CliPresenter extends APresenter
{
    /**
     * Controller processor
     * 
     * @param mixed $param optional parameter
     * 
     * @return self
     */
    public function process($param = null)
    {
        $climate = new CLImate;
        $climate->out(
            "<bold><green>Tesseract CLI</green></bold>\tapp: "
            . $this->getData("VERSION_SHORT")
            . " (" . $this->getData("VERSION_DATE") . ")\n"
        );
        return $this;
    }

    /**
     * Show presenter output
     * 
     * @param mixed $p presenter name
     * 
     * @return void
     */
    public function show($p = "home")
    {
        $p = trim($p);
        if (empty($p) || !strlen($p)) { // no presenter
            die("FATAL ERROR: No presenter is set!\n");
        }
        $data = $this->getData();
        $router = $this->getRouter();
        $presenter = $this->getPresenter();

        $route = $router[$p];
        $pres = $route["presenter"] ?? "home";
        $data["view"] = $route["view"] ?? "home";
        $data["controller"] = $c = ucfirst(strtolower($pres)) . "Presenter";
        $controller = "\\GSC\\{$c}";

        echo $controller::getInstance()
            ->setData($data)->process()->getData()["output"] ?? "";
        exit(0);
    }

    /**
     * Show CORE presenter output
     * 
     * @param string $v   view name inside CORE presenter
     * @param array  $arg arguments (optional)
     * 
     * @return void
     */
    public function showCore($v = "PingBack", $arg = null)
    {
        $v = trim($v);
        if (empty($v) || !strlen($v)) { // no view
            die("FATAL ERROR: No view is set!\n");
        }
        $data = $this->getData();
        $router = $this->getRouter();
        $presenter = $this->getPresenter();

        $data["controller"] = $c = "CorePresenter";
        $controller = "\\GSC\\{$c}";
        $data["view"] = $v;

        $data["base"] = $arg["base"] ?? "https://example.com/";
        $data["match"] = $arg["match"] ?? null;

        echo $controller::getInstance()
            ->setData($data)->process()->getData()["output"] ?? "";
        exit(0);
    }

    /**
     * Display user defined constants
     *
     * @return self
     */
    public function showConst()
    {
        $arr = array_filter(
            get_defined_constants(true)["user"], function ($key) {
                // filter out Sodium constants
                return !(stripos($key, "sodium") === 0);
            }, ARRAY_FILTER_USE_KEY
        );
        dump($arr);
        return $this;
    }

    /**
     * Display CLI help
     *
     * @return self
     */
    public function help()
    {
        $climate = new CLImate;
        $climate->out(
            "Usage: \t<bold>php -f Bootstrap.php"
            . " <command> [<param> ...]</bold>\n"
        );
        $climate->out("\t<bold>app</bold> '<code>'\t- run inline code");
        $climate->out("\t<bold>clear</bold>\t\t- alias for <bold>clearall</bold>");
        $climate->out("\t<bold>clearall</bold>\t- clear all temporary files");
        $climate->out("\t<bold>clearcache</bold>\t- clear cache");
        $climate->out("\t<bold>clearci</bold>\t\t- clear CI logs");
        $climate->out("\t<bold>clearlogs</bold>\t- clear runtime logs");
        $climate->out("\t<bold>cleartemp</bold>\t- clear temporary files");
        $climate->out("\t<bold>doctor</bold>\t\t- check system requirements");
        $climate->out("\t<bold>local</bold>\t\t- run local CI test");
        $climate->out("\t<bold>prod</bold>\t\t- run production CI test");
        $climate->out("\t<bold>unit</bold>\t\t- run Unit tests");
        $climate->out(
            "\t<bold>version</bold>\t\t"
            . "- display version information in JSON format\n"
        );
        return $this;
    }

    /**
     * Evaluate input string
     *
     * @param $app  object this object
     * @param $argc int ARGC
     * @param $argv array ARGV
     * 
     * @return self
     */
    public function evaler($app, $argc, $argv)
    {
        $climate = new CLImate;
        if ($argc != 3) {
            // show examples
            $climate->out("Tesseract app examples:\n");
            $climate->out('<bold>app</bold> \'$app->showConst()\'');
            $climate->out('<bold>app</bold> \'dump($app->getIdentity())\'');
        } else {
            $code = trim($argv[2]) . ';';
            try {
                //error_reporting(0);
                eval($code);
            } catch (ParseError $e) {
                echo 'Caught exception: ' . $e->getMessage() . "\n";
            }
            error_reporting(E_ALL);
        }
        echo "\n";
        return $this;
    }

    /**
     * Select CLI module
     *
     * @param $module string CLI parameter
     * @param $argc   int ARGC number of arguments
     * @param $argv   array ARGV array of arguments
     * 
     * @return void
     */
    public function selectModule($module, $argc = null, $argv = null)
    {
        $climate = new CLImate;
        $module = trim($module);
        switch ($module) {
        case "clear":
        case "clearall":
            $this->selectModule("clearcache");
            $this->selectModule("clearci");
            $this->selectModule("clearlogs");
            $this->selectModule("cleartemp");
            $climate->out('');
            break;

        case "local":
        case "prod":
        case "testlocal":
        case "testprod":
            include_once "CiTester.php";
            new CiTester($this->getCfg(), $this->getPresenter(), $module);
            break;

        case "clearcache":
            foreach ($this->getData("cache_profiles") as $k => $v) {
                // clear all cache profiles
                Cache::clear($k);
                Cache::clear("{$k}_file");
            }
            array_map("unlink", glob(CACHE . DS . "*.php"));
            array_map("unlink", glob(CACHE . DS . "*.tmp"));
            array_map("unlink", glob(CACHE . DS . CACHEPREFIX . "*"));
            clearstatcache();
            $climate->out("<bold>Cache 🧹</bold>");
            break;

        case "clearci":
            $files = glob(ROOT . DS . "ci" . DS . "*");
            $c = count($files);
            array_map("unlink", $files);
            $climate->out("CI logs <bold>$c file(s) 🧹</bold>");
            break;

        case "clearlogs":
            $files = glob(LOGS . DS . "*");
            $c = count($files);
            array_map("unlink", $files);
            $climate->out("Other logs <bold>$c file(s) 🧹</bold>");
            break;

        case "cleartemp":
            $files = glob(TEMP . DS . "*");
            $c = count($files);
            array_map("unlink", $files);
            $climate->out("Temporary <bold>$c file(s) 🧹</bold>");
            break;

        case "unit":
            include_once "UnitTester.php";
            new UnitTester;
            break;

        case "doctor":
            include_once "Doctor.php";
            new Doctor;
            break;

        case "app":
            $this->evaler($this, $argc, $argv);
            break;

        default:
            $module = ucfirst(strtolower($module));
            $presenter = "\\GSC\\Cli{$module}";
            if (\class_exists($presenter)) {
                $presenter::getInstance()->setData($this->getData())->process();
                exit;
            }
            $this->help();
            return $this;
                break;
        }
    }
}
