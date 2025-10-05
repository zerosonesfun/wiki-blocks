<?php
/**
 * Admin Handler for Wiki Blocks
 *
 * @package WikiBlocks
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin handler class for Wiki Blocks
 *
 * @since 1.0.0
 */
class Wilcoskywb_Wiki_Blocks_Admin {

	/**
	 * Instance of this class
	 *
	 * @var Wilcoskywb_Wiki_Blocks_Admin
	 */
	private static $instance = null;

	/**
	 * Get instance of this class
	 *
	 * @return Wilcoskywb_Wiki_Blocks_Admin
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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'init_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_wilcoskywb_wiki_blocks_admin_get_stats', array( $this, 'get_admin_stats' ) );
		add_action( 'wp_ajax_wilcoskywb_wiki_blocks_admin_cleanup_versions', array( $this, 'cleanup_versions' ) );
		add_action( 'wp_ajax_wilcoskywb_wiki_blocks_admin_cleanup_orphaned', array( $this, 'cleanup_orphaned_content' ) );
		add_action( 'wp_ajax_wilcoskywb_wiki_blocks_admin_cleanup_old_activity', array( $this, 'cleanup_old_activity' ) );
		add_filter( 'plugin_action_links_' . WILCOSKYWB_WIKI_BLOCKS_PLUGIN_BASENAME, array( $this, 'add_plugin_links' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'Wiki Blocks Settings', 'wiki-blocks' ),
			__( 'Wiki Blocks', 'wiki-blocks' ),
			'manage_options',
			'wilcoskywb-wiki-blocks',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Initialize settings
	 */
	public function init_settings() {
		register_setting(
			'wilcoskywb_wiki_blocks_settings',
			'wilcoskywb_wiki_blocks_merge_permissions',
			array(
				'type' => 'array',
				'sanitize_callback' => array( $this, 'sanitize_permissions' ),
				'default' => array( 'administrator' ),
			)
		);

		register_setting(
			'wilcoskywb_wiki_blocks_settings',
			'wilcoskywb_wiki_blocks_browse_permissions',
			array(
				'type' => 'array',
				'sanitize_callback' => array( $this, 'sanitize_permissions' ),
				'default' => array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ),
			)
		);

