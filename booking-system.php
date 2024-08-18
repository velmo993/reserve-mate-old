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
add_action('init', 'create_booking_post_type');

// Enqueue styles and scripts
// function enqueue_booking_scripts() {
//     wp_enqueue_style('booking-style', plugins_url('/css/booking-style.css', __FILE__));
//     wp_enqueue_script('booking-script', plugins_url('/js/booking-script.js', __FILE__), array('jquery'), null, true);
// }
// add_action('wp_enqueue_scripts', 'enqueue_booking_scripts');
