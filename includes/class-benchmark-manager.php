<?php
/**
 * The Benchmark Manager class.
 *
 * Handles industry benchmark data and reporting.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Includes
 */

namespace Ryvr\Benchmarks;

use Ryvr\Database\Database_Manager;
use Exception;

// Create a local alias to use the correct class
use Ryvr\API\Services\DataForSEO_Service;

/**
 * The Benchmark Manager class.
 *
 * This class handles the collection, storage, and reporting of
 * industry benchmark data to provide users with relevant
 * competitive insights.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Includes
 */
class Benchmark_Manager {

    /**
     * Database Manager instance.
     *
     * @var Database_Manager
     */
    private $db_manager;

    /**
     * DataForSEO service instance.
     *
     * @var object
     */
    private $dataforseo;

    /**
     * Benchmarks table name.
     *
     * @var string
     */
    private $benchmarks_table;

    /**
     * Available industries list.
     *
     * @var array
     */
    private $industries = [
        'ecommerce' => 'E-Commerce',
        'finance' => 'Finance & Banking',
        'healthcare' => 'Healthcare',
        'education' => 'Education',
        'technology' => 'Technology & SaaS',
        'real_estate' => 'Real Estate',
        'travel' => 'Travel & Hospitality',
        'legal' => 'Legal Services',
        'manufacturing' => 'Manufacturing',
        'retail' => 'Retail',
        'food' => 'Food & Restaurants',
        'professional_services' => 'Professional Services',
        'media' => 'Media & Entertainment',
        'nonprofit' => 'Non-profit & Charity',
        'automotive' => 'Automotive',
    ];

    /**
     * Available benchmark types.
     *
     * @var array
     */
    private $benchmark_types = [
        'seo' => 'SEO Performance',
        'ppc' => 'PPC Performance',
        'content' => 'Content Performance',
        'social' => 'Social Media',
        'conversion' => 'Conversion Rates',
        'technical' => 'Technical SEO',
    ];

    /**
     * Initialize the class.
     *
     * @param Database_Manager    $db_manager Database manager instance.
     * @param object              $dataforseo DataForSEO service instance.
     * @return void
     */
    public function init( Database_Manager $db_manager, $dataforseo = null ) {
        $this->db_manager = $db_manager;
        $this->dataforseo = $dataforseo;
        $this->benchmarks_table = $this->db_manager->get_table( 'benchmarks' );
        
        // Register hooks.
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 20 );
        add_action( 'wp_ajax_ryvr_generate_benchmark_report', [ $this, 'ajax_generate_benchmark_report' ] );
        add_action( 'wp_ajax_ryvr_get_benchmark_data', [ $this, 'ajax_get_benchmark_data' ] );

        // Schedule benchmark data collection
        add_action( 'ryvr_monthly_benchmark_update', [ $this, 'update_benchmark_data' ] );
        
