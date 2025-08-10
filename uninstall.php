<?php
/**
 * Uninstall Wiki Blocks Plugin
 *
 * @package WikiBlocks
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if cleanup is enabled
$cleanup_on_uninstall = get_option( 'wilcoskywb_wiki_blocks_cleanup_on_uninstall', true );

if ( ! $cleanup_on_uninstall ) {
	return;
}

// Get database prefix
global $wpdb;

// Drop custom tables - Direct database queries are necessary for plugin uninstallation
// These operations remove custom plugin tables and their data
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wilcoskywb_wiki_block_versions" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wilcoskywb_wiki_block_settings" );

// Delete plugin options
delete_option( 'wilcoskywb_wiki_blocks_cleanup_on_uninstall' );
delete_option( 'wilcoskywb_wiki_blocks_merge_permissions' );
delete_option( 'wilcoskywb_wiki_blocks_browse_permissions' );
delete_option( 'wilcoskywb_wiki_blocks_suggest_permissions' );
delete_option( 'wilcoskywb_wiki_blocks_require_login_browse' );

// Clear any cached data that has been removed
wp_cache_flush(); 