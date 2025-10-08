=== Wiki Blocks ===
Contributors: wilcosky
Tags: blocks, gutenberg, wiki, collaboration, version-control
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add wiki Gutenberg blocks to any page/post with version control and user collaboration features.

== Description ==

Wiki Blocks lets you add collaborative wiki content with full version control. Users can suggest changes, review version history, and merge updates with proper permission controls.

= Key Features =

* **Gutenberg Block Integration**: Seamlessly adds the wiki block type
* **Version Control**: Complete history of all changes with user attribution
* **Collaborative Editing**: Users can suggest changes that are reviewed before acceptance
* **Permission System**: Granular control over who can suggest, browse, and merge changes
* **Modern UI**: Clean, responsive interface with modal dialogs
* **Accessibility**: Full accessibility support with keyboard navigation and screen reader compatibility

= How It Works =

1. **Add a Wiki Block**: Insert the Wiki Block into any post or page
2. **Initial Content**: Set the initial content that becomes the first version
3. **User Suggestions**: Logged-in users can suggest changes with summaries
4. **Version History**: Browse all versions with excerpts and full content views
5. **Review & Merge**: Administrators or authorized users can merge suggestions into the live version

= Permission Levels =

* **Suggest Changes**: Control which user roles can submit suggestions
* **Browse History**: Manage who can view version history
* **Merge Versions**: Restrict who can accept changes as the new live version
* **Login Requirements**: Optionally require login to browse versions

= Use Cases =

* **Documentation Sites**: Collaborative documentation with version tracking
* **Knowledge Bases**: Community-driven content with review process
* **Policy Pages**: Controlled content updates with approval workflow
* **Educational Content**: Student contributions with teacher oversight
* **Team Wikis**: Internal knowledge sharing with permission controls

== Installation ==

1. Upload the `wiki-blocks` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure global permissions in 'Settings > Wiki Blocks'
4. Add Wiki Blocks to your posts and pages using the Gutenberg editor

== Frequently Asked Questions ==

= Can I turn any block into a wiki and use any type of content? =

Version 1 is focused on using the actual wiki block only (search for /wiki). So no, you cannot turn any block into a wiki. Also, for now only basic formatting and image uploading is available. This is enough for most blog articles or text-heavy website sections.

= What happens if I disable the plugin? =

Your content remains intact. There are clean up options in the settings. There is also the ability to clean up everything on uninstall if you would like.

= Can I control who can make changes? =

Absolutely! You can set permissions globally and per-block for suggesting changes, browsing history, and merging versions.

= Is this compatible with my theme? =

Yes, Wiki Blocks is designed to work with any WordPress theme and follows WordPress coding standards.

= Can I export version history? =

Version history is stored in the database and can be exported using standard WordPress database export tools.

= What if I want to remove all wiki data? =

The plugin includes an uninstall option that can completely remove all wiki data when the plugin is deleted.

== Screenshots ==

1. Wiki Block in Gutenberg Editor
2. Frontend Wiki Block with Controls
3. Version History Modal
4. Suggest Changes Form
5. Admin Settings Page
6. Merge action button on frontend

== Changelog ==

= 1.1.4 =
* Editor and styling updates

= 1.1.1 =
* Improved frontend JavaScript event handling for better plugin compatibility
* Changed edit and history buttons to use mousedown/touchstart events with click fallback
* Enhanced cross-device compatibility and future-proofing against plugin conflicts

= 1.1.0 =
* Added formatting and image handling
* Added more clean up options
* Fixed bugs

= 1.0.1 =
* Readme edits

= 1.0.0 =
* Initial release
* Gutenberg block integration
* Version control system
* Permission management
* Responsive UI design
* Accessibility features
* Cache busting for assets
* Comprehensive error handling

== Upgrade Notice ==

= 1.1.4 =
Minor update with editor and styling improvements

= 1.1.1 =
Minor update that improves frontend JavaScript compatibility and cross-device support

= 1.1.0 =
Major update which fixes bugs and adds formatting

== Translation ==

This plugin is translation-ready and includes a POT file for internationalization. To contribute translations:

1. Download the POT file from `/languages/wiki-blocks.pot`
2. Translate using a tool like Poedit
3. Save as `wiki-blocks-{locale}.po` and `wiki-blocks-{locale}.mo`
4. Submit translations to the plugin repository

== Support ==

For support, feature requests, or bug reports, please visit the [plugin support page](https://wilcosky.com/contact).

== Credits ==

Developed by [Billy Wilcosky](https://wilcosky.com)

Built with WordPress best practices and modern web standards.

== License ==

This plugin is licensed under the GPL v2 or later.

== Privacy ==

Wiki Blocks stores version history and user contributions in your WordPress database. This data includes:

* Content versions and change summaries
* User attribution for changes
* Block-specific settings and permissions

No data is sent to external servers. All information remains within your WordPress installation.

== Security ==

Wiki Blocks follows WordPress security best practices:

* All user inputs are sanitized and validated
* Nonces are used for all AJAX requests
* SQL queries use prepared statements
* Output is properly escaped
* Permission checks are performed on all actions

== Performance ==

* Optimized database queries with proper indexing
* Efficient asset loading with cache busting
* Minimal impact on page load times
* Responsive design for all devices

== Accessibility ==

* Full keyboard navigation support
* Screen reader compatible
* High contrast mode support
* Reduced motion preferences respected
* Semantic HTML structure
* ARIA labels and descriptions

== Development ==

Wiki Blocks is built with:

* PHP 8.0+ compatibility
* WordPress coding standards
* Modern JavaScript (ES6+)
* Responsive CSS with Flexbox/Grid
* Accessibility-first design principles

== Roadmap ==

Future versions may include:

* What you suggest

== Contributing ==

Contributions are welcome! Please:

1. Follow WordPress coding standards
2. Include proper documentation
3. Test thoroughly before submitting
4. Use meaningful commit messages
5. Respect accessibility guidelines

== Donate ==

If you find this plugin useful, consider supporting its development:

* [Donate](https://buymeacoffee.com/billyw)

Thank you for using Wiki Blocks! 