<?php
/**
 * Database Handler for Wiki Blocks
 *
 * @package WikiBlocks
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database handler class for Wiki Blocks
 *
 * @since 1.0.0
 */
class Wilcoskywb_Wiki_Blocks_Database {

	/**
	 * Create database tables
	 *
	 * @since 1.0.0
	 */
	public static function create_tables() {
		// Create tables
		self::create_version_table();
		self::create_settings_table();
		
		// Run migrations
		self::run_migrations();
	}

	/**
	 * Create version table
	 *
	 * @since 1.0.0
	 */
	private static function create_version_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Wiki block versions table
		$table_name = $wpdb->prefix . 'wilcoskywb_wiki_block_versions';
		
		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			block_id varchar(255) NOT NULL,
			post_id bigint(20) unsigned DEFAULT NULL,
			content longtext NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			version_number int(11) NOT NULL DEFAULT 1,
			is_current tinyint(1) NOT NULL DEFAULT 0,
			change_summary text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY block_id (block_id),
			KEY post_id (post_id),
			KEY user_id (user_id),
			KEY is_current (is_current)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create settings table
	 *
	 * @since 1.0.0
	 */
	private static function create_settings_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Wiki block settings table
		$settings_table = $wpdb->prefix . 'wilcoskywb_wiki_block_settings';
		