		register_setting(
			'wilcoskywb_wiki_blocks_settings',
			'wilcoskywb_wiki_blocks_suggest_permissions',
			array(
				'type' => 'array',
				'sanitize_callback' => array( $this, 'sanitize_permissions' ),
				'default' => array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ),
			)
		);

		register_setting(
			'wilcoskywb_wiki_blocks_settings',
			'wilcoskywb_wiki_blocks_require_login_browse',
			array(
				'type' => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default' => false,
			)
		);

		register_setting(
			'wilcoskywb_wiki_blocks_settings',
			'wilcoskywb_wiki_blocks_cleanup_on_uninstall',
			array(
				'type' => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default' => true,
			)
		);

		add_settings_section(
			'wilcoskywb_wiki_blocks_permissions_section',
			__( 'Permission Settings', 'wiki-blocks' ),
			array( $this, 'render_permissions_section' ),
			'wilcoskywb_wiki_blocks_settings'
		);

		add_settings_field(
			'wilcoskywb_wiki_blocks_merge_permissions',
			__( 'Merge Permissions', 'wiki-blocks' ),
			array( $this, 'render_merge_permissions_field' ),
			'wilcoskywb_wiki_blocks_settings',
			'wilcoskywb_wiki_blocks_permissions_section'
		);

		add_settings_field(
			'wilcoskywb_wiki_blocks_browse_permissions',
			__( 'Browse Permissions', 'wiki-blocks' ),
			array( $this, 'render_browse_permissions_field' ),
			'wilcoskywb_wiki_blocks_settings',
			'wilcoskywb_wiki_blocks_permissions_section'
		);

		add_settings_field(
			'wilcoskywb_wiki_blocks_suggest_permissions',
			__( 'Suggest Permissions', 'wiki-blocks' ),
			array( $this, 'render_suggest_permissions_field' ),
			'wilcoskywb_wiki_blocks_settings',
			'wilcoskywb_wiki_blocks_permissions_section'
		);

		add_settings_field(
			'wilcoskywb_wiki_blocks_require_login_browse',
			__( 'Require Login to Browse', 'wiki-blocks' ),
			array( $this, 'render_require_login_browse_field' ),
			'wilcoskywb_wiki_blocks_settings',
			'wilcoskywb_wiki_blocks_permissions_section'
		);

		add_settings_field(
			'wilcoskywb_wiki_blocks_cleanup_on_uninstall',
			__( 'Cleanup on Uninstall', 'wiki-blocks' ),
			array( $this, 'render_cleanup_on_uninstall_field' ),
			'wilcoskywb_wiki_blocks_settings',
			'wilcoskywb_wiki_blocks_permissions_section'
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'settings_page_wilcoskywb-wiki-blocks' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'wilcoskywb-wiki-blocks-admin',
			Wilcoskywb_Wiki_Blocks_Assets::get_asset_url( 'assets/js/admin.js' ),
			array( 'jquery', 'wp-util' ),
			Wilcoskywb_Wiki_Blocks_Assets::get_asset_version( 'assets/js/admin.js' ),
			true
		);

		wp_enqueue_style(
			'wilcoskywb-wiki-blocks-admin',
			Wilcoskywb_Wiki_Blocks_Assets::get_asset_url( 'assets/css/admin.css' ),
			array(),
			Wilcoskywb_Wiki_Blocks_Assets::get_asset_version( 'assets/css/admin.css' )
		);

		wp_localize_script(
			'wilcoskywb-wiki-blocks-admin',
			'wilcoskywbWikiBlocksAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'wilcoskywb_wiki_blocks_admin' ),
				'strings' => array(
					'confirmCleanup' => __( 'Are you sure you want to delete all old versions? This action cannot be undone.', 'wiki-blocks' ),
					'orphanedCleanupConfirm' => __( 'Are you sure you want to delete all wiki content that is no longer associated with any posts? This action cannot be undone.', 'wiki-blocks' ),
					'activityCleanupConfirm' => __( 'Are you sure you want to delete old activity data? This will remove version history older than your retention settings. Current versions will be preserved. This action cannot be undone.', 'wiki-blocks' ),
					'cleanupSuccess' => __( 'Cleanup completed successfully!', 'wiki-blocks' ),
					'cleanupError' => __( 'Error during cleanup. Please try again.', 'wiki-blocks' ),
					'loading' => __( 'Loading...', 'wiki-blocks' ),
				),
			)
		);
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wiki-blocks' ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<div class="wilcoskywb-wiki-blocks-admin-content">
				<div class="wilcoskywb-wiki-blocks-admin-main">
					<form method="post" action="options.php">
						<?php
						settings_fields( 'wilcoskywb_wiki_blocks_settings' );
						do_settings_sections( 'wilcoskywb_wiki_blocks_settings' );
						submit_button();
						?>
					</form>
				</div>

				<div class="wilcoskywb-wiki-blocks-admin-sidebar">
					<div class="wilcoskywb-wiki-blocks-admin-widget">
						<h3><?php esc_html_e( 'Statistics', 'wiki-blocks' ); ?></h3>
						<div id="wilcoskywb-wiki-blocks-stats">
							<p><?php esc_html_e( 'Loading statistics...', 'wiki-blocks' ); ?></p>
						</div>
					</div>

					<div class="wilcoskywb-wiki-blocks-admin-widget">
						<h3><?php esc_html_e( 'Maintenance', 'wiki-blocks' ); ?></h3>
						<p><?php esc_html_e( 'Clean up old wiki block versions to free up database space.', 'wiki-blocks' ); ?></p>
						<button type="button" id="wilcoskywb-wiki-blocks-cleanup" class="button button-secondary">
							<?php esc_html_e( 'Cleanup Old Versions', 'wiki-blocks' ); ?>
						</button>
						<br><br>
						<p><?php esc_html_e( 'Remove wiki content that is no longer associated with any existing posts or pages.', 'wiki-blocks' ); ?></p>
						<button type="button" id="wilcoskywb-wiki-blocks-cleanup-orphaned" class="button button-secondary">
							<?php esc_html_e( 'Cleanup Orphaned Content', 'wiki-blocks' ); ?>
						</button>
						<br><br>
						<p><?php esc_html_e( 'Remove old activity data to prevent database bloat while preserving recent versions and current content.', 'wiki-blocks' ); ?></p>
						<button type="button" id="wilcoskywb-wiki-blocks-cleanup-activity" class="button button-secondary">
							<?php esc_html_e( 'Cleanup Old Activity', 'wiki-blocks' ); ?>
						</button>
					</div>

					<div class="wilcoskywb-wiki-blocks-admin-widget">
						<h3><?php esc_html_e( 'Maintenance Help', 'wiki-blocks' ); ?></h3>
						<div class="wilcoskywb-wiki-blocks-help-text">
							<div class="wilcoskywb-wiki-blocks-cleanup-help">
								<h4><?php esc_html_e( 'Cleanup Old Versions:', 'wiki-blocks' ); ?></h4>
								<p><?php esc_html_e( 'Removes orphaned version numbers that are higher than the current version. This typically happens when suggestions are made but never merged, or when multiple suggestions are created before any are accepted. The current version and all valid historical versions are preserved.', 'wiki-blocks' ); ?></p>
								
								<h4><?php esc_html_e( 'Cleanup Orphaned Content:', 'wiki-blocks' ); ?></h4>
								<p><?php esc_html_e( 'Removes all wiki block content (versions and settings) that is no longer associated with any existing posts or pages. This includes content from deleted posts, blocks that were never properly associated with posts, and any other orphaned data that cannot be accessed through normal WordPress content.', 'wiki-blocks' ); ?></p>
								
								<h4><?php esc_html_e( 'Cleanup Old Activity:', 'wiki-blocks' ); ?></h4>
								<p><?php esc_html_e( 'Removes old version history to prevent database bloat while preserving recent activity and current versions. Uses configurable retention settings: keeps versions newer than specified days and limits the number of versions per block. Current versions are always preserved.', 'wiki-blocks' ); ?></p>
								
								<p><strong><?php esc_html_e( 'Important:', 'wiki-blocks' ); ?></strong> <?php esc_html_e( 'All cleanup operations are irreversible. Always backup your database before running cleanup operations.', 'wiki-blocks' ); ?></p>
							</div>
						</div>
					</div>

					<div class="wilcoskywb-wiki-blocks-admin-widget">
						<h3><?php esc_html_e( 'Documentation', 'wiki-blocks' ); ?></h3>
						<p><?php esc_html_e( 'Learn how to use Wiki Blocks effectively.', 'wiki-blocks' ); ?></p>
						<a href="https://wordpress.org/plugins/wiki-blocks" class="button button-secondary" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Documentation', 'wiki-blocks' ); ?></a>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render permissions section
	 */
	public function render_permissions_section() {
		echo '<div class="wilcoskywb-wiki-blocks-help-text">';
		echo '<p>' . esc_html__( 'Configure who can perform different actions with wiki blocks. These are global settings that apply to all wiki blocks unless overridden by block-specific settings.', 'wiki-blocks' ) . '</p>';
		echo '<div class="wilcoskywb-wiki-blocks-permission-help">';
		echo '<h4>' . esc_html__( 'Permission Types:', 'wiki-blocks' ) . '</h4>';
		echo '<ul>';
		echo '<li><strong>' . esc_html__( 'Merge Permissions:', 'wiki-blocks' ) . '</strong> ' . esc_html__( 'Who can approve and merge suggested changes to make them live.', 'wiki-blocks' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Browse Permissions:', 'wiki-blocks' ) . '</strong> ' . esc_html__( 'Who can view the version history of wiki blocks.', 'wiki-blocks' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Suggest Permissions:', 'wiki-blocks' ) . '</strong> ' . esc_html__( 'Who can submit suggested changes to wiki blocks.', 'wiki-blocks' ) . '</li>';
		echo '</ul>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render merge permissions field
	 */
	public function render_merge_permissions_field() {
		$value = get_option( 'wilcoskywb_wiki_blocks_merge_permissions', array( 'administrator' ) );
		$roles = Wilcoskywb_Wiki_Blocks_Permissions::get_available_roles();

		echo '<fieldset>';
		foreach ( $roles as $role_key => $role_name ) {
			$checked = in_array( $role_key, $value, true ) ? 'checked' : '';
			echo '<label><input type="checkbox" name="wilcoskywb_wiki_blocks_merge_permissions[]" value="' . esc_attr( $role_key ) . '" ' . esc_attr( $checked ) . '> ' . esc_html( $role_name ) . '</label><br>';
		}
		echo '<p class="description">' . esc_html__( 'Select which user roles can merge wiki block versions.', 'wiki-blocks' ) . '</p>';
		echo '</fieldset>';
	}

	/**
	 * Render browse permissions field
	 */
	public function render_browse_permissions_field() {
		$value = get_option( 'wilcoskywb_wiki_blocks_browse_permissions', array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ) );
		$roles = Wilcoskywb_Wiki_Blocks_Permissions::get_available_roles();

		echo '<fieldset>';
		foreach ( $roles as $role_key => $role_name ) {
			$checked = in_array( $role_key, $value, true ) ? 'checked' : '';
			echo '<label><input type="checkbox" name="wilcoskywb_wiki_blocks_browse_permissions[]" value="' . esc_attr( $role_key ) . '" ' . esc_attr( $checked ) . '> ' . esc_html( $role_name ) . '</label><br>';
		}
		echo '<p class="description">' . esc_html__( 'Select which user roles can browse wiki block versions.', 'wiki-blocks' ) . '</p>';
		echo '</fieldset>';
	}

	/**
	 * Render suggest permissions field
	 */
	public function render_suggest_permissions_field() {
		$value = get_option( 'wilcoskywb_wiki_blocks_suggest_permissions', array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ) );
		$roles = Wilcoskywb_Wiki_Blocks_Permissions::get_available_roles();

		echo '<fieldset>';
		foreach ( $roles as $role_key => $role_name ) {
			$checked = in_array( $role_key, $value, true ) ? 'checked' : '';
			echo '<label><input type="checkbox" name="wilcoskywb_wiki_blocks_suggest_permissions[]" value="' . esc_attr( $role_key ) . '" ' . esc_attr( $checked ) . '> ' . esc_html( $role_name ) . '</label><br>';
		}
		echo '<p class="description">' . esc_html__( 'Select which user roles can suggest changes to wiki blocks.', 'wiki-blocks' ) . '</p>';
		echo '</fieldset>';
	}

	/**
	 * Render require login browse field
	 */
	public function render_require_login_browse_field() {
		$value = get_option( 'wilcoskywb_wiki_blocks_require_login_browse', false );
		$checked = $value ? 'checked' : '';

		echo '<fieldset>';
		echo '<label><input type="checkbox" name="wilcoskywb_wiki_blocks_require_login_browse" value="1" ' . esc_attr( $checked ) . '> ' . esc_html__( 'Require users to be logged in to browse versions', 'wiki-blocks' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'If checked, only logged-in users can browse wiki block versions.', 'wiki-blocks' ) . '</p>';
		echo '</fieldset>';
	}

	/**
	 * Render cleanup on uninstall field
	 */
	public function render_cleanup_on_uninstall_field() {
		$value = get_option( 'wilcoskywb_wiki_blocks_cleanup_on_uninstall', true );
		$checked = $value ? 'checked' : '';

		echo '<fieldset>';
		echo '<label><input type="checkbox" name="wilcoskywb_wiki_blocks_cleanup_on_uninstall" value="1" ' . esc_attr( $checked ) . '> ' . esc_html__( 'Clean up all data when plugin is deleted', 'wiki-blocks' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'If checked, all wiki block data (versions, settings) will be permanently deleted when the plugin is uninstalled. If unchecked, data will be preserved.', 'wiki-blocks' ) . '</p>';
		echo '</fieldset>';
	}

	/**
	 * Sanitize permissions array
	 *
	 * @param array $input The input array.
	 * @return array Sanitized array.
	 */
	public function sanitize_permissions( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$valid_roles = array_keys( Wilcoskywb_Wiki_Blocks_Permissions::get_available_roles() );
		$sanitized = array();

		foreach ( $input as $role ) {
			if ( in_array( $role, $valid_roles, true ) ) {
				$sanitized[] = sanitize_text_field( $role );
			}
		}

		return $sanitized;
	}

	/**
	 * Get admin statistics
	 */
	public function get_admin_stats() {
		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wilcoskywb_wiki_blocks_admin' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wiki-blocks' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'wiki-blocks' ) ) );
		}

		global $wpdb;

		// Check cache first for admin statistics
		$cache_key = 'wilcoskywb_admin_stats';
		$stats = wp_cache_get( $cache_key, 'wiki-blocks' );

		if ( false === $stats ) {
			// Direct database queries are necessary for custom table operations
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$total_versions = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wilcoskywb_wiki_block_versions" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$total_blocks = $wpdb->get_var( "SELECT COUNT(DISTINCT block_id) FROM {$wpdb->prefix}wilcoskywb_wiki_block_versions" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$total_users = $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}wilcoskywb_wiki_block_versions" );

			// Get recent activity with post information
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$recent_activity = $wpdb->get_results(
				"SELECT v.*, u.display_name, p.post_title, v.block_id
				FROM {$wpdb->prefix}wilcoskywb_wiki_block_versions v
				LEFT JOIN {$wpdb->users} u ON v.user_id = u.ID
				LEFT JOIN {$wpdb->posts} p ON v.post_id = p.ID
				ORDER BY v.created_at DESC
				LIMIT 5"
			);

			$stats = array(
				'total_versions' => (int) $total_versions,
				'total_blocks' => (int) $total_blocks,
				'total_users' => (int) $total_users,
				'recent_activity' => $recent_activity,
			);

			// Cache for 2 minutes (admin stats don't need to be real-time)
			wp_cache_set( $cache_key, $stats, 'wiki-blocks', 120 );
		}

		wp_send_json_success( $stats );
	}

	/**
	 * Cleanup old versions
	 */
	public function cleanup_versions() {
		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wilcoskywb_wiki_blocks_admin' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wiki-blocks' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'wiki-blocks' ) ) );
		}

		global $wpdb;

		// Direct database query is necessary for custom table cleanup operations
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$deleted = $wpdb->query(
			"DELETE v1 FROM {$wpdb->prefix}wilcoskywb_wiki_block_versions v1
			LEFT JOIN (
				SELECT block_id, MAX(version_number) as max_version
				FROM {$wpdb->prefix}wilcoskywb_wiki_block_versions
				WHERE is_current = 1
				GROUP BY block_id
			) v2 ON v1.block_id = v2.block_id AND v1.version_number = v2.max_version
			WHERE v2.max_version IS NULL"
		);

		if ( false === $deleted ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Error during cleanup.', 'wiki-blocks' ) ) );
		}

		// Clear all wiki blocks cache since cleanup affects multiple blocks
		wp_cache_delete( 'wilcoskywb_admin_stats', 'wiki-blocks' );

		wp_send_json_success( array(
			/* translators: %d: number of deleted versions */
			'message' => sprintf( esc_html__( 'Cleanup completed. Deleted %d old versions.', 'wiki-blocks' ), $deleted ),
			'deleted_count' => $deleted,
		) );
	}

	/**
	 * Cleanup orphaned wiki content not associated with any existing posts
	 *
	 * @since 1.1.0
	 */
	public function cleanup_orphaned_content() {
		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wilcoskywb_wiki_blocks_admin' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wiki-blocks' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'wiki-blocks' ) ) );
		}

		global $wpdb;
		$versions_table = $wpdb->prefix . 'wilcoskywb_wiki_block_versions';
		$settings_table = $wpdb->prefix . 'wilcoskywb_wiki_block_settings';
		$posts_table = $wpdb->posts;

		// Find all block IDs that have post_id references to non-existent posts
		$orphaned_blocks = $wpdb->get_col(
			"SELECT DISTINCT v.block_id 
			FROM $versions_table v
			LEFT JOIN $posts_table p ON v.post_id = p.ID
			WHERE v.post_id IS NOT NULL AND p.ID IS NULL"
		);

		$deleted_versions = 0;
		$deleted_settings = 0;

		// Clean up orphaned blocks
		foreach ( $orphaned_blocks as $block_id ) {
			// Delete all versions for this orphaned block
			$versions_deleted = $wpdb->delete(
				$versions_table,
				array( 'block_id' => $block_id ),
				array( '%s' )
			);
			$deleted_versions += $versions_deleted;

			// Delete settings for this orphaned block
			$settings_deleted = $wpdb->delete(
				$settings_table,
				array( 'block_id' => $block_id ),
				array( '%s' )
			);
			$deleted_settings += $settings_deleted;
		}

		// Also find and delete any versions with NULL post_id that might be orphaned
		// (This catches blocks that were never properly associated with posts)
		$null_post_versions = $wpdb->get_results(
			"SELECT DISTINCT block_id FROM $versions_table WHERE post_id IS NULL"
		);

		foreach ( $null_post_versions as $version ) {
			// Check if this block_id appears in any existing post content
			$exists_in_content = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $posts_table 
					WHERE post_content LIKE %s AND post_status IN ('publish', 'draft', 'private', 'pending')",
					'%' . $version->block_id . '%'
				)
			);

			if ( ! $exists_in_content ) {
				// Block not found in any post content, safe to delete
				$versions_deleted = $wpdb->delete(
					$versions_table,
					array( 'block_id' => $version->block_id ),
					array( '%s' )
				);
				$deleted_versions += $versions_deleted;

				$settings_deleted = $wpdb->delete(
					$settings_table,
					array( 'block_id' => $version->block_id ),
					array( '%s' )
				);
				$deleted_settings += $settings_deleted;
			}
		}

		// Clear cache
		wp_cache_delete( 'wilcoskywb_admin_stats', 'wiki-blocks' );

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %1$d: number of deleted versions, %2$d: number of deleted settings */
				esc_html__( 'Orphaned content cleanup completed. Deleted %1$d versions and %2$d settings from orphaned blocks.', 'wiki-blocks' ),
				$deleted_versions,
				$deleted_settings
			),
			'deleted_versions' => $deleted_versions,
			'deleted_settings' => $deleted_settings,
		) );
	}

	/**
	 * Cleanup old activity data to prevent database bloat
	 *
	 * @since 1.1.0
	 */
	public function cleanup_old_activity() {
		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'wilcoskywb_wiki_blocks_admin' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wiki-blocks' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'wiki-blocks' ) ) );
		}

		global $wpdb;
		$versions_table = $wpdb->prefix . 'wilcoskywb_wiki_block_versions';

		// Get cleanup settings
		$keep_days = get_option( 'wilcoskywb_wiki_blocks_activity_retention_days', 90 );
		$keep_versions_per_block = get_option( 'wilcoskywb_wiki_blocks_max_versions_per_block', 50 );

		$deleted_count = 0;

		// Method 1: Delete versions older than X days (but keep current versions)
		if ( $keep_days > 0 ) {
			$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$keep_days} days" ) );
			
			$deleted_by_date = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $versions_table 
					WHERE created_at < %s AND is_current = 0",
					$cutoff_date
				)
			);
			
			$deleted_count += $deleted_by_date;
		}

		// Method 2: For each block, keep only the most recent X versions (but always keep current)
		if ( $keep_versions_per_block > 0 ) {
			// Get all blocks
			$blocks = $wpdb->get_col(
				"SELECT DISTINCT block_id FROM $versions_table"
			);

			foreach ( $blocks as $block_id ) {
				// Get versions for this block, ordered by version number DESC
				$versions = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT id, version_number, is_current 
						FROM $versions_table 
						WHERE block_id = %s 
						ORDER BY version_number DESC",
						$block_id
					)
				);

				// Find current version
				$current_version = null;
				foreach ( $versions as $version ) {
					if ( $version->is_current ) {
						$current_version = $version;
						break;
					}
				}

				// Keep current version + X most recent versions
				$versions_to_keep = array();
				
				// Always keep current version
				if ( $current_version ) {
					$versions_to_keep[] = $current_version->id;
				}

				// Keep most recent versions (excluding current if already counted)
				$count = 0;
				foreach ( $versions as $version ) {
					if ( ! $version->is_current && $count < $keep_versions_per_block ) {
						$versions_to_keep[] = $version->id;
						$count++;
					}
				}

				// Delete versions not in the keep list
				if ( ! empty( $versions_to_keep ) ) {
					$placeholders = implode( ',', array_fill( 0, count( $versions_to_keep ), '%d' ) );
					$deleted_for_block = $wpdb->query(
						$wpdb->prepare(
							"DELETE FROM $versions_table 
							WHERE block_id = %s AND id NOT IN ($placeholders)",
							array_merge( array( $block_id ), $versions_to_keep )
						)
					);
					$deleted_count += $deleted_for_block;
				}
			}
		}

		// Clear cache
		wp_cache_delete( 'wilcoskywb_admin_stats', 'wiki-blocks' );

		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %d: number of deleted versions */
				esc_html__( 'Old activity cleanup completed. Deleted %d old versions.', 'wiki-blocks' ),
				$deleted_count
			),
			'deleted_count' => $deleted_count,
		) );
	}

	/**
	 * Add plugin action links
	 *
	 * @param array $links Plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function add_plugin_links( $links ) {
		$settings_link = '<a href="' . admin_url( 'options-general.php?page=wilcoskywb-wiki-blocks' ) . '">' . esc_html__( 'Settings', 'wiki-blocks' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
} 