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
    /**
     * Main controller
     *
     * @return object Singleton instance
     */
    public function process()
    {
        $match = $this->getMatch();

        switch ($match["params"]["call"] ?? null) {

            // API call 1
            case "GetCall1":
                return $this->writeJsonData(["result" => 12345], ["name" => "API call", "fn" => "GetCall1"]);
                break;

            // API call 2
            case "GetCall2":
                $trailing = $match["params"]["trailing"] ?? null;
                return $this->writeJsonData(["result" => 6789, "parameter" => $trailing], ["name" => "API call", "fn" => "GetCall2"]);
                break;

            default:
                $this->setLocation("/err/400");
                exit;
        }

        return $this;
    }

}
