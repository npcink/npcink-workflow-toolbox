<?php
/** Executes uninstall.php against isolated WordPress API stubs. */

define( 'WP_UNINSTALL_PLUGIN', true );

$GLOBALS['npcink_uninstall_options']    = array();
$GLOBALS['npcink_uninstall_transients'] = array();
$GLOBALS['npcink_uninstall_hooks']      = array();

function delete_option( string $name ): bool {
	$GLOBALS['npcink_uninstall_options'][] = $name;
	return true;
}

function delete_transient( string $name ): bool {
	$GLOBALS['npcink_uninstall_transients'][] = $name;
	return true;
}

function wp_clear_scheduled_hook( string $hook ): int {
	$GLOBALS['npcink_uninstall_hooks'][] = $hook;
	return 1;
}

function is_multisite(): bool {
	return false;
}

final class Npcink_Uninstall_Wpdb_Stub {
	public string $options = 'wp_options';

	public function esc_like( string $value ): string {
		return addcslashes( $value, '_%\\' );
	}

	public function prepare( string $query, string ...$values ): string {
		return vsprintf( str_replace( '%s', "'%s'", $query ), $values );
	}

	/** @return string[] */
	public function get_col( string $query ): array {
		if ( false === strpos( $query, '_transient\\_npcink\\_toolbox\\_editor\\_' ) ) {
			fwrite( STDERR, "FAIL: Dynamic editor transient query is not bounded to the Toolbox prefix.\n" );
			exit( 1 );
		}
		return array(
			'_transient_npcink_toolbox_editor_fixture',
			'_transient_timeout_npcink_toolbox_editor_fixture',
		);
	}
}

$wpdb = new Npcink_Uninstall_Wpdb_Stub();

require dirname( __DIR__ ) . '/uninstall.php';

$required_options = array(
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
	'_transient_npcink_toolbox_editor_fixture',
	'_transient_timeout_npcink_toolbox_editor_fixture',
);
$required_hooks = array(
	'npcink_local_automation_runtime_nightly_inspection_dry_run',
	'npcink_toolbox_continue_media_recognition',
	'npcink_toolbox_weekly_media_fingerprint_scan',
	'npcink_toolbox_process_site_knowledge_auto_sync',
	'npcink_toolbox_reconcile_site_knowledge_auto_sync',
);

$missing_options = array_diff( $required_options, $GLOBALS['npcink_uninstall_options'] );
$missing_hooks   = array_diff( $required_hooks, $GLOBALS['npcink_uninstall_hooks'] );
if ( array() !== $missing_options || array() !== $missing_hooks || array( 'npcink_toolbox_zhihu_hot_topic_pool_v2' ) !== $GLOBALS['npcink_uninstall_transients'] ) {
	fwrite( STDERR, 'FAIL: Uninstall left Toolbox-owned state behind.' . PHP_EOL );
	exit( 1 );
}

echo "Uninstall cleanup behavior: ok\n";
