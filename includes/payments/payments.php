<?php
defined('ABSPATH') or die('No script please!');

require_once plugin_dir_path(__FILE__) . '../api/stripe/init.php';

use Stripe\Stripe;
use Stripe\Charge;

function process_payment($details, $total_cost, $currency) {
    $payment_settings = get_option('payment_settings');

    if (!empty($payment_settings['stripe_enabled'])) {
        \Stripe\Stripe::setApiKey(get_option('payment_settings')['stripe_secret_key']);
    
        if (empty($details['stripeToken']) || !is_numeric($total_cost) || $total_cost <= 0) {
            return ['success' => false, 'message' => 'Invalid payment details.'];
        }
    
        try {
            $charge = \Stripe\Charge::create([
                'amount' => intval($total_cost * 100), // Amount in cents
                'currency' => $currency,
                'source' => $details['stripeToken'],
                'description' => 'Room booking payment',
            ]);
            return ['success' => true];
        } catch (\Stripe\Exception\CardException $e) {
            error_log('Card error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Stripe API error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Payment failed. Please try again.'];
        } catch (Exception $e) {
            error_log('Unexpected error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An unexpected error occurred.'];
        }
    }
}