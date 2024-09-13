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
    $room = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}reservemate_rooms WHERE id = %d", $room_id), ARRAY_A);
    return $room;
}

add_shortcode('booking_form', 'display_booking_form');

function search_available_rooms($adults, $children, $start_date, $end_date) {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'reservemate_rooms';
    $bookings_table = $wpdb->prefix . 'reservemate_bookings';
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
                
                if($room['description']) {
                    echo '<div class="room-description">' . esc_html($room['description']) . '</div>';
                }
                echo '<div class="room-availability">';
                echo '<span class="room-status">Booked, available from ' . esc_html($room['next_available_date']) . '</span>';
                echo '</div>';
            } else {
                echo '<div class="room-name-radio">';
                    echo '<input type="radio" id="room-' . esc_attr($room['id']) . '" name="room-id" value="' . esc_attr($room['id']) . '" required>';
                    echo '<label for="room-' . esc_attr($room['id']) . '">' . esc_html($room['name']) . '</label>';
                echo '</div>';
                echo '<div class="room-container">';
                    $room_images = get_room_pictures($room['id']);
                    if ($room_images) {
                        echo '<div class="room-images">';
                        foreach ($room_images as $image) {
                            $image_url = wp_get_attachment_url($image->image_id);
                            echo '<img class="room-img" src="' . esc_url($image_url) . '" alt="' . esc_attr($room['name']) . '">';
                        }
                        echo '</div>';
                    } else {
                        echo '<div class="room-images">';
                            echo '<img class="room-img" src="' . esc_url('https://placehold.co/140x140?text=Image+not+available') . '" alt="Placeholder">';
                            echo '<img class="room-img" src="' . esc_url('https://placehold.co/140x140?text=Image+not+available') . '" alt="Placeholder">';
                        echo '</div>';
                    }
                    echo '<div class="room-details">';
                        echo '<div class="room-size-guests">';
                            echo '<div class="room-size">' . esc_html($room['size']) . 'm&sup2;' . '</div>';
                            echo '<div class="room-max-guests"> ' . esc_html($room['max_guests']) . ' guests' . ' ' . '</div>';
                        echo '</div>';
                    echo '</div>';
                echo '</div>';
                $amenities = get_all_room_amenities($room['id']);
                if (!empty($amenities)) {
                    echo '<div class="room-amenities">';
                    echo '<ul class="available-room-amenities">';
                    foreach ($amenities as $amenity_name) {
                        $formatted_name = format_amenity_name($amenity_name);
                        $amenity_icon = get_amenity_icon($amenity_name);
                        echo '<li>';
                        echo '<i class="' . esc_attr($amenity_icon) . '" title="' . esc_attr($formatted_name) . '"></i>';
                        echo '<span class="amenity-name">' . esc_html($formatted_name) . '</span>';
                        echo '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                }
                echo '<div class="room-cost">';
                echo '<span class="cost-per-day">'
                . esc_html($currency_symbol) .'<i>' . esc_html($room['cost_per_day']) . '</i>' . '/' . 'night';
                echo '<span>';
                echo '</div>';
                if($room['description']) {
                    echo '<div class="room-description"><p>' . esc_html($room['description']) . '</p></div>';
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
            $wpdb->prefix . 'reservemate_bookings',
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

function get_all_room_amenities($room_id) {
    global $wpdb;

    // Query to get the amenity names linked to the room
    $results = $wpdb->get_results(
        $wpdb->prepare("
            SELECT a.amenity_name 
            FROM {$wpdb->prefix}reservemate_amenities AS a
            INNER JOIN {$wpdb->prefix}reservemate_room_amenities AS ra
            ON a.id = ra.amenity_id
            WHERE ra.room_id = %d
        ", $room_id)
    );

    // If there are results, return an array of amenity names
    if ($results) {
        return wp_list_pluck($results, 'amenity_name');
    }

    return []; // Return an empty array if no amenities are found
}

function get_amenity_icon($amenity_key) {
    // Convert database format to the format used in $amenity_symbols
    $key = strtolower(str_replace(' ', '_', $amenity_key));

    // Emoji symbols for amenities
    $amenity_symbols = [
        'air_conditioning' => 'fa fa-snowflake',
        'airport_shuttle' => 'fa fa-shuttle-van',
        'babysitter' => 'fa fa-baby',
        'balcony' => 'fa fa-tree',
        'bar' => 'fa fa-glass-whiskey',
        'bath' => 'fa fa-bath',
        'bathtub' => 'fa fa-bath',
        'bbq_grill' => 'fa fa-drumstick-bite',
        'beach_access' => 'fa fa-umbrella-beach',
        'bicycle_rental' => 'fa fa-bicycle',
        'breakfast' => 'fa fa-utensils',
        'business_center' => 'fa fa-briefcase',
        'cable_tv' => 'fa fa-tv',
        'car_rental' => 'fa fa-car',
        'casino' => 'fa fa-dice',
        'ceiling_fan' => 'fa fa-fan',
        'charging_outlets' => 'fa fa-plug',
        'charging_station' => 'fa fa-charging-station',
        'child_bed' => 'fa fa-baby',
        'city_view' => 'fa fa-city',
        'coffee_maker' => 'fa fa-coffee',
        'concierge' => 'fa fa-concierge-bell',
        'conference_room' => 'fa fa-building',
        'courtyard' => 'fa fa-leaf',
        'crib' => 'fa fa-baby-carriage',
        'dining_area' => 'fa fa-chair',
        'dishwasher' => 'fa fa-utensils',
        'dj_services' => 'fa fa-music',
        'driver_service' => 'fa fa-user-tie',
        'dryer' => 'fa fa-fan',
        'elevator' => 'fa fa-elevator',
        'express_checkout' => 'fa fa-receipt',
        'fan' => 'fa fa-fan',
        'fire_extinguisher' => 'fa fa-fire-extinguisher',
        'fireplace' => 'fa fa-fire',
        'fitness_center' => 'fa fa-heartbeat',
        'free_wifi' => 'fa fa-wifi',
        'fridge' => 'fa fa-ice-cream',
        'game_room' => 'fa fa-gamepad',
        'garden_view' => 'fa fa-seedling',
        'grill' => 'fa fa-drumstick-bite',
        'gym' => 'fa fa-dumbbell',
        'hair_dryer' => 'fa fa-wind',
        'heater' => 'fa fa-thermometer-half',
        'high_chair' => 'fa fa-child',
        'in_room_safe' => 'fa fa-shield-alt',
        'iron' => 'fa fa-tshirt',
        'jacuzzi' => 'fa fa-hot-tub',
        'keyless_entry' => 'fa fa-key',
        'king_bed' => 'fa fa-bed',
        'kitchen' => 'fa fa-blender',
        'library' => 'fa fa-book',
        'lounge' => 'fa fa-couch',
        'meeting_room' => 'fa fa-handshake',
        'microwave' => 'fa fa-mitten',
        'mini_bar' => 'fa fa-glass-cheers',
        'mini_golf' => 'fa fa-golf-ball',
        'mountain_view' => 'fa fa-mountain',
        'non_smoking' => 'fa fa-smoking-ban',
        'ocean_view' => 'fa fa-water',
        'outdoor_furniture' => 'fa fa-chair',
        'parking' => 'fa fa-parking',
        'patio' => 'fa fa-umbrella-beach',
        'pet_bedding' => 'fa fa-paw',
        'pet_friendly' => 'fa fa-paw',
        'pet_walking' => 'fa fa-dog',
        'playground' => 'fa fa-swings',
        'pool_bar' => 'fa fa-cocktail',
        'pool_view' => 'fa fa-swimming-pool',
        'private_pool' => 'fa fa-swimming-pool',
        'restaurant' => 'fa fa-utensils',
        'rooftop_access' => 'fa fa-building',
        'room_service' => 'fa fa-bell-concierge',
        'safe' => 'fa fa-lock',
        'sauna' => 'fa fa-hot-tub',
        'security' => 'fa fa-shield-alt',
        'self_check_in' => 'fa fa-key',
        'shower' => 'fa fa-shower',
        'ski_in_ski_out' => 'fa fa-skiing',
        'smoke_alarm' => 'fa fa-exclamation-triangle',
        'sofa_bed' => 'fa fa-couch',
        'solar_power' => 'fa fa-solar-panel',
        'soundproof' => 'fa fa-volume-mute',
        'spa' => 'fa fa-spa',
        'stove' => 'fa fa-fire-alt',
        'towels' => 'fa fa-toilet-paper',
        'tv' => 'fa fa-tv',
        'twin_beds' => 'fa fa-bed',
        'valet_parking' => 'fa fa-car-side',
        'washer' => 'fa fa-soap',
        'washing_machine' => 'fa fa-soap',
        'wheelchair_accessible' => 'fa fa-wheelchair',
        'workspace' => 'fa fa-laptop',
    ];

    // Return the icon if found, otherwise a default one
    return isset($amenity_symbols[$key]) ? $amenity_symbols[$key] : 'fa fa-info';
}

function format_amenity_name($amenity_key) {
    return ucwords(str_replace('_', ' ', $amenity_key));
}

function get_room_pictures($room_id) {
    global $wpdb;
    $images_table = $wpdb->prefix . 'reservemate_room_images';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT image_id FROM $images_table WHERE room_id = %d",
        $room_id
    ));
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
        max-width: 80%;
        margin: 0 auto;
        padding: 1rem 0;
        border: 1px solid #ddd;
        border-radius: 8px;
    }
    
    #select-room-form .form-wrap {
        padding: 20px;
    }

    #select-room-form .form-wrap .available-room {
        display: flex;
        align-items: center;
        flex-direction: column;
        margin-bottom: 3rem;
        padding: 1rem 2rem;
        background-color: #f9f9f9;
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
        border-bottom: 2px solid #98e6c0;
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
    
    #select-room-form .room-container {
        width: 100%;
        display: flex;
        height: 100%;
        justify-content: space-around;    
    }
    
    #select-room-form .room-amenities {
        width: 100%;
    }
    
    
    #select-room-form .available-room-amenities {
        list-style: none;
        display: flex;
        width: 100%;
        justify-content: space-evenly;
        flex-wrap: wrap;
        margin: 0;
        padding: 0;
    }
    
    #select-room-form .available-room-amenities li {
        position: relative;
    }
    
    #select-room-form .available-room-amenities li i {
        cursor: pointer;
        display: inline-block;
        font-size: 24px; /* Adjust size of the icon as needed */
    }
    
    #select-room-form .available-room-amenities li span.amenity-name {
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
    
    #select-room-form .available-room-amenities li span.amenity-name::before {
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
    
    #select-room-form .available-room-amenities li i.active + span.amenity-name {
        display: inline-block;
        opacity: 1;
        pointer-events: auto;
        visibility: visible;
    }
    
    #select-room-form .room-images {
        text-align: center;
    }
    
    #select-room-form .room-img {
        margin: 0.5rem;
        width: 120px;
        height: 120px !important;
    }
    
    
    /* Mobile devices (below 768px) */
    @media screen and (max-width: 768px) {
        
        #select-room-form {
            max-width: 100%;
            border: none;
        }
        #select-room-form .form-wrap {
            padding: 1rem 0.5rem;
        }
        #select-room-form .form-wrap .available-room {
            display: flex;
            align-items: center;
            flex-direction: column;
            margin-bottom: 3rem;
            padding: 2rem 0;
            background-color: #f9f9f9;
        }
        
        #select-room-form .form-wrap .available-room .room-name-radio {
            justify-content: space-around;
            width: 90%;
            
        }
        
        #select-room-form .form-wrap .available-room .room-amenities {
            padding: 0 1rem;
        }
        
        #select-room-form .form-wrap .available-room .room-amenities ul {
            border: 1px solid #e8e4e4;
            border-bottom: 0;
            padding: 1rem 0;
        }
        
        #select-room-form .form-wrap .available-room .room-cost {
            padding: 0 1rem 1rem;
        }
        
        #select-room-form .form-wrap .available-room .room-cost .cost-per-day {
            width: 100%;
            display: inline-block;
            border: 1px solid #e8e4e4;
            border-top: none;
            padding: 1rem;
        }
        
        #select-room-form .form-wrap .available-room .room-description p {
            width: 100%;
            display: inline-block;
            border: 1px solid #e8e4e4;
            padding: 1rem;
        }
        
        #select-room-form .room-container {
            display: flex;
            flex-direction: column;
            width: 100%;
            height: 100%;
        }
        
        #select-room-form .room-images {
            border: 1px solid #e8e4e4;
            border-bottom: 0;
            padding: 2rem 0;
        }
        
        #select-room-form .room-details {
            padding: 0 0 2rem;
        }
        
        #select-room-form .room-size-guests {
            display: flex;
            border: 1px solid #e8e4e4;
        }
        
        #select-room-form .room-size-guests > * {
            width: 50%;
            padding: 1rem;
            text-align: center;
        }
        
        #select-room-form .room-details .room-size {
            border-right: 1px solid #e8e4e4;
        }
        
        #select-room-form .room-img {
            width: 200px;
            height: 200px !important;
        }
        
        #select-room-form .room-img:not(:first-child) {
            display: none;
        }
        
        
    }
    
    </style>
    <?php
}

add_action('wp_head', 'enqueue_custom_styles');
add_shortcode('booking_form', 'display_booking_form');

