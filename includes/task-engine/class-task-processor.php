<?php
/**
 * The Task Processor abstract class.
 *
 * Defines the interface for task processors.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Task_Engine
 */

namespace Ryvr\Task_Engine;

/**
 * The Task Processor abstract class.
 *
 * This class defines the interface that all task processors
 * must implement to handle task processing.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Task_Engine
 */
abstract class Task_Processor {

    /**
     * Task type identifier.
     *
     * @var string
     */
    protected $task_type = '';

    /**
     * API Manager instance.
     *
     * @var \Ryvr\API\API_Manager
     */
    protected $api_manager;

    /**
     * Constructor.
     */
    public function __construct() {
        // Get API Manager instance.
        $this->api_manager = ryvr()->get_component( 'api_manager' );
    }

    /**
     * Process a task.
     *
     * This method should be implemented by all task processors
     * to perform the actual task processing.
     *
     * @param object $task Task object.
     * @return array|WP_Error Task outputs or error.
     */
    abstract public function process( $task );

    /**
     * Validate task inputs.
     *
     * This method should be implemented by all task processors
     * to validate task inputs before processing.
     *
     * @param array $inputs Task inputs.
     * @return true|WP_Error True if valid, WP_Error if invalid.
     */
    abstract public function validate_inputs( $inputs );

    /**
     * Get task type.
     *
     * @return string Task type.
     */
    public function get_task_type() {
        return $this->task_type;
    }

    /**
     * Get an API service.
     *
     * @param string $service API service name.
     * @return object|null API service instance or null if not found.
     */
    protected function get_api_service( $service ) {
        $api_manager = ryvr()->get_component( 'api_manager' );
        
        if ( ! $api_manager ) {
            return null;
        }
        
        // Get the client ID from the task inputs if available
        $client_id = 0;
        if (isset($this->task) && !empty($this->task->inputs) && isset($this->task->inputs['client_id'])) {
            $client_id = intval($this->task->inputs['client_id']);
        }
        
        return $api_manager->get_service( $service, $client_id );
    }

    /**
     * Log a message.
     *
     * @param int    $task_id Task ID.
     * @param string $message Log message.
     * @param string $level Log level.
     * @return void
     */
    protected function log( $task_id, $message, $level = 'info' ) {
        $task_engine = ryvr()->get_component( 'task_engine' );
        
        if ( $task_engine ) {
            $task_engine->log_task( $task_id, $message, $level );
        }
    }

    /**
     * Format task outputs for returning.
     *
     * @param array $outputs Raw outputs.
     * @return array Formatted outputs.
     */
    protected function format_outputs( $outputs ) {
        // Add timestamp.
        $outputs['generated_at'] = current_time( 'mysql', true );
        
        return $outputs;
    }

    /**
     * Create a task error.
     *
     * @param string $code Error code.
     * @param string $message Error message.
     * @param mixed  $data Error data.
     * @return \WP_Error Error object.
     */
    protected function create_error( $code, $message, $data = null ) {
        return new \WP_Error( $code, $message, $data );
    }
} 