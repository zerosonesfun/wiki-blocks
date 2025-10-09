/**
 * Admin JavaScript for Wiki Blocks
 *
 * @package WikiBlocks
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Wiki Blocks Admin Class
    var WikiBlocksAdmin = {
        init: function() {
            this.bindEvents();
            this.loadStats();
        },

        bindEvents: function() {
            // Bind cleanup buttons with standard click events
            $('#wilcoskywb-wiki-blocks-cleanup').on('click', this.handleCleanup.bind(this));
            $('#wilcoskywb-wiki-blocks-cleanup-orphaned').on('click', this.handleOrphanedCleanup.bind(this));
            $('#wilcoskywb-wiki-blocks-cleanup-activity').on('click', this.handleActivityCleanup.bind(this));
        },

        loadStats: function() {
            var self = this;
                    var $statsContainer = $('#wilcoskywb-wiki-blocks-stats');
        
        $statsContainer.html('<p>' + wilcoskywbWikiBlocksAdmin.strings.loading + '</p>');
        
        $.ajax({
            url: wilcoskywbWikiBlocksAdmin.ajaxUrl,
            type: 'POST',
            cache: false, // Disable browser cache for AJAX
            data: {
                action: 'wilcoskywb_wiki_blocks_admin_get_stats',
                nonce: wilcoskywbWikiBlocksAdmin.nonce,
                _timestamp: new Date().getTime() // Cache-busting parameter
            },
                success: function(response) {
                    if (response.success) {
                        self.renderStats(response.data, $statsContainer);
                    } else {
                        $statsContainer.html('<p class="error">' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    $statsContainer.html('<p class="error">' + wilcoskywbWikiBlocksAdmin.strings.error + '</p>');
                }
            });
        },

        renderStats: function(stats, $container) {
            var html = '<div class="wilcoskywb-wiki-blocks-stats-grid">';
            
            // Total versions
            html += '<div class="wilcoskywb-wiki-blocks-stat-item">';
            html += '<div class="wilcoskywb-wiki-blocks-stat-number">' + stats.total_versions + '</div>';
            html += '<div class="wilcoskywb-wiki-blocks-stat-label">' + 'Total Versions' + '</div>';
            html += '</div>';
            
            // Total blocks
            html += '<div class="wilcoskywb-wiki-blocks-stat-item">';
            html += '<div class="wilcoskywb-wiki-blocks-stat-number">' + stats.total_blocks + '</div>';
            html += '<div class="wilcoskywb-wiki-blocks-stat-label">' + 'Wiki Blocks' + '</div>';
            html += '</div>';
            
            // Total users
            html += '<div class="wilcoskywb-wiki-blocks-stat-item">';
            html += '<div class="wilcoskywb-wiki-blocks-stat-number">' + stats.total_users + '</div>';
            html += '<div class="wilcoskywb-wiki-blocks-stat-label">' + 'Contributors' + '</div>';
            html += '</div>';
            
            html += '</div>';

            // Recent activity
            if (stats.recent_activity && stats.recent_activity.length > 0) {
                html += '<div class="wilcoskywb-wiki-blocks-recent-activity">';
                html += '<h4>Recent Activity</h4>';
                html += '<ul>';
                
                stats.recent_activity.forEach(function(activity) {
                    var postTitle = activity.post_title || 'Unknown Post';
                    var userName = activity.display_name || 'Unknown User';
                    var date = new Date(activity.created_at).toLocaleDateString();
                    
                    html += '<li>';
                    if (activity.post_title) {
                        html += '<strong>' + userName + '</strong> suggested a change to a wiki block in <em>' + postTitle + '</em>';
                    } else {
                        html += '<strong>' + userName + '</strong> suggested a change to <em>Wiki Block</em>';
                    }
                    html += '<br><small>' + date + '</small>';
                    html += '</li>';
                });
                
                html += '</ul>';
                html += '</div>';
            } else {
                html += '<p>No recent activity.</p>';
            }

            $container.html(html);
        },

        handleCleanup: function(e) {
            e.preventDefault();
            var self = this;
            var $btn = $(e.currentTarget); // Use currentTarget instead of target
            var originalText = $btn.text();
            
            // Use custom confirm modal instead of browser confirm
            this.showConfirm(
                wilcoskywbWikiBlocksAdmin.strings.confirmCleanup,
                function() {
                    // User confirmed - proceed with cleanup
                    $btn.prop('disabled', true).text(wilcoskywbWikiBlocksAdmin.strings.loading);
            
                    $.ajax({
                        url: wilcoskywbWikiBlocksAdmin.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wilcoskywb_wiki_blocks_admin_cleanup_versions',
                            nonce: wilcoskywbWikiBlocksAdmin.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Use the actual message from the server (shows how many deleted)
                                self.showNotice(response.data.message || wilcoskywbWikiBlocksAdmin.strings.cleanupSuccess, 'success');
                                self.loadStats(); // Reload stats
                            } else {
                                self.showNotice(response.data.message || wilcoskywbWikiBlocksAdmin.strings.cleanupError, 'error');
                            }
                        },
                        error: function() {
                            self.showNotice(wilcoskywbWikiBlocksAdmin.strings.cleanupError, 'error');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text(originalText);
                        }
                    });
                }
            );
        },

        handleOrphanedCleanup: function(e) {
            e.preventDefault();
            
            var self = this;
            var $btn = $(e.currentTarget); // Use currentTarget instead of target
            var originalText = $btn.text();
            
            // Use custom confirm modal instead of browser confirm
            this.showConfirm(
                wilcoskywbWikiBlocksAdmin.strings.orphanedCleanupConfirm,
                function() {
                    // User confirmed - proceed with cleanup
                    $btn.prop('disabled', true).text(wilcoskywbWikiBlocksAdmin.strings.loading);
            
                    $.ajax({
                        url: wilcoskywbWikiBlocksAdmin.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wilcoskywb_wiki_blocks_admin_cleanup_orphaned',
                            nonce: wilcoskywbWikiBlocksAdmin.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Use the actual message from the server (shows how many deleted)
                                self.showNotice(response.data.message || wilcoskywbWikiBlocksAdmin.strings.cleanupSuccess, 'success');
                                self.loadStats(); // Reload stats
                            } else {
                                self.showNotice(response.data.message || wilcoskywbWikiBlocksAdmin.strings.cleanupError, 'error');
                            }
                        },
                        error: function() {
                            self.showNotice(wilcoskywbWikiBlocksAdmin.strings.cleanupError, 'error');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text(originalText);
                        }
                    });
                }
            );
        },

        handleActivityCleanup: function(e) {
            e.preventDefault();
            
            var self = this;
            var $btn = $(e.currentTarget); // Use currentTarget instead of target
            var originalText = $btn.text();
            
            // Use custom confirm modal instead of browser confirm
            this.showConfirm(
                wilcoskywbWikiBlocksAdmin.strings.activityCleanupConfirm,
                function() {
                    // User confirmed - proceed with cleanup
                    $btn.prop('disabled', true).text(wilcoskywbWikiBlocksAdmin.strings.loading);
            
                    $.ajax({
                        url: wilcoskywbWikiBlocksAdmin.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wilcoskywb_wiki_blocks_admin_cleanup_old_activity',
                            nonce: wilcoskywbWikiBlocksAdmin.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                // Use the actual message from the server (shows how many deleted)
                                self.showNotice(response.data.message || wilcoskywbWikiBlocksAdmin.strings.cleanupSuccess, 'success');
                                self.loadStats(); // Reload stats
                            } else {
                                self.showNotice(response.data.message || wilcoskywbWikiBlocksAdmin.strings.cleanupError, 'error');
                            }
                        },
                        error: function() {
                            self.showNotice(wilcoskywbWikiBlocksAdmin.strings.cleanupError, 'error');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text(originalText);
                        }
                    });
                }
            );
        },

        showNotice: function(message, type) {
            if (!message) return;
            
            // Create notice element with dismissible functionality
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>');
            
            // Try multiple selectors to find the right place to insert
            var $target = $('.wrap > h1').first();
            if (!$target.length) {
                $target = $('.wrap h1').first();
            }
            if (!$target.length) {
                $target = $('#wpbody-content .wrap').first();
            }
            
            if ($target.length) {
                // Remove any existing cleanup notices to avoid duplicates
                $('.notice.wilcoskywb-cleanup-notice').remove();
                
                // Add a custom class to track our notices
                $notice.addClass('wilcoskywb-cleanup-notice');
                
                // Insert notice
                $target.after($notice);
                
                // Scroll to notice
                $('html, body').animate({
                    scrollTop: $notice.offset().top - 100
                }, 300);
                
                // Bind dismiss button
                $notice.find('.notice-dismiss').on('click', function() {
                    $notice.fadeOut(function() {
                        $(this).remove();
                    });
                });
                
                // Auto-dismiss after 8 seconds
                setTimeout(function() {
                    if ($notice.is(':visible')) {
                        $notice.fadeOut(function() {
                            $(this).remove();
                        });
                    }
                }, 8000);
            } else {
                // Fallback: use alert if we can't find where to insert notice
                alert(message);
            }
        },

        showConfirm: function(message, onConfirm, onCancel) {
            var self = this;
            
            // Create confirm modal HTML with ARIA attributes for accessibility
            var confirmHtml = '<div class="wilcoskywb-admin-confirm-modal" style="display: flex;" role="dialog" aria-modal="true" aria-labelledby="wilcoskywb-admin-confirm-title">' +
                '<div class="wilcoskywb-admin-confirm-overlay" aria-hidden="true"></div>' +
                '<div class="wilcoskywb-admin-confirm-content">' +
                '<p id="wilcoskywb-admin-confirm-title" class="wilcoskywb-admin-confirm-message">' + message + '</p>' +
                '<div class="wilcoskywb-admin-confirm-actions">' +
                '<button type="button" class="button wilcoskywb-admin-confirm-cancel" aria-label="Cancel">Cancel</button>' +
                '<button type="button" class="button button-primary wilcoskywb-admin-confirm-ok" aria-label="Confirm">OK</button>' +
                '</div>' +
                '</div>' +
                '</div>';
            
            // Add to body
            var $confirmModal = $(confirmHtml);
            $('body').append($confirmModal);
            
            // Bind confirm button
            $confirmModal.find('.wilcoskywb-admin-confirm-ok').on('click', function() {
                $confirmModal.remove();
                $('body').css('overflow', ''); // Restore scrolling
                $(document).off('keydown.wilcoskywb-admin-confirm');
                if (onConfirm) {
                    onConfirm();
                }
            });
            
            // Bind cancel button and overlay
            $confirmModal.find('.wilcoskywb-admin-confirm-cancel, .wilcoskywb-admin-confirm-overlay').on('click', function() {
                $confirmModal.remove();
                $('body').css('overflow', ''); // Restore scrolling
                $(document).off('keydown.wilcoskywb-admin-confirm');
                if (onCancel) {
                    onCancel();
                }
            });
            
            // Handle Escape key to close
            $(document).on('keydown.wilcoskywb-admin-confirm', function(e) {
                if (e.keyCode === 27) { // ESC key
                    $confirmModal.remove();
                    $('body').css('overflow', ''); // Restore scrolling
                    $(document).off('keydown.wilcoskywb-admin-confirm');
                    if (onCancel) {
                        onCancel();
                    }
                }
            });
            
            // Prevent body scrolling when modal is open
            $('body').css('overflow', 'hidden');
            
            // Focus OK button for keyboard accessibility
            setTimeout(function() {
                $confirmModal.find('.wilcoskywb-admin-confirm-ok').focus();
            }, 100);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WikiBlocksAdmin.init();
    });

})(jQuery); 