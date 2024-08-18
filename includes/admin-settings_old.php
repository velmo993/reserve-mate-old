<?php
// Prevent direct access
defined('ABSPATH') or die('No script please!');

function add_admin_menu() {
    add_menu_page(
        'Booking System Settings',
        'Booking Settings',
        'manage_options',
        'booking-settings',
        'booking_settings_page'
    );
}
add_action('admin_menu', 'add_admin_menu');

function booking_settings_page() {
    ?>
    <div class="wrap">
        <h1>Booking System Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('booking_settings_group');
            do_settings_sections('booking-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function register_booking_settings() {
    register_setting('booking_settings_group', 'booking_settings');

    add_settings_section(
        'general_settings',
        'General Settings',
        null,
        'booking-settings'
    );

    add_settings_field(
        'calendar_api_key',
        'Google Calendar API Credentials (JSON)',
        'display_calendar_api_key_field',
        'booking-settings',
        'general_settings'
    );
}

function display_calendar_api_key_field() {
    $options = get_option('booking_settings');
    
    // Ensure $options is an array and not a boolean
    if (!is_array($options)) {
        $options = array(); // Initialize as an empty array if not set
    }
    
    $api_key = isset($options['calendar_api_key']) ? esc_textarea($options['calendar_api_key']) : '';
    ?>
    <textarea name="booking_settings[calendar_api_key]" rows="10" cols="50"><?php echo $api_key; ?></textarea>
    <?php
}

add_action('admin_init', 'register_booking_settings');
