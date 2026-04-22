<?php
/**
 * Legacy string functions for PHP compatibility
 * 
 * These functions provide consistent string operations across different PHP versions
 * and serve as polyfills for older PHP versions that lack native implementations.
 */

/**
 * Check if a string ends with a specific substring
 * 
 * @param string $haystack The string to search in
 * @param string $needle The substring to search for
 * @return bool True if haystack ends with needle, false otherwise
 */
function strEndsWith($haystack, $needle)
{
    // Empty needle always matches
    if ($needle === '') {
        return true;
    }
    
    // Empty haystack can't contain non-empty needle
    if ($haystack === '') {
        return false;
    }
    
    $needleLength = strlen($needle);
    
    // Use more efficient comparison for better performance
    return $needleLength <= strlen($haystack) && 
           substr_compare($haystack, $needle, -$needleLength) === 0;
}

/**
 * Check if a string contains a specific substring
 * 
 * Note: This function is named differently from PHP 8.0's native str_contains()
 * to avoid conflicts while providing similar functionality.
 * 
 * @param string $haystack The string to search in
 * @param string $needle The substring to search for
 * @return bool True if haystack contains needle, false otherwise
 */
function strContains($haystack, $needle)
{
    // Empty needle always matches
    if ($needle === '') {
        return true;
    }
    
    // Empty haystack can't contain non-empty needle
    if ($haystack === '') {
        return false;
    }
    
    return strpos($haystack, $needle) !== false;
}

/**
 * Check if a string starts with a specific substring
 * 
 * @param string $haystack The string to search in
 * @param string $needle The substring to search for
 * @return bool True if haystack starts with needle, false otherwise
 */
function strStartsWith($haystack, $needle)
{
    // Empty needle always matches
    if ($needle === '') {
        return true;
    }
    
    // Empty haystack can't contain non-empty needle
    if ($haystack === '') {
        return false;
    }
    
    return strncmp($haystack, $needle, strlen($needle)) === 0;
}
