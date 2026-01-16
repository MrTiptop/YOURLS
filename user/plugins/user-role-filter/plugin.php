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
 * Hook into pre_add_new_link to catch link creation early
 * DISABLED - was causing issues with link creation
 */
// yourls_add_action('pre_add_new_link', 'user_role_filter_pre_add_new_link', 10, 3);
function user_role_filter_pre_add_new_link($url, $keyword, $title) {
    // Disabled to prevent any potential issues
    return;
}

/**
 * Store username when creating a link
 * Hook into insert_link action which is called after successful insertion
 * This runs AFTER the link is created, so it cannot prevent link creation
 * 
 * Note: $inserted is a boolean (true/false), not the number of rows
 */
// DISABLED - not currently used
// yourls_add_action('insert_link', 'user_role_filter_store_username', 10, 6);
function user_role_filter_store_username($inserted, $url, $keyword, $title, $timestamp, $ip) {
    // Only store username if link was successfully inserted
    if (!$inserted) {
        if (defined('YOURLS_DEBUG') && YOURLS_DEBUG) {
            yourls_debug_log("User Role Filter: insert_link called but insert failed for keyword: $keyword");
        }
        return;
    }
    
    // Try immediately first
    try {
        user_role_filter_store_username_helper($keyword);
    } catch (Exception $e) {
        // Silently fail
    }
    
    // Also schedule a delayed update in case of timing issues
    register_shutdown_function(function() use ($keyword) {
        try {
            user_role_filter_store_username_helper($keyword);
        } catch (Exception $e) {
            // Silently fail
        }
    });
}

/**
 * Backup: Also hook into post_add_new_link to ensure username is stored
 * This catches cases where insert_link might not fire or fails
 */
// DISABLED - not currently used
// yourls_add_action('post_add_new_link', 'user_role_filter_store_username_backup', 10, 4);
function user_role_filter_store_username_backup($url, $keyword, $title, $return) {
    // Only store username if link was successfully created
    if (!isset($return['status']) || $return['status'] !== 'success') {
        return;
    }
    
    // Try to get keyword from return array if not passed directly
    if (empty($keyword) && isset($return['url']['keyword'])) {
        $keyword = $return['url']['keyword'];
    }
    
    // Also try to extract from shorturl if available
    if (empty($keyword) && isset($return['shorturl'])) {
        $shorturl = $return['shorturl'];
        if (preg_match('/\/([^\/]+)$/', $shorturl, $matches)) {
            $keyword = $matches[1];
        }
    }
    
    if (empty($keyword)) {
        return;
    }
    
    // Store username - this is our backup method
    user_role_filter_store_username_helper($keyword);
}

/**
 * PRIMARY METHOD: Hook into add_new_link filter (this ALWAYS fires)
 * This filter is called at the very end, after everything is done
 * Filters are guaranteed to execute, unlike actions which might not fire
 * 
 * Note: Filter signature is ($return, $url, $keyword, $title) - 4 parameters
 * 
 * SAFE VERSION: Minimal code, maximum safety
 */
yourls_add_filter('add_new_link', 'user_role_filter_store_username_final', 999, 4);
function user_role_filter_store_username_final($return, $url, $keyword, $title) {
    // CRITICAL: Always return the array immediately - don't do anything that could break it
    // Store username in background using shutdown function
    
    // Quick check: only proceed if return is valid array with success status
    if (!is_array($return) || !isset($return['status']) || $return['status'] !== 'success') {
        return $return;
    }
    
    // Extract keyword safely
    $final_keyword = '';
    if (!empty($keyword)) {
        $final_keyword = $keyword;
    } elseif (isset($return['url']['keyword']) && !empty($return['url']['keyword'])) {
        $final_keyword = $return['url']['keyword'];
    } elseif (isset($return['shorturl']) && !empty($return['shorturl'])) {
        // Extract from shorturl
        if (preg_match('/\/([^\/]+)$/', $return['shorturl'], $matches)) {
            $final_keyword = $matches[1];
        }
    }
    
    // Store username in background - use shutdown to avoid any blocking
    if (!empty($final_keyword)) {
        // Use a simple variable copy to avoid closure issues
        $kw = $final_keyword;
        register_shutdown_function(function() use ($kw) {
            @user_role_filter_store_username_helper($kw);
        });
    }
    
    // CRITICAL: Return immediately - don't modify the array
    return $return;
}

