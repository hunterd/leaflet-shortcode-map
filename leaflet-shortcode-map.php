<?php
/**
 * Plugin Name: Leaflet Shortcode Map
 * Description: Adds a "Map" CPT with admin geo UI, infinite marker repeater (name, subtitle, address, Dashicon or custom image icon, color), bulk import, global icon/color settings, and a [ma_carte id="..."] shortcode.
 * Version:     1.5
 * Author:      David Mussard
 * Text Domain: leaflet-shortcode-map
 * Domain Path: /languages
 * Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=mussarddavid@hotmail.com&item_name=Donation+for+Leaflet+Shortcode+Map&currency_code=EUR
 */

if ( ! defined('ABSPATH') ) exit;

/* 1) CPT "Map" */
add_action('init', function(){
    register_post_type('lsm_map', [
        'labels'      => [
            'name'          => __('Maps', 'leaflet-shortcode-map'),
            'singular_name' => __('Map', 'leaflet-shortcode-map'),
            'add_new_item'  => __('Add New Map', 'leaflet-shortcode-map'),
            'edit_item'     => __('Edit Map', 'leaflet-shortcode-map'),
            'all_items'     => __('All Maps', 'leaflet-shortcode-map'),
        ],
        'public'      => false, // Keep admin-only
        'show_ui'     => true,
        'menu_icon'   => 'dashicons-location-alt',
        'supports'    => ['title'],
        'show_in_rest' => true, // Optional: Enable Gutenberg support if needed later
    ]);
});

