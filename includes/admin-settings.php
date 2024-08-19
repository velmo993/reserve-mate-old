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

function fix_json($raw_json) {
    $decoded = json_decode($raw_json, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        return json_encode($decoded, JSON_PRETTY_PRINT);
    } else {
        $fixed_json = htmlspecialchars_decode($raw_json);
        $decoded = json_decode($fixed_json, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_PRETTY_PRINT);
        } else {
            // Log error or handle it as needed
            // error_log('Invalid JSON: ' . json_last_error_msg());
            return false;
        }
    }
}

function booking_settings_page() {
    ?>
    <div class="wrap">
        <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        } ?>
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
    register_setting('booking_settings_group', 'booking_settings', array(
        'sanitize_callback' => 'sanitize_booking_settings'
    ));

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
    
    add_settings_field(
        'calendar_timezones',
        'Calendar Timezone',
        'display_calendar_timezones',
        'booking-settings',
        'general_settings'
    );
    
    add_settings_field(
        'default_attendees',
        'Event Attendees (comma-separated emails, optional)',
        'display_calendar_event_attendees_field',
        'booking-settings',
        'general_settings'
    );
}

function sanitize_booking_settings($input) {
    if (isset($input['calendar_api_key'])) {
        $input['calendar_api_key'] = fix_json($input['calendar_api_key']);
    }
    return $input;
}

function display_calendar_api_key_field() {
    $options = get_option('booking_settings');
    $api_key = isset($options['calendar_api_key']) ? esc_textarea($options['calendar_api_key']) : '';
    ?>
    <textarea name="booking_settings[calendar_api_key]" rows="10" cols="50"><?php echo $api_key; ?></textarea>
    <?php
}

function display_calendar_timezones() {
    $options = get_option('booking_settings');
    $default_timezone = 'America/New_York';
    $timezone = isset($options['calendar_timezones']) ? esc_attr($options['calendar_timezones']) : $default_timezone;

    $timezones = timezone_identifiers_list();

    echo '<select name="booking_settings[calendar_timezones]">';
    foreach ($timezones as $tz) {
        echo '<option value="' . esc_attr($tz) . '"' . selected($timezone, $tz, false) . '>' . esc_html($tz) . '</option>';
    }
    echo '</select>';
}

function display_calendar_event_attendees_field() {
    $options = get_option('booking_settings');
    $attendees = isset($options['calendar_event_attendees']) ? esc_attr($options['calendar_event_attendees']) : '';
    ?>
    <input type="text" name="booking_settings[calendar_event_attendees]" value="<?php echo $attendees; ?>" />
    <?php
}

add_action('admin_init', 'register_booking_settings');