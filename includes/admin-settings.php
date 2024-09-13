<?php
// Prevent direct access
defined('ABSPATH') or die('No script please!');

// Add "Booking Settings" menu and submenus
function add_admin_menu() {
    // Add the main menu page
    add_menu_page(
        'Booking System Settings',    // Page title
        'Booking Settings',           // Menu title
        'manage_options',             // Capability required
        'booking-settings',           // Menu slug for main page
        'booking_settings_page'       // Callback function for the main page (general settings)
    );

    // Add "Settings" submenu under "Booking Settings"
    add_submenu_page(
        'booking-settings', 
        'Settings',                   // Page title
        'Settings',                   // Menu title
        'manage_options', 
        'booking-settings',           // This keeps the same slug to show this page as the main page
        'booking_settings_page'       // Callback function for the "Settings" page
    );

    // Add "Manage Rooms" submenu under "Booking Settings"
    add_submenu_page(
        'booking-settings', 
        'Manage Rooms', 
        'Manage Rooms', 
        'manage_options', 
        'manage-rooms',               // Unique slug for "Manage Rooms"
        'manage_rooms_page'           // Unique callback function for "Manage Rooms"
    );

    // Add "Manage Bookings" submenu under "Booking Settings"
    add_submenu_page(
        'booking-settings', 
        'Manage Bookings', 
        'Manage Bookings', 
        'manage_options', 
        'manage-bookings',            // Unique slug for "Manage Bookings"
        'display_manage_bookings_page'// Callback function for "Manage Bookings"
    );
}

// Register and display booking settings
function register_booking_settings() {
    // Register the settings
    register_setting('booking_settings_group', 'booking_settings', array(
        'sanitize_callback' => 'sanitize_booking_settings'
    ));

    // Add the General Settings section
    add_settings_section(
        'general_settings',
        'General Settings',
        null,
        'booking-settings'
    );

    // Add the fields for the General Settings section
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

// The callback function for the "Settings" page
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

function manage_rooms_page() {
    global $wpdb;
    $currency_symbol = get_currency();
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = isset($_POST['room_name']) ? sanitize_text_field($_POST['room_name']) : '';
        $description = isset($_POST['room_description']) ? sanitize_textarea_field($_POST['room_description']) : '';
        $max_guests = isset($_POST['max_guests']) ? intval($_POST['max_guests']) : 0;
        $cost_per_day = isset($_POST['cost_per_day']) ? floatval($_POST['cost_per_day']) : 0.00;
        $size = isset($_POST['size']) ? sanitize_text_field($_POST['size']) : '';
        $amenities = isset($_POST['amenities']) ? $_POST['amenities'] : [];
        
        if (isset($_POST['add_room'])) {
            handle_room_insert($_POST);
        } elseif (isset($_POST['edit_room'])) {
            handle_room_update($_POST);
        } elseif (isset($_POST['delete_room'])) {
            delete_room($_POST['room_id']);
        } elseif (isset($_POST['add_amenity'])) {
            add_amenity($_POST['new_amenity']);
        } elseif (isset($_POST['delete_amenity'])) {
            delete_amenity($_POST['delete_amenity']);
        }
    }

    render_tabs();
    render_add_room_form($currency_symbol);
    render_manage_amenities();
    // Fetch and display rooms
    $rooms = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}reservemate_rooms");
    render_existing_rooms($rooms, $currency_symbol);
}

function handle_room_insert($data) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'reservemate_rooms';
    $room_data = [
        'name' => sanitize_text_field($data['room_name']),
        'description' => sanitize_textarea_field($data['room_description']),
        'max_guests' => intval($data['max_guests']),
        'cost_per_day' => floatval($data['cost_per_day']),
        'size' => sanitize_text_field($data['size']),
    ];
    
    $wpdb->insert($table_name, $room_data);
    $room_id = $wpdb->insert_id;

    if ($room_id) {
        // Handle amenities and image uploads
        insert_room_amenities($room_id, $data['amenities']);
        handle_image_upload($room_id);
    }
}

function handle_room_update($data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservemate_rooms';
    
    $room_data = [
        'name' => sanitize_text_field($data['room_name']),
        'description' => sanitize_textarea_field($data['room_description']),
        'max_guests' => intval($data['max_guests']),
        'cost_per_day' => floatval($data['cost_per_day']),
        'size' => sanitize_text_field($data['size']),
    ];
    $data['amenities'] = isset($data['amenities']) ? $data['amenities'] : [];
    
    // Update room information
    $wpdb->update($table_name, $room_data, ['id' => intval($data['room_id'])]);

    // Update room amenities
    update_room_amenities($data['room_id'], $data['amenities']);

    // Handle new image uploads
    handle_image_upload($data['room_id']);
    
    // Handle image removal
    if (!empty($data['remove_images'])) {
        remove_room_images($data['remove_images']);
    }
}

