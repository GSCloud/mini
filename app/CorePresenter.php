<?php
/**
 * GSC Tesseract
 * php version 8.2
 *
 * @category Framework
 * @package  Tesseract
 * @author   Fred Brooker <git@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://mini.gscloud.cz
 */

namespace GSC;

/**
 * Core Presenter
 * 
 * @category Framework
 * @package  Tesseract
 * @author   Fred Brooker <git@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://mini.gscloud.cz
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
        case "PingBack":
            $this->checkRateLimit();
            $x = file_get_contents("/proc/meminfo") ?? "";
            $meminfo = explode("\n", $x);
            $meminfo = array_map("trim", $meminfo);
            $meminfo = array_filter($meminfo, "strlen");
            foreach ($meminfo as $k => $v) {
                if (!strpos($v, ':')) {
                    continue;
                }
                $x = explode(':', $v);
                unset($meminfo[$k]);
                $meminfo[$x[0]] = trim($x[1]);
            }
            $data = [
                "system_load" => function_exists("sys_getloadavg")
                    ? \sys_getloadavg() : null,
                "memory_info" => $meminfo ?? null,
            ];
            return $this->writeJsonData($data, $extras);
                break;

        case "GetTXTSitemap":
            $this->setHeaderText();
            $map = [];
            foreach ($presenter as $p) {
                if (isset($p["sitemap"]) && $p["sitemap"]) {
                    $map[] = \trim($p["path"], "/ \t\n\r\0\x0B");
                }
            }
            return $this->setData(
                "output",
                $this->setData("sitemap", $map)->renderHTML("sitemap.txt")
            );
            break;

        case "GetXMLSitemap":
            $this->setHeaderXML();
            $map = [];
            foreach ($presenter as $p) {
                if (isset($p["sitemap"]) && $p["sitemap"]) {
                    $map[] = \trim($p["path"], "/ \t\n\r\0\x0B");
                }
            }
            return $this->setData(
                "output",
                $this->setData("sitemap", $map)->renderHTML("sitemap.xml")
            );
            break;

        case "GetRSSXML":
            $this->setHeaderXML();
            $language = "en";
            $l = $this->getLocale($language);
            if (class_exists("\\GSC\\RSSPresenter")) {
                $map = RSSPresenter::getInstance()->process() ?? [];
            } else {
                $map = [];
            }
            $this->setData("rss_channel_description", $l["meta_description"] ?? "");
            $this->setData("rss_channel_link", $l['$canonical_url'] ?? "");
            $this->setData("rss_channel_title", $l["title"] ?? "");
            return $this->setData(
                "output",
                $this->setData("rss_items", (array) $map)->renderHTML("rss.xml")
            );
            break;

        case "GetServiceWorker":
            $this->setHeaderJavaScript();
            $map = [];
            foreach ($presenter as $p) {
                if (isset($p["sitemap"]) && $p["sitemap"]) {
                    $map[] = \trim($p["path"], "/ \t\n\r\0\x0B");
                }
            }
            return $this->setData(
                "output",
                $this->setData("sitemap", $map)->renderHTML("sw.js")
            );
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
                        array(
                            '#href=&quot;(.*)&quot;#',
                            '#&lt;(/?(?:pre|a|b|br|u|ul|li|ol)(\shref=".*")?/?)&gt;#'
                        ),
                        array('href="\1"', '<\1>'),
                        $info
                    );
                    $map[] = [
                        "count" => \count($p["api_example"]),
                        "deprecated" => $p["deprecated"] ?? false,
                        "desc" => \htmlspecialchars($p["api_description"] ?? ""),
                        "exam" => $p["api_example"] ?? [],
                        "finished" => $p["finished"] ?? false,
                        "info" => $info ?
                            "<br><blockquote>{$info}</blockquote>" : "",
                        "key" => $p["use_key"] ?? false,
                        "linkit" => !(\strpos($p["path"], "[") ?? false),
                        "method" => \strtoupper($p["method"]),
                        "path" => \trim($p["path"], "/ \t\n\r\0\x0B"),
                        "private" => $p["private"] ?? false,
                    ];
                }
            }
            \usort(
                $map, function ($a, $b) {
                        return \strcmp($a["desc"], $b["desc"]);
                }
            );
            return $this->setData(
                "output",
                $this->setData(
                    "apis",
                    $map
                )->setData(
                    "l",
                    $this->getData("l")
                )->renderHTML("apis")
            );
            break;
        }
        return $this;
    }
}
