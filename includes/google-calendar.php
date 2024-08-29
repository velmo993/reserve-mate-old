<?php
// Prevent direct access
defined('ABSPATH') or die('No script please!');

// Load the Google API client library
require_once(plugin_dir_path(__FILE__) . 'google-api-client/vendor/autoload.php');

function sync_with_google_calendar($event_details) {
    try {
        // Retrieve Google Calendar API credentials from options
        $options = get_option('booking_settings');
        $selected_timezone = isset($options['calendar_timezones']) ? $options['calendar_timezones'] : 'UTC';
        
        if (!isset($options['calendar_api_key'])) {
            throw new Exception('Google Calendar API credentials not set.');
        }
        if (!isset($options['calendar_id'])) {
            throw new Exception('Google Calendar Id not set.');
        }
        
        // Decode the JSON credentials
        $credentials_json = $options['calendar_api_key'];
        $credentials = json_decode($credentials_json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid API key format.');
        }
        
        $calendarId = $options['calendar_id'];

        // Create a Google Client
        $client = new Google_Client();
        $client->setApplicationName('booking-service');
        $client->setAuthConfig($credentials); // Use the decoded credentials array
        $client->addScope(Google_Service_Calendar::CALENDAR);

        $service = new Google_Service_Calendar($client);
        
        // Convert to UTC for Google Calendar
        $start = new DateTime($event_details['start'], new DateTimeZone($selected_timezone));
        $end = new DateTime($event_details['end'], new DateTimeZone($selected_timezone));
        
        $event = new Google_Service_Calendar_Event(array(
            'summary' => $event_details['summary'],
            'description' => $event_details['description'],
            'location' => isset($event_details['location']) ? $event_details['location'] : '',
            'start' => array(
                'dateTime' => $start->format(DateTime::ATOM),
                'timeZone' => $selected_timezone,
            ),
            'end' => array(
                'dateTime' => $end->format(DateTime::ATOM),
                'timeZone' => $selected_timezone,
            ),
            'reminders' => array(
                'useDefault' => FALSE,
                'overrides' => array(
                    array('method' => 'email', 'minutes' => 24 * 60),
                    array('method' => 'popup', 'minutes' => 10),
                ),
            )
        ));

        // Debug: Log the event object to check its structure
        // error_log('Event Object: ' . print_r($event, true));

        // Insert the event into the calendar
        // $calendarId = '1a48b015cf5a51aecb06e738d4ca10e49ba4583bbd4ecbb4a83011695bcf226f@group.calendar.google.com'; // Replace with your calendar ID
        $createdEvent = $service->events->insert($calendarId, $event);

        // Log the created event details for debugging
        // error_log('Created Event: ' . print_r($createdEvent, true));

        return $createdEvent->getId(); // Return the event ID if needed
    } catch (Exception $e) {
        // Detailed error logging
        // error_log('Google Calendar sync failed: ' . $e->getMessage());
        // error_log('Event Details: ' . print_r($event_details, true));
        // error_log('Request Payload: ' . (isset($event) ? json_encode($event) : 'Event object not created.'));
        return false;
    }
}

