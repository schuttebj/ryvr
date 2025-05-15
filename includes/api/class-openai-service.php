<?php
/**
 * OpenAI API Service.
 *
 * Handles communication with the OpenAI API.
 *
 * @package    Ryvr
 * @subpackage Ryvr/API
 */

namespace Ryvr\API;

use Ryvr\Database\Database_Manager;

/**
 * OpenAI API Service Class.
 *
 * Implementation of the API_Service for OpenAI API integration.
 * 
 * @link https://platform.openai.com/docs/api-reference
 *
 * @package    Ryvr
 * @subpackage Ryvr/API
 */
class OpenAI_Service extends API_Service {

    /**
     * The API base URL.
     *
     * @var string
     */
    protected $api_base_url = 'https://api.openai.com/v1';

    /**
     * Available models.
     *
     * @var array
     */
    protected $models = array(
        'gpt-4-turbo' => array(
            'input_cost_per_1k' => 10,
            'output_cost_per_1k' => 30,
            'context_window' => 128000,
        ),
        'gpt-4' => array(
            'input_cost_per_1k' => 30,
            'output_cost_per_1k' => 60,
            'context_window' => 8192,
        ),
        'gpt-3.5-turbo' => array(
            'input_cost_per_1k' => 1,
            'output_cost_per_1k' => 2,
            'context_window' => 16385,
        ),
        'dall-e-3' => array(
            'cost_per_image' => array(
                '1024x1024' => 40,
                '1792x1024' => 80,
                '1024x1792' => 80,
            ),
        ),
        'tts-1' => array(
            'cost_per_1k' => 15,
        ),
        'tts-1-hd' => array(
            'cost_per_1k' => 30,
        ),
        'whisper-1' => array(
            'cost_per_minute' => 10,
        ),
    );

    /**
     * Constructor.
     *
     * @param Database_Manager $db_manager Database manager instance.
     * @param API_Cache        $cache      API Cache instance.
     * @param int              $user_id    User ID.
     * @param string           $api_key    API key.
     * @param string           $api_secret API secret (not used for OpenAI).
     */
    public function __construct( Database_Manager $db_manager, API_Cache $cache, $user_id = null, $api_key = null, $api_secret = null ) {
        $this->service_name = 'openai';
        parent::__construct( $db_manager, $cache, $user_id, $api_key, $api_secret );
    }

    /**
     * Generate a completion using GPT models.
     *
     * @param array $params Request parameters.
     * @return array Response data.
     */
    public function create_chat_completion( $params ) {
        // Set defaults if not provided
        $params = wp_parse_args( $params, array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(),
            'temperature' => 0.7,
            'max_tokens' => 500,
        ) );
        
        // Check if enough credits before making the request
        $estimated_cost = $this->estimate_chat_completion_cost( $params );
        if ( !$this->sandbox_mode && !$this->check_credits( $estimated_cost ) ) {
            return array(
                'success' => false,
                'error' => array(
                    'code' => 'insufficient_credits',
                    'message' => 'Not enough credits to perform this operation.'
                )
            );
        }
        
