<?php
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['continuation_options'] = array();
	$GLOBALS['continuation_events'] = array();
	$GLOBALS['continuation_run_responses'] = array();
	$GLOBALS['continuation_result_responses'] = array();
	function get_option( $key, $default = false ) { return $GLOBALS['continuation_options'][ $key ] ?? $default; }
	function update_option( $key, $value, $autoload = null ) { $GLOBALS['continuation_options'][ $key ] = $value; return true; }
	function add_option( $key, $value, $deprecated = '', $autoload = null ) { if ( array_key_exists( $key, $GLOBALS['continuation_options'] ) ) { return false; } $GLOBALS['continuation_options'][ $key ] = $value; return true; }
	function delete_option( $key ) { unset( $GLOBALS['continuation_options'][ $key ] ); return true; }
	function wp_next_scheduled( $hook ) { return $GLOBALS['continuation_events'][ $hook ] ?? false; }
	function wp_schedule_single_event( $time, $hook ) { $GLOBALS['continuation_events'][ $hook ] = $time; return true; }
	function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['continuation_events'][ $hook ] ); }
	function add_action() {}
	function absint( $value ) { return abs( (int) $value ); }
	function sanitize_text_field( $value ) { return trim( (string) $value ); }
	function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
	function wp_generate_uuid4() { static $i = 0; return sprintf( '11111111-1111-4111-8111-%012d', ++$i ); }
	function is_wp_error( $value ) { return $value instanceof WP_Error; }
	function npcink_cloud_addon_get_toolbox_runtime_run() { return array_shift( $GLOBALS['continuation_run_responses'] ); }
	function npcink_cloud_addon_get_toolbox_runtime_run_result() { return array_shift( $GLOBALS['continuation_result_responses'] ); }
	class WP_Error {
		private string $code;
		public function __construct( string $code, string $message = '' ) { $this->code = $code; }
		public function get_error_code(): string { return $this->code; }
	}
	function continuation_assert( bool $condition, string $message ): void {
		if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
		echo "PASS: {$message}\n";
	}
}

namespace Npcink_Toolbox {
	final class Provider_Client {
		public array $results = array();
		public array $inputs = array();
		public function refresh_site_media_index_batch( array $input ) { $this->inputs[] = $input; return array_shift( $this->results ); }
	}
	require_once dirname( __DIR__ ) . '/includes/Media_Recognition_Continuation.php';

	$client = new Provider_Client();
	$client->results[] = array( 'indexed_items' => 2, 'screened_items' => 1, 'has_more' => false, 'next_cursor' => array( 'after_id' => 12 ) );
	$owner = new Media_Recognition_Continuation( $client );
	$started = $owner->start( array( 'per_page' => 2 ) );
	$owner->process();
	$complete = $owner->status();
	\continuation_assert( 'queued' === $started['state'] && 'id_asc' === $started['stable_order'], 'A new continuation owns one stable id_asc plan.' );
	\continuation_assert( 'complete' === $complete['state'] && 2 === $complete['processed'] && 1 === $complete['skipped'] && 12 === $complete['next_cursor']['after_id'], 'A local-only batch commits its pending cursor and counts.' );
	\continuation_assert( 0 === $client->inputs[0]['after_id'], 'The first batch starts at the stable after_id origin.' );

	$GLOBALS['continuation_options'] = array();
	$GLOBALS['continuation_events'] = array();
	$client->results = array( array( 'indexed_items' => 2, 'visual_evidence_reused_items' => 1, 'visual_evidence_run_id' => 'run-media-1', 'has_more' => true, 'next_cursor' => array( 'after_id' => 42 ) ) );
	$GLOBALS['continuation_run_responses'][] = array( 'data' => array( 'status' => 'succeeded' ) );
	$GLOBALS['continuation_result_responses'][] = array( 'data' => array( 'result' => array( 'artifact_type' => 'image_context_evidence', 'contract_version' => 'image_context_evidence.v1', 'items' => array( array( 'attachment_id' => '42' ) ), 'progress' => array( 'failed_items' => 0, 'skipped_items' => 0 ) ) ) );
	$owner->start( array( 'per_page' => 2 ) );
	$owner->process();
	$waiting = $owner->status();
	\continuation_assert( 'processing' === $waiting['state'] && 0 === $waiting['next_cursor']['after_id'] && 42 === $waiting['pending_cursor']['after_id'], 'A Cloud run keeps the page cursor pending.' );
	unset( $GLOBALS['continuation_events']['npcink_toolbox_continue_media_recognition'] );
	$owner->process();
	$continued = $owner->status();
	\continuation_assert( 'queued' === $continued['state'] && 42 === $continued['next_cursor']['after_id'] && 2 === $continued['qualified'], 'A validated Cloud result commits reused and newly-qualified counts once.' );

	$GLOBALS['continuation_options'] = array();
	$GLOBALS['continuation_events'] = array();
	$client->results = array( new \WP_Error( 'transport_failed' ), new \WP_Error( 'transport_failed' ), new \WP_Error( 'transport_failed' ) );
	$owner->start();
	for ( $attempt = 0; $attempt < 3; $attempt++ ) {
		unset( $GLOBALS['continuation_events']['npcink_toolbox_continue_media_recognition'] );
		$GLOBALS['continuation_options']['npcink_toolbox_media_recognition_continuation']['next_eligible_at'] = '';
		$owner->process();
	}
	$paused = $owner->status();
	\continuation_assert( 'paused' === $paused['state'] && 3 === $paused['retry_count'] && 'transport_failed' === $paused['pause_reason'], 'Three consecutive failures pause the same plan.' );
	\continuation_assert( 'queued' === $owner->resume()['state'], 'Explicit recovery resumes only the paused plan.' );

	$GLOBALS['continuation_options']['npcink_toolbox_media_recognition_continuation_lock'] = array( 'token' => 'stale', 'acquired_at' => time() - 601 );
	$GLOBALS['continuation_options']['npcink_toolbox_media_recognition_continuation']['next_eligible_at'] = '';
	$client->results = array( array( 'indexed_items' => 0, 'has_more' => false, 'next_cursor' => array( 'after_id' => 42 ) ) );
	unset( $GLOBALS['continuation_events']['npcink_toolbox_continue_media_recognition'] );
	$owner->process();
	\continuation_assert( ! isset( $GLOBALS['continuation_options']['npcink_toolbox_media_recognition_continuation_lock'] ), 'A stale atomic lock self-heals and is released after one batch.' );
}
