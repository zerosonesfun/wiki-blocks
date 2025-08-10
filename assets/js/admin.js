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
            // Bind cleanup button
            $('#wilcoskywb-wiki-blocks-cleanup').on('click', this.handleCleanup.bind(this));
        },

        loadStats: function() {
            var self = this;
                    var $statsContainer = $('#wilcoskywb-wiki-blocks-stats');
        
        $statsContainer.html('<p>' + wilcoskywbWikiBlocksAdmin.strings.loading + '</p>');
        
        $.ajax({
            url: wilcoskywbWikiBlocksAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wilcoskywb_wiki_blocks_admin_get_stats',
                nonce: wilcoskywbWikiBlocksAdmin.nonce
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
            var $btn = $(e.target);
            var originalText = $btn.text();
            
            if (!confirm(wilcoskywbWikiBlocksAdmin.strings.confirmCleanup)) {
                return;
            }
            
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
                        self.showNotice(wilcoskywbWikiBlocksAdmin.strings.cleanupSuccess, 'success');
                        self.loadStats(); // Reload stats
                    } else {
                        self.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    self.showNotice(wilcoskywbWikiBlocksAdmin.strings.cleanupError, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        showNotice: function(message, type) {
            // Create notice element
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Insert at the top of the page
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WikiBlocksAdmin.init();
    });

})(jQuery); 