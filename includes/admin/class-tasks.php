<?php

class Tasks {
    public function get_columns() {
        $columns = [
            'title'       => __( 'Task', 'ryvr-ai' ),
            'description' => __( 'Description', 'ryvr-ai' ),
            'task_type'   => __( 'Type', 'ryvr-ai' ),
            'client'      => __( 'Client', 'ryvr-ai' ),
            'status'      => __( 'Status', 'ryvr-ai' ),
            'created'     => __( 'Created', 'ryvr-ai' ),
            'updated'     => __( 'Updated', 'ryvr-ai' ),
            'actions'     => __( 'Actions', 'ryvr-ai' ),
        ];

        return $columns;
    }

    /**
     * Render client column.
     *
     * @param array $task Task data.
     * @return string HTML.
     */
    public function column_client( $task ) {
        // Check if task has client ID in inputs
        $client_id = isset($task['inputs']) && isset($task['inputs']['client_id']) ? intval($task['inputs']['client_id']) : 0;
        
        if ($client_id > 0) {
            $client = get_post($client_id);
            if ($client) {
                return esc_html($client->post_title);
            }
        }
        
        return '<span class="ryvr-label">—</span>';
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook_suffix Current admin page.
     * @return void
     */
    public function enqueue_scripts( $hook_suffix ) {
        // Only load on our task pages
        if ( ! $this->is_task_page( $hook_suffix ) ) {
            return;
        }
        
        // Enqueue select2 if available (for dependency selector)
        if ( ! wp_script_is( 'select2', 'registered' ) ) {
            wp_register_script( 'select2', RYVR_URL . 'admin/js/select2.min.js', array( 'jquery' ), '4.0.13', true );
            wp_register_style( 'select2', RYVR_URL . 'admin/css/select2.min.css', array(), '4.0.13' );
        }
        
        if ( ! wp_style_is( 'select2', 'registered' ) ) {
            wp_register_style( 'select2', RYVR_URL . 'admin/css/select2.min.css', array(), '4.0.13' );
        }
        
        wp_enqueue_script( 'select2' );
        wp_enqueue_style( 'select2' );
        
        // Main task scripts
        wp_enqueue_script( 'ryvr-task-scripts', RYVR_URL . 'admin/js/tasks.js', array( 'jquery' ), RYVR_VERSION, true );
        
        // Task dependencies scripts
        wp_enqueue_script( 'ryvr-task-dependencies', RYVR_URL . 'admin/js/task-dependencies.js', array( 'jquery', 'select2' ), RYVR_VERSION, true );
        
        // Localize scripts
        wp_localize_script( 'ryvr-task-scripts', 'rvyrTasks', array(
            'nonce'    => wp_create_nonce( 'ryvr_task_nonce' ),
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'messages' => array(
                'confirmCancel'   => __( 'Are you sure you want to cancel this task?', 'ryvr-ai' ),
                'loading'         => __( 'Loading...', 'ryvr-ai' ),
                'taskCreated'     => __( 'Task created successfully.', 'ryvr-ai' ),
                'taskCancelled'   => __( 'Task cancelled successfully.', 'ryvr-ai' ),
                'errorOccurred'   => __( 'An error occurred.', 'ryvr-ai' ),
            ),
        ) );
        
        wp_localize_script( 'ryvr-task-dependencies', 'rvyrTaskDeps', array(
            'nonce'    => wp_create_nonce( 'ryvr_task_nonce' ),
            'messages' => array(
                'selectTask'             => __( 'Please select a task.', 'ryvr-ai' ),
                'selectTaskPlaceholder'  => __( 'Select a task...', 'ryvr-ai' ),
                'confirmRemove'          => __( 'Are you sure you want to remove this dependency?', 'ryvr-ai' ),
                'noDependencies'         => __( 'No dependencies.', 'ryvr-ai' ),
                'remove'                 => __( 'Remove', 'ryvr-ai' ),
                'ajaxError'              => __( 'An error occurred while processing your request.', 'ryvr-ai' ),
            ),
        ) );
    }
} 