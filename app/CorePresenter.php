<?php
/**
 * GSC Tesseract
 *
 * @category Framework
 * @author   Fred Brooker <oscadal@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://mini.gscloud.cz
 */

namespace GSC;

/**
 * Core Presenter
 */
class CorePresenter extends APresenter
{
    /**
     * Process presenter
     *
     * @return object Singleton instance
     */
    public function process()
    {
        $data = $this->getData();
        $presenter = $this->getPresenter();
        $view = $this->getView();

        switch ($view) {

            // sitemap
            case "sitemap":
                $this->setHeaderText();
                $a = [];
                foreach ($presenter as $p) {
                    if (isset($p["sitemap"]) && $p["sitemap"]) {
                        $x = trim($p["path"], "/ \t\n\r\0\x0B");
                        $a[] = $x;
                    }
                }
                $data["sitemap"] = $a;
                $output = $this->setData($data)->renderHTML("sitemap.txt");
                return $this->setData($data, "output", $output);
                break;

            // sw.js
            case "swjs":
                $this->setHeaderJavaScript();
                $a = [];
                foreach ($presenter as $p) {
                    if (isset($p["sitemap"]) && $p["sitemap"]) {
                        $x = trim($p["path"], "/ \t\n\r\0\x0B");
                        $a[] = $x;
                    }
                }
                $data["sitemap"] = $a;
                $output = $this->setData($data)->renderHTML("sw.js");
                return $this->setData($data, "output", $output);
                break;

        }
        return $this;
    }
}
