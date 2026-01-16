<?php
/**
 * Plugin Name: Local Subnet Mapping
 * Plugin URI: https://github.com/YOURLS/YOURLS
 * Description: Maps local/private IP addresses and subnets to custom location names (e.g., "Office Network", "VPN", "Docker Network")
 * Version: 1.0.0
 * Author: Robbie De Wet
 */

// No direct call
if (!defined('YOURLS_ABSPATH')) die();

/**
 * Filter GeoIP country code to map local subnets to custom locations
 */
yourls_add_filter('geo_ip_to_countrycode', 'local_subnet_map_ip');

/**
 * Map IP addresses to custom location names based on subnet configuration
 * 
 * @param string $country_code Current country code (or empty)
 * @param string $ip IP address
 * @param string $default Default value
 * @return string Country code or custom location code
 */
function local_subnet_map_ip($country_code, $ip, $default) {
    // Get subnet mappings from config
    $subnet_mappings = local_subnet_get_mappings();
    
    if (empty($subnet_mappings)) {
        return $country_code;
    }
    
    // Sort subnets by specificity (more specific CIDR first)
    // This ensures 172.18.0.0/16 matches before 172.16.0.0/12
    uksort($subnet_mappings, function($a, $b) {
        $mask_a = strpos($a, '/') !== false ? intval(explode('/', $a)[1]) : 32;
        $mask_b = strpos($b, '/') !== false ? intval(explode('/', $b)[1]) : 32;
        return $mask_b - $mask_a; // Higher mask (more specific) first
    });
    
    // Check if IP matches any configured subnet (most specific first)
    foreach ($subnet_mappings as $subnet => $location) {
        if (local_subnet_ip_in_range($ip, $subnet)) {
            // Generate a 2-character code for database storage
            $code = local_subnet_get_location_code($location);
            // Store the mapping for later retrieval
            local_subnet_store_mapping($code, $location);
            return $code;
        }
    }
    
    return $country_code;
}

/**
 * Generate a 2-character code from location name
 * 
 * @param string $location Location name
 * @return string 2-character code
 */
function local_subnet_get_location_code($location) {
    // Remove common words and get first 2 meaningful characters
    $clean = preg_replace('/\b(network|private|local|office|corporate|vpn|docker|server)\b/i', '', $location);
    $clean = preg_replace('/[^a-zA-Z]/', '', $clean);
    $clean = strtoupper($clean);
    
    // Use first 2 letters, or generate from words
    if (strlen($clean) >= 2) {
        return substr($clean, 0, 2);
    }
    
    // Fallback: use first letter of each word
    $words = explode(' ', $location);
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    
    // Last resort: use "XX" for unknown local
    return 'XX';
}

/**
 * Store location code to name mapping
 * Uses YOURLS options to persist mappings
 * 
 * @param string $code 2-character code
 * @param string $location Full location name
 */
function local_subnet_store_mapping($code, $location) {
    static $mappings_cache = null;
    
    if ($mappings_cache === null) {
        $mappings_cache = yourls_get_option('local_subnet_code_mappings', array());
    }
    
    if (!isset($mappings_cache[$code])) {
        $mappings_cache[$code] = $location;
        yourls_update_option('local_subnet_code_mappings', $mappings_cache);
    }
}

/**
 * Shunt filter to intercept country code to name mapping for local subnets
 */
yourls_add_filter('shunt_geo_countrycode_to_countryname', 'local_subnet_map_country_name');

/**
 * Map custom location codes to readable names
 * 
 * @param mixed $pre Previous filter result (false if not handled)
 * @param string $code Country code
 * @return string|false Country/location name or false to continue with default
 */
function local_subnet_map_country_name($pre, $code) {
    // Check if it's a local subnet mapping code
    // Get stored mappings from options
    $mappings = yourls_get_option('local_subnet_code_mappings', array());
    
    if (!empty($code) && isset($mappings[$code])) {
        return $mappings[$code];
    }
    
    // Also check for old LOCAL_ prefix format (for backward compatibility)
    if (!empty($code) && strpos($code, 'LOCAL_') === 0) {
        $location = str_replace('LOCAL_', '', $code);
        $location = str_replace('_', ' ', $location);
        // Convert to title case for better readability
        $location = ucwords(strtolower($location));
        return $location;
    }
    
    // Not a local subnet, let default handler process it
    return false;
}

