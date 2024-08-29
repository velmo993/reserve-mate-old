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
    $table_name = $wpdb->prefix . 'rooms';
    $charset_collate = $wpdb->get_charset_collate();
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        description text NULL,
        max_guests mediumint(9) NOT NULL,
        cost_per_day decimal(10, 2) NOT NULL DEFAULT 0.00,
        amenities text NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    }
}

function create_bookings_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bookings';
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
        FOREIGN KEY (room_id) REFERENCES {$wpdb->prefix}rooms(id) ON DELETE CASCADE
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
        plugin_dir_url(__FILE__) . 'includes/js/frontend/script.js', // Adjust path if needed
        array('jquery'),
        '1.0.0',
        true
    );
}

function enqueue_font_awesome() {
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css');
}

register_activation_hook(__FILE__, 'create_rooms_table');
register_activation_hook(__FILE__, 'create_bookings_table');

add_action('init', 'create_booking_post_type');
add_action('admin_enqueue_scripts', 'enqueue_admin_styles');
add_action('admin_enqueue_scripts', 'enqueue_admin_scripts');
add_action('wp_enqueue_scripts', 'enqueue_custom_date_picker_assets');

add_action('wp_footer', 'initialize_date_picker');
add_action('wp_enqueue_scripts', 'enqueue_booking_form_scripts');
add_action('wp_enqueue_scripts', 'enqueue_font_awesome');

