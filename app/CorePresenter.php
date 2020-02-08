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

        $data = $this->getData();
        $presenter = $this->getPresenter();
        $view = $this->getView();

        // fix locales
        $data["l"] = $data["l"] ?? [];
        foreach ($data["l"] as $k => $v) {
            StringFilters::correct_text_spacing($data["l"][$k], $data["lang"]);
        }

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

            // api
            case "api":
                $this->setHeaderHTML();
                $map = [];
                foreach ($presenter as $p) {
                    if (isset($p["api"]) && $p["api"]) {
                        $info = $p["api_info"] ?? "";
                        StringFilters::convert_eol_to_br($info);
                        $map[] = [
                            "count" => count($p["api_example"]),
                            "desc" => $p["api_description"] ?? "",
                            "exam" => $p["api_example"] ?? [],
                            "finished" => $p["finished"] ?? false,
                            "deprecated" => $p["deprecated"] ?? false,
                            "info" => $info ? "<br><blockquote>${info}</blockquote>" : "",
                            "key" => $p["use_key"] ?? false,
                            "linkit" => !(\strpos($p["path"], "[") ?? false),
                            "method" => \strtoupper($p["method"]),
                            "path" => trim($p["path"], "/ \t\n\r\0\x0B"),
                            "private" => $p["private"] ?? false,
                        ];
                    }
                }

                /* // do not sort API list :)
                usort($map, function ($a, $b) {
                return strcmp($a["desc"], $b["desc"]);
                });
                 */
                $output = $this->setData($data)->setData("apis", $map)->renderHTML("apis");
                return $this->setData("output", $output);
                break;

        }
        return $this;
    }
}
