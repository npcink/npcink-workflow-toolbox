<?php
/**
 * Foreground exact-manifest media optimization batches.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Media_Optimization_Batches {
	public const CONTRACT_VERSION = 'toolbox_media_optimization_batch.v1';
	private const OPTION_NAME = 'npcink_toolbox_media_optimization_batches';
	private const MANIFEST_ABILITY_ID = 'npcink-abilities-toolkit/build-media-derivative-batch-plan';
	private const ABILITY_ID = 'npcink-abilities-toolkit/adopt-cloud-media-derivative';
	private const RESTORE_ABILITY_ID = 'npcink-abilities-toolkit/restore-media-backup';
	private const MAX_BATCHES = 20;
	private const MAX_ITEMS = 1000;
	private const CHUNK_SIZE = 10;

	/** @return array<string,mixed>|WP_Error */
	public function build_manifest( array $input ) {
		return $this->run_registered_ability( self::MANIFEST_ABILITY_ID, $input );
	}

	/** @return array<string,mixed>|WP_Error */
	public function create( array $payload ) {
		$plan = is_array( $payload['plan'] ?? null ) ? $payload['plan'] : array();
		$candidates = is_array( $plan['candidates'] ?? null ) ? array_values( $plan['candidates'] ) : array();
		if ( 'toolbox_media_optimization_manifest.v1' !== (string) ( $plan['plan_contract_version'] ?? '' ) ) {
			return $this->error( 'npcink_toolbox_media_batch_manifest_invalid', 'The media optimization manifest contract is invalid.', 400 );
		}
		if ( empty( $candidates ) || count( $candidates ) > self::MAX_ITEMS ) {
			return $this->error( 'npcink_toolbox_media_batch_size_invalid', 'The media optimization manifest must contain between 1 and 1000 images.', 400 );
		}

		$items = array();
		foreach ( $candidates as $candidate ) {
			$candidate = is_array( $candidate ) ? $candidate : array();
			$attachment_id = absint( $candidate['attachment_id'] ?? 0 );
			$fingerprint = $this->normalize_fingerprint( (string) ( $candidate['media_fingerprint'] ?? '' ) );
			if ( $attachment_id <= 0 || isset( $items[ $attachment_id ] ) || ! $this->can_edit_image( $attachment_id ) || '' === $fingerprint ) {
				return $this->error( 'npcink_toolbox_media_batch_item_invalid', 'A media optimization manifest item is invalid or duplicated.', 409 );
			}
			if ( ! hash_equals( $fingerprint, $this->current_fingerprint( $attachment_id ) ) ) {
				return $this->error( 'npcink_toolbox_media_batch_source_drift', 'An image changed while the optimization list was being prepared.', 409 );
			}
			$items[ $attachment_id ] = array(
				'attachment_id'     => $attachment_id,
				'title'             => sanitize_text_field( (string) ( $candidate['title'] ?? '' ) ),
				'mime_type'         => sanitize_text_field( (string) ( $candidate['mime_type'] ?? '' ) ),
				'url'               => esc_url_raw( (string) ( $candidate['url'] ?? '' ) ),
				'width'             => absint( $candidate['width'] ?? 0 ),
				'height'            => absint( $candidate['height'] ?? 0 ),
				'filesize_bytes'    => absint( $candidate['filesize_bytes'] ?? 0 ),
				'source_fingerprint' => $fingerprint,
				'cloud_request_input' => $this->cloud_input( $attachment_id, $candidate, $plan ),
				'status'            => 'pending',
				'attempt_count'     => 0,
				'error_code'        => '',
				'bytes_after'       => 0,
				'replacement_id'    => '',
				'backup_id'         => '',
				'restore_status'    => 'available_after_success',
			);
		}

		$filters = is_array( $plan['filters'] ?? null ) ? $this->sanitize_array( $plan['filters'] ) : array();
		$manifest_seed = array(
			'filters' => $filters,
			'optimization_profile' => 'auto_safe.v1',
			'resize_mode' => 'fit' === (string) ( $filters['resize_mode'] ?? '' ) ? 'fit' : 'preserve',
			'items' => array_map( static fn( $item ) => array( $item['attachment_id'], $item['source_fingerprint'] ), array_values( $items ) ),
		);
		$digest = 'sha256:' . hash( 'sha256', (string) wp_json_encode( $manifest_seed ) );
		$batch_id = 'media_opt_' . str_replace( '-', '', (string) wp_generate_uuid4() );
		$now = gmdate( 'c' );
		$batch = array(
			'contract_version' => self::CONTRACT_VERSION,
			'batch_id' => $batch_id,
			'manifest_digest' => $digest,
			'created_at_gmt' => $now,
			'updated_at_gmt' => $now,
			'confirmed_at_gmt' => '',
			'confirmed_by' => 0,
			'status' => 'ready_for_review',
			'optimization_profile' => 'auto_safe.v1',
			'resize_mode' => $manifest_seed['resize_mode'],
			'chunk_size' => self::CHUNK_SIZE,
			'recoverable_until_gmt' => gmdate( 'c', time() + ( 30 * 86400 ) ),
			'filters' => $filters,
			'items' => array_values( $items ),
			'summary' => array(),
		);
		$batch = $this->with_summary( $batch );
		$this->save_batch( $batch );
		return $batch;
	}

	/** @return array<string,mixed>|WP_Error */
	public function confirm( string $batch_id, array $payload ) {
		$batch = $this->find( $batch_id );
		if ( is_wp_error( $batch ) ) {
			return $batch;
		}
		$digest = $this->normalize_fingerprint( (string) ( $payload['manifest_digest'] ?? '' ) );
		if ( true !== ( $payload['confirm'] ?? null ) || '' === $digest || ! hash_equals( (string) $batch['manifest_digest'], $digest ) ) {
			return $this->error( 'npcink_toolbox_media_batch_confirmation_invalid', 'Confirm the exact media optimization list before starting.', 409 );
		}
		if ( 'completed' !== (string) $batch['status'] ) {
			$batch['status'] = 'running';
			$batch['confirmed_at_gmt'] = gmdate( 'c' );
			$batch['confirmed_by'] = get_current_user_id();
			$this->save_batch( $batch );
		}
		return $this->with_summary( $batch );
	}

	/** @return array<string,mixed>|WP_Error */
	public function complete_item( string $batch_id, int $attachment_id, array $payload ) {
		$batch = $this->find( $batch_id );
		if ( is_wp_error( $batch ) ) {
			return $batch;
		}
		if ( ! in_array( (string) $batch['status'], array( 'running', 'paused' ), true ) || empty( $batch['confirmed_at_gmt'] ) ) {
			return $this->error( 'npcink_toolbox_media_batch_not_confirmed', 'Start the confirmed media optimization batch before processing images.', 409 );
		}
		$index = $this->item_index( $batch, $attachment_id );
		if ( $index < 0 ) {
			return $this->error( 'npcink_toolbox_media_batch_item_not_found', 'The image is not part of this exact optimization list.', 404 );
		}
		$item = $batch['items'][ $index ];
		if ( in_array( (string) $item['status'], array( 'completed', 'skipped' ), true ) ) {
			return $this->with_summary( $batch );
		}
		$status = sanitize_key( (string) ( $payload['status'] ?? '' ) );
		$item['attempt_count'] = absint( $item['attempt_count'] ?? 0 ) + 1;
		if ( 'skipped' === $status ) {
			$item['status'] = 'skipped';
			$item['error_code'] = sanitize_key( (string) ( $payload['reason'] ?? 'cloud_not_qualified' ) );
		} elseif ( 'failed' === $status ) {
			$item['status'] = 'failed';
			$item['error_code'] = sanitize_key( (string) ( $payload['reason'] ?? 'processing_failed' ) );
		} elseif ( 'qualified' === $status ) {
			$current = $this->current_fingerprint( $attachment_id );
			if ( '' === $current || ! hash_equals( (string) $item['source_fingerprint'], $current ) ) {
				$item['status'] = 'skipped';
				$item['error_code'] = 'source_fingerprint_changed';
			} else {
				$result = $this->adopt( $batch, $item, $payload );
				if ( is_wp_error( $result ) ) {
					$item['status'] = 'failed';
					$item['error_code'] = sanitize_key( $result->get_error_code() );
				} else {
					$item['status'] = 'completed';
					$item['error_code'] = '';
					$item['bytes_after'] = absint( $result['after']['filesize_bytes'] ?? 0 );
					$item['replacement_id'] = sanitize_text_field( (string) ( $result['replacement_id'] ?? '' ) );
					$item['backup_id'] = sanitize_text_field( (string) ( $result['backup']['backup_id'] ?? $result['replacement_id'] ?? '' ) );
					$item['restore_status'] = 'available';
				}
			}
		} else {
			return $this->error( 'npcink_toolbox_media_batch_item_status_invalid', 'The media optimization result status is invalid.', 400 );
		}
		$batch['items'][ $index ] = $item;
		$batch = $this->apply_stop_policy( $this->with_summary( $batch ), $index );
		$this->save_batch( $batch );
		return $batch;
	}

	/** @return array<string,mixed>|WP_Error */
	public function current() {
		$batches = $this->all();
		return empty( $batches ) ? $this->error( 'npcink_toolbox_media_batch_not_found', 'No media optimization history is available.', 404 ) : $this->with_summary( $batches[0] );
	}

	/** @return array<string,mixed>|WP_Error */
	public function restore_item( string $batch_id, int $attachment_id ) {
		$batch = $this->find( $batch_id );
		if ( is_wp_error( $batch ) ) return $batch;
		$index = $this->item_index( $batch, $attachment_id );
		if ( $index < 0 ) return $this->error( 'npcink_toolbox_media_batch_item_not_found', 'The image is not part of this optimization batch.', 404 );
		$item = $batch['items'][ $index ];
		if ( 'restored' === (string) ( $item['restore_status'] ?? '' ) ) return $batch;
		if ( 'completed' !== (string) ( $item['status'] ?? '' ) || empty( $item['replacement_id'] ) ) {
			return $this->error( 'npcink_toolbox_media_batch_restore_unavailable', 'This image does not have a completed optimization to restore.', 409 );
		}
		$input = array(
			'attachment_id' => $attachment_id,
			'backup_id' => (string) $item['backup_id'],
			'target_conflict_mode' => 'fail',
			'idempotency_key' => 'toolbox-batch-restore-' . sanitize_key( $batch_id ) . '-' . $attachment_id,
			'dry_run' => true,
			'commit' => false,
		);
		$preview = $this->run_registered_ability( self::RESTORE_ABILITY_ID, $input );
		if ( is_wp_error( $preview ) ) return $preview;
		$authorize = static fn( bool $allowed, string $ability_id ): bool => self::RESTORE_ABILITY_ID === $ability_id ? true : $allowed;
		add_filter( 'npcink_abilities_toolkit_write_commit_allowed', $authorize, 10, 2 );
		try {
			$input['dry_run'] = false;
			$input['commit'] = true;
			$result = $this->run_registered_ability( self::RESTORE_ABILITY_ID, $input );
		} finally {
			remove_filter( 'npcink_abilities_toolkit_write_commit_allowed', $authorize, 10 );
		}
		if ( is_wp_error( $result ) ) {
			$item['restore_status'] = 'failed';
			$item['restore_error_code'] = sanitize_key( $result->get_error_code() );
		} else {
			$item['restore_status'] = 'restored';
			$item['restored_at_gmt'] = gmdate( 'c' );
		}
		$batch['items'][ $index ] = $item;
		$this->save_batch( $batch );
		return is_wp_error( $result ) ? $result : $this->with_summary( $batch );
	}

	/** @return array<int,array<string,mixed>> */
	public function all(): array {
		$value = get_option( self::OPTION_NAME, array() );
		return is_array( $value ) ? array_values( array_filter( $value, 'is_array' ) ) : array();
	}

	/** @return array<string,mixed>|WP_Error */
	public function find( string $batch_id ) {
		foreach ( $this->all() as $batch ) {
			if ( hash_equals( (string) ( $batch['batch_id'] ?? '' ), sanitize_text_field( $batch_id ) ) ) {
				return $this->with_summary( $batch );
			}
		}
		return $this->error( 'npcink_toolbox_media_batch_not_found', 'The media optimization batch was not found.', 404 );
	}

	private function adopt( array $batch, array $item, array $payload ) {
		$artifact = is_array( $payload['derivative_artifact'] ?? null ) ? $payload['derivative_artifact'] : array();
		if ( empty( $artifact['artifact_id'] ) ) {
			return $this->error( 'npcink_toolbox_media_batch_artifact_missing', 'The qualified Cloud result did not include an artifact.', 400 );
		}
		$input = array(
			'attachment_id' => (int) $item['attachment_id'],
			'derivative_artifact' => $artifact,
			'expected_derivative_mime_type' => sanitize_text_field( (string) ( $artifact['mime_type'] ?? 'image/webp' ) ),
			'backup_suffix' => 'npcink-toolbox-batch-backup',
			'idempotency_key' => 'toolbox-batch-' . sanitize_key( (string) $batch['batch_id'] ) . '-' . (int) $item['attachment_id'],
			'batch_id' => (string) $batch['batch_id'],
			'optimization_profile' => 'auto_safe.v1',
			'batch_confirmation_digest' => (string) $batch['manifest_digest'],
			'dry_run' => true,
			'commit' => false,
		);
		$preview = $this->run_registered_ability( self::ABILITY_ID, $input );
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}
		$authorize = static fn( bool $allowed, string $ability_id ): bool => self::ABILITY_ID === $ability_id ? true : $allowed;
		add_filter( 'npcink_abilities_toolkit_write_commit_allowed', $authorize, 10, 2 );
		try {
			$input['dry_run'] = false;
			$input['commit'] = true;
			return $this->run_registered_ability( self::ABILITY_ID, $input );
		} finally {
			remove_filter( 'npcink_abilities_toolkit_write_commit_allowed', $authorize, 10 );
		}
	}

	private function run_registered_ability( string $ability_id, array $input ) {
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return $this->error( 'npcink_toolbox_media_batch_toolkit_unavailable', 'Npcink Abilities Toolkit is required for media optimization.', 503 );
		}
		$registered = npcink_abilities_toolkit_get_registered();
		$callback = $registered[ $ability_id ]['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return $this->error( 'npcink_toolbox_media_batch_ability_unavailable', 'The requested Toolkit media ability is unavailable.', 503 );
		}
		$result = call_user_func( $callback, $input );
		return is_array( $result ) || is_wp_error( $result ) ? $result : $this->error( 'npcink_toolbox_media_batch_ability_invalid', 'The Toolkit returned an invalid media optimization result.', 502 );
	}

	private function cloud_input( int $attachment_id, array $candidate, array $plan ): array {
		$filters = is_array( $plan['filters'] ?? null ) ? $plan['filters'] : array();
		return array(
			'attachment_id' => $attachment_id,
			'optimization_mode' => 'auto_safe',
			'optimization_profile' => 'auto_safe.v1',
			'preferred_format' => 'webp',
			'target_max_width' => 1920,
			'resize_mode' => 'fit' === (string) ( $filters['resize_mode'] ?? '' ) ? 'fit' : 'preserve',
			'expected_source_media_fingerprint' => (string) ( $candidate['media_fingerprint'] ?? '' ),
		);
	}

	private function current_fingerprint( int $attachment_id ): string {
		$path = (string) get_attached_file( $attachment_id );
		if ( '' === $path || ! is_readable( $path ) ) {
			return '';
		}
		$hash = hash_file( 'sha256', $path );
		return is_string( $hash ) ? 'sha256:' . strtolower( $hash ) : '';
	}

	private function normalize_fingerprint( string $value ): string {
		$value = strtolower( trim( $value ) );
		if ( 0 !== strpos( $value, 'sha256:' ) ) {
			$value = 'sha256:' . $value;
		}
		return preg_match( '/^sha256:[0-9a-f]{64}$/', $value ) ? $value : '';
	}

	private function can_edit_image( int $attachment_id ): bool {
		return 'attachment' === get_post_type( $attachment_id ) && wp_attachment_is_image( $attachment_id ) && current_user_can( 'upload_files' ) && current_user_can( 'edit_post', $attachment_id );
	}

	private function item_index( array $batch, int $attachment_id ): int {
		foreach ( (array) ( $batch['items'] ?? array() ) as $index => $item ) {
			if ( $attachment_id === absint( is_array( $item ) ? ( $item['attachment_id'] ?? 0 ) : 0 ) ) {
				return (int) $index;
			}
		}
		return -1;
	}

	private function apply_stop_policy( array $batch, int $last_index ): array {
		$items = (array) $batch['items'];
		$consecutive_failures = 0;
		for ( $index = $last_index; $index >= 0; --$index ) {
			if ( 'failed' !== (string) ( $items[ $index ]['status'] ?? '' ) ) {
				break;
			}
			++$consecutive_failures;
		}
		$chunk_start = (int) floor( $last_index / self::CHUNK_SIZE ) * self::CHUNK_SIZE;
		$chunk = array_slice( $items, $chunk_start, self::CHUNK_SIZE );
		$processed = array_values( array_filter( $chunk, static fn( $item ) => in_array( (string) ( $item['status'] ?? '' ), array( 'completed', 'skipped', 'failed' ), true ) ) );
		$failed = array_values( array_filter( $processed, static fn( $item ) => 'failed' === (string) ( $item['status'] ?? '' ) ) );
		if ( $consecutive_failures >= 3 || ( count( $processed ) >= 3 && count( $failed ) / count( $processed ) > 0.3 ) ) {
			$batch['status'] = 'paused';
			$batch['pause_reason'] = $consecutive_failures >= 3 ? 'three_consecutive_failures' : 'chunk_failure_rate_exceeded';
		} elseif ( 0 === (int) ( $batch['summary']['pending'] ?? 0 ) ) {
			$batch['status'] = 'completed';
		}
		return $batch;
	}

	private function with_summary( array $batch ): array {
		$summary = array( 'total' => 0, 'pending' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'bytes_before' => 0, 'bytes_after' => 0, 'bytes_saved' => 0 );
		foreach ( (array) ( $batch['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) { continue; }
			++$summary['total'];
			$status = (string) ( $item['status'] ?? 'pending' );
			$key = 'completed' === $status ? 'success' : ( isset( $summary[ $status ] ) ? $status : 'pending' );
			++$summary[ $key ];
			if ( 'completed' === $status ) {
				$summary['bytes_before'] += absint( $item['filesize_bytes'] ?? 0 );
				$summary['bytes_after'] += absint( $item['bytes_after'] ?? 0 );
			}
		}
		$summary['bytes_saved'] = max( 0, $summary['bytes_before'] - $summary['bytes_after'] );
		$batch['summary'] = $summary;
		$batch['updated_at_gmt'] = gmdate( 'c' );
		return $batch;
	}

	private function save_batch( array $batch ): void {
		$batches = array_values( array_filter( $this->all(), static fn( $existing ) => (string) ( $existing['batch_id'] ?? '' ) !== (string) $batch['batch_id'] ) );
		array_unshift( $batches, $this->with_summary( $batch ) );
		update_option( self::OPTION_NAME, array_slice( $batches, 0, self::MAX_BATCHES ), false );
	}

	private function sanitize_array( array $value ): array {
		return map_deep( $value, static fn( $item ) => is_scalar( $item ) ? sanitize_text_field( (string) $item ) : $item );
	}

	private function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
