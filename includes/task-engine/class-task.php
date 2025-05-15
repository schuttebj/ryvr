<?php
/**
 * The Task class.
 *
 * Represents a task in the Ryvr platform.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Task_Engine
 */

namespace Ryvr\Task_Engine;

/**
 * The Task class.
 *
 * This class represents a task in the system.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Task_Engine
 */
class Task {

    /**
     * Task ID.
     *
     * @var int
     */
    private $id;

    /**
     * User ID.
     *
     * @var int
     */
    private $user_id;

    /**
     * Task type.
     *
     * @var string
     */
    private $task_type;

    /**
     * Task status.
     *
     * @var string
     */
    private $status;

    /**
     * Task title.
     *
     * @var string
     */
    private $title;

    /**
     * Task description.
     *
     * @var string
     */
    private $description;

    /**
     * Task inputs.
     *
     * @var array
     */
    private $inputs;

    /**
     * Task outputs.
     *
     * @var array
     */
    private $outputs;

    /**
     * Credits cost.
     *
     * @var int
     */
    private $credits_cost;

    /**
     * Task priority (0-100, higher = more important).
     *
     * @var int
     */
    private $priority;

    /**
     * Dependent tasks IDs.
     *
     * @var array
     */
    private $dependencies;

    /**
     * Created at timestamp.
     *
     * @var string
     */
    private $created_at;

    /**
     * Updated at timestamp.
     *
     * @var string
     */
    private $updated_at;

    /**
     * Started at timestamp.
     *
     * @var string
     */
    private $started_at;

    /**
     * Completed at timestamp.
     *
     * @var string
     */
    private $completed_at;

    /**
     * Constructor.
     *
     * @param object|array $data Task data.
     */
    public function __construct( $data ) {
        if ( is_object( $data ) ) {
            $data = get_object_vars( $data );
        }

        $this->id = isset( $data['id'] ) ? (int) $data['id'] : 0;
        $this->user_id = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
        $this->task_type = isset( $data['task_type'] ) ? $data['task_type'] : '';
        $this->status = isset( $data['status'] ) ? $data['status'] : '';
        $this->title = isset( $data['title'] ) ? $data['title'] : '';
        $this->description = isset( $data['description'] ) ? $data['description'] : '';
        $this->inputs = isset( $data['inputs'] ) ? (array) $data['inputs'] : [];
        $this->outputs = isset( $data['outputs'] ) ? (array) $data['outputs'] : [];
        $this->credits_cost = isset( $data['credits_cost'] ) ? (int) $data['credits_cost'] : 0;
        $this->priority = isset( $data['priority'] ) ? (int) $data['priority'] : 50; // Default priority: medium (50)
        $this->dependencies = isset( $data['dependencies'] ) ? (array) $data['dependencies'] : [];
        $this->created_at = isset( $data['created_at'] ) ? $data['created_at'] : '';
        $this->updated_at = isset( $data['updated_at'] ) ? $data['updated_at'] : '';
        $this->started_at = isset( $data['started_at'] ) ? $data['started_at'] : '';
        $this->completed_at = isset( $data['completed_at'] ) ? $data['completed_at'] : '';
    }

    /**
     * Get task ID.
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get user ID.
     *
     * @return int
     */
    public function get_user_id() {
        return $this->user_id;
    }

    /**
     * Get task type.
     *
     * @return string
     */
    public function get_task_type() {
        return $this->task_type;
    }

    /**
     * Get task status.
     *
     * @return string
     */
    public function get_status() {
        return $this->status;
    }

    /**
     * Get task title.
     *
     * @return string
     */
    public function get_title() {
        return $this->title;
    }

    /**
     * Get task description.
     *
     * @return string
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * Get task inputs.
     *
     * @return array
     */
    public function get_inputs() {
        return $this->inputs;
    }

    /**
     * Get a specific input value.
     *
     * @param string $key Input key.
     * @param mixed  $default Default value if key doesn't exist.
     * @return mixed
     */
    public function get_input( $key, $default = null ) {
        return isset( $this->inputs[ $key ] ) ? $this->inputs[ $key ] : $default;
    }

    /**
     * Get task outputs.
     *
     * @return array
     */
    public function get_outputs() {
        return $this->outputs;
    }

    /**
     * Get a specific output value.
     *
     * @param string $key Output key.
     * @param mixed  $default Default value if key doesn't exist.
     * @return mixed
     */
    public function get_output( $key, $default = null ) {
        return isset( $this->outputs[ $key ] ) ? $this->outputs[ $key ] : $default;
    }

    /**
     * Get credits cost.
     *
     * @return int
     */
    public function get_credits_cost() {
        return $this->credits_cost;
    }