/* 2) Enqueue admin JS/CSS + colorpicker + media uploader */
add_action('admin_enqueue_scripts', function($hook){
    global $post;
    if ( in_array($hook, ['post-new.php','post.php']) && isset($post->post_type) && $post->post_type === 'lsm_map' ) {
        wp_enqueue_media(); // For media uploader
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        // Enqueue admin script and localize strings for JS
        wp_enqueue_script('lsm-admin-js', plugins_url('admin.js', __FILE__), ['jquery','wp-color-picker'], '1.5', true);
        wp_localize_script('lsm-admin-js','lsm_params',[ // Changed object name to lsm_params
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('lsm_geocode'),
            'i18n'     => [ // Translatable strings for JS
                'geocoding'         => __('Geocoding...', 'leaflet-shortcode-map'),
                'geocode'           => __('Geocode', 'leaflet-shortcode-map'),
                'geocode_fail'      => __('Geocoding failed:', 'leaflet-shortcode-map'),
                'cannot_add'        => __('Cannot add marker: unable to clone existing marker.', 'leaflet-shortcode-map'),
                'error_cannot_clone'=> __('Error: Cannot find a marker to clone. Please reload the page.', 'leaflet-shortcode-map'),
                'last_marker_cleared'=> __('The last marker cannot be deleted. Its fields have been cleared instead.', 'leaflet-shortcode-map'),
                'choose_icon'       => __('Choose a Custom Icon', 'leaflet-shortcode-map'),
                'choose_global_icon'=> __('Choose a Global Custom Icon', 'leaflet-shortcode-map'),
                'use_image'         => __('Use This Image', 'leaflet-shortcode-map'),
                'confirm_remove'    => __('Are you sure you want to remove this marker?', 'leaflet-shortcode-map'), // Added confirmation
                'bulk_import_confirm' => __('This will replace all current markers. Are you sure?', 'leaflet-shortcode-map'), // Added confirmation
            ]
        ]);

        // Enqueue admin styles
        wp_enqueue_style('lsm-admin-css', plugins_url('admin.css', __FILE__), [], '1.5');

        // Load plugin text domain for translations
        load_plugin_textdomain('leaflet-shortcode-map', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
});

/* 3) Meta-box Centre + Bulk import + Global + Repeater Marqueurs */
add_action('add_meta_boxes', function(){
    add_meta_box(
        'lsm_map_settings_meta', // Changed ID slightly
        __('Map Settings', 'leaflet-shortcode-map'),
        'lsm_render_map_settings_meta_box', // Changed callback function name
        'lsm_map',
        'normal',
        'high'
    );
});

// Callback function to render the meta box content
function lsm_render_map_settings_meta_box($post){
    wp_nonce_field('lsm_save_map_meta', 'lsm_meta_nonce'); // Changed action name slightly

    // Get existing meta values
    $center_addr = get_post_meta($post->ID, '_lsm_center_addr', true);
    $center_lat  = get_post_meta($post->ID, '_lsm_center_lat', true);
    $center_lon  = get_post_meta($post->ID, '_lsm_center_lon', true);
    $center_zoom = get_post_meta($post->ID, '_lsm_center_zoom', true) ?: 9; // Default zoom 9

    // Available Dashicons list
    $icon_list = [
        ''                       => __('-- Select --', 'leaflet-shortcode-map'),
        'dashicons-location'     => __('Location Pin', 'leaflet-shortcode-map'),
        'dashicons-location-alt' => __('Location Alt', 'leaflet-shortcode-map'),
        'dashicons-admin-site'   => __('Site', 'leaflet-shortcode-map'),
        'dashicons-admin-home'   => __('Home', 'leaflet-shortcode-map'),
        'dashicons-format-status'=> __('Status', 'leaflet-shortcode-map'),
        'dashicons-marker'       => __('Marker', 'leaflet-shortcode-map'),
        // Add more if needed
    ];

    // Get global icon/color (if previously saved, though not standard meta)
    // These are UI controls, not saved meta fields directly for global settings.
    $global_icon = ''; // Default empty
    $global_color = '#d00'; // Default red
    $global_custom_icon_id = ''; // Default empty

    ?>
    <div class="lsm-meta-section lsm-centre">
      <h4><?php esc_html_e('Map Center', 'leaflet-shortcode-map'); ?></h4>
      <p>
        <input type="text"
               id="lsm_center_addr"
               name="lsm_center_addr"
               placeholder="<?php esc_attr_e('Address or Place (e.g., Paris, France)', 'leaflet-shortcode-map'); ?>"
               value="<?php echo esc_attr($center_addr); ?>"
               style="width:70%;">
        <button type="button" class="button lsm-geocode"><?php esc_html_e('Geocode', 'leaflet-shortcode-map'); ?></button>
      </p>
      <p>
        <label for="lsm_center_lat"><?php esc_html_e('Lat:', 'leaflet-shortcode-map'); ?></label>
        <input type="text"
               id="lsm_center_lat"
               name="lsm_center_lat"
               placeholder="<?php esc_attr_e('Latitude', 'leaflet-shortcode-map'); ?>"
               value="<?php echo esc_attr($center_lat); ?>"
               style="width:20%;">
        <label for="lsm_center_lon"><?php esc_html_e('Lon:', 'leaflet-shortcode-map'); ?></label>
        <input type="text"
               id="lsm_center_lon"
               name="lsm_center_lon"
               placeholder="<?php esc_attr_e('Longitude', 'leaflet-shortcode-map'); ?>"
               value="<?php echo esc_attr($center_lon); ?>"
               style="width:20%;">
        <label for="lsm_center_zoom"><?php esc_html_e('Zoom:', 'leaflet-shortcode-map'); ?></label>
        <input type="number"
               id="lsm_center_zoom"
               name="lsm_center_zoom"
               min="1" max="18"
               value="<?php echo esc_attr($center_zoom); ?>"
               style="width:10%;">
      </p>
    </div>

    <div class="lsm-meta-section lsm-bulk">
      <h4><?php esc_html_e('Bulk Import Markers', 'leaflet-shortcode-map'); ?></h4>
      <p><?php esc_html_e('One line per marker – format:', 'leaflet-shortcode-map'); ?><br>
        <code><?php esc_html_e('Name|Subtitle|Address|Lat|Lon|DashiconClass|ColorHex', 'leaflet-shortcode-map'); ?></code>
      </p>
      <textarea id="lsm_bulk_markers"
                placeholder="<?php esc_attr_e('Marker A|Subtitle A|1 Infinite Loop, Cupertino|37.3318|-122.0312|dashicons-location-alt|#ff0000', 'leaflet-shortcode-map'); ?>"
                rows="5"
                style="width:100%;"></textarea>
      <p>
        <button type="button" id="lsm_import_bulk" class="button button-primary">
          <?php esc_html_e('Import Markers', 'leaflet-shortcode-map'); ?>
        </button>
         <small style="display: block; margin-top: 5px;"><?php esc_html_e('Warning: This will replace all current markers.', 'leaflet-shortcode-map'); ?></small>
      </p>
    </div>

    <div class="lsm-meta-section lsm-global">
      <h4><?php esc_html_e('Global Icon & Color', 'leaflet-shortcode-map'); ?></h4>
      <p>
        <label for="lsm_global_icon"><?php esc_html_e('Global Dashicon:', 'leaflet-shortcode-map'); ?></label>
        <select id="lsm_global_icon">
          <?php foreach($icon_list as $cls => $label): ?>
          <option value="<?php echo esc_attr($cls);?>"><?php echo esc_html($label);?></option>
          <?php endforeach;?>
        </select>
        <span id="lsm_global_preview" class="dashicons" style="vertical-align:middle; margin-left:8px;"></span>
      </p>
      <p>
        <label for="lsm_global_color"><?php esc_html_e('Global Color:', 'leaflet-shortcode-map'); ?></label>
        <input type="text" id="lsm_global_color" class="lsm-color-field" value="<?php echo esc_attr($global_color); ?>">
      </p>
      <p>
        <label for="lsm_upload_global_icon"><?php esc_html_e('Global Custom Icon:', 'leaflet-shortcode-map'); ?></label>
        <input type="hidden" id="lsm_global_custom_icon_id" value="<?php echo esc_attr($global_custom_icon_id); ?>">
        <button type="button" id="lsm_upload_global_icon" class="button"><?php esc_html_e('Choose Global Image', 'leaflet-shortcode-map'); ?></button>
        <img id="lsm_global_custom_icon_preview" src="<?php echo $global_custom_icon_id ? esc_url(wp_get_attachment_image_url($global_custom_icon_id, 'thumbnail')) : ''; ?>" style="max-height:30px; vertical-align:middle; margin-left:8px; <?php echo $global_custom_icon_id ? '' : 'display: none;'; ?>">
      </p>
      <p>
        <button type="button" id="lsm_apply_all" class="button button-primary">
          <?php esc_html_e('Apply to All Markers', 'leaflet-shortcode-map'); ?>
        </button>
        <small style="display: block; margin-top: 5px;"><?php esc_html_e('Priority is given to the custom icon if set.', 'leaflet-shortcode-map'); ?></small>
      </p>
    </div>

    <?php
    // Repeater markers
    $markers = get_post_meta($post->ID, '_lsm_markers', true);
    if (!is_array($markers) || empty($markers)) {
        // Initialize with one empty marker row
        $markers = [[
            'name' => '', 'subtitle' => '', 'addr' => '', 'lat' => '', 'lon' => '',
            'icon' => '', 'color' => '#d00', 'custom_icon_id' => 0
        ]];
    }
    ?>
    <div class="lsm-meta-section lsm-markers">
      <h4><?php esc_html_e('Markers', 'leaflet-shortcode-map'); ?></h4>
      <div id="lsm_markers_list"> <?php // Changed ID slightly ?>
        <?php foreach($markers as $i => $m):
            // Sanitize marker data for display
            $m_name = isset($m['name']) ? esc_attr($m['name']) : '';
            $m_subtitle = isset($m['subtitle']) ? esc_attr($m['subtitle']) : '';
            $m_addr = isset($m['addr']) ? esc_attr($m['addr']) : '';
            $m_lat = isset($m['lat']) ? esc_attr($m['lat']) : '';
            $m_lon = isset($m['lon']) ? esc_attr($m['lon']) : '';
            $m_icon = isset($m['icon']) ? esc_attr($m['icon']) : '';
            $m_color = isset($m['color']) ? esc_attr($m['color']) : '#d00';
            $m_custom_icon_id = isset($m['custom_icon_id']) ? intval($m['custom_icon_id']) : 0;
            $m_custom_icon_url = $m_custom_icon_id ? esc_url(wp_get_attachment_image_url($m_custom_icon_id, 'thumbnail', true)) : ''; // Use thumbnail size
        ?>
        <div class="lsm-marker" data-index="<?php echo $i;?>">
          <span class="lsm-remove-marker" title="<?php esc_attr_e('Remove Marker', 'leaflet-shortcode-map'); ?>">×</span>

          <p>
            <label><?php esc_html_e('Name:', 'leaflet-shortcode-map'); ?></label>
            <input type="text"
                   name="lsm_markers[<?php echo $i;?>][name]"
                   placeholder="<?php esc_attr_e('Marker Title', 'leaflet-shortcode-map'); ?>"
                   value="<?php echo $m_name;?>">
          </p>
          <p>
            <label><?php esc_html_e('Subtitle:', 'leaflet-shortcode-map'); ?></label>
            <input type="text"
                   name="lsm_markers[<?php echo $i;?>][subtitle]"
                   placeholder="<?php esc_attr_e('Optional Subtitle', 'leaflet-shortcode-map'); ?>"
                   value="<?php echo $m_subtitle;?>">
          </p>
          <p>
            <label><?php esc_html_e('Address:', 'leaflet-shortcode-map'); ?></label>
            <input type="text"
                   class="lsm-addr"
                   name="lsm_markers[<?php echo $i;?>][addr]"
                   placeholder="<?php esc_attr_e('Address or GPS Coordinates', 'leaflet-shortcode-map'); ?>"
                   value="<?php echo $m_addr;?>">
            <button type="button" class="button lsm-geocode"><?php esc_html_e('Geocode', 'leaflet-shortcode-map'); ?></button>
          </p>
          <p>
            <label><?php esc_html_e('Lat:', 'leaflet-shortcode-map'); ?></label>
            <input type="text"
                   name="lsm_markers[<?php echo $i;?>][lat]"
                   placeholder="<?php esc_attr_e('Latitude', 'leaflet-shortcode-map'); ?>"
                   value="<?php echo $m_lat;?>"
                   style="width:20%;">
            <label><?php esc_html_e('Lon:', 'leaflet-shortcode-map'); ?></label>
            <input type="text"
                   name="lsm_markers[<?php echo $i;?>][lon]"
                   placeholder="<?php esc_attr_e('Longitude', 'leaflet-shortcode-map'); ?>"
                   value="<?php echo $m_lon;?>"
                   style="width:20%;">
          </p>
          <p>
            <label><?php esc_html_e('Dashicon:', 'leaflet-shortcode-map'); ?></label>
            <select name="lsm_markers[<?php echo $i;?>][icon]">
              <?php foreach($icon_list as $cls => $label): ?>
              <option value="<?php echo esc_attr($cls);?>" <?php selected($m_icon, $cls); ?>>
                <?php echo esc_html($label);?>
              </option>
              <?php endforeach;?>
            </select>
            <span class="lsm-icon-preview dashicons <?php echo $m_icon;?>"
                  style="color:<?php echo $m_color;?>; vertical-align:middle; margin-left:8px;"></span>
          </p>
          <p>
            <label><?php esc_html_e('Color:', 'leaflet-shortcode-map'); ?></label>
            <input type="text"
                   class="lsm-color-field"
                   name="lsm_markers[<?php echo $i;?>][color]"
                   value="<?php echo $m_color;?>">
          </p>
          <p>
            <label><?php esc_html_e('Custom Icon:', 'leaflet-shortcode-map'); ?></label>
            <input type="hidden"
                   name="lsm_markers[<?php echo $i;?>][custom_icon_id]"
                   class="lsm-custom-icon-id"
                   value="<?php echo $m_custom_icon_id;?>">
            <button type="button" class="button lsm-upload-icon"><?php esc_html_e('Choose Image', 'leaflet-shortcode-map'); ?></button>
            <img class="lsm-custom-icon-preview"
                 src="<?php echo $m_custom_icon_url; ?>"
                 style="max-height:30px; vertical-align:middle; margin-left:8px; <?php echo $m_custom_icon_id ? '' : 'display: none;'; ?>">
             <button type="button" class="button lsm-remove-custom-icon" style="<?php echo $m_custom_icon_id ? '' : 'display: none;'; ?> margin-left: 5px;"><?php esc_html_e('Remove', 'leaflet-shortcode-map'); ?></button> <?php // Added Remove button ?>
          </p>
        </div>
        <?php endforeach;?>
      </div>
      <p>
        <button type="button" id="lsm_add_marker" class="button">
          <span class="dashicons dashicons-plus-alt" style="vertical-align: text-bottom;"></span> <?php esc_html_e('Add Marker', 'leaflet-shortcode-map'); ?>
        </button>
      </p>
    </div>
    <?php
}

/* 4) Save Meta Data */
add_action('save_post_lsm_map', function($post_id){
    // Check nonce
    if (!isset($_POST['lsm_meta_nonce']) || !wp_verify_nonce($_POST['lsm_meta_nonce'], 'lsm_save_map_meta')) {
        return;
    }
    // Check if autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save Center data
    update_post_meta($post_id, '_lsm_center_addr', isset($_POST['lsm_center_addr']) ? sanitize_text_field($_POST['lsm_center_addr']) : '');
    update_post_meta($post_id, '_lsm_center_lat', isset($_POST['lsm_center_lat']) ? sanitize_text_field($_POST['lsm_center_lat']) : '');
    update_post_meta($post_id, '_lsm_center_lon', isset($_POST['lsm_center_lon']) ? sanitize_text_field($_POST['lsm_center_lon']) : '');
    update_post_meta($post_id, '_lsm_center_zoom', isset($_POST['lsm_center_zoom']) ? intval($_POST['lsm_center_zoom']) : 9);

    // Save Markers data
    $markers_input = isset($_POST['lsm_markers']) ? (array) $_POST['lsm_markers'] : [];
    $sanitized_markers = [];
    foreach ($markers_input as $marker_data) {
        if (!is_array($marker_data)) continue;

        $name     = isset($marker_data['name']) ? sanitize_text_field($marker_data['name']) : '';
        $subtitle = isset($marker_data['subtitle']) ? sanitize_text_field($marker_data['subtitle']) : '';
        $addr     = isset($marker_data['addr']) ? sanitize_text_field($marker_data['addr']) : '';
        $lat      = isset($marker_data['lat']) ? sanitize_text_field($marker_data['lat']) : '';
        $lon      = isset($marker_data['lon']) ? sanitize_text_field($marker_data['lon']) : '';
        $icon     = isset($marker_data['icon']) ? sanitize_text_field($marker_data['icon']) : '';
        $color    = isset($marker_data['color']) ? sanitize_hex_color($marker_data['color']) : '#d00';
        $custom_id= isset($marker_data['custom_icon_id']) ? intval($marker_data['custom_icon_id']) : 0;

        // Only save if name or address is present (or lat/lon)
        if ($name || $addr || ($lat && $lon)) {
            $sanitized_markers[] = [
                'name'           => $name,
                'subtitle'       => $subtitle,
                'addr'           => $addr,
                'lat'            => $lat,
                'lon'            => $lon,
                'icon'           => $icon,
                'color'          => $color,
                'custom_icon_id' => $custom_id,
            ];
        }
    }
    update_post_meta($post_id, '_lsm_markers', $sanitized_markers);
});

/* 5) AJAX Geocode Handler (Admin) */
add_action('wp_ajax_lsm_geocode', function(){
    check_ajax_referer('lsm_geocode', 'nonce'); // Check nonce first

    $addr = isset($_REQUEST['addr']) ? sanitize_text_field(wp_unslash($_REQUEST['addr'])) : '';
    if (empty($addr)) {
        wp_send_json_error(['message' => __('Address cannot be empty.', 'leaflet-shortcode-map')]);
        return;
    }

    $coordinates = lsm_geocode_address($addr); // Use the geocoding function

    if ($coordinates) {
        wp_send_json_success(['lat' => $coordinates[0], 'lon' => $coordinates[1]]);
    } else {
        wp_send_json_error(['message' => __('Address not found or geocoding service error.', 'leaflet-shortcode-map')]);
    }
});

/* Geocoding Function (Server-side with Cache) */
function lsm_geocode_address($address) {
    $address = trim($address);
    if (empty($address)) return false;

    $cache_key = 'lsm_geo_' . md5($address);
    $cached_coords = get_transient($cache_key);

    if (false !== $cached_coords && is_array($cached_coords)) {
        return $cached_coords; // Return cached result
    }

    // Use Nominatim API
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $address,
        'format' => 'json',
        'limit' => 1,
        'addressdetails' => 0 // Don't need full address details
    ]);

    // Get site URL for User-Agent
    $site_url = home_url();
    $user_agent = 'WordPress/' . get_bloginfo('version') . '; ' . $site_url . ' (Leaflet Shortcode Map Plugin)';

    $response = wp_remote_get($url, [
        'headers' => ['User-Agent' => $user_agent],
        'timeout' => 10 // Increased timeout slightly
    ]);

    if (is_wp_error($response)) {
        // Log error maybe? error_log('Nominatim API error: ' . $response->get_error_message());
        return false; // WP error during request
    }

    $body = wp_remote_retrieve_body($response);
    $status_code = wp_remote_retrieve_response_code($response);

    if ($status_code !== 200) {
         // Log error maybe? error_log('Nominatim API non-200 status: ' . $status_code);
        return false; // Non-successful HTTP status
    }

    $data = json_decode($body, true);

    if (empty($data) || !isset($data[0]['lat']) || !isset($data[0]['lon'])) {
        return false; // No results or invalid data format
    }

    // Extract coordinates
    $lat = floatval($data[0]['lat']);
    $lon = floatval($data[0]['lon']);

    if ($lat == 0 && $lon == 0) {
        return false; // Avoid (0,0) coordinates which are often invalid
    }

    $coordinates = [$lat, $lon];

    // Cache the result for 1 day
    set_transient($cache_key, $coordinates, DAY_IN_SECONDS);

    return $coordinates;
}


