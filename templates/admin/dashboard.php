<?php
/**
 * Admin Dashboard Template
 *
 * @package    Ryvr
 * @subpackage Ryvr/Admin/Templates
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get user credits.
$user_id = get_current_user_id();
$credits = ryvr_get_user_credits( $user_id );

// Get task engine.
$task_engine = ryvr()->get_component( 'task_engine' );
$task_types = $task_engine ? $task_engine->get_task_types() : [];

// Get task statistics.
global $wpdb;
$tasks_table = $wpdb->prefix . 'ryvr_tasks';

// Count tasks by status.
$total_tasks = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$tasks_table} WHERE user_id = %d",
        $user_id
    )
);

$active_tasks = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$tasks_table} WHERE user_id = %d AND status IN ('pending', 'approval_required', 'processing')",
        $user_id
    )
);

$completed_tasks = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$tasks_table} WHERE user_id = %d AND status = 'completed'",
        $user_id
    )
);

// Get recent tasks.
$recent_tasks = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$tasks_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 5",
        $user_id
    )
);

// Get usage by task type.
$task_type_usage = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT task_type, COUNT(*) as count, SUM(credits_cost) as credits FROM {$tasks_table} WHERE user_id = %d GROUP BY task_type",
        $user_id
    )
);

$task_type_stats = array();
foreach ( $task_type_usage as $usage ) {
    $task_type_stats[ $usage->task_type ] = array(
        'count' => $usage->count,
        'credits' => $usage->credits,
    );
}

// Get total credits used.
$total_credits_used = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT SUM(credits_cost) FROM {$tasks_table} WHERE user_id = %d",
        $user_id
    )
);
$total_credits_used = $total_credits_used ? $total_credits_used : 0;
?>

<div class="wrap ryvr-dashboard">
    <h1><?php esc_html_e( 'Ryvr AI Dashboard', 'ryvr-ai' ); ?></h1>
    
    <div class="ryvr-dashboard-header">
        <div class="ryvr-dashboard-credit-balance">
            <h2><?php esc_html_e( 'Credit Balance', 'ryvr-ai' ); ?></h2>
            
            <div class="ryvr-credit-count">
                <?php echo esc_html( ryvr_format_credits( $credits ) ); ?>
            </div>
            
            <p><?php esc_html_e( 'Use credits to run AI-powered tasks like content generation, keyword research, and SEO audits.', 'ryvr-ai' ); ?></p>
            
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-credits' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Get More Credits', 'ryvr-ai' ); ?></a>
        </div>
        
        <div class="ryvr-dashboard-usage">
            <h2><?php esc_html_e( 'Usage Statistics', 'ryvr-ai' ); ?></h2>
            
            <div class="ryvr-usage-stats">
                <div class="ryvr-usage-stat">
                    <span class="ryvr-usage-label"><?php esc_html_e( 'Total Tasks', 'ryvr-ai' ); ?></span>
                    <span class="ryvr-usage-value"><?php echo esc_html( $total_tasks ); ?></span>
                </div>
                
                <div class="ryvr-usage-stat">
                    <span class="ryvr-usage-label"><?php esc_html_e( 'Active Tasks', 'ryvr-ai' ); ?></span>
                    <span class="ryvr-usage-value"><?php echo esc_html( $active_tasks ); ?></span>
                </div>
                
                <div class="ryvr-usage-stat">
                    <span class="ryvr-usage-label"><?php esc_html_e( 'Completed Tasks', 'ryvr-ai' ); ?></span>
                    <span class="ryvr-usage-value"><?php echo esc_html( $completed_tasks ); ?></span>
                </div>
                
                <div class="ryvr-usage-stat">
                    <span class="ryvr-usage-label"><?php esc_html_e( 'Credits Used', 'ryvr-ai' ); ?></span>
                    <span class="ryvr-usage-value"><?php echo esc_html( $total_credits_used ); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="ryvr-quick-actions">
        <h2><?php esc_html_e( 'Quick Actions', 'ryvr-ai' ); ?></h2>
        
        <div class="ryvr-quick-actions-grid">
            <?php if ( isset( $task_types['keyword_research'] ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-new-task&type=keyword_research' ) ); ?>" class="ryvr-quick-action">
                    <span class="dashicons dashicons-search"></span>
                    <span class="ryvr-quick-action-label"><?php esc_html_e( 'Keyword Research', 'ryvr-ai' ); ?></span>
                </a>
            <?php endif; ?>
            
            <?php if ( isset( $task_types['content_generation'] ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-new-task&type=content_generation' ) ); ?>" class="ryvr-quick-action">
                    <span class="dashicons dashicons-welcome-write-blog"></span>
                    <span class="ryvr-quick-action-label"><?php esc_html_e( 'Generate Content', 'ryvr-ai' ); ?></span>
                </a>
            <?php endif; ?>
            
            <?php if ( isset( $task_types['seo_audit'] ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-new-task&type=seo_audit' ) ); ?>" class="ryvr-quick-action">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <span class="ryvr-quick-action-label"><?php esc_html_e( 'SEO Audit', 'ryvr-ai' ); ?></span>
                </a>
            <?php endif; ?>
            
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-new-task' ) ); ?>" class="ryvr-quick-action">
                <span class="dashicons dashicons-plus-alt"></span>
                <span class="ryvr-quick-action-label"><?php esc_html_e( 'New Task', 'ryvr-ai' ); ?></span>
            </a>
            
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-tasks' ) ); ?>" class="ryvr-quick-action">
                <span class="dashicons dashicons-list-view"></span>
                <span class="ryvr-quick-action-label"><?php esc_html_e( 'View Tasks', 'ryvr-ai' ); ?></span>
            </a>
            
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-settings' ) ); ?>" class="ryvr-quick-action">
                <span class="dashicons dashicons-admin-settings"></span>
                <span class="ryvr-quick-action-label"><?php esc_html_e( 'Settings', 'ryvr-ai' ); ?></span>
            </a>
        </div>
    </div>
    
    <?php if ( !empty( $recent_tasks ) ) : ?>
        <div class="ryvr-recent-tasks">
            <h2><?php esc_html_e( 'Recent Tasks', 'ryvr-ai' ); ?></h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Title', 'ryvr-ai' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'ryvr-ai' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'ryvr-ai' ); ?></th>
                        <th><?php esc_html_e( 'Created', 'ryvr-ai' ); ?></th>
                        <th><?php esc_html_e( 'Credits', 'ryvr-ai' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'ryvr-ai' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent_tasks as $task ) : ?>
                        <?php 
                        $task_obj = new Ryvr\Task_Engine\Task( $task );
                        $task_type_info = isset( $task_types[ $task->task_type ] ) ? $task_types[ $task->task_type ] : null;
                        $task_type_name = $task_type_info ? $task_type_info['name'] : $task->task_type;
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-tasks&task=' . $task->id ) ); ?>">
                                    <?php echo esc_html( $task->title ); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html( $task_type_name ); ?></td>
                            <td>
                                <span class="ryvr-task-status <?php echo esc_attr( $task_obj->get_status_class() ); ?> ryvr-task-status-poll" data-task-id="<?php echo esc_attr( $task->id ); ?>" data-task-status="<?php echo esc_attr( $task->status ); ?>">
                                    <?php echo esc_html( $task_obj->get_status_label() ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( human_time_diff( strtotime( $task->created_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'ryvr-ai' ) ); ?></td>
                            <td><?php echo esc_html( $task->credits_cost ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-tasks&task=' . $task->id ) ); ?>" class="button button-small"><?php esc_html_e( 'View', 'ryvr-ai' ); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p class="ryvr-view-all">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-tasks' ) ); ?>" class="button"><?php esc_html_e( 'View All Tasks', 'ryvr-ai' ); ?></a>
            </p>
        </div>
    <?php endif; ?>
    
    <?php if ( !empty( $task_type_stats ) ) : ?>
        <div class="ryvr-task-type-stats">
            <h2><?php esc_html_e( 'Task Type Usage', 'ryvr-ai' ); ?></h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Task Type', 'ryvr-ai' ); ?></th>
                        <th><?php esc_html_e( 'Tasks Created', 'ryvr-ai' ); ?></th>
                        <th><?php esc_html_e( 'Credits Used', 'ryvr-ai' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'ryvr-ai' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $task_type_stats as $type => $stats ) : ?>
                        <?php $task_type_info = isset( $task_types[ $type ] ) ? $task_types[ $type ] : null; ?>
                        <tr>
                            <td><?php echo esc_html( $task_type_info ? $task_type_info['name'] : $type ); ?></td>
                            <td><?php echo esc_html( $stats['count'] ); ?></td>
                            <td><?php echo esc_html( $stats['credits'] ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-new-task&type=' . $type ) ); ?>" class="button button-small"><?php esc_html_e( 'Create Task', 'ryvr-ai' ); ?></a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-tasks&type=' . $type ) ); ?>" class="button button-small"><?php esc_html_e( 'View Tasks', 'ryvr-ai' ); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <div class="ryvr-dashboard-resources">
        <h2><?php esc_html_e( 'Resources', 'ryvr-ai' ); ?></h2>
        
        <div class="ryvr-resources-grid">
            <div class="ryvr-resource-card">
                <h3><span class="dashicons dashicons-book"></span> <?php esc_html_e( 'Documentation', 'ryvr-ai' ); ?></h3>
                <p><?php esc_html_e( 'Learn how to use Ryvr AI to automate your tasks and save time.', 'ryvr-ai' ); ?></p>
                <a href="https://ryvr.io/docs/" target="_blank" class="button"><?php esc_html_e( 'View Documentation', 'ryvr-ai' ); ?></a>
            </div>
            
            <div class="ryvr-resource-card">
                <h3><span class="dashicons dashicons-video-alt3"></span> <?php esc_html_e( 'Tutorials', 'ryvr-ai' ); ?></h3>
                <p><?php esc_html_e( 'Watch step-by-step tutorials on how to get the most out of Ryvr AI.', 'ryvr-ai' ); ?></p>
                <a href="https://ryvr.io/tutorials/" target="_blank" class="button"><?php esc_html_e( 'Watch Tutorials', 'ryvr-ai' ); ?></a>
            </div>
            
            <div class="ryvr-resource-card">
                <h3><span class="dashicons dashicons-editor-help"></span> <?php esc_html_e( 'Support', 'ryvr-ai' ); ?></h3>
                <p><?php esc_html_e( 'Need help? Our support team is ready to assist you.', 'ryvr-ai' ); ?></p>
                <a href="https://ryvr.io/support/" target="_blank" class="button"><?php esc_html_e( 'Get Support', 'ryvr-ai' ); ?></a>
            </div>
        </div>
    </div>
    
    <?php
    // Get client manager and clients
    $client_manager = ryvr()->get_component('admin')->client_manager;
    $clients = $client_manager ? $client_manager->get_clients() : [];
    
    if (!empty($clients)):
    ?>
    <div class="ryvr-dashboard-widget ryvr-clients-widget">
        <h2><?php esc_html_e('Clients', 'ryvr-ai'); ?></h2>
        
        <div class="ryvr-clients-list">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Client', 'ryvr-ai'); ?></th>
                        <th><?php esc_html_e('Tasks', 'ryvr-ai'); ?></th>
                        <th><?php esc_html_e('API Keys', 'ryvr-ai'); ?></th>
                        <th><?php esc_html_e('Actions', 'ryvr-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): 
                        // Count tasks for this client
                        $task_count = $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT COUNT(*) FROM {$wpdb->prefix}ryvr_tasks WHERE 
                                inputs LIKE %s",
                                '%"client_id":' . $client->ID . '%'
                            )
                        );
                        
                        // Check API keys
                        $has_dataforseo = $client_manager->client_has_api_keys($client->ID, 'dataforseo');
                        $has_openai = $client_manager->client_has_api_keys($client->ID, 'openai');
                    ?>
                    <tr>
                        <td>
                            <strong><a href="<?php echo esc_url(get_edit_post_link($client->ID)); ?>"><?php echo esc_html($client->post_title); ?></a></strong>
                        </td>
                        <td><?php echo esc_html($task_count); ?></td>
                        <td>
                            <span class="ryvr-api-status <?php echo $has_dataforseo ? 'ryvr-api-active' : 'ryvr-api-inactive'; ?>">
                                <?php esc_html_e('DataForSEO', 'ryvr-ai'); ?>
                            </span>
                            <span class="ryvr-api-status <?php echo $has_openai ? 'ryvr-api-active' : 'ryvr-api-inactive'; ?>">
                                <?php esc_html_e('OpenAI', 'ryvr-ai'); ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=ryvr-ai-new-task&client_id=' . $client->ID)); ?>" class="button button-small">
                                <?php esc_html_e('New Task', 'ryvr-ai'); ?>
                            </a>
                            <a href="<?php echo esc_url(get_edit_post_link($client->ID)); ?>" class="button button-small">
                                <?php esc_html_e('Edit', 'ryvr-ai'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="ryvr-widget-actions">
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=ryvr_client')); ?>" class="button"><?php esc_html_e('Add New Client', 'ryvr-ai'); ?></a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=ryvr_client')); ?>" class="button"><?php esc_html_e('View All Clients', 'ryvr-ai'); ?></a>
        </div>
    </div>
    <?php endif; ?>
</div> 