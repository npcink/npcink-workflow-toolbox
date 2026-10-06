<?php
/**
 * Media derivative preview and local-review REST bridge cluster, moved
 * verbatim from Rest_Controller behind one-line facade delegates.
 *
 * Boundary posture is unchanged: preview runs and optimization payloads
 * are Cloud Addon transports with request-scoped artifacts; the local
 * review route serves capability- and nonce-gated bytes only; nothing
 * here imports media, mutates attachments, or writes WordPress data.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Rest_Media_Derivative_Previews {

	private Provider_Client $client;

	public function __construct( Provider_Client $client ) {
		$this->client = $client;
	}

	public function media_derivative_handoff( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->build_media_derivative_handoff( is_array( $params ) ? $params : array() ) );
	}

	/**
	 * Starts one preview-only Cloud derivative run through the Cloud Addon seam.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */

	public function create_media_derivative_preview( WP_REST_Request $request ) {
		$preview_input = $this->media_derivative_preview_input( $request );
		if ( is_wp_error( $preview_input ) ) {
			return $preview_input;
		}

		if ( ! function_exists( 'npcink_cloud_addon_dispatch_media_derivative_cloud_request' ) ) {
			return $this->media_derivative_cloud_addon_unavailable();
		}

		$watermark_id = absint( $preview_input['watermark_attachment_id'] ?? 0 );
		$ability_input = $preview_input;
		unset( $ability_input['watermark_attachment_id'] );

		$ability_response = $this->run_toolkit_ability( 'npcink-abilities-toolkit/build-media-derivative-cloud-request', $ability_input );
		if ( is_wp_error( $ability_response ) ) {
			return $ability_response;
		}

		$source_artifact = $this->media_derivative_attachment_descriptor( absint( $ability_input['attachment_id'] ?? 0 ) );
		if ( is_wp_error( $source_artifact ) ) {
			return $source_artifact;
		}

		$watermark_artifact = array();
		if ( $watermark_id > 0 ) {
			$watermark_artifact = $this->media_derivative_attachment_descriptor( $watermark_id );
			if ( is_wp_error( $watermark_artifact ) ) {
				return $watermark_artifact;
			}
		}

		$trace_id = sanitize_text_field( (string) ( $request->get_param( 'trace_id' ) ?: wp_generate_uuid4() ) );
		$dispatch = npcink_cloud_addon_dispatch_media_derivative_cloud_request(
			$ability_response,
			$source_artifact,
			$trace_id,
			sanitize_text_field( (string) $request->get_param( 'idempotency_key' ) ),
			$watermark_artifact
		);
		if ( is_wp_error( $dispatch ) ) {
			return $dispatch;
		}

		$cloud_run = is_array( $dispatch ) ? $dispatch : array();
		$run_id    = sanitize_text_field( (string) ( $cloud_run['run_id'] ?? '' ) );

		return new WP_REST_Response(
			array(
				'contract_version' => 'toolbox_media_derivative_preview.v2',
				'status'           => 'submitted',
				'run_id'           => sanitize_text_field( (string) $run_id ),
				'cloud_run'        => $cloud_run,
				'ability_response' => $ability_response,
				'write_posture'    => 'preview_only',
				'direct_wordpress_write' => false,
				'core_proposal_created'   => false,
			),
			202
		);
	}

	public function get_media_derivative_preview( WP_REST_Request $request ) {
		if ( ! function_exists( 'npcink_cloud_addon_get_media_derivative_run' ) ) {
			return $this->media_derivative_cloud_addon_unavailable();
		}

		$result = npcink_cloud_addon_get_media_derivative_run(
			sanitize_text_field( (string) $request->get_param( 'run_id' ) ),
			sanitize_text_field( (string) $request->get_param( 'trace_id' ) )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'contract_version' => 'toolbox_media_derivative_preview_status.v2',
				'cloud_run'        => is_array( $result ) ? $result : array(),
				'local_review'     => $this->media_derivative_local_review_projection( is_array( $result ) ? $result : array() ),
				'direct_wordpress_write' => false,
			)
		);
	}

	public function get_media_derivative_preview_result( WP_REST_Request $request ) {
		if ( ! function_exists( 'npcink_cloud_addon_get_media_derivative_run_result' ) ) {
			return $this->media_derivative_cloud_addon_unavailable();
		}

		$result = npcink_cloud_addon_get_media_derivative_run_result(
			sanitize_text_field( (string) $request->get_param( 'run_id' ) ),
			sanitize_text_field( (string) $request->get_param( 'trace_id' ) )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$cloud_result = is_array( $result ) ? $result : array();
		$local_review = $this->media_derivative_local_review_projection( $cloud_result );
		$optimization = is_array( $cloud_result['optimization'] ?? null ) ? $cloud_result['optimization'] : array();
		if ( 'skipped' === (string) ( $optimization['status'] ?? '' ) ) {
			return rest_ensure_response(
				array(
					'contract_version' => 'toolbox_media_derivative_preview_result.v3',
					'cloud_result' => $cloud_result,
					'local_review' => array(),
					'optimization' => $optimization,
					'direct_wordpress_write' => false,
				)
			);
		}
		if ( empty( $local_review ) ) {
			return new WP_Error(
				'npcink_toolbox_media_derivative_local_review_unavailable',
				__( 'Cloud media derivative result did not include a valid local review artifact.', 'npcink-workflow-toolbox' ),
				array( 'status' => 502 )
			);
		}

		return rest_ensure_response(
			array(
				'contract_version' => 'toolbox_media_derivative_preview_result.v2',
				'cloud_result'     => $cloud_result,
				'local_review'     => $local_review,
				'direct_wordpress_write' => false,
			)
		);
	}

	public function build_media_derivative_optimization_payload( WP_REST_Request $request ) {
		if ( ! function_exists( 'npcink_cloud_addon_build_media_derivative_optimization_payload' ) ) {
			return $this->media_derivative_cloud_addon_unavailable();
		}

		$payload = npcink_cloud_addon_build_media_derivative_optimization_payload(
			$this->media_derivative_object_param( $request, 'ability_response' ),
			$this->media_derivative_object_param( $request, 'cloud_result' ),
			$this->media_derivative_object_param( $request, 'derivative_artifact' ),
			$this->media_derivative_object_param( $request, 'media_details_input' )
		);

		return is_wp_error( $payload ) ? $payload : rest_ensure_response( $payload );
	}

	public function serve_media_derivative_local_review( WP_REST_Request $request ) {
		if ( ! function_exists( 'npcink_cloud_addon_receive_media_derivative_artifact' ) ) {
			return $this->media_derivative_cloud_addon_unavailable();
		}
		$allowed_params = array( 'artifact_id', 'artifact' );
		$unknown_params = array_diff( array_keys( $request->get_params() ), $allowed_params );
		$query_params   = method_exists( $request, 'get_query_params' ) ? $request->get_query_params() : array();
		$json_params    = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		if (
			! empty( $unknown_params )
			|| ! empty( $query_params )
			|| ! is_array( $json_params )
			|| array( 'artifact' ) !== array_keys( $json_params )
		) {
			return new WP_Error(
				'npcink_toolbox_media_derivative_local_review_args_invalid',
				__( 'Media derivative local review requires one exact JSON artifact body and no query parameters.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400, 'unsupported_fields' => array_values( $unknown_params ) )
			);
		}

		$artifact = $this->media_derivative_local_review_artifact_from_request( $request );
		if ( is_wp_error( $artifact ) ) {
			return $artifact;
		}
		$artifact_id = (string) $artifact['artifact_id'];
		$expected_local_artifact_keys = array(
			'artifact_id',
			'expires_at',
			'mime_type',
			'format',
			'width',
			'height',
			'filesize_bytes',
			'sha256',
			'suggested_filename',
			'filename_basis',
			'processing_warnings',
			'transform_facts',
		);
		if ( $expected_local_artifact_keys !== array_keys( $artifact ) ) {
			return new WP_Error(
				'npcink_toolbox_media_derivative_local_review_contract_invalid',
				__( 'Media derivative local review could not build the exact Addon receive contract.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}
		$received = npcink_cloud_addon_receive_media_derivative_artifact( $artifact );
		if ( is_wp_error( $received ) ) {
			return $received;
		}

		$contents  = is_string( $received['contents'] ?? null ) ? $received['contents'] : '';
		$mime_type = sanitize_text_field( (string) ( $received['mime_type'] ?? '' ) );
		if ( '' === $contents || ! in_array( $mime_type, array( 'image/avif', 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return new WP_Error(
				'npcink_toolbox_media_derivative_local_review_invalid',
				__( 'Cloud Addon did not return verified media derivative bytes.', 'npcink-workflow-toolbox' ),
				array( 'status' => 502 )
			);
		}
		if ( function_exists( 'status_header' ) ) {
			status_header( 200 );
		}
		nocache_headers();
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Length: ' . strlen( $contents ) );
		header( 'Content-Disposition: inline; filename="' . (string) $artifact['suggested_filename'] . '"' );
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'Pragma: no-cache' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Npcink-Artifact-Id: ' . $artifact_id );
		echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Returns the exact preview input contract or rejects legacy/unknown fields.
	 *
	 * watermark_attachment_id is a WordPress-local upload selector. It is removed
	 * before the canonical Toolkit ability input is dispatched.
	 *
	 * @return array<string, mixed>|WP_Error
	 */

	private function media_derivative_preview_input( WP_REST_Request $request ) {
		$input = $request->get_param( 'input' );
		$input = is_array( $input ) ? $input : array();

		$legacy_fields = array( 'target_format', 'max_width', 'watermark_enabled' );
		foreach ( $legacy_fields as $legacy_field ) {
			if ( array_key_exists( $legacy_field, $input ) ) {
				return new WP_Error(
					'npcink_toolbox_media_derivative_preview_legacy_field',
					__( 'The media derivative preview input contains a removed legacy field.', 'npcink-workflow-toolbox' ),
					array( 'status' => 400, 'field' => $legacy_field )
				);
			}
		}

		$allowed_fields = array(
			'attachment_id',
			'optimization_mode',
			'optimization_profile',
			'resize_mode',
			'expected_source_media_fingerprint',
			'target_max_width',
			'large_file_threshold_bytes',
			'preferred_format',
			'quality',
			'crop',
			'watermark',
			'watermark_attachment_id',
		);
		foreach ( array_keys( $input ) as $field ) {
			if ( ! is_string( $field ) || ! in_array( $field, $allowed_fields, true ) ) {
				return new WP_Error(
					'npcink_toolbox_media_derivative_preview_unknown_field',
					__( 'The media derivative preview input contains an unknown field.', 'npcink-workflow-toolbox' ),
					array( 'status' => 400, 'field' => sanitize_key( (string) $field ) )
				);
			}
		}

		$nested_fields = array(
			'crop'      => array( 'type', 'aspect_ratio', 'position' ),
			'watermark' => array( 'type', 'artifact_id', 'text', 'position', 'opacity', 'scale_percent', 'font_size', 'color', 'background', 'margin_px' ),
		);
		foreach ( $nested_fields as $parent => $allowed_nested_fields ) {
			if ( ! array_key_exists( $parent, $input ) ) {
				continue;
			}
			if ( ! is_array( $input[ $parent ] ) ) {
				return new WP_Error(
					'npcink_toolbox_media_derivative_preview_invalid_field',
					__( 'The media derivative preview input contains an invalid field value.', 'npcink-workflow-toolbox' ),
					array( 'status' => 400, 'field' => $parent )
				);
			}
			foreach ( array_keys( $input[ $parent ] ) as $nested_field ) {
				if ( ! is_string( $nested_field ) || ! in_array( $nested_field, $allowed_nested_fields, true ) ) {
					return new WP_Error(
						'npcink_toolbox_media_derivative_preview_unknown_field',
						__( 'The media derivative preview input contains an unknown field.', 'npcink-workflow-toolbox' ),
						array( 'status' => 400, 'field' => $parent . '.' . sanitize_key( (string) $nested_field ) )
					);
				}
			}
		}

		$watermark               = is_array( $input['watermark'] ?? null ) ? $input['watermark'] : array();
		$watermark_type          = ! empty( $watermark ) ? sanitize_key( (string) ( $watermark['type'] ?? 'image' ) ) : '';
		$watermark_attachment_id = absint( $input['watermark_attachment_id'] ?? 0 );
		if ( 'image' === $watermark_type && $watermark_attachment_id <= 0 ) {
			return new WP_Error(
				'npcink_toolbox_media_derivative_preview_watermark_attachment_required',
				__( 'Image watermark previews require a configured local watermark attachment.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400, 'field' => 'watermark_attachment_id' )
			);
		}
		if ( $watermark_attachment_id > 0 && 'image' !== $watermark_type ) {
			return new WP_Error(
				'npcink_toolbox_media_derivative_preview_watermark_attachment_unexpected',
				__( 'A local watermark attachment is allowed only for an image watermark preview.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400, 'field' => 'watermark_attachment_id' )
			);
		}

		$input = map_deep( $input, 'sanitize_text_field' );
		$input['attachment_id'] = absint( $input['attachment_id'] ?? 0 );
		if ( isset( $input['watermark_attachment_id'] ) ) {
			$input['watermark_attachment_id'] = absint( $input['watermark_attachment_id'] );
		}
		if ( isset( $input['target_max_width'] ) ) {
			$input['target_max_width'] = absint( $input['target_max_width'] );
		}
		if ( isset( $input['large_file_threshold_bytes'] ) ) {
			$input['large_file_threshold_bytes'] = absint( $input['large_file_threshold_bytes'] );
		}
		if ( isset( $input['preferred_format'] ) ) {
			$input['preferred_format'] = sanitize_key( (string) $input['preferred_format'] );
		}
		foreach ( array( 'optimization_mode', 'resize_mode' ) as $key_field ) {
			if ( isset( $input[ $key_field ] ) ) {
				$input[ $key_field ] = sanitize_key( (string) $input[ $key_field ] );
			}
		}
		if ( isset( $input['optimization_profile'] ) ) {
			$input['optimization_profile'] = sanitize_text_field( (string) $input['optimization_profile'] );
		}
		if ( isset( $input['quality'] ) ) {
			$input['quality'] = max( 1, min( 100, absint( $input['quality'] ) ) );
		}

		return $input;
	}

	private function run_toolkit_ability( string $ability_id, array $input ) {
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return new WP_Error(
				'npcink_toolbox_media_derivative_toolkit_unavailable',
				__( 'Npcink Abilities Toolkit is required to build the media derivative request.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();
		$callback   = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error(
				'npcink_toolbox_media_derivative_toolkit_unavailable',
				__( 'The Toolkit media derivative request ability is not callable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$result = call_user_func( $callback, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return is_array( $result ) ? $result : new WP_Error(
			'npcink_toolbox_media_derivative_toolkit_invalid_response',
			__( 'The Toolkit media derivative request ability returned an invalid response.', 'npcink-workflow-toolbox' ),
			array( 'status' => 502 )
		);
	}

	private function media_derivative_attachment_descriptor( int $attachment_id ) {
		$path = $attachment_id > 0 ? get_attached_file( $attachment_id ) : '';
		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
			return new WP_Error(
				'npcink_toolbox_media_derivative_file_unreadable',
				__( 'The selected attachment file is not readable for the preview upload.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400, 'attachment_id' => $attachment_id )
			);
		}

		return array(
			'path'      => $path,
			'filename'  => sanitize_file_name( basename( $path ) ),
			'mime_type' => sanitize_text_field( (string) get_post_mime_type( $attachment_id ) ),
		);
	}

	private function media_derivative_cloud_addon_unavailable(): WP_Error {
		return new WP_Error(
			'npcink_toolbox_media_derivative_cloud_addon_unavailable',
			__( 'Npcink Cloud Addon is required for media derivative preview transport.', 'npcink-workflow-toolbox' ),
			array( 'status' => 503, 'required_plugin' => 'npcink-cloud-addon' )
		);
	}

	private function media_derivative_object_param( WP_REST_Request $request, string $key ): array {
		$value = $request->get_param( $key );
		return is_array( $value ) ? $value : array();
	}

	private function media_derivative_local_review_projection( array $cloud_projection ): array {
		$artifact = is_array( $cloud_projection['artifact'] ?? null ) ? $cloud_projection['artifact'] : array();
		$expected_keys = array(
			'artifact_id',
			'artifact_reference',
			'expires_at',
			'suggested_filename',
			'filename_basis',
			'mime_type',
			'format',
			'width',
			'height',
			'filesize_bytes',
			'checksum',
			'processing_warnings',
			'transform_facts',
		);
		if ( count( $artifact ) !== count( $expected_keys ) || array() !== array_diff( $expected_keys, array_keys( $artifact ) ) || array() !== array_diff( array_keys( $artifact ), $expected_keys ) ) {
			return array();
		}

		$artifact_id = (string) $artifact['artifact_id'];
		$expires_at  = (string) $artifact['expires_at'];
		$expires_ts  = self::media_derivative_strict_timestamp( $expires_at );
		$format      = (string) $artifact['format'];
		$mime_type   = (string) $artifact['mime_type'];
		$mime_by_format = array(
			'avif' => 'image/avif',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'webp' => 'image/webp',
		);
		$artifact_reference = $artifact['artifact_reference'];
		$filename_basis     = $artifact['filename_basis'];
		if (
			! is_string( $artifact['artifact_id'] )
			|| 1 !== preg_match( '/^art_[0-9a-f]{32}$/', $artifact_id )
			|| ! is_string( $artifact['expires_at'] )
			|| ! is_string( $artifact['mime_type'] )
			|| ! is_string( $artifact['format'] )
			|| ! is_string( $artifact['checksum'] )
			|| ! is_string( $artifact['suggested_filename'] )
			|| ! is_array( $artifact_reference )
			|| array( 'artifact_id' ) !== array_keys( $artifact_reference )
			|| $artifact_id !== (string) ( $artifact_reference['artifact_id'] ?? '' )
			|| ! is_array( $filename_basis )
			|| 3 !== count( $filename_basis )
			|| array() !== array_diff( array( 'owner', 'strategy', 'final_sanitize_unique_required' ), array_keys( $filename_basis ) )
			|| array() !== array_diff( array_keys( $filename_basis ), array( 'owner', 'strategy', 'final_sanitize_unique_required' ) )
			|| 'wordpress_write_ability_final' !== ( $filename_basis['owner'] ?? null )
			|| 'format_checksum' !== ( $filename_basis['strategy'] ?? null )
			|| true !== ( $filename_basis['final_sanitize_unique_required'] ?? null )
			|| false === $expires_ts
			|| $expires_ts <= time()
			|| ! isset( $mime_by_format[ $format ] )
			|| $mime_by_format[ $format ] !== $mime_type
			|| ! is_int( $artifact['width'] )
			|| (int) $artifact['width'] <= 0
			|| (int) $artifact['width'] > 8192
			|| ! is_int( $artifact['height'] )
			|| (int) $artifact['height'] <= 0
			|| (int) $artifact['height'] > 8192
			|| (int) $artifact['width'] * (int) $artifact['height'] > 16777216
			|| ! is_int( $artifact['filesize_bytes'] )
			|| (int) $artifact['filesize_bytes'] <= 0
			|| (int) $artifact['filesize_bytes'] > 26214400
			|| 1 !== preg_match( '/^sha256:[0-9a-f]{64}$/', (string) $artifact['checksum'] )
			|| ! is_array( $artifact['processing_warnings'] )
			|| ! is_array( $artifact['transform_facts'] )
			|| empty( $artifact['transform_facts'] )
			|| ( ! empty( $artifact['processing_warnings'] ) && array_keys( $artifact['processing_warnings'] ) !== range( 0, count( $artifact['processing_warnings'] ) - 1 ) )
			|| count( $artifact['processing_warnings'] ) > 20
			|| '' === (string) $artifact['suggested_filename']
			|| strlen( (string) $artifact['suggested_filename'] ) > 120
			|| sanitize_file_name( (string) $artifact['suggested_filename'] ) !== (string) $artifact['suggested_filename']
		) {
			return array();
		}
		foreach ( $artifact['processing_warnings'] as $warning ) {
			if ( ! is_string( $warning ) || strlen( $warning ) > 200 || sanitize_text_field( $warning ) !== $warning ) {
				return array();
			}
		}

		$local_artifact = array(
			'artifact_id'         => $artifact_id,
			'expires_at'          => $expires_at,
			'mime_type'           => $mime_type,
			'format'              => $format,
			'width'               => (int) $artifact['width'],
			'height'              => (int) $artifact['height'],
			'filesize_bytes'      => (int) $artifact['filesize_bytes'],
			'sha256'              => substr( (string) $artifact['checksum'], 7 ),
			'suggested_filename'  => (string) $artifact['suggested_filename'],
			'filename_basis'      => $filename_basis,
			'processing_warnings' => array_values( $artifact['processing_warnings'] ),
			'transform_facts'     => $artifact['transform_facts'],
		);

		return array(
			'endpoint' => rest_url( Plugin::REST_NAMESPACE . '/media-derivative-local-review/' . rawurlencode( $artifact_id ) ),
			'method'   => 'POST',
			'artifact' => $local_artifact,
		);
	}

	/**
	 * Parses the exact UTC RFC3339 forms emitted by Cloud without calendar normalization.
	 *
	 * @param string $value Timestamp.
	 * @return int|false
	 */
	private static function media_derivative_strict_timestamp( string $value ) {
		$utc = new \DateTimeZone( 'UTC' );
		$formats = array(
			'!Y-m-d\TH:i:s\Z'   => 'Y-m-d\TH:i:s\Z',
			'!Y-m-d\TH:i:sP'    => 'Y-m-d\TH:i:sP',
			'!Y-m-d\TH:i:s.u\Z' => 'Y-m-d\TH:i:s.u\Z',
			'!Y-m-d\TH:i:s.uP'  => 'Y-m-d\TH:i:s.uP',
		);
		foreach ( $formats as $parse_format => $roundtrip_format ) {
			$timestamp = \DateTimeImmutable::createFromFormat( $parse_format, $value, $utc );
			$errors    = \DateTimeImmutable::getLastErrors();
			if (
				false !== $timestamp
				&& ( ! is_array( $errors ) || ( 0 === (int) $errors['warning_count'] && 0 === (int) $errors['error_count'] ) )
				&& 0 === $timestamp->getOffset()
				&& $value === $timestamp->format( $roundtrip_format )
			) {
				return $timestamp->getTimestamp();
			}
		}

		return false;
	}

	/**
	 * Builds the exact local12 artifact only after fail-closed cross-field validation.
	 *
	 * WordPress REST args validate individual body fields, but cannot validate
	 * MIME/format, width/height area, or the complete descriptor together.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>|WP_Error
	 */
	private function media_derivative_local_review_artifact_from_request( WP_REST_Request $request ) {
		$path_artifact_id   = $request->get_param( 'artifact_id' );
		$json_params        = method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		$artifact           = is_array( $json_params['artifact'] ?? null ) ? $json_params['artifact'] : array();
		$expected_keys      = array(
			'artifact_id',
			'expires_at',
			'mime_type',
			'format',
			'width',
			'height',
			'filesize_bytes',
			'sha256',
			'suggested_filename',
			'filename_basis',
			'processing_warnings',
			'transform_facts',
		);
		if (
			count( $artifact ) !== count( $expected_keys )
			|| array() !== array_diff( $expected_keys, array_keys( $artifact ) )
			|| array() !== array_diff( array_keys( $artifact ), $expected_keys )
		) {
			return $this->media_derivative_local_review_descriptor_invalid();
		}

		$artifact_id        = $artifact['artifact_id'];
		$expires_at         = $artifact['expires_at'];
		$mime_type          = $artifact['mime_type'];
		$format             = $artifact['format'];
		$width              = self::media_derivative_local_review_positive_integer( $artifact['width'] );
		$height             = self::media_derivative_local_review_positive_integer( $artifact['height'] );
		$filesize_bytes     = self::media_derivative_local_review_positive_integer( $artifact['filesize_bytes'] );
		$sha256             = $artifact['sha256'];
		$suggested_filename = $artifact['suggested_filename'];
		$filename_basis     = $artifact['filename_basis'];
		$processing_warnings = $artifact['processing_warnings'];
		$transform_facts     = $artifact['transform_facts'];
		$expires_timestamp  = is_string( $expires_at ) ? self::media_derivative_strict_timestamp( $expires_at ) : false;
		$mime_by_format     = array(
			'avif' => 'image/avif',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'webp' => 'image/webp',
		);
		if (
			! is_string( $path_artifact_id )
			|| ! is_string( $artifact_id )
			|| 1 !== preg_match( '/^art_[0-9a-f]{32}$/', $artifact_id )
			|| $path_artifact_id !== $artifact_id
			|| false === $expires_timestamp
			|| $expires_timestamp <= time()
			|| ! is_string( $mime_type )
			|| ! is_string( $format )
			|| ! isset( $mime_by_format[ $format ] )
			|| $mime_by_format[ $format ] !== $mime_type
			|| false === $width
			|| $width > 8192
			|| false === $height
			|| $height > 8192
			|| $width * $height > 16777216
			|| false === $filesize_bytes
			|| $filesize_bytes > 26214400
			|| ! is_string( $sha256 )
			|| 1 !== preg_match( '/^[0-9a-f]{64}$/', $sha256 )
			|| ! is_string( $suggested_filename )
			|| '' === $suggested_filename
			|| strlen( $suggested_filename ) > 120
			|| sanitize_file_name( $suggested_filename ) !== $suggested_filename
			|| ! is_array( $filename_basis )
			|| 3 !== count( $filename_basis )
			|| array() !== array_diff( array( 'owner', 'strategy', 'final_sanitize_unique_required' ), array_keys( $filename_basis ) )
			|| array() !== array_diff( array_keys( $filename_basis ), array( 'owner', 'strategy', 'final_sanitize_unique_required' ) )
			|| 'wordpress_write_ability_final' !== ( $filename_basis['owner'] ?? null )
			|| 'format_checksum' !== ( $filename_basis['strategy'] ?? null )
			|| true !== ( $filename_basis['final_sanitize_unique_required'] ?? null )
			|| ! is_array( $transform_facts )
			|| empty( $transform_facts )
		) {
			return $this->media_derivative_local_review_descriptor_invalid();
		}

		if (
			! is_array( $processing_warnings )
			|| ( ! empty( $processing_warnings ) && array_keys( $processing_warnings ) !== range( 0, count( $processing_warnings ) - 1 ) )
			|| count( $processing_warnings ) > 20
		) {
			return $this->media_derivative_local_review_descriptor_invalid();
		}
		foreach ( $processing_warnings as $warning ) {
			if ( ! is_string( $warning ) || strlen( $warning ) > 200 || sanitize_text_field( $warning ) !== $warning ) {
				return $this->media_derivative_local_review_descriptor_invalid();
			}
		}

		return array(
			'artifact_id'         => $artifact_id,
			'expires_at'          => $expires_at,
			'mime_type'           => $mime_type,
			'format'              => $format,
			'width'               => $width,
			'height'              => $height,
			'filesize_bytes'      => $filesize_bytes,
			'sha256'              => $sha256,
			'suggested_filename'  => $suggested_filename,
			'filename_basis'      => array(
				'owner'                          => 'wordpress_write_ability_final',
				'strategy'                       => 'format_checksum',
				'final_sanitize_unique_required' => true,
			),
			'processing_warnings' => $processing_warnings,
			'transform_facts'     => $transform_facts,
		);
	}

	/**
	 * Accepts one exact positive JSON integer without coercion.
	 *
	 * @param mixed $value Raw value.
	 * @return int|false
	 */
	private static function media_derivative_local_review_positive_integer( $value ) {
		return is_int( $value ) && $value > 0 ? $value : false;
	}

	private function media_derivative_local_review_descriptor_invalid(): WP_Error {
		return new WP_Error(
			'npcink_toolbox_media_derivative_local_review_descriptor_invalid',
			__( 'Media derivative local review descriptor facts are invalid.', 'npcink-workflow-toolbox' ),
			array( 'status' => 400 )
		);
	}

}
