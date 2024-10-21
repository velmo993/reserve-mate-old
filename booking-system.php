<?php
/*
Plugin Name: Reserve Mate
Description: A customizable booking system with Google Calendar integration and payment options.
Version: 1.0
Author: velmoweb.com
*/

// Prevent direct access
defined('ABSPATH') or die('No script please!');

// Include required files
include_once(plugin_dir_path(__FILE__) . 'includes/admin-settings.php');
include_once(plugin_dir_path(__FILE__) . 'includes/booking-form.php');
include_once(plugin_dir_path(__FILE__) . 'includes/google-calendar.php');
include_once(plugin_dir_path(__FILE__) . 'includes/payments.php');

// Register custom post types
function create_booking_post_type() {
    register_post_type('booking',
        array(
            'labels' => array(
                'name' => __('Bookings'),
                'singular_name' => __('Booking')
            ),
            'public' => true,
            'has_archive' => true,
            'supports' => array('title', 'editor', 'custom-fields'),
            'show_in_rest' => true,
        )
    );
}

function create_rooms_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservemate_rooms';
    $charset_collate = $wpdb->get_charset_collate();

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text NULL,
            max_guests mediumint(9) NOT NULL,
            cost_per_day decimal(10, 2) NOT NULL DEFAULT 0.00,
            adult_price decimal(10, 2) NOT NULL DEFAULT 0.00,
            child_price decimal(10, 2) NOT NULL DEFAULT 0.00,
            size varchar(100) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
    
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

function create_room_images_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservemate_room_images';
    $charset_collate = $wpdb->get_charset_collate();

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            room_id mediumint(9) NOT NULL,
            image_id mediumint(9) NOT NULL,
            PRIMARY KEY (id),
            KEY room_id (room_id),
            FOREIGN KEY (room_id) REFERENCES {$wpdb->prefix}reservemate_rooms(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}


function create_amenities_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservemate_amenities';
    $charset_collate = $wpdb->get_charset_collate();

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            amenity_name varchar(255) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        insert_predefined_amenities();
    }
}

function insert_predefined_amenities() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservemate_amenities';

    $amenities = [
        'Air Conditioning',
        'Airport Shuttle',
        'Babysitter',
        'Balcony',
        'Bar',
        'Bath',
        'Bathtub',
        'BBQ Grill',
        'Beach Access',
        'Bicycle Rental',
        'Breakfast',
        'Business Center',
        'Car Rental',
        'Casino',
        'Charging Outlets',
        'Charging Station',
        'Child Bed',
        'City View',
        'Coffee Maker',
        'Concierge',
        'Conference Room',
        'Courtyard',
        'Crib',
        'Dining Area',
        'Dishwasher',
        'DJ Services',
        'Driver Service',
        'Dryer',
        'Elevator',
        'Express Checkout',
        'Fan',
        'Fire Extinguisher',
        'Fireplace',
        'Fitness Center',
        'Free WiFi',
        'Fridge',
        'Game Room',
        'Garden View',
        'Grill',
        'Gym',
        'Hair Dryer',
        'Heater',
        'High Chair',
        'In-Room Safe',
        'Iron',
        'Jacuzzi',
        'Keyless Entry',
        'King Bed',
        'Kitchen',
        'Library',
        'Lounge',
        'Meeting Room',
        'Microwave',
        'Mini Bar',
        'Mini Golf',
        'Mountain View',
        'Non-Smoking',
        'Ocean View',
        'Outdoor Furniture',
        'Parking',
        'Patio',
        'Pet Bedding',
        'Pet Friendly',
        'Pet Walking',
        'Playground',
        'Pool Bar',
        'Pool View',
        'Private Pool',
        'Restaurant',
        'Rooftop Access',
        'Room Service',
        'Safe',
        'Sauna',
        'Security',
        'Self Check-In',
        'Shower',
        'Ski-In/Ski-Out',
        'Smoke Alarm',
        'Sofa Bed',
        'Solar Power',
        'Soundproof',
        'Spa',
        'Stove',
        'Towels',
        'Twin Beds',
        'Valet Parking',
        'Washer',
        'Washing Machine',
        'Wheelchair Accessible',
        'Workspace',
    ];

    foreach ($amenities as $amenity) {
        $wpdb->insert($table_name, ['amenity_name' => $amenity]);
    }
}

