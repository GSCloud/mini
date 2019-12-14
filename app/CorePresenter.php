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
     * @return object Singleton instance
     */
    public function process()
    {
        $this->checkRateLimit();

        $presenter = $this->getPresenter();
        $view = $this->getView();

        switch ($view) {

            // sitemap
            case "sitemap":
                $this->setHeaderText();
                $map = [];
                foreach ($presenter as $p) {
                    if (isset($p["sitemap"]) && $p["sitemap"]) {
                        $map[] = trim($p["path"], "/ \t\n\r\0\x0B");
                    }
                }
                $output = $this->setData("sitemap", $map)->renderHTML("sitemap.txt");
                return $this->setData("output", $output);
                break;

            // sw.js
            case "swjs":
                $this->setHeaderJavaScript();
                $map = [];
                foreach ($presenter as $p) {
                    if (isset($p["sitemap"]) && $p["sitemap"]) {
                        $map[] = trim($p["path"], "/ \t\n\r\0\x0B");
                    }
                }
                $output = $this->setData("sitemap", $map)->renderHTML("sw.js");
                return $this->setData("output", $output);
                break;

            case "api":
                $this->setHeaderHTML();
                $map = [];
                foreach ($presenter as $p) {
                    if (isset($p["api"]) && $p["api"]) {
                        $info = $p["api_info"] ?? "";
                        StringFilters::convert_eol_to_br($info);
                        $map[] = [
                            "path" => trim($p["path"], "/ \t\n\r\0\x0B"),
                            "desc" => $p["api_description"] ?? "",
                            "exam" => $p["api_example"] ?? [],
                            "info" => $info ? "<br><blockquote>${info}</blockquote>" : "",
                            "count" => count($p["api_example"]),
                            "method" => \strtoupper($p["method"]),
                        ];
                    }
                }
                usort($map, function ($a, $b) {
                    return strcmp($a["desc"], $b["desc"]);
                });
                return $this->setData("output", $this->setData("apis", $map)->setData("l", $this->getLocale("en"))->renderHTML("apis"));
                break;

        }
        return $this;
    }
}
