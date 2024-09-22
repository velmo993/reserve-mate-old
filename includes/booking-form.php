<?php
// Prevent direct access
defined('ABSPATH') or die('No script please!');

require_once(plugin_dir_path(__FILE__) . 'google-calendar.php');

function display_search_form() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adults'])) {
        // Process the form submission
        return handle_room_search();
    }
    ob_start();
    ?>
    <form id="search-room-form" method="post">
        <input type="hidden" name="action" value="search_rooms">
        <label for="adults">Adults:</label>
        <div class="form-field">
            <input type="number" id="adults" name="adults" min="1" value="2" required>
        </div>
        
        <label for="children">Children:</label>
        <div class="form-field">
            <input type="number" id="children" name="children" min="0" value="0" required>
        </div>
        
        <div>
            <div class="date-picker">
                <label for="start-date">Arrival:</label>
                <input type="text" id="start-date" name="start-date" required>
            </div>
        
            <div class="date-picker">
                <label for="end-date">Departure:</label>
                <input type="text" id="end-date" name="end-date" required>
            </div>
        </div>
    
        <input type="submit" value="Search Available Rooms">
    </form>

    <?php
    return ob_get_clean();
}

function handle_room_search() {
    $adults = intval($_POST['adults']);
    $children = intval($_POST['children']);
    $start_date = sanitize_text_field($_POST['start-date']);
    $end_date = sanitize_text_field($_POST['end-date']);
    $options = get_option('booking_settings');
    $currency = get_currency();

    ob_start(); ?>
        <form method="post" id="select-room-form">
            <button id="prev-room" disabled><i class="fa fa-caret-left"></i></button>
            <button id="next-room"><i class="fa fa-caret-right"></i></button>
            <button id="filter-btn"><i class="fa fa-filter"></i></button>
            <button id="reset-filters"><i class="fa fa-redo"></i></button>
            <div id="filter-menu" class="filter-modal">
                <div class="filter-content">
                    <span id="close-modal">&times;</span>
                    <h3>Filter Options</h3>
                    <label for="min-price-range">Min Price:</label>
                    <input type="range" id="min-price-range" name="min-price-range" min="20" max="400" step="10" value="20">
                    
                    <label for="max-price-range">Max Price:</label>
                    <input type="range" id="max-price-range" name="max-price-range" min="120" max="500" step="10" value="120">
                    
                    <span id="price-range-display"><?php echo $currency ?> 20 - <?php echo $currency ?> 120</span>
                    
                    <div class="filter-room-size">
                        <label for="room-size-select">Room Size (Min):</label>
                        <select id="room-size-select">
                            <option value="none">None</option>
                            <option value="10">10 m²</option>
                            <option value="20">20 m²</option>
                            <option value="30">30 m²</option>
                            <option value="40">40 m²</option>
                            <option value="50">50 m²</option>
                            <option value="60">60 m²</option>
                            <option value="70">70 m²</option>
                            <option value="80">80 m²</option>
                            <option value="90">90 m²</option>
                            <option value="100">100 m²</option>
                        </select>
                    </div>
                    
                    <label for="amenities-container">Amenities:</label>
                    <div id="amenities-container"></div>
                    
                    <button id="apply-filters">Apply Filters</button>
                </div>
            </div>
            
            <div class="form-wrap">
                <input type="hidden" name="select-room-adults" id="select-room-adults" value="<?php echo esc_attr($adults); ?>">
                <input type="hidden" name="select-room-children" id="select-room-children" value="<?php echo esc_attr($children); ?>">
                <input type="hidden" name="select-room-start-date" id="select-room-start-date" value="<?php echo esc_attr($start_date); ?>">
                <input type="hidden" name="select-room-end-date" id="select-room-end-date" value="<?php echo esc_attr($end_date); ?>">
                
                <div id="rooms-container"></div>
                
            </div>
        </form>

    <?php return ob_get_clean();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room-id']) && isset($_POST['name'])) {
    
    $room_id = sanitize_text_field($_POST['room-id']);
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    $adults = intval($_POST['select-room-adults']);
    $children = intval($_POST['select-room-children']);
    $start_date = sanitize_text_field($_POST['select-room-start-date']);
    $end_date = sanitize_text_field($_POST['select-room-end-date']);
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $days_booked = $interval->days;
    $cost_per_day = get_room_cost($room_id);
    $total_cost = $days_booked * floatval($cost_per_day);

    save_booking_to_db($room_id, $name, $email, $phone, $adults, $children, $start_date, $end_date, $total_cost, 0);

    save_booking_to_calendar($room_id, $adults, $children, $start_date, $end_date, $name, $email);
    
    header('Location: ' . add_query_arg('booking_status', 'success', $_SERVER['REQUEST_URI']));
    exit;
}

