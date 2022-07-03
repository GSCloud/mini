<?php
/**
 * GSC Tesseract
 * php version 7.4.0
 *
 * @category Framework
 * @package  Tesseract
 * @author   Fred Brooker <git@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://mini.gscloud.cz
 */

namespace GSC;

use Cake\Cache\Cache;
use Michelf\MarkdownExtra;

/**
 * Mini Presenter
 * 
 * @category Framework
 * @package  Tesseract
 * @author   Fred Brooker <git@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://mini.gscloud.cz
 */

class MiniPresenter extends APresenter
{
    /**
     * Main controller
     * 
     * @return self
     */
    public function process()
    {
        // basic setup
        $data = $this->getData();
        $presenter = $this->getPresenter();
        $view = $this->getView();
        $this->checkRateLimit()->setHeaderHtml()->dataExpander($data);

        // process advanced caching
        $use_cache = (bool) (DEBUG ? false : $data["use_cache"] ?? false);
        $cache_key = hash(
            "sha256", join(
                "_", [$data["host"], $data["request_path"], "htmlpage"]
            )
        );
        if ($use_cache && $output = Cache::read($cache_key, "page")) {
            header("X-Cached: true");
            return $this->setData(
                "output",
                $output .= "\n<script>console.log('* page content cached');</script>"
            );
        }

        // HTML content
        if (file_exists($file = ROOT . "/README.md")) {
            $data["l"]["readme"] = MarkdownExtra::defaultTransform(
                @file_get_contents($file)
            );
        }

        // output
        $output = $this->setData($data)->renderHTML($presenter[$view]["template"]);
        StringFilters::trim_html_comment($output); // fix content
        Cache::write($cache_key, $output, "page"); // save cache
        header("X-Cached: false");
        return $this->setData("output", $output); // save model
    }
}
