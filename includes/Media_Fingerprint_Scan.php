<?php
/**
 * Low-frequency local media freshness scan.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Media_Fingerprint_Scan {
	private const HOOK       = 'npcink_toolbox_weekly_media_fingerprint_scan';
	private const RECURRENCE = 'npcink_toolbox_weekly';
	private const MAX_ITEMS  = 100;

	private Provider_Client $client;

	public function __construct( Provider_Client $client ) {
		$this->client = $client;
	}

	public function register_hooks(): void {
		add_filter( 'cron_schedules', array( $this, 'register_schedule' ) );
		add_action( 'init', array( $this, 'maybe_schedule' ) );
		add_action( self::HOOK, array( $this, 'run' ) );
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public function register_schedule( array $schedules ): array {
		$schedules[ self::RECURRENCE ] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly', 'npcink-workflow-toolbox' ),
		);
		return $schedules;
	}

	public function maybe_schedule(): void {
		if ( ! $this->enabled() ) {
			wp_clear_scheduled_hook( self::HOOK );
			return;
		}
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, self::RECURRENCE, self::HOOK );
		}
	}

	public function run(): void {
		if ( ! $this->enabled() ) {
			return;
		}

		$changes = $this->client->scan_media_fingerprint_changes( self::MAX_ITEMS );
		if ( ! is_array( $changes ) ) {
			return;
		}

		$seen = array();
		foreach ( $changes as $change ) {
			$attachment_id = is_array( $change ) ? absint( $change['attachment_id'] ?? 0 ) : 0;
			if ( $attachment_id <= 0 || isset( $seen[ $attachment_id ] ) ) {
				continue;
			}
			$seen[ $attachment_id ] = true;
			do_action(
				'npcink_abilities_toolkit_media_file_version_changed',
				$attachment_id,
				array(
					'new_media_fingerprint' => sanitize_text_field( (string) ( $change['media_fingerprint'] ?? '' ) ),
					'reason'                => 'periodic_fingerprint_scan',
				)
			);
		}
	}

	/** @return array<string,mixed> */
	public function status(): array {
		$enabled = $this->enabled();
		$next_run = $enabled ? wp_next_scheduled( self::HOOK ) : false;
		$overdue = is_int( $next_run ) && $next_run > 0 && $next_run < time() - DAY_IN_SECONDS;
		return array(
			'enabled' => $enabled,
			'next_run' => is_int( $next_run ) ? $next_run : 0,
			'overdue' => $overdue,
			'status' => ! $enabled ? 'disabled' : ( $overdue ? 'overdue' : ( $next_run ? 'scheduled' : 'not_scheduled' ) ),
		);
	}

	private function enabled(): bool {
		return (bool) apply_filters( 'npcink_toolbox_media_fingerprint_scan_enabled', true )
			&& (bool) apply_filters( 'npcink_toolbox_cloud_addon_verified', false )
			&& (bool) apply_filters( 'npcink_toolbox_site_knowledge_transport_enabled', false );
	}
}
