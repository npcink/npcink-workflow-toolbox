<?php
/**
 * Bounded local continuation for Cloud-owned site media recognition.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Media_Recognition_Continuation {
	private const OPTION_NAME = 'npcink_toolbox_media_recognition_continuation';
	private const LOCK_NAME   = 'npcink_toolbox_media_recognition_continuation_lock';
	private const CRON_HOOK   = 'npcink_toolbox_continue_media_recognition';
	private const MAX_RETRIES = 3;
	private const MAX_PER_PAGE = 10;
	private const LOCK_TTL = 600;

	private Provider_Client $client;

	public function __construct( Provider_Client $client ) {
		$this->client = $client;
	}

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'ensure_schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'process' ) );
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		delete_option( self::LOCK_NAME );
	}

	/** Restores one missing wakeup for an active continuation. */
	public function ensure_schedule(): void {
		$state = $this->status();
		if (
			! in_array( $state['state'], array( 'queued', 'processing', 'retrying' ), true )
			|| false !== wp_next_scheduled( self::CRON_HOOK )
			|| $this->lock_is_current()
		) {
			return;
		}

		$eligible_at = strtotime( (string) $state['next_eligible_at'] );
		$this->schedule( false !== $eligible_at && $eligible_at > time() ? $eligible_at - time() : 30 );
	}

	/** @return array<string,mixed> */
	public function status(): array {
		$state = get_option( self::OPTION_NAME, array() );
		return is_array( $state ) ? $this->normalize_state( $state ) : $this->default_state();
	}

	/** @return array<string,mixed> */
	public function start( array $input = array() ): array {
		$current = $this->status();
		if ( in_array( $current['state'], array( 'queued', 'processing', 'retrying', 'paused' ), true ) ) {
			return $current;
		}

		$state                     = $this->default_state();
		$state['plan_id']          = 'media_recognition_' . wp_generate_uuid4();
		$state['per_page']         = max( 1, min( self::MAX_PER_PAGE, absint( $input['per_page'] ?? self::MAX_PER_PAGE ) ) );
		$state['state']            = 'queued';
		$state['next_eligible_at'] = gmdate( 'c' );
		update_option( self::OPTION_NAME, $state, false );
		$this->schedule( 1 );

		return $state;
	}

	/** @return array<string,mixed> */
	public function resume(): array {
		$state = $this->status();
		if ( 'paused' !== $state['state'] ) {
			return $state;
		}

		$state['state']            = 'queued';
		$state['pause_reason']     = '';
		$state['retry_count']      = 0;
		$state['next_eligible_at'] = gmdate( 'c' );
		update_option( self::OPTION_NAME, $state, false );
		$this->schedule( 1 );

		return $state;
	}

	public function process(): void {
		$state = $this->status();
		if ( ! in_array( $state['state'], array( 'queued', 'processing', 'retrying' ), true ) ) {
			return;
		}

		$eligible_at = strtotime( (string) $state['next_eligible_at'] );
		if ( false !== $eligible_at && $eligible_at > time() ) {
			$this->schedule( max( 1, $eligible_at - time() ) );
			return;
		}

		$lock_token = $this->acquire_lock();
		if ( '' === $lock_token ) {
			return;
		}

		try {
			$this->advance( $state );
		} finally {
			$this->release_lock( $lock_token );
		}
	}

	/** @param array<string,mixed> $state */
	private function advance( array $state ): void {
		if ( '' !== $state['run_id'] ) {
			$this->advance_cloud_run( $state );
			return;
		}

		$state['state'] = 'processing';
		$result = $this->client->refresh_site_media_index_batch(
			array(
				'after_id'    => absint( $state['next_cursor']['after_id'] ?? 0 ),
				'per_page'    => $state['per_page'],
				'upload_scope' => $state['plan_id'],
			)
		);
		if ( is_wp_error( $result ) ) {
			$this->retry_or_pause( $state, $result->get_error_code() );
			return;
		}

		$state['run_id']        = sanitize_text_field( (string) ( $result['visual_evidence_run_id'] ?? $result['run_id'] ?? '' ) );
		$state['has_more']      = ! empty( $result['has_more'] );
		$state['pending_counts'] = array(
			'processed' => absint( $result['indexed_items'] ?? 0 ),
			'qualified' => absint( $result['visual_evidence_reused_items'] ?? 0 ),
			'skipped'   => absint( $result['screened_items'] ?? 0 ),
			'failed'    => 0,
		);
		$state['pending_cursor'] = array(
			'after_id' => absint( $result['next_cursor']['after_id'] ?? $state['next_cursor']['after_id'] ),
		);

		if ( '' !== $state['run_id'] ) {
			$state['state'] = 'processing';
		} else {
			$state = $this->commit_pending_batch( $state );
		}
		$state['retry_count']      = 0;
		$state['pause_reason']     = '';
		$state['next_eligible_at'] = '';
		update_option( self::OPTION_NAME, $state, false );
		if ( in_array( $state['state'], array( 'queued', 'processing' ), true ) ) {
			$this->schedule( 'processing' === $state['state'] ? 30 : 15 );
		}
	}

	/** @param array<string,mixed> $state */
	private function advance_cloud_run( array $state ): void {
		$run = function_exists( 'npcink_cloud_addon_get_toolbox_runtime_run' )
			? npcink_cloud_addon_get_toolbox_runtime_run( $state['run_id'], 'media_recognition_poll' )
			: new WP_Error( 'cloud_runtime_unavailable', 'Cloud runtime facade unavailable.' );
		if ( is_wp_error( $run ) ) {
			$this->retry_or_pause( $state, $run->get_error_code() );
			return;
		}

		$remote_state = sanitize_key( (string) ( $run['status'] ?? $run['data']['status'] ?? '' ) );
		if ( ! in_array( $remote_state, array( 'completed', 'succeeded', 'failed', 'error', 'skipped' ), true ) ) {
			$state['state'] = 'processing';
			update_option( self::OPTION_NAME, $state, false );
			$this->schedule( 30 );
			return;
		}
		if ( in_array( $remote_state, array( 'failed', 'error' ), true ) ) {
			$state['run_id'] = '';
			$this->retry_or_pause( $state, 'cloud_run_' . $remote_state );
			return;
		}

		$result = function_exists( 'npcink_cloud_addon_get_toolbox_runtime_run_result' )
			? npcink_cloud_addon_get_toolbox_runtime_run_result( $state['run_id'], 'media_recognition_result' )
			: new WP_Error( 'cloud_runtime_result_unavailable', 'Cloud runtime result facade unavailable.' );
		if ( is_wp_error( $result ) ) {
			$this->retry_or_pause( $state, $result->get_error_code() );
			return;
		}

		$counts = $this->result_counts( $result );
		if ( is_wp_error( $counts ) ) {
			$this->retry_or_pause( $state, $counts->get_error_code() );
			return;
		}
		$state['pending_counts']['qualified'] += $counts['qualified'];
		$state['pending_counts']['skipped']   += $counts['skipped'];
		$state['pending_counts']['failed']    += $counts['failed'];
		$state['run_id']                       = '';
		$state                                 = $this->commit_pending_batch( $state );
		$state['retry_count']                  = 0;
		$state['pause_reason']                 = '';
		$state['next_eligible_at']             = '';
		update_option( self::OPTION_NAME, $state, false );
		if ( 'queued' === $state['state'] ) {
			$this->schedule( 15 );
		}
	}

	/** @param array<string,mixed> $state @return array<string,mixed> */
	private function commit_pending_batch( array $state ): array {
		foreach ( array( 'processed', 'qualified', 'skipped', 'failed' ) as $key ) {
			$state[ $key ] += absint( $state['pending_counts'][ $key ] ?? 0 );
		}
		$state['next_cursor']    = $state['pending_cursor'];
		$state['pending_counts'] = $this->empty_counts();
		$state['state']          = ! empty( $state['has_more'] ) ? 'queued' : 'complete';
		return $state;
	}

	/** @param array<string,mixed> $state */
	private function retry_or_pause( array $state, string $reason ): void {
		$state['retry_count']++;
		$state['failed']++;
		$state['pause_reason'] = sanitize_key( $reason );
		if ( $state['retry_count'] >= self::MAX_RETRIES ) {
			$state['state'] = 'paused';
			update_option( self::OPTION_NAME, $state, false );
			return;
		}

		$state['state']            = 'retrying';
		$state['next_eligible_at'] = gmdate( 'c', time() + 300 );
		update_option( self::OPTION_NAME, $state, false );
		$this->schedule( 300 );
	}

	private function schedule( int $delay ): void {
		if ( false === wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + max( 1, $delay ), self::CRON_HOOK );
		}
	}

	private function acquire_lock(): string {
		$lock = $this->normalized_lock();
		if ( ! empty( $lock ) && $lock['acquired_at'] < time() - self::LOCK_TTL ) {
			delete_option( self::LOCK_NAME );
		}

		$token = wp_generate_uuid4();
		return add_option( self::LOCK_NAME, array( 'token' => $token, 'acquired_at' => time() ), '', false ) ? $token : '';
	}

	private function lock_is_current(): bool {
		$lock = $this->normalized_lock();
		return ! empty( $lock ) && $lock['acquired_at'] >= time() - self::LOCK_TTL;
	}

	private function release_lock( string $token ): void {
		$lock = $this->normalized_lock();
		if ( ! empty( $lock ) && hash_equals( $lock['token'], $token ) ) {
			delete_option( self::LOCK_NAME );
		}
	}

	/** @return array{token:string,acquired_at:int}|array{} */
	private function normalized_lock(): array {
		$lock = get_option( self::LOCK_NAME, array() );
		if ( ! is_array( $lock ) || '' === (string) ( $lock['token'] ?? '' ) || 0 >= absint( $lock['acquired_at'] ?? 0 ) ) {
			return array();
		}
		return array( 'token' => (string) $lock['token'], 'acquired_at' => absint( $lock['acquired_at'] ) );
	}

	/** @param array<string,mixed> $response @return array{qualified:int,skipped:int,failed:int}|WP_Error */
	private function result_counts( array $response ) {
		$result = is_array( $response['data']['result'] ?? null ) ? $response['data']['result'] : ( is_array( $response['result'] ?? null ) ? $response['result'] : array() );
		if ( 'image_context_evidence.v1' !== (string) ( $result['contract_version'] ?? '' ) || 'image_context_evidence' !== (string) ( $result['artifact_type'] ?? '' ) ) {
			return new WP_Error( 'media_recognition_result_contract_invalid', 'Cloud returned an incompatible media recognition result.' );
		}

		$qualified = count(
			array_filter(
				(array) ( $result['items'] ?? array() ),
				static fn( $item ): bool => is_array( $item ) && absint( $item['attachment_id'] ?? 0 ) > 0
			)
		);
		$progress = is_array( $result['progress'] ?? null ) ? $result['progress'] : array();
		return array(
			'qualified' => $qualified,
			'skipped'   => absint( $progress['skipped_items'] ?? 0 ),
			'failed'    => absint( $progress['failed_items'] ?? 0 ),
		);
	}

	/** @return array<string,mixed> */
	private function default_state(): array {
		return array(
			'plan_id' => '', 'stable_order' => 'id_asc', 'next_cursor' => array( 'after_id' => 0 ),
			'pending_cursor' => array( 'after_id' => 0 ), 'pending_counts' => $this->empty_counts(),
			'run_id' => '', 'state' => 'idle', 'processed' => 0, 'failed' => 0, 'skipped' => 0,
			'qualified' => 0, 'retry_count' => 0, 'next_eligible_at' => '', 'pause_reason' => '',
			'per_page' => self::MAX_PER_PAGE, 'has_more' => false,
		);
	}

	/** @param array<string,mixed> $state @return array<string,mixed> */
	private function normalize_state( array $state ): array {
		$next_cursor    = is_array( $state['next_cursor'] ?? null ) ? $state['next_cursor'] : array();
		$pending_cursor = is_array( $state['pending_cursor'] ?? null ) ? $state['pending_cursor'] : array();
		$pending_counts = is_array( $state['pending_counts'] ?? null ) ? $state['pending_counts'] : array();
		return array_merge(
			$this->default_state(),
			array(
				'plan_id' => sanitize_text_field( (string) ( $state['plan_id'] ?? '' ) ),
				'stable_order' => 'id_asc',
				'next_cursor' => array( 'after_id' => absint( $next_cursor['after_id'] ?? 0 ) ),
				'pending_cursor' => array( 'after_id' => absint( $pending_cursor['after_id'] ?? $next_cursor['after_id'] ?? 0 ) ),
				'pending_counts' => array(
					'processed' => absint( $pending_counts['processed'] ?? 0 ),
					'qualified' => absint( $pending_counts['qualified'] ?? 0 ),
					'skipped' => absint( $pending_counts['skipped'] ?? 0 ),
					'failed' => absint( $pending_counts['failed'] ?? 0 ),
				),
				'run_id' => sanitize_text_field( (string) ( $state['run_id'] ?? '' ) ),
				'state' => sanitize_key( (string) ( $state['state'] ?? 'idle' ) ),
				'processed' => absint( $state['processed'] ?? 0 ), 'failed' => absint( $state['failed'] ?? 0 ),
				'skipped' => absint( $state['skipped'] ?? 0 ), 'qualified' => absint( $state['qualified'] ?? 0 ),
				'retry_count' => min( self::MAX_RETRIES, absint( $state['retry_count'] ?? 0 ) ),
				'next_eligible_at' => sanitize_text_field( (string) ( $state['next_eligible_at'] ?? '' ) ),
				'pause_reason' => sanitize_key( (string) ( $state['pause_reason'] ?? '' ) ),
				'per_page' => max( 1, min( self::MAX_PER_PAGE, absint( $state['per_page'] ?? self::MAX_PER_PAGE ) ) ),
				'has_more' => ! empty( $state['has_more'] ),
			)
		);
	}

	/** @return array{processed:int,qualified:int,skipped:int,failed:int} */
	private function empty_counts(): array {
		return array( 'processed' => 0, 'qualified' => 0, 'skipped' => 0, 'failed' => 0 );
	}
}
