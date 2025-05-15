<?php
/**
 * The Content Generation Processor class.
 *
 * Handles processing of content generation tasks.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Task_Engine/Processors
 */

namespace Ryvr\Task_Engine\Processors;

use Ryvr\Task_Engine\Task_Processor;

/**
 * The Content Generation Processor class.
 *
 * This class processes content generation tasks using OpenAI API.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Task_Engine/Processors
 */
class Content_Generation_Processor extends Task_Processor {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct();
        $this->task_type = 'content_generation';
    }

    /**
     * Process a content generation task.
     *
     * @param object $task Task object.
     * @return array|WP_Error Task outputs or error.
     */
    public function process( $task ) {
        // Log the start of processing.
        $this->log( $task->id, __( 'Starting content generation task...', 'ryvr-ai' ) );

        // Validate inputs.
        $inputs = $task->inputs;
        $validation = $this->validate_inputs( $inputs );
        
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // Get API service.
        $openai = $this->get_api_service( 'openai' );
        
        if ( ! $openai ) {
            return $this->create_error( 
                'api_service_unavailable', 
                __( 'OpenAI API service is not available.', 'ryvr-ai' ) 
            );
        }

        // Extract task parameters.
        $content_type = isset( $inputs['content_type'] ) ? $inputs['content_type'] : 'blog_post';
        $topic = isset( $inputs['topic'] ) ? $inputs['topic'] : '';
        $keywords = isset( $inputs['keywords'] ) ? $inputs['keywords'] : [];
        $tone = isset( $inputs['tone'] ) ? $inputs['tone'] : 'professional';
        $outline = isset( $inputs['outline'] ) ? $inputs['outline'] : '';
        $word_count = isset( $inputs['word_count'] ) ? $inputs['word_count'] : 800;
        
        // Get default model from settings.
        $model = get_option( 'ryvr_openai_model', 'gpt-3.5-turbo' );

        // Initialize outputs.
        $outputs = [
            'content' => '',
            'title' => '',
            'meta_description' => '',
            'stats' => [
                'word_count' => 0,
                'character_count' => 0,
                'reading_time' => 0,
            ],
        ];

        try {
            // Generate prompt based on content type and inputs.
            $prompt = $this->build_prompt( $content_type, $topic, $keywords, $tone, $outline, $word_count );
            
            // Log prompt generation.
            $this->log( $task->id, __( 'Prompt generated for content creation', 'ryvr-ai' ) );
            
            // Build messages for chat completion.
            $messages = [
                [
                    'role' => 'system',
                    'content' => "You are an expert content creator specializing in {$content_type} writing with a {$tone} tone. Create high-quality, engaging content that incorporates the keywords provided naturally.",
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ];
            
            // Set OpenAI options.
            $options = [
                'model' => $model,
                'max_tokens' => $this->calculate_max_tokens( $word_count ),
                'temperature' => $this->get_temperature_for_tone( $tone ),
            ];
            
            // Log API call.
            $this->log( $task->id, sprintf( __( 'Making OpenAI request for %s about %s', 'ryvr-ai' ), $content_type, $topic ) );
            
            // Make the API request.
            $response = $openai->generate_chat_completion( $messages, $options );
            
            if ( is_wp_error( $response ) ) {
                return $this->create_error( 
                    'api_error', 
                    sprintf( __( 'OpenAI API error: %s', 'ryvr-ai' ), $response->get_error_message() ),
                    $response->get_error_data()
                );
            }
            
            // Process the response.
            if ( isset( $response['choices'][0]['message']['content'] ) ) {
                $content = $response['choices'][0]['message']['content'];
                
                // Extract title if present.
                preg_match( '/^#\s+(.+?)(?:\n|$)/m', $content, $title_matches );
                $title = isset( $title_matches[1] ) ? $title_matches[1] : '';
                
                // If no title was found in the content, generate one.
                if ( empty( $title ) ) {
                    $this->log( $task->id, __( 'Generating title for content', 'ryvr-ai' ) );
                    
                    $title_prompt = "Generate a compelling title for the following content that includes some of these keywords if possible: " . implode(', ', $keywords) . ".\n\nContent:\n" . substr( $content, 0, 500 ) . "...";
                    
                    $title_messages = [
                        [
                            'role' => 'system',
                            'content' => "You are an expert at creating engaging titles for {$content_type} content.",
                        ],
                        [
                            'role' => 'user',
                            'content' => $title_prompt,
                        ],
                    ];
                    
                    $title_response = $openai->generate_chat_completion( $title_messages, [
                        'model' => $model,
                        'max_tokens' => 50,
                        'temperature' => 0.7,
                    ]);
                    
                    if ( ! is_wp_error( $title_response ) && isset( $title_response['choices'][0]['message']['content'] ) ) {
                        $title = trim( $title_response['choices'][0]['message']['content'] );
                        // Remove quotes if present.
                        $title = trim( $title, '"\'');
                    }
                }
                
                // Generate meta description.
                $this->log( $task->id, __( 'Generating meta description', 'ryvr-ai' ) );
                
                $meta_prompt = "Generate a compelling meta description (about 150-160 characters) for SEO purposes for the following content. Include primary keywords if possible:\n\nTitle: {$title}\n\nContent:\n" . substr( $content, 0, 500 ) . "...";
                
                $meta_messages = [
                    [
                        'role' => 'system',
                        'content' => "You are an SEO expert specializing in meta descriptions. Create compelling descriptions that encourage clicks while incorporating keywords naturally.",
                    ],
                    [
                        'role' => 'user',
                        'content' => $meta_prompt,
                    ],
                ];
                
                $meta_response = $openai->generate_chat_completion( $meta_messages, [
                    'model' => $model,
                    'max_tokens' => 100,
                    'temperature' => 0.7,
                ]);
                
                $meta_description = '';
                if ( ! is_wp_error( $meta_response ) && isset( $meta_response['choices'][0]['message']['content'] ) ) {
                    $meta_description = trim( $meta_response['choices'][0]['message']['content'] );
                    // Remove quotes if present.
                    $meta_description = trim( $meta_description, '"\'');
                }
                
                // Calculate content stats.
                $word_count = str_word_count( $content );
                $char_count = strlen( $content );
                $reading_time = ceil( $word_count / 225 ); // Average reading speed of 225 words per minute.
                
                // Update outputs.
                $outputs['content'] = $content;
                $outputs['title'] = $title;
                $outputs['meta_description'] = $meta_description;
                $outputs['stats']['word_count'] = $word_count;
                $outputs['stats']['character_count'] = $char_count;
                $outputs['stats']['reading_time'] = $reading_time;
                
                // Log success.
                $this->log( $task->id, sprintf( __( 'Content generation complete. Generated %d words.', 'ryvr-ai' ), $word_count ) );
            } else {
                return $this->create_error( 
                    'invalid_response', 
                    __( 'Invalid response from OpenAI API.', 'ryvr-ai' )
                );
            }

            // Return formatted outputs.
            return $this->format_outputs( $outputs );
            
        } catch ( \Exception $e ) {
            return $this->create_error( 
                'processing_exception', 
                sprintf( __( 'Exception during task processing: %s', 'ryvr-ai' ), $e->getMessage() ),
                [ 'exception' => $e ]
            );
        }
    }

    /**
     * Build a prompt for content generation.
     *
     * @param string $content_type Type of content to generate.
     * @param string $topic Topic of the content.
     * @param array  $keywords Keywords to include.
     * @param string $tone Tone of the content.
     * @param string $outline Outline of the content.
     * @param int    $word_count Target word count.
     * @return string Prompt for OpenAI.
     */
    private function build_prompt( $content_type, $topic, $keywords, $tone, $outline, $word_count ) {
        $prompt = "Create a {$tone}-toned {$content_type} about {$topic}. ";
        
        // Add keyword instructions.
        if ( ! empty( $keywords ) ) {
            $keyword_list = implode( ', ', $keywords );
            $prompt .= "Include the following keywords naturally throughout the content: {$keyword_list}. ";
        }
        
        // Add outline if provided.
        if ( ! empty( $outline ) ) {
            $prompt .= "Follow this outline:\n\n{$outline}\n\n";
        } else {
            switch ( $content_type ) {
                case 'blog_post':
                    $prompt .= "The blog post should include an introduction, several body sections with subheadings, and a conclusion. ";
                    break;
                case 'product_description':
                    $prompt .= "The product description should highlight benefits, features, and include a call-to-action. ";
                    break;
                case 'landing_page':
                    $prompt .= "The landing page content should be persuasive, addressing pain points and highlighting solutions with a strong call-to-action. ";
                    break;
                case 'email':
                    $prompt .= "The email should have a compelling subject line, personalized greeting, valuable body content, and a clear call-to-action. ";
                    break;
                case 'social_media':
                    $prompt .= "The social media post should be engaging, concise, and include relevant hashtags. ";
                    break;
            }
        }
        
        // Add word count instruction.
        $prompt .= "The content should be approximately {$word_count} words. ";
        
        // Add formatting instruction.
        $prompt .= "Format the content using Markdown, with a title, headings, and appropriate formatting for readability. Include internal links where relevant.";
        
        return $prompt;
    }

    /**
     * Calculate max tokens based on word count.
     *
     * @param int $word_count Target word count.
     * @return int Max tokens.
     */
    private function calculate_max_tokens( $word_count ) {
        // A rough estimate: 1 word ≈ 1.3 tokens.
        $token_estimate = $word_count * 1.3;
        
        // Add some buffer for formatting, etc.
        $token_estimate *= 1.2;
        
        // Ensure we don't exceed OpenAI's limits.
        return min( 4000, (int) $token_estimate );
    }

    /**
     * Get temperature setting based on tone.
     *
     * @param string $tone Content tone.
     * @return float Temperature value.
     */
    private function get_temperature_for_tone( $tone ) {
        $temperatures = [
            'professional' => 0.5,
            'conversational' => 0.7,
            'casual' => 0.8,
            'humorous' => 0.9,
            'formal' => 0.4,
            'technical' => 0.3,
        ];
        
        return isset( $temperatures[ $tone ] ) ? $temperatures[ $tone ] : 0.7;
    }

    /**
     * Validate task inputs.
     *
     * @param array $inputs Task inputs.
     * @return true|WP_Error True if valid, WP_Error if invalid.
     */
    public function validate_inputs( $inputs ) {
        // Check if topic is provided.
        if ( empty( $inputs['topic'] ) ) {
            return $this->create_error( 
                'missing_topic', 
                __( 'Topic is required for content generation.', 'ryvr-ai' ) 
            );
        }

        // Validate content type.
        $valid_content_types = [ 'blog_post', 'product_description', 'landing_page', 'email', 'social_media' ];
        if ( isset( $inputs['content_type'] ) && ! in_array( $inputs['content_type'], $valid_content_types, true ) ) {
            return $this->create_error( 
                'invalid_content_type', 
                __( 'Invalid content type.', 'ryvr-ai' ) 
            );
        }

        // Validate tone.
        $valid_tones = [ 'professional', 'conversational', 'casual', 'humorous', 'formal', 'technical' ];
        if ( isset( $inputs['tone'] ) && ! in_array( $inputs['tone'], $valid_tones, true ) ) {
            return $this->create_error( 
                'invalid_tone', 
                __( 'Invalid tone.', 'ryvr-ai' ) 
            );
        }

        // Validate word count.
        if ( isset( $inputs['word_count'] ) ) {
            $word_count = (int) $inputs['word_count'];
            if ( $word_count < 100 || $word_count > 3000 ) {
                return $this->create_error( 
                    'invalid_word_count', 
                    __( 'Word count must be between 100 and 3000.', 'ryvr-ai' ) 
                );
            }
        }

        return true;
    }
} 