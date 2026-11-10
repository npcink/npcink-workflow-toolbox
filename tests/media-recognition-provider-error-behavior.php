<?php
/**
 * Focused behavior checks for media-recognition Cloud errors and visual projection reuse.
 *
 * @package Npcink_Toolbox
 */

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/wp-stub/' );
	}

	if ( ! class_exists( 'WP_Error' ) ) {
		class WP_Error {
			private string $code;

			public function __construct( string $code ) {
				$this->code = $code;
			}

			public function get_error_code(): string {
				return $this->code;
			}
		}
	}

	function is_wp_error( $value ): bool {
		return $value instanceof WP_Error;
	}

	function absint( $value ): int {
		return max( 0, (int) $value );
	}

	function sanitize_key( $value ): string {
		return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ) ?? '';
	}

	function sanitize_text_field( $value ): string {
		return trim( (string) $value );
	}

	function sanitize_textarea_field( $value ): string {
		return trim( (string) $value );
	}

	function __( string $value, string $domain = '' ): string {
		return $value;
	}

	$GLOBALS['npcink_toolbox_media_test_meta'] = array();

	function get_post_meta( int $post_id, string $key, bool $single = false ) {
		unset( $single );
		return $GLOBALS['npcink_toolbox_media_test_meta'][ $post_id ][ $key ] ?? array();
	}

	$GLOBALS['npcink_toolbox_media_provider_calls'] = 0;

	function npcink_cloud_addon_request_image_context_evidence( array $request, string $trace_id, string $idempotency_key ) {
		++$GLOBALS['npcink_toolbox_media_provider_calls'];
		$GLOBALS['npcink_toolbox_media_last_request'] = $request;
		return $GLOBALS['npcink_toolbox_media_vision_result'] ?? new WP_Error( 'cloud_media_recognition_temporarily_unavailable' );
	}

	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( string $tag, $value, ...$args ) {
			if ( 'npcink_toolbox_site_knowledge_cloud_request' === $tag ) {
				if ( 'npcink-cloud/site-knowledge-status' === ( $args[1] ?? '' ) ) {
					return array( 'status' => 'ok', 'data' => array( 'result' => array( 'status' => 'ready', 'media_evidence_items' => $GLOBALS['npcink_toolbox_media_cache'] ?? array() ) ) );
				}
				$GLOBALS['npcink_toolbox_media_projection'][] = $args[0]['input'];
				return array( 'status' => 'ok', 'data' => array( 'result' => array( 'status' => 'ready' ) ) );
			}
			return $value;
		}
	}

	define( 'MB_IN_BYTES', 1024 * 1024 );
	function get_attached_file( int $id ) { return $GLOBALS['npcink_toolbox_media_files'][ $id ] ?? ''; }
	function wp_upload_dir(): array { return array( 'basedir' => sys_get_temp_dir() ); }
	function trailingslashit( string $path ): string { return rtrim( $path, '/' ) . '/'; }
	function wp_get_image_mime( string $path ): string { return 'image/png'; }
	function esc_url_raw( string $url, array $protocols = array() ): string { return $url; }
	function npcink_cloud_addon_upload_toolbox_site_media_visual_source( array $source, string $trace, string $key ): array {
		++$GLOBALS['npcink_toolbox_media_upload_calls'];
		return array( 'artifact_id' => 'art_0123456789abcdef0123456789abcdef' );
	}

	function npcink_toolbox_media_recognition_error_assert( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "FAIL: {$message}\n" );
			exit( 1 );
		}
		echo "PASS: {$message}\n";
	}
}

namespace Npcink_Toolbox {
	final class Settings {
		public function raw_responses_enabled(): bool { return false; }
	}
}

namespace {
	require_once dirname( __DIR__ ) . '/tests/load-provider-client.php';

	$client  = new Npcink_Toolbox\Provider_Client( new Npcink_Toolbox\Settings() );
	$request = array(
		'contract_version'       => 'image_context_evidence_request.v1',
		'write_posture'          => 'suggestion_only',
		'direct_wordpress_write' => false,
		'idempotency_scope'      => 'site_media_semantic_index',
		'items'                  => array(
			array(
				'attachment_id'    => 42,
				'media_fingerprint' => str_repeat( 'a', 64 ),
			),
		),
	);

	$background_request                  = $request;
	$background_request['dispatch_mode'] = 'background_completion';
	$background_result                   = $client->request_image_context_evidence( $background_request );

