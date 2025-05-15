<?php
/**
 * API Integration Demo Page
 *
 * This file demonstrates how to use the OpenAI and DataForSEO API services.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Admin/Partials
 */

// Don't allow direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check if test mode is enabled
$sandbox_mode = isset( $_GET['sandbox'] ) ? (bool) $_GET['sandbox'] : false;

// Get the database manager
$db_manager = new Ryvr\Database\Database_Manager();

// Create API cache instance
$api_cache = new Ryvr\API\API_Cache();

// Get API credentials
$current_user_id = get_current_user_id();

// Create service instances
$openai_service = new Ryvr\API\OpenAI_Service(
    $db_manager,
    $api_cache,
    $current_user_id
);

$dataforseo_service = new Ryvr\API\DataForSEO_Service(
    $db_manager,
    $api_cache,
    $current_user_id
);

// Set sandbox mode if requested
if ( $sandbox_mode ) {
    $openai_service->set_sandbox_mode( true );
    $dataforseo_service->set_sandbox_mode( true );
}

// Handle form submissions
$openai_result = null;
$dataforseo_result = null;
$error_message = null;

if ( isset( $_POST['openai_submit'] ) && isset( $_POST['openai_prompt'] ) ) {
    // Process OpenAI request
    $prompt = sanitize_textarea_field( $_POST['openai_prompt'] );
    $model = isset( $_POST['openai_model'] ) ? sanitize_text_field( $_POST['openai_model'] ) : 'gpt-3.5-turbo';
    
    if ( !empty( $prompt ) ) {
        // Make OpenAI request
        $params = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt,
                ),
            ),
            'temperature' => 0.7,
            'max_tokens' => 500,
        );
        
        $openai_result = $openai_service->create_chat_completion( $params );
    } else {
        $error_message = 'Please enter a prompt for OpenAI.';
    }
} elseif ( isset( $_POST['dataforseo_submit'] ) && isset( $_POST['dataforseo_keyword'] ) ) {
    // Process DataForSEO request
    $keyword = sanitize_text_field( $_POST['dataforseo_keyword'] );
    $location = isset( $_POST['dataforseo_location'] ) ? sanitize_text_field( $_POST['dataforseo_location'] ) : '2840';
    
    if ( !empty( $keyword ) ) {
        // Make DataForSEO request
        $params = array(
            'keyword' => $keyword,
            'location_code' => (int) $location,
            'language_code' => 'en',
        );
        
        $dataforseo_result = $dataforseo_service->get_google_serp_organic( $params );
    } else {
        $error_message = 'Please enter a keyword for DataForSEO.';
    }
}

// Get cache statistics
$cache_stats = $api_cache->get_cache_stats();

?>

