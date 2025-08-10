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
    const { RichText, InspectorControls, ColorPalette, PanelColorSettings, BlockControls, AlignmentToolbar } = wp.blockEditor;
    const { PanelBody, TextControl, CheckboxControl, SelectControl, Button, Notice } = wp.components;
    const { __ } = wp.i18n;
    const { useSelect, useDispatch } = wp.data;

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
            const { content, blockId, align, backgroundColor, textColor, fontSize } = attributes;

            // Generate block ID if not set
            if (!blockId) {
                setAttributes({ blockId: 'wiki-block-' + clientId });
            }

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
            const { content, blockId, align, backgroundColor, textColor, fontSize } = attributes;

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