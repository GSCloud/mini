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
        \Tracy\Debugger::timer("CITEST");
        $climate = new CLImate;
        $cfg = (array) $cfg;
        $key = $cfg["ci_tester"]["api_key"] ?? "";
        $presenter = (array) $presenter;
        $type = (string) $type;

        switch ($type) {
            case "local":
            case "testlocal":
                $case = "local";
                $target = $cfg["test_origin"] ?? $cfg["local_goauth_origin"] ?? "";
                break;

            case "prod":
            case "testprod":
            default:
                $case = "production";
                $target = $cfg["goauth_origin"] ?? "";
        }
        if (!isset($cfg['app'])) {
            $climate->out("<red>FATAL ERROR: missing app definition!\n\007");
            exit;
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
                $u = "<bold><blue>${target}${p['path']}</blue></bold>";
                $climate->out(
                    "${u};skipped"
                );
                continue;
            }
            if (strpos($p["path"], "*") !== false) {
                $u = "<bold><blue>${target}${p['path']}</blue></bold>";
                $climate->out(
                    "${u};skipped"
                );
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
                $pages[$i]["assert_json"] = $p["assert_json"];
                $pages[$i]["assert_values"] = $p["assert_values"];
                $pages[$i]["url"] = $target . $p["path"];
            }
            $i++;
        }
        ksort($pages);
        ksort($redirects);
        $pages_reworked = array_merge($redirects, $pages);

        // setup curl multi
        $i = 0;
        $ch = [];
        $multi = curl_multi_init();
        foreach ($pages_reworked as $x) {
            $ch[$i] = curl_init();
            curl_setopt($ch[$i], CURLOPT_URL, $x["url"] . "?api=${key}");
            curl_setopt($ch[$i], CURLINFO_HEADER_OUT, true);
            curl_setopt($ch[$i], CURLOPT_BUFFERSIZE, 4096);
            curl_setopt($ch[$i], CURLOPT_FAILONERROR, true);
            curl_setopt($ch[$i], CURLOPT_HEADER, true);
            curl_setopt($ch[$i], CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch[$i], CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch[$i], CURLOPT_NOBODY, false);
            curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch[$i], CURLOPT_TIMEOUT, 20);
            curl_multi_add_handle($multi, $ch[$i]);
            $i++;
        }

        // wait for all curls to finish
        $active = null;
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

        // parse results
        $i = 0;
        $errors = 0;
        foreach ($pages_reworked as $x) {
            $bad = 0;
            $f1 = date("Y-m-d") . strtr("_${target}", '\/:.', '____');
            $f2 = date("Y-m-d") . strtr("_${target}_${x['path']}", '\/:.', '____');
            $u1 = "<bold>${x['site']}${x['path']}</bold>";
            $u2 = "${x['site']}${x['path']}";

            // get curl data
            $m = curl_multi_getcontent($ch[$i]);
            @file_put_contents(ROOT . "/ci/${f2}.curl.txt", $m);
            curl_multi_remove_handle($multi, $ch[$i]);

            // separate headers and content
            $information = explode("\r\n\r\n", $m);
            $headers = [];
            $content = [];
            foreach ($information as $index => $segment) {
                if (0 === mb_strpos($segment, "HTTP/", 0)) {
                    array_push($headers, $segment);
                } else {
                    array_push($content, $segment);
                }
            }
            $content = implode("\r\n\r\n", $content);
            $headers = implode("\r\n\r\n", $headers);
            $code = curl_getinfo($ch[$i], CURLINFO_HTTP_CODE);
            $time = round(curl_getinfo($ch[$i], CURLINFO_TOTAL_TIME) * 1000, 1);
            $length = strlen($content);

            // assert JSON
            $json = true;
            $jsformat = "HTML";
            $jscode = "-";
            if ($x["assert_json"]) {
                $arr = json_decode($content, true);
                if (is_null($arr)) {
                    $bad++;
                    $json = false;
                    $jsformat = "JSON_ERROR";
                    $climate->out('!!! JSON ERRROR !!!');
                    $climate->out($content);
                } else {
                    $jsformat = "JSON";
                    if ($arr["code"] == 200) {
                        $jscode = "200";
                    } else {
                        $jscode = "BAD_CODE:" . $arr["code"];
                        $bad++;
                        $climate->out('!!! JSON CODE ERRROR !!!');
                    }
                }
            }

            // assert HTTP code
            $http_code = true;
            if ($code != $x["assert_httpcode"]) {
                $bad++;
                $http_code = false;
                $climate->out('!!! HTTP CODE ERRROR !!!');
            }
            if ($bad == 0) { // OK
                $climate->out(
                    "${u1} length: <green>${length}</green> code: <green>${code}</green> time: <green>${time} ms</green> format: <blue>$jsformat</blue> JScode: <green>$jscode</green>"
                );
                @file_put_contents(ROOT . "/ci/tests_${f1}.assert.txt",
                    "${u2};length:${length};code:${code};assert:${x['assert_httpcode']};time:${time};$jsformat;$jscode\n", FILE_APPEND | LOCK_EX);
            } else { // error
                $errors++;
                $climate->out(
                    "<red>${u1} length: <bold>${length}</bold> code: <bold>${code}</bold> assert: <bold>${x['assert_httpcode']}</bold> time: ${time} ms format: $jsformat JScode: $jscode</red>\007"
                );
                @file_put_contents(ROOT . "/ci/errors_${f1}.assert.txt",
                    "${u2};length:${length};code:${code};assert:${x['assert_httpcode']};time:${time};format:$jsformat;jscode:$jscode\n", FILE_APPEND | LOCK_EX);
            }
            $i++;
        }
        curl_multi_close($multi);

        $time = round((float) \Tracy\Debugger::timer("CITEST"), 2);
        $climate->out("\nTotal time: <bold><green>$time s</green></bold>");

        if ($errors) {
            $climate->out("\nErrors: <bold>" . $errors . "\007\n");
        }
        exit($errors);
    }
}
