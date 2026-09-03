<?php
/**
 * Focused behavior checks for media-recognition Cloud error propagation.
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

	$GLOBALS['npcink_toolbox_media_test_meta'] = array();

	function get_post_meta( int $post_id, string $key, bool $single = false ) {
		unset( $single );
		return $GLOBALS['npcink_toolbox_media_test_meta'][ $post_id ][ $key ] ?? array();
	}

	function npcink_cloud_addon_request_image_context_evidence( array $request, string $trace_id, string $idempotency_key ) {
		return new WP_Error( 'cloud_media_recognition_temporarily_unavailable' );
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
	}
}

namespace {
	require_once dirname( __DIR__ ) . '/includes/Provider_Client.php';

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
}
