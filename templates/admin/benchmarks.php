<?php
/**
 * Template for the Benchmarks admin page.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Benchmarks
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap ryvr-benchmarks-wrap">
    <h1><?php echo esc_html__( 'Industry Benchmarks', 'ryvr-ai' ); ?></h1>
    
    <div class="ryvr-benchmarks-description">
        <p><?php echo esc_html__( 'Industry benchmarks provide valuable insights into how your performance compares to industry standards. Select an industry and benchmark type to view the data.', 'ryvr-ai' ); ?></p>
    </div>
    
    <div class="ryvr-benchmarks-filters">
        <div class="ryvr-benchmark-filter">
            <label for="ryvr-benchmark-industry"><?php echo esc_html__( 'Industry', 'ryvr-ai' ); ?></label>
            <select id="ryvr-benchmark-industry" class="ryvr-benchmark-filter-select">
                <option value=""><?php echo esc_html__( 'Select Industry', 'ryvr-ai' ); ?></option>
                <?php foreach ( $this->industries as $key => $name ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $name ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="ryvr-benchmark-filter">
            <label for="ryvr-benchmark-type"><?php echo esc_html__( 'Benchmark Type', 'ryvr-ai' ); ?></label>
            <select id="ryvr-benchmark-type" class="ryvr-benchmark-filter-select">
                <option value=""><?php echo esc_html__( 'Select Benchmark Type', 'ryvr-ai' ); ?></option>
                <?php foreach ( $this->benchmark_types as $key => $name ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $name ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="ryvr-benchmark-filter">
            <label for="ryvr-benchmark-period"><?php echo esc_html__( 'Time Period', 'ryvr-ai' ); ?></label>
            <select id="ryvr-benchmark-period" class="ryvr-benchmark-filter-select">
                <option value="last_30_days"><?php echo esc_html__( 'Last 30 Days', 'ryvr-ai' ); ?></option>
                <option value="last_90_days"><?php echo esc_html__( 'Last 90 Days', 'ryvr-ai' ); ?></option>
                <option value="last_6_months"><?php echo esc_html__( 'Last 6 Months', 'ryvr-ai' ); ?></option>
                <option value="last_year"><?php echo esc_html__( 'Last Year', 'ryvr-ai' ); ?></option>
                <option value="ytd"><?php echo esc_html__( 'Year to Date', 'ryvr-ai' ); ?></option>
            </select>
        </div>
        
        <div class="ryvr-benchmark-actions">
            <button id="ryvr-generate-report" class="button button-primary">
                <?php echo esc_html__( 'Generate Report', 'ryvr-ai' ); ?>
            </button>
            
            <button id="ryvr-export-report" class="button" disabled>
                <?php echo esc_html__( 'Export to CSV', 'ryvr-ai' ); ?>
            </button>
        </div>
    </div>
    
    <div class="ryvr-benchmarks-container">
        <div class="ryvr-benchmarks-loading" style="display: none;">
            <div class="spinner is-active"></div>
            <p><?php echo esc_html__( 'Loading benchmark data...', 'ryvr-ai' ); ?></p>
        </div>
        
        <div class="ryvr-benchmarks-error" style="display: none;">
            <p class="ryvr-benchmarks-error-message"></p>
        </div>
        
        <div class="ryvr-benchmarks-empty" style="display: none;">
            <p><?php echo esc_html__( 'No benchmark data available for the selected criteria. Please select different filters or try again later.', 'ryvr-ai' ); ?></p>
        </div>
        
        <div class="ryvr-benchmarks-content" style="display: none;">
            <div class="ryvr-benchmarks-header">
                <h2 class="ryvr-benchmarks-title"></h2>
                <p class="ryvr-benchmarks-subtitle"></p>
            </div>
            
            <div class="ryvr-benchmarks-summary">
                <div class="ryvr-benchmarks-summary-card">
                    <h3><?php echo esc_html__( 'About This Report', 'ryvr-ai' ); ?></h3>
                    <p class="ryvr-benchmarks-description"></p>
                    <div class="ryvr-benchmarks-meta">
                        <p><strong><?php echo esc_html__( 'Date Range:', 'ryvr-ai' ); ?></strong> <span class="ryvr-benchmarks-date-range"></span></p>
                        <p><strong><?php echo esc_html__( 'Data Source:', 'ryvr-ai' ); ?></strong> <?php echo esc_html__( 'Ryvr Industry Analytics', 'ryvr-ai' ); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="ryvr-benchmarks-charts">
                <div class="ryvr-benchmarks-chart-container">
                    <h3><?php echo esc_html__( 'Key Metrics Overview', 'ryvr-ai' ); ?></h3>
                    <div class="ryvr-chart-container">
                        <canvas id="ryvr-overview-chart"></canvas>
                    </div>
                </div>
                
                <div class="ryvr-benchmarks-chart-container">
                    <h3><?php echo esc_html__( 'Comparison with Previous Period', 'ryvr-ai' ); ?></h3>
                    <div class="ryvr-chart-container">
                        <canvas id="ryvr-comparison-chart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="ryvr-benchmarks-table-container">
                <h3><?php echo esc_html__( 'Detailed Metrics', 'ryvr-ai' ); ?></h3>
                <table class="wp-list-table widefat fixed striped ryvr-benchmarks-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__( 'Metric', 'ryvr-ai' ); ?></th>
                            <th><?php echo esc_html__( 'Average Value', 'ryvr-ai' ); ?></th>
                            <th><?php echo esc_html__( 'Min Value', 'ryvr-ai' ); ?></th>
                            <th><?php echo esc_html__( 'Max Value', 'ryvr-ai' ); ?></th>
                            <th><?php echo esc_html__( 'Previous Period', 'ryvr-ai' ); ?></th>
                            <th><?php echo esc_html__( 'Change', 'ryvr-ai' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="ryvr-benchmarks-table-body">
                        <!-- Data will be inserted here dynamically -->
                    </tbody>
                </table>
            </div>
            
            <div class="ryvr-benchmarks-insights">
                <h3><?php echo esc_html__( 'Insights & Recommendations', 'ryvr-ai' ); ?></h3>
                <div class="ryvr-benchmarks-insights-content">
                    <!-- Will be populated dynamically -->
                </div>
            </div>
        </div>
    </div>
</div> 