<?php
/**
 * Verified Cloud image artifact delivery for editor preview and adoption.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Cloud_Image_Artifact_Transport {
	private const MAX_FILE_BYTES  = 10485760;
	private const MAX_IMAGE_PIXELS = 40000000;
	private const ARTIFACT_ID_PATTERN = '/^art_[0-9a-f]{32}$/';
	private const DELIVERY_ID_PATTERN = '/^mdl_[0-9a-f]{32}$/';
	private const ARTIFACT_KEYS = array( 'artifact_id', 'artifact_reference', 'status', 'media_kind', 'operation', 'content_type', 'format', 'width', 'height', 'filesize_bytes', 'checksum', 'expires_at' );

	/**
	 * Pulls, verifies, and acknowledges one short-lived Cloud image artifact.
	 *
	 * @param array<string,mixed> $artifact Cloud artifact contract.
	 * @return array<string,mixed>|WP_Error
	 */
	public function receive( array $artifact, string $trace_id = '' ) {
		$validated = $this->validate_artifact( $artifact );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$client = apply_filters( 'npcink_toolbox_cloud_image_artifact_client', null, $artifact );
		if ( ! is_object( $client ) && function_exists( 'npcink_cloud_addon_verified_runtime_client' ) ) {
			$client = npcink_cloud_addon_verified_runtime_client();
		}
		if ( is_wp_error( $client ) ) {
			return $client;
		}
		if ( ! is_object( $client ) || ! method_exists( $client, 'pull_media_artifact' ) || ! method_exists( $client, 'acknowledge_media_artifact_delivery' ) ) {
			return new WP_Error(
				'npcink_toolbox_cloud_image_artifact_transport_unavailable',
				__( 'Connect and verify Npcink Cloud Addon before receiving generated images.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$artifact_id = (string) $validated['artifact_id'];
		$download    = $client->pull_media_artifact( $artifact_id, sanitize_text_field( $trace_id ) );
		if ( is_wp_error( $download ) ) {
			return $download;
		}

		$contents        = is_string( $download['body'] ?? null ) ? $download['body'] : '';
		$content_type    = $this->normalize_mime( (string) ( $download['content_type'] ?? '' ) );
		$image_info      = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $contents ) : false;
		$decoded_mime    = is_array( $image_info ) ? $this->normalize_mime( (string) ( $image_info['mime'] ?? '' ) ) : '';
		$actual_checksum = 'sha256:' . hash( 'sha256', $contents );
		$delivery_id     = sanitize_text_field( (string) ( $download['delivery_id'] ?? '' ) );
		$ack_deadline    = $this->strict_timestamp( (string) ( $download['delivery_ack_deadline'] ?? '' ) );
		$expires_at      = $this->strict_timestamp( (string) $validated['expires_at'] );

		if (
			'' === $contents
			|| strlen( $contents ) > self::MAX_FILE_BYTES
			|| (string) $validated['content_type'] !== $content_type
			|| $content_type !== $decoded_mime
			|| (int) $validated['filesize_bytes'] !== strlen( $contents )
			|| strlen( $contents ) !== absint( $download['content_length'] ?? 0 )
			|| (string) $validated['checksum'] !== $actual_checksum
			|| $artifact_id !== (string) ( $download['artifact_id'] ?? '' )
			|| (string) $validated['checksum'] !== strtolower( (string) ( $download['artifact_checksum'] ?? '' ) )
			|| ! is_array( $image_info )
			|| (int) $validated['width'] !== absint( $image_info[0] ?? 0 )
			|| (int) $validated['height'] !== absint( $image_info[1] ?? 0 )
			|| 1 !== preg_match( self::DELIVERY_ID_PATTERN, $delivery_id )
			|| false === $ack_deadline
			|| $ack_deadline <= time()
			|| false === $expires_at
			|| $ack_deadline > $expires_at
		) {
			return new WP_Error(
				'npcink_toolbox_cloud_image_artifact_verification_failed',
				__( 'The generated image artifact failed local integrity verification.', 'npcink-workflow-toolbox' ),
				array( 'status' => 422 )
			);
		}

		$ack_payload = array(
			'contract_version'   => 'media_artifact_delivery_ack.v1',
			'delivery_id'        => $delivery_id,
			'received_byte_size' => strlen( $contents ),
			'received_checksum'  => $actual_checksum,
		);
		$ack = $client->acknowledge_media_artifact_delivery( $artifact_id, $ack_payload, sanitize_text_field( $trace_id ) );
		if ( is_wp_error( $ack ) ) {
			return $ack;
		}
		$acknowledged_at = $this->strict_timestamp( (string) ( $ack['acknowledged_at'] ?? '' ) );
		$ack_expires_at  = $this->strict_timestamp( (string) ( $ack['artifact_expires_at'] ?? '' ) );
		if (
			$artifact_id !== (string) ( $ack['artifact_id'] ?? '' )
			|| $delivery_id !== (string) ( $ack['delivery_id'] ?? '' )
			|| strlen( $contents ) !== ( $ack['received_byte_size'] ?? null )
			|| $actual_checksum !== (string) ( $ack['received_checksum'] ?? '' )
			|| true !== ( $ack['byte_size_verified'] ?? null )
			|| true !== ( $ack['checksum_verified'] ?? null )
			|| false === $acknowledged_at
			|| $acknowledged_at > $ack_deadline
			|| false === $ack_expires_at
			|| $ack_expires_at !== $expires_at
			|| (string) ( $ack['artifact_expires_at'] ?? '' ) !== (string) $validated['expires_at']
		) {
			return new WP_Error(
				'npcink_toolbox_cloud_image_artifact_ack_invalid',
				__( 'Cloud did not confirm the verified generated image transfer.', 'npcink-workflow-toolbox' ),
				array( 'status' => 502 )
			);
		}

		return array(
			'artifact_id'  => $artifact_id,
			'body'         => $contents,
			'content_type' => $content_type,
			'format'       => (string) $validated['format'],
			'width'        => (int) $validated['width'],
			'height'       => (int) $validated['height'],
			'byte_size'    => strlen( $contents ),
			'checksum'     => $actual_checksum,
			'expires_at'   => (string) $validated['expires_at'],
		);
	}

	/**
	 * @param array<string,mixed> $artifact Artifact.
	 * @return array<string,mixed>|WP_Error
	 */
	public function validate_artifact( array $artifact ) {
		$contract = $artifact;
		if ( array_key_exists( 'purged_at', $contract ) ) {
			if ( null !== $contract['purged_at'] ) {
				return new WP_Error( 'npcink_toolbox_cloud_image_artifact_contract_invalid', __( 'The generated image artifact contract is invalid or expired.', 'npcink-workflow-toolbox' ), array( 'status' => 422 ) );
			}
			unset( $contract['purged_at'] );
		}
		$artifact_id = sanitize_text_field( (string) ( $artifact['artifact_id'] ?? '' ) );
		$reference   = is_array( $artifact['artifact_reference'] ?? null ) ? $artifact['artifact_reference'] : array();
		$content_type = $this->normalize_mime( (string) ( $artifact['content_type'] ?? '' ) );
		$width        = $artifact['width'] ?? null;
		$height       = $artifact['height'] ?? null;
		$byte_size    = $artifact['filesize_bytes'] ?? null;
		$checksum     = strtolower( (string) ( $artifact['checksum'] ?? '' ) );
		$expires_at   = sanitize_text_field( (string) ( $artifact['expires_at'] ?? '' ) );
		$expires_ts   = $this->strict_timestamp( $expires_at );
		$format       = sanitize_key( (string) ( $artifact['format'] ?? '' ) );

		if (
			array() !== array_diff( self::ARTIFACT_KEYS, array_keys( $contract ) )
			|| array() !== array_diff( array_keys( $contract ), self::ARTIFACT_KEYS )
			|| 1 !== preg_match( self::ARTIFACT_ID_PATTERN, $artifact_id )
			|| array( 'artifact_id' ) !== array_keys( $reference )
			|| $artifact_id !== (string) ( $reference['artifact_id'] ?? '' )
			|| 'available' !== (string) ( $artifact['status'] ?? '' )
			|| 'image' !== (string) ( $artifact['media_kind'] ?? '' )
			|| 'image.generate.v1' !== (string) ( $artifact['operation'] ?? '' )
			|| '' === $content_type
			|| $this->format_for_mime( $content_type ) !== $format
			|| ! is_int( $width ) || $width < 1
			|| ! is_int( $height ) || $height < 1
			|| ( $width * $height ) > self::MAX_IMAGE_PIXELS
			|| ! is_int( $byte_size ) || $byte_size < 1 || $byte_size > self::MAX_FILE_BYTES
			|| 1 !== preg_match( '/^sha256:[0-9a-f]{64}$/', $checksum )
			|| false === $expires_ts || $expires_ts <= time()
		) {
			return new WP_Error(
				'npcink_toolbox_cloud_image_artifact_contract_invalid',
				__( 'The generated image artifact contract is invalid or expired.', 'npcink-workflow-toolbox' ),
				array( 'status' => 422 )
			);
		}

		return array(
			'artifact_id'     => $artifact_id,
			'content_type'    => $content_type,
			'format'          => $format,
			'width'           => $width,
			'height'          => $height,
			'filesize_bytes'  => $byte_size,
			'checksum'        => $checksum,
			'expires_at'      => $expires_at,
		);
	}

	private function normalize_mime( string $mime ): string {
		$mime = strtolower( trim( explode( ';', $mime, 2 )[0] ) );

		return in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ? $mime : '';
	}

	private function format_for_mime( string $mime ): string {
		return array( 'image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/webp' => 'webp' )[ $mime ] ?? '';
	}

	/**
	 * @return int|false
	 */
	private function strict_timestamp( string $value ) {
		$utc     = new \DateTimeZone( 'UTC' );
		$formats = array(
			'!Y-m-d\TH:i:s\Z'   => 'Y-m-d\TH:i:s\Z',
			'!Y-m-d\TH:i:s.u\Z' => 'Y-m-d\TH:i:s.u\Z',
		);
		foreach ( $formats as $parse_format => $roundtrip_format ) {
			$timestamp = \DateTimeImmutable::createFromFormat( $parse_format, $value, $utc );
			$errors    = \DateTimeImmutable::getLastErrors();
			if (
				false !== $timestamp
				&& ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) )
				&& $value === $timestamp->format( $roundtrip_format )
			) {
				return $timestamp->getTimestamp();
			}
		}

		return false;
	}
}