if (isset($_GET['booking_status']) && $_GET['booking_status'] === 'success') {
    echo 'Booking successfully completed!';
}

function save_booking_to_db($room_id, $name, $email, $phone, $adults, $children, $start_date, $end_date, $total_cost, $paid = 0) {
    global $wpdb;
    $result = $wpdb->insert(
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
}

function save_booking_to_calendar($room_id, $adults, $children, $start_date, $end_date, $name, $email) {
    $room = get_room_details($room_id);
    $options = get_option('booking_settings');
    $checkin_time = isset($options['checkin_time']) ? esc_attr($options['checkin_time']) : '14:00';
    $checkout_time = isset($options['checkout_time']) ? esc_attr($options['checkout_time']) : '12:00';
    
    $event_details = array(
        'summary' => 'Booked Room: ' . $room['name'],
        'description' => 'Room: ' . $room['name'] . "\n" .
                 'Booked by: ' . $name . "\n" .
                 'Email: ' . $email . "\n" .
                 'Adults: ' . $adults . "\n" .
                 'Children: ' . $children,
        'start' => $start_date . 'T' . $checkin_time . ':00',
        'end' => $end_date . 'T' . $checkout_time . ':00',
        'attendees' => array(
            array('email' => $email),
        ),
    );
    
    $result = sync_with_google_calendar($event_details);
}

function get_room_cost($room_id) {
    global $wpdb;
    $cost_per_day = $wpdb->get_var($wpdb->prepare("SELECT cost_per_day FROM {$wpdb->prefix}reservemate_rooms WHERE id = %d", $room_id));
    return $cost_per_day;
}

function get_room_details($room_id) {
    global $wpdb;
    $room = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}reservemate_rooms WHERE id = %d", $room_id), ARRAY_A);
    return $room;
}

