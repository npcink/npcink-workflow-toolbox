<?php
/** Focused no-credit checks for one local media replacement. */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wp-stub/' );
}

class WP_Error {
	private string $code;
	public function __construct( string $code ) { $this->code = $code; }
	public function get_error_code(): string { return $this->code; }
}

class WP_REST_Request {
	/** @var array<string,mixed> */
	private array $json;
	/** @param array<string,mixed> $json */
	public function __construct( array $json ) { $this->json = $json; }
	/** @return array<string,mixed> */
	public function get_json_params(): array { return $this->json; }
	public function get_param( string $key ) { return $this->json[ $key ] ?? null; }
}

function __( string $value ): string { return $value; }
function sanitize_key( string $value ): string { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ) ?? ''; }
function sanitize_text_field( string $value ): string { return trim( $value ); }
function sanitize_file_name( string $value ): string { return preg_replace( '/[^A-Za-z0-9._-]/', '', $value ) ?? ''; }
function absint( $value ): int { return abs( (int) $value ); }
function get_post_type( int $post_id ): string { return 42 === $post_id ? 'attachment' : ''; }
function wp_attachment_is_image( int $post_id ): bool { return 42 === $post_id; }
function current_user_can( string $capability, int $post_id = 0 ): bool { return 'upload_files' === $capability || ( 'edit_post' === $capability && 42 === $post_id ); }
function get_current_user_id(): int { return 7; }
function is_wp_error( $value ): bool { return $value instanceof WP_Error; }

$npcink_toolbox_test_filters = array();
$npcink_toolbox_test_calls   = array();
$npcink_toolbox_restore_mode = 'success';
function add_filter( string $hook, callable $callback ): void { global $npcink_toolbox_test_filters; $npcink_toolbox_test_filters[ $hook ] = $callback; }
function remove_filter( string $hook, callable $callback ): void { global $npcink_toolbox_test_filters; if ( ( $npcink_toolbox_test_filters[ $hook ] ?? null ) === $callback ) { unset( $npcink_toolbox_test_filters[ $hook ] ); } }

function npcink_abilities_toolkit_get_registered(): array {
	return array(
		'npcink-abilities-toolkit/adopt-cloud-media-derivative' => array(
			'execute_callback' => static function ( array $input ) {
				global $npcink_toolbox_test_calls, $npcink_toolbox_test_filters;
				$npcink_toolbox_test_calls[] = $input;
				if ( empty( $input['dry_run'] ) ) {
					$filter = $npcink_toolbox_test_filters['npcink_abilities_toolkit_write_commit_allowed'] ?? null;
					if ( ! is_callable( $filter ) || ! $filter( false, 'npcink-abilities-toolkit/adopt-cloud-media-derivative' ) || $filter( false, 'npcink-abilities-toolkit/update-post' ) ) {
						return new WP_Error( 'test_authorization_scope_failed' );
					}
				}
				return ! empty( $input['dry_run'] )
					? array( 'dry_run' => true, 'preview' => array( 'backup_created' => true ) )
					: array( 'replaced' => true, 'backup' => array( 'relative_file' => 'backup.webp' ), 'verification' => array( 'verified' => true ) );
			},
		),
		'npcink-abilities-toolkit/list-media-backups' => array(
			'execute_callback' => static function ( array $input ) {
				global $npcink_toolbox_test_calls, $npcink_toolbox_restore_mode;
				$npcink_toolbox_test_calls[] = array( 'ability_id' => 'list-media-backups', 'input' => $input );
				if ( 'list_error' === $npcink_toolbox_restore_mode ) {
					return new WP_Error( 'test_list_error' );
				}
				$backups = array(
					array( 'backup_id' => 'backup_new', 'file_exists' => true ),
					array( 'backup_id' => 'backup_missing', 'file_exists' => false ),
				);
				if ( 'duplicate' === $npcink_toolbox_restore_mode ) {
					$backups[] = array( 'backup_id' => 'backup_new', 'file_exists' => true );
				}
				return array( 'data' => array( 'backups' => $backups, 'current_file' => array( 'relative_file' => '2026/08/current.webp', 'mime_type' => 'image/webp' ) ) );
			},
		),
		'npcink-abilities-toolkit/restore-media-backup' => array(
			'execute_callback' => static function ( array $input ) {
				global $npcink_toolbox_test_calls, $npcink_toolbox_test_filters, $npcink_toolbox_restore_mode;
				$npcink_toolbox_test_calls[] = array( 'ability_id' => 'restore-media-backup', 'input' => $input );
				if ( ! empty( $input['dry_run'] ) ) {
					return 'dry_run_error' === $npcink_toolbox_restore_mode ? new WP_Error( 'test_restore_dry_run_error' ) : array( 'dry_run' => true, 'preview' => array( 'restore_ready' => true ) );
				}
				$filter = $npcink_toolbox_test_filters['npcink_abilities_toolkit_write_commit_allowed'] ?? null;
				if ( ! is_callable( $filter ) || ! $filter( false, 'npcink-abilities-toolkit/restore-media-backup' ) || $filter( false, 'npcink-abilities-toolkit/update-post' ) ) {
					return new WP_Error( 'test_restore_authorization_scope_failed' );
				}
				if ( 'commit_error' === $npcink_toolbox_restore_mode ) {
					return new WP_Error( 'test_restore_commit_error' );
				}
				$result = array(
					'restored' => true,
					'rolled_back' => true,
					'verification' => array(
						'media_file_matches_expected' => true,
						'media_mime_type_matches_expected' => true,
						'backup_available' => true,
						'rollback_available' => true,
						'post_references_verified' => array( array( 'old_url_absent' => true, 'new_url_present' => true ) ),
					),
				);
				if ( 'incomplete_success' === $npcink_toolbox_restore_mode ) {
					unset( $result['verification']['rollback_available'] );
				}
				return $result;
			},
		),
	);
}