/**
 * Get subnet mappings from config
 * 
 * @return array Array of 'subnet' => 'location name'
 */
function local_subnet_get_mappings() {
    static $mappings = null;
    
    if ($mappings !== null) {
        return $mappings;
    }
    
    // Default mappings for common private IP ranges
    $default_mappings = array(
        '127.0.0.0/8' => 'Localhost',
        '10.0.0.0/8' => 'Private Network (10.x)',
        '172.16.0.0/12' => 'Private Network (172.16-31.x)',
        '192.168.0.0/16' => 'Private Network (192.168.x)',
        '172.18.0.0/16' => 'Docker Network',
        '172.17.0.0/16' => 'Docker Default Bridge',
    );
    
    // Check if custom mappings are defined in config
    global $local_subnet_mappings;
    if (isset($local_subnet_mappings) && is_array($local_subnet_mappings)) {
        $mappings = array_merge($default_mappings, $local_subnet_mappings);
    } else {
        $mappings = $default_mappings;
    }
    
    return $mappings;
}

/**
 * Check if an IP address is within a subnet range
 * 
 * @param string $ip IP address (e.g., "192.168.1.100")
 * @param string $subnet Subnet in CIDR notation (e.g., "192.168.1.0/24")
 * @return bool True if IP is in subnet
 */
function local_subnet_ip_in_range($ip, $subnet) {
    if (strpos($subnet, '/') === false) {
        // Single IP match
        return $ip === $subnet;
    }
    
    list($subnet_ip, $mask) = explode('/', $subnet);
    $mask = (int)$mask;
    
    // Convert IPs to long integers
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet_ip);
    
    if ($ip_long === false || $subnet_long === false) {
        return false;
    }
    
    // Calculate network mask
    $network_mask = -1 << (32 - $mask);
    
    // Check if IP is in the network
    return ($ip_long & $network_mask) === ($subnet_long & $network_mask);
}

/**
 * Register admin page for managing subnet mappings
 */
yourls_add_action('plugins_loaded', 'local_subnet_register_admin_page');

function local_subnet_register_admin_page() {
    yourls_register_plugin_page('local_subnet', 'Local Subnet Mapping', 'local_subnet_admin_page');
}

/**
 * Admin page for managing subnet mappings
 */
