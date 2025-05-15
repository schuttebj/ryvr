<?php
/**
 * Settings page of the plugin.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Admin/Partials
 */

// Don't allow direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get active tab
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';

// Process form submissions
if ( isset( $_POST['ryvr_settings_submit'] ) && check_admin_referer( 'ryvr_settings_nonce', 'ryvr_settings_nonce' ) ) {
    // Handle general settings
    if ( $active_tab === 'general' ) {
        update_option( 'ryvr_sandbox_mode', isset( $_POST['sandbox_mode'] ) ? 1 : 0 );
        update_option( 'ryvr_api_cache_ttl', isset( $_POST['api_cache_ttl'] ) ? (int) $_POST['api_cache_ttl'] : 3600 );
        
        // Success message
        add_settings_error( 'ryvr_settings', 'settings_updated', 'Settings saved successfully.', 'updated' );
    }
    
    // Handle API keys
    elseif ( $active_tab === 'api_keys' ) {
        // Save OpenAI API key
        if ( isset( $_POST['openai_api_key'] ) && !empty( $_POST['openai_api_key'] ) ) {
            $openai_api_key = sanitize_text_field( $_POST['openai_api_key'] );
            
            // Store in database
            global $wpdb;
            $db_manager = new Ryvr\Database\Database_Manager();
            $api_keys_table = $db_manager->get_table( 'api_keys' );
            $user_id = get_current_user_id();
            
            // Check if key already exists
            $existing_key = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $api_keys_table WHERE user_id = %d AND service = %s",
                    $user_id,
                    'openai'
                )
            );
            
            if ( $existing_key ) {
                // Update existing key
                $wpdb->update(
                    $api_keys_table,
                    array(
                        'api_key' => $openai_api_key,
                        'updated_at' => current_time( 'mysql' ),
                    ),
                    array(
                        'id' => $existing_key,
                    )
                );
            } else {
                // Insert new key
                $wpdb->insert(
                    $api_keys_table,
                    array(
                        'user_id' => $user_id,
                        'service' => 'openai',
                        'api_key' => $openai_api_key,
                        'is_active' => 1,
                        'created_at' => current_time( 'mysql' ),
                        'updated_at' => current_time( 'mysql' ),
                    )
                );
            }
        }
        
        // Save DataForSEO API credentials
        if ( isset( $_POST['dataforseo_username'] ) && !empty( $_POST['dataforseo_username'] ) && 
             isset( $_POST['dataforseo_password'] ) && !empty( $_POST['dataforseo_password'] ) ) {
            
            $dataforseo_username = sanitize_text_field( $_POST['dataforseo_username'] );
            $dataforseo_password = sanitize_text_field( $_POST['dataforseo_password'] );
            
            // Store in database
            global $wpdb;
            $db_manager = new Ryvr\Database\Database_Manager();
            $api_keys_table = $db_manager->get_table( 'api_keys' );
            $user_id = get_current_user_id();
            
            // Check if key already exists
            $existing_key = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $api_keys_table WHERE user_id = %d AND service = %s",
                    $user_id,
                    'dataforseo'
                )
            );
            
            if ( $existing_key ) {
                // Update existing key
                $wpdb->update(
                    $api_keys_table,
                    array(
                        'api_key' => $dataforseo_username,
                        'api_secret' => $dataforseo_password,
                        'updated_at' => current_time( 'mysql' ),
                    ),
                    array(
                        'id' => $existing_key,
                    )
                );
            } else {
                // Insert new key
                $wpdb->insert(
                    $api_keys_table,
                    array(
                        'user_id' => $user_id,
                        'service' => 'dataforseo',
                        'api_key' => $dataforseo_username,
                        'api_secret' => $dataforseo_password,
                        'is_active' => 1,
                        'created_at' => current_time( 'mysql' ),
                        'updated_at' => current_time( 'mysql' ),
                    )
                );
            }
        }
        
        // Success message
        add_settings_error( 'ryvr_settings', 'settings_updated', 'API keys saved successfully.', 'updated' );
    }
}

// Get saved values
$sandbox_mode = get_option( 'ryvr_sandbox_mode', 0 );
$api_cache_ttl = get_option( 'ryvr_api_cache_ttl', 3600 );

// Get API keys
global $wpdb;
$db_manager = new Ryvr\Database\Database_Manager();
$api_keys_table = $db_manager->get_table( 'api_keys' );
$user_id = get_current_user_id();

$openai_api_key = '';
$dataforseo_username = '';
$dataforseo_password = '';

$openai_key_row = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT api_key FROM $api_keys_table WHERE user_id = %d AND service = %s",
        $user_id,
        'openai'
    )
);

if ( $openai_key_row ) {
    $openai_api_key = $openai_key_row->api_key;
}

$dataforseo_key_row = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT api_key, api_secret FROM $api_keys_table WHERE user_id = %d AND service = %s",
        $user_id,
        'dataforseo'
    )
);