function single_image_test_assert( bool $condition, string $message ): void {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
	echo "PASS: {$message}\n";
}

require_once dirname( __DIR__ ) . '/includes/Operation_Classifier.php';
require_once dirname( __DIR__ ) . '/includes/Single_Image_Media_Optimization.php';

$artifact_id = 'art_' . str_repeat( 'a', 32 );
$artifact = array(
	'artifact_id' => $artifact_id, 'expires_at' => gmdate( 'c', time() + 900 ),
	'mime_type' => 'image/webp', 'format' => 'webp', 'width' => 320, 'height' => 180,
	'filesize_bytes' => 1024, 'sha256' => str_repeat( 'b', 64 ),
	'suggested_filename' => 'optimized.webp', 'filename_basis' => array(), 'processing_warnings' => array(),
);
$request_data = array(
	'action' => 'replace_current',
	'confirmed_action' => 'replace_current',
	'confirmed_artifact_id' => $artifact_id,
	'preview_verified' => true,
	'input' => array( 'attachment_id' => 42, 'derivative_artifact' => $artifact, 'expected_derivative_mime_type' => 'image/webp', 'file_name' => 'reviewed.webp' ),
);
$service = new \Npcink_Toolbox\Single_Image_Media_Optimization();

$unverified = $request_data;
$unverified['preview_verified'] = false;
$unverified_result = $service->execute( new WP_REST_Request( $unverified ) );
single_image_test_assert( is_wp_error( $unverified_result ) && 'npcink_toolbox_single_image_preview_unverified' === $unverified_result->get_error_code(), 'Local replacement rejects an unverified preview.' );

$mismatch = $request_data;
$mismatch['confirmed_artifact_id'] = 'art_' . str_repeat( 'c', 32 );
$mismatch_result = $service->execute( new WP_REST_Request( $mismatch ) );
single_image_test_assert( is_wp_error( $mismatch_result ) && 'npcink_toolbox_single_image_artifact_unconfirmed' === $mismatch_result->get_error_code(), 'Local replacement rejects artifact confirmation drift.' );

$reordered_request = array_reverse( $request_data, true );
$reordered_result = $service->execute( new WP_REST_Request( $reordered_request ) );
single_image_test_assert( is_array( $reordered_result ) && 'completed' === ( $reordered_result['status'] ?? '' ), 'Equivalent JSON object key order does not change the exact request contract.' );
$npcink_toolbox_test_calls = array();

$result = $service->execute( new WP_REST_Request( $request_data ) );
single_image_test_assert( is_array( $result ) && true === ( $result['direct_wordpress_write'] ?? false ) && false === ( $result['core_proposal_required'] ?? true ), 'One exact confirmed attachment replacement completes without a Core proposal.' );
single_image_test_assert( 2 === count( $npcink_toolbox_test_calls ) && true === $npcink_toolbox_test_calls[0]['dry_run'] && false === $npcink_toolbox_test_calls[1]['dry_run'], 'Toolkit dry-run validation precedes the commit call.' );
single_image_test_assert( empty( $npcink_toolbox_test_filters ), 'Request-scoped Toolkit authorization is removed after execution.' );

$restore_data = array(
	'attachment_id' => 42,
	'backup_id' => 'backup_new',
	'confirmed_backup_id' => 'backup_new',
	'preview_verified' => true,
	'confirm_restore' => true,
);

$restore_extra = $restore_data;
$restore_extra['unexpected'] = true;
$restore_extra_result = $service->restore( new WP_REST_Request( $restore_extra ) );
single_image_test_assert( is_wp_error( $restore_extra_result ) && 'npcink_toolbox_single_image_request_invalid' === $restore_extra_result->get_error_code(), 'Restore rejects unsupported top-level fields.' );

$restore_drift = $restore_data;
$restore_drift['confirmed_backup_id'] = 'backup_old';
$restore_drift_result = $service->restore( new WP_REST_Request( $restore_drift ) );
single_image_test_assert( is_wp_error( $restore_drift_result ) && 'npcink_toolbox_media_restore_unconfirmed' === $restore_drift_result->get_error_code(), 'Restore rejects backup confirmation drift.' );

