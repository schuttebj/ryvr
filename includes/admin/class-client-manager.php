<?php
/**
 * The Client Manager class.
 *
 * Handles client management.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Admin
 */

namespace Ryvr\Admin;

/**
 * The Client Manager class.
 *
 * This class handles client post type and client-specific API key management.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Admin
 */
class Client_Manager {

    /**
     * Initialize the client manager.
     *
     * @return void
     */
    public function init() {
        // Register post type.
        add_action('init', [$this, 'register_post_type']);
        
        // Register ACF fields if ACF is active.
        add_action('acf/init', [$this, 'register_acf_fields']);
        
        // Add admin menu item.
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    /**
     * Register client post type.
     *
     * @return void
     */
    public function register_post_type() {
        $labels = [
            'name'                  => _x('Clients', 'Post type general name', 'ryvr-ai'),
            'singular_name'         => _x('Client', 'Post type singular name', 'ryvr-ai'),
            'menu_name'             => _x('Clients', 'Admin Menu text', 'ryvr-ai'),
            'name_admin_bar'        => _x('Client', 'Add New on Toolbar', 'ryvr-ai'),
            'add_new'               => __('Add New', 'ryvr-ai'),
            'add_new_item'          => __('Add New Client', 'ryvr-ai'),
            'new_item'              => __('New Client', 'ryvr-ai'),
            'edit_item'             => __('Edit Client', 'ryvr-ai'),
            'view_item'             => __('View Client', 'ryvr-ai'),
            'all_items'             => __('All Clients', 'ryvr-ai'),
            'search_items'          => __('Search Clients', 'ryvr-ai'),
            'parent_item_colon'     => __('Parent Clients:', 'ryvr-ai'),
            'not_found'             => __('No clients found.', 'ryvr-ai'),
            'not_found_in_trash'    => __('No clients found in Trash.', 'ryvr-ai'),
            'featured_image'        => _x('Client Logo', 'Featured Image', 'ryvr-ai'),
            'set_featured_image'    => _x('Set client logo', 'Featured Image', 'ryvr-ai'),
            'remove_featured_image' => _x('Remove client logo', 'Featured Image', 'ryvr-ai'),
            'use_featured_image'    => _x('Use as client logo', 'Featured Image', 'ryvr-ai'),
            'archives'              => _x('Client archives', 'Archives', 'ryvr-ai'),
            'insert_into_item'      => _x('Insert into client', 'Post Type', 'ryvr-ai'),
            'uploaded_to_this_item' => _x('Uploaded to this client', 'Post Type', 'ryvr-ai'),
            'filter_items_list'     => _x('Filter clients list', 'Post Type', 'ryvr-ai'),
            'items_list_navigation' => _x('Clients list navigation', 'Post Type', 'ryvr-ai'),
            'items_list'            => _x('Clients list', 'Post Type', 'ryvr-ai'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false, // We'll add it under our custom menu
            'query_var'          => true,
            'rewrite'            => ['slug' => 'client'],
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => ['title', 'thumbnail', 'editor', 'excerpt'],
            'menu_icon'          => 'dashicons-businessperson',
        ];

        register_post_type('ryvr_client', $args);
    }

    /**
     * Register ACF fields for client post type.
     *
     * @return void
     */
    public function register_acf_fields() {
        // Check if ACF is active.
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        // API keys field group
        acf_add_local_field_group([
            'key' => 'group_ryvr_client_api_keys',
            'title' => 'API Keys',
            'fields' => [
                [
                    'key' => 'field_ryvr_use_default_dataforseo',
                    'label' => 'Use Platform DataForSEO Credentials',
                    'name' => 'ryvr_use_default_dataforseo',
                    'type' => 'true_false',
                    'instructions' => 'Toggle to use platform default DataForSEO credentials (recommended) or client-specific credentials.',
                    'default_value' => 1,
                    'ui' => 1,
                    'ui_on_text' => 'Use Platform Default',
                    'ui_off_text' => 'Use Client-Specific',
                ],
                [
                    'key' => 'field_ryvr_dataforseo_username',
                    'label' => 'DataForSEO Username',
                    'name' => 'ryvr_dataforseo_username',
                    'type' => 'text',
                    'instructions' => 'Enter the client-specific DataForSEO username.',
                    'required' => 0,
                    'wrapper' => [
                        'width' => '50',
                    ],
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_ryvr_use_default_dataforseo',
                                'operator' => '!=',
                                'value' => '1',
                            ],
                        ],
                    ],
                ],
                [
                    'key' => 'field_ryvr_dataforseo_password',
                    'label' => 'DataForSEO Password',
                    'name' => 'ryvr_dataforseo_password',
                    'type' => 'password',
                    'instructions' => 'Enter the client-specific DataForSEO password.',
                    'required' => 0,
                    'wrapper' => [
                        'width' => '50',
                    ],
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_ryvr_use_default_dataforseo',
                                'operator' => '!=',
                                'value' => '1',
                            ],
                        ],
                    ],
                ],
                [
                    'key' => 'field_ryvr_use_default_openai',
                    'label' => 'Use Platform OpenAI API Key',
                    'name' => 'ryvr_use_default_openai',
                    'type' => 'true_false',
                    'instructions' => 'Toggle to use platform default OpenAI API key (recommended) or client-specific key.',
                    'default_value' => 1,
                    'ui' => 1,
                    'ui_on_text' => 'Use Platform Default',
                    'ui_off_text' => 'Use Client-Specific',
                ],
                [
                    'key' => 'field_ryvr_openai_api_key',
                    'label' => 'OpenAI API Key',
                    'name' => 'ryvr_openai_api_key',
                    'type' => 'password',
                    'instructions' => 'Enter the client-specific OpenAI API key.',
                    'required' => 0,
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_ryvr_use_default_openai',
                                'operator' => '!=',
                                'value' => '1',
                            ],
                        ],
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'ryvr_client',
                    ],
                ],
            ],
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'active' => true,
        ]);

        // Client details field group
        acf_add_local_field_group([
            'key' => 'group_ryvr_client_details',
            'title' => 'Client Details',
            'fields' => [
                [
                    'key' => 'field_ryvr_client_email',
                    'label' => 'Email',
                    'name' => 'ryvr_client_email',
                    'type' => 'email',
                    'instructions' => 'Primary contact email for the client.',
                    'required' => 1,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_ryvr_client_phone',
                    'label' => 'Phone',
                    'name' => 'ryvr_client_phone',
                    'type' => 'text',
                    'instructions' => 'Primary contact phone for the client.',
                    'required' => 0,
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_ryvr_client_website',
                    'label' => 'Website',
                    'name' => 'ryvr_client_website',
                    'type' => 'url',
                    'instructions' => 'Client website URL.',
                    'required' => 0,
                ],
                [
                    'key' => 'field_ryvr_client_address',
                    'label' => 'Address',
                    'name' => 'ryvr_client_address',
                    'type' => 'textarea',
                    'instructions' => 'Client physical address.',
                    'required' => 0,
                    'rows' => 3,
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'ryvr_client',
                    ],
                ],
            ],
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'active' => true,
        ]);
    }

    /**
     * Add client management to admin menu.
     *
     * @return void
     */
    public function add_admin_menu() {
        add_submenu_page(
            'ryvr-ai',
            __('Clients', 'ryvr-ai'),
            __('Clients', 'ryvr-ai'),
            'manage_options',
            'edit.php?post_type=ryvr_client'
        );
    }

    /**
     * Get all clients.
     *
     * @return array Array of client posts.
     */
    public function get_clients() {
        $args = [
            'post_type' => 'ryvr_client',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ];

        return get_posts($args);
    }

    /**
     * Get a client dropdown for forms.
     *
     * @param string $name Form field name.
     * @param int    $selected Selected client ID.
     * @param bool   $include_empty Whether to include an empty option.
     * @return string HTML select element.
     */
    public function get_client_dropdown($name, $selected = 0, $include_empty = true) {
        $clients = $this->get_clients();
        
        $html = '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" class="ryvr-client-select">';
        
        if ($include_empty) {
            $html .= '<option value="">' . esc_html__('Select a client', 'ryvr-ai') . '</option>';
        }
        
        foreach ($clients as $client) {
            $html .= '<option value="' . esc_attr($client->ID) . '" ' . selected($selected, $client->ID, false) . '>' . esc_html($client->post_title) . '</option>';
        }
        
        $html .= '</select>';
        
        return $html;
    }

    /**
     * Get client API keys.
     *
     * @param int $client_id Client ID.
     * @return array Client API keys.
     */
    public function get_client_api_keys($client_id) {
        if (!$client_id) {
            return [];
        }
        
        // OpenAI settings
        $use_default_openai = get_post_meta($client_id, 'ryvr_use_default_openai', true);
        if ($use_default_openai === '') {
            $use_default_openai = '1'; // Default to platform keys if not set
        }
        
        $openai_api_key = '';
        if ($use_default_openai === '1') {
            $openai_api_key = get_option('ryvr_openai_api_key', '');
        } else {
            $openai_api_key = get_post_meta($client_id, 'ryvr_openai_api_key', true);
        }
        
        // DataForSEO settings
        $use_default_dataforseo = get_post_meta($client_id, 'ryvr_use_default_dataforseo', true);
        if ($use_default_dataforseo === '') {
            $use_default_dataforseo = '1'; // Default to platform keys if not set
        }
        
        $dataforseo_username = '';
        $dataforseo_password = '';
        if ($use_default_dataforseo === '1') {
            $dataforseo_username = get_option('ryvr_dataforseo_api_login', '');
            $dataforseo_password = get_option('ryvr_dataforseo_api_password', '');
        } else {
            $dataforseo_username = get_post_meta($client_id, 'ryvr_dataforseo_username', true);
            $dataforseo_password = get_post_meta($client_id, 'ryvr_dataforseo_password', true);
        }
        
        return [
            'openai' => [
                'use_default' => $use_default_openai === '1',
                'api_key' => $openai_api_key,
            ],
            'dataforseo' => [
                'use_default' => $use_default_dataforseo === '1',
                'username' => $dataforseo_username,
                'password' => $dataforseo_password,
            ],
        ];
    }

    /**
     * Check if a client has API keys for a specific service.
     *
     * @param int    $client_id Client ID.
     * @param string $service Service name.
     * @return bool Whether client has API keys for the service.
     */
    public function client_has_api_keys($client_id, $service) {
        if (!$client_id) {
            return false;
        }
        
        // Check if using platform defaults
        if ($service === 'openai') {
            $use_default = get_post_meta($client_id, 'ryvr_use_default_openai', true);
            
            // If using platform default or not explicitly set
            if ($use_default === '1' || $use_default === '') {
                // Check if platform has the API key configured
                return !empty(get_option('ryvr_openai_api_key', ''));
            }
            
            // Otherwise check for client-specific key
            return !empty(get_post_meta($client_id, 'ryvr_openai_api_key', true));
        }
        
        if ($service === 'dataforseo') {
            $use_default = get_post_meta($client_id, 'ryvr_use_default_dataforseo', true);
            
            // If using platform default or not explicitly set
            if ($use_default === '1' || $use_default === '') {
                // Check if platform has the API credentials configured
                return !empty(get_option('ryvr_dataforseo_api_login', '')) && 
                       !empty(get_option('ryvr_dataforseo_api_password', ''));
            }
            
            // Otherwise check for client-specific credentials
            return !empty(get_post_meta($client_id, 'ryvr_dataforseo_username', true)) && 
                   !empty(get_post_meta($client_id, 'ryvr_dataforseo_password', true));
        }
        
        return false;
    }

    /**
     * Register the admin menu page.
     */
    public function register_admin_menu() {
        add_submenu_page(
            'ryvr-ai',  // Parent menu slug
            __('Client Manager', 'ryvr-ai'),
            __('Client Manager', 'ryvr-ai'),
            'manage_options',
            'ryvr-ai-client-manager',
            array($this, 'render_page')
        );
    }
} 