<?php
/**
 * The Task Engine class.
 *
 * Manages tasks in the Ryvr platform.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Task_Engine
 */

namespace Ryvr\Task_Engine;

/**
 * The Task Engine class.
 *
 * This class manages task creation, processing, and status tracking.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Task_Engine
 */
class Task_Engine {

    /**
     * Task types registry.
     *
     * @var array
     */
    private $task_types = [];

    /**
     * Task processors registry.
     *
     * @var array
     */
    private $task_processors = [];

    /**
     * Initialize the task engine.
     *
     * @return void
     */
    public function init() {
        // Register built-in task types.
        $this->register_task_types();
        
        // Register task processors.
        $this->register_task_processors();
        
        // Register hooks.
        $this->register_hooks();
        
        // Register cron jobs.
        $this->register_cron_jobs();
    }

    /**
     * Register built-in task types.
     *
     * @return void
     */
    private function register_task_types() {
        // Include task type classes.
        require_once RYVR_INCLUDES_DIR . 'task-engine/class-task.php';
        require_once RYVR_INCLUDES_DIR . 'task-engine/class-task-processor.php';
        
        // Register known task types.
        $this->register_task_type(
            'keyword_research',
            __( 'Keyword Research', 'ryvr-ai' ),
            __( 'Discover and analyze keywords for SEO and content strategies.', 'ryvr-ai' ),
            [
                'credits_cost' => 5,
                'requires_approval' => false,
                'icon' => 'search',
                'category' => 'seo',
            ]
        );
        
        $this->register_task_type(
            'content_generation',
            __( 'Content Generation', 'ryvr-ai' ),
            __( 'Generate content using AI based on keywords and outlines.', 'ryvr-ai' ),
            [
                'credits_cost' => 10,
                'requires_approval' => true,
                'icon' => 'edit',
                'category' => 'content',
            ]
        );
        
        $this->register_task_type(
            'seo_audit',
            __( 'SEO Audit', 'ryvr-ai' ),
            __( 'Analyze website SEO health and get recommendations.', 'ryvr-ai' ),
            [
                'credits_cost' => 15,
                'requires_approval' => false,
                'icon' => 'chart-bar',
                'category' => 'seo',
            ]
        );
        
        $this->register_task_type(
            'backlink_analysis',
            __( 'Backlink Analysis', 'ryvr-ai' ),
            __( 'Analyze backlink profile and competitor links.', 'ryvr-ai' ),
            [
                'credits_cost' => 8,
                'requires_approval' => false,
                'icon' => 'external',
                'category' => 'seo',
            ]
        );
        
        $this->register_task_type(
            'ad_copy',
            __( 'Ad Copy', 'ryvr-ai' ),
            __( 'Generate ad copy for PPC campaigns.', 'ryvr-ai' ),
            [
                'credits_cost' => 5,
                'requires_approval' => true,
                'icon' => 'megaphone',
                'category' => 'ppc',
            ]
        );
        
        // Allow other plugins to register task types.
        do_action( 'ryvr_register_task_types', $this );
    }

