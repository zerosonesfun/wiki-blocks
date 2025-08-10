# Wiki Blocks

A WordPress plugin that adds collaborative wiki functionality to Gutenberg blocks with version control and user collaboration features.

## Description

Wiki Blocks allows you to add wiki-style blocks to your WordPress posts and pages using the Gutenberg editor. Users can suggest changes to the content, and administrators can review and merge different versions. The plugin provides a complete version control system with user permissions, change tracking, and a modern interface.

## Features

- **Gutenberg Block Integration**: Add wiki blocks directly in the WordPress editor
- **Version Control**: Track all changes with full version history
- **User Collaboration**: Allow logged-in users to suggest changes
- **Permission System**: Configure who can merge versions and browse history
- **Modern UI**: Clean, responsive interface with modals and notifications
- **Accessibility**: WCAG compliant with keyboard navigation and screen reader support
- **Security**: Proper sanitization, validation, and nonce protection
- **WordPress Standards**: Follows all WordPress coding standards and best practices

## Requirements

- WordPress 5.0 or higher
- PHP 8.0 or higher
- MySQL 5.6 or higher

## Installation

1. Download the plugin files
2. Upload the `wiki-blocks` folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure the plugin settings under 'Settings > Wiki Blocks'

## Usage

### Adding a Wiki Block

1. Edit a post or page in the Gutenberg editor
2. Click the '+' button to add a new block
3. Search for "Wiki Block" or find it in the "Wiki Blocks" category
4. Add your content to the block
5. Publish or update the post

### Frontend Features

#### For Logged-in Users
- **Suggest Changes**: Click "Suggest Change" to propose modifications
- **View History**: Click "View History" to browse all versions
- **Merge Versions**: If you have permission, merge any version as the current version

#### For Administrators
- **Manage Permissions**: Configure who can merge and browse versions
- **Review Changes**: See all suggested changes with user information
- **Version Control**: Maintain complete history of all modifications

### Settings Configuration

Navigate to **Settings > Wiki Blocks** to configure:

- **Merge Permissions**: Select which user roles can merge versions
- **Browse Permissions**: Choose who can view version history
- **Login Requirements**: Optionally require login to browse versions

## File Structure

```
wiki-blocks/
├── wiki-blocks.php                 # Main plugin file
├── includes/                       # PHP classes
│   ├── class-wilcoskywb-wiki-blocks-admin.php
│   ├── class-wilcoskywb-wiki-blocks-ajax.php
│   ├── class-wilcoskywb-wiki-blocks-assets.php
│   ├── class-wilcoskywb-wiki-blocks-blocks.php
│   ├── class-wilcoskywb-wiki-blocks-database.php
│   └── class-wilcoskywb-wiki-blocks-permissions.php
├── assets/                         # Frontend assets
│   ├── css/
│   │   ├── admin.css
│   │   ├── editor.css
│   │   └── frontend.css
│   └── js/
│       ├── admin.js
│       ├── editor.js
│       └── frontend.js
├── languages/                      # Translation files
└── README.md
```

## Database Tables

The plugin creates two database tables:

### `wp_wilcoskywb_wiki_block_versions`
Stores all version history for wiki blocks:
- `id`: Primary key
- `block_id`: Unique block identifier
- `post_id`: Associated post ID
- `content`: Block content
- `user_id`: User who made the change
- `version_number`: Sequential version number
- `is_current`: Whether this is the current version
- `change_summary`: Description of changes
- `created_at`: Timestamp

### `wp_wilcoskywb_wiki_block_settings`
Stores block-specific settings:
- `id`: Primary key
- `block_id`: Unique block identifier
- `post_id`: Associated post ID
- `merge_permissions`: JSON array of allowed roles
- `browse_permissions`: JSON array of allowed roles
- `require_login_browse`: Boolean flag
- `created_at`: Timestamp
- `updated_at`: Last update timestamp

## Security Features

- **Nonce Verification**: All AJAX requests are protected with WordPress nonces
- **Input Sanitization**: All user inputs are properly sanitized
- **Output Escaping**: All output is escaped using appropriate WordPress functions
- **Permission Checks**: Comprehensive permission system for all actions
- **SQL Prepared Statements**: All database queries use prepared statements
- **Prefix Protection**: Uses unique plugin prefix to avoid conflicts

## Accessibility

- **Keyboard Navigation**: Full keyboard support for all interactions
- **Screen Reader Support**: Proper ARIA labels and semantic HTML
- **High Contrast Mode**: Support for high contrast display preferences
- **Reduced Motion**: Respects user's motion preferences
- **Focus Management**: Proper focus handling in modals and forms

## Hooks and Filters

### Actions
- `wilcoskywb_wiki_blocks_version_created`: Fired when a new version is created
- `wilcoskywb_wiki_blocks_version_merged`: Fired when a version is merged
- `wilcoskywb_wiki_blocks_settings_updated`: Fired when settings are updated

### Filters
- `wilcoskywb_wiki_blocks_merge_permissions`: Modify merge permissions
- `wilcoskywb_wiki_blocks_browse_permissions`: Modify browse permissions
- `wilcoskywb_wiki_blocks_version_content`: Filter version content before saving
- `wilcoskywb_wiki_blocks_display_content`: Filter content before display

## Development

### Building from Source

1. Clone the repository
2. Install dependencies (if any)
3. Make your changes
4. Test thoroughly
5. Follow WordPress coding standards

### Coding Standards

This plugin follows:
- WordPress Coding Standards
- WordPress Plugin Development Guidelines
- PSR-12 PHP Standards
- Modern JavaScript ES6+ standards

### Testing

- Test with different user roles
- Test with various content types
- Test on different devices and browsers
- Test accessibility features
- Test security measures

## Support

For support, feature requests, or bug reports, please contact the plugin author.

## Changelog

### Version 1.0.0
- Initial release
- Gutenberg block integration
- Version control system
- User collaboration features
- Permission management
- Modern UI with modals
- Accessibility support
- Security features

## License

This plugin is licensed under the GPL v2 or later.

## Author

**Billy Wilcosky** - [wilcosky.com](https://wilcosky.com)

## Credits

- Built for WordPress
- Uses WordPress core APIs and standards
- Follows WordPress plugin development best practices 