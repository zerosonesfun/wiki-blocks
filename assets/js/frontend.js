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
        mergeInProgress: false, // Flag to prevent multiple merge operations
        
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
            
            // Initialize WYSIWYG editor after modal is shown
            setTimeout(() => {
                this.initWysiwygEditor($modal.find('#wilcoskywb-wiki-content'));
            }, 100);
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
            var $btn = $(e.currentTarget);
            
            // Prevent double-clicks and multiple merge operations
            if ($btn.prop('disabled') || this.mergeInProgress) {
                return;
            }
            
            var versionId = $btn.data('version-id');
            var $block = $btn.closest('.wilcoskywb-wiki-block, .wp-block-wilcoskywb-wiki-block');
            var blockId = $block.data('block-id');
            
            if (!confirm(wilcoskywbWikiBlocksFrontend.strings.mergeConfirm)) {
                return;
            }
            
            // Set flag and disable button immediately to prevent double-clicks
            this.mergeInProgress = true;
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
                        this.mergeInProgress = false;
                        $btn.prop('disabled', false).text(wilcoskywbWikiBlocksFrontend.strings.merge);
                    }
                }.bind(this),
                error: function() {
                    this.showMessage(wilcoskywbWikiBlocksFrontend.strings.error, 'error');
                    this.mergeInProgress = false;
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
        },

        // Initialize lightweight WYSIWYG editor
        initWysiwygEditor: function($textarea) {
            if (!$textarea.length || $textarea.data('wysiwyg-initialized')) {
                return;
            }

            var self = this;
            var $container = $textarea.closest('.wilcoskywb-wiki-form-group');
            
            // Create editor wrapper
            var $editorWrapper = $('<div class="wilcoskywb-wysiwyg-editor"></div>');
            var $toolbar = $('<div class="wilcoskywb-wysiwyg-toolbar"></div>');
            var $contentArea = $('<div class="wilcoskywb-wysiwyg-content" contenteditable="true" role="textbox" aria-label="Rich text editor" tabindex="0"></div>');
            
            // Add toolbar buttons with proper accessibility attributes
            $toolbar.append('<button type="button" class="wilcoskywb-wysiwyg-btn" data-command="bold" title="Bold" aria-label="Make text bold" tabindex="0">B</button>');
            $toolbar.append('<button type="button" class="wilcoskywb-wysiwyg-btn" data-command="italic" title="Italic" aria-label="Make text italic" tabindex="0">I</button>');
            $toolbar.append('<button type="button" class="wilcoskywb-wysiwyg-btn" data-command="underline" title="Underline" aria-label="Underline text" tabindex="0">U</button>');
            $toolbar.append('<span class="wilcoskywb-wysiwyg-separator" role="separator" aria-hidden="true"></span>');
            $toolbar.append('<button type="button" class="wilcoskywb-wysiwyg-btn" data-command="insertUnorderedList" title="Bullet List" aria-label="Insert bullet list" tabindex="0">•</button>');
            $toolbar.append('<button type="button" class="wilcoskywb-wysiwyg-btn" data-command="insertOrderedList" title="Numbered List" aria-label="Insert numbered list" tabindex="0">1.</button>');
            $toolbar.append('<span class="wilcoskywb-wysiwyg-separator" role="separator" aria-hidden="true"></span>');
            $toolbar.append('<button type="button" class="wilcoskywb-wysiwyg-btn" data-command="createLink" title="Insert Link" aria-label="Insert or edit link" tabindex="0"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg></button>');
            $toolbar.append('<button type="button" class="wilcoskywb-wysiwyg-btn" data-command="insertImage" title="Insert Image" aria-label="Insert image from media library" tabindex="0"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg></button>');
            
            // Assemble editor
            $editorWrapper.append($toolbar);
            $editorWrapper.append($contentArea);
            
            // Insert editor after textarea
            $textarea.after($editorWrapper);
            
            // Hide original textarea
            $textarea.hide();
            
            // Initialize content
            var initialContent = $textarea.val();
            $contentArea.html(this.convertHtmlToEditable(initialContent));
            
            // Bind toolbar events
            $toolbar.on('click', '.wilcoskywb-wysiwyg-btn', function(e) {
                e.preventDefault();
                self.handleToolbarCommand($(this), $contentArea);
            });
            
            // Add keyboard navigation for toolbar buttons
            $toolbar.on('keydown', '.wilcoskywb-wysiwyg-btn', function(e) {
                if (e.which === 13 || e.which === 32) { // Enter or Space
                    e.preventDefault();
                    self.handleToolbarCommand($(this), $contentArea);
                }
            });
            
            // Sync content changes back to textarea
            $contentArea.on('input', function() {
                var content = $contentArea.html();
                $textarea.val(content);
            });
            
            // Handle paste events to clean up content
            $contentArea.on('paste', function(e) {
                setTimeout(function() {
                    var content = $contentArea.html();
                    $contentArea.html(self.cleanPastedContent(content));
                    $textarea.val($contentArea.html());
                }, 10);
            });
            
            // Mark as initialized
            $textarea.data('wysiwyg-initialized', true);
        },

        // Handle toolbar command execution
        handleToolbarCommand: function($btn, $contentArea) {
            // Store current selection before any operations
            var selection = window.getSelection();
            var range = selection.rangeCount > 0 ? selection.getRangeAt(0) : null;
            
            var command = $btn.data('command');
            if (command === 'createLink') {
                // Don't change focus, just open modal
                this.showLinkModal($contentArea);
            } else if (command === 'insertImage') {
                // Don't change focus, just open modal
                this.showImageModal($contentArea);
            } else {
                // For other commands, restore selection first
                if (range) {
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
                document.execCommand(command, false, null);
                $contentArea.focus();
            }
        },

        // Convert HTML content to editable format
        convertHtmlToEditable: function(html) {
            // Clean up the HTML for editing
            var $temp = $('<div>').html(html);
            
            // Handle wiki images
            $temp.find('.wilcoskywb-wiki-image').each(function() {
                var $img = $(this).find('img');
                var $caption = $(this).find('.wilcoskywb-wiki-image-caption');
                var imgHtml = '<div class="wilcoskywb-wiki-image">' + $img.prop('outerHTML');
                if ($caption.length) {
                    imgHtml += '<p class="wilcoskywb-wiki-image-caption">' + $caption.text() + '</p>';
                }
                imgHtml += '</div>';
                $(this).replaceWith(imgHtml);
            });
            
            return $temp.html();
        },

        // Clean pasted content
        cleanPastedContent: function(html) {
            // Remove unwanted attributes and clean up
            var $temp = $('<div>').html(html);
            
            // Remove style attributes that might cause issues
            $temp.find('*').removeAttr('style');
            $temp.find('*').removeAttr('class');
            
            // Preserve only essential formatting
            $temp.find('strong, b').each(function() {
                $(this).replaceWith('<strong>' + $(this).text() + '</strong>');
            });
            
            $temp.find('em, i').each(function() {
                $(this).replaceWith('<em>' + $(this).text() + '</em>');
            });
            
            $temp.find('u').each(function() {
                $(this).replaceWith('<u>' + $(this).text() + '</u>');
            });
            
            return $temp.html();
        },

        // Show link modal for creating/editing links
        showLinkModal: function($contentArea) {
            var self = this;
            
            // Store the current selection and cursor position
            var selection = window.getSelection();
            var range = null;
            var selectedText = '';
            var $existingLink = null;
            var existingUrl = '';
            var existingText = '';
            var isExternal = false;
            
            // Check if there's a valid selection within the content area
            if (selection.rangeCount > 0) {
                range = selection.getRangeAt(0);
                
                // Check if selection is within our content area
                var isWithinContentArea = $contentArea[0].contains(range.commonAncestorContainer) || 
                                        $contentArea[0].contains(range.startContainer) || 
                                        $contentArea[0].contains(range.endContainer);
                
                if (isWithinContentArea) {
                    selectedText = range.toString().trim();
                    
                    // Check if we're clicking on an existing link
                    var container = range.commonAncestorContainer;
                    var $container = $(container.nodeType === 3 ? container.parentNode : container);
                    $existingLink = $container.closest('a');
                    
                    if ($existingLink.length) {
                        existingUrl = $existingLink.attr('href') || '';
                        existingText = $existingLink.text();
                        isExternal = existingUrl.indexOf('http') === 0;
                    }
                } else {
                    // Selection is outside content area, clear it
                    range = null;
                }
            }
            
            // Create link modal HTML
            var hasExistingLink = $existingLink && $existingLink.length;
            var linkModalHtml = `
                <div class="wilcoskywb-link-modal-overlay">
                    <div class="wilcoskywb-link-modal">
                        <div class="wilcoskywb-link-modal-header">
                            <h4>${hasExistingLink ? 'Edit Link' : 'Insert Link'}</h4>
                            <button type="button" class="wilcoskywb-link-modal-close">&times;</button>
                        </div>
                        <div class="wilcoskywb-link-modal-body">
                            <div class="wilcoskywb-link-form-group">
                                <label for="wilcoskywb-link-url">URL</label>
                                <input type="url" id="wilcoskywb-link-url" value="${existingUrl}" placeholder="https://example.com">
                            </div>
                            <div class="wilcoskywb-link-form-group">
                                <label for="wilcoskywb-link-text">Link Text</label>
                                <input type="text" id="wilcoskywb-link-text" value="${hasExistingLink ? existingText : selectedText}" placeholder="Link text">
                            </div>
                            <div class="wilcoskywb-link-form-group">
                                <label>
                                    <input type="checkbox" id="wilcoskywb-link-external" ${isExternal ? 'checked' : ''}> 
                                    Open in new tab (external links)
                                </label>
                            </div>
                        </div>
                        <div class="wilcoskywb-link-modal-footer">
                            <button type="button" class="wilcoskywb-link-cancel">Cancel</button>
                            <button type="button" class="wilcoskywb-link-insert">${hasExistingLink ? 'Update' : 'Insert'} Link</button>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to page
            $('body').append(linkModalHtml);
            var $linkModal = $('.wilcoskywb-link-modal-overlay');
            
            // Focus on URL input
            setTimeout(function() {
                $linkModal.find('#wilcoskywb-link-url').focus();
            }, 100);
            
            // Bind events
            $linkModal.on('click', '.wilcoskywb-link-modal-close, .wilcoskywb-link-cancel', function() {
                $linkModal.remove();
            });
            
            $linkModal.on('click', '.wilcoskywb-link-modal-overlay', function(e) {
                if (e.target === this) {
                    $linkModal.remove();
                }
            });
            
            $linkModal.on('click', '.wilcoskywb-link-insert', function() {
                var url = $linkModal.find('#wilcoskywb-link-url').val().trim();
                var text = $linkModal.find('#wilcoskywb-link-text').val().trim();
                var isExternal = $linkModal.find('#wilcoskywb-link-external').is(':checked');
                
                if (!url) {
                    alert('Please enter a URL');
                    return;
                }
                
                if (!text) {
                    text = url;
                }
                
                // Ensure URL has protocol for external links
                if (isExternal && !url.match(/^https?:\/\//)) {
                    url = 'https://' + url;
                }
                
                // Create link HTML - escape attributes to prevent XSS
                var escapeHtml = function(text) {
                    var div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                };
                
                var linkHtml = '<a href="' + escapeHtml(url) + '"' + (isExternal ? ' target="_blank" rel="noopener noreferrer"' : '') + '>' + escapeHtml(text) + '</a>';
                
                if ($existingLink && $existingLink.length) {
                    // Update existing link
                    $existingLink.replaceWith(linkHtml);
                } else {
                    // Create new link - try to preserve selection/range
                    if (range && range.toString()) {
                        // There was selected text, replace it
                        range.deleteContents();
                        var tempDiv = document.createElement('div');
                        tempDiv.innerHTML = linkHtml;
                        var linkNode = tempDiv.firstChild;
                        range.insertNode(linkNode);
                        
                        // Position cursor after the link
                        range.setStartAfter(linkNode);
                        range.setEndAfter(linkNode);
                        selection.removeAllRanges();
                        selection.addRange(range);
                    } else {
                        // No selection, insert at cursor position
                        document.execCommand('insertHTML', false, linkHtml);
                    }
                }
                
                // Update textarea
                $contentArea.trigger('input');
                
                $linkModal.remove();
                $contentArea.focus();
            });
            
            // Handle Enter key in inputs
            $linkModal.on('keypress', 'input', function(e) {
                if (e.which === 13) { // Enter key
                    $linkModal.find('.wilcoskywb-link-insert').click();
                }
            });
        },

        // Show image modal for inserting images from WordPress Media Library
        showImageModal: function($contentArea) {
            var self = this;
            
            // Check if WordPress media library is available
            if (typeof wp === 'undefined' || !wp.media) {
                alert('Media library is not available. Please refresh the page and try again.');
                return;
            }
            
            // Create media library frame
            var frame = wp.media({
                title: 'Select Image',
                button: {
                    text: 'Insert Image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            
            // Ensure media library appears above the suggest modal
            frame.on('open', function() {
                $('.media-modal').css('z-index', '999999');
                $('.media-modal-backdrop').css('z-index', '999998');
            });
            
            // Handle selection
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                
                if (attachment) {
                    // Escape HTML attributes to prevent XSS
                    var escapeHtml = function(text) {
                        var div = document.createElement('div');
                        div.textContent = text;
                        return div.innerHTML;
                    };
                    
                    var imageHtml = '<div class="wilcoskywb-wiki-image">' +
                        '<img src="' + escapeHtml(attachment.url) + '" alt="' + escapeHtml(attachment.alt || '') + '" />';
                    
                    if (attachment.caption) {
                        imageHtml += '<p class="wilcoskywb-wiki-image-caption">' + escapeHtml(attachment.caption) + '</p>';
                    }
                    
                    imageHtml += '</div>';
                    
                    // Insert at cursor position
                    document.execCommand('insertHTML', false, imageHtml);
                    
                    // Update textarea
                    $contentArea.trigger('input');
                }
                
                $contentArea.focus();
            });
            
            // Open the media library
            frame.open();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WikiBlocksFrontend.init();
    });

})(jQuery); 