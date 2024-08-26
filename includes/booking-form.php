<?php
// Prevent direct access
defined('ABSPATH') or die('No script please!');

// Include the Google Calendar functions
require_once(plugin_dir_path(__FILE__) . 'google-calendar.php');

// Register shortcode for booking form
function display_booking_form() {
    ob_start();
    ?>
    <form id="booking-form" method="post">
        <label for="adults">Number of Adults:</label>
        <input type="number" id="adults" name="adults" min="1" required>

        <label for="children">Number of Children:</label>
        <input type="number" id="children" name="children" min="0" required>

        <label for="start-date">Start Date:</label>
        <input type="date" id="start-date" name="start-date" required>

        <label for="end-date">End Date:</label>
        <input type="date" id="end-date" name="end-date" required>

        <input type="submit" value="Search Available Rooms">
    </form>
    <?php
    return ob_get_clean();
}

function get_room_details($room_id) {
    global $wpdb;
    $room = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_rooms WHERE room_id = %d", $room_id), ARRAY_A);
    return $room;
}

add_shortcode('booking_form', 'display_booking_form');

function search_available_rooms($adults, $children, $start_date, $end_date) {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'rooms';
    $bookings_table = $wpdb->prefix . 'bookings';
    $total_guests = $adults + $children;

    // Get all rooms that can accommodate the total number of guests
    $rooms = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $rooms_table WHERE max_guests >= %d",
        $total_guests
    ), ARRAY_A);

    // Get bookings that overlap with the selected date range
    $booked_rooms = $wpdb->get_results($wpdb->prepare(
        "SELECT room_id, MIN(start_date) as next_available_date FROM $bookings_table 
        WHERE (
            (start_date <= %s AND end_date >= %s) OR
            (start_date >= %s AND start_date <= %s)
        )
        GROUP BY room_id",
        $end_date, $start_date, $start_date, $end_date
    ), ARRAY_A);

    // Map booked rooms by room_id
    $booked_rooms_map = [];
    foreach ($booked_rooms as $booked_room) {
        $booked_rooms_map[$booked_room['room_id']] = $booked_room['next_available_date'];
    }

    // Add next available date to each room if booked
    foreach ($rooms as &$room) {
        if (isset($booked_rooms_map[$room['id']])) {
            $room['is_booked'] = true;
            $room['next_available_date'] = $booked_rooms_map[$room['id']];
        } else {
            $room['is_booked'] = false;
        }
    }

    return $rooms;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adults'])) {
    $adults = intval($_POST['adults']);
    $children = intval($_POST['children']);
    $start_date = sanitize_text_field($_POST['start-date']);
    $end_date = sanitize_text_field($_POST['end-date']);

    $available_rooms = search_available_rooms($adults, $children, $start_date, $end_date);

    if ($available_rooms) {
        echo '<h3>Available Rooms:</h3>';
        echo '<form method="post" id="select-room-form">';
        echo '<input type="hidden" name="adults" value="' . esc_attr($adults) . '">';
        echo '<input type="hidden" name="children" value="' . esc_attr($children) . '">';
        echo '<input type="hidden" name="start-date" value="' . esc_attr($start_date) . '">';
        echo '<input type="hidden" name="end-date" value="' . esc_attr($end_date) . '">';
        
        foreach ($available_rooms as $room) {
        echo '<div>';
        if ($room['is_booked']) {
            echo '<input type="radio" id="room-' . esc_attr($room['id']) . '" name="room-id" value="' . esc_attr($room['id']) . '" disabled>';
            echo '<label for="room-' . esc_attr($room['id']) . '">' . esc_html($room['name']) . ' - ' . esc_html($room['description']) . ' (Booked, available from ' . esc_html($room['next_available_date']) . ')</label>';
        } else {
            echo '<input type="radio" id="room-' . esc_attr($room['id']) . '" name="room-id" value="' . esc_attr($room['id']) . '" required>';
            echo '<label for="room-' . esc_attr($room['id']) . '">' . esc_html($room['name']) . ' - ' . esc_html($room['description']) . '</label>';
        }
        echo '</div>';
    }
        
        // Add fields for name, email, and phone number
        echo '<div>';
        echo '<label for="name">Name:</label>';
        echo '<input type="text" id="name" name="name" required>';
        echo '</div>';

        echo '<div>';
        echo '<label for="email">Email Address:</label>';
        echo '<input type="email" id="email" name="email" required>';
        echo '</div>';

        echo '<div>';
        echo '<label for="phone">Phone Number:</label>';
        echo '<input type="tel" id="phone" name="phone" required>';
        echo '</div>';
        
        echo '<input type="submit" value="Book Selected Room">';
        echo '</form>';
    } else {
        echo 'No available rooms found for the selected dates and number of people.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room-id'])) {
    $room_id = sanitize_text_field($_POST['room-id']);
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    $adults = intval($_POST['adults']);
    $children = intval($_POST['children']);
    $start_date = sanitize_text_field($_POST['start-date']);
    $end_date = sanitize_text_field($_POST['end-date']);

    // Retrieve room details from the database
    $room = get_room_details($room_id);

    // Prepare event details for Google Calendar
    $event_details = array(
        'summary' => 'Room Booking: ' . $room['name'],
        'description' => 'Room: ' . $room['name'] . 'Adults: ' . $adults . 'Children: ' . $children,
        'start' => $start_date . 'T14:00:00', // Assuming check-in time is 14:00
        'end' => $end_date . 'T12:00:00', // Assuming check-out time is 12:00
    );

    // Call your existing function to sync with Google Calendar
    $result = sync_with_google_calendar($event_details);

    if ($result) {
        // Save booking to the database
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'bookings',
            array(
                'room_id' => $room_id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'adults' => $adults,
                'children' => $children,
                'start_date' => $start_date,
                'end_date' => $end_date,
            ),
            array(
                '%d',  // room_id
                '%s',  // name
                '%s',  // email
                '%s',  // phone
                '%d',  // adults
                '%d',  // children
                '%s',  // start_date
                '%s'   // end_date
            )
        );
        echo 'Booking successfully added to Google Calendar!';
    } else {
        echo 'Failed to add booking to Google Calendar.';
    }
}
