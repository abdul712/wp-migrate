/**
 * WP Migrate Admin JavaScript
 * Handles admin interface interactions and AJAX operations
 */

(function($) {
    'use strict';

    var WPMigrateAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initProgressTracking();
            this.initConnectionTesting();
        },
        
        bindEvents: function() {
            // Test connection button
            $(document).on('click', '.wp-migrate-test-connection', this.testConnection);
            
            // Start migration button
            $(document).on('click', '.wp-migrate-start-migration', this.startMigration);
            
            // Delete confirmation
            $(document).on('click', '.wp-migrate-delete-item', this.confirmDelete);
            
            // Form validation
            $(document).on('submit', '.wp-migrate-form', this.validateForm);
            
            // File upload handling
            $(document).on('change', '.wp-migrate-file-input', this.handleFileUpload);
            
            // Progress tracking
            $(document).on('click', '.wp-migrate-check-progress', this.checkProgress);
            
            // Wizard navigation
            $(document).on('click', '.wp-migrate-wizard-next', this.wizardNext);
            $(document).on('click', '.wp-migrate-wizard-prev', this.wizardPrev);
        },
        
        testConnection: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var connectionId = $button.data('connection-id');
            var $status = $button.siblings('.connection-status');
            
            if (!connectionId) {
                alert('Invalid connection ID');
                return;
            }
            
            $button.prop('disabled', true).text(wpMigrateAdmin.strings.connectionTesting);
            $status.html('<span class="spinner is-active"></span>');
            
            $.ajax({
                url: wpMigrateAdmin.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wp_migrate_test_connection',
                    nonce: wpMigrateAdmin.nonce,
                    connection_id: connectionId
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span class="dashicons dashicons-yes-alt" style="color: green;"></span> Connected');
                        WPMigrateAdmin.showNotice('Connection test successful!', 'success');
                    } else {
                        $status.html('<span class="dashicons dashicons-dismiss" style="color: red;"></span> Failed');
                        WPMigrateAdmin.showNotice('Connection test failed: ' + response.error, 'error');
                    }
                },
                error: function() {
                    $status.html('<span class="dashicons dashicons-dismiss" style="color: red;"></span> Error');
                    WPMigrateAdmin.showNotice('Connection test error occurred', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        },
        
        startMigration: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var migrationType = $button.data('migration-type');
            var connectionId = $button.data('connection-id');
            var $progress = $('.migration-progress');
            
            if (!confirm('Are you sure you want to start this migration?')) {
                return;
            }
            
            $button.prop('disabled', true).text(wpMigrateAdmin.strings.migrationStarting);
            $progress.show();
            
            // Collect form options
            var options = {};
            $('.migration-option').each(function() {
                var $input = $(this);
                var name = $input.attr('name');
                
                if ($input.is(':checkbox')) {
                    options[name] = $input.is(':checked');
                } else {
                    options[name] = $input.val();
                }
            });
            
            $.ajax({
                url: wpMigrateAdmin.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wp_migrate_start_migration',
                    nonce: wpMigrateAdmin.nonce,
                    migration_type: migrationType,
                    connection_id: connectionId,
                    options: options
                },
                success: function(response) {
                    if (response.success) {
                        WPMigrateAdmin.showNotice('Migration started successfully!', 'success');
                        WPMigrateAdmin.trackMigrationProgress(response.migration_id);
                    } else {
                        WPMigrateAdmin.showNotice('Failed to start migration: ' + response.error, 'error');
                        $button.prop('disabled', false).text('Start Migration');
                        $progress.hide();
                    }
                },
                error: function() {
                    WPMigrateAdmin.showNotice('Error starting migration', 'error');
                    $button.prop('disabled', false).text('Start Migration');
                    $progress.hide();
                }
            });
        },
        
        trackMigrationProgress: function(migrationId) {
            var progressInterval = setInterval(function() {
                $.ajax({
                    url: wpMigrateAdmin.ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'wp_migrate_get_migration_status',
                        nonce: wpMigrateAdmin.nonce,
                        migration_id: migrationId
                    },
                    success: function(response) {
                        if (response.success) {
                            var progress = response.progress;
                            var status = response.status;
                            var message = response.message;
                            
                            $('.migration-progress-bar').css('width', progress + '%');
                            $('.migration-progress-text').text(message);
                            
                            if (status === 'completed') {
                                clearInterval(progressInterval);
                                WPMigrateAdmin.showNotice(wpMigrateAdmin.strings.migrationCompleted, 'success');
                                $('.wp-migrate-start-migration').prop('disabled', false).text('Start Migration');
                                
                                setTimeout(function() {
                                    $('.migration-progress').hide();
                                    location.reload();
                                }, 2000);
                                
                            } else if (status === 'failed') {
                                clearInterval(progressInterval);
                                WPMigrateAdmin.showNotice(wpMigrateAdmin.strings.migrationFailed + ': ' + message, 'error');
                                $('.wp-migrate-start-migration').prop('disabled', false).text('Start Migration');
                                $('.migration-progress').hide();
                            }
                        }
                    }
                });
            }, 2000); // Check every 2 seconds
        },
        
        confirmDelete: function(e) {
            if (!confirm(wpMigrateAdmin.strings.confirmDelete)) {
                e.preventDefault();
                return false;
            }
        },
        
        validateForm: function(e) {
            var $form = $(this);
            var isValid = true;
            var errors = [];
            
            // Check required fields
            $form.find('[required]').each(function() {
                var $field = $(this);
                if (!$field.val().trim()) {
                    isValid = false;
                    errors.push($field.attr('name') + ' is required');
                    $field.addClass('error');
                } else {
                    $field.removeClass('error');
                }
            });
            
            // URL validation
            $form.find('.url-field').each(function() {
                var $field = $(this);
                var url = $field.val().trim();
                
                if (url && !WPMigrateAdmin.isValidUrl(url)) {
                    isValid = false;
                    errors.push('Invalid URL format');
                    $field.addClass('error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                WPMigrateAdmin.showNotice('Please fix the following errors:\n' + errors.join('\n'), 'error');
            }
        },
        
        handleFileUpload: function(e) {
            var file = e.target.files[0];
            var $input = $(this);
            var $preview = $input.siblings('.file-preview');
            
            if (file) {
                var fileName = file.name;
                var fileSize = WPMigrateAdmin.formatFileSize(file.size);
                
                $preview.html('<strong>' + fileName + '</strong> (' + fileSize + ')').show();
            } else {
                $preview.hide();
            }
        },
        
        checkProgress: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var migrationId = $button.data('migration-id');
            
            if (!migrationId) {
                return;
            }
            
            WPMigrateAdmin.trackMigrationProgress(migrationId);
        },
        
        wizardNext: function(e) {
            e.preventDefault();
            
            var $currentStep = $('.wizard-step.active');
            var $nextStep = $currentStep.next('.wizard-step');
            
            if ($nextStep.length) {
                $currentStep.removeClass('active').addClass('completed');
                $nextStep.addClass('active');
                
                // Update step indicator
                var stepNumber = $nextStep.data('step');
                $('.step-indicator .step').removeClass('active').eq(stepNumber - 1).addClass('active');
            }
        },
        
        wizardPrev: function(e) {
            e.preventDefault();
            
            var $currentStep = $('.wizard-step.active');
            var $prevStep = $currentStep.prev('.wizard-step');
            
            if ($prevStep.length) {
                $currentStep.removeClass('active');
                $prevStep.removeClass('completed').addClass('active');
                
                // Update step indicator
                var stepNumber = $prevStep.data('step');
                $('.step-indicator .step').removeClass('active').eq(stepNumber - 1).addClass('active');
            }
        },
        
        initProgressTracking: function() {
            // Check for active migrations on page load
            var activeMigrations = $('.migration-item[data-status="in_progress"]');
            
            activeMigrations.each(function() {
                var migrationId = $(this).data('migration-id');
                if (migrationId) {
                    WPMigrateAdmin.trackMigrationProgress(migrationId);
                }
            });
        },
        
        initConnectionTesting: function() {
            // Auto-test connections on page load if enabled
            if ($('.auto-test-connections').length) {
                $('.wp-migrate-test-connection').each(function() {
                    $(this).trigger('click');
                });
            }
        },
        
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wp-migrate-notices').prepend($notice);
            
            // Auto-dismiss success notices
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        },
        
        isValidUrl: function(url) {
            var pattern = /^https?:\/\/[^\s/$.?#].[^\s]*$/;
            return pattern.test(url);
        },
        
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WPMigrateAdmin.init();
    });

    // Make WPMigrateAdmin globally accessible
    window.WPMigrateAdmin = WPMigrateAdmin;

})(jQuery);