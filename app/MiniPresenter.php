<?php
/**
 * GSC Tesseract MINI
 *
 * @category Framework
 * @author   Fred Brooker <oscadal@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://lasagna.gscloud.cz
 */

namespace GSC;

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
        $output = $this->setData($data)->renderHTML($presenter[$view]["template"]);
        return $this->setData($data, "output", $output);
    }
}