	npcink_toolbox_media_recognition_error_assert(
		is_wp_error( $background_result )
			&& 'cloud_media_recognition_temporarily_unavailable' === $background_result->get_error_code(),
		'Background media recognition returns the Cloud WP_Error without a PHP return-type failure.'
	);

	$interactive_result = $client->request_image_context_evidence( $request );
	npcink_toolbox_media_recognition_error_assert(
		array() === $interactive_result,
		'Interactive image evidence keeps the existing non-blocking empty fallback.'
	);

	$provider_calls_before_cache_only = $GLOBALS['npcink_toolbox_media_provider_calls'];
	$cache_only_result = $client->resolve_media_image_context_evidence( $request, false, '', false );
	npcink_toolbox_media_recognition_error_assert(
		is_array( $cache_only_result )
			&& 0 === (int) ( $cache_only_result['submitted_count'] ?? -1 )
			&& array( 42 ) === ( $cache_only_result['recognition_required_attachment_ids'] ?? array() )
			&& $provider_calls_before_cache_only === $GLOBALS['npcink_toolbox_media_provider_calls'],
		'Cache-only visual evidence resolution reports the miss without calling Cloud recognition.'
	);

	$reflection = new \ReflectionClass( $client );
	$fingerprint_method = $reflection->getMethod( 'runtime_safe_media_fingerprint' );
	$fingerprint_method->setAccessible( true );
	npcink_toolbox_media_recognition_error_assert(
		'sha256:' . str_repeat( 'a', 64 ) === $fingerprint_method->invoke( $client, str_repeat( 'A', 64 ) ),
		'Current media fingerprints normalize a raw SHA-256 digest to the canonical lowercase format.'
	);
	npcink_toolbox_media_recognition_error_assert(
		'' === $fingerprint_method->invoke( $client, 'attachment-42' ),
		'Unknown media identity values fail closed instead of being hashed into a fake file fingerprint.'
	);

