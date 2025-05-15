    /**
     * Register the admin menu page.
     */
    public function register_admin_menu() {
        add_submenu_page(
            'ryvr-ai',  // Parent menu slug
            __('Benchmark Manager', 'ryvr-ai'),
            __('Benchmark Manager', 'ryvr-ai'),
            'manage_options',
            'ryvr-ai-benchmark-manager',
            array($this, 'render_page')
        );
    } 