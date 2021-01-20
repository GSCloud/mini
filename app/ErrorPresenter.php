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
 * Error Presenter
 */
class ErrorPresenter extends APresenter
{
    const CODESET = [
        400 => "Bad Request",
        401 => "Unauthorized",
        403 => "Forbidden",
        404 => "Not Found",
        406 => "Not Acceptable",
        410 => "Gone",
        420 => "Enhance Your Calm",
        429 => "Too Many Requests",
        500 => "Internal Server Error",
        502 => "Bad Gateway",
        503 => "Service Unavailable",
        504 => "Gateway Timeout",
    ];

    /**
     * Main controller
     *
     * @param int $error error code (optional)
     */
    public function process($err = null)
    {
        $this->setHeaderHtml();
        if (is_int($err)) {
            $code = $err;
        } else {
            $match = $this->getMatch();
            $params = (array) ($match["params"] ?? []);
            if (array_key_exists("code", $params)) {
                $code = (int) $params["code"];
            } else {
                $code = 404;
            }
        }
        if (!isset(self::CODESET[$code])) {
            $code = 404;
        }
        $error = self::CODESET[$code];
        header("HTTP/1.1 ${code} ${error}");
        $template = '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="x-ua-compatible" content="IE=edge"><body><center><h1>'
            . "<br>🤔 WebApp Error &ndash; #$code 💣</h1><h2>"
            . self::CODESET[$code]
            . '<br></h2><h1><a rel=nofollow style="color:red;text-decoration:none" href="/">RELOAD ↻</a></h1>'
            . '<img alt="dead robot" src=/img/error.png></body></html>';
        return $this->setData("output", $this->renderHTML($template));
    }
}