        // Make the request (don't use cache for chat completions)
        return $this->make_request( 'chat/completions', $params, 'POST' );
    }

    /**
     * Generate an image using DALL-E.
     *
     * @param array $params Request parameters.
     * @return array Response data.
     */
    public function create_image( $params ) {
        // Set defaults if not provided
        $params = wp_parse_args( $params, array(
            'model' => 'dall-e-3',
            'prompt' => '',
            'n' => 1,
            'size' => '1024x1024',
            'response_format' => 'url',
        ) );
        
        // Check if enough credits before making the request
        $estimated_cost = $this->estimate_image_cost( $params );
        if ( !$this->sandbox_mode && !$this->check_credits( $estimated_cost ) ) {
            return array(
                'success' => false,
                'error' => array(
                    'code' => 'insufficient_credits',
                    'message' => 'Not enough credits to perform this operation.'
                )
            );
        }
        
        // Make the request (don't use cache for image generation)
        return $this->make_request( 'images/generations', $params, 'POST' );
    }

    /**
     * Generate speech from text using TTS.
     *
     * @param array $params Request parameters.
     * @return array Response data.
     */
    public function create_speech( $params ) {
        // Set defaults if not provided
        $params = wp_parse_args( $params, array(
            'model' => 'tts-1',
            'input' => '',
            'voice' => 'alloy',
            'response_format' => 'mp3',
        ) );
        
        // Check if enough credits before making the request
        $estimated_cost = $this->estimate_speech_cost( $params );
        if ( !$this->sandbox_mode && !$this->check_credits( $estimated_cost ) ) {
            return array(
                'success' => false,
                'error' => array(
                    'code' => 'insufficient_credits',
                    'message' => 'Not enough credits to perform this operation.'
                )
            );
        }
        
        // Make the request (don't use cache for speech generation)
        return $this->make_request( 'audio/speech', $params, 'POST' );
    }

    /**
     * Transcribe audio to text using Whisper.
     *
     * @param array $params Request parameters.
     * @return array Response data.
     */
    public function create_transcription( $params ) {
        // Set defaults if not provided
        $params = wp_parse_args( $params, array(
            'model' => 'whisper-1',
            'file' => '',
            'response_format' => 'json',
        ) );
        
        // Check if enough credits before making the request
        $estimated_cost = $this->estimate_transcription_cost( $params );
        if ( !$this->sandbox_mode && !$this->check_credits( $estimated_cost ) ) {
            return array(
                'success' => false,
                'error' => array(
                    'code' => 'insufficient_credits',
                    'message' => 'Not enough credits to perform this operation.'
                )
            );
        }
        
        // Prepare multipart request
        $file_params = array(
            'file' => $params['file']
        );
        unset( $params['file'] );
        
        // Make the request (don't use cache for audio transcription)
        return $this->make_request( 'audio/transcriptions', $params, 'POST', null, $file_params );
    }

    /**
     * Estimate cost for chat completion.
     *
     * @param array $params Request parameters.
     * @return int Estimated cost in credits.
     */
    public function estimate_chat_completion_cost( $params ) {
        $model = isset( $params['model'] ) ? $params['model'] : 'gpt-3.5-turbo';
        
        if ( !isset( $this->models[$model] ) ) {
            return 0;
        }
        
        $model_info = $this->models[$model];
        
        // Estimate input token count from messages
        $input_tokens = 0;
        if ( isset( $params['messages'] ) && is_array( $params['messages'] ) ) {
            foreach ( $params['messages'] as $message ) {
                // Rough token count estimate (1 token ~= 4 chars)
                $content = isset( $message['content'] ) ? $message['content'] : '';
                $input_tokens += ceil( strlen( $content ) / 4 );
            }
        }
        
        // Estimate output token count from max_tokens
        $output_tokens = isset( $params['max_tokens'] ) ? $params['max_tokens'] : 500;
        
        // Calculate cost based on token count and model rates
        $input_cost = ceil( $input_tokens / 1000 ) * $model_info['input_cost_per_1k'];
        $output_cost = ceil( $output_tokens / 1000 ) * $model_info['output_cost_per_1k'];
        
        return $input_cost + $output_cost;
    }

    /**
     * Estimate cost for image generation.
     *
     * @param array $params Request parameters.
     * @return int Estimated cost in credits.
     */
    public function estimate_image_cost( $params ) {
        $model = isset( $params['model'] ) ? $params['model'] : 'dall-e-3';
        $size = isset( $params['size'] ) ? $params['size'] : '1024x1024';
        $quantity = isset( $params['n'] ) ? (int) $params['n'] : 1;
        
        if ( !isset( $this->models[$model] ) || !isset( $this->models[$model]['cost_per_image'][$size] ) ) {
            return 0;
        }
        
        $cost_per_image = $this->models[$model]['cost_per_image'][$size];
        
        return $cost_per_image * $quantity;
    }

    /**
     * Estimate cost for speech generation.
     *
     * @param array $params Request parameters.
     * @return int Estimated cost in credits.
     */
    public function estimate_speech_cost( $params ) {
        $model = isset( $params['model'] ) ? $params['model'] : 'tts-1';
        $input = isset( $params['input'] ) ? $params['input'] : '';
        
        if ( !isset( $this->models[$model] ) ) {
            return 0;
        }
        
        // Calculate characters and convert to 1k units
        $char_count = strlen( $input );
        $units = ceil( $char_count / 1000 );
        
        return $units * $this->models[$model]['cost_per_1k'];
    }

    /**
     * Estimate cost for audio transcription.
     *
     * @param array $params Request parameters.
     * @return int Estimated cost in credits.
     */
    public function estimate_transcription_cost( $params ) {
        $model = isset( $params['model'] ) ? $params['model'] : 'whisper-1';
        
        if ( !isset( $this->models[$model] ) ) {
            return 0;
        }
        
        // Whisper costs are per minute
        // For estimation, assume a 2-minute audio by default
        // In production, this would need to analyze the audio file length
        $minutes = 2;
        
        if ( isset( $params['file'] ) && is_string( $params['file'] ) && file_exists( $params['file'] ) ) {
            // If file is accessible, try to get actual duration
            $minutes = $this->get_audio_duration_minutes( $params['file'] );
        }
        
        return ceil( $minutes ) * $this->models[$model]['cost_per_minute'];
    }

    /**
     * Get audio file duration in minutes.
     *
     * @param string $file_path Path to audio file.
     * @return float Duration in minutes.
     */
    private function get_audio_duration_minutes( $file_path ) {
        // Default to 2 minutes if we can't determine
        $minutes = 2;
        
        // Try to use getID3 if available
        if ( function_exists( 'getid3_lib' ) ) {
            require_once( ABSPATH . 'wp-includes/ID3/getid3.php' );
            $getID3 = new \getID3();
            $file_info = $getID3->analyze( $file_path );
            
            if ( isset( $file_info['playtime_seconds'] ) ) {
                $minutes = $file_info['playtime_seconds'] / 60;
            }
        }
        
        return $minutes;
    }

    /**
     * Execute the actual API request.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param string $method   HTTP method.
     * @param int    $timeout  Request timeout in seconds.
     * @param array  $files    Files to include in the request.
     * @return mixed Response data.
     * @throws \Exception If the request fails.
     */
    protected function execute_request( $endpoint, $params = array(), $method = 'GET', $timeout = 30, $files = array() ) {
        // Check if we have an API key
        if ( empty( $this->api_key ) ) {
            throw new \Exception( 'OpenAI API key not provided.', 401 );
        }
        
        $url = $this->api_base_url . '/' . $endpoint;
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
        );
        
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => $timeout,
        );
        
        // Handle different HTTP methods
        if ( $method === 'GET' ) {
            $url = add_query_arg( $params, $url );
        } else {
            // Special handling for file uploads
            if ( !empty( $files ) ) {
                $boundary = wp_generate_password( 24, false );
                $headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
                
                $payload = '';
                
                // Add regular fields
                foreach ( $params as $name => $value ) {
                    $payload .= '--' . $boundary . "\r\n";
                    $payload .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
                    $payload .= $value . "\r\n";
                }
                
                // Add files
                foreach ( $files as $name => $path ) {
                    if ( !file_exists( $path ) ) {
                        throw new \Exception( 'File not found: ' . $path, 400 );
                    }
                    
                    $file_name = basename( $path );
                    $file_content = file_get_contents( $path );
                    
                    $payload .= '--' . $boundary . "\r\n";
                    $payload .= 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $file_name . '"' . "\r\n";
                    $payload .= 'Content-Type: ' . mime_content_type( $path ) . "\r\n\r\n";
                    $payload .= $file_content . "\r\n";
                }
                
                $payload .= '--' . $boundary . '--';
                
                $args['body'] = $payload;
            } else {
                $args['body'] = wp_json_encode( $params );
            }
        }
        
        // Make the request
        $response = wp_remote_request( $url, $args );
        
        // Check for errors
        if ( is_wp_error( $response ) ) {
            throw new \Exception( $response->get_error_message(), $response->get_error_code() ?: 500 );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        
        // Parse the response
        $data = json_decode( $response_body, true );
        
        // Handle error responses
        if ( $response_code >= 400 ) {
            $error_message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown API error';
            throw new \Exception( $error_message, $response_code );
        }
        
        return $data;
    }

    /**
     * Generate a fake response when in sandbox mode.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param string $method   HTTP method.
     * @return array Mock response data.
     */
    protected function generate_sandbox_response( $endpoint, $params, $method ) {
        $response = array(
            'success' => true,
            'data' => array(),
            'sandbox' => true,
        );
        
        // Generate different responses based on endpoint
        switch ( $endpoint ) {
            case 'chat/completions':
                $response['data'] = $this->generate_sandbox_chat_completion( $params );
                break;
                
            case 'images/generations':
                $response['data'] = $this->generate_sandbox_image( $params );
                break;
                
            case 'audio/speech':
                $response['data'] = $this->generate_sandbox_speech( $params );
                break;
                
            case 'audio/transcriptions':
                $response['data'] = $this->generate_sandbox_transcription( $params );
                break;
                
            default:
                $response['data'] = array(
                    'message' => 'Sandbox mode: No specific mock response for this endpoint.',
                );
        }
        
        return $response;
    }

    /**
     * Generate sandbox chat completion response.
     *
     * @param array $params Request parameters.
     * @return array Mock completion data.
     */
    private function generate_sandbox_chat_completion( $params ) {
        $model = isset( $params['model'] ) ? $params['model'] : 'gpt-3.5-turbo';
        $messages = isset( $params['messages'] ) ? $params['messages'] : array();
        
        // Extract the last user message to generate a response to
        $last_user_message = '';
        foreach ( array_reverse( $messages ) as $message ) {
            if ( isset( $message['role'] ) && $message['role'] === 'user' ) {
                $last_user_message = isset( $message['content'] ) ? $message['content'] : '';
                break;
            }
        }
        
        // Generate a mock response based on the last user message
        $response_content = 'This is a sandbox response. ';
        if ( !empty( $last_user_message ) ) {
            $response_content .= "You asked about: \"" . substr( $last_user_message, 0, 50 ) . "...\" ";
        }
        $response_content .= "In a real environment, you would get an actual response from the OpenAI " . $model . " model.";
        
        return array(
            'id' => 'sandbox-' . uniqid(),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $model,
            'choices' => array(
                array(
                    'index' => 0,
                    'message' => array(
                        'role' => 'assistant',
                        'content' => $response_content,
                    ),
                    'finish_reason' => 'stop',
                ),
            ),
            'usage' => array(
                'prompt_tokens' => 100,
                'completion_tokens' => 150,
                'total_tokens' => 250,
            ),
        );
    }

    /**
     * Generate sandbox image response.
     *
     * @param array $params Request parameters.
     * @return array Mock image data.
     */
    private function generate_sandbox_image( $params ) {
        $size = isset( $params['size'] ) ? $params['size'] : '1024x1024';
        $n = isset( $params['n'] ) ? (int) $params['n'] : 1;
        
        $images = array();
        for ( $i = 0; $i < $n; $i++ ) {
            $images[] = array(
                'url' => 'https://placehold.co/' . str_replace( 'x', '/', $size ) . '?text=Sandbox+Image',
                'b64_json' => null,
            );
        }
        
        return array(
            'created' => time(),
            'data' => $images,
        );
    }

    /**
     * Generate sandbox speech response.
     *
     * @param array $params Request parameters.
     * @return array Mock speech data.
     */
    private function generate_sandbox_speech( $params ) {
        return array(
            'content_type' => 'audio/mpeg',
            'b64_json' => 'SGVsbG8sIHRoaXMgaXMgYSBzYW5kYm94IHJlc3BvbnNlLg==', // Base64 placeholder
        );
    }

    /**
     * Generate sandbox transcription response.
     *
     * @param array $params Request parameters.
     * @return array Mock transcription data.
     */
    private function generate_sandbox_transcription( $params ) {
        return array(
            'text' => 'This is a sandbox transcription. In a real environment, this would be the transcribed text from the audio file.',
        );
    }

    /**
     * Calculate credits used for an API call.
     *
     * @param string $endpoint API endpoint.
     * @param array  $params   Request parameters.
     * @param array  $response Response data.
     * @return int Credits used.
     */
    protected function calculate_credits_used( $endpoint, $params, $response ) {
        if ( isset( $response['error'] ) ) {
            // Don't charge for errors
            return 0;
        }
        
        // Calculate credits based on endpoint
        switch ( $endpoint ) {
            case 'chat/completions':
                return $this->calculate_chat_completion_credits( $params, $response );
                
            case 'images/generations':
                return $this->calculate_image_credits( $params, $response );
                
            case 'audio/speech':
                return $this->calculate_speech_credits( $params, $response );
                
            case 'audio/transcriptions':
                return $this->calculate_transcription_credits( $params, $response );
                
            default:
                return 0;
        }
    }

    /**
     * Calculate credits used for chat completion.
     *
     * @param array $params   Request parameters.
     * @param array $response Response data.
     * @return int Credits used.
     */
    private function calculate_chat_completion_credits( $params, $response ) {
        if ( !isset( $response['data']['usage'] ) || !isset( $response['data']['model'] ) ) {
            return 0;
        }
        
        $model = $response['data']['model'];
        $usage = $response['data']['usage'];
        
        if ( !isset( $this->models[$model] ) ) {
            return 0;
        }
        
        $model_info = $this->models[$model];
        
        $input_tokens = isset( $usage['prompt_tokens'] ) ? $usage['prompt_tokens'] : 0;
        $output_tokens = isset( $usage['completion_tokens'] ) ? $usage['completion_tokens'] : 0;
        
        $input_cost = ceil( $input_tokens / 1000 ) * $model_info['input_cost_per_1k'];
        $output_cost = ceil( $output_tokens / 1000 ) * $model_info['output_cost_per_1k'];
        
        return $input_cost + $output_cost;
    }

    /**
     * Calculate credits used for image generation.
     *
     * @param array $params   Request parameters.
     * @param array $response Response data.
     * @return int Credits used.
     */
    private function calculate_image_credits( $params, $response ) {
        $model = isset( $params['model'] ) ? $params['model'] : 'dall-e-3';
        $size = isset( $params['size'] ) ? $params['size'] : '1024x1024';
        
        if ( !isset( $this->models[$model] ) || !isset( $this->models[$model]['cost_per_image'][$size] ) ) {
            return 0;
        }
        
        $image_count = 0;
        if ( isset( $response['data']['data'] ) && is_array( $response['data']['data'] ) ) {
            $image_count = count( $response['data']['data'] );
        }
        
        return $this->models[$model]['cost_per_image'][$size] * max( 1, $image_count );
    }

    /**
     * Calculate credits used for speech generation.
     *
     * @param array $params   Request parameters.
     * @param array $response Response data.
     * @return int Credits used.
     */
    private function calculate_speech_credits( $params, $response ) {
        $model = isset( $params['model'] ) ? $params['model'] : 'tts-1';
        $input = isset( $params['input'] ) ? $params['input'] : '';
        
        if ( !isset( $this->models[$model] ) ) {
            return 0;
        }
        
        $char_count = strlen( $input );
        $units = ceil( $char_count / 1000 );
        
        return $units * $this->models[$model]['cost_per_1k'];
    }

    /**
     * Calculate credits used for audio transcription.
     *
     * @param array $params   Request parameters.
     * @param array $response Response data.
     * @return int Credits used.
     */
    private function calculate_transcription_credits( $params, $response ) {
        $model = isset( $params['model'] ) ? $params['model'] : 'whisper-1';
        
        if ( !isset( $this->models[$model] ) ) {
            return 0;
        }
        
        // For now, use a fixed 2-minute value or estimate from file
        $minutes = 2;
        
        if ( isset( $params['file'] ) && is_string( $params['file'] ) && file_exists( $params['file'] ) ) {
            $minutes = $this->get_audio_duration_minutes( $params['file'] );
        }
        
        return ceil( $minutes ) * $this->models[$model]['cost_per_minute'];
    }

    /**
     * Get available models.
     *
     * @return array Available models with pricing info.
     */
    public function get_available_models() {
        return $this->models;
    }
} 