<?php
/**
 * The Email Channel class.
 *
 * Handles sending email notifications.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Notifications/Channels
 */

namespace Ryvr\Notifications\Channels;

/**
 * The Email Channel class.
 *
 * This class handles sending email notifications to users.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Notifications/Channels
 */
class Email_Channel {

    /**
     * Send a notification via email.
     *
     * @param int    $user_id User ID.
     * @param string $subject Email subject.
     * @param string $body Email body.
     * @param array  $data Additional data.
     * @return bool Whether the email was sent.
     */
    public function send($user_id, $subject, $body, $data = []) {
        // Get user data.
        $user = get_userdata($user_id);
        
        if (!$user) {
            return false;
        }
        
        // Get user email.
        $email = $user->user_email;
        
        if (empty($email)) {
            return false;
        }
        
        // Get site info.
        $site_name = get_bloginfo('name');
        $admin_email = get_bloginfo('admin_email');
        
        // Email headers.
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $site_name, $admin_email),
        ];
        
        // Format email body.
        $email_body = $this->format_email_body($subject, $body, $data);
        
        // Send email.
        $result = wp_mail($email, $subject, $email_body, $headers);
        
        return $result;
    }
    
    /**
     * Format the email body with HTML.
     *
     * @param string $subject Email subject.
     * @param string $body Email body.
     * @param array  $data Additional data.
     * @return string Formatted email body.
     */
    private function format_email_body($subject, $body, $data = []) {
        // Get site info.
        $site_name = get_bloginfo('name');
        $site_url = get_bloginfo('url');
        $site_logo = isset($data['site_logo']) ? $data['site_logo'] : '';
        
        if (empty($site_logo)) {
            // Try to get logo from customizer.
            $custom_logo_id = get_theme_mod('custom_logo');
            if ($custom_logo_id) {
                $site_logo = wp_get_attachment_image_url($custom_logo_id, 'full');
            }
        }
        
        // Start building HTML email.
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <title>' . esc_html($subject) . '</title>
            <style type="text/css">
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333333;
                    background-color: #f9f9f9;
                    margin: 0;
                    padding: 0;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                    padding: 20px;
                    border-radius: 5px;
                    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                }
                .header {
                    text-align: center;
                    padding-bottom: 20px;
                    border-bottom: 1px solid #eeeeee;
                    margin-bottom: 20px;
                }
                .logo {
                    max-width: 200px;
                    height: auto;
                }
                .content {
                    padding: 20px 0;
                }
                .footer {
                    text-align: center;
                    padding-top: 20px;
                    border-top: 1px solid #eeeeee;
                    margin-top: 20px;
                    font-size: 12px;
                    color: #888888;
                }
                a {
                    color: #0066cc;
                    text-decoration: none;
                }
                .button {
                    display: inline-block;
                    background-color: #0066cc;
                    color: #ffffff !important;
                    padding: 10px 20px;
                    border-radius: 5px;
                    margin: 15px 0;
                    text-decoration: none;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">';
        
        if (!empty($site_logo)) {
            $html .= '<img src="' . esc_url($site_logo) . '" alt="' . esc_attr($site_name) . '" class="logo">';
        } else {
            $html .= '<h2>' . esc_html($site_name) . '</h2>';
        }
        
        $html .= '</div>
                <div class="content">
                    <h3>' . esc_html($subject) . '</h3>
                    <p>' . wp_kses_post(nl2br($body)) . '</p>';
        
        // Add action button if URL is provided.
        if (isset($data['action_url']) && isset($data['action_text'])) {
            $html .= '<p style="text-align: center;">
                <a href="' . esc_url($data['action_url']) . '" class="button">' . esc_html($data['action_text']) . '</a>
            </p>';
        } else if (isset($data['task_url'])) {
            $html .= '<p style="text-align: center;">
                <a href="' . esc_url($data['task_url']) . '" class="button">' . esc_html__('View Task', 'ryvr-ai') . '</a>
            </p>';
        } else if (isset($data['approval_url'])) {
            $html .= '<p style="text-align: center;">
                <a href="' . esc_url($data['approval_url']) . '" class="button">' . esc_html__('Approve Task', 'ryvr-ai') . '</a>
            </p>';
        }
        
        $html .= '</div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . esc_html($site_name) . '. ' . esc_html__('All rights reserved.', 'ryvr-ai') . '</p>
                    <p>
                        <a href="' . esc_url($site_url) . '">' . esc_html__('Visit Website', 'ryvr-ai') . '</a> | 
                        <a href="' . esc_url(admin_url('admin.php?page=ryvr-settings')) . '">' . esc_html__('Manage Preferences', 'ryvr-ai') . '</a>
                    </p>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
} 