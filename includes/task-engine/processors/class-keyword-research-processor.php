<?php
/**
 * The Keyword Research Processor class.
 *
 * Handles processing of keyword research tasks.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Task_Engine/Processors
 */

namespace Ryvr\Task_Engine\Processors;

use Ryvr\Task_Engine\Task_Processor;

/**
 * The Keyword Research Processor class.
 *
 * This class processes keyword research tasks using DataForSEO API.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Task_Engine/Processors
 */
class Keyword_Research_Processor extends Task_Processor {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct();
        $this->task_type = 'keyword_research';
    }

    /**
     * Process a keyword research task.
     *
     * @param object $task Task object.
     * @return array|WP_Error Task outputs or error.
     */
    public function process( $task ) {
        // Log the start of processing.
        $this->log( $task->id, __( 'Starting keyword research task...', 'ryvr-ai' ) );

        // Validate inputs.
        $inputs = $task->inputs;
        $validation = $this->validate_inputs( $inputs );
        
        if ( is_wp_error( $validation ) ) {
            $this->log( $task->id, sprintf( __( 'Input validation failed: %s', 'ryvr-ai' ), $validation->get_error_message() ), 'error' );
            return $validation;
        }

        // Get API service.
        $dataforseo = $this->get_api_service( 'dataforseo' );
        
        if ( ! $dataforseo ) {
            $this->log( $task->id, __( 'DataForSEO API service is not available.', 'ryvr-ai' ), 'error' );
            return $this->create_error( 
                'api_service_unavailable', 
                __( 'DataForSEO API service is not available.', 'ryvr-ai' ) 
            );
        }

        // Extract task parameters.
        $seed_keyword = isset( $inputs['seed_keyword'] ) ? $inputs['seed_keyword'] : '';
        $location = isset( $inputs['location'] ) ? $inputs['location'] : 2840; // Default to US
        $language = isset( $inputs['language'] ) ? $inputs['language'] : 'en';
        $limit = isset( $inputs['limit'] ) ? $inputs['limit'] : 100;

        // Log parameters for debugging
        $this->log( $task->id, sprintf( __( 'Parameters: keyword=%s, location=%s, language=%s, limit=%d', 'ryvr-ai' ), 
            $seed_keyword, $location, $language, $limit ), 'debug' );

        // Initialize outputs.
        $outputs = [
            'keywords' => [],
            'stats' => [
                'total_keywords' => 0,
                'average_volume' => 0,
                'average_cpc' => 0,
                'average_competition' => 0,
            ],
            'suggestions' => [
                'high_volume' => [],
                'low_competition' => [],
                'high_cpc' => [],
            ],
        ];

        try {
            // Log API call.
            $this->log( $task->id, sprintf( __( 'Fetching keyword data for: %s', 'ryvr-ai' ), $seed_keyword ) );

            // Get keyword suggestions.
            $options = [
                'location_code' => $location,
                'language_code' => $language,
                'limit' => $limit,
            ];

            // Check if API credentials are configured
            if (!$dataforseo->is_configured()) {
                $this->log( $task->id, __( 'DataForSEO API credentials are not configured.', 'ryvr-ai' ), 'error' );
                return $this->create_error(
                    'api_credentials_missing',
                    __( 'DataForSEO API credentials are not configured.', 'ryvr-ai' )
                );
            }

            // Log that we're about to make the API call
            $this->log( $task->id, __( 'Making API call to DataForSEO...', 'ryvr-ai' ), 'debug' );

            // Make API call to get keyword data.
            $response = $dataforseo->keyword_suggestions( $seed_keyword, $options );

            if ( is_wp_error( $response ) ) {
                $error_message = $response->get_error_message();
                $error_data = $response->get_error_data();
                
                // Log detailed error
                $this->log( $task->id, sprintf( __( 'DataForSEO API error: %s', 'ryvr-ai' ), $error_message ), 'error' );
                if ($error_data) {
                    $this->log( $task->id, sprintf( __( 'Error details: %s', 'ryvr-ai' ), 
                        is_array($error_data) ? json_encode($error_data) : $error_data ), 'error' );
                }
                
                return $this->create_error( 
                    'api_error', 
                    sprintf( __( 'DataForSEO API error: %s', 'ryvr-ai' ), $error_message ),
                    $error_data
                );
            }

            // Log success response for debugging
            $this->log( $task->id, __( 'Received response from DataForSEO API', 'ryvr-ai' ), 'debug' );
            
            // Debug: Log the structure of the response
            if (isset($response['tasks'])) {
                $this->log( $task->id, sprintf( __( 'Response contains %d tasks', 'ryvr-ai' ), count($response['tasks']) ), 'debug' );
            } else {
                $this->log( $task->id, __( 'Response does not contain tasks array', 'ryvr-ai' ), 'warning' );
                $this->log( $task->id, sprintf( __( 'Response structure: %s', 'ryvr-ai' ), json_encode(array_keys($response)) ), 'debug' );
            }

            // Process task_post response to get task ID.
            if ( empty( $response['tasks'] ) || ! isset( $response['tasks'][0]['id'] ) ) {
                return $this->create_error( 
                    'task_creation_failed', 
                    __( 'Failed to create DataForSEO task.', 'ryvr-ai' ) 
                );
            }

            $task_id = $response['tasks'][0]['id'];
            $this->log( $task->id, sprintf( __( 'DataForSEO task created with ID: %s', 'ryvr-ai' ), $task_id ) );
            
            // Wait for task to complete (could implement polling here, but using a simple delay for now).
            sleep(5);
            
            // Get task results.
            $result_response = $dataforseo->check_task_status( $task_id );
            
            if ( is_wp_error( $result_response ) ) {
                return $this->create_error( 
                    'task_status_check_failed', 
                    sprintf( __( 'Failed to check task status: %s', 'ryvr-ai' ), $result_response->get_error_message() ),
                    $result_response->get_error_data()
                );
            }
            
            // If task is ready, get results.
            if ( isset( $result_response['tasks'][0]['status'] ) && $result_response['tasks'][0]['status'] === 'ok' ) {
                $task_result = $dataforseo->keyword_search_volume( [$seed_keyword], $options );
                
                if ( is_wp_error( $task_result ) ) {
                    return $this->create_error( 
                        'task_result_failed', 
                        sprintf( __( 'Failed to get task results: %s', 'ryvr-ai' ), $task_result->get_error_message() ),
                        $task_result->get_error_data()
                    );
                }
                
                // Process the results.
                if ( isset( $task_result['tasks'][0]['result'] ) ) {
                    $results = $task_result['tasks'][0]['result'];
                    
                    // Process related keywords.
                    if ( !empty( $results ) ) {
                        $this->log( $task->id, sprintf( __( 'Processing %d keywords', 'ryvr-ai' ), count( $results ) ) );
                        
                        $total_volume = 0;
                        $total_cpc = 0;
                        $total_competition = 0;
                        $keyword_count = 0;
                        
                        foreach ( $results as $result ) {
                            if ( isset( $result['keyword_data'] ) ) {
                                $keyword_data = $result['keyword_data'];
                                
                                // Add to keywords list.
                                $outputs['keywords'][] = [
                                    'keyword' => $keyword_data['keyword'],
                                    'search_volume' => $keyword_data['search_volume'] ?? 0,
                                    'cpc' => $keyword_data['cpc'] ?? 0,
                                    'competition' => $keyword_data['competition'] ?? 0,
                                ];
                                
                                // Update stats.
                                $keyword_count++;
                                $total_volume += $keyword_data['search_volume'] ?? 0;
                                $total_cpc += $keyword_data['cpc'] ?? 0;
                                $total_competition += $keyword_data['competition'] ?? 0;
                                
                                // Categorize for suggestions.
                                if ( ($keyword_data['search_volume'] ?? 0) > 1000 ) {
                                    $outputs['suggestions']['high_volume'][] = $keyword_data['keyword'];
                                }
                                
                                if ( ($keyword_data['competition'] ?? 0) < 0.3 ) {
                                    $outputs['suggestions']['low_competition'][] = $keyword_data['keyword'];
                                }
                                
                                if ( ($keyword_data['cpc'] ?? 0) > 1.0 ) {
                                    $outputs['suggestions']['high_cpc'][] = $keyword_data['keyword'];
                                }
                            }
                        }
                        
                        // Calculate averages.
                        if ( $keyword_count > 0 ) {
                            $outputs['stats']['total_keywords'] = $keyword_count;
                            $outputs['stats']['average_volume'] = $total_volume / $keyword_count;
                            $outputs['stats']['average_cpc'] = $total_cpc / $keyword_count;
                            $outputs['stats']['average_competition'] = $total_competition / $keyword_count;
                        }
                    }
                }
            } else {
                // Task not ready yet or failed.
                $this->log( $task->id, __( 'DataForSEO task not ready or failed', 'ryvr-ai' ), 'warning' );
                
                // Get domain keywords as fallback.
                $domain_keywords = $dataforseo->domain_keywords( 'google.com', $options );
                
                if ( ! is_wp_error( $domain_keywords ) && isset( $domain_keywords['tasks'][0]['result'] ) ) {
                    $this->log( $task->id, __( 'Using domain keywords as fallback', 'ryvr-ai' ) );
                    
                    $results = $domain_keywords['tasks'][0]['result'];
                    
                    if ( !empty( $results ) ) {
                        foreach ( $results as $result ) {
                            if ( isset( $result['keyword'] ) ) {
                                $outputs['keywords'][] = [
                                    'keyword' => $result['keyword'],
                                    'search_volume' => $result['search_volume'] ?? 0,
                                    'cpc' => $result['cpc'] ?? 0,
                                    'competition' => $result['competition'] ?? 0,
                                ];
                            }
                        }
                        
                        $outputs['stats']['total_keywords'] = count( $outputs['keywords'] );
                    }
                }
            }

            // Log completion.
            $this->log( $task->id, sprintf( __( 'Keyword research complete. Found %d keywords.', 'ryvr-ai' ), count( $outputs['keywords'] ) ) );

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
     * Validate task inputs.
     *
     * @param array $inputs Task inputs.
     * @return true|WP_Error True if valid, WP_Error if invalid.
     */
    public function validate_inputs( $inputs ) {
        // Check if seed keyword is provided.
        if ( empty( $inputs['seed_keyword'] ) ) {
            return $this->create_error( 
                'missing_seed_keyword', 
                __( 'Seed keyword is required.', 'ryvr-ai' ) 
            );
        }

        return true;
    }
} 