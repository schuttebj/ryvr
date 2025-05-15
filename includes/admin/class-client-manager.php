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
                    'key' => 'field_ryvr_dataforseo_username',
                    'label' => 'DataForSEO Username',
                    'name' => 'ryvr_dataforseo_username',
                    'type' => 'text',
                    'instructions' => 'Enter the client-specific DataForSEO username.',
                    'required' => 0,
                    'wrapper' => [
                        'width' => '50',
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
                ],
                [
                    'key' => 'field_ryvr_openai_api_key',
                    'label' => 'OpenAI API Key',
                    'name' => 'ryvr_openai_api_key',
                    'type' => 'password',
                    'instructions' => 'Enter the client-specific OpenAI API key.',
                    'required' => 0,
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
     * Get client-specific API keys.
     *
     * @param int $client_id Client ID.
     * @return array API keys.
     */
    public function get_client_api_keys($client_id) {
        $keys = [
            'dataforseo_username' => get_post_meta($client_id, 'ryvr_dataforseo_username', true),
            'dataforseo_password' => get_post_meta($client_id, 'ryvr_dataforseo_password', true),
            'openai_api_key' => get_post_meta($client_id, 'ryvr_openai_api_key', true),
        ];
        
        return $keys;
    }

    /**
     * Check if client has API keys set.
     *
     * @param int    $client_id Client ID.
     * @param string $service Service name (dataforseo, openai).
     * @return bool Whether the client has API keys set for the service.
     */
    public function client_has_api_keys($client_id, $service) {
        if ($service === 'dataforseo') {
            $username = get_post_meta($client_id, 'ryvr_dataforseo_username', true);
            $password = get_post_meta($client_id, 'ryvr_dataforseo_password', true);
            
            return !empty($username) && !empty($password);
        } elseif ($service === 'openai') {
            $api_key = get_post_meta($client_id, 'ryvr_openai_api_key', true);
            
            return !empty($api_key);
        }
        
        return false;
    }
} 