<?php
/**
 * GSC Tesseract
 *
 * @category Framework
 * @author   Fred Brooker <oscadal@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://lasagna.gscloud.cz
 */

namespace GSC;

use Michelf\MarkdownExtra;

/**
 * Mini presenter
 */
class MiniPresenter extends APresenter
{
    /**
     * Process presenter
     *
     * @return object Singleton instance
     */
    public function process()
    {
        $this->checkRateLimit();
        $data = $this->getData();
        $presenter = $this->getPresenter();
        $view = $this->getView();
        $data["user"] = $this->getCurrentUser();
        $data["user_group"] = $this->getUserGroup();
        $readme = "";
        if (file_exists(ROOT."/README.md")) {
            $readme = @file_get_contents(ROOT."/README.md");
        }
        $data["l"]["readme"] = MarkdownExtra::defaultTransform($readme);
        $output = $this->setData($data)->renderHTML($presenter[$view]["template"]);
        return $this->setData($data, "output", $output);
    }
}