function get_all_room_amenities($room_id) {
    global $wpdb;

    $results = $wpdb->get_results(
        $wpdb->prepare("
            SELECT a.amenity_name 
            FROM {$wpdb->prefix}reservemate_amenities AS a
            INNER JOIN {$wpdb->prefix}reservemate_room_amenities AS ra
            ON a.id = ra.amenity_id
            WHERE ra.room_id = %d
        ", $room_id)
    );

    if ($results) {
        return wp_list_pluck($results, 'amenity_name');
    }

    return [];
}

function get_amenity_icon($amenity_key) {
    $key = strtolower(str_replace(' ', '_', $amenity_key));

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

    #search-room-form {
        max-width: 600px;
        margin: 0 auto;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
        background-color: #f9f9f9;
    }

    /* Style labels */
    #search-room-form label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
    }

    /* Style inputs */
    #search-room-form input[type="number"],
    #search-room-form input[type="text"],
    #search-room-form input[type="email"],
    #search-room-form input[type="tel"] {
        width: calc(100% - 22px);
        padding: 10px;
        margin-bottom: 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 16px;
    }

    /* Style submit button */
    #search-room-form input[type="submit"] {
        background-color: #007cba;
        color: #fff;
        border: none;
        padding: 12px 20px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
    }

    #search-room-form input[type="submit"]:hover {
        background-color: #005a8d;
    }
    
    #search-room-form div {
        display: flex;
        padding-bottom: 1rem;
    }
    
    #search-room-form div:not(:first-child) {
        margin: 0 auto;
    }
        
    #search-room-form div .date-picker {
        width: 45%;
        display: flex;
        flex-direction: column;
    }   
    
    #select-room-form {
        position: relative;
        max-width: 80%;
        margin: 0 auto;
        padding: 1rem 0;
        border: 1px solid #ddd;
        border-radius: 8px;
    }
    
    #select-room-form .form-wrap {
        padding: 20px;
    }
    
    #prev-room {
        position: fixed;
        top: 50%;
        left: 0;
        height: 10%;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 2px;
        background: transparent;
    }

    #next-room {
        position: fixed;
        top: 50%;
        right: 0;
        height: 10%;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 2px;
        background: transparent;
    }
    
    #prev-room i,
    #next-room i {
        font-size: 3rem;
        color: #000;
    }
    
    #filter-btn {
        margin-left: 1.4rem;    
    }
    
    #reset-filters {
        background: gray;
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
        justify-content: space-around;
        align-items: baseline;
        padding: 1rem;
        font-size: 1.2rem;
        border-bottom: 2px solid #98e6c0;
        border-top: 2px solid #98e6c0;
        margin: 1rem auto;
    }
    
    #select-room-form .form-wrap .room-availability {
        text-align: center;
        font-size: 1.4rem;
        padding: 1rem;
    }
    
    #select-room-form .form-wrap .room-status {
        color: red;
    }
    
    #select-room-form .form-wrap .available-room .room-cost {
        width: 100%;
        display: flex;
    }
    
    #select-room-form .form-wrap .available-room .room-cost div {
        width: 50%;
    }
    
    #select-room-form .form-wrap .available-room .room-cost i {
        font-size: 1.2rem;
        font-weight: 500;
        font-style: normal;
    }
    
    #select-room-form .form-wrap .available-room .room-description {
        width: 100%;
    }
    
    #select-room-form .form-wrap .available-room input[type="radio"] {
        transform: scale(1.5);
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
    
    #select-room-form #book-now-button {
        margin: 0;
    }
    
    #select-room-form .single-room-container {
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
        flex-wrap: wrap;
        margin: 0;
        padding: 0;
    }
    
    #select-room-form .available-room-amenities li {
        position: relative;
        margin: 10px;
        border: 1px solid gainsboro;
        width: 40px;
        height: 40px;
        text-align: center;
        align-content: center;
    }
    
    #select-room-form .available-room-amenities li i {
        cursor: pointer;
        display: inline-block;
        font-size: 1.2rem;
        padding: 0.4rem;
    }
    
    #select-room-form .available-room-amenities li span.amenity-name {
        display: none;
        position: absolute;
        bottom: 125%;
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
    
    #rooms-container {
        box-shadow: 0px 0px 70px 24px #2f4f4f;
    }
        
    .available-room.hidden {
        display: none !important;
    }

    .available-room.active {
        display: block !important;
    }
    
    .lightbox {
        display: none;
        position: fixed;
        z-index: 999;
        padding-top: 60px;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.9);
        overflow: hidden;
    }

    .lightbox-content {
        margin: auto;
        display: block;
        max-width: 80%;
        max-height: 80vh;
        position: relative;
    }
    
    .lightbox-counter {
        color: #fff;
        text-align: center;
    }
    
    #lightbox-img {
        width: auto;
        height: auto;
        max-width: 100%;
        max-height: 100%;
    }
    
    @media only screen and (orientation: landscape) {
        .lightbox-content {
            max-width: 50vw;
        }
    }
    
    .close {
        position: absolute;
        top: 15px;
        right: 35px;
        color: white;
        font-size: 40px;
        font-weight: bold;
        cursor: pointer;
    }
    
    .prev, .next {
        position: absolute;
        top: 50%;
        color: white;
        font-size: 30px;
        font-weight: bold;
        cursor: pointer;
        padding: 10px;
        user-select: none;
    }
    
    .prev {
        left: 0;
    }
    
    .next {
        right: 0;
    }
    
    .no-touch {
        overflow: hidden;
        touch-action: none;
    }
    
    .range-container {
        display: flex;
        justify-content: space-between;
        padding: 1rem;
    }
    
    
        /* General styling for range sliders */
    input[type="range"] {
        -webkit-appearance: none; /* Remove default styling */
        width: 100%; /* Full width */
        background: #ddd; /* Background color */
        border-radius: 5px; /* Rounded corners */
        outline: none; /* Remove outline */
        margin: 0;
        padding: 0.2rem 0.4rem;
    }
    
    /* Thumb (the draggable part) styling */
    input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none; /* Remove default styling */
        appearance: none;
        width: 1px; /* Increase thumb width */
        height: 25px; /* Increase thumb height */
        
        background: #4CAF50; /* Thumb color */
        border-radius: 40%; /* Round thumb */
        cursor: pointer; /* Pointer on hover */
    }
    
    input[type="range"]::-moz-range-thumb {
        width: 1px; /* Increase thumb width */
        height: 25px; /* Increase thumb height */
        
        background: #4CAF50; /* Thumb color */
        border-radius: 40%; /* Round thumb */
        cursor: pointer; /* Pointer on hover */
    }
    
    /* For Firefox */
    input[type="range"]::-ms-thumb {
        width: 1px; /* Increase thumb width */
        height: 25px; /* Increase thumb height */
        
        background: #4CAF50; /* Thumb color */
        border-radius: 40%; /* Round thumb */
        cursor: pointer; /* Pointer on hover */
    }
    
    /* Optional: Styling for the track */
    input[type="range"]::-webkit-slider-runnable-track {
        background: #ddd; /* Background for the track */
        border-radius: 5px; /* Rounded corners */
    }
    
    input[type="range"]::-moz-range-track {
        background: #ddd; /* Background for the track */
        border-radius: 5px; /* Rounded corners */
    }
    
    input[type="range"]::-ms-track {
        background: #ddd; /* Background for the track */
        border-radius: 5px; /* Rounded corners */
    }
    
    /* Hide default input styles for Internet Explorer */
    input[type="range"]::-ms-fill-lower {
        background: #ddd;
        border-radius: 5px;
    }
    
    input[type="range"]::-ms-fill-upper {
        background: #ddd;
        border-radius: 5px;
    }
    
    .filter-modal {
        display: none;
        position: fixed;
        z-index: 100;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }
    
    .filter-menu {
        height: 80%;
    }

    .filter-content {
        background-color: #fff;
        height: 80%;
        margin-top: 140px;
        margin: 15% auto;
        padding: 20px;
        border-radius: 10px;
        width: 90%;
        max-width: 400px;
    }
    
    .filter-room-size {
        padding: 1rem 0;
    }
    
    #amenities-container {
        display: flex;
        flex-direction: column;
        height: 30%;
        overflow-y: scroll;
        margin: 0.5rem 0 1rem;
    }

    #close-modal {
        cursor: pointer;
        float: right;
        font-size: 24px;
    }
    
    .filter-room-size select {
        padding: 0.5rem;
    }
    
    #apply-filters {
        margin-top: 1rem;
    }
    
    #room-counter {
        text-align: center;
    }
    
    
    
    
    /****************************  Mobile devices (below 768px) ******************************************/
    @media screen and (max-width: 768px) {
        #select-room-form {
            max-width: 100%;
            border: none;
        }
        
        #select-room-form .form-wrap {
            padding: 0;
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
            width: 90%;
        }
        
        #select-room-form .form-wrap .available-room .room-amenities {
            padding: 0 1rem;
        }
        
        #select-room-form .form-wrap .available-room .room-amenities ul {
            border: 1px solid #e8e4e4;
            padding: 1rem;
        }
        
        #select-room-form .form-wrap .available-room .room-cost {
            border: 1px solid #e8e4e4;
            border-bottom: 0;
        }
        
        #select-room-form .form-wrap .available-room .room-cost div {
            width: 50%;
            padding: 1rem;
            text-align: center;
        }
        
        #select-room-form .form-wrap .available-room .room-cost .cost-per-day {
            border-right: 1px solid #e8e4e4;
        }
        
        #select-room-form .form-wrap .available-room .room-description p {
            width: 100%;
            display: inline-block;
            border: 1px solid #e8e4e4;
            padding: 1rem;
        }
        
        #select-room-form .single-room-container {
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
        
        #select-room-form .room-img:nth-child(n+3) {
            display: none;
        }
        
    }
    /****************************  Mobile devices (below 768px) end ******************************************/
    
    </style>
    <?php
}

add_action('wp_head', 'enqueue_custom_styles');
add_shortcode('booking_form', 'display_search_form');

