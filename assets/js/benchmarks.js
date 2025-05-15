/**
 * Benchmarks JavaScript
 *
 * Handles the functionality for the benchmarks admin page.
 */

(function($) {
    'use strict';
    
    // Chart instances
    let overviewChart = null;
    let comparisonChart = null;
    
    // Current report data
    let currentReportData = null;
    
    // Initialize
    $(document).ready(function() {
        initBenchmarks();
    });
    
    /**
     * Initialize benchmarks functionality.
     */
    function initBenchmarks() {
        // Set up event handlers
        setupEventHandlers();
    }
    
    /**
     * Set up event handlers.
     */
    function setupEventHandlers() {
        $('#ryvr-generate-report').on('click', function() {
            generateBenchmarkReport();
        });
        
        $('#ryvr-export-report').on('click', function() {
            exportReportToCSV();
        });
        
        // Enable/disable generate button based on selections
        $('.ryvr-benchmark-filter-select').on('change', function() {
            validateFormInputs();
        });
    }
    
    /**
     * Validate form inputs and enable/disable the generate button.
     */
    function validateFormInputs() {
        const industry = $('#ryvr-benchmark-industry').val();
        const benchmarkType = $('#ryvr-benchmark-type').val();
        
        if (industry && benchmarkType) {
            $('#ryvr-generate-report').prop('disabled', false);
        } else {
            $('#ryvr-generate-report').prop('disabled', true);
        }
    }
    
    /**
     * Generate benchmark report.
     */
    function generateBenchmarkReport() {
        const industry = $('#ryvr-benchmark-industry').val();
        const benchmarkType = $('#ryvr-benchmark-type').val();
        const period = $('#ryvr-benchmark-period').val();
        
        if (!industry || !benchmarkType) {
            showError(__('Please select both industry and benchmark type.', 'ryvr-ai'));
            return;
        }
        
        // Show loading
        showLoading();
        
        // Reset content
        resetContent();
        
        // Send AJAX request
        $.ajax({
            url: ryvrBenchmarks.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ryvr_generate_benchmark_report',
                nonce: ryvrBenchmarks.nonce,
                industry: industry,
                benchmark_type: benchmarkType,
                period: period
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    currentReportData = response.data;
                    renderBenchmarkReport(response.data);
                } else {
                    showError(response.data.message || __('Error generating report.', 'ryvr-ai'));
                }
            },
            error: function() {
                hideLoading();
                showError(__('Error connecting to the server.', 'ryvr-ai'));
            }
        });
    }
    
    /**
     * Render benchmark report.
     *
     * @param {Object} data Benchmark report data.
     */
    function renderBenchmarkReport(data) {
        if (!data || !data.metrics || data.metrics.length === 0) {
            showEmpty();
            return;
        }
        
        // Update header
        updateReportHeader(data);
        
        // Update summary
        updateReportSummary(data);
        
        // Generate charts
        generateCharts(data);
        
        // Generate table
        generateMetricsTable(data);
        
        // Generate insights
        generateInsights(data);
        
        // Show content
        $('.ryvr-benchmarks-content').show();
        
        // Enable export button
        $('#ryvr-export-report').prop('disabled', false);
    }
    
    /**
     * Update report header.
     *
     * @param {Object} data Benchmark report data.
     */
    function updateReportHeader(data) {
        $('.ryvr-benchmarks-title').text(data.industry_name + ' ' + data.benchmark_type_name + ' Benchmarks');
        
        let periodText = '';
        switch (data.period) {
            case 'last_30_days':
                periodText = __('Last 30 Days', 'ryvr-ai');
                break;
            case 'last_90_days':
                periodText = __('Last 90 Days', 'ryvr-ai');
                break;
            case 'last_6_months':
                periodText = __('Last 6 Months', 'ryvr-ai');
                break;
            case 'last_year':
                periodText = __('Last Year', 'ryvr-ai');
                break;
            case 'ytd':
                periodText = __('Year to Date', 'ryvr-ai');
                break;
            default:
                periodText = __('Custom Period', 'ryvr-ai');
                break;
        }
        
        $('.ryvr-benchmarks-subtitle').text(__('Report for', 'ryvr-ai') + ' ' + periodText);
    }
    
    /**
     * Update report summary.
     *
     * @param {Object} data Benchmark report data.
     */
    function updateReportSummary(data) {
        $('.ryvr-benchmarks-description').text(
            __('This report provides industry benchmarks for', 'ryvr-ai') + ' ' + 
            data.industry_name + ' ' + 
            __('companies focusing on', 'ryvr-ai') + ' ' + 
            data.benchmark_type_name.toLowerCase() + ' ' +
            __('metrics.', 'ryvr-ai')
        );
        
        $('.ryvr-benchmarks-date-range').text(
            formatDate(data.date_range.start) + ' ' + 
            __('to', 'ryvr-ai') + ' ' + 
            formatDate(data.date_range.end)
        );
    }
    
    /**
     * Generate charts.
     *
     * @param {Object} data Benchmark report data.
     */
    function generateCharts(data) {
        // Destroy existing charts
        if (overviewChart) {
            overviewChart.destroy();
        }
        
        if (comparisonChart) {
            comparisonChart.destroy();
        }
        
        // Create datasets
        const labels = data.metrics.map(function(metric) {
            return formatMetricName(metric.metric_name);
        });
        
        const averageValues = data.metrics.map(function(metric) {
            return parseFloat(metric.avg_value);
        });
        
        const comparisonValues = data.metrics.map(function(metric) {
            return parseFloat(metric.avg_comparison_value);
        });
        
        // Create overview chart
        const overviewCtx = document.getElementById('ryvr-overview-chart').getContext('2d');
        overviewChart = new Chart(overviewCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: __('Industry Average', 'ryvr-ai'),
                    data: averageValues,
                    backgroundColor: 'rgba(34, 113, 177, 0.7)',
                    borderColor: 'rgba(34, 113, 177, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Create comparison chart
        const comparisonCtx = document.getElementById('ryvr-comparison-chart').getContext('2d');
        comparisonChart = new Chart(comparisonCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: __('Current Period', 'ryvr-ai'),
                        data: averageValues,
                        backgroundColor: 'rgba(34, 113, 177, 0.2)',
                        borderColor: 'rgba(34, 113, 177, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.1
                    },
                    {
                        label: __('Previous Period', 'ryvr-ai'),
                        data: comparisonValues,
                        backgroundColor: 'rgba(125, 125, 125, 0.2)',
                        borderColor: 'rgba(125, 125, 125, 1)',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        fill: false,
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    /**
     * Generate metrics table.
     *
     * @param {Object} data Benchmark report data.
     */
    function generateMetricsTable(data) {
        const tableBody = $('#ryvr-benchmarks-table-body');
        tableBody.empty();
        
        data.metrics.forEach(function(metric) {
            const currentValue = parseFloat(metric.avg_value);
            const previousValue = parseFloat(metric.avg_comparison_value);
            const percentChange = previousValue ? ((currentValue - previousValue) / previousValue) * 100 : 0;
            
            let changeClass = '';
            let changeIndicator = '';
            
            if (percentChange > 0) {
                changeClass = 'positive-change';
                changeIndicator = '↑';
            } else if (percentChange < 0) {
                changeClass = 'negative-change';
                changeIndicator = '↓';
            }
            
            // For some metrics, negative change might be positive (e.g., bounce rate)
            // This would require additional logic based on metric name
            
            tableBody.append(
                $('<tr>').append(
                    $('<td>').text(formatMetricName(metric.metric_name)),
                    $('<td>').text(formatValue(metric.avg_value, metric.metric_name)),
                    $('<td>').text(formatValue(metric.min_value, metric.metric_name)),
                    $('<td>').text(formatValue(metric.max_value, metric.metric_name)),
                    $('<td>').text(formatValue(metric.avg_comparison_value, metric.metric_name)),
                    $('<td>').addClass(changeClass).text(
                        changeIndicator + ' ' + Math.abs(percentChange).toFixed(2) + '%'
                    )
                )
            );
        });
    }
    
    /**
     * Generate insights based on the benchmark data.
     *
     * @param {Object} data Benchmark report data.
     */
    function generateInsights(data) {
        const insightsContainer = $('.ryvr-benchmarks-insights-content');
        insightsContainer.empty();
        
        let insightsHtml = '<p>' + __('Based on the benchmark data for', 'ryvr-ai') + ' ' + 
            data.industry_name + ' ' + __('industry', 'ryvr-ai') + ', ' + 
            __('here are some key insights and recommendations:', 'ryvr-ai') + '</p>';
        
        insightsHtml += '<ul class="ryvr-insights-list">';
        
        // Generate simple insights based on the benchmark type
        switch (data.benchmark_type) {
            case 'seo':
                insightsHtml += '<li>' + __('Organic traffic benchmarks show that top-performing websites in your industry average', 'ryvr-ai') + ' ' + 
                    getMetricAvg(data, 'organic_traffic') + ' ' + __('visitors per month.', 'ryvr-ai') + '</li>';
                insightsHtml += '<li>' + __('The average website in this industry ranks for', 'ryvr-ai') + ' ' + 
                    getMetricAvg(data, 'keyword_rankings') + ' ' + __('keywords in the top 10 results.', 'ryvr-ai') + '</li>';
                insightsHtml += '<li>' + __('Focus on building quality backlinks, as industry leaders have an average of', 'ryvr-ai') + ' ' + 
                    getMetricAvg(data, 'backlinks') + ' ' + __('backlinks.', 'ryvr-ai') + '</li>';
                break;
                
            case 'ppc':
                insightsHtml += '<li>' + __('The average cost per click (CPC) in your industry is', 'ryvr-ai') + ' $' + 
                    getMetricAvg(data, 'cpc') + '.</li>';
                insightsHtml += '<li>' + __('Top performers achieve an average click-through rate (CTR) of', 'ryvr-ai') + ' ' + 
                    getMetricAvg(data, 'ctr') + '%.</li>';
                insightsHtml += '<li>' + __('The average conversion rate for PPC campaigns in this industry is', 'ryvr-ai') + ' ' + 
                    getMetricAvg(data, 'conversion_rate') + '%.</li>';
                break;
                
            case 'content':
                insightsHtml += '<li>' + __('The average time visitors spend on content pages is', 'ryvr-ai') + ' ' + 
                    getMetricAvg(data, 'avg_time_on_page') + ' ' + __('seconds.', 'ryvr-ai') + '</li>';
                insightsHtml += '<li>' + __('The benchmark for bounce rate in your industry is', 'ryvr-ai') + ' ' + 
                    getMetricAvg(data, 'bounce_rate') + '%.</li>';
                insightsHtml += '<li>' + __('Content that performs well typically achieves', 'ryvr-ai') + ' ' + 
                    getMetricAvg(data, 'social_shares') + ' ' + __('social shares on average.', 'ryvr-ai') + '</li>';
                break;
                
            case 'social':
                insightsHtml += '<li>' + __('The average engagement rate for social media posts in your industry is', 'ryvr-ai') + ' ' + 
                    getMetricAvg(data, 'engagement_rate') + '%.</li>';
                insightsHtml += '<li>' + __('Top performers see follower growth rates of approximately', 'ryvr-ai') + ' ' + 
                    getMetricAvg(data, 'followers_growth') + '% ' + __('per month.', 'ryvr-ai') + '</li>';
                insightsHtml += '<li>' + __('The average cost per engagement for social media campaigns is', 'ryvr-ai') + ' $' + 
                    getMetricAvg(data, 'cost_per_engagement') + '.</li>';
                break;
                
            case 'conversion':
                insightsHtml += '<li>' + __('The average overall conversion rate in your industry is', 'ryvr-ai') + ' ' + 
                    getMetricAvg(data, 'overall_conversion_rate') + '%.</li>';
                insightsHtml += '<li>' + __('Cart abandonment rates typically average around', 'ryvr-ai') + ' ' + 
                    getMetricAvg(data, 'cart_abandonment_rate') + '% ' + __('for this industry.', 'ryvr-ai') + '</li>';
                insightsHtml += '<li>' + __('Top performers achieve an average order value of', 'ryvr-ai') + ' $' + 
                    getMetricAvg(data, 'avg_order_value') + '.</li>';
                break;
                
            case 'technical':
                insightsHtml += '<li>' + __('The average page load time for top-performing websites is', 'ryvr-ai') + ' ' + 
                    getMetricAvg(data, 'page_load_time') + ' ' + __('seconds.', 'ryvr-ai') + '</li>';
                insightsHtml += '<li>' + __('Aim for a mobile score of at least', 'ryvr-ai') + ' ' + 
                    getMetricAvg(data, 'mobile_score') + ' ' + __('to match industry standards.', 'ryvr-ai') + '</li>';
                insightsHtml += '<li>' + __('Websites in your industry typically have', 'ryvr-ai') + ' ' + 
                    getMetricAvg(data, 'crawl_errors') + ' ' + __('crawl errors on average.', 'ryvr-ai') + '</li>';
                break;
        }
        
        insightsHtml += '</ul>';
        
        insightsHtml += '<p><strong>' + __('Recommendations:', 'ryvr-ai') + '</strong></p>';
        insightsHtml += '<ol>';
        insightsHtml += '<li>' + __('Compare your current performance metrics against these industry benchmarks to identify areas for improvement.', 'ryvr-ai') + '</li>';
        insightsHtml += '<li>' + __('Focus on metrics where you fall significantly below the industry average.', 'ryvr-ai') + '</li>';
        insightsHtml += '<li>' + __('Set realistic goals based on the industry top performers (max values).', 'ryvr-ai') + '</li>';
        insightsHtml += '<li>' + __('Implement tracking to monitor your progress toward these benchmark targets.', 'ryvr-ai') + '</li>';
        insightsHtml += '</ol>';
        
        insightsContainer.html(insightsHtml);
    }
    
    /**
     * Get the average value for a specific metric.
     *
     * @param {Object} data       Benchmark report data.
     * @param {string} metricName Metric name.
     * @return {string} Formatted average value or N/A if not found.
     */
    function getMetricAvg(data, metricName) {
        const metric = data.metrics.find(function(m) {
            return m.metric_name === metricName;
        });
        
        if (metric) {
            return formatValue(metric.avg_value, metricName);
        }
        
        return 'N/A';
    }
    
    /**
     * Format a value based on the metric type.
     *
     * @param {number} value      Value to format.
     * @param {string} metricName Metric name for context.
     * @return {string} Formatted value.
     */
    function formatValue(value, metricName) {
        if (value === null || value === undefined) {
            return 'N/A';
        }
        
        value = parseFloat(value);
        
        // Format based on metric name
        if (metricName.includes('cpc') || metricName.includes('cost') || metricName.includes('value')) {
            // Currency
            return '$' + value.toFixed(2);
        } else if (metricName.includes('rate') || metricName.includes('ctr') || metricName.includes('engagement')) {
            // Percentage
            return value.toFixed(2) + '%';
        } else if (metricName.includes('time') || metricName.includes('load')) {
            // Time in seconds
            return value.toFixed(2) + 's';
        } else if (metricName.includes('traffic') || metricName.includes('clicks') || metricName.includes('impressions') || metricName.includes('backlinks') || metricName.includes('shares')) {
            // Large numbers
            return value.toLocaleString(undefined, { maximumFractionDigits: 0 });
        } else if (metricName.includes('score')) {
            // Scores
            return value.toFixed(0);
        } else {
            // Default
            return value.toFixed(2);
        }
    }
    
    /**
     * Format a metric name for display.
     *
     * @param {string} metricName Metric name.
     * @return {string} Formatted metric name.
     */
    function formatMetricName(metricName) {
        return metricName
            .replace(/_/g, ' ')
            .replace(/\b\w/g, function(l) { return l.toUpperCase(); });
    }
    
    /**
     * Format a date string for display.
     *
     * @param {string} dateString Date string in YYYY-MM-DD format.
     * @return {string} Formatted date.
     */
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString();
    }
    
    /**
     * Export the current report to CSV.
     */
    function exportReportToCSV() {
        if (!currentReportData) {
            return;
        }
        
        let csvContent = 'data:text/csv;charset=utf-8,';
        
        // Add header
        csvContent += 'Industry: ' + currentReportData.industry_name + '\n';
        csvContent += 'Benchmark Type: ' + currentReportData.benchmark_type_name + '\n';
        csvContent += 'Period: ' + formatDate(currentReportData.date_range.start) + ' to ' + formatDate(currentReportData.date_range.end) + '\n\n';
        
        // Add column headers
        csvContent += 'Metric,Average Value,Min Value,Max Value,Previous Period,Change (%)\n';
        
        // Add data rows
        currentReportData.metrics.forEach(function(metric) {
            const currentValue = parseFloat(metric.avg_value);
            const previousValue = parseFloat(metric.avg_comparison_value);
            const percentChange = previousValue ? ((currentValue - previousValue) / previousValue) * 100 : 0;
            
            csvContent += [
                formatMetricName(metric.metric_name),
                metric.avg_value,
                metric.min_value,
                metric.max_value,
                metric.avg_comparison_value,
                percentChange.toFixed(2)
            ].join(',') + '\n';
        });
        
        // Create download link
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', 'ryvr_benchmark_report_' + currentReportData.industry + '_' + currentReportData.benchmark_type + '.csv');
        document.body.appendChild(link);
        
        // Trigger download
        link.click();
        
        // Clean up
        document.body.removeChild(link);
    }
    
    /**
     * Show loading overlay.
     */
    function showLoading() {
        $('.ryvr-benchmarks-loading').show();
        $('.ryvr-benchmarks-error').hide();
        $('.ryvr-benchmarks-empty').hide();
        $('.ryvr-benchmarks-content').hide();
    }
    
    /**
     * Hide loading overlay.
     */
    function hideLoading() {
        $('.ryvr-benchmarks-loading').hide();
    }
    
    /**
     * Show error message.
     *
     * @param {string} message Error message.
     */
    function showError(message) {
        $('.ryvr-benchmarks-loading').hide();
        $('.ryvr-benchmarks-error').show();
        $('.ryvr-benchmarks-error-message').text(message);
        $('.ryvr-benchmarks-empty').hide();
        $('.ryvr-benchmarks-content').hide();
    }
    
    /**
     * Show empty state.
     */
    function showEmpty() {
        $('.ryvr-benchmarks-loading').hide();
        $('.ryvr-benchmarks-error').hide();
        $('.ryvr-benchmarks-empty').show();
        $('.ryvr-benchmarks-content').hide();
    }
    
    /**
     * Reset content.
     */
    function resetContent() {
        $('.ryvr-benchmarks-error').hide();
        $('.ryvr-benchmarks-empty').hide();
        $('.ryvr-benchmarks-content').hide();
        
        // Reset export button
        $('#ryvr-export-report').prop('disabled', true);
    }
    
    /**
     * Translate string.
     *
     * @param {string} string String to translate.
     * @param {string} domain Text domain.
     * @return {string} Translated string.
     */
    function __(string, domain) {
        // This is a simple implementation, in a real plugin you would use wp.i18n.__
        return string;
    }
    
})(jQuery); 