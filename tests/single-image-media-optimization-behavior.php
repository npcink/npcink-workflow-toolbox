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

echo "Single-image media optimization behavior checks passed.\n";
