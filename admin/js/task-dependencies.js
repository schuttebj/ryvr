/**
 * Task Dependencies and Priority Management
 *
 * JavaScript for handling task dependencies and priorities in the admin UI.
 */

(function($) {
    'use strict';

    // Initialize on document ready
    $(document).ready(function() {
        initTaskDependencyUI();
        initTaskPriorityUI();
    });

    /**
     * Initialize task dependency UI.
     */
    function initTaskDependencyUI() {
        // Add dependency button click handler
        $('.add-dependency-btn').on('click', function(e) {
            e.preventDefault();
            
            var taskId = $(this).data('task-id');
            var dependencySelector = $('#dependency-selector-' + taskId);
            var dependencyId = dependencySelector.val();
            
            if (!dependencyId) {
                alert(rvyrTaskDeps.messages.selectTask);
                return;
            }
            
            addTaskDependency(taskId, dependencyId);
        });
        
        // Remove dependency button click handler
        $(document).on('click', '.remove-dependency-btn', function(e) {
            e.preventDefault();
            
            var taskId = $(this).data('task-id');
            var dependencyId = $(this).data('dependency-id');
            
            removeTaskDependency(taskId, dependencyId);
        });
        
        // Initialize dependency selectors (uses select2 if available)
        if ($.fn.select2) {
            $('.dependency-selector').select2({
                placeholder: rvyrTaskDeps.messages.selectTaskPlaceholder,
                allowClear: true,
                width: '100%'
            });
        }
    }
    
    /**
     * Initialize task priority UI.
     */
    function initTaskPriorityUI() {
        // Priority slider changes
        $('.priority-slider').on('input change', function() {
            var value = $(this).val();
            var taskId = $(this).data('task-id');
            
            // Update displayed value
            $('#priority-value-' + taskId).text(value);
        });
        
        // Priority save button
        $('.save-priority-btn').on('click', function(e) {
            e.preventDefault();
            
            var taskId = $(this).data('task-id');
            var priorityValue = $('#priority-slider-' + taskId).val();
            
            updateTaskPriority(taskId, priorityValue);
        });
    }
    
    /**
     * Add a task dependency via AJAX.
     *
     * @param {number} taskId The task ID.
     * @param {number} dependencyId The dependency task ID.
     */
    function addTaskDependency(taskId, dependencyId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ryvr_add_task_dependency',
                task_id: taskId,
                dependency_id: dependencyId,
                nonce: rvyrTaskDeps.nonce
            },
            beforeSend: function() {
                // Show loading indicator
                $('#dependency-loader-' + taskId).show();
            },
            success: function(response) {
                if (response.success) {
                    // Refresh dependency list
                    refreshDependencyList(taskId);
                    
                    // Show success message
                    showMessage(response.data.message, 'success');
                } else {
                    // Show error message
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                // Show generic error message
                showMessage(rvyrTaskDeps.messages.ajaxError, 'error');
            },
            complete: function() {
                // Hide loading indicator
                $('#dependency-loader-' + taskId).hide();
            }
        });
    }
    
    /**
     * Remove a task dependency via AJAX.
     *
     * @param {number} taskId The task ID.
     * @param {number} dependencyId The dependency task ID.
     */
    function removeTaskDependency(taskId, dependencyId) {
        if (!confirm(rvyrTaskDeps.messages.confirmRemove)) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ryvr_remove_task_dependency',
                task_id: taskId,
                dependency_id: dependencyId,
                nonce: rvyrTaskDeps.nonce
            },
            beforeSend: function() {
                // Show loading indicator
                $('#dependency-loader-' + taskId).show();
            },
            success: function(response) {
                if (response.success) {
                    // Refresh dependency list
                    refreshDependencyList(taskId);
                    
                    // Show success message
                    showMessage(response.data.message, 'success');
                } else {
                    // Show error message
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                // Show generic error message
                showMessage(rvyrTaskDeps.messages.ajaxError, 'error');
            },
            complete: function() {
                // Hide loading indicator
                $('#dependency-loader-' + taskId).hide();
            }
        });
    }
    
    /**
     * Update task priority via AJAX.
     *
     * @param {number} taskId The task ID.
     * @param {number} priority The priority value.
     */
    function updateTaskPriority(taskId, priority) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ryvr_update_task_priority',
                task_id: taskId,
                priority: priority,
                nonce: rvyrTaskDeps.nonce
            },
            beforeSend: function() {
                // Show loading indicator
                $('#priority-loader-' + taskId).show();
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    showMessage(response.data.message, 'success');
                } else {
                    // Show error message
                    showMessage(response.data.message, 'error');
                }
            },
            error: function() {
                // Show generic error message
                showMessage(rvyrTaskDeps.messages.ajaxError, 'error');
            },
            complete: function() {
                // Hide loading indicator
                $('#priority-loader-' + taskId).hide();
            }
        });
    }
    
    /**
     * Refresh the dependency list for a task.
     *
     * @param {number} taskId The task ID.
     */
    function refreshDependencyList(taskId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ryvr_get_task_status',
                task_id: taskId,
                nonce: rvyrTaskDeps.nonce
            },
            success: function(response) {
                if (response.success && response.data.task) {
                    var task = response.data.task;
                    var dependencies = task.dependencies || [];
                    var html = '';
                    
                    if (dependencies.length === 0) {
                        html = '<p class="no-dependencies">' + rvyrTaskDeps.messages.noDependencies + '</p>';
                    } else {
                        html = '<ul class="dependencies-list">';
                        
                        for (var i = 0; i < dependencies.length; i++) {
                            var dep = dependencies[i];
                            html += '<li>';
                            html += '<span>' + dep.title + ' (#' + dep.id + ')</span>';
                            html += '<a href="#" class="remove-dependency-btn" data-task-id="' + taskId + '" data-dependency-id="' + dep.id + '">';
                            html += rvyrTaskDeps.messages.remove;
                            html += '</a>';
                            html += '</li>';
                        }
                        
                        html += '</ul>';
                    }
                    
                    $('#dependency-list-' + taskId).html(html);
                }
            }
        });
    }
    
    /**
     * Show a message to the user.
     *
     * @param {string} message The message text.
     * @param {string} type The message type (success, error).
     */
    function showMessage(message, type) {
        var noticeClass = 'notice-' + (type === 'error' ? 'error' : 'success');
        var html = '<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>';
        
        // Remove any existing notices
        $('.task-message-container .notice').remove();
        
        // Add the new notice
        $('.task-message-container').html(html).show();
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.task-message-container .notice').fadeOut();
        }, 5000);
    }

})(jQuery); 