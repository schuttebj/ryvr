/**
 * Debug page JavaScript
 */
(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        
        // Handle date selection change
        $('#ryvr-log-date').on('change', function() {
            var date = $(this).val();
            window.location.href = 'admin.php?page=ryvr-ai-debug&date=' + date;
        });
        
        // Handle log level change
        $('#ryvr-log-level').on('change', function() {
            var level = $(this).val();
            
            // Send AJAX request
            $.ajax({
                url: ryvrDebug.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ryvr_change_log_level',
                    nonce: ryvrDebug.nonce,
                    level: level
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        alert('Log level updated to: ' + level);
                    } else {
                        // Show error message
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred while updating log level.');
                }
            });
        });
        
        // Handle refresh button
        $('#ryvr-refresh-log').on('click', function() {
            location.reload();
        });
        
        // Handle clear log button
        $('#ryvr-clear-log').on('click', function() {
            if (!confirm('Are you sure you want to clear this log file?')) {
                return;
            }
            
            var date = $('#ryvr-log-date').val();
            
            // Send AJAX request
            $.ajax({
                url: ryvrDebug.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ryvr_clear_log',
                    nonce: ryvrDebug.nonce,
                    date: date
                },
                success: function(response) {
                    if (response.success) {
                        // Clear log viewer
                        $('#ryvr-log-viewer').html('');
                        
                        // Show empty message
                        $('.ryvr-debug-content').html(
                            '<div class="ryvr-debug-empty">No log entries found for this date.</div>'
                        );
                        
                        // Show success message
                        alert('Log cleared successfully.');
                    } else {
                        // Show error message
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred while clearing the log.');
                }
            });
        });
        
        // Handle download log button
        $('#ryvr-download-log').on('click', function() {
            var content = $('#ryvr-log-viewer').text();
            if (!content) {
                alert('No log content to download.');
                return;
            }
            
            // Create blob with content
            var blob = new Blob([content], { type: 'text/plain' });
            
            // Create temporary download link
            var date = $('#ryvr-log-date').val();
            var filename = 'ryvr-debug-' + (date === 'today' ? new Date().toISOString().split('T')[0] : date) + '.log';
            
            var link = document.createElement('a');
            link.href = window.URL.createObjectURL(blob);
            link.download = filename;
            
            // Click the link to download
            link.click();
            
            // Clean up
            window.URL.revokeObjectURL(link.href);
        });
        
        // Colorize log levels in the viewer
        function colorizeLogLevels() {
            var content = $('#ryvr-log-viewer').html();
            if (!content) return;
            
            // Replace log level tags with colored spans
            content = content.replace(/\[EMERGENCY\]/g, '<span class="emergency">[EMERGENCY]</span>');
            content = content.replace(/\[ALERT\]/g, '<span class="alert">[ALERT]</span>');
            content = content.replace(/\[CRITICAL\]/g, '<span class="critical">[CRITICAL]</span>');
            content = content.replace(/\[ERROR\]/g, '<span class="error">[ERROR]</span>');
            content = content.replace(/\[WARNING\]/g, '<span class="warning">[WARNING]</span>');
            content = content.replace(/\[NOTICE\]/g, '<span class="notice">[NOTICE]</span>');
            content = content.replace(/\[INFO\]/g, '<span class="info">[INFO]</span>');
            content = content.replace(/\[DEBUG\]/g, '<span class="debug">[DEBUG]</span>');
            
            $('#ryvr-log-viewer').html(content);
        }
        
        // Initial colorize
        colorizeLogLevels();
        
        // Scroll to bottom of log viewer
        var logViewer = document.getElementById('ryvr-log-viewer');
        if (logViewer) {
            logViewer.scrollTop = logViewer.scrollHeight;
        }
    });

})(jQuery); 