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

        }
        return $this;
    }
}
