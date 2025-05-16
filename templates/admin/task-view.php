<?php
/**
 * Admin Task View Template
 *
 * @package    Ryvr
 * @subpackage Ryvr/Admin/Templates
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure we have a task object.
if ( ! isset( $task ) || ! $task ) {
    return;
}

// Get task type information.
$task_type_info = isset( $task_types[ $task->task_type ] ) ? $task_types[ $task->task_type ] : null;
$task_type_name = $task_type_info ? $task_type_info['name'] : $task->task_type;

// Create task object to get helper methods.
$task_obj = new Ryvr\Task_Engine\Task( $task );

// Get task logs.
global $wpdb;
$logs_table = $wpdb->prefix . 'ryvr_task_logs';
$logs = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$logs_table} WHERE task_id = %d ORDER BY created_at ASC",
        $task->id
    )
);

// Get task processor for type-specific processing.
$task_processor = $task_engine ? $task_engine->get_task_processor( $task->task_type ) : null;

// Process task outputs for display.
$outputs = json_decode( $task->outputs );
$formatted_outputs = $task_processor ? $task_processor->format_outputs( $outputs ) : $outputs;
?>

<div class="wrap ryvr-task-view" id="ryvr-task-view" data-task-id="<?php echo esc_attr( $task->id ); ?>" data-task-status="<?php echo esc_attr( $task->status ); ?>">
    <h1><?php echo esc_html( $task->title ); ?></h1>
    
    <div class="ryvr-task-header">
        <div class="ryvr-task-status-container">
            <span class="ryvr-task-status <?php echo esc_attr( $task_obj->get_status_class() ); ?>">
                <?php echo esc_html( $task_obj->get_status_label() ); ?>
            </span>
        </div>
        
        <div class="ryvr-task-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-tasks' ) ); ?>" class="button"><?php esc_html_e( 'Back to Tasks', 'ryvr-ai' ); ?></a>
            
            <?php if ( $task_obj->requires_approval() ) : ?>
                <a href="#" class="button button-primary ryvr-approve-task" data-task-id="<?php echo esc_attr( $task->id ); ?>"><?php esc_html_e( 'Approve Task', 'ryvr-ai' ); ?></a>
            <?php endif; ?>
            
            <?php if ( $task_obj->is_active() ) : ?>
                <a href="#" class="button ryvr-cancel-task" data-task-id="<?php echo esc_attr( $task->id ); ?>"><?php esc_html_e( 'Cancel Task', 'ryvr-ai' ); ?></a>
            <?php endif; ?>
            
            <?php if ( $task_obj->is_completed() ) : ?>
                <div class="ryvr-task-actions-dropdown">
                    <button class="button dropdown-toggle"><?php esc_html_e( 'Export', 'ryvr-ai' ); ?> &#9662;</button>
                    <div class="dropdown-content">
                        <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=ryvr_export_task&format=json&task_id=' . $task->id . '&nonce=' . wp_create_nonce( 'ryvr_export_task' ) ) ); ?>"><?php esc_html_e( 'JSON', 'ryvr-ai' ); ?></a>
                        <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=ryvr_export_task&format=csv&task_id=' . $task->id . '&nonce=' . wp_create_nonce( 'ryvr_export_task' ) ) ); ?>"><?php esc_html_e( 'CSV', 'ryvr-ai' ); ?></a>
                        <a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=ryvr_export_task&format=text&task_id=' . $task->id . '&nonce=' . wp_create_nonce( 'ryvr_export_task' ) ) ); ?>"><?php esc_html_e( 'Text', 'ryvr-ai' ); ?></a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="ryvr-task-info">
        <div class="ryvr-task-info-item">
            <span class="ryvr-task-info-label"><?php esc_html_e( 'Task Type', 'ryvr-ai' ); ?></span>
            <?php echo esc_html( $task_type_name ); ?>
        </div>
        
        <?php 
        // Show client info if available
        $client_id = 0;
        if (!empty($task->inputs)) {
            $inputs = json_decode($task->inputs, true);
            if (is_array($inputs) && isset($inputs['client_id'])) {
                $client_id = intval($inputs['client_id']);
            }
        }
        
        if ($client_id > 0) {
            $client = get_post($client_id);
            if ($client) {
                ?>
                <div class="ryvr-task-info-item">
                    <span class="ryvr-task-info-label"><?php esc_html_e( 'Client', 'ryvr-ai' ); ?></span>
                    <a href="<?php echo esc_url(get_edit_post_link($client_id)); ?>"><?php echo esc_html($client->post_title); ?></a>
                </div>
                <?php
            }
        }
        ?>
        
        <div class="ryvr-task-info-item">
            <span class="ryvr-task-info-label"><?php esc_html_e( 'Created', 'ryvr-ai' ); ?></span>
            <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $task->created_at ) ) ); ?>
        </div>
        
        <div class="ryvr-task-info-item">
            <span class="ryvr-task-info-label"><?php esc_html_e( 'Last Updated', 'ryvr-ai' ); ?></span>
            <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $task->updated_at ) ) ); ?>
        </div>
        
        <div class="ryvr-task-info-item">
            <span class="ryvr-task-info-label"><?php esc_html_e( 'Credits Used', 'ryvr-ai' ); ?></span>
            <?php 
            echo esc_html( $task->credits_cost );
            
            // Show which credit system was used
            if ($client_id > 0) {
                echo ' <span class="ryvr-credit-source">(' . esc_html__('Client credits', 'ryvr-ai') . ')</span>';
            } else {
                echo ' <span class="ryvr-credit-source">(' . esc_html__('User credits', 'ryvr-ai') . ')</span>';
            }
            ?>
        </div>
    </div>
    
    <?php if ( $task->description ) : ?>
        <div class="ryvr-task-description">
            <h2><?php esc_html_e( 'Description', 'ryvr-ai' ); ?></h2>
            <p><?php echo esc_html( $task->description ); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="ryvr-task-details">
        <h2><?php esc_html_e( 'Task Details', 'ryvr-ai' ); ?></h2>
        
        <div class="ryvr-task-inputs">
            <h3><?php esc_html_e( 'Inputs', 'ryvr-ai' ); ?></h3>
            
            <?php if ( $task->inputs ) : ?>
                <div class="ryvr-task-inputs-content">
                    <?php
                    $inputs = json_decode( $task->inputs, true );
                    if ( $inputs && is_array( $inputs ) ) :
                    ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Parameter', 'ryvr-ai' ); ?></th>
                                    <th><?php esc_html_e( 'Value', 'ryvr-ai' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $inputs as $key => $value ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( str_replace( '_', ' ', ucfirst( $key ) ) ); ?></td>
                                        <td>
                                            <?php
                                            if ( is_array( $value ) ) {
                                                if ( isset( $value[0] ) && is_string( $value[0] ) ) {
                                                    // Array of strings (e.g. keywords)
                                                    echo esc_html( implode( ', ', $value ) );
                                                } else {
                                                    // Complex array, show as JSON
                                                    echo '<pre>' . esc_html( json_encode( $value, JSON_PRETTY_PRINT ) ) . '</pre>';
                                                }
                                            } else {
                                                echo esc_html( $value );
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <pre><?php echo esc_html( $task->inputs ); ?></pre>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <p><?php esc_html_e( 'No inputs provided.', 'ryvr-ai' ); ?></p>
            <?php endif; ?>
        </div>
        
        <?php if ( $task_obj->is_completed() || $task_obj->has_partial_results() ) : ?>
            <div class="ryvr-task-outputs">
                <h3><?php esc_html_e( 'Results', 'ryvr-ai' ); ?></h3>
                
                <?php if ( $task->outputs ) : ?>
                    <div class="ryvr-task-result" id="task-outputs">
                        <?php
                        // Handle different output types differently
                        if ( $task->task_type === 'content_generation' ) :
                            // For content generation, we need raw and formatted versions
                            $content = is_object( $outputs ) && isset( $outputs->content ) ? $outputs->content : $outputs;
                            ?>
                            <div class="ryvr-content-tabs">
                                <div class="ryvr-tabs-nav">
                                    <button class="ryvr-tab-button active" data-tab="preview"><?php esc_html_e( 'Preview', 'ryvr-ai' ); ?></button>
                                    <button class="ryvr-tab-button" data-tab="raw"><?php esc_html_e( 'Raw', 'ryvr-ai' ); ?></button>
                                </div>
                                
                                <div class="ryvr-tab-content active" id="tab-preview">
                                    <div id="content-preview" class="ryvr-content-preview">
                                        <?php
                                        // Content will be rendered by JavaScript
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="ryvr-tab-content" id="tab-raw">
                                    <pre id="content-output"><?php echo esc_html( $content ); ?></pre>
                                </div>
                            </div>
                        <?php elseif ( $task->task_type === 'keyword_research' ) : ?>
                            <?php if ( isset( $formatted_outputs ) && is_array( $formatted_outputs ) ) : ?>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'Keyword', 'ryvr-ai' ); ?></th>
                                            <th><?php esc_html_e( 'Search Volume', 'ryvr-ai' ); ?></th>
                                            <th><?php esc_html_e( 'CPC', 'ryvr-ai' ); ?></th>
                                            <th><?php esc_html_e( 'Competition', 'ryvr-ai' ); ?></th>
                                            <th><?php esc_html_e( 'Difficulty', 'ryvr-ai' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $formatted_outputs as $keyword ) : ?>
                                            <tr>
                                                <td><?php echo esc_html( $keyword->keyword ); ?></td>
                                                <td><?php echo esc_html( $keyword->search_volume ); ?></td>
                                                <td><?php echo esc_html( $keyword->cpc ); ?></td>
                                                <td><?php echo esc_html( $keyword->competition ); ?></td>
                                                <td><?php echo esc_html( $keyword->difficulty ); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else : ?>
                                <pre><?php echo esc_html( json_encode( $outputs, JSON_PRETTY_PRINT ) ); ?></pre>
                            <?php endif; ?>
                        <?php elseif ( $task->task_type === 'seo_audit' ) : ?>
                            <?php if ( isset( $formatted_outputs ) && isset( $formatted_outputs->issues ) ) : ?>
                                <div class="ryvr-seo-score">
                                    <h4><?php esc_html_e( 'SEO Score', 'ryvr-ai' ); ?></h4>
                                    <div class="ryvr-score-display">
                                        <div class="ryvr-score-circle" style="--score: <?php echo esc_attr( $formatted_outputs->score ); ?>%">
                                            <span class="ryvr-score-value"><?php echo esc_html( $formatted_outputs->score ); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="ryvr-seo-issues">
                                    <h4><?php esc_html_e( 'Issues Found', 'ryvr-ai' ); ?></h4>
                                    
                                    <div class="ryvr-issues-summary">
                                        <div class="ryvr-issue-count ryvr-critical">
                                            <span class="ryvr-count"><?php echo esc_html( $formatted_outputs->issue_counts->critical ); ?></span>
                                            <span class="ryvr-label"><?php esc_html_e( 'Critical', 'ryvr-ai' ); ?></span>
                                        </div>
                                        <div class="ryvr-issue-count ryvr-warning">
                                            <span class="ryvr-count"><?php echo esc_html( $formatted_outputs->issue_counts->warning ); ?></span>
                                            <span class="ryvr-label"><?php esc_html_e( 'Warning', 'ryvr-ai' ); ?></span>
                                        </div>
                                        <div class="ryvr-issue-count ryvr-info">
                                            <span class="ryvr-count"><?php echo esc_html( $formatted_outputs->issue_counts->info ); ?></span>
                                            <span class="ryvr-label"><?php esc_html_e( 'Info', 'ryvr-ai' ); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="ryvr-issues-list">
                                        <div class="ryvr-tabs-nav">
                                            <button class="ryvr-tab-button active" data-tab="critical"><?php esc_html_e( 'Critical', 'ryvr-ai' ); ?></button>
                                            <button class="ryvr-tab-button" data-tab="warning"><?php esc_html_e( 'Warning', 'ryvr-ai' ); ?></button>
                                            <button class="ryvr-tab-button" data-tab="info"><?php esc_html_e( 'Info', 'ryvr-ai' ); ?></button>
                                        </div>
                                        
                                        <div class="ryvr-tab-content active" id="tab-critical">
                                            <?php if ( !empty( $formatted_outputs->issues->critical ) ) : ?>
                                                <ul class="ryvr-issues">
                                                    <?php foreach ( $formatted_outputs->issues->critical as $issue ) : ?>
                                                        <li class="ryvr-issue">
                                                            <h5 class="ryvr-issue-title"><?php echo esc_html( $issue->title ); ?></h5>
                                                            <p class="ryvr-issue-description"><?php echo esc_html( $issue->description ); ?></p>
                                                            <?php if ( !empty( $issue->urls ) ) : ?>
                                                                <ul class="ryvr-issue-urls">
                                                                    <?php foreach ( $issue->urls as $url ) : ?>
                                                                        <li><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a></li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else : ?>
                                                <p><?php esc_html_e( 'No critical issues found.', 'ryvr-ai' ); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="ryvr-tab-content" id="tab-warning">
                                            <?php if ( !empty( $formatted_outputs->issues->warning ) ) : ?>
                                                <ul class="ryvr-issues">
                                                    <?php foreach ( $formatted_outputs->issues->warning as $issue ) : ?>
                                                        <li class="ryvr-issue">
                                                            <h5 class="ryvr-issue-title"><?php echo esc_html( $issue->title ); ?></h5>
                                                            <p class="ryvr-issue-description"><?php echo esc_html( $issue->description ); ?></p>
                                                            <?php if ( !empty( $issue->urls ) ) : ?>
                                                                <ul class="ryvr-issue-urls">
                                                                    <?php foreach ( $issue->urls as $url ) : ?>
                                                                        <li><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a></li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else : ?>
                                                <p><?php esc_html_e( 'No warning issues found.', 'ryvr-ai' ); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="ryvr-tab-content" id="tab-info">
                                            <?php if ( !empty( $formatted_outputs->issues->info ) ) : ?>
                                                <ul class="ryvr-issues">
                                                    <?php foreach ( $formatted_outputs->issues->info as $issue ) : ?>
                                                        <li class="ryvr-issue">
                                                            <h5 class="ryvr-issue-title"><?php echo esc_html( $issue->title ); ?></h5>
                                                            <p class="ryvr-issue-description"><?php echo esc_html( $issue->description ); ?></p>
                                                            <?php if ( !empty( $issue->urls ) ) : ?>
                                                                <ul class="ryvr-issue-urls">
                                                                    <?php foreach ( $issue->urls as $url ) : ?>
                                                                        <li><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a></li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else : ?>
                                                <p><?php esc_html_e( 'No info issues found.', 'ryvr-ai' ); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php else : ?>
                                <pre><?php echo esc_html( json_encode( $outputs, JSON_PRETTY_PRINT ) ); ?></pre>
                            <?php endif; ?>
                        <?php else : ?>
                            <pre><?php echo esc_html( json_encode( $outputs, JSON_PRETTY_PRINT ) ); ?></pre>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <p class="ryvr-no-results"><?php esc_html_e( 'No results yet.', 'ryvr-ai' ); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ( $task_obj->is_completed() ) : ?>
            <div id="task-complete-message" class="ryvr-task-complete-message">
                <h3><?php esc_html_e( 'Task Completed', 'ryvr-ai' ); ?></h3>
                <p><?php esc_html_e( 'This task has been completed successfully.', 'ryvr-ai' ); ?></p>
            </div>
        <?php elseif ( $task_obj->is_failed() ) : ?>
            <div class="ryvr-task-error-message">
                <h3><?php esc_html_e( 'Task Failed', 'ryvr-ai' ); ?></h3>
                <p><?php echo esc_html( $task->error_message ); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ( $task_obj->is_processing() ) : ?>
            <div class="ryvr-task-processing-message">
                <h3><?php esc_html_e( 'Processing Task', 'ryvr-ai' ); ?></h3>
                <div class="ryvr-progress-bar">
                    <div class="ryvr-progress-indicator"></div>
                </div>
                <p><?php esc_html_e( 'Your task is currently being processed. This page will automatically update when complete.', 'ryvr-ai' ); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ( $task_obj->requires_approval() ) : ?>
            <div class="ryvr-task-approval-message">
                <h3><?php esc_html_e( 'Approval Required', 'ryvr-ai' ); ?></h3>
                <p><?php esc_html_e( 'This task requires your approval before processing.', 'ryvr-ai' ); ?></p>
                <a href="#" class="button button-primary ryvr-approve-task" data-task-id="<?php echo esc_attr( $task->id ); ?>"><?php esc_html_e( 'Approve Task', 'ryvr-ai' ); ?></a>
            </div>
        <?php endif; ?>
        
        <div class="ryvr-task-logs">
            <h3><?php esc_html_e( 'Task Logs', 'ryvr-ai' ); ?></h3>
            
            <div class="ryvr-logs-container" id="task-logs">
                <?php if ( empty( $logs ) ) : ?>
                    <p><?php esc_html_e( 'No logs available.', 'ryvr-ai' ); ?></p>
                <?php else : ?>
                    <?php foreach ( $logs as $log ) : ?>
                        <div class="ryvr-log-entry ryvr-log-<?php echo esc_attr( $log->log_level ); ?>">
                            <span class="ryvr-log-timestamp"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->created_at ) ) ); ?></span>
                            <div class="ryvr-log-message"><?php echo esc_html( $log->message ); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.ryvr-tab-button').on('click', function() {
        var tabId = $(this).data('tab');
        
        // Update tab buttons
        $(this).parent().find('.ryvr-tab-button').removeClass('active');
        $(this).addClass('active');
        
        // Update tab content
        var tabPrefix = '#tab-';
        if ($(this).closest('.ryvr-tabs-nav').siblings('.ryvr-tab-content').length) {
            tabPrefix = '#tab-';
        }
        
        $(this).closest('.ryvr-tabs-nav').siblings('.ryvr-tab-content').removeClass('active');
        $(tabPrefix + tabId).addClass('active');
    });
});
</script> 