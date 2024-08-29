<?php
// Prevent direct access
defined('ABSPATH') or die('No script please!');

// Add "Booking Settings" menu and submenus
function add_admin_menu() {
    // Add the main menu page
    add_menu_page(
        'Booking System Settings',    // Page title
        'Booking Settings',          // Menu title
        'manage_options',            // Capability required
        'booking-settings',          // Menu slug
        'booking_settings_page'      // Callback function for the page
    );

    // Add "Manage Rooms" submenu under "Booking Settings"
    add_submenu_page(
        'booking-settings', 
        'Manage Rooms', 
        'Manage Rooms', 
        'manage_options', 
        'booking-settings',  // This should match the main menu slug if it's the same page
        'booking_settings_page' // The callback function for this submenu
    );

    // Add "Manage Bookings" submenu under "Booking Settings"
    add_submenu_page(
        'booking-settings', 
        'Manage Bookings', 
        'Manage Bookings', 
        'manage_options', 
        'manage_bookings_page', // Menu slug for "Manage Bookings" page
        'display_manage_bookings_page' // Callback function for "Manage Bookings" page
    );
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
        'calendar_id',
        'Google Calendar ID',
        'display_calendar_id_field',
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
        'currency',
        'Currency',
        'display_currency_field',
        'booking-settings',
        'general_settings'
    );
    
}

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

function get_currency() {
    $options = get_option('booking_settings');
    $currency = isset($options['currency']) ? $options['currency'] : 'USD';

    $c_symbol = '$'; // Default to USD
    if ($currency === 'EUR') {
        $c_symbol = '€';
    } elseif ($currency === 'GBP') {
        $c_symbol = '£';
    } elseif ($currency === 'JPY') {
        $c_symbol = '¥';
    }
    return sanitize_text_field($c_symbol);
}

function booking_settings_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'rooms';
    $currency_symbol = get_currency();

    // Handle form submissions for room management
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_room'])) {
            $name = sanitize_text_field($_POST['room_name']);
            $description = sanitize_textarea_field($_POST['room_description']);
            $max_guests = intval($_POST['max_guests']);
            $cost_per_day = floatval($_POST['cost_per_day']);
            $amenities = isset($_POST['amenities']) ? serialize($_POST['amenities']) : '';


            // Insert the new room into the database
            $wpdb->insert($table_name, 
                array(
                    'name' => $name,
                    'description' => $description,
                    'max_guests' => $max_guests,
                    'cost_per_day' => $cost_per_day,
                    'amenities' => $amenities
                ),
                array(
                    '%s',  // name
                    '%s',  // description
                    '%d',  // max_guests
                    '%f',  // cost_per_day (float)
                    '%s'   // amenities (string)
                )
            );
        } elseif (isset($_POST['edit_room'])) {
            $room_id = intval($_POST['room_id']);
            $name = sanitize_text_field($_POST['room_name']);
            $description = sanitize_textarea_field($_POST['room_description']);
            $max_guests = intval($_POST['max_guests']);
            $cost_per_day = floatval($_POST['cost_per_day']);
            $amenities = isset($_POST['amenities']) ? serialize($_POST['amenities']) : '';

            // Update the room in the database
            $wpdb->update($table_name, 
                array(
                    'name' => $name,
                    'description' => $description,
                    'max_guests' => $max_guests,
                    'cost_per_day' => $cost_per_day,
                    'amenities' => $amenities
                ), 
                array('id' => $room_id),
                array(
                    '%s',  // name (string)
                    '%s',  // description (string)
                    '%d',  // max_guests (integer)
                    '%f',  // cost_per_day (float)
                    '%s'   // amenities (string)
                ),
                array('%d')  // id (integer)
            );
        } elseif (isset($_POST['delete_room'])) {
            $room_id = intval($_POST['room_id']);
            // Delete the room from the database
            $wpdb->delete($table_name, array('id' => $room_id));
        }
    }

    // Retrieve all rooms from the database
    $rooms = $wpdb->get_results("SELECT * FROM $table_name");

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

        <h2>Existing Rooms</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Max Guests</th>
                    <th>Cost Per Day</th>
                    <th>Amenities</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rooms) : ?>
                    <?php foreach ($rooms as $room) : ?>
                        <tr>
                            <td><?php echo esc_html($room->id); ?></td>
                            <td><?php echo esc_html($room->name); ?></td>
                            <td><?php echo esc_html($room->description); ?></td>
                            <td><?php echo esc_html($room->max_guests); ?></td>
                            <td><?php echo esc_html($room->cost_per_day .' '. ( $currency_symbol )); ?></td>
                            <td>
                                <?php if ($room->amenities): $amenities = unserialize($room->amenities); ?>
                                    <ul>
                                        <?php foreach ($amenities as $amenity_key) : ?>
                                            <li><?php echo esc_html($amenity_key); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else : ?>
                                    <span>No amenities selected</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions-dropdown">
                                    <button class="dropdown-toggle">Actions</button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a href="#" class="edit-room-button" 
                                            data-room-id="<?php echo esc_attr($room->id); ?>" 
                                            data-room-name="<?php echo esc_attr($room->name); ?>" 
                                            data-room-description="<?php echo esc_attr($room->description); ?>" 
                                            data-max-guests="<?php echo esc_attr($room->max_guests); ?>" 
                                            data-cost-per-day="<?php echo esc_attr($room->cost_per_day); ?>" 
                                            data-amenities="<?php echo esc_attr(json_encode(unserialize($room->amenities))); ?>">Edit</a>
                                        </li>
                                        <li>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="room_id" value="<?php echo esc_attr($room->id); ?>">
                                                <input type="submit" name="delete_room" class="button-link-delete" value="Delete">
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="6">No rooms found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Edit Room Modal -->
        <div id="edit-room-modal" style="display:none;">
            <h2>Edit Room</h2>
            <form method="post">
                <input type="hidden" name="room_id" id="edit-room-id">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="edit_room_name">Room Name</label></th>
                        <td><input name="room_name" type="text" id="edit_room_name" class="regular-text" required></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="edit_room_description">Room Description</label></th>
                        <td><textarea name="room_description" id="edit_room_description" class="large-text" required></textarea></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="edit_max_guests">Max Guests</label></th>
                        <td><input name="max_guests" type="number" id="edit_max_guests" min="1" class="regular-text" required></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="edit_cost_per_day">Cost per Day (<?php echo $currency_symbol; ?>)</label></th>
                        <td><input name="cost_per_day" type="number" step="0.01" id="edit_cost_per_day" class="regular-text" required></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="edit_amenities_checkboxes">Amenities</label></th>
                        <td id="edit-amenities-checkboxes"><?php display_amenities_checkboxes([]); ?></td>
                    </tr>
                </table>
                <p class="submit"><input type="submit" name="edit_room" class="button-primary" value="Save Changes"></p>
            </form>
        </div>
        
        <h2>Add a New Room</h2>
        <form method="post">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="room_name">Room Name</label></th>
                    <td><input name="room_name" type="text" id="room_name" class="regular-text" required></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="room_description">Room Description</label></th>
                    <td><textarea name="room_description" id="room_description" class="large-text" required></textarea></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="max_guests">Max Guests</label></th>
                    <td><input name="max_guests" type="number" id="max_guests" min="1" class="regular-text" required></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="cost_per_day">Cost per Day (<?php echo $currency_symbol; ?>)</label></th>
                    <td><input name="cost_per_day" type="number" step="0.01" id="cost_per_day" class="regular-text" required></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="amenities">Amenities</label></th>
                    <td><?php display_amenities_checkboxes([]); ?></td>
                </tr>
            </table>
            <p class="submit"><input type="submit" name="add_room" class="button-primary" value="Add Room"></p>
        </form>
        
    </div>
    <?php
}