<div class="wrap">
    <h1>API Integration Demo</h1>
    
    <?php if ( $error_message ) : ?>
        <div class="notice notice-error">
            <p><?php echo esc_html( $error_message ); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="sandbox-toggle">
        <h2>API Mode</h2>
        <p>
            <a href="<?php echo esc_url( add_query_arg( 'sandbox', '1' ) ); ?>" class="button <?php echo $sandbox_mode ? 'button-primary' : ''; ?>">Sandbox Mode</a>
            <a href="<?php echo esc_url( remove_query_arg( 'sandbox' ) ); ?>" class="button <?php echo !$sandbox_mode ? 'button-primary' : ''; ?>">Live Mode</a>
        </p>
        <p class="description">
            <?php if ( $sandbox_mode ) : ?>
                <strong>Sandbox mode is ON.</strong> No real API calls will be made and no credits will be used.
            <?php else : ?>
                <strong>Live mode is ON.</strong> Real API calls will be made and credits will be used.
            <?php endif; ?>
        </p>
    </div>
    
    <div class="api-demo-container" style="display: flex; flex-wrap: wrap; margin: 0 -10px;">
        <!-- OpenAI Demo -->
        <div class="api-demo-section" style="flex: 1 1 45%; min-width: 300px; margin: 10px; padding: 20px; background: #fff; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2>OpenAI API Demo</h2>
            
            <form method="post" action="">
                <div class="form-group">
                    <label for="openai_model"><strong>Model:</strong></label>
                    <select name="openai_model" id="openai_model">
                        <option value="gpt-3.5-turbo" selected>GPT-3.5 Turbo</option>
                        <option value="gpt-4">GPT-4</option>
                        <option value="gpt-4-turbo">GPT-4 Turbo</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin: 15px 0;">
                    <label for="openai_prompt"><strong>Prompt:</strong></label>
                    <textarea name="openai_prompt" id="openai_prompt" rows="5" style="width: 100%;" placeholder="Enter your prompt here..."><?php echo isset( $_POST['openai_prompt'] ) ? esc_textarea( $_POST['openai_prompt'] ) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <input type="submit" name="openai_submit" class="button button-primary" value="Generate Response">
                </div>
            </form>
            
            <?php if ( $openai_result ) : ?>
                <div class="api-result" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                    <h3>Result:</h3>
                    
                    <?php if ( isset( $openai_result['success'] ) && !$openai_result['success'] ) : ?>
                        <div class="notice notice-error">
                            <p>Error: <?php echo esc_html( $openai_result['error']['message'] ); ?></p>
                        </div>
                    <?php else : ?>
                        <?php if ( isset( $openai_result['data'] ) && isset( $openai_result['data']['choices'][0]['message']['content'] ) ) : ?>
                            <div class="response-content" style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                                <?php echo nl2br( esc_html( $openai_result['data']['choices'][0]['message']['content'] ) ); ?>
                            </div>
                            
                            <?php if ( isset( $openai_result['data']['usage'] ) ) : ?>
                                <div class="usage-info" style="margin-top: 10px; font-size: 0.9em; color: #666;">
                                    <p>
                                        <strong>Tokens Used:</strong> <?php echo esc_html( $openai_result['data']['usage']['total_tokens'] ); ?> 
                                        (Input: <?php echo esc_html( $openai_result['data']['usage']['prompt_tokens'] ); ?>, 
                                        Output: <?php echo esc_html( $openai_result['data']['usage']['completion_tokens'] ); ?>)
                                    </p>
                                </div>
                            <?php endif; ?>
                        <?php elseif ( isset( $openai_result['sandbox'] ) ) : ?>
                            <div class="response-content" style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                                <p><strong>Sandbox Response:</strong></p>
                                <pre><?php print_r( $openai_result ); ?></pre>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- DataForSEO Demo -->
        <div class="api-demo-section" style="flex: 1 1 45%; min-width: 300px; margin: 10px; padding: 20px; background: #fff; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2>DataForSEO API Demo</h2>
            
            <form method="post" action="">
                <div class="form-group">
                    <label for="dataforseo_location"><strong>Location:</strong></label>
                    <select name="dataforseo_location" id="dataforseo_location">
                        <option value="2840" selected>United States</option>
                        <option value="2826">United Kingdom</option>
                        <option value="2124">Canada</option>
                        <option value="2036">Australia</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin: 15px 0;">
                    <label for="dataforseo_keyword"><strong>Keyword:</strong></label>
                    <input type="text" name="dataforseo_keyword" id="dataforseo_keyword" style="width: 100%;" placeholder="Enter your keyword here..." value="<?php echo isset( $_POST['dataforseo_keyword'] ) ? esc_attr( $_POST['dataforseo_keyword'] ) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <input type="submit" name="dataforseo_submit" class="button button-primary" value="Search Results">
                </div>
            </form>
            
            <?php if ( $dataforseo_result ) : ?>
                <div class="api-result" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                    <h3>Result:</h3>
                    
                    <?php if ( isset( $dataforseo_result['success'] ) && !$dataforseo_result['success'] ) : ?>
                        <div class="notice notice-error">
                            <p>Error: <?php echo esc_html( $dataforseo_result['error']['message'] ); ?></p>
                        </div>
                    <?php else : ?>
                        <?php if ( isset( $dataforseo_result['data']['tasks'][0]['result'] ) ) : ?>
                            <div class="response-content" style="background: #f9f9f9; padding: 15px; border-radius: 5px; max-height: 400px; overflow-y: auto;">
                                <h4>Top 5 Results for "<?php echo esc_html( $_POST['dataforseo_keyword'] ); ?>":</h4>
                                <?php 
                                $results = $dataforseo_result['data']['tasks'][0]['result'];
                                $count = 0;
                                foreach ( $results as $result ) {
                                    if ( ++$count > 5 ) break;
                                    ?>
                                    <div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                                        <h5 style="margin-bottom: 5px;"><a href="<?php echo esc_url( $result['url'] ); ?>" target="_blank"><?php echo esc_html( $result['title'] ); ?></a></h5>
                                        <p style="color: #080; margin: 0; font-size: 0.9em;"><?php echo esc_url( $result['url'] ); ?></p>
                                        <p style="margin: 5px 0;"><?php echo esc_html( $result['description'] ); ?></p>
                                    </div>
                                    <?php
                                }
                                ?>
                            </div>
                            
                            <div class="usage-info" style="margin-top: 10px; font-size: 0.9em; color: #666;">
                                <p><strong>Total Results:</strong> <?php echo esc_html( count( $results ) ); ?></p>
                                <?php if ( isset( $dataforseo_result['data']['cost'] ) ) : ?>
                                    <p><strong>Cost:</strong> <?php echo esc_html( $dataforseo_result['data']['cost'] ); ?> DFS credits</p>
                                <?php endif; ?>
                            </div>
                        <?php elseif ( isset( $dataforseo_result['sandbox'] ) ) : ?>
                            <div class="response-content" style="background: #f9f9f9; padding: 15px; border-radius: 5px;">
                                <p><strong>Sandbox Response:</strong></p>
                                <pre><?php print_r( $dataforseo_result ); ?></pre>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Cache Statistics -->
    <?php if ( !empty( $cache_stats ) && $cache_stats['total_cached_items'] > 0 ) : ?>
        <div class="cache-stats" style="margin-top: 30px; padding: 20px; background: #fff; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2>API Cache Statistics</h2>
            <p><strong>Total Cached Items:</strong> <?php echo esc_html( $cache_stats['total_cached_items'] ); ?></p>
            
            <?php if ( !empty( $cache_stats['by_service'] ) ) : ?>
                <h3>By Service</h3>
                <ul>
                <?php foreach ( $cache_stats['by_service'] as $service => $count ) : ?>
                    <li><strong><?php echo esc_html( $service ); ?>:</strong> <?php echo esc_html( $count ); ?> cached items</li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <?php if ( !empty( $cache_stats['by_endpoint'] ) ) : ?>
                <h3>By Endpoint</h3>
                <ul>
                <?php foreach ( $cache_stats['by_endpoint'] as $endpoint => $count ) : ?>
                    <li><strong><?php echo esc_html( $endpoint ); ?>:</strong> <?php echo esc_html( $count ); ?> cached items</li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <div style="margin-top: 15px;">
                <form method="post" action="">
                    <?php wp_nonce_field( 'clear_api_cache', 'clear_cache_nonce' ); ?>
                    <input type="submit" name="clear_cache" class="button button-secondary" value="Clear All Cache">
                </form>
            </div>
        </div>
    <?php endif; ?>
</div> 