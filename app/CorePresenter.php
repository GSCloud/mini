<?php
/**
 * GSC Tesseract
 *
 * @category Framework
 * @author   Fred Brooker <oscadal@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 */

namespace GSC;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Core Presenter
 */
class CorePresenter extends APresenter
{
    /**
     * Main controller
     *
     * @return void
     */
    public function process()
    {
        if (isset($_GET["api"])) {
            $api = (string) $_GET["api"];
            $key = $this->getCfg("ci_tester.api_key") ?? null;
            if ($key !== $api) {
                $this->checkRateLimit();
            }
        } else {
            $this->checkRateLimit();
        }

        $data = $this->getData();
        $match = $this->getMatch();
        $presenter = $this->getPresenter();
        $view = $this->getView();
        $extras = [
            "name" => "LASAGNA Core",
            "fn" => $view,
        ];

        switch ($view) {

            case "GetWebManifest":
                $this->setHeaderJson();
                $lang = $_GET["lang"] ?? "cs"; // language switch by GET parameter
                if (!in_array($lang, ["cs", "en"])) {
                    $lang = "cs";
                }
                return $this->setData("output", $this->setData("l", $this->getLocale($lang))->renderHTML("manifest"));
                break;

            case "ReadEpubBook":
                if (isset($match["params"]["trailing"])) {
                    $epub = \trim($match["params"]["trailing"]);
                    // security tweaks
                    $epub = \str_replace("..", "", $epub);
                    $epub = \str_replace("\\", "", $epub);
                    $epub = \str_ireplace(".epub", "", $epub);
                }
                $file = WWW . "/${epub}.epub";
                if ($epub && \file_exists($file)) {
                    $this->setHeaderHTML();
                    $data["epub"] = "/${epub}.epub";
                    $output = $this->setData($data)->renderHTML($presenter[$view]["template"]);
                    return $this->setData("output", $output);
                }
                return $this->writeJsonData(400, $extras);
                break;

            case "GetTXTSitemap":
                $this->setHeaderText();
                $map = [];
                foreach ($presenter as $p) {
                    if (isset($p["sitemap"]) && $p["sitemap"]) {
                        $map[] = \trim($p["path"], "/ \t\n\r\0\x0B");
                    }
                }
                return $this->setData("output", $this->setData("sitemap", $map)->renderHTML("sitemap.txt"));
                break;

            case "GetXMLSitemap":
                $this->setHeaderXML();
                $map = [];
                foreach ($presenter as $p) {
                    if (isset($p["sitemap"]) && $p["sitemap"]) {
                        $map[] = \trim($p["path"], "/ \t\n\r\0\x0B");
                    }
                }
                return $this->setData("output", $this->setData("sitemap", $map)->renderHTML("sitemap.xml"));
                break;

            case "GetRSSXML":
                $this->setHeaderXML();
                $language = "en";
                $l = $this->getLocale($language);
                if (class_exists("\\GSC\\RSSPresenter")) {
                    $map = RSSPresenter::getInstance()->process() ?? []; // get items map from RSSPresenter
                } else {
                    $map = [];
                }
                $this->setData("rss_channel_description", $l["meta_description"] ?? "");
                $this->setData("rss_channel_link", $l['$canonical_url'] ?? "");
                $this->setData("rss_channel_title", $l["title"] ?? "");
                return $this->setData("output", $this->setData("rss_items", (array) $map)->renderHTML("rss.xml"));
                break;

            case "GetCsArticleHTMLExport":
            case "GetEnArticleHTMLExport":
                $language = \strtolower($presenter[$view]["language"]) ?? "cs";
                $x = 0;
                if (isset($match["params"]["profile"])) {
                    $profile = trim($match["params"]["profile"]);
                    $x++;
                }
                if (isset($match["params"]["trailing"])) {
                    $path = trim($match["params"]["trailing"]);
                    $x++;
                }
                if ($x !== 2) { // ERROR
                    return $this->writeJsonData(400, $extras);
                }
                $html = '';
                if ($path == "!") {
                    $path = $language;
                } else {
                    $path = $language . "/" . $path;
                }
                $hash = hash("sha256", $path);
                $file = DATA . "/summernote_${profile}_${hash}.json";
                if (\file_exists($file)) {
                    $html = \json_decode(@\file_get_contents(DATA . "/summernote_${profile}_${hash}.json"), true);
                    if (\is_array($html)) {
                        $html = \join("\n", $html);
                    } else {
                        $html = '';
                    }
                }
                return $this->setHeaderHTML()->setData("output", $this->renderHTML($html));
                break;

            case "GetQR":
                $x = 0;
                if (isset($match["params"]["size"])) {
                    $size = trim($match["params"]["size"]);
                    switch ($size) {
                        case "m":
                            $scale = 8;
                            break;
                        case "l":
                            $scale = 10;
                            break;
                        case "x":
                            $scale = 15;
                            break;
                        case "s":
                        default:
                            $scale = 5;
                    }
                    $x++;
                }
                if (isset($match["params"]["trailing"])) {
                    $text = trim($match["params"]["trailing"]);
                    $x++;
                }
                if ($x !== 2) { // ERROR
                    return $this->writeJsonData(400, $extras);
                }
                $options = new QROptions([
                    'version' => 7,
                    'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                    'eccLevel' => QRCode::ECC_L,
                    'scale' => $scale,
                    'imageBase64' => false,
                    'imageTransparent' => false,
                ]);
                header('Content-type: image/png');
                echo (new QRCode($options))->render($text ?? "", CACHE . "/" . hash("sha256", $text) . ".png");
                exit;
                break;

            case "GetServiceWorker":
                $this->setHeaderJavaScript();
                $map = [];
                foreach ($presenter as $p) {
                    if (isset($p["sitemap"]) && $p["sitemap"]) {
                        $map[] = \trim($p["path"], "/ \t\n\r\0\x0B");
                    }
                }
                return $this->setData("output", $this->setData("sitemap", $map)->renderHTML("sw.js"));
                break;

            case "API":
                $this->setHeaderHTML();
                $map = [];
                foreach ($presenter as $p) {
                    if (isset($p["api"]) && $p["api"]) {
                        $info = $p["api_info"] ?? "";
                        StringFilters::convert_eol_to_br($info);
                        $info = \htmlspecialchars($info);
                        $info = \preg_replace(
                            array('#href=&quot;(.*)&quot;#', '#&lt;(/?(?:pre|a|b|br|em|u|ul|li|ol)(\shref=".*")?/?)&gt;#'),
                            array('href="\1"', '<\1>'),
                            $info
                        );
                        $map[] = [
                            "count" => \count($p["api_example"]),
                            "deprecated" => $p["deprecated"] ?? false,
                            "desc" => \htmlspecialchars($p["api_description"] ?? ""),
                            "exam" => $p["api_example"] ?? [],
                            "finished" => $p["finished"] ?? false,
                            "info" => $info ? "<br><blockquote>${info}</blockquote>" : "",
                            "key" => $p["use_key"] ?? false,
                            "linkit" => !(\strpos($p["path"], "[") ?? false), // do not link path with parameters
                            "method" => \strtoupper($p["method"]),
                            "path" => \trim($p["path"], "/ \t\n\r\0\x0B"),
                            "private" => $p["private"] ?? false,
                        ];
                    }
                }
                \usort($map, function ($a, $b) {
                    return \strcmp($a["desc"], $b["desc"]);
                });
                return $this->setData("output", $this->setData("apis", $map)->setData("l", $this->getLocale("en"))->renderHTML("apis"));
                break;

            case "GetAndroidJs":
                $file = WWW . "/js/android-app.js";
                if (\file_exists($file)) {
                    $content = @\file_get_contents($file);
                    $time = \filemtime(WWW . "/js/android-app.js") ?? null;
                    $version = \hash("sha256", $content);
                } else {
                    $content = null;
                    $version = null;
                    $time = null;
                }
                return $this->writeJsonData([
                    "js" => $content,
                    "timestamp" => $time,
                    "version" => $version,
                ], $extras);
                break;

            case "GetAndroidCss":
                $file = WWW . "/css/android.css";
                if (\file_exists($file)) {
                    $content = @\file_get_contents($file);
                    $time = \filemtime(WWW . "/css/android.css") ?? null;
                    $version = \hash("sha256", $content);
                } else {
                    $content = null;
                    $version = null;
                    $time = null;
                }
                return $this->writeJsonData([
                    "css" => $content,
                    "timestamp" => $time,
                    "version" => $version,
                ], $extras);
                break;

            case "GetCoreVersion":
                $d = [];
                $d["LASAGNA"]["core"]["date"] = (string) $data["VERSION_DATE"];
                $d["LASAGNA"]["core"]["revisions"] = (int) $data["REVISIONS"];
                $d["LASAGNA"]["core"]["timestamp"] = (int) $data["VERSION_TIMESTAMP"];
                $d["LASAGNA"]["core"]["version"] = (string) $data["VERSION"];
                return $this->writeJsonData($d, $extras);
                break;

            case "ReadArticles":
                $x = 0;
                if (isset($match["params"]["profile"])) {
                    $profile = trim($match["params"]["profile"]);
                    $x++;
                }
                if (isset($match["params"]["hash"])) {
                    $hash = trim($match["params"]["hash"]);
                    $x++;
                }
                if ($x !== 2) { // ERROR
                    return $this->writeJsonData(400, $extras);
                }
                $data = "";
                $time = null;
                $file = DATA . "/summernote_${profile}_${hash}.json";
                if (\file_exists($file)) {
                    $data = @\file_get_contents($file);
                    $time = \filemtime(DATA . "/summernote_${profile}_${hash}.json");
                }
                $crc = hash("sha256", $data);
                if (isset($_GET["crc"])) {
                    if ($_GET["crc"] == $crc) { // not modified
                        return $this->writeJsonData(304, $extras);
                    }
                }
                return $this->writeJsonData([
                    "crc" => $crc,
                    "hash" => $hash,
                    "html" => $data,
                    "profile" => $profile,
                    "timestamp" => $time,
                ], $extras);
                break;
        }

        $language = \strtolower($presenter[$view]["language"]) ?? "cs";
        $locale = $this->getLocale($language);
        $hash = \hash('sha256', (string) \json_encode($locale));

        switch ($view) {
            case "GetCsDataVersion":
            case "GetEnDataVersion":
                $d = [];
                $d["LASAGNA"]["data"]["language"] = $language;
                $d["LASAGNA"]["data"]["version"] = $hash;
                return $this->writeJsonData($d, $extras);
                break;

            default:
                ErrorPresenter::getInstance()->process(404);
        }
        return $this;
    }
}