function display_manage_bookings_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bookings';

    // Handle booking deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking'])) {
        $booking_id = intval($_POST['booking_id']);
        // Delete the booking from the database
        $wpdb->delete($table_name, array('id' => $booking_id));
    }

    // Retrieve all bookings from the database
    $bookings = $wpdb->get_results("SELECT * FROM $table_name");

    ?>
    <div class="wrap">
        <h1>Manage Bookings</h1>

        <h2>Existing Bookings</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Room ID</th>
                    <th>Total Cost</th>
                    <th>Paid</th>
                    <th>Actions</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Adults</th>
                    <th>Children</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($bookings) : ?>
                    <?php foreach ($bookings as $booking) : ?>
                        <tr>
                            <td><?php echo esc_html($booking->id); ?></td>
                            <td><?php echo esc_html($booking->room_id); ?></td>
                            <td><?php echo esc_html($booking->total_cost . ' '. get_currency()); ?></td>
                            <td><?php echo esc_html($booking->paid ? 'Yes' : 'No'); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking->id); ?>">
                                    <input type="submit" name="delete_booking" class="button-link-delete" value="Delete">
                                </form>
                            </td>
                            <td><?php echo esc_html($booking->name); ?></td>
                            <td><?php echo esc_html($booking->email); ?></td>
                            <td><?php echo esc_html($booking->phone); ?></td>
                            <td><?php echo esc_html($booking->adults); ?></td>
                            <td><?php echo esc_html($booking->children); ?></td>
                            <td><?php echo esc_html($booking->start_date); ?></td>
                            <td><?php echo esc_html($booking->end_date); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="10">No bookings found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function get_predefined_amenities() {
    return [
        'air_conditioning' => 'Air Conditioning',
        'bath' => 'Bath',
        'shower' => 'Shower',
        'balcony' => 'Balcony',
        'breakfast' => 'Breakfast Included',
        'pool_view' => 'Pool View',
        'free_wifi' => 'Free WiFi',
        // Add more amenities as needed
    ];
}

function display_amenities_checkboxes($room = []) {
    $amenities = get_predefined_amenities();
    $selected_amenities = isset($room['amenities']) ? $room['amenities'] : [];

    echo '<h3>Select Amenities:</h3>';
    foreach ($amenities as $key => $label) {
        $checked = in_array($key, $selected_amenities) ? 'checked' : '';
        echo '<label>';
        echo '<input type="checkbox" name="amenities[]" value="' . esc_attr($key) . '" ' . $checked . '> ';
        echo esc_html($label);
        echo '</label><br>';
    }
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

function display_calendar_id_field() {
    $options = get_option('booking_settings');
    $calendar_id = isset($options['calendar_id']) ? esc_attr($options['calendar_id']) : '';
    ?>
    <input type="text" name="booking_settings[calendar_id]" value="<?php echo $calendar_id; ?>" class="regular-text">
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

function display_currency_field() {
    $options = get_option('booking_settings');
    $currency = isset($options['currency']) ? $options['currency'] : 'USD';
    ?>
    <select name="booking_settings[currency]">
        <option value="USD" <?php selected($currency, 'USD'); ?>>USD ($)</option>
        <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR (€)</option>
        <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP (£)</option>
        <option value="JPY" <?php selected($currency, 'JPY'); ?>>JPY (¥)</option>
    </select>
    <?php
}

add_action('admin_menu', 'add_admin_menu');
add_action('admin_init', 'register_booking_settings');