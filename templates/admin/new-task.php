<?php
/**
 * Admin New Task Template
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

// Get pre-selected task type from URL.
$selected_type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';

// Get user credits.
$user_id = get_current_user_id();
$credits = ryvr_get_user_credits( $user_id );

// Get available clients.
$client_manager = ryvr()->get_component('admin')->client_manager;
$clients = $client_manager ? $client_manager->get_clients() : [];
?>

<div class="wrap ryvr-new-task">
    <h1><?php esc_html_e( 'Create New Task', 'ryvr-ai' ); ?></h1>
    
    <div class="ryvr-credit-info">
        <p>
            <?php 
            printf(
                /* translators: %s: formatted credit count */
                esc_html__( 'Your credit balance: %s', 'ryvr-ai' ),
                '<strong>' . esc_html( ryvr_format_credits( $credits ) ) . '</strong>'
            );
            ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-credits' ) ); ?>" class="button button-small"><?php esc_html_e( 'Get More Credits', 'ryvr-ai' ); ?></a>
        </p>
    </div>
    
    <?php if ( empty( $task_types ) ) : ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'No task types are registered. Please contact the administrator.', 'ryvr-ai' ); ?></p>
        </div>
    <?php else : ?>
        <div class="ryvr-task-form-container">
            <form id="ryvr-new-task-form" method="post">
                <?php wp_nonce_field( 'ryvr_nonce', 'ryvr_nonce' ); ?>
                
                <div class="ryvr-form-section">
                    <h2><?php esc_html_e( 'Select Task Type', 'ryvr-ai' ); ?></h2>
                    
                    <div class="ryvr-task-types">
                        <?php foreach ( $task_types as $type => $info ) : ?>
                            <div class="ryvr-task-type-card <?php echo $selected_type === $type ? 'selected' : ''; ?>" data-task-type="<?php echo esc_attr( $type ); ?>" data-credits="<?php echo esc_attr( $info['credits_cost'] ); ?>">
                                <div class="ryvr-task-type-header">
                                    <span class="dashicons dashicons-<?php echo esc_attr( $info['icon'] ); ?>"></span>
                                    <h3><?php echo esc_html( $info['name'] ); ?></h3>
                                </div>
                                <div class="ryvr-task-type-description">
                                    <p><?php echo esc_html( $info['description'] ); ?></p>
                                </div>
                                <div class="ryvr-task-type-cost">
                                    <?php 
                                    echo esc_html(
                                        sprintf(
                                            /* translators: %d: number of credits */
                                            _n( '%d Credit', '%d Credits', $info['credits_cost'], 'ryvr-ai' ),
                                            $info['credits_cost']
                                        )
                                    ); 
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <input type="hidden" name="task_type" id="task_type" value="<?php echo esc_attr( $selected_type ); ?>">
                </div>
                
                <div class="ryvr-form-section" id="task-details-section" style="<?php echo empty( $selected_type ) ? 'display: none;' : ''; ?>">
                    <h2><?php esc_html_e( 'Task Details', 'ryvr-ai' ); ?></h2>
                    
                    <?php if (!empty($clients)): ?>
                    <div class="ryvr-form-field">
                        <label for="client_id"><?php esc_html_e( 'Client', 'ryvr-ai' ); ?></label>
                        <?php echo $client_manager->get_client_dropdown('client_id', 0, true); ?>
                        <p class="description"><?php esc_html_e( 'Select a client for this task. Client-specific API keys will be used if available.', 'ryvr-ai' ); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="ryvr-form-field">
                        <label for="task_title"><?php esc_html_e( 'Task Title', 'ryvr-ai' ); ?></label>
                        <input type="text" name="task_title" id="task_title" class="regular-text" required>
                        <p class="description"><?php esc_html_e( 'Enter a descriptive title for this task.', 'ryvr-ai' ); ?></p>
                    </div>
                    
                    <div class="ryvr-form-field">
                        <label for="task_description"><?php esc_html_e( 'Description (Optional)', 'ryvr-ai' ); ?></label>
                        <textarea name="task_description" id="task_description" rows="3" class="large-text"></textarea>
                        <p class="description"><?php esc_html_e( 'Provide additional details about this task.', 'ryvr-ai' ); ?></p>
                    </div>
                    
                    <!-- Task type specific fields will be loaded here -->
                    <div id="task-specific-fields"></div>
                    
                    <div class="ryvr-form-submit">
                        <p class="ryvr-credits-required">
                            <?php esc_html_e( 'Credits Required:', 'ryvr-ai' ); ?> <span id="credits-cost">0</span>
                        </p>
                        <button type="submit" class="button button-primary" id="create-task-button"><?php esc_html_e( 'Create Task', 'ryvr-ai' ); ?></button>
                    </div>
                </div>
            </form>
            
            <!-- Task type specific templates -->
            <div id="task-templates" style="display: none;">
                <!-- Keyword Research Template -->
                <div id="template-keyword_research">
                    <div class="ryvr-form-field">
                        <label for="seed_keyword"><?php esc_html_e( 'Seed Keyword', 'ryvr-ai' ); ?></label>
                        <input type="text" name="inputs[seed_keyword]" id="seed_keyword" class="regular-text" required>
                        <p class="description"><?php esc_html_e( 'Enter the main keyword to research.', 'ryvr-ai' ); ?></p>
                    </div>
                    
                    <div class="ryvr-form-field">
                        <label for="location"><?php esc_html_e( 'Location', 'ryvr-ai' ); ?></label>
                        <select name="inputs[location]" id="location">
                            <option value="2840"><?php esc_html_e( 'United States', 'ryvr-ai' ); ?></option>
                            <option value="2826"><?php esc_html_e( 'United Kingdom', 'ryvr-ai' ); ?></option>
                            <option value="2124"><?php esc_html_e( 'Canada', 'ryvr-ai' ); ?></option>
                            <option value="2036"><?php esc_html_e( 'Australia', 'ryvr-ai' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Select the target location for keyword research.', 'ryvr-ai' ); ?></p>
                    </div>
                    
                    <div class="ryvr-form-field">
                        <label for="language"><?php esc_html_e( 'Language', 'ryvr-ai' ); ?></label>
                        <select name="inputs[language]" id="language">
                            <option value="en"><?php esc_html_e( 'English', 'ryvr-ai' ); ?></option>
                            <option value="es"><?php esc_html_e( 'Spanish', 'ryvr-ai' ); ?></option>
                            <option value="fr"><?php esc_html_e( 'French', 'ryvr-ai' ); ?></option>
                            <option value="de"><?php esc_html_e( 'German', 'ryvr-ai' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Select the target language for keyword research.', 'ryvr-ai' ); ?></p>
                    </div>
                    
                    <div class="ryvr-form-field">
                        <label for="limit"><?php esc_html_e( 'Keyword Limit', 'ryvr-ai' ); ?></label>
                        <input type="number" name="inputs[limit]" id="limit" min="10" max="500" value="100">
                        <p class="description"><?php esc_html_e( 'Maximum number of keywords to return.', 'ryvr-ai' ); ?></p>
                    </div>
                </div>
                
                <!-- Content Generation Template -->
                <div id="template-content_generation">
                    <div class="ryvr-form-field">
                        <label for="content_type"><?php esc_html_e( 'Content Type', 'ryvr-ai' ); ?></label>
                        <select name="inputs[content_type]" id="content_type">
                            <option value="blog_post"><?php esc_html_e( 'Blog Post', 'ryvr-ai' ); ?></option>
                            <option value="product_description"><?php esc_html_e( 'Product Description', 'ryvr-ai' ); ?></option>
                            <option value="landing_page"><?php esc_html_e( 'Landing Page', 'ryvr-ai' ); ?></option>
                            <option value="email"><?php esc_html_e( 'Email', 'ryvr-ai' ); ?></option>
                            <option value="social_media"><?php esc_html_e( 'Social Media Post', 'ryvr-ai' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Select the type of content to generate.', 'ryvr-ai' ); ?></p>
                    </div>
                    
                    <div class="ryvr-form-field">
                        <label for="topic"><?php esc_html_e( 'Topic', 'ryvr-ai' ); ?></label>
                        <input type="text" name="inputs[topic]" id="topic" class="regular-text" required>
                        <p class="description"><?php esc_html_e( 'Enter the main topic for the content.', 'ryvr-ai' ); ?></p>
                    </div>
                    
                    <div class="ryvr-form-field">
                        <label for="keywords"><?php esc_html_e( 'Keywords', 'ryvr-ai' ); ?></label>
                        <input type="text" name="inputs[keywords]" id="keywords" class="regular-text">
                        <p class="description"><?php esc_html_e( 'Enter keywords to include, separated by commas.', 'ryvr-ai' ); ?></p>
                    </div>
                    
                    <div class="ryvr-form-field">
                        <label for="tone"><?php esc_html_e( 'Tone', 'ryvr-ai' ); ?></label>
                        <select name="inputs[tone]" id="tone">
                            <option value="professional"><?php esc_html_e( 'Professional', 'ryvr-ai' ); ?></option>
                            <option value="conversational"><?php esc_html_e( 'Conversational', 'ryvr-ai' ); ?></option>
                            <option value="casual"><?php esc_html_e( 'Casual', 'ryvr-ai' ); ?></option>
                            <option value="humorous"><?php esc_html_e( 'Humorous', 'ryvr-ai' ); ?></option>
                            <option value="formal"><?php esc_html_e( 'Formal', 'ryvr-ai' ); ?></option>
                            <option value="technical"><?php esc_html_e( 'Technical', 'ryvr-ai' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Select the tone for the content.', 'ryvr-ai' ); ?></p>
                    </div>
                    
                    <div class="ryvr-form-field">
                        <label for="outline"><?php esc_html_e( 'Outline (Optional)', 'ryvr-ai' ); ?></label>
                        <textarea name="inputs[outline]" id="outline" rows="5" class="large-text"></textarea>
                        <p class="description"><?php esc_html_e( 'Provide an outline for the content structure. Leave blank for AI to generate.', 'ryvr-ai' ); ?></p>
                    </div>
                    
                    <div class="ryvr-form-field">
                        <label for="word_count"><?php esc_html_e( 'Word Count', 'ryvr-ai' ); ?></label>
                        <input type="number" name="inputs[word_count]" id="word_count" min="100" max="3000" value="800">
                        <p class="description"><?php esc_html_e( 'Target word count for the content.', 'ryvr-ai' ); ?></p>
                    </div>
                </div>
                
                <!-- SEO Audit Template -->
                <div id="template-seo_audit">
                    <div class="ryvr-form-field">
                        <label for="domain"><?php esc_html_e( 'Domain', 'ryvr-ai' ); ?></label>
                        <input type="text" name="inputs[domain]" id="domain" class="regular-text" required>
                        <p class="description"><?php esc_html_e( 'Enter the domain to audit (e.g., example.com).', 'ryvr-ai' ); ?></p>
                    </div>
                    
                    <div class="ryvr-form-field">
                        <label for="max_pages"><?php esc_html_e( 'Max Pages', 'ryvr-ai' ); ?></label>
                        <input type="number" name="inputs[max_pages]" id="max_pages" min="10" max="1000" value="100">
                        <p class="description"><?php esc_html_e( 'Maximum number of pages to analyze.', 'ryvr-ai' ); ?></p>
                    </div>
                    
                    <div class="ryvr-form-field">
                        <label for="competitors"><?php esc_html_e( 'Competitors (Optional)', 'ryvr-ai' ); ?></label>
                        <textarea name="inputs[competitors]" id="competitors" rows="3" class="large-text"></textarea>
                        <p class="description"><?php esc_html_e( 'Enter competitor domains, one per line. Leave blank for auto-detection.', 'ryvr-ai' ); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="task-response" class="ryvr-task-response" style="display: none;">
            <h2><?php esc_html_e( 'Task Created', 'ryvr-ai' ); ?></h2>
            <div class="ryvr-task-response-content"></div>
            <div class="ryvr-task-response-actions">
                <a href="#" class="button button-primary" id="view-task-button"><?php esc_html_e( 'View Task', 'ryvr-ai' ); ?></a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-new-task' ) ); ?>" class="button"><?php esc_html_e( 'Create Another Task', 'ryvr-ai' ); ?></a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Task type selection
    $('.ryvr-task-type-card').on('click', function() {
        $('.ryvr-task-type-card').removeClass('selected');
        $(this).addClass('selected');
        
        var taskType = $(this).data('task-type');
        var credits = $(this).data('credits');
        
        $('#task_type').val(taskType);
        $('#credits-cost').text(credits);
        
        // Show task details section
        $('#task-details-section').show();
        
        // Load task-specific fields
        loadTaskSpecificFields(taskType);
    });
    
    // Pre-select task type if set in URL
    var selectedType = $('#task_type').val();
    if (selectedType) {
        var creditsCost = $('.ryvr-task-type-card[data-task-type="' + selectedType + '"]').data('credits');
        $('#credits-cost').text(creditsCost);
        loadTaskSpecificFields(selectedType);
    }
    
    // Load task-specific fields
    function loadTaskSpecificFields(taskType) {
        var template = $('#template-' + taskType).html();
        $('#task-specific-fields').html(template || '');
    }
    
    // Form submission
    $('#ryvr-new-task-form').on('submit', function(e) {
        e.preventDefault();
        
        var taskType = $('#task_type').val();
        if (!taskType) {
            alert('<?php echo esc_js( __( 'Please select a task type.', 'ryvr-ai' ) ); ?>');
            return;
        }
        
        // Get form data
        var formData = $(this).serialize();
        formData += '&action=ryvr_create_task';
        
        // Disable submit button
        $('#create-task-button').prop('disabled', true).text('<?php echo esc_js( __( 'Creating Task...', 'ryvr-ai' ) ); ?>');
        
        // Submit task
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('#ryvr-new-task-form').hide();
                    $('#task-response').show();
                    $('.ryvr-task-response-content').html('<p><?php echo esc_js( __( 'Your task has been created successfully!', 'ryvr-ai' ) ); ?></p>');
                    
                    // Update view task button
                    $('#view-task-button').attr('href', '<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-tasks&task=' ) ); ?>' + response.data.task_id);
                } else {
                    // Show error message
                    alert(response.data.message || '<?php echo esc_js( __( 'An error occurred while creating the task.', 'ryvr-ai' ) ); ?>');
                    $('#create-task-button').prop('disabled', false).text('<?php echo esc_js( __( 'Create Task', 'ryvr-ai' ) ); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js( __( 'An error occurred while communicating with the server.', 'ryvr-ai' ) ); ?>');
                $('#create-task-button').prop('disabled', false).text('<?php echo esc_js( __( 'Create Task', 'ryvr-ai' ) ); ?>');
            }
        });
    });
});
</script> 