<?php
/**
 * Admin Tasks Template
 *
 * @package    Ryvr
 * @subpackage Ryvr/Admin/Templates
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get task engine.
$task_engine = ryvr()->get_component( 'task_engine' );
$task_types = $task_engine ? $task_engine->get_task_types() : [];

// Check if viewing a specific task.
$task_id = isset( $_GET['task'] ) ? intval( $_GET['task'] ) : 0;

if ( $task_id ) {
    // Get the specific task.
    $task = $task_engine ? $task_engine->get_task( $task_id ) : null;
    
    if ( $task ) {
        // Task found, display task view.
        include RYVR_TEMPLATES_DIR . 'admin/task-view.php';
        return;
    }
}

// Get tasks for the current user.
global $wpdb;
$tasks_table = $wpdb->prefix . 'ryvr_tasks';
$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = 20;
$offset = ( $page - 1 ) * $per_page;
$user_id = get_current_user_id();

// Count total tasks.
$total_tasks = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$tasks_table} WHERE user_id = %d",
        $user_id
    )
);

// Get tasks.
$tasks = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$tasks_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d, %d",
        $user_id,
        $offset,
        $per_page
    )
);

// Calculate pagination.
$total_pages = ceil( $total_tasks / $per_page );

// Status filter.
$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
if ( $status_filter ) {
    $tasks = array_filter( $tasks, function( $task ) use ( $status_filter ) {
        return $task->status === $status_filter;
    });
}

// Type filter.
$type_filter = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';
if ( $type_filter ) {
    $tasks = array_filter( $tasks, function( $task ) use ( $type_filter ) {
        return $task->task_type === $type_filter;
    });
}
?>

<div class="wrap ryvr-tasks">
    <h1><?php esc_html_e( 'Tasks', 'ryvr-ai' ); ?></h1>
    
    <div class="ryvr-tasks-header">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-new-task' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Create New Task', 'ryvr-ai' ); ?></a>
        
        <div class="ryvr-tasks-filters">
            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="ryvr-ai-tasks">
                
                <select name="status">
                    <option value=""><?php esc_html_e( 'All Statuses', 'ryvr-ai' ); ?></option>
                    <option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pending', 'ryvr-ai' ); ?></option>
                    <option value="approval_required" <?php selected( $status_filter, 'approval_required' ); ?>><?php esc_html_e( 'Approval Required', 'ryvr-ai' ); ?></option>
                    <option value="processing" <?php selected( $status_filter, 'processing' ); ?>><?php esc_html_e( 'Processing', 'ryvr-ai' ); ?></option>
                    <option value="completed" <?php selected( $status_filter, 'completed' ); ?>><?php esc_html_e( 'Completed', 'ryvr-ai' ); ?></option>
                    <option value="failed" <?php selected( $status_filter, 'failed' ); ?>><?php esc_html_e( 'Failed', 'ryvr-ai' ); ?></option>
                    <option value="canceled" <?php selected( $status_filter, 'canceled' ); ?>><?php esc_html_e( 'Canceled', 'ryvr-ai' ); ?></option>
                </select>
                
                <select name="type">
                    <option value=""><?php esc_html_e( 'All Types', 'ryvr-ai' ); ?></option>
                    <?php foreach ( $task_types as $type => $info ) : ?>
                        <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $type_filter, $type ); ?>>
                            <?php echo esc_html( $info['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="button"><?php esc_html_e( 'Apply Filters', 'ryvr-ai' ); ?></button>
            </form>
        </div>
    </div>
    
    <?php if ( empty( $tasks ) ) : ?>
        <div class="ryvr-no-items">
            <p><?php esc_html_e( 'No tasks found.', 'ryvr-ai' ); ?></p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-new-task' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Create Your First Task', 'ryvr-ai' ); ?></a>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped ryvr-tasks-table">
            <thead>
                <tr>
                    <th width="5%"><?php esc_html_e( 'ID', 'ryvr-ai' ); ?></th>
                    <th width="25%"><?php esc_html_e( 'Title', 'ryvr-ai' ); ?></th>
                    <th width="15%"><?php esc_html_e( 'Type', 'ryvr-ai' ); ?></th>
                    <th width="10%"><?php esc_html_e( 'Client', 'ryvr-ai' ); ?></th>
                    <th width="10%"><?php esc_html_e( 'Status', 'ryvr-ai' ); ?></th>
                    <th width="15%"><?php esc_html_e( 'Created', 'ryvr-ai' ); ?></th>
                    <th width="10%"><?php esc_html_e( 'Credits', 'ryvr-ai' ); ?></th>
                    <th width="10%"><?php esc_html_e( 'Actions', 'ryvr-ai' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $tasks as $task ) : ?>
                    <?php 
                    $task_obj = new Ryvr\Task_Engine\Task( $task );
                    $task_type_info = isset( $task_types[ $task->task_type ] ) ? $task_types[ $task->task_type ] : null;
                    $task_type_name = $task_type_info ? $task_type_info['name'] : $task->task_type;
                    ?>
                    <tr>
                        <td><?php echo esc_html( $task->id ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-tasks&task=' . $task->id ) ); ?>">
                                <?php echo esc_html( $task->title ); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html( $task_type_name ); ?></td>
                        <td>
                        <?php 
                            $client_id = isset($task->inputs) && is_array($task->inputs) && isset($task->inputs['client_id']) ? 
                                intval($task->inputs['client_id']) : 0;
                            
                            if ($client_id > 0) {
                                $client = get_post($client_id);
                                if ($client) {
                                    echo esc_html($client->post_title);
                                } else {
                                    echo '—';
                                }
                            } else {
                                echo '—';
                            }
                        ?>
                        </td>
                        <td>
                            <span class="ryvr-task-status <?php echo esc_attr( $task_obj->get_status_class() ); ?> ryvr-task-status-poll" data-task-id="<?php echo esc_attr( $task->id ); ?>" data-task-status="<?php echo esc_attr( $task->status ); ?>">
                                <?php echo esc_html( $task_obj->get_status_label() ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( human_time_diff( strtotime( $task->created_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'ryvr-ai' ) ); ?></td>
                        <td><?php echo esc_html( $task->credits_cost ); ?></td>
                        <td>
                            <div class="row-actions">
                                <span class="view">
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-tasks&task=' . $task->id ) ); ?>"><?php esc_html_e( 'View', 'ryvr-ai' ); ?></a>
                                </span>
                                
                                <?php if ( $task_obj->requires_approval() ) : ?>
                                    <span class="approve"> | 
                                        <a href="#" class="ryvr-approve-task" data-task-id="<?php echo esc_attr( $task->id ); ?>"><?php esc_html_e( 'Approve', 'ryvr-ai' ); ?></a>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ( $task_obj->is_active() ) : ?>
                                    <span class="cancel"> | 
                                        <a href="#" class="ryvr-cancel-task" data-task-id="<?php echo esc_attr( $task->id ); ?>"><?php esc_html_e( 'Cancel', 'ryvr-ai' ); ?></a>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php 
                        printf(
                            /* translators: %s: number of tasks */
                            _n( '%s task', '%s tasks', $total_tasks, 'ryvr-ai' ),
                            number_format_i18n( $total_tasks )
                        );
                        ?>
                    </span>
                    
                    <span class="pagination-links">
                        <?php
                        // First page link.
                        if ( $page > 1 ) {
                            printf(
                                '<a class="first-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
                                esc_url( add_query_arg( 'paged', 1 ) ),
                                esc_html__( 'First page', 'ryvr-ai' ),
                                '&laquo;'
                            );
                        }
                        
                        // Previous page link.
                        if ( $page > 1 ) {
                            printf(
                                '<a class="prev-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
                                esc_url( add_query_arg( 'paged', max( 1, $page - 1 ) ) ),
                                esc_html__( 'Previous page', 'ryvr-ai' ),
                                '&lsaquo;'
                            );
                        }
                        
                        // Current page text.
                        printf(
                            '<span class="paging-input">%s / <span class="total-pages">%s</span></span>',
                            $page,
                            $total_pages
                        );
                        
                        // Next page link.
                        if ( $page < $total_pages ) {
                            printf(
                                '<a class="next-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
                                esc_url( add_query_arg( 'paged', min( $total_pages, $page + 1 ) ) ),
                                esc_html__( 'Next page', 'ryvr-ai' ),
                                '&rsaquo;'
                            );
                        }
                        
                        // Last page link.
                        if ( $page < $total_pages ) {
                            printf(
                                '<a class="last-page button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
                                esc_url( add_query_arg( 'paged', $total_pages ) ),
                                esc_html__( 'Last page', 'ryvr-ai' ),
                                '&raquo;'
                            );
                        }
                        ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div> 