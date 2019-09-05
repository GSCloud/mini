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
        $this->checkRateLimit();    // check for abuse

        $data = $this->getData();
        $presenter = $this->getPresenter();
        $view = $this->getView();

        // expand data model
        $this->dataExpander($data);

        // advanced caching
        $use_cache = $data["use_cache"] ?? false;   // set in dataExpander
        $cache_key = strtolower(join([
            $data["host"],
            $data["request_path"],
        ], "_"));
        if ($use_cache && $output = Cache::read($cache_key, "page")) {
            $output .= "\n<script>console.log('(page content cached)');</script>";  // console notification
            return $this->setData("output", $output);
        }

        // generate content
        if (file_exists(ROOT."/README.md")) {
            $readme = @file_get_contents(ROOT."/README.md");
            $data["l"]["readme"] = MarkdownExtra::defaultTransform($readme);    // render, add to model
        }

        // render output & save to model & cache
        $output = $this->setData($data)->renderHTML($presenter[$view]["template"]); // render
        $output = StringFilters::trim_html_comment($output);    // remove comment blocks
        Cache::write($cache_key, $output, "page");  // cache page
        return $this->setData("output", $output);
    }
}
