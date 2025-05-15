<?php
/**
 * Credits page template.
 *
 * @package Ryvr
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check if user is an administrator
$is_admin = current_user_can('manage_options');

// Get user's credit balance
$api_manager = ryvr()->get_component('api_manager');
$api_credits = [
    'openai' => [
        'used' => get_user_meta(get_current_user_id(), 'ryvr_openai_credits_used', true) ?: 0,
        'total' => $is_admin ? '∞' : 1000, // Unlimited for admins
    ],
    'dataforseo' => [
        'used' => get_user_meta(get_current_user_id(), 'ryvr_dataforseo_credits_used', true) ?: 0,
        'total' => $is_admin ? '∞' : 500, // Unlimited for admins
    ],
];

// Get usage history (for demonstration purposes)
$recent_usage = [];
if ($api_manager) {
    // Get API logs if available
    $openai_service = $api_manager->get_service('openai');
    if ($openai_service) {
        $recent_usage['openai'] = method_exists($openai_service, 'get_recent_logs') ? 
            $openai_service->get_recent_logs(5) : [];
    }
    
    $dataforseo_service = $api_manager->get_service('dataforseo');
    if ($dataforseo_service) {
        $recent_usage['dataforseo'] = method_exists($dataforseo_service, 'get_recent_logs') ? 
            $dataforseo_service->get_recent_logs(5) : [];
    }
}
?>

<div class="wrap ryvr-credits-wrap">
    <h1><?php esc_html_e('API Credits', 'ryvr-ai'); ?></h1>
    
    <div class="ryvr-credits-summary">
        <div class="ryvr-credit-cards">
            <div class="ryvr-credit-card openai">
                <h2><?php esc_html_e('OpenAI Credits', 'ryvr-ai'); ?></h2>
                <div class="ryvr-credit-amount">
                    <?php if ($is_admin): ?>
                        <span class="ryvr-unlimited"><?php esc_html_e('Unlimited', 'ryvr-ai'); ?></span>
                    <?php else: ?>
                        <span class="ryvr-used"><?php echo esc_html($api_credits['openai']['used']); ?></span>
                        <span class="ryvr-divider">/</span>
                        <span class="ryvr-total"><?php echo esc_html($api_credits['openai']['total']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="ryvr-credit-details">
                    <?php if (!$is_admin): ?>
                        <div class="ryvr-credit-progress">
                            <div class="ryvr-progress-bar" style="width: <?php echo min(100, ($api_credits['openai']['used'] / $api_credits['openai']['total']) * 100); ?>%"></div>
                        </div>
                    <?php endif; ?>
                    <p class="ryvr-credit-info">
                        <?php if ($is_admin): ?>
                            <?php esc_html_e('As an administrator, you have unlimited access to the OpenAI API.', 'ryvr-ai'); ?>
                        <?php else: ?>
                            <?php
                            $remaining = $api_credits['openai']['total'] - $api_credits['openai']['used'];
                            printf(
                                esc_html__('You have %d credits remaining for OpenAI API requests.', 'ryvr-ai'),
                                $remaining
                            );
                            ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <div class="ryvr-credit-card dataforseo">
                <h2><?php esc_html_e('DataForSEO Credits', 'ryvr-ai'); ?></h2>
                <div class="ryvr-credit-amount">
                    <?php if ($is_admin): ?>
                        <span class="ryvr-unlimited"><?php esc_html_e('Unlimited', 'ryvr-ai'); ?></span>
                    <?php else: ?>
                        <span class="ryvr-used"><?php echo esc_html($api_credits['dataforseo']['used']); ?></span>
                        <span class="ryvr-divider">/</span>
                        <span class="ryvr-total"><?php echo esc_html($api_credits['dataforseo']['total']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="ryvr-credit-details">
                    <?php if (!$is_admin): ?>
                        <div class="ryvr-credit-progress">
                            <div class="ryvr-progress-bar" style="width: <?php echo min(100, ($api_credits['dataforseo']['used'] / $api_credits['dataforseo']['total']) * 100); ?>%"></div>
                        </div>
                    <?php endif; ?>
                    <p class="ryvr-credit-info">
                        <?php if ($is_admin): ?>
                            <?php esc_html_e('As an administrator, you have unlimited access to the DataForSEO API.', 'ryvr-ai'); ?>
                        <?php else: ?>
                            <?php
                            $remaining = $api_credits['dataforseo']['total'] - $api_credits['dataforseo']['used'];
                            printf(
                                esc_html__('You have %d credits remaining for DataForSEO API requests.', 'ryvr-ai'),
                                $remaining
                            );
                            ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="ryvr-credits-info">
        <h2><?php esc_html_e('About API Credits', 'ryvr-ai'); ?></h2>
        <p>
            <?php esc_html_e('API credits are used when making requests to external services like OpenAI and DataForSEO.', 'ryvr-ai'); ?>
            <?php if (!$is_admin): ?>
                <?php esc_html_e('Different types of requests consume different amounts of credits based on complexity and resources required.', 'ryvr-ai'); ?>
            <?php endif; ?>
        </p>
        
        <?php if ($is_admin): ?>
            <div class="notice notice-info">
                <p>
                    <strong><?php esc_html_e('Administrator Status', 'ryvr-ai'); ?>:</strong>
                    <?php esc_html_e('As an administrator, you have unlimited credits for all API services. Your usage is still tracked for monitoring purposes.', 'ryvr-ai'); ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($recent_usage['openai']) || !empty($recent_usage['dataforseo'])): ?>
    <div class="ryvr-recent-usage">
        <h2><?php esc_html_e('Recent API Usage', 'ryvr-ai'); ?></h2>
        
        <?php if (!empty($recent_usage['openai'])): ?>
        <div class="ryvr-usage-section">
            <h3><?php esc_html_e('OpenAI API', 'ryvr-ai'); ?></h3>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'ryvr-ai'); ?></th>
                        <th><?php esc_html_e('Endpoint', 'ryvr-ai'); ?></th>
                        <th><?php esc_html_e('Credits Used', 'ryvr-ai'); ?></th>
                        <th><?php esc_html_e('Status', 'ryvr-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_usage['openai'] as $log): ?>
                    <tr>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?></td>
                        <td><?php echo esc_html($log->endpoint); ?></td>
                        <td><?php echo esc_html($log->credits_used); ?></td>
                        <td>
                            <span class="ryvr-status ryvr-status-<?php echo esc_attr($log->status); ?>">
                                <?php echo esc_html(ucfirst($log->status)); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($recent_usage['dataforseo'])): ?>
        <div class="ryvr-usage-section">
            <h3><?php esc_html_e('DataForSEO API', 'ryvr-ai'); ?></h3>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'ryvr-ai'); ?></th>
                        <th><?php esc_html_e('Endpoint', 'ryvr-ai'); ?></th>
                        <th><?php esc_html_e('Credits Used', 'ryvr-ai'); ?></th>
                        <th><?php esc_html_e('Status', 'ryvr-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_usage['dataforseo'] as $log): ?>
                    <tr>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?></td>
                        <td><?php echo esc_html($log->endpoint); ?></td>
                        <td><?php echo esc_html($log->credits_used); ?></td>
                        <td>
                            <span class="ryvr-status ryvr-status-<?php echo esc_attr($log->status); ?>">
                                <?php echo esc_html(ucfirst($log->status)); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
    .ryvr-credit-cards {
        display: flex;
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .ryvr-credit-card {
        flex: 1;
        padding: 20px;
        border-radius: 8px;
        background-color: #fff;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }
    
    .ryvr-credit-card.openai {
        border-top: 5px solid #10a37f;
    }
    
    .ryvr-credit-card.dataforseo {
        border-top: 5px solid #4c6ef5;
    }
    
    .ryvr-credit-amount {
        font-size: 32px;
        font-weight: bold;
        margin: 15px 0;
    }
    
    .ryvr-unlimited {
        color: #10a37f;
    }
    
    .ryvr-used {
        color: #333;
    }
    
    .ryvr-divider {
        color: #999;
        margin: 0 5px;
    }
    
    .ryvr-total {
        color: #777;
    }
    
    .ryvr-credit-progress {
        height: 10px;
        background-color: #f0f0f0;
        border-radius: 5px;
        margin-bottom: 10px;
    }
    
    .ryvr-progress-bar {
        height: 100%;
        border-radius: 5px;
        background-color: #10a37f;
    }
    
    .ryvr-credit-card.dataforseo .ryvr-progress-bar {
        background-color: #4c6ef5;
    }
    
    .ryvr-credit-info {
        color: #555;
        margin-top: 10px;
    }
    
    .ryvr-credits-info {
        margin-bottom: 30px;
    }
    
    .ryvr-recent-usage h3 {
        margin-top: 25px;
    }
    
    .ryvr-status {
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 12px;
    }
    
    .ryvr-status-success {
        background-color: #d4edda;
        color: #155724;
    }
    
    .ryvr-status-error {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    @media (max-width: 782px) {
        .ryvr-credit-cards {
            flex-direction: column;
        }
    }
</style> 