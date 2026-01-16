<?php
/*
Plugin Name: Interactive Location Map
Plugin URI: https://github.com/YOURLS/YOURLS
Description: Adds an interactive Leaflet map alongside the Google Charts map with tabbed interface for Top 5, Overall, and Interactive views.
Version: 1.0.0
Author: Robbie De Wet
*/

// No direct call
if (!defined('YOURLS_ABSPATH')) die();

// Store keyword from action hook
$interactive_map_keyword = '';

/**
 * Capture keyword from location tab action
 */
yourls_add_action('pre_yourls_info_location', 'interactive_location_map_capture_keyword');
function interactive_location_map_capture_keyword($keyword) {
    global $interactive_map_keyword;
    $interactive_map_keyword = $keyword;
}

/**
 * Add tabs before the location table
 */
yourls_add_action('pre_yourls_info_location', 'interactive_location_map_add_tabs');
function interactive_location_map_add_tabs($keyword) {
    global $interactive_map_keyword;
    
    // Try to get keyword
    $current_keyword = '';
    if (!empty($interactive_map_keyword)) {
        $current_keyword = $interactive_map_keyword;
    } elseif (!empty($keyword)) {
        $current_keyword = $keyword;
    }
    
    // Add CSS for tabs
    echo '<style>
        .stats-tabs {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .stats-tabs .tab {
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px 2px;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid transparent;
        }
        .stats-tabs .tab:hover {
            color: #1f669c;
        }
        .stats-tabs .tab.active {
            color: #1f669c;
            border-bottom: 2px solid #1f669c;
        }
        .stats-tabs .divider {
            color: #aaa;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        #stat_tab_location .tab-content[data-tab="interactive"] {
            width: 100%;
            max-width: 100%;
        }
        #stat_tab_location .tab-content[data-tab="interactive"] #interactive_map_stat_tab_location_map {
            width: 100%;
            min-width: 800px;
            height: 500px;
        }
        @media (max-width: 600px) {
            .stats-tabs {
                flex-wrap: wrap;
            }
        }
    </style>';
    
    // Add tabs
    echo '<div class="stats-tabs" id="stats_tabs_location">';
    echo '<button class="tab active" data-tab="top5">Top 5</button>';
    echo '<span class="divider">|</span>';
    echo '<button class="tab" data-tab="overall">Overall</button>';
    if (!empty($current_keyword)) {
        echo '<span class="divider">|</span>';
        echo '<button class="tab" data-tab="interactive">Interactive</button>';
    }
    echo '</div>';
}

/**
 * Filter to add interactive Leaflet map alongside the default Google Charts map
 */
yourls_add_filter('stats_countries_map', 'interactive_location_map_add', 10, 4);

