<?php
/**
 * GSC Tesseract
 * php version 8.2
 *
 * @category CMS
 * @package  Framework
 * @author   Fred Brooker <git@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://lasagna.gscloud.cz
 */

namespace GSC;

/**
 * CLI Demo class
 *
 * @category CMS
 * @package  Framework
 * @author   Fred Brooker <git@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://lasagna.gscloud.cz
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
