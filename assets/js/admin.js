/**
 * Ryvr AI Platform Admin Scripts
 */

(function($) {
    'use strict';

    /**
     * Task Status Polling
     * Automatically updates task status in the UI
     */
    var TaskStatusPoller = {
        init: function() {
            this.taskStatusElements = $('.ryvr-task-status-poll');
            this.taskViewContainer = $('#ryvr-task-view');
            this.pollingInterval = 5000; // 5 seconds
            this.pollingActive = false;
            this.taskPolls = {};

            if (this.taskStatusElements.length > 0) {
                this.initPolling();
            }

            // Initialize single task view polling if present
            if (this.taskViewContainer.length > 0 && this.taskViewContainer.data('task-id')) {
                this.initTaskViewPolling();
            }
        },

        initPolling: function() {
            var self = this;

            // Set up polling for each task status element
            this.taskStatusElements.each(function() {
                var $element = $(this);
                var taskId = $element.data('task-id');
                var taskStatus = $element.data('task-status');

                // Only poll active tasks
                if (self.isActiveStatus(taskStatus)) {
                    self.taskPolls[taskId] = {
                        element: $element,
                        status: taskStatus
                    };
                }
            });

            if (Object.keys(this.taskPolls).length > 0) {
                this.startPolling();
            }
        },

        initTaskViewPolling: function() {
            var taskId = this.taskViewContainer.data('task-id');
            var taskStatus = this.taskViewContainer.data('task-status');

            if (this.isActiveStatus(taskStatus)) {
                this.startTaskViewPolling(taskId);
            }
        },

        startPolling: function() {
            if (this.pollingActive) {
                return;
            }

            var self = this;
            this.pollingActive = true;

            this.pollingTimer = setInterval(function() {
                self.pollTaskStatuses();
            }, this.pollingInterval);
        },

        stopPolling: function() {
            this.pollingActive = false;
            clearInterval(this.pollingTimer);
        },

        startTaskViewPolling: function(taskId) {
            var self = this;
            
            // Clear any existing polling
            if (this.taskViewPollingTimer) {
                clearInterval(this.taskViewPollingTimer);
            }
            
            // Start polling for task view
            this.taskViewPollingTimer = setInterval(function() {
                self.pollTaskView(taskId);
            }, this.pollingInterval);
        },

        pollTaskStatuses: function() {
            var self = this;
            var taskIds = Object.keys(this.taskPolls);
            
            if (taskIds.length === 0) {
                this.stopPolling();
                return;
            }

            // Poll each active task
            $.each(taskIds, function(index, taskId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ryvr_get_task_status',
                        task_id: taskId,
                        nonce: ryvrData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            self.updateTaskStatus(taskId, response.data);
                        }
                    }
                });
            });
        },

        pollTaskView: function(taskId) {
            var self = this;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ryvr_get_task_status',
                    task_id: taskId,
                    nonce: ryvrData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateTaskView(response.data);
                    }
                }
            });
        },

        updateTaskStatus: function(taskId, data) {
            var taskPoll = this.taskPolls[taskId];
            
            if (!taskPoll) {
                return;
            }

            // Update the status
            taskPoll.element.text(data.statusText);
            taskPoll.element.attr('class', 'ryvr-task-status ' + data.statusClass);
            taskPoll.status = data.status;

            // Remove from polling if no longer active
            if (!this.isActiveStatus(data.status)) {
                delete this.taskPolls[taskId];
                
                // Refresh the page if task is completed
                if (data.status === 'completed') {
                    window.location.reload();
                }
            }
        },

        updateTaskView: function(data) {
            // Update task status
            $('.ryvr-task-status').text(data.statusText).attr('class', 'ryvr-task-status ' + data.statusClass);
            
            // Update outputs if available
            if (data.outputs) {
                var $outputsContainer = $('#task-outputs');
                if ($outputsContainer.length > 0) {
                    this.renderTaskOutputs($outputsContainer, data.outputs);
                }
            }
            
            // Update logs
            var $logsContainer = $('#task-logs');
            if ($logsContainer.length > 0 && data.logs && data.logs.length > 0) {
                this.renderTaskLogs($logsContainer, data.logs);
            }
            
            // Stop polling if task is no longer active
            if (!this.isActiveStatus(data.status)) {
                clearInterval(this.taskViewPollingTimer);
                
                // Show success message if completed
                if (data.status === 'completed') {
                    $('#task-complete-message').show();
                }
            }
        },

        renderTaskOutputs: function($container, outputs) {
            // Implementation will depend on output structure
            // This is a simple example
            var html = '<div class="ryvr-task-output">';
            
            if (typeof outputs === 'object') {
                html += '<pre>' + JSON.stringify(outputs, null, 2) + '</pre>';
            } else {
                html += '<p>' + outputs + '</p>';
            }
            
            html += '</div>';
            
            $container.html(html);
        },

        renderTaskLogs: function($container, logs) {
            var html = '';
            
            $.each(logs, function(index, log) {
                var logClass = 'ryvr-log-' + log.log_level;
                html += '<div class="ryvr-log-entry ' + logClass + '">';
                html += '<span class="ryvr-log-timestamp">' + log.created_at + '</span>';
                html += '<div class="ryvr-log-message">' + log.message + '</div>';
                html += '</div>';
            });
            
            $container.html(html);
            
            // Scroll to bottom
            $container.scrollTop($container[0].scrollHeight);
        },

        isActiveStatus: function(status) {
            return ['pending', 'approval_required', 'processing'].indexOf(status) !== -1;
        }
    };

    /**
     * Task Actions
     * Handles task cancellation, approval, etc.
     */
    var TaskActions = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Cancel task
            $(document).on('click', '.ryvr-cancel-task', this.handleCancelTask);
            
            // Approve task
            $(document).on('click', '.ryvr-approve-task', this.handleApproveTask);
        },

        handleCancelTask: function(e) {
            e.preventDefault();
            
            var taskId = $(this).data('task-id');
            
            if (!taskId) {
                return;
            }
            
            if (!confirm(ryvrData.strings.confirmCancel)) {
                return;
            }
            
            var $button = $(this);
            $button.prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ryvr_cancel_task',
                    task_id: taskId,
                    nonce: ryvrData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Refresh the page or update UI
                        window.location.reload();
                    } else {
                        alert(response.data.message || ryvrData.strings.error);
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert(ryvrData.strings.error);
                    $button.prop('disabled', false);
                }
            });
        },

        handleApproveTask: function(e) {
            e.preventDefault();
            
            var taskId = $(this).data('task-id');
            
            if (!taskId) {
                return;
            }
            
            if (!confirm(ryvrData.strings.confirmApprove)) {
                return;
            }
            
            var $button = $(this);
            $button.prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ryvr_approve_task',
                    task_id: taskId,
                    nonce: ryvrData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Refresh the page or update UI
                        window.location.reload();
                    } else {
                        alert(response.data.message || ryvrData.strings.error);
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    alert(ryvrData.strings.error);
                    $button.prop('disabled', false);
                }
            });
        }
    };

    /**
     * Task Creation Form
     * Handles initialization and processing of the new task form
     */
    var TaskForm = {
        init: function() {
            this.form = $('#ryvr-new-task-form');
            
            if (this.form.length > 0) {
                this.bindEvents();
                this.handleKeywordsInput();
            }
        },

        bindEvents: function() {
            var self = this;
            
            // Handle form submission via AJAX
            this.form.on('submit', function(e) {
                // Form validation and submission is handled by the inline script in the template
            });
            
            // Process special fields
            $('#keywords').on('change', function() {
                self.processKeywordsInput($(this));
            });
            
            $('#competitors').on('change', function() {
                self.processMultilineInput($(this));
            });
        },

        handleKeywordsInput: function() {
            var keywordsInput = $('#keywords');
            
            if (keywordsInput.length > 0) {
                this.processKeywordsInput(keywordsInput);
            }
        },

        processKeywordsInput: function($input) {
            var value = $input.val();
            
            if (!value) {
                return;
            }
            
            // Convert keywords string to array
            var keywords = value.split(',').map(function(keyword) {
                return keyword.trim();
            }).filter(function(keyword) {
                return keyword.length > 0;
            });
            
            // Create hidden inputs for each keyword
            this.createHiddenInputs('inputs[keywords][]', keywords);
        },

        processMultilineInput: function($input) {
            var value = $input.val();
            
            if (!value) {
                return;
            }
            
            // Convert multiline string to array
            var items = value.split('\n').map(function(item) {
                return item.trim();
            }).filter(function(item) {
                return item.length > 0;
            });
            
            // Create hidden inputs for each item
            var name = $input.attr('name') + '[]';
            this.createHiddenInputs(name, items);
        },

        createHiddenInputs: function(name, values) {
            // Remove existing hidden inputs
            $('.hidden-input-' + name.replace(/[\[\]]/g, '-')).remove();
            
            // Create hidden inputs
            $.each(values, function(index, value) {
                $('<input>').attr({
                    type: 'hidden',
                    name: name,
                    value: value,
                    class: 'hidden-input-' + name.replace(/[\[\]]/g, '-')
                }).appendTo('form');
            });
        }
    };

    /**
     * Content Preview
     * Handles previewing generated content
     */
    var ContentPreview = {
        init: function() {
            this.previewContainer = $('#content-preview');
            this.contentContainer = $('#content-output');
            
            if (this.previewContainer.length > 0 && this.contentContainer.length > 0) {
                this.initMarkdownPreview();
            }
        },

        initMarkdownPreview: function() {
            var content = this.contentContainer.text();
            
            if (!content) {
                return;
            }
            
            // If using a markdown library like Marked, you would do something like:
            // var html = marked(content);
            // this.previewContainer.html(html);
            
            // Basic markdown conversion for demonstration
            var html = this.simpleMarkdown(content);
            this.previewContainer.html(html);
        },

        simpleMarkdown: function(text) {
            // Very simple markdown conversion (for demonstration only)
            // In a real implementation, use a proper markdown library
            
            // Headers
            text = text.replace(/^# (.*?)$/gm, '<h1>$1</h1>');
            text = text.replace(/^## (.*?)$/gm, '<h2>$1</h2>');
            text = text.replace(/^### (.*?)$/gm, '<h3>$1</h3>');
            
            // Bold and Italic
            text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');
            
            // Links
            text = text.replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2">$1</a>');
            
            // Lists
            text = text.replace(/^\* (.*?)$/gm, '<li>$1</li>');
            text = text.replace(/(<li>.*?<\/li>)\n+(?=<li>)/g, '$1');
            text = text.replace(/(?:^\s*<li>.*?<\/li>\n?)+/gm, function(match) {
                return '<ul>' + match + '</ul>';
            });
            
            // Paragraphs
            text = text.replace(/^(?!<[a-z])(.*?)$/gm, '<p>$1</p>');
            text = text.replace(/<p><\/p>/g, '');
            
            return text;
        }
    };

    /**
     * Initialize all components when DOM is ready
     */
    $(document).ready(function() {
        TaskStatusPoller.init();
        TaskActions.init();
        TaskForm.init();
        ContentPreview.init();
    });

})(jQuery); 