		$settings_sql = "CREATE TABLE $settings_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			block_id varchar(255) NOT NULL,
			merge_permissions text,
			browse_permissions text,
			suggest_permissions text,
			require_login_browse tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY block_unique (block_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $settings_sql );
	}

	/**
	 * Run database migrations
	 *
	 * @since 1.0.0
	 */
	public static function run_migrations() {
		global $wpdb;

		$current_version = get_option( 'wilcoskywb_wiki_blocks_db_version', '1.0.0' );

		// Migration 1.0.1: Add post_id column to versions table
		if ( version_compare( $current_version, '1.0.1', '<' ) ) {
			$table_name = $wpdb->prefix . 'wilcoskywb_wiki_block_versions';
			
			// Check if post_id column exists
			$column_exists = $wpdb->get_results( "SHOW COLUMNS FROM $table_name LIKE 'post_id'" );
			
			if ( empty( $column_exists ) ) {
				$wpdb->query( "ALTER TABLE $table_name ADD COLUMN post_id bigint(20) unsigned DEFAULT NULL AFTER block_id" );
				$wpdb->query( "ALTER TABLE $table_name ADD KEY post_id (post_id)" );
			}
			
			update_option( 'wilcoskywb_wiki_blocks_db_version', '1.0.1' );
		}
	}

	/**
	 * Insert a new wiki block version
	 *
	 * @since 1.0.0
	 * @param string $block_id The unique block identifier.
	 * @param string $content The block content.
	 * @param int    $user_id The user ID who made the change.
	 * @param string $change_summary Optional change summary.
	 * @param int    $post_id Optional post ID.
	 * @return int|false The insert ID on success, false on failure.
	 */
	public static function insert_version( $block_id, $content, $user_id, $change_summary = '', $post_id = null ) {
		global $wpdb;

		// Sanitize inputs
		$block_id = sanitize_text_field( $block_id );
		$content = wp_kses_post( $content );
		$user_id = absint( $user_id );
		$change_summary = sanitize_textarea_field( $change_summary );

		// Get next version number
		$version_number = self::get_next_version_number( $block_id );

		// Check if this is the first version (no existing versions)
		// Direct database query is necessary for custom table operations
		$existing_versions = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wilcoskywb_wiki_block_versions WHERE block_id = %s",
				$block_id
			)
		);

		// Only set as current if this is the first version
		$is_current = ( $existing_versions == 0 ) ? 1 : 0;

		// Check if post_id column exists
		$table_name = $wpdb->prefix . 'wilcoskywb_wiki_block_versions';
		$column_exists = $wpdb->get_results( "SHOW COLUMNS FROM $table_name LIKE 'post_id'" );
		
		// Prepare insert data - use the correct column order
		$insert_data = array(
			'block_id' => $block_id,
			'user_id' => $user_id,
			'version_number' => $version_number,
			'is_current' => $is_current,
			'change_summary' => $change_summary,
		);
		
		$insert_format = array( '%s', '%d', '%d', '%d', '%s' );
		
		// Add post_id and content if column exists
		if ( ! empty( $column_exists ) ) {
			$insert_data = array(
				'block_id' => $block_id,
				'post_id' => $post_id,
				'content' => $content,
				'user_id' => $user_id,
				'version_number' => $version_number,
				'is_current' => $is_current,
				'change_summary' => $change_summary,
			);
			$insert_format = array( '%s', '%d', '%s', '%d', '%d', '%d', '%s' );
		} else {
			$insert_data['content'] = $content;
		}

		// Insert new version
		$result = $wpdb->insert(
			$table_name,
			$insert_data,
			$insert_format
		);

		if ( false === $result ) {
			return false;
		}

		// Clear cache for this block
		wp_cache_delete( 'wilcoskywb_block_versions_' . $block_id, 'wiki-blocks' );
		wp_cache_delete( 'wilcoskywb_current_version_' . $block_id, 'wiki-blocks' );

		return $wpdb->insert_id;
	}

	/**
	 * Get the next version number for a block
	 *
	 * @since 1.0.0
	 * @param string $block_id The block ID.
	 * @return int The next version number.
	 */
	private static function get_next_version_number( $block_id ) {
		global $wpdb;

		// Direct database query is necessary for custom table operations
		$max_version = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(version_number) FROM {$wpdb->prefix}wilcoskywb_wiki_block_versions WHERE block_id = %s",
				$block_id
			)
		);

		return (int) $max_version + 1;
	}

	/**
	 * Get all versions for a block
	 *
	 * @since 1.0.0
	 * @param string $block_id The block ID.
	 * @return array Array of version objects.
	 */
	public static function get_block_versions( $block_id ) {
		global $wpdb;

		$block_id = sanitize_text_field( $block_id );

		// Check cache first
		$cache_key = 'wilcoskywb_block_versions_' . $block_id;
		$versions = wp_cache_get( $cache_key, 'wiki-blocks' );

		if ( false === $versions ) {
			// Direct database query is necessary for custom table operations
			$versions = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT v.*, u.display_name, u.user_email 
					FROM {$wpdb->prefix}wilcoskywb_wiki_block_versions v
					LEFT JOIN {$wpdb->users} u ON v.user_id = u.ID
					WHERE v.block_id = %s
					ORDER BY v.version_number DESC",
					$block_id
				)
			);

			// Cache for 5 minutes
			wp_cache_set( $cache_key, $versions, 'wiki-blocks', 300 );
		}

		return $versions;
	}

	/**
	 * Get current version for a block
	 *
	 * @since 1.0.0
	 * @param string $block_id The block ID.
	 * @return object|null Version object or null if not found.
	 */
	public static function get_current_version( $block_id ) {
		global $wpdb;

		$block_id = sanitize_text_field( $block_id );

		// Check cache first
		$cache_key = 'wilcoskywb_current_version_' . $block_id;
		$version = wp_cache_get( $cache_key, 'wiki-blocks' );

		if ( false === $version ) {
			// Direct database query is necessary for custom table operations
			$version = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT v.*, u.display_name, u.user_email 
					FROM {$wpdb->prefix}wilcoskywb_wiki_block_versions v
					LEFT JOIN {$wpdb->users} u ON v.user_id = u.ID
					WHERE v.block_id = %s AND v.is_current = 1",
					$block_id
				)
			);

			// Cache for 5 minutes
			wp_cache_set( $cache_key, $version, 'wiki-blocks', 300 );
		}

		return $version;
	}

	/**
	 * Merge a specific version as current
	 *
	 * @since 1.0.0
	 * @param int $version_id The version ID to merge.
	 * @return bool True on success, false on failure.
	 */
	public static function merge_version( $version_id ) {
		global $wpdb;

		$version_id = absint( $version_id );

		// Get the version to merge
		$version = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wilcoskywb_wiki_block_versions WHERE id = %d",
				$version_id
			)
		);

		if ( ! $version ) {
			return false;
		}

		// Set all versions for this block as not current
		// Direct database query is necessary for custom table operations
		$wpdb->update(
			$wpdb->prefix . 'wilcoskywb_wiki_block_versions',
			array( 'is_current' => 0 ),
			array( 'block_id' => $version->block_id ),
			array( '%d' ),
			array( '%s' )
		);

		// Set the selected version as current
		// Direct database query is necessary for custom table operations
		$result = $wpdb->update(
			$wpdb->prefix . 'wilcoskywb_wiki_block_versions',
			array( 'is_current' => 1 ),
			array( 'id' => $version_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			// Clear cache for this block
			wp_cache_delete( 'wilcoskywb_block_versions_' . $version->block_id, 'wiki-blocks' );
			wp_cache_delete( 'wilcoskywb_current_version_' . $version->block_id, 'wiki-blocks' );
		}

		return false !== $result;
	}

	/**
	 * Save block settings
	 *
	 * @since 1.0.0
	 * @param string $block_id The block ID.
	 * @param array  $settings The settings array.
	 * @return bool True on success, false on failure.
	 */
	public static function save_block_settings( $block_id, $settings ) {
		global $wpdb;

		$block_id = sanitize_text_field( $block_id );

		// Sanitize settings
		$merge_permissions = isset( $settings['merge_permissions'] ) ? array_map( 'sanitize_text_field', $settings['merge_permissions'] ) : array();
		$browse_permissions = isset( $settings['browse_permissions'] ) ? array_map( 'sanitize_text_field', $settings['browse_permissions'] ) : array();
		$suggest_permissions = isset( $settings['suggest_permissions'] ) ? array_map( 'sanitize_text_field', $settings['suggest_permissions'] ) : array();
		$require_login_browse = isset( $settings['require_login_browse'] ) ? (bool) $settings['require_login_browse'] : false;

		$data = array(
			'block_id' => $block_id,
			'merge_permissions' => wp_json_encode( $merge_permissions ),
			'browse_permissions' => wp_json_encode( $browse_permissions ),
			'suggest_permissions' => wp_json_encode( $suggest_permissions ),
			'require_login_browse' => $require_login_browse ? 1 : 0,
		);

		// Direct database query is necessary for custom table operations
		$result = $wpdb->replace(
			$wpdb->prefix . 'wilcoskywb_wiki_block_settings',
			$data,
			array( '%s', '%s', '%s', '%s', '%d' )
		);

		if ( false !== $result ) {
			// Clear cache for this block's settings
			wp_cache_delete( 'wilcoskywb_block_settings_' . $block_id, 'wiki-blocks' );
		}

		return false !== $result;
	}

	/**
	 * Get block settings
	 *
	 * @since 1.0.0
	 * @param string $block_id The block ID.
	 * @return array The settings array.
	 */
	public static function get_block_settings( $block_id ) {
		global $wpdb;

		$block_id = sanitize_text_field( $block_id );

		// Check cache first
		$cache_key = 'wilcoskywb_block_settings_' . $block_id;
		$settings = wp_cache_get( $cache_key, 'wiki-blocks' );

		if ( false === $settings ) {
			// Direct database query is necessary for custom table operations
			$settings = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}wilcoskywb_wiki_block_settings WHERE block_id = %s",
					$block_id
				)
			);

			// Cache for 5 minutes
			wp_cache_set( $cache_key, $settings, 'wiki-blocks', 300 );
		}

		if ( ! $settings ) {
			// Return default settings
			return array(
				'merge_permissions' => array( 'administrator' ),
				'browse_permissions' => array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ),
				'suggest_permissions' => array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ),
				'require_login_browse' => false,
			);
		}

		return array(
			'merge_permissions' => json_decode( $settings->merge_permissions, true ) ?: array( 'administrator' ),
			'browse_permissions' => json_decode( $settings->browse_permissions, true ) ?: array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ),
			'suggest_permissions' => json_decode( $settings->suggest_permissions, true ) ?: array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ),
			'require_login_browse' => (bool) $settings->require_login_browse,
		);
	}

	/**
	 * Delete all versions for a block
	 *
	 * @since 1.0.0
	 * @param string $block_id The block ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_block_versions( $block_id ) {
		global $wpdb;

		$block_id = sanitize_text_field( $block_id );

		// Direct database query is necessary for custom table operations
		$result = $wpdb->delete(
			$wpdb->prefix . 'wilcoskywb_wiki_block_versions',
			array( 'block_id' => $block_id ),
			array( '%s' )
		);

		if ( false !== $result ) {
			// Clear cache for this block
			wp_cache_delete( 'wilcoskywb_block_versions_' . $block_id, 'wiki-blocks' );
			wp_cache_delete( 'wilcoskywb_current_version_' . $block_id, 'wiki-blocks' );
		}

		return false !== $result;
	}

	/**
	 * Delete block settings
	 *
	 * @since 1.0.0
	 * @param string $block_id The block ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_block_settings( $block_id ) {
		global $wpdb;

		$block_id = sanitize_text_field( $block_id );

		// Direct database query is necessary for custom table operations
		$result = $wpdb->delete(
			$wpdb->prefix . 'wilcoskywb_wiki_block_settings',
			array( 'block_id' => $block_id ),
			array( '%s' )
		);

		if ( false !== $result ) {
			// Clear cache for this block's settings
			wp_cache_delete( 'wilcoskywb_block_settings_' . $block_id, 'wiki-blocks' );
		}

		return false !== $result;
	}
} 