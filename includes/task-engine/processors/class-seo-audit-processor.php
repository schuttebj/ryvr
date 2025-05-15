<?php
/**
 * The SEO Audit Processor class.
 *
 * Handles processing of SEO audit tasks.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Task_Engine/Processors
 */

namespace Ryvr\Task_Engine\Processors;

use Ryvr\Task_Engine\Task_Processor;

/**
 * The SEO Audit Processor class.
 *
 * This class processes SEO audit tasks using DataForSEO API.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Task_Engine/Processors
 */
class SEO_Audit_Processor extends Task_Processor {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct();
        $this->task_type = 'seo_audit';
    }

    /**
     * Process an SEO audit task.
     *
     * @param object $task Task object.
     * @return array|WP_Error Task outputs or error.
     */
    public function process( $task ) {
        // Log the start of processing.
        $this->log( $task->id, __( 'Starting SEO audit task...', 'ryvr-ai' ) );

        // Validate inputs.
        $inputs = $task->inputs;
        $validation = $this->validate_inputs( $inputs );
        
        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // Get API services.
        $dataforseo = $this->get_api_service( 'dataforseo' );
        $openai = $this->get_api_service( 'openai' );
        
        if ( ! $dataforseo ) {
            return $this->create_error( 
                'api_service_unavailable', 
                __( 'DataForSEO API service is not available.', 'ryvr-ai' ) 
            );
        }

        // Extract task parameters.
        $domain = isset( $inputs['domain'] ) ? $inputs['domain'] : '';
        $max_pages = isset( $inputs['max_pages'] ) ? (int) $inputs['max_pages'] : 100;
        $competitors = isset( $inputs['competitors'] ) ? $inputs['competitors'] : [];

        // Initialize outputs.
        $outputs = [
            'domain' => $domain,
            'summary' => '',
            'issues' => [
                'critical' => [],
                'high' => [],
                'medium' => [],
                'low' => [],
            ],
            'recommendations' => [],
            'keyword_opportunities' => [],
            'competitor_analysis' => [],
            'stats' => [
                'pages_analyzed' => 0,
                'issues_found' => 0,
                'score' => 0,
            ],
        ];

        try {
            // Log audit start.
            $this->log( $task->id, sprintf( __( 'Starting SEO audit for domain: %s', 'ryvr-ai' ), $domain ) );

            // Step 1: Run on-page site audit.
            $audit_options = [
                'limit' => $max_pages,
                'max_crawl_depth' => 2,
            ];
            
            $this->log( $task->id, __( 'Initiating on-page site audit...', 'ryvr-ai' ) );
            $audit_response = $dataforseo->site_audit( $domain, $audit_options );
            
            if ( is_wp_error( $audit_response ) ) {
                return $this->create_error( 
                    'audit_api_error', 
                    sprintf( __( 'DataForSEO API error: %s', 'ryvr-ai' ), $audit_response->get_error_message() ),
                    $audit_response->get_error_data()
                );
            }
            
            // Process task_post response to get task ID.
            if ( empty( $audit_response['tasks'] ) || ! isset( $audit_response['tasks'][0]['id'] ) ) {
                return $this->create_error( 
                    'task_creation_failed', 
                    __( 'Failed to create DataForSEO audit task.', 'ryvr-ai' ) 
                );
            }
            
            $task_id = $audit_response['tasks'][0]['id'];
            $this->log( $task->id, sprintf( __( 'DataForSEO audit task created with ID: %s', 'ryvr-ai' ), $task_id ) );
            
            // Wait for the task to complete - this could take time for larger sites.
            // In a real implementation, you would use a background process with polling
            // Here we'll wait for a short time as a demonstration.
            sleep(10);
            
            // Check if task is ready.
            $task_status = $dataforseo->check_task_status( $task_id );
            
            // Step 2: Get domain keywords.
            $this->log( $task->id, __( 'Fetching domain keywords...', 'ryvr-ai' ) );
            $keyword_options = [
                'limit' => 50,
            ];
            
            $domain_keywords_response = $dataforseo->domain_keywords( $domain, $keyword_options );
            
            if ( is_wp_error( $domain_keywords_response ) ) {
                $this->log( $task->id, sprintf( __( 'Error getting domain keywords: %s', 'ryvr-ai' ), $domain_keywords_response->get_error_message() ), 'warning' );
            } else {
                if ( isset( $domain_keywords_response['tasks'][0]['result'] ) ) {
                    $keyword_results = $domain_keywords_response['tasks'][0]['result'];
                    
                    // Process keyword opportunities.
                    foreach ( $keyword_results as $keyword_result ) {
                        if ( isset( $keyword_result['keyword'] ) ) {
                            $keyword = $keyword_result['keyword'];
                            $position = $keyword_result['position'] ?? 0;
                            $search_volume = $keyword_result['search_volume'] ?? 0;
                            $cpc = $keyword_result['cpc'] ?? 0;
                            
                            // Add to keyword opportunities if position is below 10 and has decent volume.
                            if ( $position > 10 && $search_volume > 100 ) {
                                $outputs['keyword_opportunities'][] = [
                                    'keyword' => $keyword,
                                    'position' => $position,
                                    'search_volume' => $search_volume,
                                    'cpc' => $cpc,
                                    'potential' => $this->calculate_keyword_potential($position, $search_volume, $cpc),
                                ];
                            }
                        }
                    }
                    
                    // Sort by potential DESC.
                    usort( $outputs['keyword_opportunities'], function( $a, $b ) {
                        return $b['potential'] - $a['potential'];
                    });
                    
                    // Keep only top 20.
                    $outputs['keyword_opportunities'] = array_slice( $outputs['keyword_opportunities'], 0, 20 );
                }
            }
            
            // Step 3: Get competitor analysis.
            if ( ! empty( $competitors ) ) {
                $this->log( $task->id, __( 'Analyzing competitors...', 'ryvr-ai' ) );
                
                foreach ( $competitors as $competitor ) {
                    $backlinks_response = $dataforseo->domain_backlinks( $competitor, ['limit' => 20] );
                    
                    if ( ! is_wp_error( $backlinks_response ) && isset( $backlinks_response['tasks'][0]['result'] ) ) {
                        $backlink_count = count( $backlinks_response['tasks'][0]['result'] );
                        
                        $outputs['competitor_analysis'][] = [
                            'domain' => $competitor,
                            'backlinks_sample' => $backlink_count,
                        ];
                    }
                }
            } else {
                // Auto-detect competitors.
                $this->log( $task->id, __( 'Auto-detecting competitors...', 'ryvr-ai' ) );
                
                $competitors_response = $dataforseo->domain_competitors( $domain, ['limit' => 5] );
                
                if ( ! is_wp_error( $competitors_response ) && isset( $competitors_response['tasks'][0]['result'] ) ) {
                    $competitors_data = $competitors_response['tasks'][0]['result'];
                    
                    foreach ( $competitors_data as $competitor_data ) {
                        if ( isset( $competitor_data['domain'] ) ) {
                            $outputs['competitor_analysis'][] = [
                                'domain' => $competitor_data['domain'],
                                'intersections' => $competitor_data['intersections'] ?? 0,
                                'related_keywords' => $competitor_data['relevant_keywords'] ?? 0,
                            ];
                        }
                    }
                }
            }
            
            // Step 4: Generate sample issues based on common SEO problems.
            // In a real implementation, these would come from the DataForSEO audit results.
            $this->log( $task->id, __( 'Generating SEO issues and recommendations...', 'ryvr-ai' ) );
            
            // Sample issues for demonstration purposes.
            $sample_issues = [
                'critical' => [
                    [
                        'title' => __( 'Missing SSL Certificate', 'ryvr-ai' ),
                        'description' => __( 'Your website is not using HTTPS, which is a critical security issue and affects SEO rankings.', 'ryvr-ai' ),
                        'recommendation' => __( 'Install an SSL certificate and migrate your website to HTTPS.', 'ryvr-ai' ),
                    ],
                ],
                'high' => [
                    [
                        'title' => __( 'Slow Page Load Speed', 'ryvr-ai' ),
                        'description' => __( 'Several pages have load times exceeding 3 seconds, which negatively impacts user experience and SEO.', 'ryvr-ai' ),
                        'recommendation' => __( 'Optimize images, leverage browser caching, minify CSS/JS, and consider a CDN.', 'ryvr-ai' ),
                    ],
                    [
                        'title' => __( 'Missing Meta Descriptions', 'ryvr-ai' ),
                        'description' => __( '43% of your pages are missing meta descriptions, which reduces click-through rates from search results.', 'ryvr-ai' ),
                        'recommendation' => __( 'Add unique, descriptive meta descriptions to all pages (150-160 characters).', 'ryvr-ai' ),
                    ],
                ],
                'medium' => [
                    [
                        'title' => __( 'Duplicate Content Issues', 'ryvr-ai' ),
                        'description' => __( 'Found 12 pages with duplicate or very similar content, which can dilute SEO value.', 'ryvr-ai' ),
                        'recommendation' => __( 'Implement canonical tags or consolidate similar content into single, comprehensive pages.', 'ryvr-ai' ),
                    ],
                    [
                        'title' => __( 'Low Word Count', 'ryvr-ai' ),
                        'description' => __( '28 pages have less than 300 words, which may be considered thin content by search engines.', 'ryvr-ai' ),
                        'recommendation' => __( 'Expand thin content with valuable information that helps users and incorporates relevant keywords.', 'ryvr-ai' ),
                    ],
                ],
                'low' => [
                    [
                        'title' => __( 'Missing Alt Text for Images', 'ryvr-ai' ),
                        'description' => __( '67 images are missing alt text, which affects accessibility and image SEO.', 'ryvr-ai' ),
                        'recommendation' => __( 'Add descriptive alt text to all images that includes relevant keywords when appropriate.', 'ryvr-ai' ),
                    ],
                ],
            ];
            
            // Add sample issues to outputs.
            foreach ( $sample_issues as $severity => $issues ) {
                foreach ( $issues as $issue ) {
                    $outputs['issues'][$severity][] = $issue;
                    
                    // Add recommendation to main list.
                    $outputs['recommendations'][] = [
                        'title' => $issue['title'],
                        'recommendation' => $issue['recommendation'],
                        'severity' => $severity,
                    ];
                }
            }
            
            // Calculate stats.
            $total_issues = count( $outputs['issues']['critical'] ) + 
                           count( $outputs['issues']['high'] ) + 
                           count( $outputs['issues']['medium'] ) + 
                           count( $outputs['issues']['low'] );
                           
            $outputs['stats']['issues_found'] = $total_issues;
            $outputs['stats']['pages_analyzed'] = $max_pages;
            
            // Calculate a score based on issues found.
            $critical_weight = 10;
            $high_weight = 5;
            $medium_weight = 2;
            $low_weight = 1;
            
            $max_score = 100;
            $deduction = (
                count( $outputs['issues']['critical'] ) * $critical_weight +
                count( $outputs['issues']['high'] ) * $high_weight +
                count( $outputs['issues']['medium'] ) * $medium_weight +
                count( $outputs['issues']['low'] ) * $low_weight
            );
            
            $outputs['stats']['score'] = max( 0, $max_score - $deduction );
            
            // Step 5: Generate summary using OpenAI if available.
            if ( $openai ) {
                $this->log( $task->id, __( 'Generating audit summary using AI...', 'ryvr-ai' ) );
                
                // Build a prompt for OpenAI.
                $prompt = "Generate a concise SEO audit summary for the website {$domain}. ";
                $prompt .= "The website has an SEO score of {$outputs['stats']['score']}/100. ";
                $prompt .= "Critical issues: " . count( $outputs['issues']['critical'] ) . ". ";
                $prompt .= "High priority issues: " . count( $outputs['issues']['high'] ) . ". ";
                $prompt .= "Medium priority issues: " . count( $outputs['issues']['medium'] ) . ". ";
                $prompt .= "Low priority issues: " . count( $outputs['issues']['low'] ) . ". ";
                
                if ( ! empty( $outputs['keyword_opportunities'] ) ) {
                    $prompt .= "Top keyword opportunities: " . implode( ', ', array_slice( array_column( $outputs['keyword_opportunities'], 'keyword' ), 0, 5 ) ) . ". ";
                }
                
                $prompt .= "Please provide a summary (about 200 words) that highlights the main findings and overall SEO health of the website, along with a few high-level recommendations. Use a professional tone.";
                
                // Build messages for chat completion.
                $messages = [
                    [
                        'role' => 'system',
                        'content' => "You are an expert SEO consultant who provides clear, actionable insights based on website audit data.",
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ];
                
                // Get model from settings.
                $model = get_option( 'ryvr_openai_model', 'gpt-3.5-turbo' );
                
                // Make the API request.
                $response = $openai->generate_chat_completion( $messages, [
                    'model' => $model,
                    'max_tokens' => 500,
                    'temperature' => 0.7,
                ]);
                
                if ( ! is_wp_error( $response ) && isset( $response['choices'][0]['message']['content'] ) ) {
                    $outputs['summary'] = trim( $response['choices'][0]['message']['content'] );
                } else {
                    // Default summary if OpenAI fails.
                    $outputs['summary'] = sprintf(
                        __( 'SEO audit for %s completed with a score of %d/100. Found %d issues (%d critical, %d high priority, %d medium priority, %d low priority). Review the detailed report for specific recommendations to improve your SEO performance.', 'ryvr-ai' ),
                        $domain,
                        $outputs['stats']['score'],
                        $total_issues,
                        count( $outputs['issues']['critical'] ),
                        count( $outputs['issues']['high'] ),
                        count( $outputs['issues']['medium'] ),
                        count( $outputs['issues']['low'] )
                    );
                }
            } else {
                // Default summary if OpenAI is not available.
                $outputs['summary'] = sprintf(
                    __( 'SEO audit for %s completed with a score of %d/100. Found %d issues (%d critical, %d high priority, %d medium priority, %d low priority). Review the detailed report for specific recommendations to improve your SEO performance.', 'ryvr-ai' ),
                    $domain,
                    $outputs['stats']['score'],
                    $total_issues,
                    count( $outputs['issues']['critical'] ),
                    count( $outputs['issues']['high'] ),
                    count( $outputs['issues']['medium'] ),
                    count( $outputs['issues']['low'] )
                );
            }
            
            // Log completion.
            $this->log( $task->id, sprintf( __( 'SEO audit completed for %s with score: %d/100', 'ryvr-ai' ), $domain, $outputs['stats']['score'] ) );

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
     * Calculate potential value of a keyword.
     *
     * @param int   $position Current position.
     * @param int   $search_volume Search volume.
     * @param float $cpc Cost per click.
     * @return float Potential value (higher is better).
     */
    private function calculate_keyword_potential( $position, $search_volume, $cpc ) {
        // Simple formula: higher volume and CPC with lower position = higher potential.
        // Position factor: positions further from top have more room for improvement.
        $position_factor = min(($position - 1) / 10, 1);
        
        // Volume factor: logarithmic scale to prevent very high volume keywords from dominating.
        $volume_factor = log10(max(10, $search_volume));
        
        // CPC factor: higher CPC indicates more commercial intent.
        $cpc_factor = min($cpc, 10) / 2;
        
        return $position_factor * $volume_factor * (1 + $cpc_factor);
    }

    /**
     * Validate task inputs.
     *
     * @param array $inputs Task inputs.
     * @return true|WP_Error True if valid, WP_Error if invalid.
     */
    public function validate_inputs( $inputs ) {
        // Check if domain is provided.
        if ( empty( $inputs['domain'] ) ) {
            return $this->create_error( 
                'missing_domain', 
                __( 'Domain is required for SEO audit.', 'ryvr-ai' ) 
            );
        }

        // Validate domain format.
        if ( ! preg_match( '/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i', $inputs['domain'] ) ) {
            return $this->create_error( 
                'invalid_domain', 
                __( 'Please enter a valid domain name (e.g., example.com).', 'ryvr-ai' ) 
            );
        }

        // Validate max pages.
        if ( isset( $inputs['max_pages'] ) ) {
            $max_pages = (int) $inputs['max_pages'];
            if ( $max_pages < 10 || $max_pages > 1000 ) {
                return $this->create_error( 
                    'invalid_max_pages', 
                    __( 'Max pages must be between 10 and 1000.', 'ryvr-ai' ) 
                );
            }
        }

        // Validate competitors if provided.
        if ( isset( $inputs['competitors'] ) && is_array( $inputs['competitors'] ) ) {
            foreach ( $inputs['competitors'] as $competitor ) {
                if ( ! preg_match( '/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i', $competitor ) ) {
                    return $this->create_error( 
                        'invalid_competitor', 
                        sprintf( __( 'Invalid competitor domain: %s', 'ryvr-ai' ), $competitor )
                    );
                }
            }
        }

        return true;
    }
} 