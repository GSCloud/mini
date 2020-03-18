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
 * API Presenter
 */
class ApiPresenter extends APresenter
{

    /** @var string operations log */
    const OPLOG = "/operations.log";

    /**
     * Main controller
     */
    public function process()
    {
        setlocale(LC_ALL, 'cs_CZ.utf8');

        $cfg = $this->getCfg();
        $d = $this->getData();
        $match = $this->getMatch();
        $view = $this->getView();
    }

    /**
     * Get API call usage from operations log
     *
     * @param string $fn method name
     * @return mixed usage count
     */
    public static function getCallUsage($fn)
    {
        if (\is_null($fn)) {
            return null;
        }

        $fn = trim($fn);
        if (!\file_exists(DATA . self::OPLOG)) {
            return null;
        }

        $l = @\file(DATA . self::OPLOG);
        if (!$l) {
            return null;
        }

        $l = \array_filter($l, function ($value) use ($fn) {
            return \strpos($value, "fn:$fn");
        });
        return count($l) ? count($l) : "-";
    }

}
