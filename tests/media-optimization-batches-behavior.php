<?php
/** Focused behavior checks for foreground exact-manifest media optimization batches. */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wp-stub/' );
}

class WP_Error {
	private string $code;
	public function __construct( string $code ) { $this->code = $code; }
	public function get_error_code(): string { return $this->code; }
}

function absint( $value ): int { return abs( (int) $value ); }
function esc_url_raw( string $value ): string { return $value; }
function sanitize_key( string $value ): string { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ) ?? ''; }
function sanitize_text_field( string $value ): string { return trim( $value ); }
function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function wp_json_encode( $value ): string { return (string) json_encode( $value ); }
function wp_generate_uuid4(): string { static $sequence = 0; ++$sequence; return sprintf( '00000000-0000-4000-8000-%012d', $sequence ); }
function get_current_user_id(): int { return 7; }
function get_post_type( int $attachment_id ): string { return isset( $GLOBALS['batch_files'][ $attachment_id ] ) ? 'attachment' : ''; }
function wp_attachment_is_image( int $attachment_id ): bool { return isset( $GLOBALS['batch_files'][ $attachment_id ] ); }
function current_user_can( string $capability, int $attachment_id = 0 ): bool { return 'upload_files' === $capability || ( 'edit_post' === $capability && isset( $GLOBALS['batch_files'][ $attachment_id ] ) ); }
function get_attached_file( int $attachment_id ): string { return (string) ( $GLOBALS['batch_files'][ $attachment_id ] ?? '' ); }
function get_option( string $name, $default = false ) { return $GLOBALS['batch_options'][ $name ] ?? $default; }
function update_option( string $name, $value ): bool { $GLOBALS['batch_options'][ $name ] = $value; return true; }
function map_deep( $value, callable $callback ) {
	if ( is_array( $value ) ) return array_map( static fn( $item ) => map_deep( $item, $callback ), $value );
	return call_user_func( $callback, $value );
}

$GLOBALS['batch_filters'] = array();
$GLOBALS['batch_ability_calls'] = array();
$GLOBALS['batch_adopt_failure'] = false;
function add_filter( string $hook, callable $callback ): void { $GLOBALS['batch_filters'][ $hook ] = $callback; }
function remove_filter( string $hook, callable $callback ): void {
	if ( ( $GLOBALS['batch_filters'][ $hook ] ?? null ) === $callback ) unset( $GLOBALS['batch_filters'][ $hook ] );
}
function npcink_abilities_toolkit_get_registered(): array {
	return array(
		'npcink-abilities-toolkit/build-media-derivative-batch-plan' => array(
			'execute_callback' => static function ( array $input ) {
				$GLOBALS['batch_ability_calls'][] = array( 'manifest', $input );
				return array( 'status' => 'success', 'data' => array( 'plan_contract_version' => 'toolbox_media_optimization_manifest.v1' ) );
			},
		),
		'npcink-abilities-toolkit/adopt-cloud-media-derivative' => array(
			'execute_callback' => static function ( array $input ) {
				$GLOBALS['batch_ability_calls'][] = array( 'adopt', $input );
				if ( ! empty( $GLOBALS['batch_adopt_failure'] ) ) return new WP_Error( 'test_adopt_failed' );
				if ( ! empty( $input['dry_run'] ) ) return array( 'dry_run' => true );
				$filter = $GLOBALS['batch_filters']['npcink_abilities_toolkit_write_commit_allowed'] ?? null;
				if ( ! is_callable( $filter ) || ! $filter( false, 'npcink-abilities-toolkit/adopt-cloud-media-derivative' ) || $filter( false, 'npcink-abilities-toolkit/update-post' ) ) return new WP_Error( 'test_adopt_scope_failed' );
				return array( 'replacement_id' => 'replacement-' . $input['attachment_id'], 'backup' => array( 'backup_id' => 'backup-' . $input['attachment_id'] ), 'after' => array( 'filesize_bytes' => 500 ) );
			},
		),
		'npcink-abilities-toolkit/restore-media-backup' => array(
			'execute_callback' => static function ( array $input ) {
				$GLOBALS['batch_ability_calls'][] = array( 'restore', $input );
				if ( 'backup-' . $input['attachment_id'] !== $input['backup_id'] ) return new WP_Error( 'test_restore_backup_id_mismatch' );
				return array( 'restored' => true );
			},
		),
	);
}