        if ( ! wp_next_scheduled( 'ryvr_monthly_benchmark_update' ) ) {
            wp_schedule_event( time(), 'monthly', 'ryvr_monthly_benchmark_update' );
        }
    }

    /**
     * Register admin menu items.
     *
     * @return void
     */
    public function register_admin_menu() {
        add_submenu_page(
            'ryvr-ai-dashboard',
            __( 'Industry Benchmarks', 'ryvr-ai' ),
            __( 'Benchmarks', 'ryvr-ai' ),
            'manage_options',
            'ryvr-benchmarks',
            [ $this, 'render_benchmarks_page' ]
        );
    }

    /**
     * Render the benchmarks admin page.
     *
     * @return void
     */
    public function render_benchmarks_page() {
        wp_enqueue_style( 'ryvr-benchmark-css', RYVR_ASSETS_URL . 'css/benchmarks.css', [], RYVR_VERSION );
        wp_enqueue_script( 'ryvr-charts', RYVR_ASSETS_URL . 'js/chart.min.js', [], '3.7.0', true );
        wp_enqueue_script( 'ryvr-benchmark-js', RYVR_ASSETS_URL . 'js/benchmarks.js', [ 'jquery', 'ryvr-charts' ], RYVR_VERSION, true );
        
        wp_localize_script(
            'ryvr-benchmark-js',
            'ryvrBenchmarks',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'ryvr_benchmark_nonce' ),
                'industries' => $this->industries,
                'benchmarkTypes' => $this->benchmark_types,
                'strings' => [
                    'loading' => __( 'Loading benchmark data...', 'ryvr-ai' ),
                    'noData' => __( 'No benchmark data available for the selected criteria.', 'ryvr-ai' ),
                    'error' => __( 'Error loading benchmark data.', 'ryvr-ai' ),
                ],
            ]
        );

        require_once RYVR_TEMPLATES_DIR . 'admin/benchmarks.php';
    }

    /**
     * AJAX handler for generating benchmark reports.
     *
     * @return void
     */
    public function ajax_generate_benchmark_report() {
        check_ajax_referer( 'ryvr_benchmark_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ryvr-ai' ) ] );
            return;
        }
        
        $industry = isset( $_POST['industry'] ) ? sanitize_text_field( $_POST['industry'] ) : '';
        $benchmark_type = isset( $_POST['benchmark_type'] ) ? sanitize_text_field( $_POST['benchmark_type'] ) : '';
        $period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : 'last_30_days';
        
        if ( empty( $industry ) || empty( $benchmark_type ) ) {
            wp_send_json_error( [ 'message' => __( 'Missing required parameters.', 'ryvr-ai' ) ] );
            return;
        }
        
        try {
            $report_data = $this->generate_benchmark_report( $industry, $benchmark_type, $period );
            wp_send_json_success( $report_data );
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    /**
     * AJAX handler for getting benchmark data.
     *
     * @return void
     */
    public function ajax_get_benchmark_data() {
        check_ajax_referer( 'ryvr_benchmark_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ryvr-ai' ) ] );
            return;
        }
        
        $industry = isset( $_POST['industry'] ) ? sanitize_text_field( $_POST['industry'] ) : '';
        $benchmark_type = isset( $_POST['benchmark_type'] ) ? sanitize_text_field( $_POST['benchmark_type'] ) : '';
        $metric = isset( $_POST['metric'] ) ? sanitize_text_field( $_POST['metric'] ) : '';
        $period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : 'last_30_days';
        
        if ( empty( $industry ) || empty( $benchmark_type ) ) {
            wp_send_json_error( [ 'message' => __( 'Missing required parameters.', 'ryvr-ai' ) ] );
            return;
        }
        
        try {
            $benchmark_data = $this->get_benchmark_data( $industry, $benchmark_type, $metric, $period );
            wp_send_json_success( $benchmark_data );
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    /**
     * Generate a benchmark report for the specified parameters.
     *
     * @param string $industry       Industry to generate report for.
     * @param string $benchmark_type Type of benchmark report.
     * @param string $period         Time period for the report.
     * @return array Report data.
     */
    public function generate_benchmark_report( $industry, $benchmark_type, $period = 'last_30_days' ) {
        global $wpdb;
        
        // Validate parameters
        if ( ! array_key_exists( $industry, $this->industries ) ) {
            throw new Exception( __( 'Invalid industry.', 'ryvr-ai' ) );
        }
        
        if ( ! array_key_exists( $benchmark_type, $this->benchmark_types ) ) {
            throw new Exception( __( 'Invalid benchmark type.', 'ryvr-ai' ) );
        }
        
        // Define date range for the period
        $date_range = $this->get_date_range_for_period( $period );
        
        // Get benchmark data from database
        $query = $wpdb->prepare(
            "SELECT metric_name, AVG(metric_value) as avg_value, 
             MIN(metric_value) as min_value, MAX(metric_value) as max_value,
             AVG(comparison_value) as avg_comparison_value
             FROM {$this->benchmarks_table}
             WHERE industry = %s AND benchmark_type = %s
             AND period_start >= %s AND period_end <= %s
             GROUP BY metric_name
             ORDER BY metric_name ASC",
            $industry,
            $benchmark_type,
            $date_range['start'],
            $date_range['end']
        );
        
        $results = $wpdb->get_results( $query, ARRAY_A );
        
        if ( empty( $results ) ) {
            // If no data, try to fetch it
            $this->update_benchmark_data_for_industry( $industry, $benchmark_type );
            $results = $wpdb->get_results( $query, ARRAY_A );
            
            if ( empty( $results ) ) {
                throw new Exception( __( 'No benchmark data available for the selected criteria.', 'ryvr-ai' ) );
            }
        }
        
        return [
            'industry' => $industry,
            'industry_name' => $this->industries[$industry],
            'benchmark_type' => $benchmark_type,
            'benchmark_type_name' => $this->benchmark_types[$benchmark_type],
            'period' => $period,
            'date_range' => $date_range,
            'metrics' => $results,
        ];
    }

    /**
     * Get benchmark data for a specific metric.
     *
     * @param string $industry       Industry to get data for.
     * @param string $benchmark_type Type of benchmark.
     * @param string $metric         Specific metric to retrieve.
     * @param string $period         Time period.
     * @return array Benchmark data.
     */
    public function get_benchmark_data( $industry, $benchmark_type, $metric = '', $period = 'last_30_days' ) {
        global $wpdb;
        
        // Validate parameters
        if ( ! array_key_exists( $industry, $this->industries ) ) {
            throw new Exception( __( 'Invalid industry.', 'ryvr-ai' ) );
        }
        
        if ( ! array_key_exists( $benchmark_type, $this->benchmark_types ) ) {
            throw new Exception( __( 'Invalid benchmark type.', 'ryvr-ai' ) );
        }
        
        // Define date range for the period
        $date_range = $this->get_date_range_for_period( $period );
        
        // Prepare the query
        $query_params = [
            $industry,
            $benchmark_type,
            $date_range['start'],
            $date_range['end'],
        ];
        
        $query = "SELECT * FROM {$this->benchmarks_table}
                 WHERE industry = %s AND benchmark_type = %s
                 AND period_start >= %s AND period_end <= %s";
        
        if ( ! empty( $metric ) ) {
            $query .= " AND metric_name = %s";
            $query_params[] = $metric;
        }
        
        $query .= " ORDER BY period_start ASC, metric_name ASC";
        
        // Get the data
        $query = $wpdb->prepare( $query, $query_params );
        $results = $wpdb->get_results( $query, ARRAY_A );
        
        if ( empty( $results ) ) {
            throw new Exception( __( 'No benchmark data available for the selected criteria.', 'ryvr-ai' ) );
        }
        
        return [
            'industry' => $industry,
            'industry_name' => $this->industries[$industry],
            'benchmark_type' => $benchmark_type,
            'benchmark_type_name' => $this->benchmark_types[$benchmark_type],
            'metric' => $metric,
            'period' => $period,
            'date_range' => $date_range,
            'benchmarks' => $results,
        ];
    }

    /**
     * Update benchmark data for all industries.
     *
     * @return void
     */
    public function update_benchmark_data() {
        foreach ( array_keys( $this->industries ) as $industry ) {
            foreach ( array_keys( $this->benchmark_types ) as $benchmark_type ) {
                $this->update_benchmark_data_for_industry( $industry, $benchmark_type );
            }
        }
    }

    /**
     * Update benchmark data for a specific industry.
     *
     * @param string $industry       Industry to update.
     * @param string $benchmark_type Type of benchmark to update.
     * @return void
     */
    private function update_benchmark_data_for_industry( $industry, $benchmark_type ) {
        // This would normally fetch actual data from APIs or other sources
        // For demonstration, we'll generate sample data
        
        // Define current period
        $period_end = date( 'Y-m-d' );
        $period_start = date( 'Y-m-d', strtotime( '-30 days' ) );
        $period = 'monthly';
        
        // Get the current user ID
        $user_id = get_current_user_id();
        
        // Generate benchmark metrics based on benchmark type
        $metrics = $this->get_metrics_for_benchmark_type( $benchmark_type );
        
        foreach ( $metrics as $metric_name => $metric_settings ) {
            // Generate a realistic value based on the metric
            $metric_value = $this->generate_sample_metric_value(
                $metric_settings['min'],
                $metric_settings['max'],
                $metric_settings['decimal_places']
            );
            
            // Generate a comparison value (typically previous period)
            $comparison_value = $this->generate_sample_metric_value(
                $metric_value * 0.8,
                $metric_value * 1.2,
                $metric_settings['decimal_places']
            );
            
            // Insert or update the benchmark data
            $this->save_benchmark_data(
                $user_id,
                $industry,
                $benchmark_type,
                'ryvr_generated',  // Data source
                $metric_name,
                $metric_value,
                $comparison_value,
                $period,
                $period_start,
                $period_end,
                'US',  // Location
                'all',  // Device
                100,  // Sample size
                'Auto-generated benchmark data'
            );
        }
    }

    /**
     * Save benchmark data to the database.
     *
     * @param int    $user_id          User ID.
     * @param string $industry         Industry.
     * @param string $benchmark_type   Benchmark type.
     * @param string $data_source      Source of the data.
     * @param string $metric_name      Metric name.
     * @param float  $metric_value     Metric value.
     * @param float  $comparison_value Comparison value.
     * @param string $period           Time period.
     * @param string $period_start     Period start date.
     * @param string $period_end       Period end date.
     * @param string $location         Geographic location.
     * @param string $device           Device type.
     * @param int    $sample_size      Sample size.
     * @param string $notes            Notes about the data.
     * @return int|false Inserted row ID or false on failure.
     */
    public function save_benchmark_data(
        $user_id,
        $industry,
        $benchmark_type,
        $data_source,
        $metric_name,
        $metric_value,
        $comparison_value = null,
        $period = 'monthly',
        $period_start = '',
        $period_end = '',
        $location = 'US',
        $device = 'all',
        $sample_size = 100,
        $notes = ''
    ) {
        global $wpdb;
        
        // Set period dates if not provided
        if ( empty( $period_start ) || empty( $period_end ) ) {
            $dates = $this->get_date_range_for_period( $period );
            $period_start = $dates['start'];
            $period_end = $dates['end'];
        }
        
        // Check if this benchmark already exists
        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->benchmarks_table}
                WHERE user_id = %d AND industry = %s AND benchmark_type = %s
                AND data_source = %s AND metric_name = %s
                AND period = %s AND period_start = %s AND period_end = %s",
                $user_id,
                $industry,
                $benchmark_type,
                $data_source,
                $metric_name,
                $period,
                $period_start,
                $period_end
            )
        );
        
        if ( $existing_id ) {
            // Update existing record
            $result = $wpdb->update(
                $this->benchmarks_table,
                [
                    'metric_value' => $metric_value,
                    'comparison_value' => $comparison_value,
                    'location' => $location,
                    'device' => $device,
                    'sample_size' => $sample_size,
                    'notes' => $notes,
                    'updated_at' => current_time( 'mysql' ),
                ],
                [ 'id' => $existing_id ],
                [ '%f', '%f', '%s', '%s', '%d', '%s', '%s' ],
                [ '%d' ]
            );
            
            return $result !== false ? $existing_id : false;
        } else {
            // Insert new record
            $result = $wpdb->insert(
                $this->benchmarks_table,
                [
                    'user_id' => $user_id,
                    'industry' => $industry,
                    'benchmark_type' => $benchmark_type,
                    'data_source' => $data_source,
                    'metric_name' => $metric_name,
                    'metric_value' => $metric_value,
                    'comparison_value' => $comparison_value,
                    'period' => $period,
                    'period_start' => $period_start,
                    'period_end' => $period_end,
                    'location' => $location,
                    'device' => $device,
                    'sample_size' => $sample_size,
                    'notes' => $notes,
                    'created_at' => current_time( 'mysql' ),
                    'updated_at' => current_time( 'mysql' ),
                ],
                [
                    '%d', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s',
                    '%s', '%s', '%d', '%s', '%s', '%s',
                ]
            );
            
            return $result ? $wpdb->insert_id : false;
        }
    }

    /**
     * Get date range for a given period.
     *
     * @param string $period Period identifier.
     * @return array Start and end dates.
     */
    private function get_date_range_for_period( $period ) {
        $end_date = date( 'Y-m-d' );
        $start_date = '';
        
        switch ( $period ) {
            case 'last_7_days':
                $start_date = date( 'Y-m-d', strtotime( '-7 days' ) );
                break;
            case 'last_30_days':
                $start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
                break;
            case 'last_90_days':
                $start_date = date( 'Y-m-d', strtotime( '-90 days' ) );
                break;
            case 'last_6_months':
                $start_date = date( 'Y-m-d', strtotime( '-6 months' ) );
                break;
            case 'last_year':
                $start_date = date( 'Y-m-d', strtotime( '-1 year' ) );
                break;
            case 'ytd':
                $start_date = date( 'Y-01-01' );
                break;
            default:
                $start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
                break;
        }
        
        return [
            'start' => $start_date,
            'end' => $end_date,
        ];
    }

    /**
     * Get metrics for a specific benchmark type.
     *
     * @param string $benchmark_type Benchmark type.
     * @return array Metrics and their settings.
     */
    private function get_metrics_for_benchmark_type( $benchmark_type ) {
        $metrics = [];
        
        switch ( $benchmark_type ) {
            case 'seo':
                $metrics = [
                    'organic_traffic' => [
                        'min' => 1000,
                        'max' => 100000,
                        'decimal_places' => 0,
                    ],
                    'keyword_rankings' => [
                        'min' => 10,
                        'max' => 50,
                        'decimal_places' => 0,
                    ],
                    'organic_ctr' => [
                        'min' => 1,
                        'max' => 10,
                        'decimal_places' => 2,
                    ],
                    'indexed_pages' => [
                        'min' => 100,
                        'max' => 5000,
                        'decimal_places' => 0,
                    ],
                    'backlinks' => [
                        'min' => 100,
                        'max' => 10000,
                        'decimal_places' => 0,
                    ],
                ];
                break;
                
            case 'ppc':
                $metrics = [
                    'cpc' => [
                        'min' => 0.5,
                        'max' => 10,
                        'decimal_places' => 2,
                    ],
                    'ctr' => [
                        'min' => 0.5,
                        'max' => 8,
                        'decimal_places' => 2,
                    ],
                    'conversion_rate' => [
                        'min' => 1,
                        'max' => 10,
                        'decimal_places' => 2,
                    ],
                    'quality_score' => [
                        'min' => 5,
                        'max' => 10,
                        'decimal_places' => 1,
                    ],
                    'roi' => [
                        'min' => 100,
                        'max' => 500,
                        'decimal_places' => 0,
                    ],
                ];
                break;
                
            case 'content':
                $metrics = [
                    'avg_time_on_page' => [
                        'min' => 30,
                        'max' => 300,
                        'decimal_places' => 0,
                    ],
                    'bounce_rate' => [
                        'min' => 20,
                        'max' => 80,
                        'decimal_places' => 2,
                    ],
                    'pages_per_session' => [
                        'min' => 1,
                        'max' => 5,
                        'decimal_places' => 2,
                    ],
                    'social_shares' => [
                        'min' => 10,
                        'max' => 1000,
                        'decimal_places' => 0,
                    ],
                    'content_conversion_rate' => [
                        'min' => 0.5,
                        'max' => 8,
                        'decimal_places' => 2,
                    ],
                ];
                break;
                
            case 'social':
                $metrics = [
                    'engagement_rate' => [
                        'min' => 0.5,
                        'max' => 5,
                        'decimal_places' => 2,
                    ],
                    'followers_growth' => [
                        'min' => 1,
                        'max' => 15,
                        'decimal_places' => 2,
                    ],
                    'click_through_rate' => [
                        'min' => 0.1,
                        'max' => 3,
                        'decimal_places' => 2,
                    ],
                    'social_conversion_rate' => [
                        'min' => 0.1,
                        'max' => 2,
                        'decimal_places' => 2,
                    ],
                    'cost_per_engagement' => [
                        'min' => 0.05,
                        'max' => 1,
                        'decimal_places' => 2,
                    ],
                ];
                break;
                
            case 'conversion':
                $metrics = [
                    'overall_conversion_rate' => [
                        'min' => 1,
                        'max' => 5,
                        'decimal_places' => 2,
                    ],
                    'cart_abandonment_rate' => [
                        'min' => 60,
                        'max' => 85,
                        'decimal_places' => 2,
                    ],
                    'lead_to_customer_rate' => [
                        'min' => 5,
                        'max' => 25,
                        'decimal_places' => 2,
                    ],
                    'avg_order_value' => [
                        'min' => 50,
                        'max' => 200,
                        'decimal_places' => 2,
                    ],
                    'customer_acquisition_cost' => [
                        'min' => 10,
                        'max' => 100,
                        'decimal_places' => 2,
                    ],
                ];
                break;
                
            case 'technical':
                $metrics = [
                    'page_load_time' => [
                        'min' => 1,
                        'max' => 5,
                        'decimal_places' => 2,
                    ],
                    'mobile_score' => [
                        'min' => 50,
                        'max' => 95,
                        'decimal_places' => 0,
                    ],
                    'ssl_score' => [
                        'min' => 70,
                        'max' => 100,
                        'decimal_places' => 0,
                    ],
                    'accessibility_score' => [
                        'min' => 60,
                        'max' => 95,
                        'decimal_places' => 0,
                    ],
                    'crawl_errors' => [
                        'min' => 0,
                        'max' => 50,
                        'decimal_places' => 0,
                    ],
                ];
                break;
        }
        
        return $metrics;
    }

    /**
     * Generate a sample metric value for benchmarks.
     *
     * @param float $min            Minimum value.
     * @param float $max            Maximum value.
     * @param int   $decimal_places Number of decimal places.
     * @return float Generated value.
     */
    private function generate_sample_metric_value( $min, $max, $decimal_places = 2 ) {
        $value = $min + mt_rand() / mt_getrandmax() * ( $max - $min );
        return round( $value, $decimal_places );
    }

    /**
     * Get the list of available industries.
     *
     * @return array Industries.
     */
    public function get_industries() {
        return $this->industries;
    }

    /**
     * Get the list of available benchmark types.
     *
     * @return array Benchmark types.
     */
    public function get_benchmark_types() {
        return $this->benchmark_types;
    }
} 