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
        $this->checkRateLimit()->setHeaderHtml(); // rate limiter + set HTML headers
        $data = $this->getData();
        $presenter = $this->getPresenter();
        $view = $this->getView();
        $this->dataExpander($data);

        $use_cache = (DEBUG === true) ? false : $data["use_cache"] ?? false;
        $cache_key = strtolower(join("_", [$data["host"], $data["request_path"]])) . "_htmlpage";
        if ($use_cache && $output = Cache::read($cache_key, "page")) { // advanced caching
            $output .= "\n<script>console.log('*** page content cached');</script>";
            return $this->setData("output", $output);
        }

        if (file_exists($file = ROOT . "/README.md")) { // create HTML content
            $data["l"]["readme"] = MarkdownExtra::defaultTransform(@file_get_contents($file));
        }

        foreach ($data["l"] ??= [] as $k => $v) { // fix locales
            StringFilters::correct_text_spacing($data["l"][$k], $data["lang"]);
        }

        $output = $this->setData($data)->renderHTML($presenter[$view]["template"]); // render output
        StringFilters::trim_html_comment($output); // fix content
        Cache::write($cache_key, $output, "page"); // save to cache
        return $this->setData("output", $output); // save model
    }
}
