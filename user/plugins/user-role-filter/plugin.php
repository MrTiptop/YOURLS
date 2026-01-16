<?php
/**
 * Plugin Name: User Role Filter
 * Plugin URI: https://github.com/YOURLS/YOURLS
 * Description: Implements role-based access control. Admins can see all links, regular users can only see links they created.
 * Version: 1.0.0
 * Author: Robbie De Wet
 */

// No direct call
if (!defined('YOURLS_ABSPATH')) die();

/**
 * Add username column to URL table if it doesn't exist
 */
yourls_add_action('plugins_loaded', 'user_role_filter_add_username_column');
function user_role_filter_add_username_column() {
    global $ydb;
    $table = YOURLS_DB_TABLE_URL;
    
    // Check if username column exists
    $columns = $ydb->fetchObjects("SHOW COLUMNS FROM `$table` LIKE 'username'");
    if (empty($columns)) {
        // Add username column
        $ydb->fetchAffected("ALTER TABLE `$table` ADD COLUMN `username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `ip`, ADD KEY `username` (`username`)");
    }
}

/**
 * Store username when creating a link
 * Hook into insert_link action which is called after successful insertion
 */
yourls_add_action('insert_link', 'user_role_filter_store_username', 10, 6);
function user_role_filter_store_username($inserted, $url, $keyword, $title, $timestamp, $ip) {
    // Only store username if link was successfully inserted
    if (!$inserted) {
        return;
    }
    
    global $ydb;
    $table = YOURLS_DB_TABLE_URL;
    $username = defined('YOURLS_USER') ? YOURLS_USER : null;
    
    if ($username && !empty($keyword)) {
        $ydb->fetchAffected("UPDATE `$table` SET `username` = :username WHERE `keyword` = :keyword", 
            array('username' => $username, 'keyword' => $keyword));
    }
}

/**
 * Filter admin list to show only user's links for non-admin users
 */
yourls_add_filter('admin_list_where', 'user_role_filter_admin_list');
function user_role_filter_admin_list($where) {
    // Check if current user is admin
    if (user_role_filter_is_admin()) {
        // Admin sees all links, no filtering needed
        return $where;
    }
    
    // Regular user: only show their own links
    $username = defined('YOURLS_USER') ? YOURLS_USER : null;
    if ($username) {
        if (!empty($where['sql'])) {
            $where['sql'] .= ' AND `username` = :filter_username';
        } else {
            $where['sql'] = ' AND `username` = :filter_username';
        }
        $where['binds']['filter_username'] = $username;
    }
    
    return $where;
}

/**
 * Check if current user is an admin
 * 
 * @return bool True if user is admin, false otherwise
 */
function user_role_filter_is_admin() {
    if (!defined('YOURLS_USER')) {
        return false;
    }
    
    // Get admin users from config
    $admin_users = user_role_filter_get_admin_users();
    
    return in_array(YOURLS_USER, $admin_users);
}

/**
 * Get list of admin users from config
 * 
 * @return array List of admin usernames
 */
function user_role_filter_get_admin_users() {
    // Check if admin users are defined in config
    if (defined('YOURLS_ADMIN_USERS') && is_array(YOURLS_ADMIN_USERS)) {
        return YOURLS_ADMIN_USERS;
    }
    
    // Check for global variable (preferred method)
    global $yourls_admin_users;
    if (isset($yourls_admin_users) && is_array($yourls_admin_users) && !empty($yourls_admin_users)) {
        return $yourls_admin_users;
    }
    
    // If no admin users defined, all users are admins (backward compatibility)
    // Return all users from password array
    global $yourls_user_passwords;
    if (isset($yourls_user_passwords) && is_array($yourls_user_passwords)) {
        return array_keys($yourls_user_passwords);
    }
    
    // Fallback: empty array (no admins)
    return array();
}

/**
 * Update existing links to set username to 'admin' (one-time migration)
 * This runs automatically on first plugin load
 */
yourls_add_action('plugins_loaded', 'user_role_filter_migrate_existing_links');
function user_role_filter_migrate_existing_links() {
    // Only run once automatically
    $migration_done = yourls_get_option('user_role_filter_migration_done', false);
    if ($migration_done) {
        return;
    }
    
    // Run the migration
    user_role_filter_update_all_links_to_admin();
    
    // Mark migration as done
    yourls_update_option('user_role_filter_migration_done', true);
}

/**
 * Update all existing links (with NULL or empty username) to 'admin'
 * This function can be called manually to update links
 * 
 * @return int Number of links updated
 */
function user_role_filter_update_all_links_to_admin() {
    global $ydb;
    $table = YOURLS_DB_TABLE_URL;
    
    // Update all links without username to 'admin'
    $updated = $ydb->fetchAffected("UPDATE `$table` SET `username` = 'admin' WHERE `username` IS NULL OR `username` = ''");
    
    // Log the migration if debug is enabled
    if (defined('YOURLS_DEBUG') && YOURLS_DEBUG) {
        yourls_debug_log("User Role Filter: Updated $updated existing links to 'admin' account");
    }
    
    return $updated;
}

/**
 * Admin action hook to manually update all links to admin
 * Can be triggered via: yourls_do_action('user_role_filter_update_all_to_admin')
 */
yourls_add_action('user_role_filter_update_all_to_admin', 'user_role_filter_manual_update_all');
function user_role_filter_manual_update_all() {
    // Only allow admins to run this
    if (!user_role_filter_is_admin()) {
        return;
    }
    
    $updated = user_role_filter_update_all_links_to_admin();
    
    // You can add a notice here if needed
    if (function_exists('yourls_add_notice')) {
        yourls_add_notice("Updated $updated links to 'admin' account");
    }
}

