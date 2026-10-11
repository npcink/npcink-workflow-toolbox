<?php
/** Executes uninstall.php against isolated WordPress API stubs. */

define( 'WP_UNINSTALL_PLUGIN', true );

$GLOBALS['npcink_uninstall_options']    = array();
$GLOBALS['npcink_uninstall_transients'] = array();
$GLOBALS['npcink_uninstall_hooks']      = array();
$GLOBALS['npcink_uninstall_post_meta']  = array();

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

function delete_post_meta_by_key( string $meta_key ): bool {
	$GLOBALS['npcink_uninstall_post_meta'][] = $meta_key;
	return true;
}

function is_multisite(): bool {
	return false;
}

final class Npcink_Uninstall_Wpdb_Stub {
	public string $options = 'wp_options';

	public function esc_like( string $value ): string {
		return addcslashes( $value, '_%\\' );
	}

	public function prepare( string $query, ...$values ): string {
		if ( 1 === count( $values ) && is_array( $values[0] ) ) {
			$values = $values[0];
		}
		return vsprintf( str_replace( '%s', "'%s'", $query ), $values );
	}

	/** @var string[] */
	public array $queries = array();

	/** @return string[] */
	public function get_col( string $query ): array {
		if ( false === strpos( $query, 'npcink\\_toolbox\\_' ) ) {
			fwrite( STDERR, "FAIL: Dynamic transient query is not bounded to the Toolbox prefix.\n" );
			exit( 1 );
		}
		$this->queries[] = $query;
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
$required_hooks   = array(
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
$editor_bounded = false;
foreach ( $wpdb->queries as $query ) {
	if ( false !== strpos( $query, '_transient\\_npcink\\_toolbox\\_editor\\_' ) ) {
		$editor_bounded = true;
		break;
	}
}
if ( ! $editor_bounded ) {
	fwrite( STDERR, "FAIL: Dynamic editor transient query is not bounded to the editor prefix.\n" );
	exit( 1 );
}
if ( array( '_npcink_toolbox_media_fingerprint_cache' ) !== $GLOBALS['npcink_uninstall_post_meta'] ) {
	fwrite( STDERR, 'FAIL: Uninstall did not remove the media fingerprint memo meta.' . PHP_EOL );
	exit( 1 );
}

echo "Uninstall cleanup behavior: ok\n";
