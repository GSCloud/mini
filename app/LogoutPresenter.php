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
 * Logout Presenter
 */
class LogoutPresenter extends APresenter
{

    /**
     * Main controller
     *
     */
    public function process()
    {
        if (ob_get_level()) {
            ob_end_clean();
        }
        $this->logout();
        exit;
    }

}