function interactive_location_map_add($map, $countries, $options, $id) {
    // Get keyword from captured global variable
    global $interactive_map_keyword, $keyword;
    
    // Try multiple sources for keyword
    $current_keyword = '';
    if (!empty($interactive_map_keyword)) {
        $current_keyword = $interactive_map_keyword;
    } elseif (!empty($keyword)) {
        $current_keyword = $keyword;
    } elseif (defined('YOURLS_INFOS')) {
        // Try to extract from current URL
        $request = yourls_get_request();
        if (preg_match('/^([a-zA-Z0-9_-]+)(?:\+|\.qr)?$/', $request[0], $matches)) {
            $current_keyword = $matches[1];
        }
    }
    
    // Return the map as-is (no wrapper yet - JavaScript will wrap it)
    $combined_html = $map;
    
    // Add interactive map if we have keyword
    $map_id = '';
    $js_map_id = '';
    if (!empty($current_keyword)) {
        // Get location data with IP addresses for more detailed mapping
        $location_data = interactive_location_map_get_detailed_data($current_keyword);
        
        // Generate interactive map HTML
        $map_id = 'interactive_map_' . $id;
        $js_map_id = $map_id;
        $interactive_map_html = interactive_location_map_generate_html($map_id, $location_data, $countries);
        
        $combined_html .= '<div class="tab-content" data-tab="interactive" id="tab_interactive_' . $id . '" style="display: none;">';
        $combined_html .= '<h3>Interactive Detail Map</h3>';
        $combined_html .= $interactive_map_html;
        $combined_html .= '</div>';
    }
    
    // Add JavaScript for tab switching
    $combined_html .= '<script>
    (function() {
        var tabsId = "stats_tabs_location";
        var top5TabId = "tab_top5_' . $id . '";
        var mapId = "' . $js_map_id . '";
        
        // Find the Top 5 countries section and wrap it in a tab-content div
        function setupTop5Tab() {
            var locationTab = document.querySelector("#stat_tab_location");
            if (!locationTab) return;
            
            var table = locationTab.querySelector("table");
            if (!table) return;
            
            var firstCell = table.querySelector("td:first-child");
            if (!firstCell) return;
            
            if (firstCell.querySelector(".tab-content[data-tab=\"top5\"]")) return;
            
            var wrapper = document.createElement("div");
            wrapper.className = "tab-content active";
            wrapper.setAttribute("data-tab", "top5");
            wrapper.id = top5TabId;
            
            while (firstCell.firstChild) {
                wrapper.appendChild(firstCell.firstChild);
            }
            
            firstCell.appendChild(wrapper);
        }
        
        // Wrap the Overall tab content (h3 + map)
        function setupOverallTab() {
            var locationTab = document.querySelector("#stat_tab_location");
            if (!locationTab) return;
            
            var table = locationTab.querySelector("table");
            if (!table) return;
            
            var secondCell = table.querySelector("td:last-child");
            if (!secondCell) return;
            
            // Check if already wrapped
            if (secondCell.querySelector(\'.tab-content[data-tab="overall"]\')) {
                // Already wrapped, make sure it\'s hidden initially (Top 5 is default)
                var overallWrapper = secondCell.querySelector(\'.tab-content[data-tab="overall"]\');
                if (overallWrapper) {
                    overallWrapper.style.display = "none";
                }
                return;
            }
            
            var wrapper = document.createElement("div");
            wrapper.className = "tab-content";
            wrapper.setAttribute("data-tab", "overall");
            wrapper.id = "tab_overall_' . $id . '";
            wrapper.style.display = "none"; // Start hidden (Top 5 is default)
            
            // Move all content from second cell into wrapper
            while (secondCell.firstChild) {
                wrapper.appendChild(secondCell.firstChild);
            }
            
            secondCell.appendChild(wrapper);
        }
        
        // Initialize tabs
        function initTabs() {
            setupTop5Tab();
            setupOverallTab();
            
            var tabs = document.querySelectorAll("#" + tabsId + " .tab");
            tabs.forEach(function(tab) {
                tab.addEventListener("click", function() {
                    document.querySelectorAll("#" + tabsId + " .tab").forEach(function(t) {
                        t.classList.remove("active");
                    });
                    
                    tab.classList.add("active");
                    
                    var selected = tab.getAttribute("data-tab");
                    
                    // Hide all tab-content divs first
                    document.querySelectorAll("#stat_tab_location .tab-content").forEach(function(c) {
                        c.classList.remove("active");
                        c.style.display = "none";
                    });
                    
                    // Handle table cell visibility FIRST (before showing content)
                    var table = document.querySelector("#stat_tab_location table");
                    if (table) {
                        var firstCell = table.querySelector("td:first-child");
                        var secondCell = table.querySelector("td:last-child");
                        
                        if (selected === "top5") {
                            if (firstCell) firstCell.style.display = "table-cell";
                            if (secondCell) secondCell.style.display = "none";
                        } else if (selected === "overall") {
                            if (firstCell) firstCell.style.display = "none";
                            if (secondCell) secondCell.style.display = "table-cell";
                        } else if (selected === "interactive") {
                            if (firstCell) firstCell.style.display = "none";
                            if (secondCell) secondCell.style.display = "none";
                        }
                    }
                    
                    // Show selected tab content AFTER hiding table cells
                    document.querySelectorAll("#stat_tab_location .tab-content[data-tab=\"" + selected + "\"]").forEach(function(c) {
                        c.classList.add("active");
                        c.style.display = "block";
                    });
                    
                    // Invalidate Leaflet map size if showing interactive map
                    if (selected === "interactive" && window.L && window.L.maps && mapId) {
                        setTimeout(function() {
                            if (window.L.maps[mapId]) {
                                window.L.maps[mapId].invalidateSize();
                            }
                        }, 100);
                    }
                });
            });
        }
        
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", function() {
                setTimeout(initTabs, 500);
            });
        } else {
            setTimeout(initTabs, 500);
        }
    })();
    </script>';
    
    return $combined_html;
}

/**
 * Get detailed location data including city-level information
 */
