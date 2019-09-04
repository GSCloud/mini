<?php
/**
 * GSC Tesseract
 *
 * @category Framework
 * @author   Fred Brooker <oscadal@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 */

namespace GSC;

use League\CLImate\CLImate;
use Tester\Assert;

/**
 * Unit Tester
 */
class UnitTester
{
    public function __construct()
    {
        $climate = new CLImate;
        $climate->out("<green><bold>Tesseract Unit Tester");
        Tester\Environment::setup();

        Assert::same('Hello John', "Hello John");
        Assert::same('Hi John', 'Yo John');
    }
}