    /**
     * Register task processors.
     *
     * @return void
     */
    private function register_task_processors() {
        // Include task processor classes.
        require_once RYVR_INCLUDES_DIR . 'task-engine/processors/class-keyword-research-processor.php';
        require_once RYVR_INCLUDES_DIR . 'task-engine/processors/class-content-generation-processor.php';
        require_once RYVR_INCLUDES_DIR . 'task-engine/processors/class-seo-audit-processor.php';
        
        // Register built-in task processors.
        $this->register_task_processor( 'keyword_research', new Processors\Keyword_Research_Processor() );
        $this->register_task_processor( 'content_generation', new Processors\Content_Generation_Processor() );
        $this->register_task_processor( 'seo_audit', new Processors\SEO_Audit_Processor() );
        
        // Allow other plugins to register task processors.
        do_action( 'ryvr_register_task_processors', $this );
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks() {
        // Register AJAX actions.
        add_action( 'wp_ajax_ryvr_create_task', [ $this, 'ajax_create_task' ] );
        add_action( 'wp_ajax_ryvr_cancel_task', [ $this, 'ajax_cancel_task' ] );
        add_action( 'wp_ajax_ryvr_approve_task', [ $this, 'ajax_approve_task' ] );
        add_action( 'wp_ajax_ryvr_get_task_status', [ $this, 'ajax_get_task_status' ] );
        add_action( 'wp_ajax_ryvr_add_task_dependency', [ $this, 'ajax_add_task_dependency' ] );
        add_action( 'wp_ajax_ryvr_remove_task_dependency', [ $this, 'ajax_remove_task_dependency' ] );
        add_action( 'wp_ajax_ryvr_update_task_priority', [ $this, 'ajax_update_task_priority' ] );
        
        // Process tasks in background.
        add_action( 'ryvr_process_tasks', [ $this, 'process_pending_tasks' ] );
        add_action( 'ryvr_process_task', [ $this, 'process_task' ] );
        add_action( 'ryvr_check_dependency_tasks', [ $this, 'check_dependency_tasks' ] );
    }

    /**
     * Register cron jobs.
     *
     * @return void
     */
    private function register_cron_jobs() {
        // Schedule task processing.
        if ( ! wp_next_scheduled( 'ryvr_process_tasks' ) ) {
            wp_schedule_event( time(), 'hourly', 'ryvr_process_tasks' );
        }
        
        // Schedule dependency check (every 5 minutes).
        if ( ! wp_next_scheduled( 'ryvr_check_dependency_tasks' ) ) {
            wp_schedule_event( time(), 'five_minutes', 'ryvr_check_dependency_tasks' );
        }
        
        // Register custom cron schedule.
        add_filter( 'cron_schedules', [ $this, 'add_custom_cron_schedules' ] );
    }
    
    /**
     * Add custom cron schedules.
     *
     * @param array $schedules Existing cron schedules.
     * @return array Modified cron schedules.
     */
    public function add_custom_cron_schedules( $schedules ) {
        // Add 5-minute schedule.
        $schedules['five_minutes'] = array(
            'interval' => 300, // 5 minutes in seconds
            'display'  => __( 'Every 5 minutes', 'ryvr-ai' ),
        );
        
        return $schedules;
    }

    /**
     * Register a new task type.
     *
     * @param string $type Task type identifier.
     * @param string $name Human-readable name.
     * @param string $description Task type description.
     * @param array  $args Additional arguments.
     * @return bool Whether the registration was successful.
     */
    public function register_task_type( $type, $name, $description, $args = [] ) {
        if ( isset( $this->task_types[ $type ] ) ) {
            return false;
        }
        
        $defaults = [
            'credits_cost' => 1,
            'requires_approval' => false,
            'icon' => 'admin-generic',
            'category' => 'general',
        ];
        
        $this->task_types[ $type ] = wp_parse_args( $args, $defaults );
        $this->task_types[ $type ]['name'] = $name;
        $this->task_types[ $type ]['description'] = $description;
        
        return true;
    }

    /**
     * Register a task processor.
     *
     * @param string $type Task type.
     * @param object $processor Task processor instance.
     * @return bool Whether the registration was successful.
     */
    public function register_task_processor( $type, $processor ) {
        if ( ! isset( $this->task_types[ $type ] ) ) {
            return false;
        }
        
        if ( ! ( $processor instanceof Task_Processor ) ) {
            return false;
        }
        
        $this->task_processors[ $type ] = $processor;
        
        return true;
    }

    /**
     * Create a new task.
     *
     * @param int    $user_id User ID.
     * @param string $task_type Task type.
     * @param string $title Task title.
     * @param array  $inputs Task inputs.
     * @param string $description Task description.
     * @param int    $priority Task priority.
     * @param array  $dependencies Array of task IDs this task depends on.
     * @return int|WP_Error Task ID or error.
     */
    public function create_task( $user_id, $task_type, $title, $inputs = [], $description = '', $priority = 50, $dependencies = [] ) {
        // Log attempt for debugging
        error_log(sprintf('Creating task: type=%s, title=%s, user=%d', $task_type, $title, $user_id));
        
        // Validate task type.
        if ( ! isset( $this->task_types[ $task_type ] ) ) {
            error_log(sprintf('Invalid task type: %s', $task_type));
            return new \WP_Error( 'invalid_task_type', __( 'Invalid task type.', 'ryvr-ai' ) );
        }
        
        // Validate title.
        if ( empty( $title ) ) {
            error_log('Empty task title');
            return new \WP_Error( 'empty_title', __( 'Task title is required.', 'ryvr-ai' ) );
        }
        
        // Check for credits.
        $credits_cost = $this->task_types[ $task_type ]['credits_cost'];
        $user_credits = ryvr_get_user_credits( $user_id );
        
        if ( $user_credits < $credits_cost ) {
            error_log(sprintf('Insufficient credits: user=%d, needed=%d, available=%d', $user_id, $credits_cost, $user_credits));
            return new \WP_Error( 'insufficient_credits', __( 'You do not have enough credits to create this task.', 'ryvr-ai' ) );
        }
        
        // Get task type details.
        $task_info = $this->task_types[$task_type];
        
        // Validate priority
        $priority = max(0, min(100, (int) $priority)); // Ensure priority is between 0 and 100
        
        // Validate dependencies
        $validated_dependencies = [];
        if (!empty($dependencies)) {
            foreach ($dependencies as $dep_id) {
                // Check if dependency task exists
                $dep_task = $this->get_task($dep_id);
                if (!$dep_task) {
                    return new \WP_Error(
                        'invalid_dependency',
                        sprintf(__('Dependency task with ID %d does not exist.', 'ryvr-ai'), $dep_id)
                    );
                }
                
                // Add to validated dependencies
                $validated_dependencies[] = (int) $dep_id;
            }
        }
        
        // Serialize task inputs and dependencies
        $serialized_inputs = maybe_serialize( $inputs );
        $serialized_dependencies = maybe_serialize( $validated_dependencies );
        
        // Determine initial status based on dependencies
        $initial_status = empty($validated_dependencies) ? 'pending' : 'waiting_dependency';
        
        // Set task data.
        $data = [
            'user_id'      => $user_id,
            'task_type'    => $task_type,
            'status'       => $initial_status,
            'title'        => $title,
            'description'  => $description,
            'inputs'       => $serialized_inputs,
            'credits_cost' => $credits_cost,
            'priority'     => $priority,
            'dependencies' => $serialized_dependencies,
            'created_at'   => current_time( 'mysql', true ),
            'updated_at'   => current_time( 'mysql', true ),
        ];
        
        // If task requires approval, set status to 'approval_required'.
        if ($task_info['requires_approval'] && $initial_status === 'pending') {
            $data['status'] = 'approval_required';
        }
        
        // Get tasks table name.
        $table_name = $wpdb->prefix . 'ryvr_tasks';
        
        // Insert task.
        $result = $wpdb->insert( $table_name, $data );
        
        if ( $result === false ) {
            return new \WP_Error( 'db_error', __( 'Failed to create task.', 'ryvr-ai' ) );
        }
        
        $task_id = $wpdb->insert_id;
        
        // Deduct credits.
        $this->deduct_user_credits( $user_id, $credits_cost, $task_id );
        
        // Log task creation.
        $this->log_task(
            $task_id,
            sprintf(
                __( 'Task created: %s', 'ryvr-ai' ),
                $title
            )
        );
        
        if (!empty($validated_dependencies)) {
            $this->log_task(
                $task_id,
                sprintf(
                    __( 'Task has %d dependencies. It will start after all dependencies are completed.', 'ryvr-ai' ),
                    count($validated_dependencies)
                )
            );
        }
        
        // Fire action for task creation.
        do_action( 'ryvr_task_created', $task_id, $task_type, $user_id );
        
        return $task_id;
    }

    /**
     * Log a message for a task.
     *
     * @param int    $task_id Task ID.
     * @param string $message Log message.
     * @param string $log_level Log level (info, warning, error).
     * @return bool Whether the log was created.
     */
    public function log_task( $task_id, $message, $log_level = 'info' ) {
        global $wpdb;
        
        // Insert log entry.
        $table_name = $wpdb->prefix . 'ryvr_task_logs';
        
        $result = $wpdb->insert(
            $table_name,
            [
                'task_id'    => $task_id,
                'message'    => $message,
                'log_level'  => $log_level,
                'created_at' => current_time( 'mysql', true ),
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
            ]
        );
        
        return $result !== false;
    }

    /**
     * Get a task by ID.
     *
     * @param int $task_id Task ID.
     * @return object|null Task object or null if not found.
     */
    public function get_task( $task_id ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ryvr_tasks';
        
        $task = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $task_id
            )
        );
        