/**
 * Helper function to store username for a keyword
 * 
 * @param string $keyword The keyword to update
 */
function user_role_filter_store_username_helper($keyword) {
    // Get username - try YOURLS_USER first, then fallback to cookie
    $username = null;
    
    if (defined('YOURLS_USER') && !empty(YOURLS_USER)) {
        $username = YOURLS_USER;
    } else {
        // Fallback: Extract username from cookie using YOURLS cookie validation logic
        if (function_exists('yourls_cookie_name') && function_exists('yourls_cookie_value')) {
            $cookie_name = yourls_cookie_name();
            if (isset($_COOKIE[$cookie_name])) {
                global $yourls_user_passwords;
                if (isset($yourls_user_passwords) && is_array($yourls_user_passwords)) {
                    foreach ($yourls_user_passwords as $valid_user => $valid_password) {
                        if (yourls_cookie_value($valid_user) === $_COOKIE[$cookie_name]) {
                            $username = $valid_user;
                            break;
                        }
                    }
                }
            }
        }
    }
    
    if (empty($username)) {
        if (defined('YOURLS_DEBUG') && YOURLS_DEBUG) {
            $user_status = defined('YOURLS_USER') ? 'defined but empty' : 'not defined';
            yourls_debug_log("User Role Filter: Cannot get username ($user_status) when storing username for keyword: $keyword");
        }
        return;
    }
    
    if (empty($keyword)) {
        if (defined('YOURLS_DEBUG') && YOURLS_DEBUG) {
            yourls_debug_log("User Role Filter: Keyword is empty when storing username: $username");
        }
        return;
    }
    
    // Get database connection - use write context to ensure we can update
    $ydb = yourls_get_db('write-user_role_filter_store_username');
    $table = YOURLS_DB_TABLE_URL;
    
    try {
        // Ensure username column exists (should already exist, but check to be safe)
        $columns = $ydb->fetchObjects("SHOW COLUMNS FROM `$table` LIKE 'username'");
        if (empty($columns)) {
            // Column doesn't exist, try to add it (but don't fail if it doesn't work)
            try {
                user_role_filter_add_username_column();
            } catch (Exception $e) {
                if (defined('YOURLS_DEBUG') && YOURLS_DEBUG) {
                    yourls_debug_log("User Role Filter: Could not create username column: " . $e->getMessage());
                }
                return; // Can't store username without column
            }
        }
        
        // Sanitize keyword to match database format
        $keyword = yourls_sanitize_keyword($keyword);
        
        // First verify the keyword exists in the database
        $exists = $ydb->fetchValue("SELECT COUNT(*) FROM `$table` WHERE `keyword` = :keyword", array('keyword' => $keyword));
        
        if ($exists == 0) {
            // Keyword doesn't exist yet - this might be a timing issue
            // Wait a tiny bit and try again (for race conditions)
            usleep(100000); // 100ms delay
            $exists = $ydb->fetchValue("SELECT COUNT(*) FROM `$table` WHERE `keyword` = :keyword", array('keyword' => $keyword));
        }
        
        if ($exists > 0) {
            // Update username - use COALESCE to handle NULL, and ensure we're updating correctly
            $affected = $ydb->fetchAffected("UPDATE `$table` SET `username` = :username WHERE `keyword` = :keyword AND (`username` IS NULL OR `username` != :username)", 
                array('username' => $username, 'keyword' => $keyword));
            
            // If the above didn't update (because username already matches), try without the condition
            if ($affected == 0) {
                $affected = $ydb->fetchAffected("UPDATE `$table` SET `username` = :username WHERE `keyword` = :keyword", 
                    array('username' => $username, 'keyword' => $keyword));
            }
            
            if (defined('YOURLS_DEBUG') && YOURLS_DEBUG) {
                if ($affected > 0) {
                    yourls_debug_log("User Role Filter: Successfully stored username '$username' for keyword: $keyword");
                } else {
                    $current_username = $ydb->fetchValue("SELECT `username` FROM `$table` WHERE `keyword` = :keyword", array('keyword' => $keyword));
                    yourls_debug_log("User Role Filter: Update affected 0 rows for keyword: $keyword (trying to set: '$username', current in DB: " . var_export($current_username, true) . ")");
                }
            }
        } else {
            if (defined('YOURLS_DEBUG') && YOURLS_DEBUG) {
                yourls_debug_log("User Role Filter: Keyword '$keyword' does not exist in database when trying to store username '$username'");
            }
        }
    } catch (Exception $e) {
        if (defined('YOURLS_DEBUG') && YOURLS_DEBUG) {
            yourls_debug_log("User Role Filter: Exception storing username for keyword $keyword: " . $e->getMessage());
        }
    } catch (Error $e) {
        if (defined('YOURLS_DEBUG') && YOURLS_DEBUG) {
            yourls_debug_log("User Role Filter: Fatal error storing username for keyword $keyword: " . $e->getMessage());
        }
    }
}

