<?php
/*
Plugin Name: Device Details
Plugin URI: https://github.com/SachinSAgrawal/YOURLS-Device-Details
Description: Parses user-agent using a custom library to display information about IP and device
Version: 1.2
Author: Sachin Agrawal
Author URI: https://sachinagrawal.me
*/

// Load the user-agent parsing library WhichBrowser
require_once YOURLS_ABSPATH . '/includes/vendor/autoload.php';

yourls_add_action('post_yourls_info_stats', 'ip_detail_page');

function get_ip_info($ip) {
    $url = "https://ipinfo.io/{$ip}/json";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function get_timezone_offset($timezone) {
    $timezone_object = new DateTimeZone($timezone);
    $datetime = new DateTime("now", $timezone_object);
    $offset = $timezone_object->getOffset($datetime);
    return $offset / 60; // Convert seconds to minutes
}

function timezone_offset_to_gmt_offset($timezone_offset) {
    $timezone_offset = intval($timezone_offset);
    $hours = floor($timezone_offset / 60);
    $offset = ($timezone_offset < 0 ? '-' : '+') . abs($hours);
    return 'GMT' . $offset;
}

function ip_detail_page($shorturl) {
    $nonce = yourls_create_nonce('ip');
    global $ydb;
    $base  = YOURLS_SITE;
    $table_url = YOURLS_DB_TABLE_URL;
    $table_log = YOURLS_DB_TABLE_LOG;
    $outdata   = '';

    // Pagination settings
    $per_page = 10;
    $page = isset($_GET['device_page']) ? max(1, intval($_GET['device_page'])) : 1;
    $offset = ($page - 1) * $per_page;

    // Get total count of records
    $total_count = $ydb->fetchValue("SELECT COUNT(*) FROM `$table_log` WHERE shorturl=:keyword", array('keyword' => $shorturl[0]));
    $total_pages = ceil($total_count / $per_page);

    // Get paginated query (LIMIT and OFFSET must be integers, not placeholders)
    $query = $ydb->fetchObjects("SELECT * FROM `$table_log` WHERE shorturl=:keyword ORDER BY click_id DESC LIMIT " . intval($per_page) . " OFFSET " . intval($offset), array(
        'keyword' => $shorturl[0]
    ));

    if ($query) {
        foreach ($query as $query_result) {
            // Check if this is the current user's IP (for informational purposes only)
            $current_user_ip = yourls_get_IP();
            $is_current_user = ($query_result->ip_address == $current_user_ip);
            $user_indicator = $is_current_user ? "<br><i>this is your ip</i>" : "";

            // Parse user agent
            $ua = $query_result->user_agent;
            $wbresult = new WhichBrowser\Parser($ua);

            // Get additional IP information from ipinfo.io
            $ip_info = get_ip_info($query_result->ip_address);

            // Calculate local time
            $click_time_utc = new DateTime($query_result->click_time, new DateTimeZone('UTC'));
            $timezone_offset = isset($ip_info['timezone']) ? get_timezone_offset($ip_info['timezone']) : 0;
            $click_time_utc->modify($timezone_offset . ' minutes');
            $local_time = $click_time_utc->format('Y-m-d H:i:s');

            // Convert timezone offset to GMT offset
            $gmt_offset = timezone_offset_to_gmt_offset($timezone_offset);
            
            // Use consistent styling - no background color, or use CSS class
            $outdata .= '<tr><td>'.$query_result->click_time.'</td>
                        <td>'.$local_time.'</td>
                        <td>'.$gmt_offset.'</td>
						<td>'.$query_result->country_code.'</td>
						<td>'.(isset($ip_info['city']) ? $ip_info['city'] : '').'</td>
						<td><a href="https://who.is/whois-ip/ip-address/'.$query_result->ip_address.'" target="blank">'.$query_result->ip_address.'</a>'.$user_indicator.'</td>
						<td>'.$ua.'</td>
						<td>'.($wbresult->browser->name ?? '').' '.($wbresult->browser->version->value ?? '').'</td>
						<td>'.($wbresult->os->name ?? '').' '.($wbresult->os->version->value ?? '').'</td>
						<td>'.($wbresult->device->model ?? '').'</td>
						<td>'.($wbresult->device->manufacturer ?? '').'</td>
						<td>'.($wbresult->device->type ?? '').'</td>
						<td>'.($wbresult->engine->name ?? '').'</td>
						<td>'.$query_result->referrer.'</td>
						</tr>';
        }

        echo '<table border="1" cellpadding="5" style="margin-top:25px; width:100%; border-collapse: collapse;"><thead><tr><td width="80">Timestamp</td><td>Local Time</td><td>Timezone</td><td>Country</td><td>City</td>
				<td>IP Address</td><td>User Agent</td><td>Browser Version</td><td>OS Version</td><td>Device Model</td>
				<td>Device Vendor</td><td>Device Type</td><td>Engine</td><td>Referrer</td></tr></thead><tbody>' . $outdata . "</tbody></table>";

        // Pagination controls
        if ($total_pages > 1) {
            // Get current URL and preserve existing query parameters
            $current_url_full = yourls_get_yourls_site() . '/' . $shorturl[0] . '+';
            if (!empty($_SERVER['QUERY_STRING'])) {
                parse_str($_SERVER['QUERY_STRING'], $query_params);
                unset($query_params['device_page']); // Remove old page param
            } else {
                $query_params = array();
            }
            
            echo '<div style="margin-top: 15px; text-align: center;">';
            echo '<p style="margin: 10px 0;">Showing ' . ($offset + 1) . '-' . min($offset + $per_page, $total_count) . ' of ' . $total_count . ' clicks</p>';
            
            echo '<div style="display: inline-block;">';
            
            // Previous button
            if ($page > 1) {
                $query_params['device_page'] = $page - 1;
                $prev_url = $current_url_full . (empty($query_params) ? '' : '?' . http_build_query($query_params));
                echo '<a href="' . htmlspecialchars($prev_url) . '" style="padding: 5px 10px; margin: 0 5px; text-decoration: none; border: 1px solid #ccc; background: #f5f5f5;">&laquo; Previous</a>';
            } else {
                echo '<span style="padding: 5px 10px; margin: 0 5px; color: #999; border: 1px solid #ddd; background: #f9f9f9;">&laquo; Previous</span>';
            }
            
            // Page numbers (show up to 5 pages around current)
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1) {
                $query_params['device_page'] = 1;
                $first_url = $current_url_full . '?' . http_build_query($query_params);
                echo '<a href="' . htmlspecialchars($first_url) . '" style="padding: 5px 10px; margin: 0 2px; text-decoration: none; border: 1px solid #ccc; background: #f5f5f5;">1</a>';
                if ($start_page > 2) {
                    echo '<span style="padding: 5px; margin: 0 2px;">...</span>';
                }
            }
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $page) {
                    echo '<span style="padding: 5px 10px; margin: 0 2px; border: 1px solid #007bff; background: #007bff; color: white; font-weight: bold;">' . $i . '</span>';
                } else {
                    $query_params['device_page'] = $i;
                    $page_url = $current_url_full . '?' . http_build_query($query_params);
                    echo '<a href="' . htmlspecialchars($page_url) . '" style="padding: 5px 10px; margin: 0 2px; text-decoration: none; border: 1px solid #ccc; background: #f5f5f5;">' . $i . '</a>';
                }
            }
            
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<span style="padding: 5px; margin: 0 2px;">...</span>';
                }
                $query_params['device_page'] = $total_pages;
                $last_url = $current_url_full . '?' . http_build_query($query_params);
                echo '<a href="' . htmlspecialchars($last_url) . '" style="padding: 5px 10px; margin: 0 2px; text-decoration: none; border: 1px solid #ccc; background: #f5f5f5;">' . $total_pages . '</a>';
            }
            
            // Next button
            if ($page < $total_pages) {
                $query_params['device_page'] = $page + 1;
                $next_url = $current_url_full . '?' . http_build_query($query_params);
                echo '<a href="' . htmlspecialchars($next_url) . '" style="padding: 5px 10px; margin: 0 5px; text-decoration: none; border: 1px solid #ccc; background: #f5f5f5;">Next &raquo;</a>';
            } else {
                echo '<span style="padding: 5px 10px; margin: 0 5px; color: #999; border: 1px solid #ddd; background: #f9f9f9;">Next &raquo;</span>';
            }
            
            echo '</div>';
            echo '</div>';
        }
        
        echo "<br>\n\r";
    }
}