function interactive_location_map_get_detailed_data($keyword) {
    global $ydb;
    $table_log = YOURLS_DB_TABLE_LOG;
    $locations = array();
    
    // Get IP addresses and their locations
    $sql = "SELECT ip_address, country_code, COUNT(*) as count 
            FROM `$table_log` 
            WHERE shorturl = :keyword 
            GROUP BY ip_address, country_code";
    
    $rows = $ydb->fetchObjects($sql, array('keyword' => $keyword));
    
    foreach ($rows as $row) {
        $ip = $row->ip_address;
        $country_code = $row->country_code;
        $count = $row->count;
        
        // Get city-level info from ipinfo.io
        $ip_info = interactive_location_map_get_ip_info($ip);
        
        // Get coordinates for the location
        $coords = interactive_location_map_get_coordinates($ip_info, $country_code);
        
        if ($coords) {
            $location_name = yourls_geo_countrycode_to_countryname($country_code);
            if (isset($ip_info['city']) && !empty($ip_info['city'])) {
                $location_name = $ip_info['city'] . ', ' . $location_name;
            }
            
            $locations[] = array(
                'ip' => $ip,
                'country_code' => $country_code,
                'location_name' => $location_name,
                'count' => $count,
                'lat' => $coords['lat'],
                'lng' => $coords['lng'],
                'city' => isset($ip_info['city']) ? $ip_info['city'] : '',
                'region' => isset($ip_info['region']) ? $ip_info['region'] : '',
            );
        }
    }
    
    return $locations;
}

/**
 * Get IP information from ipinfo.io
 */
function interactive_location_map_get_ip_info($ip) {
    // Cache results to avoid too many API calls
    static $cache = array();
    if (isset($cache[$ip])) {
        return $cache[$ip];
    }
    
    // Skip API call for local IPs - return empty but cache it
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        $cache[$ip] = array();
        return array();
    }
    
    $url = "https://ipinfo.io/{$ip}/json";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    $response = @curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true) ?: array();
    $cache[$ip] = $result;
    return $result;
}

/**
 * Get coordinates for a location
 */
function interactive_location_map_get_coordinates($ip_info, $country_code) {
    // Check for custom location mappings (Basel, etc.)
    $custom_locations = interactive_location_map_get_custom_locations();
    
    // Check if this is a custom location (from local subnet mapping)
    $location_name = yourls_geo_countrycode_to_countryname($country_code);
    foreach ($custom_locations as $name => $coords) {
        if (stripos($location_name, $name) !== false || 
            (isset($ip_info['city']) && stripos($ip_info['city'], $name) !== false)) {
            return $coords;
        }
    }
    
    // Try to get coordinates from ipinfo.io
    if (isset($ip_info['loc']) && !empty($ip_info['loc'])) {
        list($lat, $lng) = explode(',', $ip_info['loc']);
        return array('lat' => floatval($lat), 'lng' => floatval($lng));
    }
    
    // Fallback to country center coordinates
    $country_coords = interactive_location_map_get_country_center($country_code);
    if ($country_coords) {
        return $country_coords;
    }
    
    return null;
}

/**
 * Get custom location coordinates (Basel, offices, etc.)
 * Can be configured in config.php
 */
function interactive_location_map_get_custom_locations() {
    // Default: Basel area
    $default_locations = array(
        'Basel' => array('lat' => 47.5596, 'lng' => 7.5886),
        'Docker Network' => array('lat' => 47.5596, 'lng' => 7.5886),
        'Localhost' => array('lat' => 47.5596, 'lng' => 7.5886),
    );
    
    // Allow override from config.php
    if (defined('INTERACTIVE_MAP_CUSTOM_LOCATIONS') && is_array(INTERACTIVE_MAP_CUSTOM_LOCATIONS)) {
        return array_merge($default_locations, INTERACTIVE_MAP_CUSTOM_LOCATIONS);
    }
    
    return $default_locations;
}

/**
 * Get country center coordinates as fallback
 */
function interactive_location_map_get_country_center($country_code) {
    // Common country centers (can be expanded)
    $country_centers = array(
        'CH' => array('lat' => 46.8182, 'lng' => 8.2275),
        'DE' => array('lat' => 51.1657, 'lng' => 10.4515),
        'FR' => array('lat' => 46.2276, 'lng' => 2.2137),
        'US' => array('lat' => 37.0902, 'lng' => -95.7129),
        'LO' => array('lat' => 47.5596, 'lng' => 7.5886),
        'DN' => array('lat' => 47.5596, 'lng' => 7.5886),
    );
    
    return isset($country_centers[$country_code]) ? $country_centers[$country_code] : null;
}

