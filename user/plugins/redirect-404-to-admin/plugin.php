<?php
/*
Plugin Name: Redirect 404 to Admin
Plugin URI: https://github.com/YOURLS/YOURLS
Description: When a short URL doesn't exist, redirect to the admin page where you can create it. Optionally pre-fills the keyword in the URL field. Works the same way as Fallback URL plugin but specifically redirects to admin creation page.
Version: 1.1
Author: Robbie De Wet
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

/**
 * Hook into the 'redirect_keyword_not_found' action
 * This action is triggered when someone tries to access a short URL that doesn't exist
 * This is the same hook used by the Fallback URL plugin
 */
yourls_add_action( 'redirect_keyword_not_found', 'redirect_404_to_admin' );

/**
 * Redirect to admin page when keyword is not found
 * 
 * This function works the same way as the Fallback URL plugin:
 * - Both use the 'redirect_keyword_not_found' action hook
 * - Both redirect with a 302 status code
 * - Both exit after redirecting
 * 
 * The difference is:
 * - Fallback URL: Redirects to a configurable URL (any URL)
 * - This plugin: Always redirects to admin page with keyword pre-filled
 * 
 * @param string|array $keyword The keyword that was not found (can be string or array)
 */
function redirect_404_to_admin( $keyword ) {
    // Handle case where $keyword might be an array (from action system)
    if ( is_array( $keyword ) ) {
        $keyword = isset( $keyword[0] ) ? $keyword[0] : '';
    }
    
    // Ensure $keyword is a string
    if ( !is_string( $keyword ) ) {
        return; // Don't redirect if we can't determine the keyword
    }
    
    // Skip .qr requests - let the QR code plugin handle them
    if ( substr( $keyword, -3 ) === '.qr' ) {
        return; // Let other plugins (like QR code) handle this
    }
    
    // Sanitize the keyword
    $keyword = yourls_sanitize_keyword( $keyword );
    
    // Build admin URL - redirect to index.php (main admin page)
    $admin_url = yourls_admin_url( 'index.php' );
    
    // Optionally, if the keyword looks valid, pre-fill it using the 'k' parameter
    // The admin page uses $_GET['k'] to pre-fill the keyword field
    if ( $keyword && preg_match( '/^[a-zA-Z0-9_-]+$/', $keyword ) ) {
        $admin_url = yourls_add_query_arg( array( 'k' => $keyword ), $admin_url );
    }
    
    // Redirect to admin page (302 temporary redirect, same as Fallback URL plugin)
    yourls_redirect( $admin_url, 302 );
    exit;
}
