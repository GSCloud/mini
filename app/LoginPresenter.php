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
 * Login Presenter class
 * 
 * @category CMS
 * @package  Framework
 * @author   Fred Brooker <git@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://lasagna.gscloud.cz
 */
class LoginPresenter extends APresenter
{
    /**
     * Controller processor
     *
     * @param mixed $param optional parameter
     * 
     * @return void
     */
    public function process($param = null)
    {
        if (\ob_get_level()) {
            @\ob_end_clean();
        }
        $this->checkRateLimit()->setHeaderHtml();

        // check OAuth parameters for validity
        $cfg = $this->getCfg();
        if (($cfg["goauth_client_id"] ?? null) === null) {
            $this->setLocation("/err/403");
        }
        if (($cfg["goauth_secret"] ?? null) === null) {
            $this->setLocation("/err/403");
        }

        // generate nonce
        $nonce = '/?nonce=' . $this->getNonce();
        
        // set return URI
        $uri = "/{$nonce}";
        $refhost = parse_url($_SERVER["HTTP_REFERER"] ?? "", PHP_URL_HOST);
        if ($refhost ?? null) {
            if (in_array($refhost, $this->getData("multisite_profiles.default"))) {
                $uri = $_SERVER["HTTP_REFERER"];
            }
        }
        \setcookie("return_uri", $uri, 0, "/", DOMAIN);

        try {
            $provider = new \League\OAuth2\Client\Provider\Google(
                [
                // OAuth 2.0 credentials
                "clientId" => $cfg["goauth_client_id"] ?? null,
                "clientSecret" => $cfg["goauth_secret"] ?? null,
                "redirectUri" => (LOCALHOST === true) ? $cfg["local_goauth_redirect"]
                    ?? null : $cfg["goauth_redirect"] ?? null,
                ]
            );
        } finally {
        }

        // check for errors
        $errors = [];
        if (!empty($_GET["error"])) {
            $errors[] = \htmlspecialchars($_GET["error"], ENT_QUOTES, "UTF-8");
        } elseif (empty($_GET["code"])) {
            $email = $_GET["login_hint"] ?? $_COOKIE["login_hint"] ?? null;
            $hint = $email ? \strtolower("&login_hint={$email}") : "";

            // check URL for relogin parameter
            if (isset($_GET["relogin"])) {
                $hint = "";
                $authUrl = $provider->getAuthorizationUrl(
                    [
                    "prompt" => "select_account consent",
                    "response_type" => "code",
                    ]
                );
            } else {
                $authUrl = $provider->getAuthorizationUrl(
                    ["response_type" => "code",]
                );
            }
            \setcookie("oauth2state", $provider->getState());
            \header("Location: " . $authUrl . $hint, true, 303);
            exit;
        } elseif (empty($_GET["state"])
            || ($_GET["state"] && !isset($_COOKIE["oauth2state"]))
        ) {
            $errors[] = "Invalid OAuth state";
        } else {
            try {
                // get access token
                $token = $provider->getAccessToken(
                    "authorization_code",
                    ["code" => $_GET["code"]]
                );
                // get owner details
                $ownerDetails = $provider->getResourceOwner(
                    $token, 
                    ["useOidcMode" => true,]
                );
                $this->setIdentity(
                    [
                        "avatar" => $ownerDetails->getAvatar(),
                        "email" => $ownerDetails->getEmail(),
                        "id" => $ownerDetails->getId(),
                        "name" => $ownerDetails->getName(),
                    ]
                );
                $this->addMessage(
                    "OAuth: "
                    . $ownerDetails->getName()
                    . " "
                    . $ownerDetails->getEmail()
                );

                if ($this->getUserGroup() == "admin") {
                    if ($this->getCfg("DEBUG_COOKIE")) {
                        // set Tracy debug cookie
                        \setcookie("tracy-debug", $this->getCfg("DEBUG_COOKIE"));
                    }
                }
                $this->clearCookie("oauth2state");
                if (\strlen($ownerDetails->getEmail())) {
                    // save email
                    \setcookie(
                        "login_hint",
                        $ownerDetails->getEmail() ?? "",
                        time() + 86400 * 31,
                        "/",
                        DOMAIN,
                    );
                }
                $this->clearCookie("oauth2state");
                $this->setLocation();
                exit;
            } catch (Exception $e) {
                $this->addError("Google OAuth: " . $e->getMessage());
            }
        }

        // display error
        $this->setLocation("/err/403");
        exit;
    }
}
