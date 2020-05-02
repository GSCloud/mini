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
        if (ob_get_level()) {
            ob_end_clean();
        }
        $this->checkRateLimit()->setHeaderHtml();

        $cfg = $this->getCfg();

        // set return URI
        $refhost = parse_url($_SERVER["HTTP_REFERER"] ?? "", PHP_URL_HOST);
        $uri = "/";
        if ($refhost) {
            if (in_array($refhost, $this->getData("multisite_profiles.default"))) {
                $uri = $_SERVER["HTTP_REFERER"];
            }
        }
        \setcookie("return_uri", $uri);

        try {
            $provider = new \League\OAuth2\Client\Provider\Google([
                // set OAuth 2.0 credentials
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
            if (isset($_GET["relogin"])) {
                $hint = "";
                $authUrl = $provider->getAuthorizationUrl([
                    "prompt" => "select_account consent",
                    "response_type" => "code",
                ]);
            } else {
                $authUrl = $provider->getAuthorizationUrl([
                    "response_type" => "code",
                ]);
            }
            \setcookie("oauth2state", $provider->getState());
            header("Location: " . $authUrl . $hint, true, 303);
            exit;
        } elseif (empty($_GET["state"]) || ($_GET["state"] && !isset($_COOKIE["oauth2state"]))) {
            // something baaaaaaaaaaaaaad happened!
            $errors[] = "Invalid OAuth state";
        } else {
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
                dump("OAuth IDENTITY:");
                dump($ownerDetails);
                exit;
                 */

                if ($this->getUserGroup() == "admin") {
                    // set Tracy debug cookie
                    if ($this->getCfg("DEBUG_COOKIE")) {
                        \setcookie("tracy-debug", $this->getCfg("DEBUG_COOKIE"));
                    }
                }
                $this->clearCookie("oauth2state");
                // store email for next run
                if (strlen($ownerDetails->getEmail())) {
                    \setcookie("login_hint", $ownerDetails->getEmail() ?? "", time() + 86400 * 31, "/", DOMAIN);
                }

                // set correct URL location
                $nonce = "nonce=" . substr(hash("sha256", random_bytes(8) . (string) time()), 0, 8);
                if (isset($_COOKIE["return_uri"])) {
                    $c = $_COOKIE["return_uri"];
                    $this->clearCookie("return_uri");
                    $this->clearCookie("oauth2state");
                    $this->setLocation("${c}&${nonce}");
                } else {
                    $this->setLocation("/?${nonce}");
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
        // process errors
        header("HTTP/1.1 400 Bad Request");
        $this->addError("HTTP/1.1 400 Bad Request");
        $this->clearCookie("login_hint");
        $this->clearCookie("oauth2state");
        $this->clearcookie("return_uri");
        $nonce = "nonce=" . substr(hash("sha256", random_bytes(8) . (string) time()), 0, 8);
        echo "<html><body><center><h1>💀 AUTHENTICATION ERROR</h1>";
        echo '<h2><a href="/login?relogin&' . $nonce . '">RELOAD ↻</a></h2>';
        //echo join("<br>", $errors);
        exit;
    }
}