/**
 * Global variable to cache usernames for current page (to avoid repeated queries)
 */
if (!isset($GLOBALS['user_role_filter_usernames'])) {
    $GLOBALS['user_role_filter_usernames'] = array();
}

/**
 * Add "Owner" column to admin table header
 */
yourls_add_filter('table_head_cells', 'user_role_filter_add_owner_column_header');
function user_role_filter_add_owner_column_header($cells) {
    // Insert "Owner" column after "IP" and before "Clicks"
    $new_cells = array();
    foreach ($cells as $key => $value) {
        $new_cells[$key] = $value;
        if ($key === 'ip') {
            $new_cells['owner'] = yourls__('Owner');
        }
    }
    return $new_cells;
}

/**
 * Cache all usernames for current page in one query
 * Hook into admin_page_before_table to pre-load usernames
 */
yourls_add_action('admin_page_before_table', 'user_role_filter_cache_all_usernames');
function user_role_filter_cache_all_usernames() {
    global $ydb;
    $table = YOURLS_DB_TABLE_URL;
    
    // Check if username column exists
    $columns = $ydb->fetchObjects("SHOW COLUMNS FROM `$table` LIKE 'username'");
    if (empty($columns)) {
        // Column doesn't exist yet, try to add it
        user_role_filter_add_username_column();
        // Re-check after adding
        $columns = $ydb->fetchObjects("SHOW COLUMNS FROM `$table` LIKE 'username'");
    }
    
    // Initialize cache
    if (!isset($GLOBALS['user_role_filter_usernames'])) {
        $GLOBALS['user_role_filter_usernames'] = array();
    }
    
    // If column exists, we can query it, but we don't know which keywords are on this page yet
    // So we'll query per-row, but at least we know the column exists
    $GLOBALS['user_role_filter_username_column_exists'] = !empty($columns);
}

/**
 * Add "Owner" cell to admin table rows
 */
yourls_add_filter('table_add_row_cell_array', 'user_role_filter_add_owner_column_cell', 10, 7);
function user_role_filter_add_owner_column_cell($cells, $keyword, $url, $title, $ip, $clicks, $timestamp) {
    global $ydb;
    $table = YOURLS_DB_TABLE_URL;
    
    // Initialize cache if needed
    if (!isset($GLOBALS['user_role_filter_usernames'])) {
        $GLOBALS['user_role_filter_usernames'] = array();
    }
    
    // Check cache first
    if (!isset($GLOBALS['user_role_filter_usernames'][$keyword])) {
        // Check if column exists
        $column_exists = isset($GLOBALS['user_role_filter_username_column_exists']) 
            ? $GLOBALS['user_role_filter_username_column_exists'] 
            : false;
        
        if (!$column_exists) {
            // Check if column exists
            $columns = $ydb->fetchObjects("SHOW COLUMNS FROM `$table` LIKE 'username'");
            $column_exists = !empty($columns);
            $GLOBALS['user_role_filter_username_column_exists'] = $column_exists;
            
            if (!$column_exists) {
                // Column doesn't exist, add it
                user_role_filter_add_username_column();
                $column_exists = true;
            }
        }
        
        if ($column_exists) {
            // Query username
            $username = $ydb->fetchValue("SELECT `username` FROM `$table` WHERE `keyword` = :keyword LIMIT 1", array('keyword' => $keyword));
            
            // fetchValue returns false if no result, or the value (which could be NULL)
            // We need to distinguish between "no result" and "NULL value"
            if ($username === false) {
                // This shouldn't happen if keyword exists, but handle it
                $username = null;
            }
            // If $username is NULL (string), that's fine - it means the column exists but is NULL
            
            $GLOBALS['user_role_filter_usernames'][$keyword] = $username;
        } else {
            $GLOBALS['user_role_filter_usernames'][$keyword] = null;
        }
    }
    
    $username = $GLOBALS['user_role_filter_usernames'][$keyword];
    
    // If no username, show "—" (em dash)
    // Check for null, empty string, or false
    $owner_display = ($username !== null && $username !== false && $username !== '') 
        ? yourls_esc_html($username) 
        : '—';
    
    // Insert "Owner" cell after "ip" and before "clicks"
    $new_cells = array();
    foreach ($cells as $key => $value) {
        $new_cells[$key] = $value;
        if ($key === 'ip') {
            $new_cells['owner'] = array(
                'template' => '%owner%',
                'owner'    => $owner_display,
            );
        }
    }
    return $new_cells;
}

/**
 * Update "No URL" message colspan to account for new Owner column
 */
yourls_add_filter('table_tbody_end', 'user_role_filter_update_colspan');
function user_role_filter_update_colspan($html) {
    // Replace colspan="6" with colspan="7" in the "No URL" row
    $html = preg_replace('/<tr id="nourl_found"[^>]*>.*?<td[^>]*colspan="6"[^>]*>/', 
        '<tr id="nourl_found" style="display:none"><td colspan="7">', 
        $html);
    return $html;
}

/**
 * Also update via JavaScript as backup
 */
yourls_add_action('admin_page_after_table', 'user_role_filter_update_colspan_script');
function user_role_filter_update_colspan_script() {
    // Update the colspan via JavaScript as backup
    echo '<script>
    (function() {
        var noUrlRow = document.getElementById("nourl_found");
        if (noUrlRow) {
            var cells = noUrlRow.querySelectorAll("td");
            if (cells.length === 1) {
                var currentColspan = cells[0].getAttribute("colspan");
                if (currentColspan === "6" || !currentColspan) {
                    cells[0].setAttribute("colspan", "7");
                }
            }
        }
    })();
    </script>';
}
