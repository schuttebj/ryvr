<?php
/**
 * The Settings class.
 *
 * Handles plugin settings.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Admin
 */

namespace Ryvr\Admin;

/**
 * The Settings class.
 *
 * This class handles plugin settings including saving, validation, and rendering.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Admin
 */
class Settings {

    /**
     * Settings sections.
     *
     * @var array
     */
    private $sections = [];

    /**
     * Settings fields.
     *
     * @var array
     */
    private $fields = [];

    /**
     * Constructor.
     */
    public function __construct() {
        // Define settings.
        $this->define_sections();
        $this->define_fields();
        
        // Register hooks.
        $this->register_hooks();
    }

    /**
     * Define settings sections.
     *
     * @return void
     */
    private function define_sections() {
        $this->sections = [
            'general' => [
                'id'    => 'general',
                'title' => __( 'General Settings', 'ryvr-ai' ),
                'desc'  => __( 'Configure general settings for the Ryvr AI Platform.', 'ryvr-ai' ),
            ],
            'api' => [
                'id'    => 'api',
                'title' => __( 'API Settings', 'ryvr-ai' ),
                'desc'  => __( 'Configure API credentials for external services.', 'ryvr-ai' ),
            ],
            'tasks' => [
                'id'    => 'tasks',
                'title' => __( 'Task Settings', 'ryvr-ai' ),
                'desc'  => __( 'Configure settings for tasks and task processing.', 'ryvr-ai' ),
            ],
            'advanced' => [
                'id'    => 'advanced',
                'title' => __( 'Advanced Settings', 'ryvr-ai' ),
                'desc'  => __( 'Configure advanced settings for the Ryvr AI Platform.', 'ryvr-ai' ),
            ],
        ];
    }