    /**
     * Get task priority.
     *
     * @return int Priority value (0-100)
     */
    public function get_priority() {
        return $this->priority;
    }

    /**
     * Get task dependencies.
     *
     * @return array Array of task IDs this task depends on
     */
    public function get_dependencies() {
        return $this->dependencies;
    }

    /**
     * Check if this task has dependencies.
     *
     * @return bool True if task has dependencies, false otherwise
     */
    public function has_dependencies() {
        return !empty($this->dependencies);
    }

    /**
     * Get created at timestamp.
     *
     * @return string
     */
    public function get_created_at() {
        return $this->created_at;
    }

    /**
     * Get updated at timestamp.
     *
     * @return string
     */
    public function get_updated_at() {
        return $this->updated_at;
    }

    /**
     * Get started at timestamp.
     *
     * @return string
     */
    public function get_started_at() {
        return $this->started_at;
    }

    /**
     * Get completed at timestamp.
     *
     * @return string
     */
    public function get_completed_at() {
        return $this->completed_at;
    }

    /**
     * Get task status label.
     *
     * @return string
     */
    public function get_status_label() {
        return ryvr_get_task_status_label( $this->status );
    }

    /**
     * Get task status CSS class.
     *
     * @return string
     */
    public function get_status_class() {
        return ryvr_get_task_status_class( $this->status );
    }

    /**
     * Get task duration in seconds.
     *
     * @return int|null Duration in seconds or null if task is not completed.
     */
    public function get_duration() {
        if ( empty( $this->started_at ) || empty( $this->completed_at ) ) {
            return null;
        }

        $start = strtotime( $this->started_at );
        $end = strtotime( $this->completed_at );

        return $end - $start;
    }

    /**
     * Get formatted task duration.
     *
     * @return string Formatted duration or empty string if task is not completed.
     */
    public function get_formatted_duration() {
        $duration = $this->get_duration();

        if ( null === $duration ) {
            return '';
        }

        if ( $duration < 60 ) {
            return sprintf( _n( '%d second', '%d seconds', $duration, 'ryvr-ai' ), $duration );
        }

        $minutes = floor( $duration / 60 );
        $seconds = $duration % 60;

        if ( $seconds > 0 ) {
            return sprintf(
                _n( '%d minute', '%d minutes', $minutes, 'ryvr-ai' ) . ', ' . _n( '%d second', '%d seconds', $seconds, 'ryvr-ai' ),
                $minutes,
                $seconds
            );
        }

        return sprintf( _n( '%d minute', '%d minutes', $minutes, 'ryvr-ai' ), $minutes );
    }

    /**
     * Get task logs.
     *
     * @return array Task logs.
     */
    public function get_logs() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ryvr_task_logs';

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE task_id = %d ORDER BY created_at ASC",
                $this->id
            )
        );

        return $logs ?: [];
    }

    /**
     * Check if task is pending.
     *
     * @return bool
     */
    public function is_pending() {
        return 'pending' === $this->status;
    }

    /**
     * Check if task requires approval.
     *
     * @return bool
     */
    public function requires_approval() {
        return 'approval_required' === $this->status;
    }

    /**
     * Check if task is processing.
     *
     * @return bool
     */
    public function is_processing() {
        return 'processing' === $this->status;
    }

    /**
     * Check if task is completed.
     *
     * @return bool
     */
    public function is_completed() {
        return 'completed' === $this->status;
    }

    /**
     * Check if task has failed.
     *
     * @return bool
     */
    public function has_failed() {
        return 'failed' === $this->status;
    }

    /**
     * Check if task is canceled.
     *
     * @return bool
     */
    public function is_canceled() {
        return 'canceled' === $this->status;
    }

    /**
     * Check if task is active (pending, approval required, or processing).
     *
     * @return bool
     */
    public function is_active() {
        return in_array( $this->status, [ 'pending', 'approval_required', 'processing' ], true );
    }

    /**
     * Check if task is finished (completed, failed, or canceled).
     *
     * @return bool
     */
    public function is_finished() {
        return in_array( $this->status, [ 'completed', 'failed', 'canceled' ], true );
    }

    /**
     * Convert task to array.
     *
     * @return array
     */
    public function to_array() {
        return [
            'id'           => $this->id,
            'user_id'      => $this->user_id,
            'task_type'    => $this->task_type,
            'status'       => $this->status,
            'title'        => $this->title,
            'description'  => $this->description,
            'inputs'       => $this->inputs,
            'outputs'      => $this->outputs,
            'credits_cost' => $this->credits_cost,
            'priority'     => $this->priority,
            'dependencies' => $this->dependencies,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
            'started_at'   => $this->started_at,
            'completed_at' => $this->completed_at,
        ];
    }
} 