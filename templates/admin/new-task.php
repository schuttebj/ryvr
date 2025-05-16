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
            <form id="ryvr-new-task-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'ryvr_create_task', 'ryvr_task_nonce' ); ?>
                <input type="hidden" name="action" value="ryvr_create_task">
                
                <div class="ryvr-form-field">
                    <label for="task_type"><?php esc_html_e( 'Task Type', 'ryvr-ai' ); ?></label>
                    <select id="task_type" name="task_type" required>
                        <option value=""><?php esc_html_e( 'Select Task Type', 'ryvr-ai' ); ?></option>
                        <?php foreach ( $task_types as $type => $task_info ) : ?>
                            <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $selected_type, $type ); ?> data-credits="<?php echo esc_attr( $task_info['credits_cost'] ); ?>"><?php echo esc_html( $task_info['name'] ); ?> (<?php echo esc_html( $task_info['credits_cost'] ); ?> <?php esc_html_e( 'credits', 'ryvr-ai' ); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description" id="task-description"></p>
                </div>
                
                <div class="ryvr-form-field">
                    <label for="title"><?php esc_html_e( 'Task Title', 'ryvr-ai' ); ?></label>
                    <input type="text" id="title" name="title" required placeholder="<?php esc_attr_e( 'Enter task title', 'ryvr-ai' ); ?>">
                </div>
                
                <div class="ryvr-form-field">
                    <label for="client_id"><?php esc_html_e( 'Client', 'ryvr-ai' ); ?></label>
                    <?php 
                    if ($client_manager) {
                        echo $client_manager->get_client_dropdown('client_id', 0, true);
                    } else {
                        echo '<select id="client_id" name="client_id"><option value="">' . esc_html__('No clients available', 'ryvr-ai') . '</option></select>';
                    }
                    ?>
                    <div id="client-credits-info" style="display:none; margin-top:10px; padding:10px; background:#f9f9f9; border-left:4px solid #007cba;">
                        <p>
                            <?php esc_html_e('Client credit balance:', 'ryvr-ai'); ?> 
                            <span id="client-credit-balance">0</span> <?php esc_html_e('credits', 'ryvr-ai'); ?>
                        </p>
                        <p id="client-credit-warning" style="color:#d63638; display:none;">
                            <?php esc_html_e('Warning: Client does not have enough credits for this task.', 'ryvr-ai'); ?>
                        </p>
                    </div>
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
    $('#task_type').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var taskType = selectedOption.val();
        
        // Show task description based on selection
        if (taskType) {
            // Get the task description from the server
            $.post(ajaxurl, {
                action: 'ryvr_get_task_type_info',
                task_type: taskType
            }, function(response) {
                if (response.success) {
                    $('#task-description').html(response.data.description);
                }
            });
        } else {
            $('#task-description').html('');
        }
        
        // Check client credits if client is selected
        checkClientCredits();
    });
    
    // Client selection
    $('#client_id').on('change', function() {
        var clientId = $(this).val();
        
        if (clientId) {
            // Get client credits
            $.post(ajaxurl, {
                action: 'ryvr_get_client_credits',
                client_id: clientId
            }, function(response) {
                if (response.success) {
                    // Show client credits info
                    $('#client-credits-info').show();
                    $('#client-credit-balance').text(response.data.credits_formatted);
                    
                    // Check if client has enough credits
                    checkClientCredits();
                } else {
                    $('#client-credits-info').hide();
                }
            });
        } else {
            $('#client-credits-info').hide();
        }
    });
    
    function checkClientCredits() {
        var clientId = $('#client_id').val();
        if (!clientId) {
            $('#client-credit-warning').hide();
            return;
        }
        
        var taskCredits = $('#task_type option:selected').data('credits') || 0;
        var clientCredits = parseInt($('#client-credit-balance').text().replace(/,/g, '')) || 0;
        
        if (taskCredits > clientCredits) {
            $('#client-credit-warning').show();
        } else {
            $('#client-credit-warning').hide();
        }
    }
    
    // Form submission
    $('#ryvr-new-task-form').on('submit', function(e) {
        e.preventDefault();
        
        var taskType = $('#task_type').val();
        if (!taskType) {
            alert('<?php echo esc_js( __( 'Please select a task type.', 'ryvr-ai' ) ); ?>');
            return;
        }
        
        console.log('Submitting task with type:', taskType);
        
        // Get form data
        var formData = $(this).serialize();
        formData += '&action=ryvr_create_task';
        
        console.log('Form data:', formData);
        
        // Disable submit button
        $('#create-task-button').prop('disabled', true).text('<?php echo esc_js( __( 'Creating Task...', 'ryvr-ai' ) ); ?>');
        
        // Clear any previous errors
        $('.ryvr-form-error').remove();
        
        // Submit task
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                console.log('Server response:', response);
                
                if (response.success) {
                    // Show success message
                    $('#ryvr-new-task-form').hide();
                    $('#task-response').show();
                    $('.ryvr-task-response-content').html('<p><?php echo esc_js( __( 'Your task has been created successfully!', 'ryvr-ai' ) ); ?></p>');
                    
                    // Update view task button
                    $('#view-task-button').attr('href', '<?php echo esc_url( admin_url( 'admin.php?page=ryvr-ai-tasks&task=' ) ); ?>' + response.data.task_id);
                } else {
                    // Show error message
                    console.error('Task creation failed:', response);
                    var errorMsg = response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'An error occurred while creating the task.', 'ryvr-ai' ) ); ?>';
                    
                    alert(errorMsg);
                    $('.ryvr-form-submit').prepend('<div class="ryvr-form-error notice notice-error"><p>' + errorMsg + '</p></div>');
                    $('#create-task-button').prop('disabled', false).text('<?php echo esc_js( __( 'Create Task', 'ryvr-ai' ) ); ?>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.log('Response text:', xhr.responseText);
                
                // Try to extract error message from response
                var errorMsg = '<?php echo esc_js( __( 'An error occurred while communicating with the server.', 'ryvr-ai' ) ); ?>';
                
                try {
                    if (xhr.responseText) {
                        var responseData = JSON.parse(xhr.responseText);
                        if (responseData.message) {
                            errorMsg += ' ' + responseData.message;
                        }
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    // If the response contains text, display the first 100 characters
                    if (xhr.responseText && xhr.responseText.length > 0) {
                        errorMsg += ' Server said: ' + xhr.responseText.substring(0, 100) + (xhr.responseText.length > 100 ? '...' : '');
                    }
                }
                
                alert(errorMsg);
                $('.ryvr-form-submit').prepend('<div class="ryvr-form-error notice notice-error"><p>' + errorMsg + '</p></div>');
                $('#create-task-button').prop('disabled', false).text('<?php echo esc_js( __( 'Create Task', 'ryvr-ai' ) ); ?>');
            }
        });
    });
});
</script> 