if ( $dataforseo_key_row ) {
    $dataforseo_username = $dataforseo_key_row->api_key;
    $dataforseo_password = $dataforseo_key_row->api_secret;
}

?>

<div class="wrap">
    <h1>Ryvr AI Platform Settings</h1>
    
    <?php settings_errors( 'ryvr_settings' ); ?>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=ryvr-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">General Settings</a>
        <a href="?page=ryvr-settings&tab=api_keys" class="nav-tab <?php echo $active_tab === 'api_keys' ? 'nav-tab-active' : ''; ?>">API Keys</a>
        <a href="?page=ryvr-settings&tab=credits" class="nav-tab <?php echo $active_tab === 'credits' ? 'nav-tab-active' : ''; ?>">Credits</a>
    </h2>
    
    <form method="post" action="">
        <?php wp_nonce_field( 'ryvr_settings_nonce', 'ryvr_settings_nonce' ); ?>
        
        <?php if ( $active_tab === 'general' ) : ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="sandbox_mode">Sandbox Mode</label>
                    </th>
                    <td>
                        <input type="checkbox" name="sandbox_mode" id="sandbox_mode" value="1" <?php checked( $sandbox_mode, 1 ); ?>>
                        <p class="description">
                            Enable sandbox mode to test API functionality without making real API calls or using credits.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="api_cache_ttl">API Cache Duration</label>
                    </th>
                    <td>
                        <input type="number" name="api_cache_ttl" id="api_cache_ttl" value="<?php echo esc_attr( $api_cache_ttl ); ?>" min="60" step="60" class="regular-text">
                        <p class="description">
                            Duration in seconds to cache API responses (minimum 60 seconds).
                        </p>
                    </td>
                </tr>
            </table>
        <?php elseif ( $active_tab === 'api_keys' ) : ?>
            <h3>OpenAI API</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="openai_api_key">API Key</label>
                    </th>
                    <td>
                        <input type="password" name="openai_api_key" id="openai_api_key" value="<?php echo esc_attr( $openai_api_key ); ?>" class="regular-text">
                        <p class="description">
                            Your OpenAI API key. <a href="https://platform.openai.com/account/api-keys" target="_blank">Get your API key</a>
                        </p>
                    </td>
                </tr>
            </table>
            
            <h3>DataForSEO API</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="dataforseo_username">API Username</label>
                    </th>
                    <td>
                        <input type="text" name="dataforseo_username" id="dataforseo_username" value="<?php echo esc_attr( $dataforseo_username ); ?>" class="regular-text">
                        <p class="description">
                            Your DataForSEO API username.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="dataforseo_password">API Password</label>
                    </th>
                    <td>
                        <input type="password" name="dataforseo_password" id="dataforseo_password" value="<?php echo esc_attr( $dataforseo_password ); ?>" class="regular-text">
                        <p class="description">
                            Your DataForSEO API password. <a href="https://dataforseo.com/" target="_blank">Get your DataForSEO API credentials</a>
                        </p>
                    </td>
                </tr>
            </table>
        <?php elseif ( $active_tab === 'credits' ) : ?>
            <h3>Credits Management</h3>
            <p>Credits are used to pay for API usage. Each API call consumes a certain number of credits.</p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Current Balance</th>
                    <td>
                        <?php
                        $credits_table = $db_manager->get_table( 'credits' );
                        $credits_balance = $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT SUM(credits_amount) FROM $credits_table WHERE user_id = %d",
                                $user_id
                            )
                        );
                        $credits_balance = $credits_balance ?: 0;
                        ?>
                        <strong><?php echo number_format( $credits_balance ); ?> credits</strong>
                    </td>
                </tr>
            </table>
            
            <h3>Credits Usage History</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $credits_history = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM $credits_table WHERE user_id = %d ORDER BY created_at DESC LIMIT 10",
                            $user_id
                        )
                    );
                    
                    if ( empty( $credits_history ) ) {
                        echo '<tr><td colspan="4">No credits history yet.</td></tr>';
                    } else {
                        foreach ( $credits_history as $entry ) {
                            ?>
                            <tr>
                                <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->created_at ) ) ); ?></td>
                                <td><?php 
                                    if ( $entry->credits_amount > 0 ) {
                                        echo '<span style="color: green;">+' . esc_html( $entry->credits_amount ) . '</span>';
                                    } else {
                                        echo '<span style="color: red;">' . esc_html( $entry->credits_amount ) . '</span>';
                                    }
                                ?></td>
                                <td><?php echo esc_html( $entry->transaction_type ); ?></td>
                                <td><?php echo esc_html( $entry->notes ); ?></td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                </tbody>
            </table>
            
            <p><strong>Note:</strong> Credits are required for API usage. Please contact your administrator to add more credits to your account.</p>
        <?php endif; ?>
        
        <?php if ( $active_tab !== 'credits' ) : ?>
            <p class="submit">
                <input type="submit" name="ryvr_settings_submit" class="button button-primary" value="Save Settings">
            </p>
        <?php endif; ?>
    </form>
</div> 