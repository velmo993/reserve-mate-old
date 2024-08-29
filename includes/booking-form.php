<?php
// Prevent direct access
defined('ABSPATH') or die('No script please!');

// Include the Google Calendar functions
require_once(plugin_dir_path(__FILE__) . 'google-calendar.php');

// Register shortcode for booking form
function display_booking_form() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adults'])) {
        // Process the form submission
        return handle_room_search();
    }
    ob_start();
    ?>
    <form id="booking-form" method="post" ?>
        <input type="hidden" name="action" value="search_rooms">
        <label for="adults">Adults:</label>
        <input type="number" id="adults" name="adults" min="1" required>
    
        <label for="children">Children:</label>
        <input type="number" id="children" name="children" min="0" required>
    
        <label for="start-date">Arrival:</label>
        <input type="text" id="start-date" name="start-date" required>
    
        <label for="end-date">Departure:</label>
        <input type="text" id="end-date" name="end-date" required>
    
        <input type="submit" value="Search Available Rooms">
    </form>

    <?php
    return ob_get_clean();
}

function get_room_details($room_id) {
    global $wpdb;
    $room = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}rooms WHERE id = %d", $room_id), ARRAY_A);
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

function handle_room_search() {
    // Process form submission and display available rooms
    $adults = intval($_POST['adults']);
    $children = intval($_POST['children']);
    $start_date = sanitize_text_field($_POST['start-date']);
    $end_date = sanitize_text_field($_POST['end-date']);

    $available_rooms = search_available_rooms($adults, $children, $start_date, $end_date);

    ob_start();
    if ($available_rooms) {
        $currency_symbol = get_option('currency_symbol', '$'); // Default is $ if not set
        echo '<h3>Available Rooms:</h3>';
        echo '<form method="post" id="select-room-form">';
        echo '<div class="form-wrap">';
        echo '<input type="hidden" name="adults" value="' . esc_attr($adults) . '">';
        echo '<input type="hidden" name="children" value="' . esc_attr($children) . '">';
        echo '<input type="hidden" name="start-date" value="' . esc_attr($start_date) . '">';
        echo '<input type="hidden" name="end-date" value="' . esc_attr($end_date) . '">';
        
        foreach ($available_rooms as $room) {
            echo '<div class="available-room">';
            
            if ($room['is_booked']) {
                echo '<div class="room-name-radio">';
                echo '<input type="radio" id="room-' . esc_attr($room['id']) . '" name="room-id" value="' . esc_attr($room['id']) . '" disabled>';
                echo '<label for="room-' . esc_attr($room['id']) . '">' . esc_html($room['name']) . '</label>';
                echo '</div>';
                
                echo '<div class="room-description">' . esc_html($room['description']) . '</div>';
                echo '<div class="room-availability">';
                echo '<span class="room-status">Booked, available from ' . esc_html($room['next_available_date']) . '</span>';
                echo '</div>';
            } else {
                echo '<div class="room-name-radio">';
                echo '<input type="radio" id="room-' . esc_attr($room['id']) . '" name="room-id" value="' . esc_attr($room['id']) . '" required>';
                echo '<label for="room-' . esc_attr($room['id']) . '">' . esc_html($room['name']) . '</label>';
                echo '</div>';
                
                echo '<div class="room-cost">';
                
                echo '<span class="cost-per-day">'
                . esc_html($currency_symbol) .'<i>' . esc_html($room['cost_per_day']) . '</i>' . '/' . 'day';
                echo '<span>';
                echo '</div>';
                echo '<div class="room-description">' . esc_html($room['description']) . '</div>';
              
                if ($room['amenities']) {
                    $amenities = unserialize($room['amenities']);
                    echo '<div class="room-amenities">';
                    echo '<ul class="available-room-amenities">';
                    foreach ($amenities as $amenity_key) {
                        $formatted_name = format_amenity_name($amenity_key);
                        $icon_class = get_amenity_icon($amenity_key);
                        echo '<li>';
                        echo '<i class="' . esc_attr($icon_class) . '" title="' . esc_attr($formatted_name) . '"></i>';
                        echo '<span class="amenity-name">' . esc_html($formatted_name) . '</span>'; // Optional: show the name next to the icon
                        echo '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                }
            }
            
            echo '</div>'; // End of available-room
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
        echo '</div>';
        echo '</form>';
    } else {
        echo 'No available rooms found for the selected dates and number of people.';
    }

    return ob_get_clean();
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
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $days_booked = $interval->days;

    // Retrieve room details from the database
    $room = get_room_details($room_id);
    $total_cost = $days_booked * floatval($room['cost_per_day']);
    
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
                'total_cost' => $total_cost,
                'paid' => 0,
            ),
            array(
                '%d',  // room_id
                '%s',  // name
                '%s',  // email
                '%s',  // phone
                '%d',  // adults
                '%d',  // children
                '%s',  // start_date
                '%s',  // end_date
                '%f',  // total cost
                '%d',  // paid
            )
        );
        echo 'Booking successfully added to Google Calendar!';
    } else {
        echo 'Failed to add booking to Google Calendar.';
    }
}