function create_room_amenities_relation_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservemate_room_amenities';
    $charset_collate = $wpdb->get_charset_collate();

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            room_id mediumint(9) NOT NULL,
            amenity_id mediumint(9) NOT NULL,
            PRIMARY KEY (id),
            KEY room_id (room_id),
            KEY amenity_id (amenity_id),
            FOREIGN KEY (room_id) REFERENCES {$wpdb->prefix}reservemate_rooms(id) ON DELETE CASCADE,
            FOREIGN KEY (amenity_id) REFERENCES {$wpdb->prefix}reservemate_amenities(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

function create_beds_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservemate_beds';
    $charset_collate = $wpdb->get_charset_collate();

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            bed_type varchar(255) NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        insert_predefined_beds();
    }
}

function insert_predefined_beds() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservemate_beds';

    $beds = [
        'Single Bed',
        'Double Bed',
        'Queen Bed',
        'King Bed',
        'Sofa Bed',
        'Bunk Bed',
    ];

    foreach ($beds as $bed) {
        $wpdb->insert($table_name, ['bed_type' => $bed]);
    }
}

function create_room_beds_relation_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservemate_room_beds';
    $charset_collate = $wpdb->get_charset_collate();

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            room_id mediumint(9) NOT NULL,
            bed_id mediumint(9) NOT NULL,
            bed_count mediumint(9) NOT NULL,
            PRIMARY KEY (id),
            KEY room_id (room_id),
            KEY bed_id (bed_id),
            FOREIGN KEY (room_id) REFERENCES {$wpdb->prefix}reservemate_rooms(id) ON DELETE CASCADE,
            FOREIGN KEY (bed_id) REFERENCES {$wpdb->prefix}reservemate_beds(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

function create_bookings_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservemate_bookings';
    $charset_collate = $wpdb->get_charset_collate();
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            room_id mediumint(9) NOT NULL,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(20) NOT NULL,
            adults int NOT NULL,
            children int NOT NULL,
            start_date date NOT NULL,
            end_date date NOT NULL,
            total_cost decimal(10, 2) NOT NULL DEFAULT 0.00,
            paid tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            FOREIGN KEY (room_id) REFERENCES {$wpdb->prefix}reservemate_rooms(id) ON DELETE CASCADE
        ) $charset_collate;";
    
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

function enqueue_admin_styles() {
    wp_enqueue_style(
        'booking-plugin-styles',
        plugin_dir_url(__FILE__) . 'includes/css/style.css',
        array(),
        '1.0.0'
    );
}

function enqueue_admin_scripts() {
    wp_enqueue_script(
        'booking-plugin-scripts',
        plugin_dir_url(__FILE__) . 'includes/js/admin/admin.js',
        array('jquery'),
        '1.0.0',
        true
    );
}

function initialize_date_picker() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var today = new Date();
        var tomorrow = new Date();
        tomorrow.setDate(today.getDate() + 1);
        
        let endDate = document.getElementById('end-date');
        let startDate = document.getElementById('start-date');
        endDate ? endDate.setAttribute('placeholder', 'Departure Date') : '';
        startDate ? startDate.setAttribute('placeholder', 'Arrival Date') : '';
        
        flatpickr("#start-date", {
            dateFormat: "Y-m-d",
            minDate: tomorrow,
            disable: [
                function(date) {
                    return date.toDateString() === today.toDateString();
                }
            ],
        });

        flatpickr("#end-date", {
            dateFormat: "Y-m-d",
            minDate: tomorrow,
            disable: [
                function(date) {
                    return date.toDateString() === tomorrow.toDateString();
                }
            ],
        });
    });
    </script>
    <?php
}

function enqueue_custom_date_picker_assets() {
    wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', array('jquery'), null, true);
}

function enqueue_booking_form_scripts() {
    wp_enqueue_script(
        'booking-form-scripts',
        plugin_dir_url(__FILE__) . 'includes/js/frontend/script.js',
        array('jquery'),
        '1.0.0',
        true
    );
}

// Deregister Elementor's Font Awesome Styles
add_action('wp_enqueue_scripts', 'remove_elementor_font_awesome_styles', 100);

