<?php
/**
 * Fired when the Pro plugin is uninstalled.
 *
 * Only removes Pro-specific data if the user opted in.
 *
 * @package Smart_Local_AI_Pro
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$pro_settings = get_option( 'atlas_ai_pro_settings', array() );
$delete_data  = isset( $pro_settings['delete_data_on_uninstall'] ) ? (bool) $pro_settings['delete_data_on_uninstall'] : false;

// Also check the free plugin's setting as a fallback.
if ( ! $delete_data ) {
	$free_settings = get_option( 'atlas_ai_settings', array() );
	$delete_data   = isset( $free_settings['delete_data_on_uninstall'] ) ? (bool) $free_settings['delete_data_on_uninstall'] : false;
}

if ( ! $delete_data ) {
	// User chose to keep data. Only clear cron hooks.
	wp_clear_scheduled_hook( 'atlas_ai_pro_aggregate_affinities' );
	wp_clear_scheduled_hook( 'atlas_ai_pro_abandoned_checkout' );
	wp_clear_scheduled_hook( 'atlas_ai_pro_license_check' );
	return;
}

// ── User opted to delete all Pro data ──

global $wpdb;

// 1. Drop Pro-specific tables.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}atlasai_user_exclusions" );

// 2. Delete Pro options.
delete_option( 'atlas_ai_pro_settings' );
delete_option( 'atlas_ai_pro_license' );

// 3. Delete Pro transients.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_atlas_ai_pro_' ) . '%%',
		$wpdb->esc_like( '_transient_timeout_atlas_ai_pro_' ) . '%%'
	)
);

// 4. Clear scheduled cron events.
wp_clear_scheduled_hook( 'atlas_ai_pro_aggregate_affinities' );
wp_clear_scheduled_hook( 'atlas_ai_pro_abandoned_checkout' );
wp_clear_scheduled_hook( 'atlas_ai_pro_license_check' );