function local_subnet_admin_page() {
    global $local_subnet_mappings;
    
    // Handle import submission
    if (isset($_POST['import_submit']) && yourls_verify_nonce('local_subnet_import', $_POST['nonce'])) {
        $imported = 0;
        $errors = array();
        
        // Get current mappings to merge with
        $mappings = local_subnet_get_mappings();
        
        // Handle file upload
        if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] == UPLOAD_ERR_OK) {
            $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
            $imported_mappings = local_subnet_parse_import($file_content, $errors);
            $mappings = array_merge($mappings, $imported_mappings);
            $imported = count($imported_mappings);
        }
        // Handle text paste
        elseif (isset($_POST['import_text']) && !empty(trim($_POST['import_text']))) {
            $imported_mappings = local_subnet_parse_import($_POST['import_text'], $errors);
            $mappings = array_merge($mappings, $imported_mappings);
            $imported = count($imported_mappings);
        }
        
        // Save merged mappings
        if ($imported > 0 && local_subnet_save_mappings($mappings)) {
            echo '<div class="success">Imported ' . $imported . ' subnet mapping(s)!</div>';
            if (!empty($errors)) {
                echo '<div class="warning">Some entries had errors: ' . implode(', ', $errors) . '</div>';
            }
        } elseif ($imported == 0) {
            echo '<div class="error">No valid subnet mappings found to import.</div>';
            if (!empty($errors)) {
                echo '<div class="error">Errors: ' . implode(', ', $errors) . '</div>';
            }
        } else {
            echo '<div class="error">Failed to save subnet mappings. Please check file permissions.</div>';
        }
    }
    
    // Handle form submission
    if (isset($_POST['submit']) && yourls_verify_nonce('local_subnet_update', $_POST['nonce'])) {
        $mappings = array();
        
        // Process existing mappings
        if (isset($_POST['subnets']) && is_array($_POST['subnets']) && isset($_POST['locations']) && is_array($_POST['locations'])) {
            foreach ($_POST['subnets'] as $key => $subnet) {
                $subnet = trim($subnet);
                $location = isset($_POST['locations'][$key]) ? trim($_POST['locations'][$key]) : '';
                if (!empty($subnet) && !empty($location)) {
                    $mappings[$subnet] = $location;
                }
            }
        }
        
        // Process new mappings
        if (isset($_POST['subnets_new']) && is_array($_POST['subnets_new']) && isset($_POST['locations_new']) && is_array($_POST['locations_new'])) {
            foreach ($_POST['subnets_new'] as $key => $subnet) {
                $subnet = trim($subnet);
                $location = isset($_POST['locations_new'][$key]) ? trim($_POST['locations_new'][$key]) : '';
                if (!empty($subnet) && !empty($location)) {
                    $mappings[$subnet] = $location;
                }
            }
        }
        
        // Save to config file
        if (local_subnet_save_mappings($mappings)) {
            echo '<div class="success">Subnet mappings updated!</div>';
        } else {
            echo '<div class="error">Failed to save subnet mappings. Please check file permissions.</div>';
        }
    }
    
    // Get current mappings
    $current_mappings = local_subnet_get_mappings();
    
    echo '<h2>Local Subnet Mapping</h2>';
    echo '<p>Configure custom location names for IP addresses in your local subnets. These will appear in the stats pages instead of country codes.</p>';
    
    echo '<form method="post">';
    yourls_nonce_field('local_subnet_update');
    
    echo '<table class="sortable">';
    echo '<thead><tr><th>Subnet (CIDR)</th><th>Location Name</th><th>Action</th></tr></thead>';
    echo '<tbody id="subnet-mappings">';
    
    $index = 0;
    foreach ($current_mappings as $subnet => $location) {
        echo '<tr>';
        echo '<td><input type="text" name="subnets[' . $index . ']" value="' . htmlspecialchars($subnet) . '" style="width: 200px;" /></td>';
        echo '<td><input type="text" name="locations[' . $index . ']" value="' . htmlspecialchars($location) . '" style="width: 300px;" /></td>';
        echo '<td><button type="button" class="remove-row">Remove</button></td>';
        echo '</tr>';
        $index++;
    }
    
    echo '</tbody></table>';
    
    echo '<p><button type="button" id="add-subnet" class="button">Add Subnet</button></p>';
    echo '<p><input type="submit" name="submit" value="Save Mappings" class="button-primary" /></p>';
    echo '</form>';
    
    // Import section
    echo '<hr style="margin: 30px 0;">';
    echo '<h3>Bulk Import Subnets</h3>';
    echo '<p>Import multiple subnet mappings at once. Supported formats:</p>';
    echo '<ul>';
    echo '<li><strong>CSV:</strong> subnet,location (one per line)</li>';
    echo '<li><strong>Text:</strong> subnet location or subnet,location (one per line)</li>';
    echo '<li><strong>JSON:</strong> {"subnet": "location", ...}</li>';
    echo '</ul>';
    
    echo '<form method="post" enctype="multipart/form-data">';
    yourls_nonce_field('local_subnet_import');
    
    echo '<h4>Option 1: Upload File</h4>';
    echo '<p><input type="file" name="import_file" accept=".csv,.txt,.json" /></p>';
    
    echo '<h4>Option 2: Paste Text</h4>';
    echo '<p><textarea name="import_text" rows="10" cols="80" placeholder="192.168.1.0/24,Office Network&#10;192.168.2.0/24,Remote Office&#10;10.0.0.0/8,Corporate VPN"></textarea></p>';
    
    echo '<p><input type="submit" name="import_submit" value="Import Subnets" class="button-primary" /></p>';
    echo '<p><small>Note: Imported subnets will be merged with existing mappings. Duplicate subnets will be overwritten.</small></p>';
    echo '</form>';
    
    echo '<script>
    document.getElementById("add-subnet").addEventListener("click", function() {
        var tbody = document.getElementById("subnet-mappings");
        var row = tbody.insertRow();
        var index = tbody.rows.length;
        row.innerHTML = \'<td><input type="text" name="subnets_new[\' + index + \']" placeholder="192.168.1.0/24" style="width: 200px;" /></td>\' +
                        \'<td><input type="text" name="locations_new[\' + index + \']" placeholder="Office Network" style="width: 300px;" /></td>\' +
                        \'<td><button type="button" class="remove-row">Remove</button></td>\';
    });
    
    document.addEventListener("click", function(e) {
        if (e.target.classList.contains("remove-row")) {
            e.target.closest("tr").remove();
        }
    });
    </script>';
}

