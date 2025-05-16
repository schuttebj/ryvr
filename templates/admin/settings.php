<?php
/**
 * Settings page template.
 *
 * @package Ryvr
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get API Manager.
$api_manager = ryvr()->get_component( 'api_manager' );

// Get services.
$openai_service = $api_manager ? $api_manager->get_service( 'openai' ) : null;
$dataforseo_service = $api_manager ? $api_manager->get_service( 'dataforseo' ) : null;

// Get settings values.
$openai_api_key = get_option( 'ryvr_openai_api_key', '' );
$dataforseo_username = get_option( 'ryvr_dataforseo_api_login', '' );
$dataforseo_password = get_option( 'ryvr_dataforseo_api_password', '' );
$dataforseo_sandbox_mode = get_option( 'ryvr_dataforseo_sandbox_mode', 'on' ) === 'on';
$debug_mode = get_option( 'ryvr_debug_mode', 'off' ) === 'on';
$log_api_calls = get_option( 'ryvr_log_api_calls', 'off' ) === 'on';

// Handle form submission.
if ( isset( $_POST['ryvr_settings_nonce'] ) && wp_verify_nonce( $_POST['ryvr_settings_nonce'], 'ryvr_save_settings' ) ) {
    // Save OpenAI API key.
    if ( isset( $_POST['ryvr_openai_api_key'] ) ) {
        $openai_api_key = sanitize_text_field( $_POST['ryvr_openai_api_key'] );
        update_option( 'ryvr_openai_api_key', $openai_api_key );
    }
    
    // Save DataForSEO credentials.
    if ( isset( $_POST['ryvr_dataforseo_username'] ) ) {
        $dataforseo_username = sanitize_text_field( $_POST['ryvr_dataforseo_username'] );
        update_option( 'ryvr_dataforseo_api_login', $dataforseo_username );
    }
    
    if ( isset( $_POST['ryvr_dataforseo_password'] ) ) {
        $dataforseo_password = sanitize_text_field( $_POST['ryvr_dataforseo_password'] );
        update_option( 'ryvr_dataforseo_api_password', $dataforseo_password );
    }
    
    // Save sandbox mode.
    $dataforseo_sandbox_mode = isset( $_POST['ryvr_dataforseo_sandbox_mode'] );
    update_option( 'ryvr_dataforseo_sandbox_mode', $dataforseo_sandbox_mode ? 'on' : 'off' );
    
    // Save debug mode.
    $debug_mode = isset( $_POST['ryvr_debug_mode'] );
    update_option( 'ryvr_debug_mode', $debug_mode ? 'on' : 'off' );
    
    // Save API call logging.
    $log_api_calls = isset( $_POST['ryvr_log_api_calls'] );
    update_option( 'ryvr_log_api_calls', $log_api_calls ? 'on' : 'off' );
    
    // Display success message.
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully.', 'ryvr-ai' ) . '</p></div>';
}
?>

<div class="wrap ryvr-settings-wrap">
    <h1><?php esc_html_e( 'Ryvr AI Platform Settings', 'ryvr-ai' ); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field( 'ryvr_save_settings', 'ryvr_settings_nonce' ); ?>
        
        <div class="ryvr-settings-section">
            <h2><?php esc_html_e( 'API Settings', 'ryvr-ai' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ryvr_openai_api_key"><?php esc_html_e( 'OpenAI API Key', 'ryvr-ai' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="ryvr_openai_api_key" name="ryvr_openai_api_key" value="<?php echo esc_attr( $openai_api_key ); ?>" class="regular-text" />
                        <p class="description">
                            <?php esc_html_e( 'Enter your OpenAI API key. You can get one from', 'ryvr-ai' ); ?>
                            <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI API Keys</a>.
                        </p>
                        <?php if ( $openai_service && $openai_api_key ) : ?>
                            <p>
                                <button type="button" class="button" id="ryvr-test-openai">
                                    <?php esc_html_e( 'Test Connection', 'ryvr-ai' ); ?>
                                </button>
                                <span id="ryvr-openai-test-result"></span>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="ryvr_dataforseo_username"><?php esc_html_e( 'DataForSEO Username', 'ryvr-ai' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="ryvr_dataforseo_username" name="ryvr_dataforseo_username" value="<?php echo esc_attr( $dataforseo_username ); ?>" class="regular-text" />
                        <p class="description">
                            <?php esc_html_e( 'Enter your DataForSEO username. You can get one from', 'ryvr-ai' ); ?>
                            <a href="https://app.dataforseo.com/register" target="_blank">DataForSEO Registration</a>.
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="ryvr_dataforseo_password"><?php esc_html_e( 'DataForSEO Password', 'ryvr-ai' ); ?></label>
                    </th>
                    <td>
                        <input type="password" id="ryvr_dataforseo_password" name="ryvr_dataforseo_password" value="<?php echo esc_attr( $dataforseo_password ); ?>" class="regular-text" />
                        <p class="description">
                            <?php esc_html_e( 'Enter your DataForSEO password.', 'ryvr-ai' ); ?>
                        </p>
                        <?php if ( $dataforseo_service && $dataforseo_username && $dataforseo_password ) : ?>
                            <p>
                                <button type="button" class="button" id="ryvr-test-dataforseo">
                                    <?php esc_html_e( 'Test Connection', 'ryvr-ai' ); ?>
                                </button>
                                <span id="ryvr-dataforseo-test-result"></span>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="ryvr_dataforseo_sandbox_mode"><?php esc_html_e( 'DataForSEO Sandbox Mode', 'ryvr-ai' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="ryvr_dataforseo_sandbox_mode" name="ryvr_dataforseo_sandbox_mode" <?php checked( $dataforseo_sandbox_mode ); ?> />
                            <?php esc_html_e( 'Enable sandbox mode', 'ryvr-ai' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Sandbox mode allows you to test DataForSEO API integration without using real credits.', 'ryvr-ai' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="ryvr-settings-section">
            <h2><?php esc_html_e( 'Advanced Settings', 'ryvr-ai' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ryvr_debug_mode"><?php esc_html_e( 'Debug Mode', 'ryvr-ai' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="ryvr_debug_mode" name="ryvr_debug_mode" <?php checked( $debug_mode ); ?> />
                            <?php esc_html_e( 'Enable debug mode', 'ryvr-ai' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Debug mode enables additional logging and debugging information.', 'ryvr-ai' ); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="ryvr_log_api_calls"><?php esc_html_e( 'Log API Calls', 'ryvr-ai' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="ryvr_log_api_calls" name="ryvr_log_api_calls" <?php checked( $log_api_calls ); ?> />
                            <?php esc_html_e( 'Enable API call logging', 'ryvr-ai' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'Log all API calls for debugging and monitoring purposes.', 'ryvr-ai' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'ryvr-ai' ); ?>" />
        </p>
    </form>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Test OpenAI connection
        $('#ryvr-test-openai').on('click', function() {
            var $button = $(this);
            var $result = $('#ryvr-openai-test-result');
            
            $button.prop('disabled', true);
            $result.html('<span class="spinner is-active"></span> <?php esc_html_e( 'Testing connection...', 'ryvr-ai' ); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ryvr_test_openai_connection',
                    api_key: $('#ryvr_openai_api_key').val(),
                    nonce: '<?php echo wp_create_nonce( 'ryvr_test_api' ); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span class="dashicons dashicons-yes" style="color:green;"></span> <?php esc_html_e( 'Connection successful!', 'ryvr-ai' ); ?>');
                    } else {
                        $result.html('<span class="dashicons dashicons-no" style="color:red;"></span> ' + response.data.message);
                    }
                },
                error: function() {
                    $result.html('<span class="dashicons dashicons-no" style="color:red;"></span> <?php esc_html_e( 'Connection failed. Please try again.', 'ryvr-ai' ); ?>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
        
        // Test DataForSEO connection
        $('#ryvr-test-dataforseo').on('click', function() {
            var $button = $(this);
            var $result = $('#ryvr-dataforseo-test-result');
            
            $button.prop('disabled', true);
            $result.html('<span class="spinner is-active"></span> <?php esc_html_e( 'Testing connection...', 'ryvr-ai' ); ?>');
            
            // For debugging
            console.log('Sending DataForSEO test with username: ' + $('#ryvr_dataforseo_username').val());
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ryvr_test_dataforseo_connection',
                    username: $('#ryvr_dataforseo_username').val(),
                    password: $('#ryvr_dataforseo_password').val(),
                    nonce: '<?php echo wp_create_nonce( 'ryvr_test_api' ); ?>'
                },
                success: function(response) {
                    console.log('DataForSEO response:', response);
                    if (response.success) {
                        $result.html('<span class="dashicons dashicons-yes" style="color:green;"></span> <?php esc_html_e( 'Connection successful!', 'ryvr-ai' ); ?>');
                    } else {
                        $result.html('<span class="dashicons dashicons-no" style="color:red;"></span> ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('DataForSEO AJAX error:', status, error);
                    $result.html('<span class="dashicons dashicons-no" style="color:red;"></span> <?php esc_html_e( 'Connection failed. Please try again.', 'ryvr-ai' ); ?>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
    });
</script> 