function remove_elementor_font_awesome_styles() {
    // Dequeue Elementor's Font Awesome styles (solid, regular, brands, etc.)
    wp_dequeue_style('elementor-icons-fa-solid');
    wp_deregister_style('elementor-icons-fa-solid');
    
    wp_dequeue_style('elementor-icons-fa-regular');
    wp_deregister_style('elementor-icons-fa-regular');
    
    wp_dequeue_style('elementor-icons-fa-brands');
    wp_deregister_style('elementor-icons-fa-brands');
    
    wp_dequeue_style('elementor-icons');
    wp_deregister_style('elementor-icons');
    
    // Optionally, if Elementor loads Font Awesome under any other handles
    wp_dequeue_style('font-awesome');
    wp_deregister_style('font-awesome');
}

// Enqueue Font Awesome 6.6.0
add_action('wp_enqueue_scripts', 'enqueue_custom_font_awesome_6', 110);

function enqueue_custom_font_awesome_6() {
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css', array(), '6.6.0');
}

function ajax_get_room_images() {
    if (!isset($_GET['room_id'])) {
        wp_send_json_error('Room ID is missing');
    }
    
    $room_id = intval($_GET['room_id']);
    $images = get_room_images($room_id);

    if ($images) {
        $result = [];
        foreach ($images as $image) {
            $image_url = wp_get_attachment_url($image->image_id);
            $result[] = [
                'id' => $image->image_id,
                'url' => $image_url
            ];
        }
        wp_send_json_success(['images' => $result]);
    } else {
        wp_send_json_success(['images' => []]);
    }
}

function ajax_delete_room_image() {
    $post_data = json_decode(file_get_contents('php://input'), true);

    if (isset($post_data['image_id'])) {
        $image_id = intval($post_data['image_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'reservemate_room_images';
        
        $deleted = $wpdb->delete($table_name, ['image_id' => $image_id]); 
        
        if ($deleted) {
            wp_send_json_success(['message' => 'Image deleted']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete image.']);
        }
    } else {
        wp_send_json_error(['message' => 'Invalid request. No image ID.']);
    }
}


function ajax_fetch_amenities() {
    global $wpdb;

    $room_id = intval($_GET['room_id']);
    $predefined_amenities = get_predefined_amenities();

    $selected_amenities = $wpdb->get_col($wpdb->prepare(
        "SELECT amenity_id FROM {$wpdb->prefix}reservemate_room_amenities WHERE room_id = %d",
        $room_id
    ));

    $response = [];
    foreach ($predefined_amenities as $id => $name) {
        $response['amenities'][] = [
            'id' => $id,
            'name' => $name,
            'selected' => in_array($id, $selected_amenities)
        ];
    }

    echo json_encode($response);
    wp_die();
}

function ajax_fetch_beds() {
    global $wpdb;

    $room_id = intval($_GET['room_id']);

    $predefined_beds = get_predefined_beds();

    $selected_beds = $wpdb->get_results($wpdb->prepare(
        "SELECT bed_type, bed_count FROM {$wpdb->prefix}reservemate_room_beds WHERE room_id = %d",
        $room_id
    ), ARRAY_A);

    $selected_beds_data = [];
    foreach ($selected_beds as $bed) {
        $selected_beds_data[$bed['bed_type']] = [
            'count' => intval($bed['bed_count']),
        ];
    }

    $response = [];
    foreach ($predefined_beds as $type => $name) {
        $response['beds'][] = [
            'type' => $type,
            'count' => isset($selected_beds_data[$type]) ? $selected_beds_data[$type]['count'] : 0,
        ];
    }

    echo json_encode($response);
    wp_die();
}

function load_room_callback() {
    global $wpdb;

    $page = isset($_GET['page']) ? intval($_GET['page']) : 0;
    $rooms_per_page = 20;
    $offset = $page * $rooms_per_page;

    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : null;
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : null;
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $days_booked = $interval->days;
    
    $rooms = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}reservemate_rooms LIMIT %d OFFSET %d",
        $rooms_per_page,
        $offset
    ));

    $bookings_table = $wpdb->prefix . 'reservemate_bookings';
    $booked_rooms = $wpdb->get_results($wpdb->prepare(
        "SELECT room_id, MIN(end_date) as next_available_date FROM $bookings_table 
        WHERE (
            (start_date <= %s AND end_date >= %s) OR
            (start_date >= %s AND start_date <= %s)
        )
        GROUP BY room_id",
        $end_date, $start_date, $start_date, $end_date
    ), ARRAY_A);

    $booked_rooms_map = [];
    foreach ($booked_rooms as $booked_room) {
        $booked_rooms_map[$booked_room['room_id']] = $booked_room['next_available_date'];
    }

    $room_data = [];
    foreach ($rooms as $room) {
        $room_id = $room->id;
        $room_images = get_room_pictures($room_id);
        $room_amenities = get_all_room_amenities($room_id);

        $bed_details = $wpdb->get_results($wpdb->prepare(
            "SELECT b.bed_type, rb.bed_count 
             FROM {$wpdb->prefix}reservemate_room_beds rb 
             JOIN {$wpdb->prefix}reservemate_beds b ON rb.bed_id = b.id 
             WHERE rb.room_id = %d",
            $room_id
        ));
        
        $beds = array_map(function($bed) {
            return [
                'bed_type' => $bed->bed_type,
                'bed_count' => intval($bed->bed_count)
            ];
        }, $bed_details);
    
        $images = array_map(function($img) {
            return [
                'url' => wp_get_attachment_url($img->image_id)
            ];
        }, $room_images);
    
        $amenities = array_map(function($amenity) {
            return [
                'name' => format_amenity_name($amenity),
                'icon' => get_amenity_icon($amenity)
            ];
        }, $room_amenities);
    
        $base_cost = $room->cost_per_day;
        $price_per_adult = $room->adult_price;
        $price_per_child = $room->child_price;
    
        if (isset($booked_rooms_map[$room->id])) {
            $is_booked = true;
            $next_available_date = date('Y-m-d', strtotime($booked_rooms_map[$room->id] . ' +1 day'));
        } else {
            $is_booked = false;
            $next_available_date = null;
        }

        $room_data[] = [
            'id' => $room->id,
            'name' => $room->name,
            'is_booked' => $is_booked,
            'next_available_date' => $next_available_date,
            'description' => $room->description,
            'base_cost' => $base_cost,
            'price_per_adult' => $price_per_adult,
            'price_per_child' => $price_per_child,
            'currency_symbol' => get_option('currency_symbol', '$'),
            'images' => $images,
            'size' => $room->size,
            'max_guests' => $room->max_guests,
            'amenities' => $amenities,
            'beds' => $beds,
            'total_cost' => $days_booked * floatval($room->cost_per_day)
        ];
    }

    wp_send_json_success([
        'rooms' => $room_data,
        'total_rooms' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}reservemate_rooms"),
    ]);
}

