<?php
/**
 * GSC Tesseract
 *
 * @category Framework
 * @author   Fred Brooker <oscadal@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 */

namespace GSC;

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
                $this->setHeaderText();
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
                $l = [];
                if (class_exists("\\GSC\\RSSPresenter")) {
                    $map = RSSPresenter::getInstance()->process(); // get items map from RSSPresenter
                } else {
                    $map = [];
                }
                $this->setData("rss_channel_description", $l["meta_description"] ?? "");
                $this->setData("rss_channel_link", $l['$canonical_url'] ?? "");
                $this->setData("rss_channel_title", $l["title"] ?? "");
                return $this->setData("output", $this->setData("rss_items", $map)->renderHTML("rss.xml"));
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
            default:
                ErrorPresenter::getInstance()->process(404);
        }
        return $this;
    }

}