function batch_assert( bool $condition, string $message ): void {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
	echo "PASS: {$message}\n";
}

require_once dirname( __DIR__ ) . '/includes/Media_Optimization_Batches.php';

$fixture_dir = sys_get_temp_dir() . '/npcink-toolbox-batch-' . getmypid();
if ( ! is_dir( $fixture_dir ) ) mkdir( $fixture_dir, 0755, true );
$GLOBALS['batch_files'] = array();
for ( $attachment_id = 41; $attachment_id <= 45; ++$attachment_id ) {
	$path = $fixture_dir . '/' . $attachment_id . '.jpg';
	file_put_contents( $path, 'source-' . $attachment_id );
	$GLOBALS['batch_files'][ $attachment_id ] = $path;
}

$candidate = static function ( int $attachment_id ): array {
	return array(
		'attachment_id' => $attachment_id,
		'title' => 'Image ' . $attachment_id,
		'mime_type' => 'image/jpeg',
		'url' => 'https://example.test/' . $attachment_id . '.jpg',
		'width' => 2400,
		'height' => 1600,
		'filesize_bytes' => 1000,
		'media_fingerprint' => 'sha256:' . hash_file( 'sha256', $GLOBALS['batch_files'][ $attachment_id ] ),
	);
};
$plan = array(
	'plan_contract_version' => 'toolbox_media_optimization_manifest.v1',
	'filters' => array( 'image_types' => array( 'jpeg' ), 'resize_mode' => 'preserve' ),
	'candidates' => array_map( $candidate, array( 41, 42, 43, 44, 45 ) ),
);

$service = new \Npcink_Toolbox\Media_Optimization_Batches();
$manifest = $service->build_manifest( array( 'scope_preset' => 'one_month', 'image_types' => array( 'jpeg', 'png', 'webp' ) ) );
batch_assert( is_array( $manifest ) && 'toolbox_media_optimization_manifest.v1' === (string) ( $manifest['data']['plan_contract_version'] ?? '' ), 'The administrator check action invokes the existing Toolkit read-only manifest ability directly.' );
batch_assert( 'manifest' === (string) ( $GLOBALS['batch_ability_calls'][0][0] ?? '' ), 'Manifest planning does not require a Core proposal or a second read confirmation.' );
$invalid = $service->create( array( 'plan' => array_merge( $plan, array( 'plan_contract_version' => 'v2' ) ) ) );
batch_assert( is_wp_error( $invalid ) && 'npcink_toolbox_media_batch_manifest_invalid' === $invalid->get_error_code(), 'Batch creation rejects any non-exact manifest contract.' );

$drift_plan = $plan;
$drift_plan['candidates'][0]['media_fingerprint'] = 'sha256:' . str_repeat( 'a', 64 );
$drift = $service->create( array( 'plan' => $drift_plan ) );
batch_assert( is_wp_error( $drift ) && 'npcink_toolbox_media_batch_source_drift' === $drift->get_error_code(), 'Batch creation rejects a source fingerprint that already drifted.' );

$batch = $service->create( array( 'plan' => $plan ) );
batch_assert( is_array( $batch ) && 'ready_for_review' === $batch['status'] && 5 === $batch['summary']['pending'], 'Batch creation freezes all exact manifest items without processing them.' );
batch_assert( 'auto_safe.v1' === $batch['optimization_profile'] && 10 === $batch['chunk_size'] && 'preserve' === $batch['resize_mode'], 'Batch pins the safe policy, foreground chunk size, and resize choice.' );
batch_assert( 'auto_safe' === $batch['items'][0]['cloud_request_input']['optimization_mode'] && ! isset( $batch['items'][0]['cloud_request_input']['quality'] ), 'Batch Cloud input omits user quality controls.' );

