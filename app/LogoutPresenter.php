<?php
/**
 * GSC Tesseract
 * php version 8.2
 *
 * @author   Fred Brooker <git@gscloud.cz>
 * @category Framework
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://lasagna.gscloud.cz
 */

namespace GSC;

/**
 * Logout Presenter class
 * 
 * @package GSC
 */
class LogoutPresenter extends APresenter
{
    /**
     * Controller processor
     *
     * @return void
     */
    public function process()
    {
        if (\ob_get_level()) {
            @\ob_end_clean();
        }
        $this->logout();
        exit;
    }
}
