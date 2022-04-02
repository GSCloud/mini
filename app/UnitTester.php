<?php
/**
 * GSC Tesseract
 *
 * @author   Fred Brooker <git@gscloud.cz>
 * @category Framework
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://lasagna.gscloud.cz
 */

namespace GSC;

use League\CLImate\CLImate;
use Tester\Assert;

/**
 * Unit Tester CLI class
 *
 * @package GSC
 */
class UnitTester
{
    /**
     * Unit Test constructor
     *
     * @return void
     */
    public function __construct()
    {
        \Tracy\Debugger::timer("UNIT");
        \Tester\Environment::setup();

        $climate = new CLImate;
        $climate->out("<green><bold>Tesseract Unit Tester");

        // all testable controllers
        $controllers = [
            'ApiPresenter',
            'CliPresenter',
            'CliDemo',
            'CliVersion',
            'CorePresenter',
            'ErrorPresenter',
            'MiniPresenter',
            'LoginPresenter',
            'LogoutPresenter',
        ];

        // check controllers
        foreach ($controllers as $c) {
            $controller = "\\GSC\\${c}";
            $app = $controller::getInstance();

            // instance of APresenter
            Assert::type('\\GSC\\APresenter', $app);

            // getData(), setData(), getCfg()
            Assert::same(null, $app->getData('just.null.testing'));
            Assert::type('array', $app->getData());
            Assert::truthy(count($app->getData()));
            Assert::same($app->getData('cfg'), $app->getCfg());

            $app->setData("animal.farm", ["dog", "cat", "bird"]);
            Assert::same(["farm" => ["dog", "cat", "bird"]], $app->getData("animal"));

            // getCfg()

            // magic __toString()
            Assert::type('string', $app->__toString());
            Assert::truthy(strlen($app->__toString()));

            // getIP()
            Assert::same('127.0.0.1', $app->getIP());

            // getIdentity()
            Assert::same([
                'country' => 'XX',
                'email' => 'john.doe@example.com',
                'id' => 1,
                'ip' => '127.0.0.1',
                'name' => 'John Doe',
            ], $app->getIdentity());

            // getCurrentUser()
            Assert::same([
                'avatar' => '',
                'country' => 'XX',
                'email' => 'john.doe@example.com',
                'id' => 1,
                'name' => 'John Doe',
                'ip' => '127.0.0.1',
                'uid' => '5d93b9f0de6d0b30934db74b6d877154d704f562ad5bb88002409d51db5345c1',
                'uidstring' => 'CLI__127.0.0.1',
            ], $app->getCurrentUser());

            // fluent interface
            Assert::same($app, $app->addCritical());
            Assert::same($app, $app->addCritical(42));
            Assert::same($app, $app->addCritical(true));
            Assert::same($app, $app->addCritical(false));
            Assert::same($app, $app->addCritical(null));
            Assert::same($app, $app->addCritical([]));
            Assert::same([], $app->getCriticals());
            Assert::same($app, $app->addCritical('test message'));
            Assert::same(['test message'], $app->getCriticals());

            // fluent interface
            Assert::same($app, $app->addError());
            Assert::same($app, $app->addError(42));
            Assert::same($app, $app->addError(true));
            Assert::same($app, $app->addError(false));
            Assert::same($app, $app->addError(null));
            Assert::same($app, $app->addError([]));
            Assert::same([], $app->getErrors());
            Assert::same($app, $app->addError('test message'));
            Assert::same(['test message'], $app->getErrors());

            // fluent interface
            Assert::same($app, $app->addMessage());
            Assert::same($app, $app->addMessage(42));
            Assert::same($app, $app->addMessage(true));
            Assert::same($app, $app->addMessage(false));
            Assert::same($app, $app->addMessage(null));
            Assert::same($app, $app->addMessage([]));
            Assert::same([], $app->getMessages());
            Assert::same($app, $app->addMessage('test message'));
            Assert::same(['test message'], $app->getMessages());

            // fluent interface
            Assert::same($app, $app->addAuditMessage());
            Assert::same($app, $app->addAuditMessage(42));
            Assert::same($app, $app->addAuditMessage(true));
            Assert::same($app, $app->addAuditMessage(false));
            Assert::same($app, $app->addAuditMessage(null));
            Assert::same($app, $app->addAuditMessage([]));
            Assert::same($app, $app->addAuditMessage('test message'));

            // null
            Assert::same(null, $app->getRateLimit());
            Assert::same(null, $app->getUserGroup());
            Assert::same(null, $app->getView());

            // getUID()
            Assert::same('5d93b9f0de6d0b30934db74b6d877154d704f562ad5bb88002409d51db5345c1', $app->getUID());

            // getUIDstring()
            Assert::same('CLI__127.0.0.1', $app->getUIDstring());

            // fluent interface
            Assert::same($app, $app->checkLocales());
            Assert::same($app, $app->checkPermission());
            Assert::same($app, $app->checkRateLimit());

            // renderHTML()
            Assert::same("<title></title>", $app->renderHTML("<title>{{notitle}}</title>"));
            Assert::same("<title>foo bar</title>", $app->setData("title", "foo bar")->renderHTML("<title>{{title}}</title>"));
            Assert::same("<b>dog</b>", $app->renderHTML("<b>{{animal.farm.0}}</b>"));
            Assert::same("<b>cat</b>", $app->renderHTML("<b>{{animal.farm.1}}</b>"));
            Assert::same("dogcatbird", $app->renderHTML('{{#animal.farm}}{{.}}{{/animal.farm}}'));
        }
        echo "Unit testing finished in: " . round((float) \Tracy\Debugger::timer("UNIT") * 1000, 2) . " ms";
        exit(0);
    }
}