function delete_room($room_id) {
    global $wpdb;
    $wpdb->delete($wpdb->prefix . 'reservemate_rooms', ['id' => intval($room_id)]);
}

function add_amenity($amenity_name) {
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'reservemate_amenities', ['amenity_name' => sanitize_text_field($amenity_name)]);
}

function delete_amenity($amenity_id) {
    global $wpdb;
    $wpdb->delete($wpdb->prefix . 'reservemate_amenities', ['id' => intval($amenity_id)]);
}

function insert_room_amenities($room_id, $amenities) {
    global $wpdb;
    foreach ($amenities as $amenity_id) {
        $wpdb->insert($wpdb->prefix . 'reservemate_room_amenities', ['room_id' => $room_id, 'amenity_id' => intval($amenity_id)]);
    }
}

function update_room_amenities($room_id, $amenities = []) {
    global $wpdb;
    $room_amenities_table = $wpdb->prefix . 'reservemate_room_amenities';

    // First, delete existing amenities for the room
    $wpdb->delete($room_amenities_table, ['room_id' => $room_id]);

    // Then, insert the new amenities
    if (!empty($amenities) && is_array($amenities)) {
        foreach ($amenities as $amenity_id) {
            $wpdb->insert($room_amenities_table, [
                'room_id' => $room_id,
                'amenity_id' => intval($amenity_id)
            ]);
        }
    }
}

function handle_image_upload($room_id) {
    if (isset($_FILES['room_images']) && !empty($_FILES['room_images']['name'][0])) {
        foreach ($_FILES['room_images']['name'] as $key => $image_name) {
            if ($_FILES['room_images']['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $image_name,
                    'type' => $_FILES['room_images']['type'][$key],
                    'tmp_name' => $_FILES['room_images']['tmp_name'][$key],
                    'error' => $_FILES['room_images']['error'][$key],
                    'size' => $_FILES['room_images']['size'][$key]
                ];
                $image_id = upload_room_image($file);
                if ($image_id) {
                    insert_room_image($room_id, $image_id);
                }
            }
        }
    }
}

function insert_room_image($room_id, $image_id) {
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'reservemate_room_images', ['room_id' => $room_id, 'image_id' => $image_id]);
}

function remove_room_images($image_ids) {
    global $wpdb;
    $image_ids_array = explode(',', sanitize_text_field($image_ids));

    foreach ($image_ids_array as $image_id) {
        // Remove image from the `reservemate_room_images` table
        $wpdb->delete($wpdb->prefix . 'reservemate_room_images', ['id' => intval($image_id)]);
        
        // Optionally, you can remove the physical image from the uploads directory as well:
        $attachment = get_post($image_id);
        if ($attachment) {
            wp_delete_attachment($image_id, true);
        }
    }
}

function render_tabs() {
    ?>
    <div class="wrap">
        <h1>Manage Rooms</h1>
        <div class="tabs">
            <button class="tab-button active" data-target="#add-room-tab">Add a New Room</button>
            <button class="tab-button" data-target="#existing-rooms-tab">Existing Rooms</button>
            <button class="tab-button" data-target="#manage-amenities-tab">Manage Amenities</button>
        </div>
    </div>
    <?php
}

function render_add_room_form($currency_symbol) {
    ?>
    <div id="add-room-tab" class="tab-content active">
        <h2>Add a New Room</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="room_id" id="room-id">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="room_name">Room Name</label><i class="star-required">*</i></th>
                    <td><input name="room_name" type="text" id="room_name" class="regular-text" required></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="room_description">Room Description / Additional Information</label></th>
                    <td><textarea name="room_description" id="room_description" class="large-text"></textarea></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="max_guests">Max Guests</label><i class="star-required">*</i></th>
                    <td><input name="max_guests" type="number" id="max_guests" min="1" class="regular-text" required></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="cost_per_day">Cost per Night (<?php echo $currency_symbol; ?>)</label><i class="star-required">*</i></th>
                    <td><input name="cost_per_day" type="number" step="0.01" id="cost_per_day" class="regular-text" required></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="size">Size (m&sup2)</label></th>
                    <td><input name="size" type="text" id="size" class="regular-text"></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="amenities">Amenities</label></th>
                    <td><?php display_amenities_checkboxes([]); ?></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="room_images">Upload Images</label></th>
                    <td>
                        <div id="drop-area">
                            <p>Drag and drop images here, or <span id="file-selector">browse</span></p>
                            <input name="room_images[]" type="file" id="room_images" multiple hidden>
                            <div id="image-preview"></div>
                        </div>
                    </td>
                </tr>
            </table>
            <p class="submit"><input type="submit" name="add_room" class="button-primary" value="Add Room"></p>
        </form>
    </div>
    <?php
}