function get_amenity_icon($amenity_key) {
    $amenity_symbols = [
        'air_conditioning' => 'fa fa-snowflake-o',
        'balcony' => 'fa fa-tree',
        'bath' => 'fa fa-bath',
        'breakfast' => 'fa fa-cutlery',
        'free_wifi' => 'fa fa-wifi',
        'pool_view' => 'fa fa-window-maximize',
        'shower' => 'fa fa-shower',
    ];
    
     return isset($amenity_symbols[$amenity_key]) ? $amenity_symbols[$amenity_key] : '❓'; // Default symbol if not found
}

function format_amenity_name($amenity_key) {
    return ucwords(str_replace('_', ' ', $amenity_key));
}

function enqueue_custom_styles() {
    ?>
    <style>
    /* Custom styling for the booking form */
    #booking-form {
        max-width: 600px;
        margin: 0 auto;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
        background-color: #f9f9f9;
    }

    /* Style labels */
    #booking-form label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
    }

    /* Style inputs */
    #booking-form input[type="number"],
    #booking-form input[type="text"],
    #booking-form input[type="email"],
    #booking-form input[type="tel"] {
        width: calc(100% - 22px);
        padding: 10px;
        margin-bottom: 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 16px;
    }

    /* Style submit button */
    #booking-form input[type="submit"] {
        background-color: #007cba;
        color: #fff;
        border: none;
        padding: 12px 20px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
    }

    #booking-form input[type="submit"]:hover {
        background-color: #005a8d;
    }
    
    
    /* Custom styling for the select-room-form */
    
    #select-room-form {
        max-width: 640px;
        margin: 0 auto;
        padding: 1rem 0;
        border: 1px solid #ddd;
        border-radius: 8px;
        background-color: #f9f9f9;
    }
    
    #select-room-form .form-wrap {
        padding: 20px;
    }

    #select-room-form .form-wrap .available-room {
        display: flex;
        align-items: center;
        flex-direction: column;
        margin-bottom: 3rem;
    }
    
    #select-room-form .form-wrap .available-room > *:not(:first-child) {
        padding: 1rem;
        font-size: 1.2rem;
    }
    
    #select-room-form .form-wrap .available-room .room-name-radio {
        display: flex;
        width: 100%;
        justify-content: space-between;
        flex-direction: row-reverse;
        padding-bottom: 1.2rem;
        border-bottom: 2px solid;
        margin: 1rem 0;
    }
    
    #select-room-form .form-wrap .available-room .room-cost {
        width: 100%;
        text-align: right;
    }
    
    #select-room-form .form-wrap .available-room .room-cost .cost-per-day i {
        font-size: 1.2rem;
        font-weight: 500;
        font-style: normal;
    }
    
    #select-room-form .form-wrap .available-room .room-description {
        width: 100%;
    }
    
    #select-room-form .form-wrap .available-room input[type="radio"] {
        transform: scale(1.5); /* Adjust the scale as needed */
    }
    
    #select-room-form .form-wrap .available-room label {
        width: 50%;
        text-align: right;
        font-size: 1.3rem;
        font-weight: bold;
    }
    
    #select-room-form .form-wrap .available-room .inline-flex {
        display: inline-flex;
        flex-direction: row-reverse;
        width: 100%;
        justify-content: space-between;
    }

    /* Style inputs */
    #select-room-form .form-wrap div input[type="number"],
    #select-room-form .form-wrap div input[type="text"],
    #select-room-form .form-wrap div input[type="email"],
    #select-room-form .form-wrap div input[type="tel"] {
        width: calc(100% - 22px);
        padding: 10px 20px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 16px;
    }

    /* Style submit button */
    #select-room-form .form-wrap input[type="submit"] {
        background-color: #007cba;
        color: #fff;
        border: none;
        padding: 12px 20px;
        margin-top: 20px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
    }

    #select-room-form .form-wrap input[type="submit"]:hover {
        background-color: #005a8d;
    }
    
    .room-amenities {
        width: 100%;
    }
    
    
    .available-room-amenities {
        list-style: none;
        display: flex;
        width: 100%;
        justify-content: space-evenly;
        flex-wrap: wrap;
        margin: 0;
        padding: 0;
    }
    
    .available-room-amenities li {
        position: relative;
    }
    
    .available-room-amenities li i {
        cursor: pointer;
        display: inline-block;
        font-size: 24px; /* Adjust size of the icon as needed */
    }
    
    .available-room-amenities li span.amenity-name {
        display: none; /* Hide text by default */
        position: absolute;
        bottom: 125%; /* Adjust based on your layout */
        left: 50%;
        transform: translateX(-50%);
        background-color: #333;
        color: #fff;
        padding: 5px;
        border-radius: 5px;
        white-space: nowrap;
        z-index: 10;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease, visibility 0.3s ease;
        visibility: hidden;
    }
    
    .available-room-amenities li span.amenity-name::before {
        content: '';
        position: absolute;
        bottom: -5px; /* Adjust based on your layout */
        left: 50%;
        transform: translateX(-50%);
        border-width: 5px;
        border-style: solid;
        border-color: #333 transparent transparent transparent;
        z-index: 10;
    }
    
    .available-room-amenities li i.active + span.amenity-name {
        display: inline-block; /* Show the text when icon is active */
        opacity: 1;
        pointer-events: auto;
        visibility: visible;
    }
    
    </style>
    <?php
}

add_action('wp_head', 'enqueue_custom_styles');
add_shortcode('booking_form', 'display_booking_form');