function get_filter_data() {
    global $wpdb;

    $amenities = $wpdb->get_results("SELECT id, amenity_name FROM {$wpdb->prefix}reservemate_amenities", ARRAY_A);

    $room_sizes = $wpdb->get_results("SELECT DISTINCT size FROM {$wpdb->prefix}reservemate_rooms", ARRAY_A);

    wp_send_json([
        'amenities' => $amenities,
        'room_sizes' => $room_sizes
    ]);
}

function enqueue_ajax_scripts() {
    wp_enqueue_script('frontend-ajax-script', get_template_directory_uri() . '/js/frontend/script.js', array('jquery'));
    
    wp_localize_script('frontend-ajax-script', 'ajaxScript', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('load_room_nonce')
    ));
}

function enqueue_stripe_scripts() {
    wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
    wp_enqueue_script('stripe-payment', plugin_dir_url(__FILE__) . 'includes/js/stripe/stripe-payment.js', ['stripe-js'], null, true);
    
    $stripe_public_key = get_option('payment_settings')['stripe_public_key'];
    wp_localize_script('stripe-payment', 'stripe_vars', [
        'stripePublicKey' => $stripe_public_key,
        'pluginDir' => plugin_dir_url(__FILE__)
    ]);
}

function enqueue_paypal_scripts() {
    $paypal_client_id = get_option('payment_settings')['paypal_client_id'];
    wp_enqueue_script('paypal-js', 'https://www.paypal.com/sdk/js?client-id=' . $paypal_client_id . '&disable-funding=card', [], null, true);
    wp_enqueue_script('paypal-payment', plugin_dir_url(__FILE__) . 'includes/js/paypal/paypal-payment.js', ['paypal-js'], null, true);
    
    $paypal_client_id = get_option('payment_settings')['paypal_client_id'];
    wp_localize_script('paypal-payment', 'paypal_vars', [
        'paypalClientId' => $paypal_client_id,
        'pluginDir' => plugin_dir_url(__FILE__)
    ]);
}

