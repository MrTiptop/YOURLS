<?php
/**
 * Plugin Name: Fix Docker IP Detection
 * Plugin URI: https://github.com/YOURLS/YOURLS
 * Description: Fixes IP address detection when running YOURLS in Docker containers. Replaces Docker gateway IP (172.18.0.1) with actual client IP.
 * Version: 1.0.0
 * Author: Robbie De Wet
 */

// No direct call
if (!defined('YOURLS_ABSPATH')) die();

/**
 * Filter IP address detection to handle Docker networking
 * 
 * When YOURLS runs in Docker, REMOTE_ADDR is often the Docker gateway IP (172.18.0.1).
 * This filter attempts to get the real client IP from various sources.
 */
yourls_add_filter('get_IP', 'fix_docker_ip_detection');

function fix_docker_ip_detection($ip) {
    // If we got a Docker gateway IP, try to find the real client IP
    if ($ip === '172.18.0.1' || $ip === '172.17.0.1' || preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\.0\.1$/', $ip)) {
        // Try to get real IP from various sources
        $real_ip = '';
        
        // Check X-Forwarded-For header (set by reverse proxies)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'];
            // X-Forwarded-For can contain multiple IPs, take the first one
            $ips = explode(',', $forwarded);
            $real_ip = trim($ips[0]);
        }
        // Check X-Real-IP header (set by nginx and some proxies)
        elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $real_ip = trim($_SERVER['HTTP_X_REAL_IP']);
        }
        // Check X-Client-IP header
        elseif (!empty($_SERVER['HTTP_X_CLIENT_IP'])) {
            $real_ip = trim($_SERVER['HTTP_X_CLIENT_IP']);
        }
        // If accessing from host machine (localhost), use 127.0.0.1
        elseif (!empty($_SERVER['HTTP_HOST']) && 
                (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                 strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) {
            $real_ip = '127.0.0.1';
        }
        
        // Validate the IP before using it
        if (!empty($real_ip) && yourls_sanitize_ip($real_ip)) {
            return yourls_sanitize_ip($real_ip);
        }
        
        // If we can't determine the real IP and it's localhost access, use 127.0.0.1
        // Otherwise, return the original IP (better than nothing)
        if (empty($real_ip) && 
            (!empty($_SERVER['HTTP_HOST']) && 
             (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
              strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false))) {
            return '127.0.0.1';
        }
    }
    
    // Return the original IP if it's not a Docker gateway IP
    return $ip;
}
