<?php
/**
 * GSC Tesseract
 *
 * @author   Fred Brooker <git@gscloud.cz>
 * @category Framework
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://app.gscloud.cz
 */

namespace GSC;

/**
 * CLI Demo class
 *
 * @package GSC
 */
class CliDemo extends APresenter
{
    /**
     * Controller constructor
     */
    public function __construct()
    {
    }

    /**
     * Controller processor
     * 
     * @param mixed $param optional parameter
     *
     * @return self
     */
    public function process($param = null)
    {
        echo "process: Hello World!\n";
        return $this;
    }
}
