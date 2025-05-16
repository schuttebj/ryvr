<?php
/**
 * Core Functions
 *
 * Utility functions for the Ryvr platform.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Core
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Log a message to the plugin's log file.
 *
 * @param string $message The message to log.
 * @param string $level The log level (debug, info, warning, error).
 * @param string $component The component that generated the log.
 * @return bool Whether the message was logged successfully.
 */
function ryvr_log( $message, $level = 'info', $component = 'core' ) {
    // Check if debug mode is enabled.
    $debug_mode = get_option( 'ryvr_debug_mode', 'off' ) === 'on';
    
    // If level is debug and debug mode is off, skip.
    if ( 'debug' === $level && ! $debug_mode ) {
        return false;
    }
    
    // Format the message.
    $timestamp = current_time( 'mysql' );
    $log_message = sprintf( '[%s] [%s] [%s] %s', $timestamp, strtoupper( $level ), $component, $message );
    
    // Get log file path.
    $log_file = RYVR_LOGS_DIR . 'ryvr-' . date( 'Y-m-d' ) . '.log';
    
    // Write to the log file.
    $result = file_put_contents( $log_file, $log_message . PHP_EOL, FILE_APPEND );
    
    return $result !== false;
}

/**
 * Check if the current user has the specified capability.
 *
 * @param string $capability The capability to check for.
 * @return bool Whether the user has the capability.
 */
function ryvr_current_user_can( $capability ) {
    // Define custom capabilities.
    $custom_capabilities = [
        'ryvr_manage_settings'   => 'manage_options',
        'ryvr_view_dashboard'    => 'edit_posts',
        'ryvr_run_tasks'         => 'edit_posts',
        'ryvr_manage_api_keys'   => 'manage_options',
        'ryvr_view_reports'      => 'edit_posts',
        'ryvr_manage_credits'    => 'manage_options',
        'ryvr_manage_users'      => 'manage_options',
    ];
    
    // Check if this is a custom capability.
    if ( isset( $custom_capabilities[ $capability ] ) ) {
        // Map to WordPress capability.
        $wp_capability = $custom_capabilities[ $capability ];
        
        // Check if user has the WordPress capability.
        return current_user_can( $wp_capability );
    }
    
    // For standard capabilities, just use WordPress function.
    return current_user_can( $capability );
}

/**
 * Format credits.
 *
 * @param int $credits The number of credits.
 * @return string Formatted credits.
 */
function ryvr_format_credits( $credits ) {
    return number_format( $credits );
}

/**
 * Get user credits balance.
 *
 * @param int $user_id The user ID. Default is current user.
 * @return int The credits balance.
 */
function ryvr_get_user_credits( $user_id = 0 ) {
    global $wpdb;
    
    // Get the user ID if not provided.
    if ( empty( $user_id ) ) {
        $user_id = get_current_user_id();
    }
    
    // Get the table name.
    $table_name = $wpdb->prefix . 'ryvr_credits';
    
    // Query for the sum of credits.
    $credits = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(credits_amount) FROM {$table_name} WHERE user_id = %d",
            $user_id
        )
    );
    
    // Return the credits, or 0 if none found.
    return $credits ? (int) $credits : 0;
}

/**
 * Add credits to a user's account.
 *
 * @param int    $user_id The user ID.
 * @param int    $amount The amount of credits to add.
 * @param string $credits_type The type of credits (regular, bonus, etc.).
 * @param string $transaction_type The type of transaction (purchase, admin, refund, etc.).
 * @param int    $reference_id Reference ID (e.g., payment ID, task ID).
 * @param string $notes Notes for the transaction.
 * @return int|false The transaction ID or false on failure.
 */
function ryvr_add_user_credits( $user_id, $amount, $credits_type = 'regular', $transaction_type = 'admin', $reference_id = 0, $notes = '' ) {
    global $wpdb;
    
    // Get the table name.
    $table_name = $wpdb->prefix . 'ryvr_credits';
    
    // Insert the credits record.
    $result = $wpdb->insert(
        $table_name,
        [
            'user_id'         => $user_id,
            'credits_amount'  => $amount,
            'credits_type'    => $credits_type,
            'transaction_type' => $transaction_type,
            'reference_id'    => $reference_id,
            'notes'           => $notes,
            'created_at'      => current_time( 'mysql', true ),
        ],
        [
            '%d',
            '%d',
            '%s',
            '%s',
            '%d',
            '%s',
            '%s',
        ]
    );
    
    return $result ? $wpdb->insert_id : false;
}