/* 6) Shortcode [ma_carte id="..."] */
add_shortcode('ma_carte', function($atts){
    $atts = shortcode_atts(['id' => 0], $atts, 'ma_carte');
    $map_id = intval($atts['id']);

    if (!$map_id || get_post_type($map_id) !== 'lsm_map' || get_post_status($map_id) !== 'publish') {
        if (current_user_can('edit_post', $map_id)) {
             return '<p style="color:red;">' . sprintf(esc_html__('Invalid Map ID (%d) or Map not published.', 'leaflet-shortcode-map'), $map_id) . '</p>';
        }
        return ''; // Don't show errors to public users
    }

    // Get Map Data
    $center_lat   = get_post_meta($map_id, '_lsm_center_lat', true);
    $center_lon   = get_post_meta($map_id, '_lsm_center_lon', true);
    $zoom         = intval(get_post_meta($map_id, '_lsm_center_zoom', true)) ?: 9;
    $markers_data = get_post_meta($map_id, '_lsm_markers', true);

    // Prepare marker data for JS
    $markers_for_js = [];
    if (is_array($markers_data)) {
        foreach ($markers_data as $m) {
            if (!empty($m['lat']) && !empty($m['lon'])) {
                $custom_icon_url = '';
                if (!empty($m['custom_icon_id'])) {
                    // Get appropriate size, e.g., 'medium' or a custom size
                    $img_data = wp_get_attachment_image_src(intval($m['custom_icon_id']), 'medium'); // Use 'medium' or custom size
                    if ($img_data) {
                        $custom_icon_url = esc_url($img_data[0]);
                        // Potentially add width/height if needed: $img_data[1], $img_data[2]
                    }
                }
                $markers_for_js[] = [
                    'lat' => floatval($m['lat']),
                    'lon' => floatval($m['lon']),
                    'name' => esc_js($m['name'] ?? ''), // Use esc_js for JS strings
                    'subtitle' => esc_js($m['subtitle'] ?? ''),
                    'addr' => esc_js($m['addr'] ?? ''),
                    'icon' => esc_js($m['icon'] ?? ''),
                    'color' => esc_js($m['color'] ?? '#d00'),
                    'customIconUrl' => $custom_icon_url, // Already escaped URL
                ];
            }
        }
    }

    // Unique ID for the map container
    $map_uid = 'lsm-map-' . $map_id . '-' . uniqid();

    // Data to pass to the frontend script
    $map_params = [
        'containerId' => $map_uid,
        'center' => [$center_lat ? floatval($center_lat) : 0, $center_lon ? floatval($center_lon) : 0], // Default to 0,0 if not set
        'zoom' => $zoom,
        'markers' => $markers_for_js,
        'tileLayerUrl' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', // Make configurable?
        'tileLayerAttribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' // Standard attribution
    ];

    // Enqueue frontend scripts/styles if not already done (can be moved to a dedicated function)
    wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
    wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);
    wp_enqueue_style('dashicons'); // Ensure Dashicons are loaded for frontend markers

    // Add inline script to initialize this specific map instance
    // Use wp_add_inline_script for better dependency management and execution order
    $init_script = sprintf(
        'document.addEventListener("DOMContentLoaded", function() { if(typeof initLsmMap !== "undefined") { initLsmMap(%s); } else { console.error("LSM Map init function not found."); } });',
        wp_json_encode($map_params) // Securely encode data for JS
    );
    wp_add_inline_script('leaflet-js', $init_script, 'after');

    // Return the map container div
    return sprintf(
        '<div id="%s" class="lsm-map-container" style="height:400px;">%s</div>', // Added default height, can be overridden by CSS
        esc_attr($map_uid),
        '<span class="lsm-loading">' . esc_html__('Loading map...', 'leaflet-shortcode-map') . '</span>' // Loading indicator
    );
});

