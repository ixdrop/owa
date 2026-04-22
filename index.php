<?php
error_reporting(E_ALL);
ini_set('ignore_repeated_errors', TRUE);
ini_set('display_errors', FALSE);
ini_set('log_errors', TRUE);
ini_set('error_log', __DIR__ . '/logs/runtime_errors.log'); // Logging file path

global $SITE_KEY, $SECRET_KEY, $config, $destinationUrls, $whitelist, $safeRedirectionUrl, $botRedirectedUrl, $customSubdomain, $safeListRedirectionUrl, $blackList;
require_once __DIR__ . '/app/legacy.php';
include_once __DIR__ . '/CONFIG.php';
require_once __DIR__ . '/app/addon/tools.php';
require_once __DIR__ . '/app/Captcha.php';
$destinationUrls = $config['useSafeListRedirection']?$safeListRedirectionUrl:[$safeRedirectionUrl];


$redirectHandler = new Captcha($SECRET_KEY, $SITE_KEY, new Redirection($config, $destinationUrls, $whitelist, $botRedirectedUrl, $customSubdomain, $blackList));
$redirectHandler->init();

