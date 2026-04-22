================================================================================
                        REDIRECTION SCRIPT - README
================================================================================

OVERVIEW
--------
This is a sophisticated PHP-based redirection system with advanced bot detection,
CAPTCHA verification, IP geolocation, and comprehensive logging capabilities.
The system provides secure URL redirection with multiple security layers and
customizable bot handling mechanisms.

FEATURES
--------
• Advanced bot detection and blocking
• IP-based geolocation and validation
• CAPTCHA verification (Cloudflare Turnstile)
• Comprehensive request logging
• Wildcard subdomain support
• Multiple redirection methods
• Email extraction from URLs with Base64 decoding
• Drive-by page functionality
• Customizable error handling
• IP whitelist/blacklist management
• Request and email filtering
• Fragment-based email separation with multiple delimiters

REQUIREMENTS
------------
• PHP 5.6+ (compatible with modern PHP versions)
• Web server (Apache/Nginx)
• SQLite support (for IP caching)
• mod_rewrite (Apache) or equivalent URL rewriting

INSTALLATION
------------
1. Upload all files to your web server directory
2. Ensure proper file permissions (755 for directories, 644 for files)
3. Configure your web server:
   - Apache: .htaccess file is included
   - Nginx: Use the provided nginx.conf configuration
4. Edit CONFIG.php to customize your settings
5. Test the installation by accessing your domain

CONFIGURATION
=============

All configuration is done through CONFIG.php. Key settings include:

CORE FUNCTIONALITY
------------------
enableIpApiCheck      : Enable IP geolocation checks (true/false)
logRequests          : Log all incoming requests (true/false)
logVisitors          : Track visitor information (true/false)
blockBots            : Enable bot detection and blocking (true/false)

SECURITY SETTINGS
-----------------
useCaptcha           : Enable CAPTCHA verification (true/false)
useAntiBot           : Enable advanced bot detection (true/false)
logRequestType       : Log request types when anti-bot disabled (true/false)
alwaysSolveCaptcha   : Force CAPTCHA on every visit (true/false)

BOT HANDLING
------------
botRedirectMethod    : How to handle detected bots
                      Options: 'random', 'error403', 'error404', 'error500', 'mediaUrl'

ERROR HANDLING
--------------
errorPattern         : Configure custom error redirects
  useLink           : Use custom URLs instead of error pages (true/false)
  404               : Custom 404 redirect URL
  403               : Custom 403 redirect URL  
  500               : Custom 500 redirect URL