$wrong_confirmation = $service->confirm( $batch['batch_id'], array( 'confirm' => true, 'manifest_digest' => 'sha256:' . str_repeat( 'b', 64 ) ) );
batch_assert( is_wp_error( $wrong_confirmation ) && 'npcink_toolbox_media_batch_confirmation_invalid' === $wrong_confirmation->get_error_code(), 'Batch start requires confirmation of the exact manifest digest.' );
$batch = $service->confirm( $batch['batch_id'], array( 'confirm' => true, 'manifest_digest' => $batch['manifest_digest'] ) );
batch_assert( 'running' === $batch['status'] && 7 === $batch['confirmed_by'], 'Exact confirmation starts foreground execution and records the administrator.' );

$batch = $service->complete_item( $batch['batch_id'], 41, array( 'status' => 'skipped', 'reason' => 'minimum_savings_not_met' ) );
$skipped_attempts = $batch['items'][0]['attempt_count'];
$batch = $service->complete_item( $batch['batch_id'], 41, array( 'status' => 'skipped', 'reason' => 'network_retry' ) );
batch_assert( 1 === $batch['summary']['skipped'] && $skipped_attempts === $batch['items'][0]['attempt_count'], 'Repeated completion of a skipped item is idempotent.' );

file_put_contents( $GLOBALS['batch_files'][42], 'changed-after-manifest' );
$batch = $service->complete_item( $batch['batch_id'], 42, array( 'status' => 'qualified', 'derivative_artifact' => array( 'artifact_id' => 'art-42' ) ) );
batch_assert( 'skipped' === $batch['items'][1]['status'] && 'source_fingerprint_changed' === $batch['items'][1]['error_code'], 'A second fingerprint check skips a source changed before replacement.' );

$GLOBALS['batch_adopt_failure'] = true;
foreach ( array( 43, 44, 45 ) as $attachment_id ) {
	$batch = $service->complete_item( $batch['batch_id'], $attachment_id, array( 'status' => 'qualified', 'derivative_artifact' => array( 'artifact_id' => 'art-' . $attachment_id ) ) );
}
batch_assert( 'paused' === $batch['status'] && 'three_consecutive_failures' === $batch['pause_reason'], 'Three consecutive item failures pause the foreground batch.' );

$success_plan = $plan;
$success_plan['candidates'] = array( $candidate( 41 ) );
$success_batch = $service->create( array( 'plan' => $success_plan ) );
$success_batch = $service->confirm( $success_batch['batch_id'], array( 'confirm' => true, 'manifest_digest' => $success_batch['manifest_digest'] ) );
$GLOBALS['batch_adopt_failure'] = false;
$success_batch = $service->complete_item( $success_batch['batch_id'], 41, array( 'status' => 'qualified', 'derivative_artifact' => array( 'artifact_id' => 'art-41', 'mime_type' => 'image/webp' ) ) );
batch_assert( 'completed' === $success_batch['status'] && 1 === $success_batch['summary']['success'] && 500 === $success_batch['summary']['bytes_saved'], 'A qualified item uses Toolkit and completes the batch with savings.' );
$current = ( new \Npcink_Toolbox\Media_Optimization_Batches() )->current();
batch_assert( $success_batch['batch_id'] === $current['batch_id'], 'A fresh service instance resumes from the latest persisted batch.' );
$restored = $service->restore_item( $success_batch['batch_id'], 41 );
batch_assert( is_array( $restored ) && 'restored' === $restored['items'][0]['restore_status'], 'A completed batch item can be restored through the existing Toolkit ability.' );
batch_assert( 'backup-41' === (string) ( $GLOBALS['batch_ability_calls'][ count( $GLOBALS['batch_ability_calls'] ) - 1 ][1]['backup_id'] ?? '' ), 'Batch restore passes the exact Toolkit backup identifier rather than the replacement history identifier.' );
$restored_again = $service->restore_item( $success_batch['batch_id'], 41 );
batch_assert( is_array( $restored_again ) && 'restored' === $restored_again['items'][0]['restore_status'], 'Repeated restore is idempotent.' );
batch_assert( empty( $GLOBALS['batch_filters'] ), 'Request-scoped Toolkit write authorization is always removed.' );

echo "OK: media optimization batch behavior\n";
