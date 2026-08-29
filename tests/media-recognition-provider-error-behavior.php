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
}