    /**
     * Define settings fields.
     *
     * @return void
     */
    private function define_fields() {
        $this->fields = [
            'general' => [
                [
                    'id'      => 'ryvr_debug_mode',
                    'title'   => __( 'Debug Mode', 'ryvr-ai' ),
                    'desc'    => __( 'Enable debug mode to log additional information. This is useful for troubleshooting issues.', 'ryvr-ai' ),
                    'type'    => 'select',
                    'options' => [
                        'off' => __( 'Off', 'ryvr-ai' ),
                        'on'  => __( 'On', 'ryvr-ai' ),
                    ],
                    'default' => 'off',
                ],
                [
                    'id'      => 'ryvr_log_api_calls',
                    'title'   => __( 'Log API Calls', 'ryvr-ai' ),
                    'desc'    => __( 'Log all API calls for debugging purposes.', 'ryvr-ai' ),
                    'type'    => 'select',
                    'options' => [
                        'off' => __( 'Off', 'ryvr-ai' ),
                        'on'  => __( 'On', 'ryvr-ai' ),
                    ],
                    'default' => 'off',
                ],
            ],
            'api' => [
                [
                    'id'       => 'ryvr_openai_api_key',
                    'title'    => __( 'OpenAI API Key', 'ryvr-ai' ),
                    'desc'     => __( 'Enter your OpenAI API key. <a href="https://platform.openai.com/account/api-keys" target="_blank">Get your API key</a>.', 'ryvr-ai' ),
                    'type'     => 'password',
                    'default'  => '',
                    'sanitize' => 'sanitize_text_field',
                ],
                [
                    'id'       => 'ryvr_dataforseo_api_login',
                    'title'    => __( 'DataForSEO API Login', 'ryvr-ai' ),
                    'desc'     => __( 'Enter your DataForSEO API login. <a href="https://app.dataforseo.com/login" target="_blank">Get your API credentials</a>.', 'ryvr-ai' ),
                    'type'     => 'text',
                    'default'  => '',
                    'sanitize' => 'sanitize_text_field',
                ],
                [
                    'id'       => 'ryvr_dataforseo_api_password',
                    'title'    => __( 'DataForSEO API Password', 'ryvr-ai' ),
                    'desc'     => __( 'Enter your DataForSEO API password.', 'ryvr-ai' ),
                    'type'     => 'password',
                    'default'  => '',
                    'sanitize' => 'sanitize_text_field',
                ],
                [
                    'id'      => 'ryvr_dataforseo_sandbox_mode',
                    'title'   => __( 'DataForSEO Sandbox Mode', 'ryvr-ai' ),
                    'desc'    => __( 'Enable sandbox mode for DataForSEO API. Use this for testing without incurring costs.', 'ryvr-ai' ),
                    'type'    => 'select',
                    'options' => [
                        'off' => __( 'Off (Live)', 'ryvr-ai' ),
                        'on'  => __( 'On (Sandbox)', 'ryvr-ai' ),
                    ],
                    'default' => 'on',
                ],
            ],
            'tasks' => [
                [
                    'id'      => 'ryvr_task_default_credits',
                    'title'   => __( 'Default Credits for New Users', 'ryvr-ai' ),
                    'desc'    => __( 'Number of credits to give to new users when they register.', 'ryvr-ai' ),
                    'type'    => 'number',
                    'default' => '50',
                    'min'     => '0',
                    'max'     => '1000',
                    'step'    => '1',
                ],
                [
                    'id'      => 'ryvr_task_process_limit',
                    'title'   => __( 'Task Processing Limit', 'ryvr-ai' ),
                    'desc'    => __( 'Maximum number of tasks to process per scheduled run.', 'ryvr-ai' ),
                    'type'    => 'number',
                    'default' => '10',
                    'min'     => '1',
                    'max'     => '50',
                    'step'    => '1',
                ],
            ],
            'advanced' => [
                [
                    'id'      => 'ryvr_openai_model',
                    'title'   => __( 'Default OpenAI Model', 'ryvr-ai' ),
                    'desc'    => __( 'Select the default OpenAI model to use for content generation.', 'ryvr-ai' ),
                    'type'    => 'select',
                    'options' => [
                        'gpt-3.5-turbo'       => __( 'GPT-3.5 Turbo', 'ryvr-ai' ),
                        'gpt-4'               => __( 'GPT-4', 'ryvr-ai' ),
                        'gpt-4-turbo-preview' => __( 'GPT-4 Turbo (Preview)', 'ryvr-ai' ),
                    ],
                    'default' => 'gpt-3.5-turbo',
                ],
                [
                    'id'       => 'ryvr_api_timeout',
                    'title'    => __( 'API Timeout', 'ryvr-ai' ),
                    'desc'     => __( 'Timeout in seconds for API requests.', 'ryvr-ai' ),
                    'type'     => 'number',
                    'default'  => '60',
                    'min'      => '10',
                    'max'      => '300',
                    'step'     => '5',
                ],
            ],
        ];
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    private function register_hooks() {
        // Register settings.
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        
        // Register AJAX actions.
        add_action( 'wp_ajax_ryvr_test_api_connection', [ $this, 'ajax_test_api_connection' ] );
    }

    /**
     * Register settings.
     *
     * @return void
     */
    public function register_settings() {
        // Register setting sections.
        foreach ( $this->sections as $section ) {
            add_settings_section(
                'ryvr_' . $section['id'] . '_section',
                $section['title'],
                [ $this, 'render_section' ],
                'ryvr-ai-settings'
            );
        }
        
        // Register setting fields.
        foreach ( $this->fields as $section_id => $fields ) {
            foreach ( $fields as $field ) {
                // Register the setting.
                register_setting(
                    'ryvr-ai-settings',
                    $field['id'],
                    [
                        'sanitize_callback' => isset( $field['sanitize'] ) ? $field['sanitize'] : null,
                        'default'           => isset( $field['default'] ) ? $field['default'] : '',
                    ]
                );
                
                // Add the field.
                add_settings_field(
                    $field['id'],
                    $field['title'],
                    [ $this, 'render_field' ],
                    'ryvr-ai-settings',
                    'ryvr_' . $section_id . '_section',
                    [
                        'id'       => $field['id'],
                        'desc'     => isset( $field['desc'] ) ? $field['desc'] : '',
                        'type'     => isset( $field['type'] ) ? $field['type'] : 'text',
                        'options'  => isset( $field['options'] ) ? $field['options'] : [],
                        'default'  => isset( $field['default'] ) ? $field['default'] : '',
                        'min'      => isset( $field['min'] ) ? $field['min'] : '',
                        'max'      => isset( $field['max'] ) ? $field['max'] : '',
                        'step'     => isset( $field['step'] ) ? $field['step'] : '',
                    ]
                );
            }
        }
    }

    /**
     * Render settings section.
     *
     * @param array $args Section arguments.
     * @return void
     */
    public function render_section( $args ) {
        $section_id = str_replace( 'ryvr_', '', str_replace( '_section', '', $args['id'] ) );
        
        if ( isset( $this->sections[ $section_id ]['desc'] ) ) {
            echo '<p>' . wp_kses_post( $this->sections[ $section_id ]['desc'] ) . '</p>';
        }
    }

    /**
     * Render settings field.
     *
     * @param array $args Field arguments.
     * @return void
     */
    public function render_field( $args ) {
        $id      = isset( $args['id'] ) ? $args['id'] : '';
        $desc    = isset( $args['desc'] ) ? $args['desc'] : '';
        $type    = isset( $args['type'] ) ? $args['type'] : 'text';
        $options = isset( $args['options'] ) ? $args['options'] : [];
        $default = isset( $args['default'] ) ? $args['default'] : '';
        $min     = isset( $args['min'] ) ? $args['min'] : '';
        $max     = isset( $args['max'] ) ? $args['max'] : '';
        $step    = isset( $args['step'] ) ? $args['step'] : '';
        
        $value = get_option( $id, $default );
        
        switch ( $type ) {
            case 'text':
                echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
                break;
                
            case 'password':
                echo '<input type="password" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
                break;
                
            case 'number':
                echo '<input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '" class="regular-text" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="' . esc_attr( $step ) . '" />';
                break;
                
            case 'textarea':
                echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" class="large-text" rows="5">' . esc_textarea( $value ) . '</textarea>';
                break;
                
            case 'select':
                echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '">';
                foreach ( $options as $option_value => $option_label ) {
                    echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( $option_label ) . '</option>';
                }
                echo '</select>';
                break;
                
            case 'checkbox':
                echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" value="1" ' . checked( 1, $value, false ) . ' />';
                break;
        }
        
        if ( ! empty( $desc ) ) {
            echo '<p class="description">' . wp_kses_post( $desc ) . '</p>';
        }
        
        // Add test connection button for API settings.
        if ( strpos( $id, 'ryvr_openai_api_key' ) !== false ) {
            echo '<p><button type="button" class="button button-secondary" id="ryvr-test-openai-api">' . esc_html__( 'Test Connection', 'ryvr-ai' ) . '</button> <span id="ryvr-test-openai-api-result"></span></p>';
        } elseif ( strpos( $id, 'ryvr_dataforseo_api_password' ) !== false ) {
            echo '<p><button type="button" class="button button-secondary" id="ryvr-test-dataforseo-api">' . esc_html__( 'Test Connection', 'ryvr-ai' ) . '</button> <span id="ryvr-test-dataforseo-api-result"></span></p>';
        }
    }

    /**
     * Get settings sections.
     *
     * @return array Settings sections.
     */
    public function get_sections() {
        return $this->sections;
    }

    /**
     * Get settings fields.
     *
     * @return array Settings fields.
     */
    public function get_fields() {
        return $this->fields;
    }

    /**
     * AJAX handler for testing API connection.
     *
     * @return void
     */
    public function ajax_test_api_connection() {
        // Check nonce.
        check_ajax_referer( 'ryvr_nonce', 'nonce' );
        
        // Check user capabilities.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ryvr-ai' ) ] );
        }
        
        // Get service.
        $service = isset( $_POST['service'] ) ? sanitize_text_field( $_POST['service'] ) : '';
        
        if ( empty( $service ) || ! in_array( $service, [ 'openai', 'dataforseo' ], true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid service.', 'ryvr-ai' ) ] );
        }
        
        // Get API credentials.
        $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';
        $api_secret = isset( $_POST['api_secret'] ) ? sanitize_text_field( $_POST['api_secret'] ) : '';
        
        // Get API Manager.
        $api_manager = ryvr()->get_component( 'api_manager' );
        
        if ( ! $api_manager ) {
            wp_send_json_error( [ 'message' => __( 'API Manager not available.', 'ryvr-ai' ) ] );
        }
        
        // Get API service.
        $api_service = $api_manager->get_service( $service );
        
        if ( ! $api_service ) {
            wp_send_json_error( [ 'message' => __( 'API service not available.', 'ryvr-ai' ) ] );
        }
        
        // Test the connection.
        $result = $api_service->test_connection( $api_key, $api_secret );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }
        
        // Return success.
        wp_send_json_success( [ 'message' => __( 'Connection successful.', 'ryvr-ai' ) ] );
    }
} 