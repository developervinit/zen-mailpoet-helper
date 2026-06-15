<?php
/**
 * AJAX handler for Zen MailPoet subscription.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Zen_MailPoet_AJAX_Subscribe {

    public function __construct() {
        add_action('wp_ajax_zen_mailpoet_subscribe', array($this, 'handle_subscription'));
        add_action('wp_ajax_nopriv_zen_mailpoet_subscribe', array($this, 'handle_subscription'));
    }

    /**
     * Process the subscription AJAX request.
     */
    public function handle_subscription() {
        // 1. Verify Nonce Security
        check_ajax_referer('zen-mailpoet-subscribe-nonce', 'security');

        // 2. Retrieve and sanitize inputs
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $list_ids_raw = isset($_POST['list_ids']) ? sanitize_text_field($_POST['list_ids']) : '';

        // Retrieve custom configuration messages passed from frontend
        $msg_success = isset($_POST['msg_success']) ? sanitize_text_field($_POST['msg_success']) : __('Thank you! You have successfully subscribed.', 'zen-mailpoet-helper');
        $msg_error = isset($_POST['msg_error']) ? sanitize_text_field($_POST['msg_error']) : __('An error occurred. Please try again.', 'zen-mailpoet-helper');
        $msg_already = isset($_POST['msg_already']) ? sanitize_text_field($_POST['msg_already']) : __('You are already subscribed to this newsletter.', 'zen-mailpoet-helper');

        // Validation: Email is required and must be valid format
        if (empty($email) || !is_email($email)) {
            wp_send_json(array(
                'success' => false,
                'code'    => 'email_invalid',
                'message' => __('Please enter a valid email address.', 'zen-mailpoet-helper')
            ));
        }

        // Parse list IDs
        $list_ids = array();
        if (!empty($list_ids_raw)) {
            $list_ids = array_map('intval', array_map('trim', explode(',', $list_ids_raw)));
            $list_ids = array_filter($list_ids); // Remove zeroes/empty IDs
        }

        // Validation: List configuration is required
        if (empty($list_ids)) {
            wp_send_json(array(
                'success' => false,
                'code'    => 'missing_list_configuration',
                'message' => __('No subscriber list has been configured for this popup.', 'zen-mailpoet-helper')
            ));
        }

        // Gather optional metadata
        $metadata = array();
        if (isset($_POST['first_name'])) {
            $metadata['first_name'] = sanitize_text_field($_POST['first_name']);
        }
        if (isset($_POST['last_name'])) {
            $metadata['last_name'] = sanitize_text_field($_POST['last_name']);
        }

        // 3. Call MailPoet Adapter service
        try {
            $adapter = new Zen_MailPoet_Adapter();
            $result = $adapter->subscribe($email, $list_ids, $metadata);

            if ($result['success']) {
                if ($result['code'] === 'already_subscribed') {
                    // Subscription exists: return failure state with custom message
                    wp_send_json(array(
                        'success' => false,
                        'code'    => 'already_subscribed',
                        'message' => $msg_already
                    ));
                } else {
                    // New subscription successful
                    wp_send_json(array(
                        'success'    => true,
                        'code'       => 'subscribed',
                        'closePopup' => true,
                        'message'    => $msg_success
                    ));
                }
            } else {
                // Adapter subscription failed with mapped error code
                $mapped_msg = $msg_error;
                if ($result['code'] === 'email_invalid') {
                    $mapped_msg = __('Please enter a valid email address.', 'zen-mailpoet-helper');
                } elseif ($result['code'] === 'email_required') {
                    $mapped_msg = __('Email address is required.', 'zen-mailpoet-helper');
                }

                wp_send_json(array(
                    'success'     => false,
                    'code'        => $result['code'],
                    'message'     => $mapped_msg,
                    'raw_message' => isset($result['message']) ? $result['message'] : ''
                ));
            }

        } catch (\Exception $e) {
            // General configuration or API load failure
            wp_send_json(array(
                'success' => false,
                'code'    => 'service_error',
                'message' => $e->getMessage()
            ));
        }
    }
}

new Zen_MailPoet_AJAX_Subscribe();