/* 7) Enqueue Frontend Scripts & Styles + Map Initialization Logic */
add_action('wp_enqueue_scripts', function(){
    // Check if Leaflet is already enqueued by another plugin/theme maybe?
    // This basic enqueue works, but could be more robust.

    // General map initialization script (will be used by inline scripts from the shortcode)
    $frontend_script = <<<'JS'
function initLsmMap(params) {
  if (typeof L === 'undefined') {
    console.error('Leaflet library not loaded.');
    return;
  }
  var mapContainer = document.getElementById(params.containerId);
  if (!mapContainer) {
    console.error('Map container not found:', params.containerId);
    return;
  }
  // Clear loading indicator
  mapContainer.innerHTML = '';

  var map = L.map(params.containerId).setView(params.center, params.zoom);

  L.tileLayer(params.tileLayerUrl, {
    attribution: params.tileLayerAttribution,
    maxZoom: 18, // Standard max zoom
  }).addTo(map);

  params.markers.forEach(function(m) {
    var marker;
    var markerLatLng = [m.lat, m.lon];

    if (m.customIconUrl) {
      // Custom image icon
      var customIcon = L.icon({
        iconUrl: m.customIconUrl,
        iconSize: [32, 32], // Make configurable?
        iconAnchor: [16, 32], // Bottom center
        popupAnchor: [0, -32] // Above the anchor
      });
      marker = L.marker(markerLatLng, { icon: customIcon });
    } else if (m.icon) {
      // Dashicon
      var divIcon = L.divIcon({
        html: '<span class="dashicons ' + m.icon + '" style="color:' + m.color + '; font-size:24px;"></span>',
        className: 'lsm-dashicon-marker', // Add a class for potential styling
        iconSize: [24, 24],
        iconAnchor: [12, 24], // Bottom center
        popupAnchor: [0, -24] // Above the anchor
      });
      marker = L.marker(markerLatLng, { icon: divIcon });
    } else {
       // Default Leaflet marker if no icon specified
       marker = L.marker(markerLatLng);
    }

    // Popup content
    var popupContent = '';
    if (m.name) {
        popupContent += '<b>' + m.name + '</b>';
    }
    if (m.subtitle) {
        popupContent += (popupContent ? '<br>' : '') + m.subtitle;
    }
     if (m.addr) {
        // Optional: Link address to Google Maps?
        // popupContent += (popupContent ? '<br>' : '') + '<a href="https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(m.addr) + '" target="_blank">' + m.addr + '</a>';
         popupContent += (popupContent ? '<br>' : '') + m.addr;
    }

    if (popupContent) {
        marker.bindPopup(popupContent);
    }

    marker.addTo(map);
  });

  // Optional: Fit map bounds to markers if center/zoom wasn't explicitly set?
  // if (params.markers.length > 1 && !mapWasExplicitlyCentered) {
  //   var group = new L.featureGroup(map.getLayers().filter(layer => layer instanceof L.Marker)); // Get only markers
  //   map.fitBounds(group.getBounds().pad(0.1)); // Add padding
  // }
}
JS;
    // Add the main init function script, dependent on leaflet-js
    wp_add_inline_script('leaflet-js', $frontend_script, 'before'); // Add before the specific map init calls

    // Add some basic CSS for loading/markers if needed
    $inline_css = ".lsm-map-container { position: relative; background: #eee; } .lsm-loading { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #666; } .lsm-dashicon-marker { background: none; border: none; }";
    wp_add_inline_style('leaflet-css', $inline_css);

});

// Function to load text domain - typically called on 'plugins_loaded'
add_action('plugins_loaded', function() {
    load_plugin_textdomain('leaflet-shortcode-map', false, dirname(plugin_basename(__FILE__)) . '/languages/');
});

// Add a donation link to the plugin actions
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'lsm_add_donation_link');

function lsm_add_donation_link($links) {
    $donation_link = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=mussarddavid@hotmail.com&item_name=Donation+for+Leaflet+Shortcode+Map&currency_code=EUR" target="_blank" style="color:#3db634; font-weight:bold;">' . __('Donate', 'leaflet-shortcode-map') . '</a>';
    array_push($links, $donation_link);
    return $links;
}