$restore_unverified = $restore_data;
$restore_unverified['preview_verified'] = false;
$restore_unverified_result = $service->restore( new WP_REST_Request( $restore_unverified ) );
single_image_test_assert( is_wp_error( $restore_unverified_result ) && 'npcink_toolbox_media_restore_unconfirmed' === $restore_unverified_result->get_error_code(), 'Restore rejects a backup that was not visibly previewed.' );

$restore_invalid_attachment = $restore_data;
$restore_invalid_attachment['attachment_id'] = 99;
$restore_invalid_attachment_result = $service->restore( new WP_REST_Request( $restore_invalid_attachment ) );
single_image_test_assert( is_wp_error( $restore_invalid_attachment_result ) && 'npcink_toolbox_single_image_attachment_invalid' === $restore_invalid_attachment_result->get_error_code(), 'Restore rejects a non-image attachment.' );

$npcink_toolbox_restore_mode = 'list_error';
$restore_list_error = $service->restore( new WP_REST_Request( $restore_data ) );
single_image_test_assert( is_wp_error( $restore_list_error ) && 'test_list_error' === $restore_list_error->get_error_code(), 'Restore propagates the backup-list ability error.' );

$npcink_toolbox_restore_mode = 'success';
$missing_backup = $restore_data;
$missing_backup['backup_id'] = 'backup_missing';
$missing_backup['confirmed_backup_id'] = 'backup_missing';
$missing_backup_result = $service->restore( new WP_REST_Request( $missing_backup ) );
single_image_test_assert( is_wp_error( $missing_backup_result ) && 'npcink_toolbox_media_backup_unavailable' === $missing_backup_result->get_error_code(), 'Restore rejects a backup whose file is unavailable.' );

$npcink_toolbox_restore_mode = 'duplicate';
$duplicate_backup_result = $service->restore( new WP_REST_Request( $restore_data ) );
single_image_test_assert( is_wp_error( $duplicate_backup_result ) && 'npcink_toolbox_media_backup_unavailable' === $duplicate_backup_result->get_error_code(), 'Restore rejects duplicate records for one backup id.' );

$npcink_toolbox_restore_mode = 'dry_run_error';
$npcink_toolbox_test_calls = array();
$restore_dry_run_error = $service->restore( new WP_REST_Request( $restore_data ) );
single_image_test_assert( is_wp_error( $restore_dry_run_error ) && 'test_restore_dry_run_error' === $restore_dry_run_error->get_error_code(), 'Restore stops when Toolkit dry-run validation fails.' );
single_image_test_assert( 2 === count( $npcink_toolbox_test_calls ), 'A failed restore dry-run never reaches the commit call.' );

$npcink_toolbox_restore_mode = 'commit_error';
$restore_commit_error = $service->restore( new WP_REST_Request( $restore_data ) );
single_image_test_assert( is_wp_error( $restore_commit_error ) && 'test_restore_commit_error' === $restore_commit_error->get_error_code(), 'Restore propagates the Toolkit commit error.' );
single_image_test_assert( empty( $npcink_toolbox_test_filters ), 'Restore authorization is removed after a failed commit.' );

$npcink_toolbox_restore_mode = 'incomplete_success';
$restore_incomplete = $service->restore( new WP_REST_Request( $restore_data ) );
single_image_test_assert( is_wp_error( $restore_incomplete ) && 'npcink_toolbox_media_restore_verification_failed' === $restore_incomplete->get_error_code(), 'Restore fails closed on an incomplete Toolkit success payload.' );
single_image_test_assert( empty( $npcink_toolbox_test_filters ), 'Restore authorization is removed after incomplete verification.' );

$npcink_toolbox_restore_mode = 'success';
$npcink_toolbox_test_calls = array();
$restore_result = $service->restore( new WP_REST_Request( $restore_data ) );
single_image_test_assert( is_array( $restore_result ) && 'completed' === ( $restore_result['status'] ?? '' ), 'A fully verified Toolkit restore completes.' );
single_image_test_assert( 'strong_local_confirmation' === (string) ( $restore_result['classification']['classification'] ?? '' ), 'Restore remains in the strong local confirmation lane.' );
single_image_test_assert( 'npcink-abilities-toolkit' === (string) ( $restore_result['write_owner'] ?? '' ) && true === ( $restore_result['confirmation_receipt']['preview_verified'] ?? false ), 'Restore response records Toolkit ownership and the visible confirmation receipt.' );
single_image_test_assert( 3 === count( $npcink_toolbox_test_calls ) && 'list-media-backups' === $npcink_toolbox_test_calls[0]['ability_id'] && true === $npcink_toolbox_test_calls[1]['input']['dry_run'] && false === $npcink_toolbox_test_calls[2]['input']['dry_run'], 'Restore strictly calls list, dry-run, then commit.' );
single_image_test_assert( '2026/08/current.webp' === $npcink_toolbox_test_calls[1]['input']['expected_current_relative_file'] && 'image/webp' === $npcink_toolbox_test_calls[1]['input']['expected_current_mime_type'], 'Restore passes the listed current-file expectations into Toolkit validation.' );
single_image_test_assert( empty( $npcink_toolbox_test_filters ), 'Restore authorization is removed after success.' );

echo "Single-image media optimization behavior checks passed.\n";
