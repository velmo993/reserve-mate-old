<?php
// Prevent direct access
defined('ABSPATH') or die('No script please!');

function process_payment($booking_details) {
    // Example for Stripe integration
    \Stripe\Stripe::setApiKey(get_option('booking_settings')['stripe_secret_key']);

    try {
        $charge = \Stripe\Charge::create([
            'amount' => $booking_details['amount'],
            'currency' => 'usd',
            'description' => 'Booking Payment',
            'source' => $booking_details['source'],
        ]);
        return $charge;
    } catch (Exception $e) {
        error_log('Payment failed: ' . $e->getMessage());
        return false;
    }
}