function render_existing_rooms($rooms, $currency_symbol) {
    global $wpdb;
    $images_table = $wpdb->prefix . 'reservemate_room_images';
    ?>
    <div id="existing-rooms-tab" class="tab-content">
        <h2>Existing Rooms</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th colspan="2">ID</th>
                    <th colspan="4">Name</th>
                    <th colspan="3">Details</th>
                    <th colspan="3">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rooms) : ?>
                    <?php foreach ($rooms as $room) : ?>
                        <?php $amenities = get_room_amenities($room->id); ?>
                        <tr class="room-summary">
                            <td colspan="2"><?php echo esc_html($room->id); ?></td>
                            <td colspan="4"><?php echo esc_html($room->name); ?></td>
                            <td colspan="3">
                                <button class="toggle-details" data-room-id="<?php echo esc_attr($room->id); ?>"><i class="fa fa-arrow-down" aria-hidden="true"></i></button>
                            </td>
                            <td colspan="3">
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
                                                data-room-size="<?php echo esc_attr($room->size); ?>" 
                                                data-amenities="<?php echo esc_attr(json_encode($amenities)); ?>">Edit</a>
                                        </li>
                                        <li>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="room_id" value="<?php echo esc_attr($room->id); ?>">
                                                <input type="submit" name="delete_room" class="button-link-delete" value="Delete" onclick="if (!confirm('Are you sure?')) { return false }">
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <tr class="room-details" id="details-<?php echo esc_attr($room->id); ?>" style="display: none;">
                            <td colspan="12">
                                <div>
                                    <div class="room-details-flex"><strong>Max guests:</strong><span class="room-data"><?php echo esc_html($room->max_guests); ?></span></div>
                                    <div class="room-details-flex"><strong>Cost per Night:</strong><span class="room-data"><?php echo esc_html($room->cost_per_day . ' ' . $currency_symbol); ?></span></div>
                                    <div class="room-details-flex"><strong>Size:</strong><span class="room-data"><?php echo esc_html($room->size) . 'm&sup2;'; ?></span></div>
                                    <div class="room-details-flex">
                                        <strong>Amenities:</strong>
                                        <div class="room-data">
                                            <?php if ($amenities): ?>
                                                <ul>
                                                    <?php foreach ($amenities as $amenity) : ?>
                                                        <li><?php echo esc_html($amenity); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else : ?>
                                                <span>No amenities selected</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="room-details-flex"><strong>Description:</strong><span class="room-data"><?php echo esc_html($room->description); ?></span></div>
                                </div>
                                <div>
                                    <?php
                                    $room_images = $wpdb->get_results($wpdb->prepare(
                                        "SELECT image_id FROM $images_table WHERE room_id = %d",
                                        $room->id
                                    ));
                                    if ($room_images) :
                                        foreach ($room_images as $room_image) :
                                            $image_url = wp_get_attachment_url($room_image->image_id);
                                            ?>
                                            <img src="<?php echo esc_url($image_url); ?>" width="100" height="auto" />
                                        <?php endforeach;
                                    else :
                                        echo 'No images';
                                    endif;
                                    ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="12">No rooms found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php render_edit_modal($currency_symbol, $rooms); ?>
    <?php
}

function render_edit_modal($currency_symbol, $rooms) {
    ?>
    <?php if($rooms) : ?>
    <div class="modal-overlay"></div>
    <div id="edit-room-modal">
        <button class="close-button">&times;</button>
        <h2>Edit Room</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="room_id" id="edit-room-id">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="edit_room_name">Room Name</label></th>
                    <td><input name="room_name" type="text" id="edit_room_name" class="regular-text" required></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="edit_room_description">Room Description / Additional Information</label></th>
                    <td><textarea name="room_description" id="edit_room_description" class="large-text"></textarea></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="edit_max_guests">Max Guests</label></th>
                    <td><input name="max_guests" type="number" id="edit_max_guests" min="1" class="regular-text" required></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="edit_cost_per_day">Cost per Night (<?php echo $currency_symbol; ?>)</label></th>
                    <td><input name="cost_per_day" type="number" step="0.01" id="edit_cost_per_day" class="regular-text" required></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="edit_room_size">Size (m&sup2)</label></th>
                    <td><input name="size" type="text" id="edit_room_size" class="regular-text"></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="edit_amenities_checkboxes">Amenities</label></th>
                    <td id="edit_amenities_checkboxes"></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="edit_room_images">Existing Images</label></th>
                    <td id="existing-images-container">
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="upload_new_images">Upload New Images</label></th>
                    <td><input name="room_images[]" type="file" id="upload_new_images" multiple></td>
                </tr>
            </table>
            <input type="hidden" name="remove_images" id="remove_images_field">
            <p class="submit"><input type="submit" name="edit_room" class="button-primary" value="Save Changes"></p>
        </form>
    </div>
    <?php endif; ?>
    <?php
}

