<?php
/**
 * GSC Tesseract
 *
 * @category Framework
 * @author   Fred Brooker <git@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 */

namespace GSC;

/**
 * CLI Demo
 */
class CliDemo extends APresenter
{
    /**
     * Constructor
     */
    public function __construct() {
        echo "__contruct: foo bar\n";
    }

    /**
     * Processor
     */
    public function process()
    {
        echo "process: Hello World!\n";
    }
}
