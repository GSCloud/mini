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
 * RSS Presenter
 */
class RSSPresenter extends APresenter
{
    /**
     * Main controller
     *
     * @return array data
     */
    public function process()
    {
        $items = [
            [
                "title" => "foo #1",
                "link" => "bar #1",
                "description" => "foo bar #1",
            ],
            [
                "title" => "foo #2",
                "link" => "bar #2",
                "description" => "foo bar #2",
            ],
            [
                "title" => "foo #3",
                "link" => "bar #3",
                "description" => "foo bar #3",
            ],
        ];
        return $items;
    }
}