/**
 * Deduct credits from a user's account.
 *
 * @param int    $user_id The user ID.
 * @param int    $amount The amount of credits to deduct.
 * @param string $transaction_type The type of transaction (task, api, etc.).
 * @param int    $reference_id Reference ID (e.g., task ID).
 * @param string $notes Notes for the transaction.
 * @return int|false The transaction ID or false on failure.
 */
function ryvr_deduct_user_credits( $user_id, $amount, $transaction_type = 'task', $reference_id = 0, $notes = '' ) {
    // Add negative credits.
    return ryvr_add_user_credits( $user_id, -1 * abs( $amount ), 'regular', $transaction_type, $reference_id, $notes );
}

/**
 * Check if a user has enough credits.
 *
 * @param int $user_id The user ID.
 * @param int $amount The amount of credits needed.
 * @return bool Whether the user has enough credits.
 */
function ryvr_user_has_credits( $user_id, $amount ) {
    $balance = ryvr_get_user_credits( $user_id );
    
    return $balance >= $amount;
}

/**
 * Get task status label.
 *
 * @param string $status The task status.
 * @return string The human-readable status label.
 */
function ryvr_get_task_status_label( $status ) {
    $statuses = [
        'draft'             => __( 'Draft', 'ryvr-ai' ),
        'pending'           => __( 'Pending', 'ryvr-ai' ),
        'approval_required' => __( 'Approval Required', 'ryvr-ai' ),
        'processing'        => __( 'Processing', 'ryvr-ai' ),
        'completed'         => __( 'Completed', 'ryvr-ai' ),
        'failed'            => __( 'Failed', 'ryvr-ai' ),
        'canceled'          => __( 'Canceled', 'ryvr-ai' ),
    ];
    
    return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
}

/**
 * Get task status CSS class.
 *
 * @param string $status The task status.
 * @return string The CSS class for the status.
 */
function ryvr_get_task_status_class( $status ) {
    $classes = [
        'draft'             => 'status-draft',
        'pending'           => 'status-pending',
        'approval_required' => 'status-approval',
        'processing'        => 'status-processing',
        'completed'         => 'status-completed',
        'failed'            => 'status-failed',
        'canceled'          => 'status-canceled',
    ];
    
    return isset( $classes[ $status ] ) ? $classes[ $status ] : '';
}

/**
 * Parse JSON safely.
 *
 * @param string $json The JSON string to parse.
 * @param bool   $assoc Whether to return an associative array.
 * @return mixed The parsed JSON data or null on failure.
 */
function ryvr_parse_json( $json, $assoc = true ) {
    if ( empty( $json ) ) {
        return null;
    }
    
    $data = json_decode( $json, $assoc );
    
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        ryvr_log( 'JSON parse error: ' . json_last_error_msg(), 'error', 'json' );
        return null;
    }
    
    return $data;
}

/**
 * Sanitize and validate API credentials.
 *
 * @param string $service The API service (openai, dataforseo, etc.).
 * @param string $api_key The API key.
 * @param string $api_secret The API secret (if applicable).
 * @return array The sanitized credentials.
 */
function ryvr_sanitize_api_credentials( $service, $api_key, $api_secret = '' ) {
    $credentials = [
        'api_key'    => sanitize_text_field( $api_key ),
        'api_secret' => sanitize_text_field( $api_secret ),
    ];
    
    return $credentials;
}

/**
 * Sanitize inputs for task parameters.
 *
 * @param array $inputs The task inputs to sanitize.
 * @return array The sanitized inputs.
 */
function ryvr_sanitize_task_inputs( $inputs ) {
    if ( ! is_array( $inputs ) ) {
        return [];
    }
    
    $sanitized = [];
    
    foreach ( $inputs as $key => $value ) {
        if ( is_array( $value ) ) {
            $sanitized[ $key ] = ryvr_sanitize_task_inputs( $value );
        } else {
            $sanitized[ $key ] = sanitize_text_field( $value );
        }
    }
    
    return $sanitized;
}