/**
 * Parse imported subnet data from various formats
 * 
 * @param string $content File content or text to parse
 * @param array &$errors Array to collect error messages
 * @return array Array of subnet => location mappings
 */
function local_subnet_parse_import($content, &$errors = array()) {
    $mappings = array();
    $lines = explode("\n", $content);
    
    // Try JSON first
    $json_data = json_decode(trim($content), true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
        foreach ($json_data as $subnet => $location) {
            $subnet = trim($subnet);
            $location = trim($location);
            if (!empty($subnet) && !empty($location)) {
                if (local_subnet_validate_subnet($subnet)) {
                    $mappings[$subnet] = $location;
                } else {
                    $errors[] = "Invalid subnet format: $subnet";
                }
            }
        }
        return $mappings;
    }
    
    // Parse line by line (CSV or text format)
    foreach ($lines as $line_num => $line) {
        $line = trim($line);
        
        // Skip empty lines and comments
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Try CSV format: subnet,location
        if (strpos($line, ',') !== false) {
            $parts = explode(',', $line, 2);
            $subnet = trim($parts[0]);
            $location = isset($parts[1]) ? trim($parts[1]) : '';
        }
        // Try space-separated: subnet location
        elseif (preg_match('/^([\d\.\/]+)\s+(.+)$/', $line, $matches)) {
            $subnet = trim($matches[1]);
            $location = trim($matches[2]);
        }
        // Try tab-separated
        elseif (strpos($line, "\t") !== false) {
            $parts = explode("\t", $line, 2);
            $subnet = trim($parts[0]);
            $location = isset($parts[1]) ? trim($parts[1]) : '';
        }
        else {
            $errors[] = "Line " . ($line_num + 1) . ": Invalid format";
            continue;
        }
        
        if (!empty($subnet) && !empty($location)) {
            if (local_subnet_validate_subnet($subnet)) {
                $mappings[$subnet] = $location;
            } else {
                $errors[] = "Line " . ($line_num + 1) . ": Invalid subnet format: $subnet";
            }
        }
    }
    
    return $mappings;
}

/**
 * Validate subnet format (CIDR notation or single IP)
 * 
 * @param string $subnet Subnet to validate
 * @return bool True if valid
 */
function local_subnet_validate_subnet($subnet) {
    // Single IP
    if (filter_var($subnet, FILTER_VALIDATE_IP)) {
        return true;
    }
    
    // CIDR notation
    if (preg_match('/^(\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $subnet)) {
        list($ip, $mask) = explode('/', $subnet);
        if (filter_var($ip, FILTER_VALIDATE_IP) && $mask >= 0 && $mask <= 32) {
            return true;
        }
    }
    
    return false;
}

/**
 * Save subnet mappings to config file
 * 
 * @param array $mappings Subnet mappings
 */
function local_subnet_save_mappings($mappings) {
    $config_file = YOURLS_USERDIR . '/config.php';
    
    if (!is_writable($config_file)) {
        return false;
    }
    
    $config_content = file_get_contents($config_file);
    
    // Remove existing local_subnet_mappings if present (handle both array() and [] syntax)
    $config_content = preg_replace(
        '/\/\*\* Local Subnet Mappings.*?\*\/\s*\$local_subnet_mappings\s*=\s*array\([^)]*\);\s*/s',
        '',
        $config_content
    );
    $config_content = preg_replace(
        '/\/\*\* Local Subnet Mappings.*?\*\/\s*\$local_subnet_mappings\s*=\s*\[[^\]]*\];\s*/s',
        '',
        $config_content
    );
    
    // Add new mappings
    $mappings_php = var_export($mappings, true);
    $mappings_code = "\n/** Local Subnet Mappings for IP Geolocation\n * Format: 'subnet/cidr' => 'Location Name'\n * Example: '192.168.1.0/24' => 'Office Network'\n */\n\$local_subnet_mappings = $mappings_php;\n";
    
    // Insert before the closing PHP tag or at the end
    if (strpos($config_content, '?>') !== false) {
        $config_content = str_replace('?>', $mappings_code . "\n?>", $config_content);
    } else {
        $config_content .= $mappings_code;
    }
    
    return file_put_contents($config_file, $config_content) !== false;
}
