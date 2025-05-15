<?php
/**
 * Dashboard page of the plugin.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Admin/Partials
 */

// Don't allow direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get current user credits
$db_manager = new Ryvr\Database\Database_Manager();
$user_id = get_current_user_id();

$credits_table = $db_manager->get_table('credits');
global $wpdb;

$credits_balance = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT SUM(credits_amount) FROM $credits_table WHERE user_id = %d",
        $user_id
    )
);

$credits_balance = $credits_balance ?: 0;

?>

<div class="wrap">
    <h1>Ryvr AI Platform Dashboard</h1>
    
    <div class="dashboard-container" style="display: flex; flex-wrap: wrap; margin: 0 -10px;">
        <!-- Credits Card -->
        <div class="dashboard-card" style="flex: 1 1 30%; min-width: 250px; margin: 10px; padding: 20px; background: #fff; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2>Credits</h2>
            <div class="credits-balance" style="font-size: 2.5em; font-weight: bold; margin: 15px 0; color: #0073aa;">
                <?php echo number_format($credits_balance); ?>
            </div>
            <p>Available credits for API usage</p>
            <a href="?page=ryvr-settings&tab=credits" class="button button-primary">Manage Credits</a>
        </div>
        
        <!-- API Calls Card -->
        <div class="dashboard-card" style="flex: 1 1 30%; min-width: 250px; margin: 10px; padding: 20px; background: #fff; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2>API Usage</h2>
            <div class="api-usage" style="font-size: 2.5em; font-weight: bold; margin: 15px 0; color: #0073aa;">
                <?php 
                // Get API call count
                $api_logs_table = $db_manager->get_table('api_logs');
                $api_call_count = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM $api_logs_table WHERE user_id = %d",
                        $user_id
                    )
                );
                echo number_format($api_call_count ?: 0); 
                ?>
            </div>
            <p>Total API calls made</p>
            <a href="?page=ryvr-api-demo" class="button button-primary">Test API</a>
        </div>
        
        <!-- Tasks Card -->
        <div class="dashboard-card" style="flex: 1 1 30%; min-width: 250px; margin: 10px; padding: 20px; background: #fff; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2>Tasks</h2>
            <div class="tasks-count" style="font-size: 2.5em; font-weight: bold; margin: 15px 0; color: #0073aa;">
                <?php 
                // Get task count
                $tasks_table = $db_manager->get_table('tasks');
                $task_count = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM $tasks_table WHERE user_id = %d",
                        $user_id
                    )
                );
                echo number_format($task_count ?: 0); 
                ?>
            </div>
            <p>Tasks created</p>
            <a href="#" class="button button-primary">View Tasks</a>
        </div>
    </div>
    
    <div style="margin-top: 30px;">
        <h2>Recent API Activity</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Endpoint</th>
                    <th>Status</th>
                    <th>Credits Used</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Get recent API logs
                $api_logs = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM $api_logs_table WHERE user_id = %d ORDER BY created_at DESC LIMIT 10",
                        $user_id
                    )
                );
                
                if (empty($api_logs)) {
                    echo '<tr><td colspan="5">No API activity yet.</td></tr>';
                } else {
                    foreach ($api_logs as $log) {
                        ?>
                        <tr>
                            <td><?php echo esc_html($log->service); ?></td>
                            <td><?php echo esc_html($log->endpoint); ?></td>
                            <td><?php 
                                if ($log->status === 'success') {
                                    echo '<span style="color: green;">Success</span>'; 
                                } else {
                                    echo '<span style="color: red;">Error</span>';
                                }
                            ?></td>
                            <td><?php echo esc_html($log->credits_used); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?></td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div> 