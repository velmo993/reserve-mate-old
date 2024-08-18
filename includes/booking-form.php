<?php
// Prevent direct access
defined('ABSPATH') or die('No script please!');

// Include the Google Calendar functions
require_once(plugin_dir_path(__FILE__) . 'google-calendar.php');

// Register shortcode for booking form
function booking_form_shortcode($atts) {
    $atts = shortcode_atts(array(
        'niche' => 'hotel', // Default to 'hotel'
    ), $atts, 'booking_form');

    return display_booking_form($atts['niche']);
}
add_shortcode('booking_form', 'booking_form_shortcode');

function display_booking_form($niche) {
    ob_start();
    ?>
    <form id="booking-form" method="post">
        <input type="hidden" name="niche" value="<?php echo esc_attr($niche); ?>">
        
        <?php if ($niche === 'hotel') : ?>
            <label for="hotel-room">Room:</label>
            <input type="text" id="hotel-room" name="hotel-room" required>
        <?php elseif ($niche === 'restaurant') : ?>
            <label for="restaurant-table">Table:</label>
            <input type="text" id="restaurant-table" name="restaurant-table" required>
        <?php elseif ($niche === 'barber') : ?>
            <label for="barber-service">Service:</label>
            <input type="text" id="barber-service" name="barber-service" required>
        <?php endif; ?>

        <label for="booking-date">Date:</label>
        <input type="date" id="booking-date" name="booking-date" required>
        <label for="booking-time">Time:</label>
        <input type="time" id="booking-time" name="booking-time" required>
        <input type="submit" value="Book Now">
    </form>
    <?php
    return ob_get_clean();
}

// Process booking form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize input based on niche
    $niche = isset($_POST['niche']) ? sanitize_text_field($_POST['niche']) : 'hotel'; // Default to 'hotel'
    $booking_date = isset($_POST['booking-date']) ? sanitize_text_field($_POST['booking-date']) : '';
    $booking_time = isset($_POST['booking-time']) ? sanitize_text_field($_POST['booking-time']) : '';

    // Initialize variables for specific niche fields
    $details = '';
    
    // Handle different niches
    if ($niche === 'hotel') {
        $room = isset($_POST['hotel-room']) ? sanitize_text_field($_POST['hotel-room']) : 'Unknown room';
        $details = 'Room: ' . $room;
    } elseif ($niche === 'restaurant') {
        $table = isset($_POST['restaurant-table']) ? sanitize_text_field($_POST['restaurant-table']) : 'Unknown table';
        $details = 'Table: ' . $table;
    } elseif ($niche === 'barber') {
        $service = isset($_POST['barber-service']) ? sanitize_text_field($_POST['barber-service']) : 'Unknown service';
        $details = 'Service: ' . $service;
    }

    // Validate required fields
    if (!empty($booking_date) && !empty($booking_time)) {
        // Prepare event details
        $event_details = array(
            'summary' => 'Booking at ' . ucfirst($niche),
            'description' => $details,
            'start' => $booking_date . 'T' . $booking_time . ':00-07:00', // Adjust timezone as needed
            'end' => $booking_date . 'T' . (intval($booking_time) + 1) . ':00-07:00', // Assuming 1-hour duration
        );

        // Call the function to sync with Google Calendar
        $result = sync_with_google_calendar($event_details);

        if ($result) {
            echo 'Booking successfully added to Google Calendar!';
        } else {
            echo 'Failed to add booking to Google Calendar.';
        }
    } else {
        echo 'Booking date and time are required.';
    }
}