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
        description text NOT NULL,
        max_guests mediumint(9) NOT NULL,
        is_available boolean NOT NULL DEFAULT 1,
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
        PRIMARY KEY  (id),
        FOREIGN KEY (room_id) REFERENCES {$wpdb->prefix}rooms(id) ON DELETE CASCADE
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    }
}

register_activation_hook(__FILE__, 'create_rooms_table');
register_activation_hook(__FILE__, 'create_bookings_table');

add_action('init', 'create_booking_post_type');
