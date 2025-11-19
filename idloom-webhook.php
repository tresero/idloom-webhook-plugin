<?php
/*
Plugin Name: Idloom Webhook Handler
Description: Handles Idloom webhooks and creates/updates CiviCRM contacts
Version: 1.0
Author: Jon Griffin
*/

add_action('rest_api_init', function () {
    register_rest_route('idloom/v1', '/webhook', [
        'methods' => 'POST',
        'callback' => 'idloom_webhook_handler',
        'permission_callback' => '__return_true',
    ]);
});

function idloom_webhook_handler(WP_REST_Request $request) {
    // Log headers and body for debugging
    $headers = $request->get_headers();
    $body = $request->get_body();
    error_log('Incoming Headers: ' . print_r($headers, true));
    error_log('Incoming Raw Body: ' . $body);

    // Validate Content-Type
    if ($request->get_header('Content-Type') !== 'application/json') {
        return new WP_Error('invalid_content_type', 'Content-Type must be application/json.', ['status' => 400]);
    }

    // Parse JSON payload
    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON Error: ' . json_last_error_msg());
        return new WP_Error('invalid_json', 'Invalid JSON payload.', ['status' => 400]);
    }

    error_log('Parsed JSON Data: ' . print_r($data, true));

    // Check consent to update (free_field59 is the consent flag)
    if (empty($data['free_field59']) || !in_array($data['free_field59'], [true, 'true', 1], true)) {
        error_log('free_field59 is not true. Webhook will not be processed.');
        return rest_ensure_response([
            'status' => 'ignored',
            'message' => 'free_field59 is not true. Webhook not processed.',
        ]);
    }

    // Sanitize input data
    $contact_data = [
        'email' => !empty($data['email']) ? sanitize_email($data['email']) : '',
        'firstname' => !empty($data['firstname']) ? sanitize_text_field($data['firstname']) : '',
        'lastname' => !empty($data['lastname']) ? sanitize_text_field($data['lastname']) : '',
        'phone' => !empty($data['phone']) ? sanitize_text_field($data['phone']) : '',
        'street' => !empty($data['cpy_street']) ? sanitize_text_field($data['cpy_street']) : '',
        'street_number' => !empty($data['cpy_street_number']) ? sanitize_text_field($data['cpy_street_number']) : '',
        'city' => !empty($data['cpy_city']) ? sanitize_text_field($data['cpy_city']) : '',
        'zip_code' => !empty($data['cpy_zip_code']) ? sanitize_text_field($data['cpy_zip_code']) : '',
        'country' => !empty($data['cpy_country']) ? sanitize_text_field($data['cpy_country']) : '',
        // FIX: Replaced free_field12 with the correct key, cpy_name
        'cast_year' => !empty($data['cpy_name']) ? sanitize_text_field($data['cpy_name']) : '' 
    ];

    // Initialize CiviCRM
    if (!function_exists('civicrm_initialize')) {
        include_once ABSPATH . '/wp-content/plugins/civicrm/civicrm.php';
        civicrm_initialize();
    }

    if (!function_exists('civicrm_api4')) {
        return new WP_Error('civicrm_error', 'CiviCRM API function not available.', ['status' => 500]);
    }

    try {
        // Create contact in CiviCRM
        $contact_result = civicrm_api4('Contact', 'create', [
            'values' => [
                'contact_type' => 'Individual',
                'first_name' => $contact_data['firstname'],
                'last_name' => $contact_data['lastname'],
                'source' => 'Idloom Registration'
            ],
            'checkPermissions' => false,
        ]);

        $contact_id = $contact_result[0]['id'];

        // Add email
        if (!empty($contact_data['email'])) {
            civicrm_api4('Email', 'create', [
                'values' => [
                    'contact_id' => $contact_id,
                    'email' => $contact_data['email'],
                    'is_primary' => true,
                ],
                'checkPermissions' => false,
            ]);
        }

        // Add phone
        if (!empty($contact_data['phone'])) {
            civicrm_api4('Phone', 'create', [
                'values' => [
                    'contact_id' => $contact_id,
                    'phone' => $contact_data['phone'],
                    'is_primary' => true,
                ],
                'checkPermissions' => false,
            ]);
        }

        // Add address
        if (!empty($contact_data['street']) || !empty($contact_data['city'])) {
            civicrm_api4('Address', 'create', [
                'values' => [
                    'contact_id' => $contact_id,
                    'street_address' => trim($contact_data['street'] . ' ' . $contact_data['street_number']),
                    'city' => $contact_data['city'],
                    'postal_code' => $contact_data['zip_code'],
                    'country' => $contact_data['country'],
                    'is_primary' => true,
                ],
                'checkPermissions' => false,
            ]);
        }

        // Add cast year as custom field if provided
        if (!empty($contact_data['cast_year'])) {
            civicrm_api4('CustomValue', 'set', [
                'values' => [
                    'entity_id' => $contact_id,
                    'field_name' => "Extra_IAA_Contact_Info.Primary_Cast",  // Replace with your actual custom field name
                    'value' => $contact_data['cast_year']
                ],
                'checkPermissions' => false,
            ]);
        }

        error_log('CiviCRM Contact Created: ' . print_r($contact_result, true));
        
        return rest_ensure_response([
            'status' => 'success',
            'message' => 'Contact created in CiviCRM successfully.',
            'contact_id' => $contact_id
        ]);

    } catch (Exception $e) {
        error_log('CiviCRM API Exception: ' . $e->getMessage());
        return new WP_Error('civicrm_exception', 'An error occurred while communicating with CiviCRM: ' . $e->getMessage(), ['status' => 500]);
    }
}