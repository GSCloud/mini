<?php
/**
 * GSC Tesseract
 *
 * @category Framework
 * @author   Fred Brooker <oscadal@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 */

namespace GSC;

use League\CLImate\CLImate;

class CiTester
{
    /**
     * Main controller
     *
     * @param array $cfg Configuration.
     * @param array $presenter Presenter.
     * @param string $type Test type: testlocal, testprod.
     *
     * @return void
     */
    public function __construct($cfg, $presenter, $type)
    {
        $climate = new CLImate;
        $cfg = (array) $cfg;
        $presenter = (array) $presenter;
        $type = (string) $type;

        switch ($type) {
            case "local":
            case "testlocal":
                $case = "local";
                $target = $cfg["local_goauth_origin"] ?? "";
                break;

            case "prod":
            case "testprod":
            default:
                $case = "production";
                $target = $cfg["goauth_origin"] ?? "";
        }

        if (!strlen($target)) {
            $climate->out("<bold><green>${cfg['app']} ${case}");
            $climate->out("<red>FATAL ERROR: missing target URI!\n\007");
            exit;
        }

        $climate->out("CI testing: <bold><green>${cfg['app']} ${case}\n");

        $i = 0;
        $pages = [];
        $redirects = [];

        foreach ($presenter as $p) {
            if (strpos($p["path"], "[") !== false) {
                continue;
            }
            if (strpos($p["path"], "*") !== false) {
                continue;
            }
            if ($p["redirect"] ?? false) {
                $redirects[$i]["path"] = $p["path"];
                $redirects[$i]["site"] = $target;
                $redirects[$i]["assert_httpcode"] = 303;
                if (stripos($p["redirect"], "http") === false) {
                    $redirects[$i]["url"] = $target . $p["path"];
                } else {
                    $redirects[$i]["url"] = $p["redirect"];
                }
            } else {
                $pages[$i]["path"] = $p["path"];
                $pages[$i]["site"] = $target;
                $pages[$i]["assert_httpcode"] = $p["assert_httpcode"];
                $pages[$i]["url"] = $target . $p["path"];
            }
            $i++;
        }
        ksort($pages);
        ksort($redirects);
        $p = array_merge($redirects, $pages);

        $i = 0;
        $ch = [];
        // create curl multi
        $multi = curl_multi_init();
        foreach ($p as $x) {
            $ch[$i] = curl_init();
            curl_setopt($ch[$i], CURLOPT_URL, $x["url"]);
            curl_setopt($ch[$i], CURLOPT_HEADER, true);
            curl_setopt($ch[$i], CURLOPT_NOBODY, false);
            curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch[$i], CURLOPT_TIMEOUT, 10);
            curl_multi_add_handle($multi, $ch[$i]);
            $i++;
        }

        $active = null;
        // process all curl calls
        do {
            $mrc = curl_multi_exec($multi, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($multi) != -1) {
                do {
                    $mrc = curl_multi_exec($multi, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        $i = 0;
        $errors = 0;
        // process results
        foreach ($p as $x) {
            $output = curl_multi_getcontent($ch[$i]);
            $code = curl_getinfo($ch[$i], CURLINFO_HTTP_CODE);
            $length = strlen($output);
            @file_put_contents(ROOT . "/ci/" . date("Y-m-d") . strtr("_${target}_${x['path']}", '\/:.', '____') . ".curl.txt", $output);

            $u1 = "<bold>${x['site']}${x['path']}</bold>";
            $u2 = "${x['site']}${x['path']}";

            $f = date("Y-m-d") . strtr("_${target}", '\/:.', '____');
            if ($code == $x["assert_httpcode"]) {
                $climate->out(
                    "${u1};length:<green>${length}</green>;code:<green>${code}</green>"
                );
                @file_put_contents(ROOT . "/ci/tests_${f}.assert.txt",
                    "${u2};length:${length};code:${code};assert:${x['assert_httpcode']}" . "\n", FILE_APPEND | LOCK_EX);
            } else {
                $errors++;
                $climate->out(
                    "<red>${u1};length:<bold>${length}</bold>;code:<bold>${code}</bold>;assert:<bold>${x['assert_httpcode']}</bold></red>\007"
                );
                @file_put_contents(ROOT . "/ci/errors_${f}.assert.txt",
                    "${u2};length:${length};code:${code};assert:${x['assert_httpcode']}" . "\n", FILE_APPEND | LOCK_EX);
            }
            curl_multi_remove_handle($multi, $ch[$i]);
            $i++;
        }
        curl_multi_close($multi);

        if ($errors) {
            $climate->out("Errors: <bold>" . $errors . "\007\n\n");
        } else {
            echo "\n";
        }
        exit($errors);
    }
}