// function enqueue_apple_pay_scripts() {
//     wp_enqueue_script('apple-pay-sdk', 'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js', [], null, true);
//     wp_enqueue_script('apple-pay-payment', plugin_dir_url(__FILE__) . 'includes/js/apple-pay/apple-pay-payment.js', ['apple-pay-sdk'], null, true);

//     $apple_pay_merchant_id = get_option('payment_settings')['apple_pay_merchant_id'];

//     wp_localize_script('apple-pay-payment', 'apple_pay_vars', [
//         'merchantId' => $apple_pay_merchant_id,
//         'pluginDir' => plugin_dir_url(__FILE__)
//     ]);
// }

function enqueue_payment_scripts() {
    wp_enqueue_script('script', plugin_dir_url(__FILE__) . 'includes/js/frontend/script.js', [], null, true);
    
    $p_settings = get_option('payment_settings');

    $payment_settings = [
        'stripe_enabled' => isset($p_settings['stripe_enabled']) ? $p_settings['stripe_enabled'] : '0',
        'paypal_enabled' => isset($p_settings['paypal_enabled']) ? $p_settings['paypal_enabled'] : '0',
        // 'apple_pay_enabled' => isset($p_settings['apple_pay_enabled']) ? $p_settings['apple_pay_enabled'] : '0',
    ];
    
    wp_localize_script('script', 'paymentSettings', $payment_settings);
}

function booking_auto_cleanup() {
    if (!wp_next_scheduled('reservemate_cleanup_unpaid_bookings')) {
        wp_schedule_event(time(), 'daily', 'reservemate_cleanup_unpaid_bookings');
    }
}

function delete_unpaid_bookings() {
    global $wpdb;
    $options = get_option('booking_settings');
    
    if (isset($options['auto_delete_booking_enabled']) && $options['auto_delete_booking_enabled'] == 1) {
        $days = isset($options['delete_after_days']) ? absint($options['delete_after_days']) : 6;
        $table_name = $wpdb->prefix . 'reservemate_bookings';

        $date_threshold = date('Y-m-d H:i:s', strtotime("-$days days"));

        error_log('Date threshold: ' . $date_threshold);

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE paid = 0 AND created_at < %s",
                $date_threshold
            )
        );
    }
}


add_action('wp_ajax_get_filter_data', 'get_filter_data');
add_action('wp_ajax_nopriv_get_filter_data', 'get_filter_data');
add_action('wp_enqueue_scripts', 'enqueue_ajax_scripts');
add_action('wp_ajax_load_room', 'load_room_callback');
add_action('wp_ajax_nopriv_load_room', 'load_room_callback');
add_action('init', 'create_booking_post_type');
add_action('admin_enqueue_scripts', 'enqueue_admin_styles');
add_action('admin_enqueue_scripts', 'enqueue_admin_scripts');
add_action('wp_enqueue_scripts', 'enqueue_custom_date_picker_assets');
add_action('wp_enqueue_scripts', 'enqueue_booking_form_scripts');
add_action('wp_ajax_get_room_images', 'ajax_get_room_images');
add_action('wp_ajax_delete_room_image', 'ajax_delete_room_image');
add_action('wp_ajax_fetch_amenities', 'ajax_fetch_amenities');
add_action('wp_ajax_fetch_beds', 'ajax_fetch_beds');
add_action('reservemate_cleanup_unpaid_bookings', 'delete_unpaid_bookings');
add_action('wp', 'booking_auto_cleanup');
add_action('wp_footer', 'initialize_date_picker');
add_action('wp_enqueue_scripts', 'enqueue_stripe_scripts');
add_action('wp_enqueue_scripts', 'enqueue_paypal_scripts');
// add_action('wp_enqueue_scripts', 'enqueue_apple_pay_scripts');
add_action('wp_enqueue_scripts', 'enqueue_payment_scripts');

register_activation_hook(__FILE__, 'create_amenities_table');
register_activation_hook(__FILE__, 'create_rooms_table');
register_activation_hook(__FILE__, 'create_room_images_table');
register_activation_hook(__FILE__, 'create_beds_table');
register_activation_hook(__FILE__, 'create_room_amenities_relation_table');
register_activation_hook(__FILE__, 'create_room_beds_relation_table');
register_activation_hook(__FILE__, 'create_bookings_table');

