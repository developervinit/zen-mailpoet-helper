<?php
/**
 * Service adapter for MailPoet API operations.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Zen_MailPoet_Adapter {

    /**
     * Subscribe an email to a set of list IDs.
     *
     * @param string $email
     * @param array $list_ids
     * @param array $metadata
     * @return array Response contract.
     * @throws Exception
     */
    public function subscribe($email, array $list_ids, array $metadata = []) {
        if (!class_exists('\MailPoet\API\API')) {
            throw new \Exception(__('MailPoet is not active or installed.', 'zen-mailpoet-helper'));
        }

        try {
            $mailpoet_api = \MailPoet\API\API::MP('v1');
        } catch (\Exception $e) {
            throw new \Exception(__('Failed to load MailPoet API v1.', 'zen-mailpoet-helper'));
        }

        $subscriber_data = array(
            'email' => $email,
        );

        if (!empty($metadata['first_name'])) {
            $subscriber_data['first_name'] = sanitize_text_field($metadata['first_name']);
        }
        if (!empty($metadata['last_name'])) {
            $subscriber_data['last_name'] = sanitize_text_field($metadata['last_name']);
        }

        $subscriber_exists_code = 12; // MailPoet's SUBSCRIBER_EXISTS code
        if (class_exists('\MailPoet\API\MP\v1\APIException') && defined('\MailPoet\API\MP\v1\APIException::SUBSCRIBER_EXISTS')) {
            $subscriber_exists_code = \MailPoet\API\MP\v1\APIException::SUBSCRIBER_EXISTS;
        }

        try {
            // Attempt to add new subscriber
            $subscriber = $mailpoet_api->addSubscriber($subscriber_data, $list_ids);
            return $this->normalize_response($subscriber, 'subscribed');

        } catch (\Exception $e) {
            // Handle existing subscriber
            if ($e->getCode() === $subscriber_exists_code) {
                try {
                    $existing = $mailpoet_api->getSubscriber($email);
                    if (!empty($existing['id'])) {
                        // Update subscriber data (names)
                        $mailpoet_api->updateSubscriber($existing['id'], $subscriber_data);
                        // Subscribe them to the specified lists
                        $mailpoet_api->subscribeToLists($existing['id'], $list_ids);
                        
                        return $this->normalize_response($existing, 'already_subscribed');
                    }
                } catch (\Exception $update_error) {
                    return $this->map_errors($update_error);
                }
            }

            return $this->map_errors($e);
        }
    }
    /**
     * Retrieve all available MailPoet subscriber lists.
     *
     * @return array
     */
    public function get_lists() {
        if (!class_exists('\MailPoet\API\API')) {
            return array();
        }

        try {
            $mailpoet_api = \MailPoet\API\API::MP('v1');
            return $mailpoet_api->getLists();
        } catch (\Exception $e) {
            return array();
        }
    }

    /**
     * Normalize MailPoet success responses into our custom contract.
     */
    private function normalize_response($subscriber, $status_code) {
        return array(
            'success'    => true,
            'code'       => $status_code,
            'closePopup' => true,
            'data'       => $subscriber
        );
    }

    /**
     * Map MailPoet errors to our custom codes and messages.
     */
    private function map_errors(\Exception $e) {
        $message = $e->getMessage();
        $code = 'error';
        $error_code = $e->getCode();

        $email_req_code = 11; // MailPoet's EMAIL_ADDRESS_REQUIRED code
        if (class_exists('\MailPoet\API\MP\v1\APIException') && defined('\MailPoet\API\MP\v1\APIException::EMAIL_ADDRESS_REQUIRED')) {
            $email_req_code = \MailPoet\API\MP\v1\APIException::EMAIL_ADDRESS_REQUIRED;
        }

        if ($error_code === $email_req_code || strpos($message, 'email') !== false && strpos($message, 'required') !== false) {
            $code = 'email_required';
        } elseif (strpos($message, 'valid') !== false) {
            $code = 'email_invalid';
        }

        return array(
            'success' => false,
            'code'    => $code,
            'message' => $message
        );
    }
}
