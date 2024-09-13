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

// Create table for rooms
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

    // Check if the table doesn't exist before creating it
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
        flatpickr("#start-date", {
            dateFormat: "Y-m-d",
            placeholder: "Select Start Date",
        });

        flatpickr("#end-date", {
            dateFormat: "Y-m-d",
            placeholder: "Select End Date",
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



add_action('init', 'create_booking_post_type');
add_action('admin_enqueue_scripts', 'enqueue_admin_styles');
add_action('admin_enqueue_scripts', 'enqueue_admin_scripts');
add_action('wp_enqueue_scripts', 'enqueue_custom_date_picker_assets');
add_action('wp_enqueue_scripts', 'enqueue_booking_form_scripts');
add_action('wp_ajax_get_room_images', 'ajax_get_room_images');
add_action('wp_ajax_delete_room_image', 'ajax_delete_room_image');
add_action('wp_ajax_fetch_amenities', 'ajax_fetch_amenities');
add_action('wp_footer', 'initialize_date_picker');

register_activation_hook(__FILE__, 'create_amenities_table');
register_activation_hook(__FILE__, 'create_rooms_table');
register_activation_hook(__FILE__, 'create_room_images_table');
register_activation_hook(__FILE__, 'create_room_amenities_relation_table');
register_activation_hook(__FILE__, 'create_bookings_table');

