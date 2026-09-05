<?php
/**
 * Uninstall cleanup for Npcink Toolbox.
 *
 * @package Npcink_Toolbox
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/** Removes Toolbox-owned state for the current site without touching adopted content or Toolkit backups. */
function npcink_toolbox_uninstall_current_site(): void {
	foreach (
		array(
			'npcink_toolbox_settings',
			'npcink_toolbox_content_context',
			'npcink_toolbox_media_optimization_settings',
			'npcink_toolbox_watermark_templates',
			'npcink_toolbox_zhihu_hot_topic_pool_backup_v1',
			'npcink_toolbox_media_optimization_batches',
			'npcink_toolbox_media_recognition_continuation',
			'npcink_toolbox_media_recognition_continuation_lock',
			'npcink_toolbox_site_knowledge_auto_sync_queue',
			'npcink_local_automation_runtime_nightly_inspection_latest_preview',
			'npcink_local_automation_runtime_nightly_inspection_schedule_signature',
		) as $option_name
	) {
		delete_option( $option_name );
	}

	delete_transient( 'npcink_toolbox_zhihu_hot_topic_pool_v2' );

	global $wpdb;
	$transient_prefixes = array(
		$wpdb->esc_like( '_transient_npcink_toolbox_editor_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_npcink_toolbox_editor_' ) . '%',
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall must enumerate dynamic, expiring transient keys before removing them through WordPress APIs.
	$editor_cache_options = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$transient_prefixes[0],
			$transient_prefixes[1]
		)
	);
	foreach ( $editor_cache_options as $option_name ) {
		delete_option( (string) $option_name );
	}

	foreach (
		array(
			'npcink_local_automation_runtime_nightly_inspection_dry_run',
			'npcink_toolbox_continue_media_recognition',
			'npcink_toolbox_weekly_media_fingerprint_scan',
			'npcink_toolbox_process_site_knowledge_auto_sync',
			'npcink_toolbox_reconcile_site_knowledge_auto_sync',
		) as $hook
	) {
		wp_clear_scheduled_hook( $hook );
	}
}

if ( is_multisite() ) {
	$npcink_toolbox_site_offset = 0;
	do {
		$npcink_toolbox_site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $npcink_toolbox_site_offset,
			)
		);
		foreach ( $npcink_toolbox_site_ids as $npcink_toolbox_site_id ) {
			switch_to_blog( (int) $npcink_toolbox_site_id );
			npcink_toolbox_uninstall_current_site();
			restore_current_blog();
		}
		$npcink_toolbox_site_offset += count( $npcink_toolbox_site_ids );
	} while ( 100 === count( $npcink_toolbox_site_ids ) );
} else {
	npcink_toolbox_uninstall_current_site();
}