/**
 * Get API usage stats for a user.
 *
 * @param int    $user_id The user ID.
 * @param string $service The API service.
 * @param string $period The period (today, week, month, all).
 * @return array The usage stats.
 */
function ryvr_get_api_usage( $user_id, $service = '', $period = 'month' ) {
    global $wpdb;
    
    // Get the table name.
    $table_name = $wpdb->prefix . 'ryvr_api_logs';
    
    // Prepare the query.
    $query = "SELECT COUNT(*) as calls, SUM(credits_used) as credits FROM {$table_name} WHERE user_id = %d";
    $params = [ $user_id ];
    
    // Add service filter if provided.
    if ( ! empty( $service ) ) {
        $query .= " AND service = %s";
        $params[] = $service;
    }
    
    // Add period filter.
    switch ( $period ) {
        case 'today':
            $query .= " AND DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $query .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $query .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        // 'all' doesn't need a filter.
    }
    
    // Execute the query.
    $results = $wpdb->get_row( $wpdb->prepare( $query, $params ), ARRAY_A );
    
    // Return the results.
    return [
        'calls'   => (int) $results['calls'],
        'credits' => (int) $results['credits'],
    ];
}

/**
 * Get client credits balance.
 *
 * @param int $client_id The client ID.
 * @return int The credits balance.
 */
function ryvr_get_client_credits( $client_id ) {
    global $wpdb;
    
    // Get the table name.
    $table_name = $wpdb->prefix . 'ryvr_client_credits';
    
    // Query for the sum of credits.
    $credits = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(credits_amount) FROM {$table_name} WHERE client_id = %d",
            $client_id
        )
    );
    
    // Return the credits, or 0 if none found.
    return $credits ? (int) $credits : 0;
}

/**
 * Add credits to a client's account.
 *
 * @param int    $client_id The client ID.
 * @param int    $amount The amount of credits to add.
 * @param string $credits_type The type of credits (regular, bonus, etc.).
 * @param string $transaction_type The type of transaction (purchase, admin, refund, etc.).
 * @param int    $reference_id Reference ID (e.g., payment ID, task ID).
 * @param string $notes Notes for the transaction.
 * @return int|false The transaction ID or false on failure.
 */
function ryvr_add_client_credits( $client_id, $amount, $credits_type = 'regular', $transaction_type = 'admin', $reference_id = 0, $notes = '' ) {
    global $wpdb;
    
    // Get the table name.
    $table_name = $wpdb->prefix . 'ryvr_client_credits';
    
    // Insert the credits record.
    $result = $wpdb->insert(
        $table_name,
        [
            'client_id'       => $client_id,
            'credits_amount'  => $amount,
            'credits_type'    => $credits_type,
            'transaction_type'=> $transaction_type,
            'reference_id'    => $reference_id,
            'notes'           => $notes,
            'created_at'      => current_time( 'mysql', true ),
        ],
        [
            '%d',
            '%d',
            '%s',
            '%s',
            '%d',
            '%s',
            '%s',
        ]
    );
    
    return $result ? $wpdb->insert_id : false;
}

/**
 * Deduct credits from a client's account.
 *
 * @param int    $client_id The client ID.
 * @param int    $amount The amount of credits to deduct (use a negative number).
 * @param string $transaction_type The type of transaction (task, api, etc.).
 * @param int    $reference_id Reference ID (e.g., task ID).
 * @param string $notes Notes for the transaction.
 * @return int|false The transaction ID or false on failure.
 */
function ryvr_deduct_client_credits( $client_id, $amount, $transaction_type = 'task', $reference_id = 0, $notes = '' ) {
    return ryvr_add_client_credits(
        $client_id,
        -1 * abs( $amount ),
        'regular',
        $transaction_type,
        $reference_id,
        $notes
    );
}

/**
 * Check if a client has enough credits.
 *
 * @param int $client_id The client ID.
 * @param int $amount The amount of credits needed.
 * @return bool True if the client has enough credits, false otherwise.
 */
function ryvr_client_has_enough_credits( $client_id, $amount ) {
    $balance = ryvr_get_client_credits( $client_id );
    return $balance >= $amount;
} 