REDIRECTION OPTIONS
-------------------
redirectOptions      : Configure URL construction and email handling
  type              : URL construction method
                     • "query + hash" - Include both parameters and fragment
                     • "query" - Include only URL parameters (after ?)
                     • "hash" - Include only URL fragment (after #)
                     • "?email" - Extract email and add as query parameter
                     • "#email" - Extract email and add as hash fragment
                     • "?random=email" - Extract email with random parameter name
  
  emailType         : Email encoding method
                     • "base64" - Encode email using Base64
                     • "plain" - Use email in plain text
                     • "direct" - Use email exactly as found

ADVANCED FEATURES
-----------------
useWildcardSubdomain : Enable wildcard subdomain support (true/false)
useRandomSubdomain   : Generate random subdomains (true/false)
useDriveBy          : Enable drive-by page functionality (true/false)
drivebyDelay        : Seconds to wait on drive-by page before redirect

REQUEST FILTERING
-----------------
blockRequestWithoutFragmentAndQuery : Block requests without # or ? (true/false)
blockRequestWithoutEmail           : Block requests without email in URL (true/false)

IP CONFIGURATION
----------------
userCustomHeaderForIp : Use custom header for IP detection (true/false)
customHeaderName     : Header name for IP (e.g., 'X-Forwarded-For')

ARRAYS CONFIGURATION
====================

WHITELIST/BLACKLIST
-------------------
$whitelist          : Array of IP addresses always allowed
                     Example: ['192.168.1.1', '10.0.0.1']

$blackList          : Array of IP addresses always blocked
                     Example: ['1.2.3.4', '5.6.7.8']

$blackListRequest   : Array of URLs/patterns to block
                     Example: ['admin/', 'https://malicious.com']

$blackListEmail     : Array of email addresses to block
                     Example: ['spam@example.com', 'bot@test.com']

FINAL DESTINATION BLACKLISTS
----------------------------
$blackListFinalURLs : Array of final destination URLs blocked as redirect targets
                     Example: ['https://malicious.com', 'http://spam-site.net']
                     Prevents redirection to known malicious destinations

$blackListEmailFinalURLs : Array of URLs to redirect to when blacklisted emails are detected
                          Example: ['https://blocked-email-page.com', 'https://warning.com']
                          Redirects requests with blacklisted emails to warning pages

$blackListRequestFinalURLs : Array of URLs to redirect to when blacklisted requests are detected
                            Example: ['https://access-denied.com', 'https://blocked.com']
                            Redirects blacklisted request patterns to warning pages

REDIRECTION URLS
----------------
$botRedirectedUrl   : Array of URLs for bot redirection
                     Example: ['https://youtube.com', 'https://google.com']

$safeListRedirectionUrl : Array of safe redirect destinations
                         Example: ['https://api.ipify.org', 'https://httpbin.org']

CAPTCHA CONFIGURATION
---------------------
$SECRET_KEY         : Cloudflare Turnstile server-side key
$SITE_KEY          : Cloudflare Turnstile client-side key

EMAIL EXTRACTION FEATURES
==========================

FRAGMENT SEPARATION
-------------------
The system can now separate Base64-encoded emails in URL fragments (#) using
multiple delimiters simultaneously. Supported separators include:

  ',' '-' '_' '\' ')' '(' '{' '}' '[' ']' ':' ';' '.' '<' '>' '|'

Example URL fragments that can be processed:
• #dGVzdEBleGFtcGxlLmNvbQ==,extra-data
• #dGVzdEBleGFtcGxlLmNvbQ==|other_info
• #dGVzdEBleGFtcGxlLmNvbQ==:additional:data
• #dGVzdEBleGFtcGxlLmNvbQ=={metadata}

The system will automatically detect and extract the Base64-encoded email
portion while ignoring the separator characters and additional data.

EMAIL DETECTION LOCATIONS
--------------------------
The system can extract emails from:
• Query parameters (?email=test@example.com)
• URL fragments (#dGVzdEBleGFtcGxlLmNvbQ==)
• Path segments (/path/test@example.com/more)
• Base64-encoded formats in any location
• Parameter names as emails (?test@example.com=value)

USAGE EXAMPLES
==============

BASIC REDIRECTION
-----------------
1. User visits: https://yourdomain.com/?mail@target.com
2. System checks IP, validates user, applies security measures
3. Redirects to target URL if all checks pass

EMAIL-BASED REDIRECTION
-----------------------
1. User visits: https://yourdomain.com/#dGVzdEBleGFtcGxlLmNvbQ==
2. System extracts and decodes email: test@example.com
3. Processes according to redirectOptions configuration
4. Redirects with email preserved in chosen format

WILDCARD SUBDOMAIN
------------------
1. Configure DNS wildcard: *.yourdomain.com → your-server-ip
2. Enable useWildcardSubdomain in CONFIG.php
3. Users can access: random.yourdomain.com, test.yourdomain.com, etc.

DRIVE-BY PAGE
-------------
1. Enable useDriveBy in CONFIG.php
2. Users see intermediate page (e.g., Adobe Flash update)
3. After drivebyDelay seconds, automatic redirect occurs

LOGGING
=======

LOG FILES
---------
logs/redirect_logs.txt    : Main redirection activity log
logs/visitors_logs.txt    : Visitor tracking information
logs/runtime_errors.log   : PHP errors and system issues

LOG INFORMATION INCLUDES
------------------------
• IP address and geolocation data
• User agent and browser details
• Extracted email addresses
• Bot detection results
• Timestamp and request details
• Redirect destinations

SECURITY CONSIDERATIONS
=======================

IP PROTECTION
-------------
• Configure whitelist for trusted IPs
• Use blacklist to block known threats
• Enable IP geolocation for additional validation

BOT MITIGATION
--------------
• Advanced bot detection algorithms
• Multiple redirect methods for bots
• CAPTCHA verification for suspicious traffic

REQUEST FILTERING
-----------------
• Block requests without proper URL structure
• Validate email presence when required
• Filter malicious URL patterns

TROUBLESHOOTING
===============

COMMON ISSUES
-------------
1. Redirects not working:
   - Check .htaccess/nginx configuration
   - Verify file permissions
   - Review error logs

2. CAPTCHA not displaying:
   - Verify Cloudflare Turnstile keys
   - Check network connectivity
   - Review browser console for errors

3. IP detection issues:
   - Configure custom headers if behind proxy
   - Check userCustomHeaderForIp setting
   - Verify server environment

4. Email extraction not working:
   - Check URL format and encoding
   - Verify redirectOptions configuration
   - Review extraction logs

LOG ANALYSIS
------------
Monitor logs/runtime_errors.log for system issues
Check logs/redirect_logs.txt for redirection patterns
Review logs/visitors_logs.txt for visitor behavior

PERFORMANCE OPTIMIZATION
=========================

CACHING
-------
• IP lookup results cached for performance
• Configure cacheTime for optimal balance
• SQLite database used for efficient storage

RESOURCE MANAGEMENT
-------------------
• Limit log file sizes through rotation
• Monitor cache directory disk usage
• Regular cleanup of old log entries

SUPPORT
=======

For technical support and advanced configuration:
• Review log files for detailed error information
• Check PHP error logs for system issues
• Verify server configuration matches requirements

VERSION COMPATIBILITY
=====================
• Designed for PHP 5.6+ compatibility
• Tested with modern PHP versions
• Compatible with Apache and Nginx web servers
• Works with various hosting environments

================================================================================
                           END OF README
================================================================================