<?php
/**
 * Manual script to update all existing links to 'admin' account
 * 
 * Usage: Run this script once via browser or CLI to update all links with NULL username to 'admin'
 * 
 * Browser: http://your-site.com/user/plugins/user-role-filter/update-links-to-admin.php
 * CLI: php update-links-to-admin.php
 */

// Load YOURLS
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/includes/load-yourls.php');

// Check if user is logged in (for browser access)
if (!defined('YOURLS_USER')) {
    // Try to authenticate
    yourls_maybe_require_auth();
}

// Only allow admins
if (!defined('YOURLS_USER') || YOURLS_USER !== 'admin') {
    die('Access denied. Only admins can run this script.');
}

global $ydb;
$table = YOURLS_DB_TABLE_URL;

// Update all links without username to 'admin'
$updated = $ydb->fetchAffected("UPDATE `$table` SET `username` = 'admin' WHERE `username` IS NULL OR `username` = ''");

// Count total links
$total = $ydb->fetchValue("SELECT COUNT(*) FROM `$table`");

// Count links with admin username
$admin_links = $ydb->fetchValue("SELECT COUNT(*) FROM `$table` WHERE `username` = 'admin'");

echo "Update complete!\n";
echo "Total links: $total\n";
echo "Links updated: $updated\n";
echo "Links with 'admin' username: $admin_links\n";
