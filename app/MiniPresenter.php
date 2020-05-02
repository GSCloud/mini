<?php
/**
 * GSC Tesseract
 *
 * @category Framework
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
        // rate limiter + set HTML headers
        $this->checkRateLimit()->setHeaderHtml();
        $data = $this->getData();
        $presenter = $this->getPresenter();
        $view = $this->getView();
        $this->dataExpander($data);

        // advanced caching
        $use_cache = (DEBUG === true) ? false : $data["use_cache"] ?? false;
        $cache_key = strtolower(join("_", [$data["host"], $data["request_path"]])) . "_htmlpage";
        if ($use_cache && $output = Cache::read($cache_key, "page")) {
            $output .= "\n<script>console.log('*** page content cached');</script>";
            return $this->setData("output", $output);
        }

        // create HTML content
        if (file_exists($file = ROOT . "/README.md")) {
            $data["l"]["readme"] = MarkdownExtra::defaultTransform(@file_get_contents($file));
        }

        // fix locales
        foreach ($data["l"] ??= [] as $k => $v) {
            StringFilters::correct_text_spacing($data["l"][$k], $data["lang"]);
        }

        // render output
        $output = $this->setData($data)->renderHTML($presenter[$view]["template"]);
        StringFilters::trim_html_comment($output); // fix content
        Cache::write($cache_key, $output, "page"); // save to cache
        return $this->setData("output", $output); // save model
    }
}
