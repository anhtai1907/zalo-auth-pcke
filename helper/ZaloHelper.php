<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Zalo\Zalo;

class ZaloHelperException extends \Exception {}

class ZaloHelper {
    /**
     * @var string The Zalo App ID.
     */
    private $appID = null;

    /**
     * @var string The Zalo App Secret.
     */
    private $appSecret = null;

    /**
     * @var string The callback URL for Zalo authentication
     */
    private $callbackURL = null;

    /**
     * @var Zalo The Zalo API client.
     */
    private $zalo;

    /**
     * Instantiates a new Zalo helper object.
     */
    public function __construct() {
        $env = parse_ini_file('.env');
        $this->appID     = $env['ZALO_APP_ID'] ?? null;
        $this->appSecret = $env['ZALO_APP_SECRET'] ?? null;

        $config = [
            'app_id'     => $this->appID,
            'app_secret' => $this->appSecret
        ];
        $this->zalo = new Zalo($config);
    }

    /**
     * Sets the callback URL for Zalo authentication.
     * 
     * @param string $url The callback URL to set.
     */
    public function setCallbackURL($url) {
        if (parse_url($url, PHP_URL_HOST) !== false) {
            $this->callbackURL = $url;
        }
    }

    /**
     * Gets the URL of the current page we are on, encodes, and returns it
     * 
     * @return string
     */
    public function getCallbackURL() {
        // If the callback URL has been set then return it.
        if (property_exists($this, 'callbackURL') && $this->callbackURL) {
            return $this->callbackURL;
        }

        // Other-wise return the URL of the current page
        if (isset($_SERVER["HTTP_UPGRADE_INSECURE_REQUESTS"]) && ($_SERVER['HTTP_UPGRADE_INSECURE_REQUESTS'] == 1)) {
            $protocol = 'https';
        } else {
            $protocol = @$_SERVER['HTTP_X_FORWARDED_PROTO']
                ?: @$_SERVER['REQUEST_SCHEME']
                ?: ((isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") ? "https" : "http");
        }

        $port = @intval($_SERVER['HTTP_X_FORWARDED_PORT'])
              ?: @intval($_SERVER["SERVER_PORT"])
              ?: (($protocol === 'https') ? 443 : 80);

        $host = @explode(":", $_SERVER['HTTP_HOST'])[0]
              ?: @$_SERVER['SERVER_NAME']
              ?: @$_SERVER['SERVER_ADDR'];

        $port = (443 == $port) || (80 == $port) ? '' : ':' . $port;

        return sprintf('%s://%s/%s', $protocol, $host, @trim(reset(explode("?", $_SERVER['REQUEST_URI'])), '/'));
    }


    public function authenticate() {
        // If we have an authorization code then proceed to request a token
        if (isset($_REQUEST["code"])) {
            $helper       = $this->zalo->getRedirectLoginHelper();
            $codeVerifier = $this->getCodeVerifier();
            $zaloToken    = $helper->getZaloTokenByOA($codeVerifier); // The function call can throw

            // Do an Zalo Connection session check
            if ($_REQUEST["state"] != $this->getState()) {
                throw new ZaloHelperException("Unable to determine state");
            }

            // If there are any errors from ZaloSDK, it means the request for Zalo OA authorization is successful.
            // So we cleanup state and code verifier session
            $this->unsetState();
            $this->unsetCodeVerifier();

            return $zaloToken;
        } else {
            $this->requestAuthorization();
            return false;
        }
    }

    /**
     * Requests user authorization from the Zalo.
     *
     * This function sends a request for user authorization to the Zalo. It generates a code challenge
     * and state value, and stores theme in the session.
     *  
     * The code challenge is an arbitrary value used for the Proof Key for Code Exchange (PKCE) technique.
     * The state essentially acts as a session key and is also an arbitrary value.
     *
     * After generating the login URL, this function commits the session and redirects the user to the Zalo login page.
     *
     * @return void
     */
    private function requestAuthorization() {
        $callbackURL   = $this->getCallbackURL();
        $codeChallenge = $this->generateCodeChallenge();
        $state         = $this->setState($this->generateRandString());

        $helper   = $this->zalo->getRedirectLoginHelper();
        $loginURL = $helper->getLoginUrlByOA($callbackURL, $codeChallenge, $state);

        // Commit the session to store the code challenge and state.
        $this->commitSession();

        // Redirect the user to the Zalo login page.
        $this->redirect($loginURL);
    }

    /**
     * Redirects user to a specified URL
     */
    public function redirect($url) {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Used for arbitrary value generation for nonces and state
     *
     * @return string
     */
    protected function generateRandString() {
        return md5(uniqid(rand(), TRUE));
    }

    /**
     * Generates the code challenge using the Proof Key for Code Exchange (PKCE) technique.
     * 
     * @return string The generated code challenge.
     */
    public function generateCodeChallenge() {
        // Generate a random code verifier
        $codeVerifier = $this->generateCodeVerifier();

        // Hash the code verifier using SHA256 hashing algorithm
        $hashedVerifier = hash('sha256', $codeVerifier);

        // Generate the code challenge using SHA256 hashing algorithm
        $codeChallenge = $this->base64UrlEncode(pack('H*', $hashedVerifier));

        return $codeChallenge;
    }

    /**
     * Generates a code verifier for the Proof Key for Code Exchange (PKCE) technique.
     *
     * @return string The generated code verifier.
     */
    protected function generateCodeVerifier() {
        $random       = bin2hex(openssl_random_pseudo_bytes(32));
        $codeVerifier = $this->base64UrlEncode(pack('H*', $random));
        return $this->setCodeVerifier($codeVerifier);
    }

    /**
     * Stores $codeVerifier
     *
     * @param string $codeVerifier
     * @return string
     */
    protected function setCodeVerifier($codeVerifier) {
        $this->setSessionKey('zalo_connect_code_verifier', $codeVerifier);
        return $codeVerifier;
    }

    /**
     * Get stored code verifier
     *
     * @return string
     */
    protected function getCodeVerifier() {
        return $this->getSessionKey('zalo_connect_code_verifier');
    }

    /**
     * Cleanup code verifier
     *
     * @return void
     */
    protected function unsetCodeVerifier() {
        $this->unsetSessionKey('zalo_connect_code_verifier');
    }

    /**
     * Stores $state
     *
     * @param string $state
     * @return string
     */
    protected function setState($state) {
        $this->setSessionKey('zalo_connect_state', $state);
        return $state;
    }

    /**
     * Get stored state
     *
     * @return string
     */
    protected function getState() {
        return $this->getSessionKey('zalo_connect_state');
    }

    /**
     * Cleanup state
     *
     * @return void
     */
    protected function unsetState() {
        $this->unsetSessionKey('zalo_connect_state');
    }

    /**
     * Starts a session if session is not already started.
     */
    protected function startSession() {
        if (!isset($_SESSION)) {
            @session_start();
        }
    }

    /**
     * Commits the session.
     */
    protected function commitSession() {
        $this->startSession();
        session_commit();
    }

    /**
     * Retrieves a value from the session using the specified key.
     *
     * @param string $key The session key.
     * @return mixed The value stored in the session.
     */
    protected function getSessionKey($key) {
        $this->startSession();
        return $_SESSION[$key];
    }

    /**
     * Sets a value in the session using the specified key.
     *
     * @param string $key The session key.
     * @param mixed $value The value to store in the session.
     */
    protected function setSessionKey($key, $value) {
        $this->startSession();
        $_SESSION[$key] = $value;
    }

    /**
     * Unsets a value from the session using the specified key.
     *
     * @param string $key The session key.
     */
    protected function unsetSessionKey($key) {
        $this->startSession();
        unset($_SESSION[$key]);
    }

    /**
     * Encodes a string using base64 URL encoding.
     *
     * @param string $data The string to encode.
     * @return string The encoded string.
     */
    protected function base64UrlEncode($data) {
        $base64    = base64_encode($data);
        $base64Url = strtr($base64, '+/', '-_');
        return rtrim($base64Url, '=');
    }
}