        if ( ! $task ) {
            return null;
        }
        
        // Parse inputs and outputs.
        $task->inputs = ryvr_parse_json( $task->inputs );
        $task->outputs = ryvr_parse_json( $task->outputs );
        
        return $task;
    }

    /**
     * Update task status.
     *
     * @param int    $task_id Task ID.
     * @param string $status New status.
     * @return bool Whether the update was successful.
     */
    public function update_task_status( $task_id, $status ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ryvr_tasks';
        
        $data = [
            'status'     => $status,
            'updated_at' => current_time( 'mysql', true ),
        ];
        
        // Add timestamps for specific statuses.
        if ( 'processing' === $status ) {
            $data['started_at'] = current_time( 'mysql', true );
        } elseif ( in_array( $status, [ 'completed', 'failed', 'canceled' ], true ) ) {
            $data['completed_at'] = current_time( 'mysql', true );
        }
        
        $result = $wpdb->update(
            $table_name,
            $data,
            [ 'id' => $task_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
        
        if ( $result ) {
            $this->log_task( $task_id, sprintf( __( 'Task status updated to: %s', 'ryvr-ai' ), ryvr_get_task_status_label( $status ) ) );
        }
        
        return $result !== false;
    }

    /**
     * Update task outputs.
     *
     * @param int   $task_id Task ID.
     * @param array $outputs Task outputs.
     * @return bool Whether the update was successful.
     */
    public function update_task_outputs( $task_id, $outputs ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ryvr_tasks';
        
        $result = $wpdb->update(
            $table_name,
            [
                'outputs'    => wp_json_encode( $outputs ),
                'updated_at' => current_time( 'mysql', true ),
            ],
            [ 'id' => $task_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
        
        if ( $result ) {
            $this->log_task( $task_id, __( 'Task outputs updated.', 'ryvr-ai' ) );
        }
        
        return $result !== false;
    }

    /**
     * Cancel a task.
     *
     * @param int $task_id Task ID.
     * @return bool Whether the cancellation was successful.
     */
    public function cancel_task( $task_id ) {
        $task = $this->get_task( $task_id );
        
        if ( ! $task ) {
            return false;
        }
        
        // Can only cancel tasks that are pending or require approval.
        if ( ! in_array( $task->status, [ 'pending', 'approval_required' ], true ) ) {
            return false;
        }
        
        return $this->update_task_status( $task_id, 'canceled' );
    }

    /**
     * Approve a task.
     *
     * @param int $task_id Task ID.
     * @return bool Whether the approval was successful.
     */
    public function approve_task( $task_id ) {
        $task = $this->get_task( $task_id );
        
        if ( ! $task ) {
            return false;
        }
        
        // Can only approve tasks that require approval.
        if ( 'approval_required' !== $task->status ) {
            return false;
        }
        
        // Update status to pending.
        $result = $this->update_task_status( $task_id, 'pending' );
        
        if ( $result ) {
            // Schedule the task for processing.
            wp_schedule_single_event( time() + 60, 'ryvr_process_task', [ $task_id ] );
            $this->log_task( $task_id, __( 'Task approved and scheduled for processing.', 'ryvr-ai' ) );
        }
        
        return $result;
    }

    /**
     * Process a specific task.
     *
     * @param int $task_id Task ID.
     * @return bool Whether processing was successful.
     */
    public function process_task( $task_id ) {
        $task = $this->get_task( $task_id );
        
        if ( ! $task ) {
            return false;
        }
        
        // Only process pending tasks.
        if ( 'pending' !== $task->status ) {
            return false;
        }
        
        // Get task processor.
        if ( ! isset( $this->task_processors[ $task->task_type ] ) ) {
            $this->log_task( $task_id, __( 'No processor found for this task type.', 'ryvr-ai' ), 'error' );
            $this->update_task_status( $task_id, 'failed' );
            return false;
        }
        
        $processor = $this->task_processors[ $task->task_type ];
        
        // Update status to processing.
        $this->update_task_status( $task_id, 'processing' );
        
        try {
            // Process the task.
            $result = $processor->process( $task );
            
            if ( is_wp_error( $result ) ) {
                // Log the error.
                $this->log_task( $task_id, sprintf( __( 'Error processing task: %s', 'ryvr-ai' ), $result->get_error_message() ), 'error' );
                $this->update_task_status( $task_id, 'failed' );
                return false;
            }
            
            // Update task outputs.
            $this->update_task_outputs( $task_id, $result );
            
            // Mark as completed.
            $this->update_task_status( $task_id, 'completed' );
            
            return true;
        } catch ( \Exception $e ) {
            // Log the exception.
            $this->log_task( $task_id, sprintf( __( 'Exception processing task: %s', 'ryvr-ai' ), $e->getMessage() ), 'error' );
            $this->update_task_status( $task_id, 'failed' );
            return false;
        }
    }

    /**
     * Process pending tasks.
     *
     * @param int $limit Maximum number of tasks to process.
     * @return int Number of tasks processed.
     */
    public function process_pending_tasks( $limit = 10 ) {
        global $wpdb;
        
        // Get tasks table name.
        $table_name = $wpdb->prefix . 'ryvr_tasks';
        
        // Get tasks that are pending and ready to process.
        $query = $wpdb->prepare(
            "SELECT id FROM $table_name 
            WHERE status = %s
            ORDER BY priority DESC, id ASC 
            LIMIT %d",
            'pending',
            $limit
        );
        
        $task_ids = $wpdb->get_col( $query );
        
        if ( empty( $task_ids ) ) {
            return 0;
        }
        
        $processed = 0;
        
        foreach ( $task_ids as $task_id ) {
            // Schedule task processing.
            wp_schedule_single_event( time(), 'ryvr_process_task', [ $task_id ] );
            
            // Update task status to 'processing'.
            $this->update_task_status( $task_id, 'processing' );
            
            $processed++;
        }
        
        // Check for tasks waiting on dependencies
        $this->check_dependency_tasks();
        
        return $processed;
    }
    
    /**
     * Check tasks that are waiting on dependencies.
     *
     * @return int Number of tasks updated.
     */
    public function check_dependency_tasks() {
        global $wpdb;
        
        // Get tasks table name.
        $table_name = $wpdb->prefix . 'ryvr_tasks';
        
        // Get tasks that are waiting on dependencies.
        $query = $wpdb->prepare(
            "SELECT id, dependencies FROM $table_name 
            WHERE status = %s",
            'waiting_dependency'
        );
        
        $tasks = $wpdb->get_results( $query );
        $updated = 0;
        
        if ( empty( $tasks ) ) {
            return 0;
        }
        
        foreach ( $tasks as $task ) {
            $dependencies = maybe_unserialize( $task->dependencies );
            
            // Skip if no dependencies
            if ( empty( $dependencies ) ) {
                // Update task status to 'pending'.
                $this->update_task_status( $task->id, 'pending' );
                $updated++;
                continue;
            }
            
            // Check if all dependencies are completed or failed or canceled
            $all_dependencies_finished = true;
            
            foreach ( $dependencies as $dep_id ) {
                $dep_task = $this->get_task( $dep_id );
                
                if ( ! $dep_task || ! $dep_task->is_finished() ) {
                    $all_dependencies_finished = false;
                    break;
                }
            }
            
            if ( $all_dependencies_finished ) {
                // Get task info to check if approval is required
                $task_object = $this->get_task( $task->id );
                
                if ( $task_object ) {
                    $task_type = $task_object->get_task_type();
                    $task_info = $this->get_task_type( $task_type );
                    
                    // Determine new status based on whether approval is required
                    $new_status = isset( $task_info['requires_approval'] ) && $task_info['requires_approval'] 
                        ? 'approval_required' 
                        : 'pending';
                    
                    // Update task status.
                    $this->update_task_status( $task->id, $new_status );
                    
                    // Log dependency completion
                    $this->log_task(
                        $task->id,
                        __( 'All dependencies completed. Task is ready for processing.', 'ryvr-ai' )
                    );
                    
                    $updated++;
                }
            }
        }
        
        return $updated;
    }

    /**
     * Add a dependency to a task.
     *
     * @param int $task_id Task ID.
     * @param int $dependency_id Dependency task ID.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function add_task_dependency( $task_id, $dependency_id ) {
        global $wpdb;
        
        // Get tasks table name.
        $table_name = $wpdb->prefix . 'ryvr_tasks';
        
        // Get task.
        $task = $this->get_task( $task_id );
        
        if ( ! $task ) {
            return new \WP_Error( 'invalid_task', __( 'Invalid task.', 'ryvr-ai' ) );
        }
        
        // Get dependency task.
        $dependency_task = $this->get_task( $dependency_id );
        
        if ( ! $dependency_task ) {
            return new \WP_Error( 'invalid_dependency', __( 'Invalid dependency task.', 'ryvr-ai' ) );
        }
        
        // Check for circular dependency.
        if ( $this->would_create_circular_dependency( $task_id, $dependency_id ) ) {
            return new \WP_Error( 'circular_dependency', __( 'Cannot add dependency: would create circular dependency.', 'ryvr-ai' ) );
        }
        
        // Get current dependencies.
        $dependencies = $task->get_dependencies();
        
        // Check if dependency already exists.
        if ( in_array( $dependency_id, $dependencies ) ) {
            return true; // Dependency already exists.
        }
        
        // Add new dependency.
        $dependencies[] = $dependency_id;
        
        // Update task dependencies in database.
        $updated = $wpdb->update(
            $table_name,
            [
                'dependencies' => maybe_serialize( $dependencies ),
                'updated_at'   => current_time( 'mysql', true ),
                'status'       => 'waiting_dependency', // Set status to waiting_dependency
            ],
            [
                'id' => $task_id,
            ]
        );
        
        if ( $updated === false ) {
            return new \WP_Error( 'db_error', __( 'Failed to update task dependencies.', 'ryvr-ai' ) );
        }
        
        // Log dependency addition.
        $this->log_task(
            $task_id,
            sprintf(
                __( 'Added dependency: Task ID %d', 'ryvr-ai' ),
                $dependency_id
            )
        );
        
        return true;
    }
    
    /**
     * Check if adding a dependency would create a circular dependency.
     *
     * @param int $task_id Task ID.
     * @param int $dependency_id Dependency task ID.
     * @param array $visited Already visited task IDs (for recursion).
     * @return bool True if circular dependency would be created, false otherwise.
     */
    private function would_create_circular_dependency( $task_id, $dependency_id, $visited = [] ) {
        // If the dependency is the same as the task, it's circular.
        if ( $task_id == $dependency_id ) {
            return true;
        }
        
        // Mark task as visited to prevent infinite recursion.
        $visited[] = $dependency_id;
        
        // Get dependency task.
        $dependency_task = $this->get_task( $dependency_id );
        
        if ( ! $dependency_task ) {
            return false; // Task doesn't exist, so no circular dependency.
        }
        
        // Check all dependencies of the dependency task.
        $dependencies = $dependency_task->get_dependencies();
        
        foreach ( $dependencies as $dep_id ) {
            // Skip if already visited.
            if ( in_array( $dep_id, $visited ) ) {
                continue;
            }
            
            // If the dependency depends on our task, it's circular.
            if ( $dep_id == $task_id ) {
                return true;
            }
            
            // Recursively check deeper dependencies.
            if ( $this->would_create_circular_dependency( $task_id, $dep_id, $visited ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Remove a dependency from a task.
     *
     * @param int $task_id Task ID.
     * @param int $dependency_id Dependency task ID.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function remove_task_dependency( $task_id, $dependency_id ) {
        global $wpdb;
        
        // Get tasks table name.
        $table_name = $wpdb->prefix . 'ryvr_tasks';
        
        // Get task.
        $task = $this->get_task( $task_id );
        
        if ( ! $task ) {
            return new \WP_Error( 'invalid_task', __( 'Invalid task.', 'ryvr-ai' ) );
        }
        
        // Get current dependencies.
        $dependencies = $task->get_dependencies();
        
        // Check if dependency exists.
        if ( ! in_array( $dependency_id, $dependencies ) ) {
            return true; // Dependency doesn't exist, nothing to remove.
        }
        
        // Remove dependency.
        $dependencies = array_diff( $dependencies, [ $dependency_id ] );
        
        // Determine if status should change
        $current_status = $task->get_status();
        $new_status = $current_status;
        
        if ( $current_status === 'waiting_dependency' && empty( $dependencies ) ) {
            // Get task type info to check if approval is required
            $task_type = $task->get_task_type();
            $task_info = $this->get_task_type( $task_type );
            
            $new_status = isset( $task_info['requires_approval'] ) && $task_info['requires_approval'] 
                ? 'approval_required' 
                : 'pending';
        }
        
        // Update task dependencies in database.
        $updated = $wpdb->update(
            $table_name,
            [
                'dependencies' => maybe_serialize( $dependencies ),
                'updated_at'   => current_time( 'mysql', true ),
                'status'       => $new_status,
            ],
            [
                'id' => $task_id,
            ]
        );
        
        if ( $updated === false ) {
            return new \WP_Error( 'db_error', __( 'Failed to update task dependencies.', 'ryvr-ai' ) );
        }
        
        // Log dependency removal.
        $this->log_task(
            $task_id,
            sprintf(
                __( 'Removed dependency: Task ID %d', 'ryvr-ai' ),
                $dependency_id
            )
        );
        
        return true;
    }
    
    /**
     * Update task priority.
     *
     * @param int $task_id Task ID.
     * @param int $priority New priority value (0-100).
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function update_task_priority( $task_id, $priority ) {
        global $wpdb;
        
        // Get tasks table name.
        $table_name = $wpdb->prefix . 'ryvr_tasks';
        
        // Get task.
        $task = $this->get_task( $task_id );
        
        if ( ! $task ) {
            return new \WP_Error( 'invalid_task', __( 'Invalid task.', 'ryvr-ai' ) );
        }
        
        // Validate priority
        $priority = max(0, min(100, (int) $priority)); // Ensure priority is between 0 and 100
        
        // Update task priority in database.
        $updated = $wpdb->update(
            $table_name,
            [
                'priority'   => $priority,
                'updated_at' => current_time( 'mysql', true ),
            ],
            [
                'id' => $task_id,
            ]
        );
        
        if ( $updated === false ) {
            return new \WP_Error( 'db_error', __( 'Failed to update task priority.', 'ryvr-ai' ) );
        }
        
        // Log priority update.
        $this->log_task(
            $task_id,
            sprintf(
                __( 'Updated priority: %d', 'ryvr-ai' ),
                $priority
            )
        );
        
        return true;
    }

    /**
     * Get task types.
     *
     * @return array Task types.
     */
    public function get_task_types() {
        return $this->task_types;
    }

    /**
     * Get a specific task type.
     *
     * @param string $type Task type.
     * @return array|null Task type details or null if not found.
     */
    public function get_task_type( $type ) {
        return isset( $this->task_types[ $type ] ) ? $this->task_types[ $type ] : null;
    }

    /**
     * AJAX handler for creating a task.
     *
     * @return void
     */
    public function ajax_create_task() {
        global $wpdb;
        
        // Enable error reporting 
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        
        try {
            // Log debug information
            error_log('AJAX create_task started');
            
            // Check POST data
            error_log('POST data: ' . print_r($_POST, true));
            
            // Check nonce.
            $nonce_verified = false;
            
            if (isset($_POST['ryvr_task_nonce'])) {
                error_log('Checking nonce: ryvr_task_nonce');
                $nonce_verified = wp_verify_nonce($_POST['ryvr_task_nonce'], 'ryvr_task_nonce');
            } else if (isset($_POST['nonce'])) {
                error_log('Checking nonce: nonce');
                $nonce_verified = wp_verify_nonce($_POST['nonce'], 'ryvr_task_nonce');
            }
            
            if (!$nonce_verified) {
                error_log('Nonce verification failed');
                wp_send_json_error([
                    'message' => __('Security check failed. Please refresh the page and try again.', 'ryvr-ai'),
                ]);
                return;
            }
            
            // Check capabilities.
            if (!current_user_can('edit_posts')) {
                error_log('Permission check failed: edit_posts');
                wp_send_json_error([
                    'message' => __('You do not have permission to create tasks.', 'ryvr-ai'),
                ]);
                return;
            }
            
            // Get parameters.
            $task_type = isset($_POST['task_type']) ? sanitize_text_field($_POST['task_type']) : '';
            error_log('Task type: ' . $task_type);
            
            if (empty($task_type)) {
                error_log('Task type is empty');
                wp_send_json_error([
                    'message' => __('Task type is required.', 'ryvr-ai'),
                ]);
                return;
            }
            
            $title = isset($_POST['task_title']) ? sanitize_text_field($_POST['task_title']) : '';
            error_log('Task title: ' . $title);
            
            if (empty($title)) {
                error_log('Task title is empty');
                wp_send_json_error([
                    'message' => __('Task title is required.', 'ryvr-ai'),
                ]);
                return;
            }
            
            $description = isset($_POST['task_description']) ? sanitize_textarea_field($_POST['task_description']) : '';
            $priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 50;
            $inputs = isset($_POST['inputs']) ? $_POST['inputs'] : [];
            $dependencies = isset($_POST['dependencies']) ? array_map('intval', (array)$_POST['dependencies']) : [];
            $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
            
            error_log('Inputs: ' . print_r($inputs, true));
            
            // Sanitize inputs.
            if (is_array($inputs)) {
                array_walk_recursive($inputs, function (&$value) {
                    if (is_string($value)) {
                        $value = sanitize_text_field($value);
                    }
                });
            } else {
                $inputs = [];
            }
            
            // Add client ID to inputs if provided
            if ($client_id > 0) {
                $inputs['client_id'] = $client_id;
            }
            
            // Create task.
            $result = $this->create_task(
                get_current_user_id(),
                $task_type,
                $title,
                $inputs,
                $description,
                $priority,
                $dependencies
            );
            
            if (is_wp_error($result)) {
                error_log('Task creation failed: ' . $result->get_error_message());
                wp_send_json_error([
                    'message' => $result->get_error_message(),
                ]);
                return;
            }
            
            error_log('Task created successfully: ' . $result);
            wp_send_json_success([
                'task_id' => $result,
                'message' => __('Task created successfully.', 'ryvr-ai'),
            ]);
            
        } catch (Exception $e) {
            error_log('Exception in ajax_create_task: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('An unexpected error occurred: ', 'ryvr-ai') . $e->getMessage(),
            ]);
        }
    }

    /**
     * AJAX handler for cancelling a task.
     */
    public function ajax_cancel_task() {
        // Check nonce.
        check_ajax_referer( 'ryvr_nonce', 'nonce' );
        
        // Check capabilities.
        if ( ! ryvr_current_user_can( 'ryvr_run_tasks' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ryvr-ai' ) ] );
        }
        
        // Get task ID.
        $task_id = isset( $_POST['task_id'] ) ? intval( $_POST['task_id'] ) : 0;
        
        if ( empty( $task_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Task ID is required.', 'ryvr-ai' ) ] );
        }
        
        // Get the task.
        $task = $this->get_task( $task_id );
        
        if ( ! $task ) {
            wp_send_json_error( [ 'message' => __( 'Task not found.', 'ryvr-ai' ) ] );
        }
        
        // Check if user owns the task or is an admin.
        if ( $task->user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ryvr-ai' ) ] );
        }
        
        // Cancel the task.
        $result = $this->cancel_task( $task_id );
        
        if ( ! $result ) {
            wp_send_json_error( [ 'message' => __( 'Failed to cancel task.', 'ryvr-ai' ) ] );
        }
        
        wp_send_json_success( [ 'message' => __( 'Task cancelled.', 'ryvr-ai' ) ] );
    }

    /**
     * AJAX handler for approving a task.
     */
    public function ajax_approve_task() {
        // Check nonce.
        check_ajax_referer( 'ryvr_nonce', 'nonce' );
        
        // Check capabilities.
        if ( ! ryvr_current_user_can( 'ryvr_run_tasks' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ryvr-ai' ) ] );
        }
        
        // Get task ID.
        $task_id = isset( $_POST['task_id'] ) ? intval( $_POST['task_id'] ) : 0;
        
        if ( empty( $task_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Task ID is required.', 'ryvr-ai' ) ] );
        }
        
        // Get the task.
        $task = $this->get_task( $task_id );
        
        if ( ! $task ) {
            wp_send_json_error( [ 'message' => __( 'Task not found.', 'ryvr-ai' ) ] );
        }
        
        // Check if user owns the task or is an admin.
        if ( $task->user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ryvr-ai' ) ] );
        }
        
        // Approve the task.
        $result = $this->approve_task( $task_id );
        
        if ( ! $result ) {
            wp_send_json_error( [ 'message' => __( 'Failed to approve task.', 'ryvr-ai' ) ] );
        }
        
        wp_send_json_success( [ 'message' => __( 'Task approved.', 'ryvr-ai' ) ] );
    }

    /**
     * AJAX handler for getting task status.
     */
    public function ajax_get_task_status() {
        // Check nonce.
        check_ajax_referer( 'ryvr_nonce', 'nonce' );
        
        // Check capabilities.
        if ( ! ryvr_current_user_can( 'ryvr_run_tasks' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ryvr-ai' ) ] );
        }
        
        // Get task ID.
        $task_id = isset( $_POST['task_id'] ) ? intval( $_POST['task_id'] ) : 0;
        
        if ( empty( $task_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Task ID is required.', 'ryvr-ai' ) ] );
        }
        
        // Get the task.
        $task = $this->get_task( $task_id );
        
        if ( ! $task ) {
            wp_send_json_error( [ 'message' => __( 'Task not found.', 'ryvr-ai' ) ] );
        }
        
        // Check if user owns the task or is an admin.
        if ( $task->user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ryvr-ai' ) ] );
        }
        
        // Get task logs.
        global $wpdb;
        $logs_table = $wpdb->prefix . 'ryvr_task_logs';
        
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$logs_table} WHERE task_id = %d ORDER BY created_at ASC",
                $task_id
            )
        );
        
        // Prepare response data.
        $data = [
            'task_id'    => $task->id,
            'title'      => $task->title,
            'status'     => $task->status,
            'statusText' => ryvr_get_task_status_label( $task->status ),
            'statusClass' => ryvr_get_task_status_class( $task->status ),
            'outputs'    => $task->outputs,
            'logs'       => $logs,
        ];
        
        wp_send_json_success( $data );
    }

    // Add new AJAX methods for managing dependencies and priority

    /**
     * AJAX handler for adding a task dependency.
     *
     * @return void
     */
    public function ajax_add_task_dependency() {
        // Check nonce.
        check_ajax_referer( 'ryvr_task_nonce', 'nonce' );
        
        // Check capabilities.
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to modify tasks.', 'ryvr-ai' ),
            ) );
        }
        
        // Get parameters.
        $task_id = isset( $_POST['task_id'] ) ? (int) $_POST['task_id'] : 0;
        $dependency_id = isset( $_POST['dependency_id'] ) ? (int) $_POST['dependency_id'] : 0;
        
        // Validate parameters.
        if ( empty( $task_id ) || empty( $dependency_id ) ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid task or dependency ID.', 'ryvr-ai' ),
            ) );
        }
        
        // Add dependency.
        $result = $this->add_task_dependency( $task_id, $dependency_id );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
            ) );
        }
        
        wp_send_json_success( array(
            'message' => __( 'Dependency added successfully.', 'ryvr-ai' ),
        ) );
    }
    
    /**
     * AJAX handler for removing a task dependency.
     *
     * @return void
     */
    public function ajax_remove_task_dependency() {
        // Check nonce.
        check_ajax_referer( 'ryvr_task_nonce', 'nonce' );
        
        // Check capabilities.
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to modify tasks.', 'ryvr-ai' ),
            ) );
        }
        
        // Get parameters.
        $task_id = isset( $_POST['task_id'] ) ? (int) $_POST['task_id'] : 0;
        $dependency_id = isset( $_POST['dependency_id'] ) ? (int) $_POST['dependency_id'] : 0;
        
        // Validate parameters.
        if ( empty( $task_id ) || empty( $dependency_id ) ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid task or dependency ID.', 'ryvr-ai' ),
            ) );
        }
        
        // Remove dependency.
        $result = $this->remove_task_dependency( $task_id, $dependency_id );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
            ) );
        }
        
        wp_send_json_success( array(
            'message' => __( 'Dependency removed successfully.', 'ryvr-ai' ),
        ) );
    }
    
    /**
     * AJAX handler for updating task priority.
     *
     * @return void
     */
    public function ajax_update_task_priority() {
        // Check nonce.
        check_ajax_referer( 'ryvr_task_nonce', 'nonce' );
        
        // Check capabilities.
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to modify tasks.', 'ryvr-ai' ),
            ) );
        }
        
        // Get parameters.
        $task_id = isset( $_POST['task_id'] ) ? (int) $_POST['task_id'] : 0;
        $priority = isset( $_POST['priority'] ) ? (int) $_POST['priority'] : 50;
        
        // Validate parameters.
        if ( empty( $task_id ) ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid task ID.', 'ryvr-ai' ),
            ) );
        }
        
        // Update priority.
        $result = $this->update_task_priority( $task_id, $priority );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
            ) );
        }
        
        wp_send_json_success( array(
            'message' => __( 'Task priority updated successfully.', 'ryvr-ai' ),
        ) );
    }
} 