/**
 * Filter admin list to show only user's links for non-admin users
 * This only affects the admin list display, not delete/edit operations
 */
yourls_add_filter('admin_list_where', 'user_role_filter_admin_list');
function user_role_filter_admin_list($where) {
    // Safety check: ensure $where is an array with expected structure
    if (!is_array($where)) {
        return array('sql' => '', 'binds' => array());
    }
    
    // Ensure binds array exists
    if (!isset($where['binds']) || !is_array($where['binds'])) {
        $where['binds'] = array();
    }
    
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
    // Also ensure the add button is enabled (fix for disabled button issue)
    // Fix table sorter column count issue
    echo '<script>
    (function() {
        // Fix colspan
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
        
        // Ensure add button is enabled on page load and after AJAX calls
        function enableAddButton() {
            var addButton = document.getElementById("add-button");
            if (addButton) {
                // Remove disabled class and attribute if present
                addButton.classList.remove("disabled");
                addButton.classList.remove("loading");
                addButton.removeAttribute("disabled");
                addButton.removeAttribute("aria-disabled");
            }
        }
        
        // Enable button on page load
        enableAddButton();
        
        // Also enable button after AJAX calls complete
        if (typeof jQuery !== "undefined") {
            jQuery(document).ajaxComplete(function() {
                setTimeout(enableAddButton, 100);
            });
        }
        
        // Fix table sorter column count - update configuration for 7 columns
        // Column order: 0=shorturl, 1=longurl, 2=date, 3=ip, 4=owner, 5=clicks, 6=actions
        if (typeof jQuery !== "undefined" && jQuery("#main_table").length) {
            jQuery(document).ready(function($) {
                // Override the table sorter initialization to account for 7 columns
                var $table = $("#main_table");
                
                // Check if table has thead with 7 columns
                var headerCount = $table.find("thead tr th").length;
                if (headerCount === 7) {
                    // Update order mapping to include owner column
                    var order = {\'keyword\':0, \'url\':1, \'timestamp\':2, \'ip\':3, \'owner\':4, \'clicks\':5};
                    var order_by = {\'asc\':0, \'desc\':1};
                    
                    // Get sort parameters from URL or use defaults
                    function getQueryParam(key) {
                        var regex = new RegExp("[\\?&]" + key.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]") + "=([^&#]*)");
                        var qs = regex.exec(window.location.href);
                        return qs === null ? null : qs[1];
                    }
                    
                    var sort_by_param = getQueryParam(\'sort_by\');
                    var sort_order_param = getQueryParam(\'sort_order\');
                    var sort_by = (sort_by_param && order[sort_by_param] !== undefined) ? order[sort_by_param] : (typeof yourls_defaultsort !== "undefined" ? yourls_defaultsort : 2);
                    var sort_order = (sort_order_param && order_by[sort_order_param] !== undefined) ? order_by[sort_order_param] : (typeof yourls_defaultorder !== "undefined" ? yourls_defaultorder : 1);
                    
                    // Destroy existing tablesorter if it exists
                    if ($table.data("tablesorter")) {
                        $table.tablesorter("destroy");
                    }
                    
                    // Reinitialize with correct column count
                    if ($table.tablesorter && $table.find("tr#nourl_found").css(\'display\') === \'none\') {
                        $table.tablesorter({
                            textExtraction: {
                                1: function(node, table, cellIndex){return $(node).find("small a").text();}
                            },
                            sortList:[[ sort_by, sort_order ]], 
                            headers: { 6: {sorter: false} }, // no sorter on column "Actions" (now column 6)
                            widgets: [\'zebra\'],
                            widgetOptions : { zebra : [ "normal-row", "alt-row" ] }
                        });
                    }
                }
            });
        }
    })();
    </script>';
}