	$reuse_method = $reflection->getMethod( 'media_visual_evidence_reuse_policy' );
	$reuse_method->setAccessible( true );
	$current_fingerprint = 'sha256:' . str_repeat( 'b', 64 );
	$evidence_fingerprint = 'sha256:' . str_repeat( 'a', 64 );
	npcink_toolbox_media_recognition_error_assert(
		'reuse' === $reuse_method->invoke( $client, 42, $current_fingerprint, $current_fingerprint, array() ),
		'Visual evidence for the exact current file fingerprint is reused without replacement lineage.'
	);
	$GLOBALS['npcink_toolbox_media_test_meta'][42]['_npcink_ai_media_file_replacement_history'] = array(
		array(
			'new_media_fingerprint' => $current_fingerprint,
			'derived_from_media_fingerprint' => 'sha256:' . str_repeat( 'c', 64 ),
			'visual_reuse_policy'   => 'reuse',
			'transform_facts'       => array( 'encoding_mode' => 'lossless' ),
		),
	);
	npcink_toolbox_media_recognition_error_assert(
		'' === $reuse_method->invoke( $client, 42, $current_fingerprint, $evidence_fingerprint, array() ),
		'Visual evidence is rejected when replacement lineage does not derive from its fingerprint.'
	);
	$GLOBALS['npcink_toolbox_media_test_meta'][42]['_npcink_ai_media_file_replacement_history'][0]['derived_from_media_fingerprint'] = $evidence_fingerprint;
	$GLOBALS['npcink_toolbox_media_test_meta'][42]['_npcink_ai_media_file_replacement_history'][0]['visual_reuse_policy'] = 'reuse_with_human_check';
	npcink_toolbox_media_recognition_error_assert(
		'reuse_with_human_check' === $reuse_method->invoke( $client, 42, $current_fingerprint, $evidence_fingerprint, array() ),
		'Lineage-matched visual evidence remains available only with its human-check policy.'
	);
	$GLOBALS['npcink_toolbox_media_test_meta'][42]['_npcink_ai_media_file_replacement_history'][0]['visual_reuse_policy'] = 'requires_reidentification';
	npcink_toolbox_media_recognition_error_assert(
		'' === $reuse_method->invoke( $client, 42, $current_fingerprint, $evidence_fingerprint, array() ),
		'Visual evidence requiring reidentification is never reused after a replacement.'
	);
	// Exercise the real local upload -> vision -> Cloud projection seam.
	$first_file = tempnam( sys_get_temp_dir(), 'npcink-visual-' );
	$changed_file = tempnam( sys_get_temp_dir(), 'npcink-visual-' );
	register_shutdown_function( static function () use ( $first_file, $changed_file ): void {
		unlink( $first_file );
		unlink( $changed_file );
	} );
	file_put_contents( $first_file, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jf1sAAAAASUVORK5CYII=' ) );
	file_put_contents( $changed_file, file_get_contents( $first_file ) . 'changed-file-bytes' );
	$GLOBALS['npcink_toolbox_media_files'][42] = $first_file;
	$GLOBALS['npcink_toolbox_media_upload_calls'] = 0;
	$GLOBALS['npcink_toolbox_media_projection'] = array();
	$GLOBALS['npcink_toolbox_media_vision_result'] = array(
		'contract_version' => 'image_context_evidence.v1',
		'run_id' => 'run_visual_projection',
		'model_id' => 'vision-model',
		'write_posture' => 'suggestion_only',
		'direct_wordpress_write' => false,
		'items' => array( array( 'attachment_id' => 42, 'status' => 'ready', 'visual_summary' => 'A red sunset over the sea.', 'source' => 'hosted_vision_model' ) ),
	);
	$request['items'][0]['url'] = 'https://site.local/uploads/sunset.png';
	$first = $client->resolve_media_image_context_evidence( $request, true, 'test-upload' );
	$projection = $GLOBALS['npcink_toolbox_media_projection'][0]['media_items'][0] ?? array();
	$vision_request = $GLOBALS['npcink_toolbox_media_last_request']['items'][0] ?? array();
	npcink_toolbox_media_recognition_error_assert(
		1 === $first['recognized_count'] && true === $first['projection_queued']
		&& 'art_0123456789abcdef0123456789abcdef' === ( $projection['source_artifact_id'] ?? '' )
		&& $projection['source_artifact_id'] === ( $vision_request['source_artifact_id'] ?? '' )
		&& $request['items'][0]['url'] === ( $projection['url'] ?? '' ) && ! isset( $vision_request['url'] )
		&& 'sha256:' . hash_file( 'sha256', $first_file ) === ( $projection['media_fingerprint'] ?? '' )
		&& 'run_visual_projection' === ( $projection['vision_run_id'] ?? '' )
		&& 'vision-model' === ( $projection['vision_model_id'] ?? '' ),
		'Local visual projection retains the uploaded artifact, original URL, current fingerprint and Cloud provenance.'
	);
	$GLOBALS['npcink_toolbox_media_cache'] = array( array(
		'attachment_id' => 42,
		'media_fingerprint' => $projection['media_fingerprint'],
		'visual_evidence' => array( 'status' => 'ready', 'visual_summary' => $projection['visual_summary'] ),
	) );
	$vision_calls = $GLOBALS['npcink_toolbox_media_provider_calls'];
	$second = $client->resolve_media_image_context_evidence( $request, true, 'test-upload' );
	npcink_toolbox_media_recognition_error_assert(
		1 === $second['reused_count'] && 0 === $second['recognized_count']
		&& $vision_calls === $GLOBALS['npcink_toolbox_media_provider_calls']
		&& 1 === $GLOBALS['npcink_toolbox_media_upload_calls']
		&& 1 === count( $GLOBALS['npcink_toolbox_media_projection'] ),
		'Cloud ready evidence for the same file avoids another upload, recognition or projection.'
	);
	$GLOBALS['npcink_toolbox_media_files'][42] = $changed_file;
	$changed = $client->resolve_media_image_context_evidence( $request, true, 'test-upload' );
	npcink_toolbox_media_recognition_error_assert(
		0 === $changed['reused_count'] && 1 === $changed['recognized_count']
		&& true === $changed['projection_queued'] && 2 === count( $GLOBALS['npcink_toolbox_media_projection'] )
		&& $vision_calls + 1 === $GLOBALS['npcink_toolbox_media_provider_calls']
		&& 'sha256:' . hash_file( 'sha256', $changed_file ) === ( $GLOBALS['npcink_toolbox_media_projection'][1]['media_items'][0]['media_fingerprint'] ?? '' ),
		'Changed file bytes reject stale Cloud evidence and project the newly recognized fingerprint.'
	);
	unset( $GLOBALS['npcink_toolbox_media_vision_result'] );
	$projection_count = count( $GLOBALS['npcink_toolbox_media_projection'] );
	$failed = $client->resolve_media_image_context_evidence( $request, true, 'test-upload' );
	npcink_toolbox_media_recognition_error_assert(
		0 === $failed['recognized_count'] && false === $failed['projection_queued']
		&& $projection_count === count( $GLOBALS['npcink_toolbox_media_projection'] ),
		'Failed recognition does not create a visual projection or a reusable success.'
	);

}
