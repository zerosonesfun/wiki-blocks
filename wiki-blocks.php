<?php
/**
 * Plugin Name: Wiki Blocks
 * Description: Add wiki functionality to Gutenberg blocks with version control and user collaboration features.
 * Version: 1.1.0
 * Author: Billy Wilcosky
 * Author URI: https://wilcosky.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wiki-blocks
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 8.0
 *
 * @package WikiBlocks
 * @author Billy Wilcosky
 * @version 1.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'WILCOSKYWB_WIKI_BLOCKS_VERSION', '1.1.0' );
define( 'WILCOSKYWB_WIKI_BLOCKS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WILCOSKYWB_WIKI_BLOCKS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WILCOSKYWB_WIKI_BLOCKS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Wiki Blocks Plugin Class
 *
 * @since 1.0.0
 */
final class Wilcoskywb_Wiki_Blocks {

	/**
	 * Plugin instance
	 *
	 * @var Wilcoskywb_Wiki_Blocks
	 */
	private static $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return Wilcoskywb_Wiki_Blocks
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Load required files
		$this->load_dependencies();
		
		// Ensure database migrations are run
		Wilcoskywb_Wiki_Blocks_Database::run_migrations();
		
		// Initialize components
		Wilcoskywb_Wiki_Blocks_Admin::get_instance();
		Wilcoskywb_Wiki_Blocks_Blocks::get_instance();
		Wilcoskywb_Wiki_Blocks_Ajax::get_instance();
		Wilcoskywb_Wiki_Blocks_Assets::get_instance();
	}

	/**
	 * Load plugin dependencies
	 */
	private function load_dependencies() {
		require_once WILCOSKYWB_WIKI_BLOCKS_PLUGIN_DIR . 'includes/class-wilcoskywb-wiki-blocks-admin.php';
		require_once WILCOSKYWB_WIKI_BLOCKS_PLUGIN_DIR . 'includes/class-wilcoskywb-wiki-blocks-blocks.php';
		require_once WILCOSKYWB_WIKI_BLOCKS_PLUGIN_DIR . 'includes/class-wilcoskywb-wiki-blocks-ajax.php';
		require_once WILCOSKYWB_WIKI_BLOCKS_PLUGIN_DIR . 'includes/class-wilcoskywb-wiki-blocks-assets.php';
		require_once WILCOSKYWB_WIKI_BLOCKS_PLUGIN_DIR . 'includes/class-wilcoskywb-wiki-blocks-database.php';
		require_once WILCOSKYWB_WIKI_BLOCKS_PLUGIN_DIR . 'includes/class-wilcoskywb-wiki-blocks-permissions.php';
	}



	/**
	 * Plugin activation
	 */
	public function activate() {
		// Load dependencies first
		$this->load_dependencies();
		
		// Create database tables
		Wilcoskywb_Wiki_Blocks_Database::create_tables();
		
		// Set default options
		$this->set_default_options();
		
		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Set default plugin options
	 */
	private function set_default_options() {
		$default_options = array(
			'wilcoskywb_wiki_blocks_merge_permissions' => array( 'administrator' ),
			'wilcoskywb_wiki_blocks_browse_permissions' => array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ),
			'wilcoskywb_wiki_blocks_suggest_permissions' => array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ),
			'wilcoskywb_wiki_blocks_require_login_browse' => false,
			'wilcoskywb_wiki_blocks_cleanup_on_uninstall' => true,
			'wilcoskywb_wiki_blocks_cleanup_on_delete' => true,
			'wilcoskywb_wiki_blocks_activity_retention_days' => 90,
			'wilcoskywb_wiki_blocks_max_versions_per_block' => 50,
		);

		foreach ( $default_options as $option_name => $default_value ) {
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $default_value );
			}
		}
	}
}

// Initialize the plugin
Wilcoskywb_Wiki_Blocks::get_instance(); 