/**
 * Generate interactive map HTML with Leaflet
 */
function interactive_location_map_generate_html($map_id, $locations, $countries) {
    // Group locations by coordinates to avoid overlapping markers
    $grouped_locations = array();
    foreach ($locations as $loc) {
        $key = round($loc['lat'], 2) . ',' . round($loc['lng'], 2);
        if (!isset($grouped_locations[$key])) {
            $grouped_locations[$key] = array(
                'lat' => $loc['lat'],
                'lng' => $loc['lng'],
                'locations' => array(),
                'total_count' => 0
            );
        }
        $grouped_locations[$key]['locations'][] = $loc;
        $grouped_locations[$key]['total_count'] += $loc['count'];
    }
    
    // Calculate center and bounds
    $center = interactive_location_map_calculate_center($grouped_locations);
    
    // Generate HTML - map container with wider dimensions
    $html = '<div id="' . $map_id . '" style="width: 100%; min-width: 800px; height: 500px; border: 1px solid #ccc;"></div>';
    
    // Add Leaflet CSS and JS
    $html .= '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />';
    $html .= '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';
    
    // Add marker clustering for better performance
    $html .= '<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />';
    $html .= '<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />';
    $html .= '<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>';
    
    // Generate JavaScript
    $html .= '<script>
    (function() {
        var map = L.map("' . $map_id . '").setView([' . $center['lat'] . ', ' . $center['lng'] . '], ' . $center['zoom'] . ');
        
        // Store map reference for resize handling
        if (!window.L) window.L = {};
        if (!window.L.maps) window.L.maps = {};
        window.L.maps["' . $map_id . '"] = map;
        
        // Add OpenStreetMap tiles
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: "&copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a> contributors",
            maxZoom: 19
        }).addTo(map);
        
        // Create marker cluster group
        var markers = L.markerClusterGroup({
            maxClusterRadius: 50,
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: true,
            zoomToBoundsOnClick: true
        });
        
        var locations = ' . json_encode(array_values($grouped_locations)) . ';
        
        locations.forEach(function(group) {
            var popupContent = "<div style=\'min-width: 250px; max-width: 400px;\'><strong>Location Details</strong><br/>";
            popupContent += "<strong>Total Clicks: " + group.total_count + "</strong><br/><br/>";
            
            group.locations.forEach(function(loc) {
                popupContent += "<strong>" + (loc.city || loc.location_name) + "</strong><br/>";
                popupContent += "IP: " + loc.ip + "<br/>";
                popupContent += "Clicks: " + loc.count + "<br/>";
                if (loc.region) {
                    popupContent += "Region: " + loc.region + "<br/>";
                }
                popupContent += "<hr/>";
            });
            
            popupContent += "</div>";
            
            var marker = L.marker([group.lat, group.lng]);
            marker.bindPopup(popupContent);
            markers.addLayer(marker);
        });
        
        map.addLayer(markers);
        
        // Fit bounds to show all markers
        if (locations.length > 0) {
            var bounds = markers.getBounds();
            if (bounds.isValid()) {
                map.fitBounds(bounds, {padding: [20, 20]});
            }
        }
    })();
    </script>';
    
    return $html;
}

/**
 * Calculate map center and zoom level
 */
function interactive_location_map_calculate_center($locations) {
    if (empty($locations)) {
        // Default to Basel
        return array('lat' => 47.5596, 'lng' => 7.5886, 'zoom' => 10);
    }
    
    $lats = array();
    $lngs = array();
    
    foreach ($locations as $loc) {
        $lats[] = $loc['lat'];
        $lngs[] = $loc['lng'];
    }
    
    $center_lat = (min($lats) + max($lats)) / 2;
    $center_lng = (min($lngs) + max($lngs)) / 2;
    
    // Calculate zoom based on spread
    $lat_diff = max($lats) - min($lats);
    $lng_diff = max($lngs) - min($lngs);
    $max_diff = max($lat_diff, $lng_diff);
    
    if ($max_diff < 0.01) {
        $zoom = 13; // City level
    } elseif ($max_diff < 0.1) {
        $zoom = 10; // Region level
    } elseif ($max_diff < 1) {
        $zoom = 7; // Country level
    } else {
        $zoom = 4; // Continental level
    }
    
    return array('lat' => $center_lat, 'lng' => $center_lng, 'zoom' => $zoom);
}
