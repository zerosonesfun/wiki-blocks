/**
 * Frontend JavaScript for Wiki Blocks
 *
 * @package WikiBlocks
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Wiki Blocks Frontend Class
    var WikiBlocksFrontend = {
        init: function() {
            this.bindEvents();
            this.initModals();
        },

        bindEvents: function() {
            // Bind click events to wiki block buttons
            $(document).on('click', '.wilcoskywb-wiki-suggest-btn', this.handleSuggestClick.bind(this));
            $(document).on('click', '.wilcoskywb-wiki-browse-btn', this.handleBrowseClick.bind(this));
            
            // Bind modal events
            $(document).on('click', '.wilcoskywb-wiki-modal-close, .wilcoskywb-wiki-modal-overlay', this.closeModal.bind(this));
            $(document).on('click', '.wilcoskywb-wiki-cancel-btn', this.closeModal.bind(this));
            
            // Bind form submission
            $(document).on('submit', '.wilcoskywb-wiki-suggest-form', this.handleSuggestSubmit.bind(this));
            
            // Bind merge button clicks
            $(document).on('click', '.wilcoskywb-wiki-merge-btn', this.handleMergeClick.bind(this));
            
            // Close modal on escape key
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27) { // ESC key
                    WikiBlocksFrontend.closeModal();
                }
            });
        },

        initModals: function() {
            // Initialize any existing modals
            $('.wilcoskywb-wiki-modal, .wilcoskywb-wiki-suggest-modal').hide();
            
            // Check if any wiki blocks exist on the page
            var $blocks = $('.wilcoskywb-wiki-block, .wp-block-wilcoskywb-wiki-block');
            
            if ($blocks.length > 0) {
                $blocks.each(function(index) {
                    var $block = $(this);
                    var blockId = $block.data('block-id');
                });
            }
        },

        handleSuggestClick: function(e) {
            e.preventDefault();
            var $block = $(e.target).closest('.wilcoskywb-wiki-block, .wp-block-wilcoskywb-wiki-block');
            var $modal = $block.find('.wilcoskywb-wiki-suggest-modal');
            
            // Pre-fill the content textarea with current content
            var currentContent = $block.find('.wilcoskywb-wiki-content').html();
            $modal.find('#wilcoskywb-wiki-content').val(currentContent);
            
            this.showModal($modal);
        },

        handleBrowseClick: function(e) {
            e.preventDefault();
            var $block = $(e.target).closest('.wilcoskywb-wiki-block, .wp-block-wilcoskywb-wiki-block');
            var $modal = $block.find('.wilcoskywb-wiki-modal');
            var blockId = $block.data('block-id');
            
            this.loadVersions(blockId, $modal);
        },

        loadVersions: function(blockId, $modal) {
            var self = this;
            var $versionsList = $modal.find('.wilcoskywb-wiki-versions-list');
            
            $versionsList.html('<p>' + wilcoskywbWikiBlocksFrontend.strings.loading + '</p>');
            this.showModal($modal);

            $.ajax({
                url: wilcoskywbWikiBlocksFrontend.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wilcoskywb_wiki_blocks_get_versions',
                    block_id: blockId,
                    nonce: wilcoskywbWikiBlocksFrontend.nonces.getVersions
                },
                success: function(response) {
                    if (response.success) {
                        self.renderVersions(response.data.versions, response.data.can_merge, $versionsList);
                    } else {
                        $versionsList.html('<p class="error">' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    $versionsList.html('<p class="error">' + wilcoskywbWikiBlocksFrontend.strings.error + '</p>');
                }
            });
        },

        renderVersions: function(versions, canMerge, $container) {
            if (!versions || versions.length === 0) {
                $container.html('<div class="wilcoskywb-wiki-empty-state">' +
                    '<p>' + (wilcoskywbWikiBlocksFrontend.strings.noVersions || 'No versions found.') + '</p>' +
                    '<p class="wilcoskywb-wiki-empty-hint">' + (wilcoskywbWikiBlocksFrontend.strings.noVersionsHint || 'Be the first to suggest a change!') + '</p>' +
                    '</div>');
                return;
            }

            var html = '<div class="wilcoskywb-wiki-versions">';
            
            versions.forEach(function(version) {
                // Ensure current version gets proper class and styling
                var versionClass = version.is_current ? 'wilcoskywb-wiki-version-current' : 'wilcoskywb-wiki-version';
                var currentBadge = version.is_current ? '<span class="wilcoskywb-wiki-current-badge">' + wilcoskywbWikiBlocksFrontend.strings.current + '</span>' : '';
                
                // Create excerpt (first 150 characters) - same for all versions including current
                var excerpt = version.content.replace(/<[^>]*>/g, '').substring(0, 150);
                if (version.content.replace(/<[^>]*>/g, '').length > 150) {
                    excerpt += '...';
                }
                
                html += '<div class="' + versionClass + '" data-version-id="' + version.id + '">';
                html += '<div class="wilcoskywb-wiki-version-header">';
                html += '<div class="wilcoskywb-wiki-version-info">';
                html += '<span class="wilcoskywb-wiki-version-number">v' + version.version_number + '</span>';
                html += currentBadge;
                html += '</div>';
                html += '<div class="wilcoskywb-wiki-version-meta">';
                html += '<img src="' + version.user.avatar_url + '" alt="" class="wilcoskywb-wiki-user-avatar">';
                html += '<span class="wilcoskywb-wiki-user-name">' + version.user.display_name + '</span>';
                html += '<span class="wilcoskywb-wiki-version-date">' + wilcoskywbWikiBlocksFrontend.strings.on + ' ' + this.formatDate(version.created_at) + '</span>';
                html += '</div>';
                html += '</div>';
                
                if (version.change_summary) {
                    html += '<div class="wilcoskywb-wiki-version-summary">';
                    html += '<strong>' + wilcoskywbWikiBlocksFrontend.strings.by + ' ' + version.user.display_name + ':</strong> ' + version.change_summary;
                    html += '</div>';
                }
                
                html += '<div class="wilcoskywb-wiki-version-content-excerpt">';
                html += '<p>' + excerpt + '</p>';
                html += '<button type="button" class="wilcoskywb-wiki-expand-btn">' + (wilcoskywbWikiBlocksFrontend.strings.readMore || 'Read Full Version') + '</button>';
                html += '</div>';
                
                html += '<div class="wilcoskywb-wiki-version-content-full" style="display: none;">';
                html += '<div class="wilcoskywb-wiki-version-content-inner">' + version.content + '</div>';
                html += '<div class="wilcoskywb-wiki-version-full-actions">';
                if (canMerge && !version.is_current) {
                    html += '<button type="button" class="wilcoskywb-wiki-merge-btn" data-version-id="' + version.id + '">' + wilcoskywbWikiBlocksFrontend.strings.merge + '</button>';
                }
                html += '<button type="button" class="wilcoskywb-wiki-collapse-btn">' + (wilcoskywbWikiBlocksFrontend.strings.readLess || 'Show Less') + '</button>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            }.bind(this));
            
            html += '</div>';
            $container.html(html);
            
            // Bind expand/collapse events
            this.bindVersionEvents($container);
        },

        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        },

        bindVersionEvents: function($container) {
            var self = this;
            
            // Bind expand/collapse events
            $container.on('click', '.wilcoskywb-wiki-expand-btn', function(e) {
                e.preventDefault();
                var $version = $(this).closest('.wilcoskywb-wiki-version, .wilcoskywb-wiki-version-current');
                $version.find('.wilcoskywb-wiki-version-content-excerpt').hide();
                $version.find('.wilcoskywb-wiki-version-content-full').show();
            });
            
            $container.on('click', '.wilcoskywb-wiki-collapse-btn', function(e) {
                e.preventDefault();
                var $version = $(this).closest('.wilcoskywb-wiki-version, .wilcoskywb-wiki-version-current');
                $version.find('.wilcoskywb-wiki-version-content-full').hide();
                $version.find('.wilcoskywb-wiki-version-content-excerpt').show();
            });
            
            // Bind merge events
            $container.on('click', '.wilcoskywb-wiki-merge-btn', function(e) {
                self.handleMergeClick(e);
            });
        },

        handleSuggestSubmit: function(e) {
            e.preventDefault();
            var $form = $(e.target);
            var $block = $form.closest('.wilcoskywb-wiki-block, .wp-block-wilcoskywb-wiki-block');
            var blockId = $block.data('block-id');
            var $submitBtn = $form.find('.wilcoskywb-wiki-submit-btn');
            var originalText = $submitBtn.text();
            
            // Validate form
            var content = $form.find('#wilcoskywb-wiki-content').val().trim();
            var summary = $form.find('#wilcoskywb-wiki-change-summary').val().trim();
            
            if (!content) {
                this.showMessage('Content is required.', 'error');
                return;
            }
            
            if (!summary) {
                this.showMessage('Change summary is required.', 'error');
                return;
            }
            
            // Add loading state
            $block.addClass('wilcoskywb-wiki-loading');
            $submitBtn.prop('disabled', true).text(wilcoskywbWikiBlocksFrontend.strings.loading);
            
            $.ajax({
                url: wilcoskywbWikiBlocksFrontend.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wilcoskywb_wiki_blocks_suggest_change',
                    block_id: blockId,
                    content: content,
                    change_summary: summary,
                    nonce: wilcoskywbWikiBlocksFrontend.nonces.suggestChange
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message - don't update content since it's just a suggestion
                        this.showMessage(wilcoskywbWikiBlocksFrontend.strings.suggestSuccess || 'Your suggestion has been submitted successfully!', 'success');
                        this.closeModal();
                    } else {
                        this.showMessage(response.data.message || 'An error occurred while submitting your suggestion.', 'error');
                    }
                }.bind(this),
                error: function(xhr, status, error) {
                    this.showMessage(wilcoskywbWikiBlocksFrontend.strings.error || 'An error occurred. Please try again.', 'error');
                }.bind(this),
                complete: function() {
                    $block.removeClass('wilcoskywb-wiki-loading');
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },

        handleMergeClick: function(e) {
            e.preventDefault();
            var $btn = $(e.target);
            var versionId = $btn.data('version-id');
            var $block = $btn.closest('.wilcoskywb-wiki-block, .wp-block-wilcoskywb-wiki-block');
            var blockId = $block.data('block-id');
            
            if (!confirm(wilcoskywbWikiBlocksFrontend.strings.mergeConfirm)) {
                return;
            }
            
            $btn.prop('disabled', true).text(wilcoskywbWikiBlocksFrontend.strings.loading);
            
            $.ajax({
                url: wilcoskywbWikiBlocksFrontend.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wilcoskywb_wiki_blocks_merge_version',
                    version_id: versionId,
                    block_id: blockId,
                    nonce: wilcoskywbWikiBlocksFrontend.nonces.mergeVersion
                },
                success: function(response) {
                    if (response.success) {
                        // Reload the page to show updated content
                        location.reload();
                    } else {
                        this.showMessage(response.data.message, 'error');
                        $btn.prop('disabled', false).text(wilcoskywbWikiBlocksFrontend.strings.merge);
                    }
                }.bind(this),
                error: function() {
                    this.showMessage(wilcoskywbWikiBlocksFrontend.strings.error, 'error');
                    $btn.prop('disabled', false).text(wilcoskywbWikiBlocksFrontend.strings.merge);
                }.bind(this)
            });
        },

        showModal: function($modal) {
            $modal.show();
            $('body').addClass('wilcoskywb-wiki-modal-open');
            
            // Focus on first input if exists
            $modal.find('input, textarea').first().focus();
        },

        closeModal: function() {
            $('.wilcoskywb-wiki-modal, .wilcoskywb-wiki-suggest-modal').hide();
            $('body').removeClass('wilcoskywb-wiki-modal-open');
        },

        showMessage: function(message, type) {
            // Create a temporary message element
            var $message = $('<div class="wilcoskywb-wiki-message wilcoskywb-wiki-message-' + type + '">' + message + '</div>');
            $('body').append($message);
            
            // Show the message
            setTimeout(function() {
                $message.addClass('wilcoskywb-wiki-message-show');
            }, 100);
            
            // Remove the message after 3 seconds
            setTimeout(function() {
                $message.removeClass('wilcoskywb-wiki-message-show');
                setTimeout(function() {
                    $message.remove();
                }, 300);
            }, 3000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WikiBlocksFrontend.init();
    });

})(jQuery); 