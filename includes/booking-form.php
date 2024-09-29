<?php
defined('ABSPATH') or die('No script please!');

require_once(plugin_dir_path(__FILE__) . 'google-calendar.php');
require_once(plugin_dir_path(__FILE__) . 'payments.php');

function display_search_form() {
    $error_message = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['start-date']) || empty($_POST['end-date'])) {
            $error_message = 'Both Arrival and Departure dates are required.';
        } elseif (!isset($_POST['adults'])) {
            $error_message = 'Number of adults is required.';
        } else {
            return handle_room_search();
        }
    }
    
    ob_start();
    ?>
    <?php if ($error_message): ?>
        <div class="error-message" style="color: red;"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <form id="search-room-form" method="post">
        <input type="hidden" name="action" value="search_rooms">
        <div>
            <div class="form-field">
                <label for="start-date">Check In:</label>
                <input type="text" id="start-date" name="start-date" required>
            </div>
        
            <div class="form-field">
                <label for="end-date">Check Out:</label>
                <input type="text" id="end-date" name="end-date" required>
            </div>
        </div>
        
        <div>
            <div class="form-field">
                <label for="adults">Adults:</label>
                <input type="number" id="adults" name="adults" min="1" value="2" required>
            </div>
            
            <div class="form-field">
                <label for="children">Children:</label>
                <input type="number" id="children" name="children" min="0" value="0" required>
            </div>
        </div>
        
        <div class="form-field">
            <input type="submit" value="Search Rooms">
        </div>
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
    $amenities = get_predefined_amenities();

    ob_start(); ?>
        <form method="post" id="select-room-form" action="" enctype="multipart/form-data">
            <button id="prev-room" disabled><i class="fa">&#xf0d9;</i></button>
            <button id="next-room"><i class="fa">&#xf0da;</i></button>
            <div class="filter-sort-controls">
                <div id="filter-controls">
                    <button id="filter-btn"><i class="fa">&#xf0b0;</i><span>Filter</span></button>
                    <button id="reset-filters"><i class="fa">&#xe17b;</i><span>Reset</span></button>
                </div>
                <div id="sort-controls">
                    <select id="sort-select">
                        <option value="" disabled selected>⇅ Sort by</option>
                        <option value="price-asc">Price ↑↑ </option>
                        <option value="price-desc">Price ↓↓ </option>
                        <option value="size-asc">Size ↑↑ </option>
                        <option value="size-desc">Size ↓↓ </option> 
                    </select>
                </div>
            </div>
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
                <input type="hidden" name="stripeToken" id="stripeToken" value="">
                
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

    $room_data = get_room_cost($room_id);
    $base_cost = floatval($room_data['base_cost']);
    $price_per_adult = floatval($room_data['price_per_adult']);
    $price_per_child = floatval($room_data['price_per_child']);

    // Calculate total cost
    $total_cost = $base_cost + ($price_per_adult * $adults * $days_booked) + ($price_per_child * $children * $days_booked);
    $currency = get_currency_code();
    
    if (isset($_POST['stripeToken'])) {
        $payment_result = process_payment($_POST, $total_cost, $currency);

        if ($payment_result['success']) {
            save_booking_to_db($room_id, $name, $email, $phone, $adults, $children, $start_date, $end_date, $total_cost, (int)1);
            save_booking_to_calendar($room_id, $adults, $children, $start_date, $end_date, $name, $email);
            echo json_encode(['success' => true, 'redirect_url' => 'success-page.php']);
        } else {
            echo json_encode(['success' => false, 'error' => $payment_result['message']]);
        }
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Stripe token not received.']);
        exit;
    }
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
            'paid' => $paid,
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

    $room_data = $wpdb->get_row($wpdb->prepare(
        "SELECT cost_per_day AS base_cost, price_per_adult, price_per_child 
         FROM {$wpdb->prefix}reservemate_rooms 
         WHERE id = %d",
         $room_id
    ), ARRAY_A);

    return $room_data;
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

    i.fa {
        font-family: 'Font Awesome\ 6 Free';
        content: "\f061";
        font-weight: 900;
        font-style: inherit;
    }
    
    #search-room-form {
        max-width: 600px;
        margin: auto;
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
        margin: 0 auto;
    }
        
    #search-room-form .form-field {
        width: 45%;
        display: flex;
        flex-direction: column;
    }   
    
    #select-room-form {
        position: relative;
        max-width: 80%;
        margin: 3rem auto;
    }
    
    #select-room-form .form-field,
    #select-room-form .form-group {
        padding: 1rem;
    }
    
    .filter-sort-controls {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 2rem 0.2rem;
    }
    
    .filter-sort-controls button,
    .filter-sort-controls select {
        height: 40px;
        padding: 0 10px;
        font-size: 16px;
        border-radius: 4px;
    }
    
    .filter-sort-controls select {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        background-color: #fff;
        border: 1px solid #ccc;
    }
    
    #sort-select {
        width: 8rem;
        margin-right: 0.5rem;
        font-size: 1em;
    }
    
    #filter-controls {
        display: flex;
        width: 50%;
    }
    
    #filter-btn {
        display: flex;
        align-items: center; 
        justify-content: center;
        width: 45%;
        margin-left: 0.5rem;
    }
    
    #filter-btn span,
    #reset-filters span {
        margin-left: 5px;
    }
    
    #reset-filters {
        display: flex;
        align-items: center; 
        justify-content: center;
        background: gray;
        margin-left: 0.5rem;
        width: 45%;
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
        width: 100%;
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
        margin-left: 1rem;
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
    
    
    input[type="range"] {
        -webkit-appearance: none;
        width: 100%;
        background: #ddd;
        border-radius: 5px;
        outline: none;
        margin: 0;
        padding: 0.2rem 0.4rem;
    }
    
    /* Thumb (the draggable part) styling */
    input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none; 
        appearance: none;
        width: 1px;
        height: 25px;
        background: #4CAF50;
        border-radius: 40%;
        cursor: pointer;
    }
    
    input[type="range"]::-moz-range-thumb {
        width: 1px;
        height: 25px;
        
        background: #4CAF50;
        border-radius: 40%;
        cursor: pointer;
    }
    
    /* For Firefox */
    input[type="range"]::-ms-thumb {
        width: 1px;
        height: 25px;
        
        background: #4CAF50;
        border-radius: 40%;
        cursor: pointer;
    }
    
    /* Styling for the track */
    input[type="range"]::-webkit-slider-runnable-track {
        background: #ddd;
        border-radius: 5px;
    }
    
    input[type="range"]::-moz-range-track {
        background: #ddd;
        border-radius: 5px;
    }
    
    input[type="range"]::-ms-track {
        background: #ddd;
        border-radius: 5px;
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
        height: 85%;
        margin-top: 140px;
        margin: auto;
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

    
    /********* Stripe  ************/
    #card-element {
        padding: 1rem;
        border: 1px solid #ccc;
        border-radius: 4px;
        width: 100%;
        box-shadow: 0px 1px 3px rgba(0, 0, 0, 0.1);
        background: var(--ast-comment-inputs-background);
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
        
        .filter-sort-controls {
            padding-bottom: 0.2rem;
        }
        
        .filter-content {
            height: 80%
        }
        
    }
    /****************************  Mobile devices (below 768px) end ******************************************/
    
    </style>
    <?php
}

add_action('wp_head', 'enqueue_custom_styles');
add_shortcode('booking_form', 'display_search_form');

