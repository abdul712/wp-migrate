/**
 * WP Migrate Admin JavaScript
 *
 * @package WPMigrate
 * @subpackage Assets
 */

// Import styles
import '../scss/admin.scss';

// Global namespace
window.wpMigrate = window.wpMigrate || {};

(function($, wpMigrate) {
    'use strict';

    /**
     * Admin functionality
     */
    wpMigrate.admin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initWizard();
            this.initTables();
            this.initModals();
            this.loadDashboardStats();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Connection form submission
            $(document).on('submit', '#wp-migrate-connection-form', this.handleConnectionForm);
            
            // Test connection button
            $(document).on('click', '.wp-migrate-test-connection', this.testConnection);
            
            // Migration start button
            $(document).on('click', '.wp-migrate-start-migration', this.startMigration);
            
            // Backup creation
            $(document).on('click', '.wp-migrate-create-backup', this.createBackup);
            
            // Delete actions
            $(document).on('click', '.wp-migrate-delete', this.confirmDelete);
            
            // File upload handling
            $(document).on('change', '.wp-migrate-file-upload', this.handleFileUpload);
            
            // Progress monitoring
            this.monitorProgress();
        },
        
        /**
         * Initialize migration wizard
         */
        initWizard: function() {
            const $wizard = $('.wp-migrate-migration-wizard');
            if (!$wizard.length) return;
            
            const $steps = $wizard.find('.wp-migrate-step');
            const $nextBtn = $('.wp-migrate-next-step');
            const $prevBtn = $('.wp-migrate-prev-step');
            const $startBtn = $('.wp-migrate-start-migration');
            
            let currentStep = 1;
            
            // Next step handler
            $nextBtn.on('click', function() {
                if (wpMigrate.admin.validateStep(currentStep)) {
                    currentStep++;
                    wpMigrate.admin.showStep(currentStep, $steps, $nextBtn, $prevBtn, $startBtn);
                }
            });
            
            // Previous step handler
            $prevBtn.on('click', function() {
                currentStep--;
                wpMigrate.admin.showStep(currentStep, $steps, $nextBtn, $prevBtn, $startBtn);
            });
            
            // Migration type change
            $wizard.on('change', 'input[name="migration_type"]', function() {
                wpMigrate.admin.updateMigrationConfig($(this).val());
            });
        },
        
        /**
         * Show wizard step
         */
        showStep: function(step, $steps, $nextBtn, $prevBtn, $startBtn) {
            $steps.removeClass('wp-migrate-step-active');
            $steps.filter('[data-step="' + step + '"]').addClass('wp-migrate-step-active');
            
            // Update navigation buttons
            $prevBtn.toggle(step > 1);
            
            if (step < $steps.length) {
                $nextBtn.show();
                $startBtn.hide();
            } else {
                $nextBtn.hide();
                $startBtn.show();
            }
            
            // Update review content on last step
            if (step === $steps.length) {
                this.updateReviewContent();
            }
        },
        
        /**
         * Validate wizard step
         */
        validateStep: function(step) {
            let isValid = true;
            
            switch (step) {
                case 1:
                    // Validate migration type selection
                    if (!$('input[name="migration_type"]:checked').length) {
                        this.showError('Please select a migration type');
                        isValid = false;
                    }
                    break;
                    
                case 2:
                    // Validate connection selection
                    if (!$('input[name="connection_id"]:checked').length) {
                        this.showError('Please select a connection');
                        isValid = false;
                    }
                    break;
                    
                case 3:
                    // Validate migration configuration
                    isValid = this.validateMigrationConfig();
                    break;
            }
            
            return isValid;
        },
        
        /**
         * Update migration configuration based on type
         */
        updateMigrationConfig: function(type) {
            const $config = $('.wp-migrate-migration-config');
            
            // Show/hide relevant configuration options
            $config.find('.config-section').hide();
            $config.find('.config-section[data-type="' + type + '"]').show();
            $config.find('.config-section[data-type="common"]').show();
        },
        
        /**
         * Validate migration configuration
         */
        validateMigrationConfig: function() {
            let isValid = true;
            
            // Validate URL replacements
            $('.url-replacement-row').each(function() {
                const fromUrl = $(this).find('input[name="from_url[]"]').val();
                const toUrl = $(this).find('input[name="to_url[]"]').val();
                
                if ((fromUrl && !toUrl) || (!fromUrl && toUrl)) {
                    wpMigrate.admin.showError('Please complete all URL replacement pairs');
                    isValid = false;
                    return false;
                }
            });
            
            return isValid;
        },
        
        /**
         * Update review content
         */
        updateReviewContent: function() {
            const migrationType = $('input[name="migration_type"]:checked').val();
            const connectionId = $('input[name="connection_id"]:checked').val();
            const connectionName = $('input[name="connection_id"]:checked').closest('.connection-card').find('h4').text();
            
            const reviewHtml = `
                <h4>Migration Summary</h4>
                <p><strong>Type:</strong> ${migrationType}</p>
                <p><strong>Target:</strong> ${connectionName}</p>
                <p><strong>URL Replacements:</strong></p>
                <ul id="review-url-replacements"></ul>
            `;
            
            $('#wp-migrate-review-content').html(reviewHtml);
            
            // Add URL replacements to review
            const $urlList = $('#review-url-replacements');
            $('.url-replacement-row').each(function() {
                const fromUrl = $(this).find('input[name="from_url[]"]').val();
                const toUrl = $(this).find('input[name="to_url[]"]').val();
                
                if (fromUrl && toUrl) {
                    $urlList.append(`<li>${fromUrl} → ${toUrl}</li>`);
                }
            });
        },
        
        /**
         * Initialize data tables
         */
        initTables: function() {
            // Sortable tables
            $('.wp-migrate-table').each(function() {
                // Add sorting functionality
                $(this).find('th[data-sort]').addClass('sortable').on('click', function() {
                    wpMigrate.admin.sortTable($(this));
                });
            });
            
            // Pagination
            $('.wp-migrate-pagination a').on('click', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                wpMigrate.admin.loadTablePage(page);
            });
        },
        
        /**
         * Sort table
         */
        sortTable: function($header) {
            const $table = $header.closest('table');
            const column = $header.data('sort');
            const direction = $header.hasClass('asc') ? 'desc' : 'asc';
            
            // Update header classes
            $table.find('th').removeClass('asc desc');
            $header.addClass(direction);
            
            // Sort rows
            const $rows = $table.find('tbody tr').get();
            $rows.sort(function(a, b) {
                const aVal = $(a).find('td').eq($header.index()).text();
                const bVal = $(b).find('td').eq($header.index()).text();
                
                if (direction === 'asc') {
                    return aVal.localeCompare(bVal);
                } else {
                    return bVal.localeCompare(aVal);
                }
            });
            
            $table.find('tbody').html($rows);
        },
        
        /**
         * Initialize modals
         */
        initModals: function() {
            // Open modal buttons
            $('.wp-migrate-add-connection').on('click', function() {
                $('#wp-migrate-connection-modal').show();
            });
            
            // Close modal buttons
            $('.wp-migrate-modal-close, .wp-migrate-modal-overlay').on('click', function() {
                $(this).closest('.wp-migrate-modal').hide();
            });
            
            // Prevent modal close when clicking inside content
            $('.wp-migrate-modal-content').on('click', function(e) {
                e.stopPropagation();
            });
        },
        
        /**
         * Handle connection form submission
         */
        handleConnectionForm: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const formData = new FormData($form[0]);
            formData.append('action', 'wp_migrate_action');
            formData.append('action_type', 'save_connection');
            formData.append('nonce', wpMigrate.nonce);
            
            wpMigrate.admin.showSpinner($form);
            
            $.ajax({
                url: wpMigrate.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        wpMigrate.admin.showSuccess('Connection saved successfully');
                        $('#wp-migrate-connection-modal').hide();
                        location.reload(); // Refresh to show new connection
                    } else {
                        wpMigrate.admin.showError(response.data.message || 'Error saving connection');
                    }
                },
                error: function() {
                    wpMigrate.admin.showError('Network error occurred');
                },
                complete: function() {
                    wpMigrate.admin.hideSpinner($form);
                }
            });
        },
        
        /**
         * Test connection
         */
        testConnection: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const connectionId = $button.data('connection-id');
            
            $button.prop('disabled', true).text('Testing...');
            
            $.ajax({
                url: wpMigrate.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_migrate_action',
                    action_type: 'test_connection',
                    connection_id: connectionId,
                    nonce: wpMigrate.nonce
                },
                success: function(response) {
                    if (response.success) {
                        wpMigrate.admin.showSuccess(wpMigrate.strings.connection_test_success);
                    } else {
                        wpMigrate.admin.showError(response.data.message || wpMigrate.strings.connection_test_failed);
                    }
                },
                error: function() {
                    wpMigrate.admin.showError('Network error occurred');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        },
        
        /**
         * Start migration
         */
        startMigration: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to start the migration?')) {
                return;
            }
            
            const migrationData = wpMigrate.admin.collectMigrationData();
            
            $.ajax({
                url: wpMigrate.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_migrate_action',
                    action_type: 'start_migration',
                    migration_data: migrationData,
                    nonce: wpMigrate.nonce
                },
                success: function(response) {
                    if (response.success) {
                        wpMigrate.admin.showSuccess(wpMigrate.strings.migration_started);
                        wpMigrate.admin.showProgressModal();
                        wpMigrate.admin.pollMigrationStatus();
                    } else {
                        wpMigrate.admin.showError(response.data.message || wpMigrate.strings.migration_failed);
                    }
                },
                error: function() {
                    wpMigrate.admin.showError('Network error occurred');
                }
            });
        },
        
        /**
         * Collect migration data from form
         */
        collectMigrationData: function() {
            const data = {
                type: $('input[name="migration_type"]:checked').val(),
                connection_id: $('input[name="connection_id"]:checked').val(),
                url_replacements: {}
            };
            
            // Collect URL replacements
            $('.url-replacement-row').each(function() {
                const fromUrl = $(this).find('input[name="from_url[]"]').val();
                const toUrl = $(this).find('input[name="to_url[]"]').val();
                
                if (fromUrl && toUrl) {
                    data.url_replacements[fromUrl] = toUrl;
                }
            });
            
            return data;
        },
        
        /**
         * Create backup
         */
        createBackup: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            $button.prop('disabled', true).text('Creating Backup...');
            
            $.ajax({
                url: wpMigrate.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_migrate_action',
                    action_type: 'create_backup',
                    nonce: wpMigrate.nonce
                },
                success: function(response) {
                    if (response.success) {
                        wpMigrate.admin.showSuccess('Backup created successfully');
                    } else {
                        wpMigrate.admin.showError(response.data.message || 'Error creating backup');
                    }
                },
                error: function() {
                    wpMigrate.admin.showError('Network error occurred');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Create Backup');
                }
            });
        },
        
        /**
         * Confirm delete action
         */
        confirmDelete: function(e) {
            e.preventDefault();
            
            if (!confirm(wpMigrate.strings.confirm_delete)) {
                return;
            }
            
            const $link = $(this);
            const actionUrl = $link.attr('href');
            
            window.location.href = actionUrl;
        },
        
        /**
         * Handle file upload
         */
        handleFileUpload: function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const $input = $(this);
            const maxSize = 50 * 1024 * 1024; // 50MB
            
            if (file.size > maxSize) {
                wpMigrate.admin.showError('File size exceeds maximum limit');
                $input.val('');
                return;
            }
            
            // Update UI to show selected file
            $input.next('.file-name').text(file.name);
        },
        
        /**
         * Show progress modal
         */
        showProgressModal: function() {
            const modalHtml = `
                <div id="wp-migrate-progress-modal" class="wp-migrate-modal">
                    <div class="wp-migrate-modal-content">
                        <h3>Migration in Progress</h3>
                        <div class="wp-migrate-progress-bar">
                            <div class="progress-fill" style="width: 0%"></div>
                        </div>
                        <p class="progress-message">Initializing migration...</p>
                        <p class="progress-percentage">0%</p>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            $('#wp-migrate-progress-modal').show();
        },
        
        /**
         * Poll migration status
         */
        pollMigrationStatus: function() {
            const poll = function() {
                $.ajax({
                    url: wpMigrate.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'wp_migrate_action',
                        action_type: 'get_migration_status',
                        nonce: wpMigrate.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            const status = response.data;
                            wpMigrate.admin.updateProgress(status.progress, status.message);
                            
                            if (status.status === 'completed') {
                                wpMigrate.admin.migrationCompleted(true);
                            } else if (status.status === 'failed') {
                                wpMigrate.admin.migrationCompleted(false, status.error);
                            } else {
                                setTimeout(poll, 2000); // Poll every 2 seconds
                            }
                        }
                    },
                    error: function() {
                        setTimeout(poll, 5000); // Retry after 5 seconds on error
                    }
                });
            };
            
            setTimeout(poll, 1000); // Start polling after 1 second
        },
        
        /**
         * Update progress display
         */
        updateProgress: function(percentage, message) {
            $('#wp-migrate-progress-modal .progress-fill').css('width', percentage + '%');
            $('#wp-migrate-progress-modal .progress-message').text(message);
            $('#wp-migrate-progress-modal .progress-percentage').text(percentage + '%');
        },
        
        /**
         * Handle migration completion
         */
        migrationCompleted: function(success, error) {
            const $modal = $('#wp-migrate-progress-modal');
            
            if (success) {
                $modal.find('h3').text('Migration Completed');
                $modal.find('.progress-message').text('Migration completed successfully!');
                wpMigrate.admin.showSuccess('Migration completed successfully');
            } else {
                $modal.find('h3').text('Migration Failed');
                $modal.find('.progress-message').text('Migration failed: ' + (error || 'Unknown error'));
                wpMigrate.admin.showError('Migration failed: ' + (error || 'Unknown error'));
            }
            
            // Add close button
            $modal.find('.wp-migrate-modal-content').append(
                '<button type="button" class="button wp-migrate-close-progress">Close</button>'
            );
            
            $('.wp-migrate-close-progress').on('click', function() {
                $modal.remove();
                location.reload(); // Refresh page to update data
            });
        },
        
        /**
         * Monitor long-running processes
         */
        monitorProgress: function() {
            // Check for any ongoing migrations on page load
            if ($('.wp-migrate-progress-indicator').length) {
                this.pollMigrationStatus();
            }
        },
        
        /**
         * Load dashboard statistics
         */
        loadDashboardStats: function() {
            if (!$('.wp-migrate-dashboard').length) return;
            
            // Update stats via AJAX for real-time data
            $.ajax({
                url: wpMigrate.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_migrate_action',
                    action_type: 'get_dashboard_stats',
                    nonce: wpMigrate.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const stats = response.data;
                        $('.stat-number').each(function() {
                            const statType = $(this).closest('.stat-item').find('.stat-label').text().toLowerCase();
                            // Update stats based on response data
                        });
                    }
                }
            });
        },
        
        /**
         * Load table page
         */
        loadTablePage: function(page) {
            // Implement table pagination loading
            console.log('Loading page:', page);
        },
        
        /**
         * Utility functions
         */
        showSpinner: function($element) {
            $element.addClass('wp-migrate-loading');
        },
        
        hideSpinner: function($element) {
            $element.removeClass('wp-migrate-loading');
        },
        
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },
        
        showError: function(message) {
            this.showNotice(message, 'error');
        },
        
        showNotice: function(message, type) {
            const noticeHtml = `
                <div class="notice notice-${type} is-dismissible wp-migrate-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `;
            
            $('.wp-migrate-notices').html(noticeHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $('.wp-migrate-notice').fadeOut();
            }, 5000);
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        wpMigrate.admin.init();
    });
    
})(jQuery, window.wpMigrate);