<?php
/**
 * The Webhook Channel class.
 *
 * Handles sending notifications via webhooks.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Notifications/Channels
 */

namespace Ryvr\Notifications\Channels;

/**
 * The Webhook Channel class.
 *
 * This class handles sending notifications to external systems via webhooks.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Notifications/Channels
 */
class Webhook_Channel {

    /**
     * Send a notification via webhook.
     *
     * @param int    $user_id User ID.
     * @param string $subject Notification subject.
     * @param string $body Notification body.
     * @param array  $data Additional data.
     * @return bool Whether the webhook was sent.
     */
    public function send($user_id, $subject, $body, $data = []) {
        // Get user webhooks.
        $webhooks = $this->get_user_webhooks($user_id);
        
        if (empty($webhooks)) {
            return false;
        }
        
        // Get user data.
        $user = get_userdata($user_id);
        
        // Additional data to include in the webhook payload.
        $webhook_data = [
            'user_id' => $user_id,
            'user_email' => $user ? $user->user_email : '',
            'user_name' => $user ? $user->display_name : '',
            'subject' => $subject,
            'message' => $body,
            'timestamp' => current_time('mysql', true),
            'notification_type' => isset($data['template_id']) ? $data['template_id'] : 'general',
        ];
        
        // Merge with additional data.
        $payload = array_merge($webhook_data, $data);
        
        // Track success.
        $success = true;
        
        // Send to each webhook.
        foreach ($webhooks as $webhook) {
            $result = $this->send_webhook($webhook['url'], $payload, $webhook['secret']);
            
            if (!$result) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Get webhooks configured for a user.
     *
     * @param int $user_id User ID.
     * @return array Array of webhook configurations.
     */
    private function get_user_webhooks($user_id) {
        // Get user's webhook endpoints.
        $user_webhooks = get_user_meta($user_id, 'ryvr_notification_webhooks', true);
        
        if (empty($user_webhooks) || !is_array($user_webhooks)) {
            $user_webhooks = [];
        }
        
        // Get global webhooks from settings.
        $global_webhooks = get_option('ryvr_notification_webhooks', []);
        
        if (!is_array($global_webhooks)) {
            $global_webhooks = [];
        }
        
        // Combine user-specific and global webhooks.
        return array_merge($user_webhooks, $global_webhooks);
    }
    
    /**
     * Send data to a webhook endpoint.
     *
     * @param string $url Webhook URL.
     * @param array  $payload Data to send.
     * @param string $secret Secret for signing the request (optional).
     * @return bool Whether the webhook was sent successfully.
     */
    private function send_webhook($url, $payload, $secret = '') {
        // Ensure URL is valid.
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // JSON encode payload.
        $json_payload = wp_json_encode($payload);
        
        // Set up request arguments.
        $args = [
            'body' => $json_payload,
            'timeout' => 15,
            'redirection' => 5,
            'blocking' => true,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'data_format' => 'body',
        ];
        
        // Add signature if secret is provided.
        if (!empty($secret)) {
            $signature = $this->generate_signature($json_payload, $secret);
            $args['headers']['X-Ryvr-Signature'] = $signature;
        }
        
        // Send the request.
        $response = wp_remote_post($url, $args);
        
        // Check for errors.
        if (is_wp_error($response)) {
            // Log error.
            error_log('Webhook error: ' . $response->get_error_message());
            return false;
        }
        
        // Check response code.
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code < 200 || $response_code >= 300) {
            // Log error.
            error_log('Webhook error: HTTP ' . $response_code);
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate a signature for the webhook payload.
     *
     * @param string $payload JSON payload.
     * @param string $secret Secret key.
     * @return string Generated signature.
     */
    private function generate_signature($payload, $secret) {
        // Calculate HMAC signature.
        $signature = hash_hmac('sha256', $payload, $secret);
        
        return 'sha256=' . $signature;
    }
    
    /**
     * Register a webhook endpoint for a user.
     *
     * @param int    $user_id User ID.
     * @param string $url Webhook URL.
     * @param string $secret Secret for signing requests (optional).
     * @param array  $events Events to send to this webhook (empty for all).
     * @return bool Whether the registration was successful.
     */
    public function register_user_webhook($user_id, $url, $secret = '', $events = []) {
        // Ensure URL is valid.
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Get existing webhooks.
        $webhooks = get_user_meta($user_id, 'ryvr_notification_webhooks', true);
        
        if (empty($webhooks) || !is_array($webhooks)) {
            $webhooks = [];
        }
        
        // Add new webhook.
        $webhooks[] = [
            'url' => $url,
            'secret' => $secret,
            'events' => $events,
            'created_at' => current_time('mysql', true),
        ];
        
        // Update user meta.
        return update_user_meta($user_id, 'ryvr_notification_webhooks', $webhooks);
    }
    
    /**
     * Register a global webhook endpoint.
     *
     * @param string $url Webhook URL.
     * @param string $secret Secret for signing requests (optional).
     * @param array  $events Events to send to this webhook (empty for all).
     * @return bool Whether the registration was successful.
     */
    public function register_global_webhook($url, $secret = '', $events = []) {
        // Ensure URL is valid.
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Get existing webhooks.
        $webhooks = get_option('ryvr_notification_webhooks', []);
        
        if (!is_array($webhooks)) {
            $webhooks = [];
        }
        
        // Add new webhook.
        $webhooks[] = [
            'url' => $url,
            'secret' => $secret,
            'events' => $events,
            'created_at' => current_time('mysql', true),
        ];
        
        // Update option.
        return update_option('ryvr_notification_webhooks', $webhooks);
    }
} 