function render_manage_amenities() {
    global $wpdb;
    ?>
    <div id="manage-amenities-tab" class="tab-content">
        <div class="add-amenity">
            <h2>Add New Amenity</h2>
            <form method="post">
                <input type="text" name="new_amenity" placeholder="Enter new amenity" required>
                <button type="submit" name="add_amenity" class="button-primary">Add Amenity</button>
            </form>
        
            <h3>Existing Amenities</h3>
            <ul>
                <?php
                $existing_amenities = $wpdb->get_results("SELECT * FROM wp_reservemate_amenities ORDER BY amenity_name ASC");
                if ($existing_amenities):
                    foreach ($existing_amenities as $amenity): ?>
                        <li>
                            <form method="post">
                                <input type="hidden" name="delete_amenity_id" value="<?php echo $amenity->id; ?>">
                                <button type="submit" name="delete_amenity" class="delete-button">x</button>
                                <span><?php echo $amenity->amenity_name; ?></span>
                            </form>
                        </li>
                    <?php endforeach;
                else: ?>
                    <li>No amenities available</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <?php
}

function display_manage_bookings_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservemate_bookings';

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
                                    <input type="submit" name="delete_booking" class="button-link-delete" value="Delete" onclick="if (!confirm('Are you sure?')) { return false }">
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

// Handle file upload
function upload_room_image($file) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    $upload = wp_handle_upload($file, array('test_form' => false));

    if (isset($upload['error'])) {
        error_log('Image upload error: ' . $upload['error']);
        return false;
    }

    if (isset($upload['file'])) {
        $wp_filetype = wp_check_filetype(basename($upload['file']), null);
        $attachment = array(
            'guid' => $upload['url'],
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($upload['file']),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attachment_id = wp_insert_attachment($attachment, $upload['file']);
        if (is_wp_error($attachment_id)) {
            error_log('Attachment insertion error: ' . $attachment_id->get_error_message());
            return false;
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
        if (is_wp_error($metadata)) {
            error_log('Metadata generation error: ' . $metadata->get_error_message());
            return false;
        }

        wp_update_attachment_metadata($attachment_id, $metadata);

        return $attachment_id;
    }

    error_log('Unknown error in upload_room_image function.');
    return false;
}

function display_room_images($room_id) {
    $images = get_room_images($room_id);
    if ($images) {
        echo '<div class="existing-images">';
        foreach ($images as $image) {
            $image_url = wp_get_attachment_url($image->image_id);
            echo '<div class="image-item">';
            echo '<img src="' . esc_url($image_url) . '" width="100" height="auto">';
            // Add X button for deletion
            echo '<span class="remove-image" data-image-id="' . esc_attr($image->image_id) . '">&times;</span>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p>No images found for this room.</p>';
    }
}

function get_room_images($room_id) {
    global $wpdb;
    $images_table = $wpdb->prefix . 'reservemate_room_images';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT image_id FROM $images_table WHERE room_id = %d",
        $room_id
    ));
}

function get_predefined_amenities() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'reservemate_amenities';

    // Get all amenities from the amenities table
    $results = $wpdb->get_results("SELECT id, amenity_name FROM $table_name", ARRAY_A);

    // Create an associative array of amenity ID => amenity name
    $amenities = [];
    if ($results) {
        foreach ($results as $amenity) {
            $amenities[$amenity['id']] = $amenity['amenity_name'];
        }
    }

    return $amenities;
}

function get_room_amenities($room_id) {
    global $wpdb;
    $results = $wpdb->get_col($wpdb->prepare(
        "SELECT amenity_name FROM {$wpdb->prefix}reservemate_room_amenities ra
        JOIN {$wpdb->prefix}reservemate_amenities a ON ra.amenity_id = a.id
        WHERE ra.room_id = %d",
        $room_id
    ));
    return $results ? $results : [];
}

function save_room_amenities($room_id) {
    global $wpdb;

    // Remove all current amenities for the room
    $table_name = $wpdb->prefix . 'reservemate_room_amenities';
    $wpdb->delete($table_name, ['room_id' => $room_id]);

    // Insert new amenities
    if (!empty($_POST['amenities'])) {
        $amenities = $_POST['amenities'];
        foreach ($amenities as $amenity_id) {
            $wpdb->insert($table_name, [
                'room_id' => $room_id,
                'amenity_id' => $amenity_id
            ]);
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

function display_amenities_checkboxes($room_id = null) {
    global $wpdb;
    
    // Fetch predefined amenities
    $amenities = get_predefined_amenities();
    
    // Fetch selected amenities for the current room (if any)
    $selected_amenities = [];
    if ($room_id) {
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT amenity_id FROM {$wpdb->prefix}reservemate_room_amenities WHERE room_id = %d",
            $room_id
        ));
        $selected_amenities = $results ? $results : [];
    }

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

