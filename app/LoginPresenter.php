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
 * Login Presenter
 */
class LoginPresenter extends APresenter
{
    /**
     * Main controller
     *
     * @return void
     */
    public function process()
    {
        $this->checkRateLimit()->setHeaderHtml();

        $cfg = $this->getCfg();

        // store return URI if exists
        if (isset($_GET["return_uri"])) {
            \setcookie("return_uri", $_GET["return_uri"]);
        }

        // set OAuth 2.0 credentials
        try {
            $provider = new \League\OAuth2\Client\Provider\Google([
                "clientId" => $cfg["goauth_client_id"],
                "clientSecret" => $cfg["goauth_secret"],
                "redirectUri" => (LOCALHOST === true) ? $cfg["local_goauth_redirect"] : $cfg["goauth_redirect"],
            ]);
        } finally {}

        // check for errors
        $errors = [];
        if (!empty($_GET["error"])) {
            $errors[] = htmlspecialchars($_GET["error"], ENT_QUOTES, "UTF-8");
        } elseif (empty($_GET["code"])) {
            $email = $_GET["login_hint"] ?? $_COOKIE["login_hint"] ?? null;
            $hint = $email ? strtolower("&login_hint=${email}") : "";

            // check URL for relogin parameter
            if (isset($_GET["relogin"]) && $_GET["relogin"] === true) {
                $authUrl = $provider->getAuthorizationUrl([
                    "prompt" => "select_account consent",
                    "response_type" => "code",
                ]);
            } else {
                $authUrl = $provider->getAuthorizationUrl([
                    "response_type" => "code",
                ]);
            }
            if (ob_get_level()) {
                ob_end_clean();
            }

            // save OAuth state into cookie
            \setcookie("oauth2state", $provider->getState());

            // OAuth processing
            header("Location: " . $authUrl . $hint, true, 303);
            exit;
        } elseif (empty($_GET["state"]) || ($_GET["state"] && !isset($_COOKIE["oauth2state"]))) {

            // something bad happened!!!
            $errors[] = "Invalid OAuth state";
        } else {

            // debugging
/*
            dump("OLD IDENTITY:");
            dump($this->getIdentity());
*/

            // get access token
            try {
                $token = $provider->getAccessToken("authorization_code", [
                    "code" => $_GET["code"],
                ]);
                $ownerDetails = $provider->getResourceOwner($token, [
                    "useOidcMode" => true,
                ]);
                $this->setIdentity([
                    "avatar" => $ownerDetails->getAvatar(),
                    "email" => $ownerDetails->getEmail(),
                    "id" => $ownerDetails->getId(),
                    "name" => $ownerDetails->getName(),
                ]);

                // debugging
/*
                dump("NEW IDENTITY:");
                dump($this->getIdentity());
                dump("OAuth details");
                dump($ownerDetails);
*/
                if ($this->getUserGroup() == "admin") {
                    // set Tracy debug cookie
                    if ($this->getCfg("DEBUG_COOKIE")) {
                        \setcookie("tracy-debug", $this->getCfg("DEBUG_COOKIE"));
                    }
                }

                // remove state cookie
                \setcookie("oauth2state", "", 0);
                unset($_COOKIE["oauth2state"]);

                // store email for next run
                if (strlen($ownerDetails->getEmail())) {
                    \setcookie("login_hint", $ownerDetails->getEmail() ?? "", time() + 86400 * 31, "/", DOMAIN);
                }

                // set correct URL location
                if (isset($_COOKIE["return_uri"])) {
                    $c = $_COOKIE["return_uri"];
                    \setcookie("return_uri", "", 0);
                    unset($_COOKIE["return_uri"]);
                    $this->setLocation($c);
                } else {
                    $time = "?nonce=" . substr(hash("sha256", random_bytes(10) . time()), 0, 8);
                    $this->setLocation("/${time}");
                }
                exit;
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        // process errors
        if (ob_get_level()) {
            ob_end_clean();
        }
        $this->addError("HTTP/1.1 400 Bad Request");
        $time = "?nonce=" . substr(hash("sha256", random_bytes(10) . time()), 0, 8);
        header("HTTP/1.1 400 Bad Request");
        echo "<html><body><center><h1>😐 AUTHENTICATION ERROR 😐</h1>";
        echo join("<br>", $errors);
        echo "<h3><a href=/login?nonce=${time}>RELOAD ↻</a></h3>";
        exit;
    }
}
