/**
 * Editor JavaScript for Wiki Blocks
 *
 * @package WikiBlocks
 * @since 1.0.0
 */

(function() {
    'use strict';

    // Import WordPress dependencies
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { RichText, InspectorControls, ColorPalette, PanelColorSettings, BlockControls, AlignmentToolbar, MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { PanelBody, TextControl, CheckboxControl, SelectControl, Button, Notice, IconButton } = wp.components;
    const { __ } = wp.i18n;
    const { useSelect, useDispatch } = wp.data;

    // Function to extract images from content
    function extractImagesFromContent(content) {
        const images = [];
        
        // Validate content before parsing
        if (!content || typeof content !== 'string') {
            return images;
        }
        
        const parser = new DOMParser();
        const doc = parser.parseFromString(content, 'text/html');
        
        // Check for parsing errors
        const parserError = doc.querySelector('parsererror');
        if (parserError) {
            console.warn('Error parsing content for image extraction:', parserError.textContent);
            return images;
        }
        
        const imageDivs = doc.querySelectorAll('.wilcoskywb-wiki-image');
        
        imageDivs.forEach((div, index) => {
            const img = div.querySelector('img');
            const caption = div.querySelector('.wilcoskywb-wiki-image-caption');
            
            if (img) {
                // Generate a consistent ID based on the image URL to avoid duplicates
                const consistentId = 'temp_' + btoa(img.src).replace(/[^a-zA-Z0-9]/g, '').substring(0, 16);
                
                images.push({
                    id: consistentId,
                    url: img.src,
                    alt: img.alt || '',
                    caption: caption ? caption.textContent : '',
                });
            }
        });
        
        return images;
    }

    // Function to fetch current version content from database
    function fetchCurrentVersionContent(blockId, setAttributes) {
        if (!blockId) return;

        const formData = new FormData();
        formData.append('action', 'wilcoskywb_wiki_blocks_get_current_version');
        formData.append('block_id', blockId);
        formData.append('nonce', wilcoskywbWikiBlocks.nonces.getCurrentVersion);

        fetch(wilcoskywbWikiBlocks.ajaxUrl, {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.content) {
                const content = data.data.content;
                const images = extractImagesFromContent(content);
                
                // Update both content and images attributes
                setAttributes({ 
                    content: content,
                    images: images
                });
            }
        })
        .catch(error => {
            console.log('Error fetching current version:', error);
        });
    }

    // Function to add image to content
    function addImageToContent(media, setAttributes, content, images) {
        const newImage = {
            id: media.id,
            url: media.url,
            alt: media.alt || '',
            caption: media.caption || '',
        };
        
        const newImages = [...(images || []), newImage];
        setAttributes({ images: newImages });
        
        // Add image HTML to content - use simple, clean HTML that won't be corrupted
        // Escape HTML attributes to prevent XSS
        const escapeHtml = (text) => {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        };
        
        const imageHtml = `<div class="wilcoskywb-wiki-image">
<img src="${escapeHtml(media.url)}" alt="${escapeHtml(media.alt || '')}" />
${media.caption ? `<p class="wilcoskywb-wiki-image-caption">${escapeHtml(media.caption)}</p>` : ''}
</div>`;
        setAttributes({ content: content + imageHtml });
    }

    // Function to remove image
    function removeImage(imageId, setAttributes, images, content) {
        const newImages = images.filter(img => img.id !== imageId);
        setAttributes({ images: newImages });
        
        // Find the image to remove
        const imageToRemove = images.find(img => img.id === imageId);
        if (imageToRemove) {
            // Remove the entire image div from content
            const imageHtml = new RegExp(`<div class="wilcoskywb-wiki-image">[\\s\\S]*?<img src="${imageToRemove.url.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}"[^>]*>[\\s\\S]*?</div>`, 'g');
            const newContent = content.replace(imageHtml, '');
            setAttributes({ content: newContent });
        }
    }

    // Register the Wiki Block
    registerBlockType('wilcoskywb/wiki-block', {
        title: wilcoskywbWikiBlocks.strings.blockTitle,
        description: wilcoskywbWikiBlocks.strings.blockDescription,
        category: 'wilcoskywb-wiki-blocks',
        icon: 'admin-page',
        keywords: [
            __('wiki', 'wiki-blocks'),
            __('collaborative', 'wiki-blocks'),
            __('version', 'wiki-blocks'),
            __('edit', 'wiki-blocks'),
        ],
        supports: {
            html: false,
            align: true,
            color: {
                background: true,
                text: true,
            },
            fontSize: true,
        },
        attributes: {
            content: {
                type: 'string',
                default: '',
            },
            images: {
                type: 'array',
                default: [],
            },
            blockId: {
                type: 'string',
                default: '',
            },
            align: {
                type: 'string',
                default: '',
            },
            backgroundColor: {
                type: 'string',
                default: '',
            },
            textColor: {
                type: 'string',
                default: '',
            },
            fontSize: {
                type: 'string',
                default: '',
            },
        },

        edit: function(props) {
            const { attributes, setAttributes, clientId } = props;
            const { content, images, blockId, align, backgroundColor, textColor, fontSize } = attributes;

            // Track if we've fetched the current version to avoid infinite loops
            const hasFetchedCurrentVersion = React.useRef(false);
            
            // Track if we've extracted images for the current content to prevent duplicates
            const lastExtractedContent = React.useRef('');

            // Generate block ID if not set
            if (!blockId) {
                setAttributes({ blockId: 'wiki-block-' + clientId });
            }

            // Fetch current version content when block loads (only for existing blocks with a persistent blockId)
            React.useEffect(() => {
                if (blockId && blockId.startsWith('wiki-block-') && !hasFetchedCurrentVersion.current) {
                    hasFetchedCurrentVersion.current = true;
                    fetchCurrentVersionContent(blockId, setAttributes);
                }
            }, [blockId]);

            // Extract images from content if images array is empty but content has images
            React.useEffect(() => {
                if (content && (!images || images.length === 0) && content !== lastExtractedContent.current) {
                    const extractedImages = extractImagesFromContent(content);
                    if (extractedImages.length > 0) {
                        setAttributes({ images: extractedImages });
                        lastExtractedContent.current = content;
                    }
                }
            }, [content, images]);

            // Block style object
            const blockStyle = {};
            if (backgroundColor) {
                blockStyle.backgroundColor = backgroundColor;
            }
            if (textColor) {
                blockStyle.color = textColor;
            }

            return el(Fragment, {},
                // Block Controls
                el(BlockControls, {},
                    el(AlignmentToolbar, {
                        value: align,
                        onChange: (newAlign) => setAttributes({ align: newAlign }),
                    })
                ),

                // Inspector Controls
                el(InspectorControls, {},
                    el(PanelBody, {
                        title: wilcoskywbWikiBlocks.strings.settingsTitle,
                        initialOpen: true,
                    },
                        el(WikiBlockSettings, {
                            blockId: blockId,
                        })
                    ),
                    el(PanelColorSettings, {
                        title: __('Color settings', 'wiki-blocks'),
                        colorSettings: [
                            {
                                value: backgroundColor,
                                onChange: (color) => setAttributes({ backgroundColor: color }),
                                label: __('Background color', 'wiki-blocks'),
                            },
                            {
                                value: textColor,
                                onChange: (color) => setAttributes({ textColor: color }),
                                label: __('Text color', 'wiki-blocks'),
                            },
                        ],
                    })
                ),

                // Block Controls (Toolbar)
                el(BlockControls, {},
                    el(MediaUploadCheck, {},
                        el(MediaUpload, {
                            onSelect: (media) => addImageToContent(media, setAttributes, content, images || []),
                            allowedTypes: ['image'],
                            value: images ? images.map(img => img.id) : [],
                            render: ({ open }) => el(IconButton, {
                                onClick: open,
                                icon: 'format-image',
                                label: __('Add Image from Media Library', 'wiki-blocks'),
                                className: 'components-button components-toolbar__control'
                            })
                        })
                    )
                ),

                // Block Content
                el('div', {
                    className: 'wilcoskywb-wiki-block-editor',
                    style: blockStyle,
                },
                    el(RichText, {
                        tagName: 'div',
                        className: 'wilcoskywb-wiki-content',
                        value: content,
                        onChange: (newContent) => setAttributes({ content: newContent }),
                        placeholder: wilcoskywbWikiBlocks.strings.contentPlaceholder,
                        allowedFormats: ['core/bold', 'core/italic', 'core/link', 'core/strikethrough'],
                    }),
                    
                    // Display current images
                    images && images.length > 0 && el('div', {
                        className: 'wilcoskywb-wiki-images',
                        style: { marginTop: '15px' }
                    }, images.map(image => el('div', {
                        key: image.id,
                        style: { 
                            display: 'inline-block', 
                            margin: '5px',
                            position: 'relative'
                        }
                    }, [
                        el('img', {
                            src: image.url,
                            alt: image.alt,
                            style: { 
                                maxWidth: '150px', 
                                height: 'auto',
                                border: '1px solid #ddd',
                                borderRadius: '4px'
                            }
                        }),
                        el(Button, {
                            onClick: () => removeImage(image.id, setAttributes, images, content),
                            className: 'components-button is-destructive is-small',
                            style: { 
                                position: 'absolute',
                                top: '5px',
                                right: '5px',
                                minWidth: '20px',
                                height: '20px',
                                padding: '0'
                            }
                        }, '×')
                    ]))),
                    el('div', {
                        className: 'wilcoskywb-wiki-editor-notice',
                    },
                        el('p', {
                            style: {
                                fontSize: '0.875em',
                                color: '#666',
                                fontStyle: 'italic',
                                margin: '0.5em 0 0 0',
                            },
                        },
                            __('This is a wiki block. Users can suggest changes and browse version history on the frontend. Use the settings panel to configure permissions for this specific block.', 'wiki-blocks')
                        )
                    )
                )
            );
        },

        save: function(props) {
            const { attributes } = props;
            const { content, images, blockId, align, backgroundColor, textColor, fontSize } = attributes;

            // Build CSS classes
            const classes = ['wp-block-wilcoskywb-wiki-block', 'wilcoskywb-wiki-block'];
            if (align) {
                classes.push('align' + align);
            }
            if (backgroundColor) {
                classes.push('has-background');
                classes.push('has-' + backgroundColor + '-background-color');
            }
            if (textColor) {
                classes.push('has-text-color');
                classes.push('has-' + textColor + '-color');
            }
            if (fontSize) {
                classes.push('has-' + fontSize + '-font-size');
            }

            // Build inline styles
            const style = {};
            if (backgroundColor && backgroundColor.indexOf('#') === 0) {
                style.backgroundColor = backgroundColor;
            }
            if (textColor && textColor.indexOf('#') === 0) {
                style.color = textColor;
            }

            return el('div', {
                className: classes.join(' '),
                style: style,
                'data-block-id': blockId,
            },
                el('div', {
                    className: 'wilcoskywb-wiki-content',
                }, content)
            );
        },
    });

    // Wiki Block Settings Component
    function WikiBlockSettings(props) {
        const { blockId } = props;
        const [settings, setSettings] = React.useState(null);
        const [loading, setLoading] = React.useState(false);
        const [message, setMessage] = React.useState('');

        // Load settings on mount
        React.useEffect(() => {
            if (blockId) {
                loadSettings();
            }
        }, [blockId]);

        function loadSettings() {
            setLoading(true);
            setMessage('');

            const formData = new FormData();
            formData.append('action', 'wilcoskywb_wiki_blocks_get_settings');
            formData.append('block_id', blockId);
            formData.append('nonce', wilcoskywbWikiBlocks.nonces.getSettings);

            fetch(wilcoskywbWikiBlocks.ajaxUrl, {
                method: 'POST',
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    setSettings(data.data.settings);
                } else {
                    setMessage(data.data.message);
                }
            })
            .catch(error => {
                setMessage(wilcoskywbWikiBlocks.strings.errorSaving);
            })
            .finally(() => {
                setLoading(false);
            });
        }

        function saveSettings(newSettings) {
            setLoading(true);
            setMessage('');

            const formData = new FormData();
            formData.append('action', 'wilcoskywb_wiki_blocks_save_settings');
            formData.append('block_id', blockId);
            formData.append('settings', JSON.stringify(newSettings));
            formData.append('nonce', wilcoskywbWikiBlocks.nonces.saveSettings);

            fetch(wilcoskywbWikiBlocks.ajaxUrl, {
                method: 'POST',
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    setSettings(newSettings);
                    setMessage(wilcoskywbWikiBlocks.strings.settingsSaved);
                    setTimeout(() => setMessage(''), 3000);
                } else {
                    setMessage(data.data.message);
                }
            })
            .catch(error => {
                setMessage(wilcoskywbWikiBlocks.strings.errorSaving);
            })
            .finally(() => {
                setLoading(false);
            });
        }

        if (loading && !settings) {
            return el('p', {}, wilcoskywbWikiBlocks.strings.loading);
        }

        if (!settings) {
            return el('p', {}, __('Unable to load settings.', 'wiki-blocks'));
        }

        return el('div', {},
            message && el(Notice, {
                status: message.includes('success') ? 'success' : 'error',
                isDismissible: true,
                onRemove: () => setMessage(''),
            }, message),

            el(CheckboxControl, {
                label: wilcoskywbWikiBlocks.strings.requireLoginBrowse,
                checked: settings.require_login_browse || false,
                onChange: (value) => {
                    const newSettings = { ...settings, require_login_browse: value };
                    saveSettings(newSettings);
                },
            }),

            el('hr', { style: { margin: '1.5em 0' } }),

            el('h4', {}, __('Merge Permissions', 'wiki-blocks')),
            el('p', {
                style: {
                    fontSize: '0.875em',
                    color: '#666',
                    marginBottom: '1em',
                },
            }, __('Select which user roles can merge versions for this block:', 'wiki-blocks')),

            // Merge permissions checkboxes
            wilcoskywbWikiBlocks.roles && wilcoskywbWikiBlocks.roles.map(role => 
                el(CheckboxControl, {
                    key: 'merge_' + role.value,
                    label: role.label,
                    checked: settings.merge_permissions && settings.merge_permissions.includes(role.value),
                    onChange: (checked) => {
                        const currentPermissions = settings.merge_permissions || [];
                        const newPermissions = checked 
                            ? [...currentPermissions, role.value]
                            : currentPermissions.filter(p => p !== role.value);
                        const newSettings = { ...settings, merge_permissions: newPermissions };
                        saveSettings(newSettings);
                    },
                })
            ),

            el('hr', { style: { margin: '1.5em 0' } }),

            el('h4', {}, __('Browse Permissions', 'wiki-blocks')),
            el('p', {
                style: {
                    fontSize: '0.875em',
                    color: '#666',
                    marginBottom: '1em',
                },
            }, __('Select which user roles can browse versions for this block:', 'wiki-blocks')),

            // Browse permissions checkboxes
            wilcoskywbWikiBlocks.roles && wilcoskywbWikiBlocks.roles.map(role => 
                el(CheckboxControl, {
                    key: 'browse_' + role.value,
                    label: role.label,
                    checked: settings.browse_permissions && settings.browse_permissions.includes(role.value),
                    onChange: (checked) => {
                        const currentPermissions = settings.browse_permissions || [];
                        const newPermissions = checked 
                            ? [...currentPermissions, role.value]
                            : currentPermissions.filter(p => p !== role.value);
                        const newSettings = { ...settings, browse_permissions: newPermissions };
                        saveSettings(newSettings);
                    },
                })
            ),

            el('hr', { style: { margin: '1.5em 0' } }),

            el('h4', {}, __('Suggest Permissions', 'wiki-blocks')),
            el('p', {
                style: {
                    fontSize: '0.875em',
                    color: '#666',
                    marginBottom: '1em',
                },
            }, __('Select which user roles can suggest changes for this block:', 'wiki-blocks')),

            // Suggest permissions checkboxes
            wilcoskywbWikiBlocks.roles && wilcoskywbWikiBlocks.roles.map(role => 
                el(CheckboxControl, {
                    key: 'suggest_' + role.value,
                    label: role.label,
                    checked: settings.suggest_permissions && settings.suggest_permissions.includes(role.value),
                    onChange: (checked) => {
                        const currentPermissions = settings.suggest_permissions || [];
                        const newPermissions = checked 
                            ? [...currentPermissions, role.value]
                            : currentPermissions.filter(p => p !== role.value);
                        const newSettings = { ...settings, suggest_permissions: newPermissions };
                        saveSettings(newSettings);
                    },
                })
            ),

            el('hr', { style: { margin: '1.5em 0' } }),

            el('p', {
                style: {
                    fontSize: '0.875em',
                    color: '#666',
                    marginTop: '1em',
                },
            }, __('These settings override the global settings for this specific block.', 'wiki-blocks'))
        );
    }

})(); 