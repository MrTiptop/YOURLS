<?php
/*
Plugin Name: Redirect 404 to Frontend
Plugin URI: https://github.com/YOURLS/YOURLS
Description: When a short URL doesn't exist, redirect to the frontend page where you can create it. Pre-fills the keyword in the custom URL field. Works the same way as Fallback URL plugin but specifically redirects to frontend creation page.
Version: 1.2
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
 * Redirect to frontend page when keyword is not found
 * 
 * This function works the same way as the Fallback URL plugin:
 * - Both use the 'redirect_keyword_not_found' action hook
 * - Both redirect with a 302 status code
 * - Both exit after redirecting
 * 
 * The difference is:
 * - Fallback URL: Redirects to a configurable URL (any URL)
 * - This plugin: Always redirects to frontend page with keyword pre-filled
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
    
    // Build frontend URL - redirect to index.php (frontend page)
    $frontend_url = yourls_site_url( 'index.php' );
    
    // If the keyword looks valid, pre-fill it using the 'k' parameter
    // The frontend will use JavaScript to pre-fill the keyword field
    if ( $keyword && preg_match( '/^[a-zA-Z0-9_-]+$/', $keyword ) ) {
        $frontend_url = yourls_add_query_arg( array( 'k' => $keyword ), $frontend_url );
    }
    
    // Redirect to frontend page (302 temporary redirect)
    yourls_redirect( $frontend_url, 302 );
    exit;
}
