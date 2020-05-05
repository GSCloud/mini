<?php
/**
 * GSC Tesseract
 *
 * @author   Fred Brooker <oscadal@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 */

namespace GSC;

use Cake\Cache\Cache;
use Michelf\MarkdownExtra;

/**
 * Mini Presenter
 */
class MiniPresenter extends APresenter
{
    /**
     * Main controller
     *
     * @return object Singleton instance
     */
    public function process()
    {
        // basic setup
        $data = $this->getData();
        $presenter = $this->getPresenter();
        $view = $this->getView();
        $this->checkRateLimit()->setHeaderHtml()->dataExpander($data); // data = Model

        // advanced caching
        $use_cache = (DEBUG === true) ? false : $data["use_cache"] ?? false;
        $cache_key = strtolower(join("_", [$data["host"], $data["request_path"]])) . "_htmlpage";
        if ($use_cache && $output = Cache::read($cache_key, "page")) {
            return $this->setData("output", $output .= "\n<script>console.log('*** page content cached');</script>");
        }

        // HTML content
        if (file_exists($file = ROOT . "/README.md")) {
            $data["l"]["readme"] = MarkdownExtra::defaultTransform(@file_get_contents($file));
        }

        foreach ($data["l"] ??= [] as $k => $v) { // fix locale text
            StringFilters::correct_text_spacing($data["l"][$k], $data["lang"] ?? "en");
        }

        // output
        $output = $this->setData($data)->renderHTML($presenter[$view]["template"]); // render
        StringFilters::trim_html_comment($output); // fix content
        Cache::write($cache_key, $output, "page"); // save cache
        return $this->setData("output", $output); // save model
    }
}
