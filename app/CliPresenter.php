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
use League\CLImate\CLImate;

/**
 * CLI Presenter class
 * 
 * @package GSC
 */
class CliPresenter extends APresenter
{
    /**
     * Controller processor
     *
     * @return self
     */
    public function process()
    {
        $climate = new CLImate;
        $climate->out("<bold><green>Tesseract CLI</green></bold>\tapp: "
            . $this->getData("VERSION_SHORT")
            . " (" . str_replace(" ", "", $this->getData("VERSION_DATE")) . ")\n");
        return $this;
    }

    /**
     * Show presenter output
     * 
     * @param string presenter name (optional)
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
        $controller = "\\GSC\\${c}";

        echo $controller::getInstance()->setData($data)->process()->getData()["output"] ?? "";
        exit(0);
    }

    /**
     * Show CORE presenter output
     * 
     * @param string view name inside CORE presenter
     * @param array arguments (optional)
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
        $controller = "\\GSC\\${c}";
        $data["view"] = $v;

        $data["base"] = $arg["base"] ?? "https://example.com/";
        $data["match"] = $arg["match"] ?? null;

        echo $controller::getInstance()->setData($data)->process()->getData()["output"] ?? "";
        exit(0);
    }

    /**
     * Display user defined constants
     *
     * @return self
     */
    private function showConst()
    {
        $arr = array_filter(get_defined_constants(true)["user"], function ($key) {
            return !(stripos($key, "sodium") === 0); // filter out Sodium constants
        }, ARRAY_FILTER_USE_KEY);
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
        $climate->out("Usage: \t <bold>php -f Bootstrap.php <command> [<parameter> ...]</bold>\n");
        $climate->out("\t <bold>app</bold> '<code>'\t - run inline code");
        $climate->out("\t <bold>clear</bold>\t\t - alias for <bold>clearall</bold>");
        $climate->out("\t <bold>clearall</bold>\t - clear all temporary files");
        $climate->out("\t <bold>clearcache</bold>\t - clear cache");
        $climate->out("\t <bold>clearci</bold>\t - clear CI logs");
        $climate->out("\t <bold>clearlogs</bold>\t - clear runtime logs");
        $climate->out("\t <bold>cleartemp</bold>\t - clear temporary files");
        $climate->out("\t <bold>doctor</bold>\t\t - check system requirements");
        $climate->out("\t <bold>local</bold>\t\t - run local CI test");
        $climate->out("\t <bold>prod</bold>\t\t - run production CI test");
        $climate->out("\t <bold>unit</bold>\t\t - run Unit tests");
        $climate->out("\t <bold>version</bold>\t - display version information in JSON format\n");
        return $this;
    }

    /**
     * Evaluate input string
     *
     * @param object this object
     * @param int ARGC
     * @param array ARGV
     * @return self
     */
    private function evaler($app, $argc, $argv)
    {
        $climate = new CLImate;
        if ($argc != 3) {
            // show examples
            $climate->out("Examples:");
            $climate->out("\t" . '<bold>app</bold> \'$app->showConst()\'');
            $climate->out("\t" . '<bold>app</bold> \'dump($app->getCurrentUser())\'');
            $climate->out("\t" . '<bold>app</bold> \'dump($app->getIdentity())\'');
            $climate->out("\t" . '<bold>app</bold> \'$app->show()\'');
            $climate->out("\t" . '<bold>app</bold> \'$app->showCore("GetTXTSitemap")\'');
            $climate->out("\t" . '<bold>app</bold> \'$app->showCore("GetWebManifest")\'');
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
     * @param string CLI parameter
     * @param int ARGC
     * @param array ARGV
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
                require_once "CiTester.php";
                new CiTester($this->getCfg(), $this->getPresenter(), $module);
                break;

            case "clearcache":
                foreach ($this->getData("cache_profiles") as $k => $v) { // clear all cache profiles
                    Cache::clear($k);
                    Cache::clear("${k}_file");
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
                require_once "UnitTester.php";
                new UnitTester;
                break;

            case "doctor":
                require_once "Doctor.php";
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
