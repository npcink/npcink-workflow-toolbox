<?php
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['continuation_options'] = array();
	$GLOBALS['continuation_events'] = array();
	$GLOBALS['continuation_run_responses'] = array();
	$GLOBALS['continuation_result_responses'] = array();
	$GLOBALS['continuation_current_user_id'] = 1;
	$GLOBALS['continuation_user_can_upload'] = array( 1 => true );
	function get_option( $key, $default = false ) { return $GLOBALS['continuation_options'][ $key ] ?? $default; }
	function update_option( $key, $value, $autoload = null ) { $GLOBALS['continuation_options'][ $key ] = $value; return true; }
	function add_option( $key, $value, $deprecated = '', $autoload = null ) { if ( array_key_exists( $key, $GLOBALS['continuation_options'] ) ) { return false; } $GLOBALS['continuation_options'][ $key ] = $value; return true; }
	function delete_option( $key ) { unset( $GLOBALS['continuation_options'][ $key ] ); return true; }
	function wp_next_scheduled( $hook ) { return $GLOBALS['continuation_events'][ $hook ] ?? false; }
	function wp_schedule_single_event( $time, $hook ) { $GLOBALS['continuation_events'][ $hook ] = $time; return true; }
	function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['continuation_events'][ $hook ] ); }
	function add_action() {}
	function get_current_user_id() { return (int) $GLOBALS['continuation_current_user_id']; }
	function wp_set_current_user( $user_id ) { $GLOBALS['continuation_current_user_id'] = (int) $user_id; }
	function current_user_can( $capability ) { return 'upload_files' === $capability && ! empty( $GLOBALS['continuation_user_can_upload'][ get_current_user_id() ] ); }
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
	$client->results[] = array( 'indexed_items' => 2, 'visual_evidence_reused_items' => 1, 'visual_evidence_recognized_items' => 0, 'screened_items' => 1, 'has_more' => false, 'next_cursor' => array( 'after_id' => 12 ) );
	$owner = new Media_Recognition_Continuation( $client );
	$started = $owner->start( array( 'per_page' => 2 ) );
	\continuation_assert( 1 === $started['initiated_by'], 'The continuation records the authorized initiating user.' );
	wp_set_current_user( 0 );
	$owner->process();
	$complete = $owner->status();
	\continuation_assert( 'queued' === $started['state'] && 'id_asc' === $started['stable_order'], 'A new continuation owns one stable id_asc plan.' );
	\continuation_assert( 'complete' === $complete['state'] && 2 === $complete['processed'] && 1 === $complete['skipped'] && 12 === $complete['next_cursor']['after_id'], 'A local-only batch commits its pending cursor and counts.' );
	\continuation_assert( 0 === $client->inputs[0]['after_id'], 'The first batch starts at the stable after_id origin.' );
	\continuation_assert( 0 === get_current_user_id(), 'Cron restores the previous WordPress user after one batch.' );
	wp_set_current_user( 1 );

	$GLOBALS['continuation_options'] = array();
	$GLOBALS['continuation_events'] = array();
	$client->results = array(
		array( 'indexed_items' => 2, 'visual_evidence_reused_items' => 0, 'visual_evidence_recognized_items' => 2, 'screened_items' => 0, 'has_more' => false, 'next_cursor' => array( 'after_id' => 22 ) ),
	);
	$client->inputs = array();
	$owner->queue_changed_attachment( 22 );
	$owner->queue_changed_attachment( 11 );
	$owner->queue_changed_attachment( 22 );
	$awaiting = $owner->status();
	\continuation_assert( 'awaiting_confirmation' === $awaiting['state'] && 'changed_attachments' === $awaiting['scope'] && array( 11, 22 ) === $awaiting['attachment_ids'], 'Changed attachment Hooks merge into one sorted awaiting-confirmation plan.' );
	\continuation_assert( array() === $client->inputs && array() === $GLOBALS['continuation_events'], 'Discovery does not call Provider work or schedule continuation processing.' );
	$confirmed = $owner->confirm_changed_attachments();
	\continuation_assert( 'queued' === $confirmed['state'] && 'confirmed' === $confirmed['confirmation_status'], 'An authorized administrator explicitly confirms the changed-attachment plan.' );
	unset( $GLOBALS['continuation_events']['npcink_toolbox_continue_media_recognition'] );
	wp_set_current_user( 0 );
	$owner->process();
	$targeted_complete = $owner->status();
	\continuation_assert( array( 11, 22 ) === $client->inputs[0]['attachment_ids'] && 'complete' === $targeted_complete['state'], 'Confirmed processing sends only the sorted changed attachment batch.' );
	wp_set_current_user( 1 );

	$GLOBALS['continuation_options'] = array();
	$GLOBALS['continuation_events'] = array();
	$client->inputs = array();
	$client->results = array( array( 'indexed_items' => 1, 'visual_evidence_reused_items' => 1, 'visual_evidence_recognized_items' => 0, 'screened_items' => 0, 'has_more' => false, 'next_cursor' => array( 'after_id' => 50 ) ) );
	$owner->start();
	$owner->queue_changed_attachment( 77 );
	unset( $GLOBALS['continuation_events']['npcink_toolbox_continue_media_recognition'] );
	wp_set_current_user( 0 );
	$owner->process();
	$followup = $owner->status();
	\continuation_assert( 'awaiting_confirmation' === $followup['state'] && array( 77 ) === $followup['attachment_ids'], 'Changes discovered during a full plan become one follow-up confirmation instead of a duplicate active plan.' );
	\continuation_assert( 0 === $followup['processed'] && 0 === $followup['qualified'] && 0 === $followup['skipped'] && 0 === $followup['failed'] && 0 === $followup['next_cursor']['after_id'], 'A follow-up changed-attachment plan does not inherit counters or cursors from the completed full plan.' );
	wp_set_current_user( 1 );

	$GLOBALS['continuation_options'] = array();
	$GLOBALS['continuation_events'] = array();
	$client->results = array(
		array( 'indexed_items' => 2, 'visual_evidence_reused_items' => 1, 'visual_evidence_run_id' => 'run-media-1', 'has_more' => true, 'next_cursor' => array( 'after_id' => 42 ) ),
		array( 'indexed_items' => 2, 'visual_evidence_run_id' => '', 'screened_items' => 0, 'has_more' => true, 'next_cursor' => array( 'after_id' => 42 ) ),
	);
	$GLOBALS['continuation_run_responses'][] = array( 'status' => 'ok', 'data' => array( 'status' => 'succeeded' ) );
	$GLOBALS['continuation_result_responses'][] = array( 'data' => array( 'result' => array( 'artifact_type' => 'image_context_evidence', 'contract_version' => 'image_context_evidence.v1', 'items' => array( array( 'attachment_id' => '42' ) ), 'progress' => array( 'failed_items' => 0, 'skipped_items' => 0 ), 'write_posture' => 'suggestion_only', 'direct_wordpress_write' => false ) ) );
	$owner->start( array( 'per_page' => 2 ) );
	wp_set_current_user( 0 );
	$owner->process();
	$waiting = $owner->status();
	\continuation_assert( 'processing' === $waiting['state'] && 0 === $waiting['next_cursor']['after_id'] && 42 === $waiting['pending_cursor']['after_id'], 'A Cloud run keeps the page cursor pending.' );
	unset( $GLOBALS['continuation_events']['npcink_toolbox_continue_media_recognition'] );
	$owner->process();
	$continued = $owner->status();
	\continuation_assert( 'queued' === $continued['state'] && 42 === $continued['next_cursor']['after_id'] && 1 === $continued['qualified'], 'A validated Cloud result commits the actual qualified item count once.' );
	\continuation_assert( 'image_context_evidence.v1' === $client->inputs[2]['image_context_evidence']['contract_version'], 'Validated Cloud evidence enters the same-batch Site Knowledge sync without option persistence.' );
	wp_set_current_user( 1 );

	$GLOBALS['continuation_options'] = array();
	$GLOBALS['continuation_events'] = array();
	$GLOBALS['continuation_run_responses'] = array( array( 'status' => 'ok', 'data' => array( 'status' => 'succeeded' ) ) );
	$GLOBALS['continuation_result_responses'] = array( array( 'data' => array( 'result' => array( 'artifact_type' => 'image_context_evidence', 'contract_version' => 'image_context_evidence.v1', 'items' => array( array( 'attachment_id' => '10' ) ), 'progress' => array( 'failed_items' => 0, 'skipped_items' => 0 ), 'write_posture' => 'suggestion_only', 'direct_wordpress_write' => false ) ) ) );
	$client->inputs = array();
	$client->results = array(
		array( 'indexed_items' => 10, 'visual_evidence_reused_items' => 0, 'visual_evidence_run_id' => 'run-targeted-1', 'has_more' => false, 'next_cursor' => array( 'after_id' => 0 ) ),
		array( 'indexed_items' => 10, 'visual_evidence_run_id' => '', 'screened_items' => 0, 'has_more' => false, 'next_cursor' => array( 'after_id' => 0 ) ),
		array( 'indexed_items' => 2, 'visual_evidence_reused_items' => 2, 'visual_evidence_recognized_items' => 0, 'screened_items' => 0, 'has_more' => false, 'next_cursor' => array( 'after_id' => 0 ) ),
	);
	foreach ( range( 1, 12 ) as $attachment_id ) {
		$owner->queue_changed_attachment( $attachment_id );
	}
	$owner->confirm_changed_attachments();
	wp_set_current_user( 0 );
	unset( $GLOBALS['continuation_events']['npcink_toolbox_continue_media_recognition'] );
	$owner->process();
	$targeted_waiting = $owner->status();
	\continuation_assert( 'processing' === $targeted_waiting['state'] && 10 === $targeted_waiting['pending_cursor']['after_id'], 'An asynchronous targeted batch holds the highest selected attachment id as its pending cursor.' );
	unset( $GLOBALS['continuation_events']['npcink_toolbox_continue_media_recognition'] );
	$owner->process();
	$targeted_continued = $owner->status();
	\continuation_assert( 'queued' === $targeted_continued['state'] && 10 === $targeted_continued['next_cursor']['after_id'], 'Targeted Cloud replay cannot reset the local attachment cursor returned as zero by the Provider client.' );
	unset( $GLOBALS['continuation_events']['npcink_toolbox_continue_media_recognition'] );
	$owner->process();
	$targeted_finished = $owner->status();
	\continuation_assert( range( 1, 10 ) === $client->inputs[0]['attachment_ids'] && range( 1, 10 ) === $client->inputs[1]['attachment_ids'] && array( 11, 12 ) === $client->inputs[2]['attachment_ids'], 'Targeted asynchronous continuation advances once and processes only the remaining attachment ids.' );
	\continuation_assert( 'complete' === $targeted_finished['state'] && 12 === $targeted_finished['next_cursor']['after_id'], 'A multi-batch targeted plan completes at the final attachment id.' );
	wp_set_current_user( 1 );

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

	$GLOBALS['continuation_options'] = array();
	$GLOBALS['continuation_events'] = array();
	wp_set_current_user( 1 );
	$owner->start();
	$GLOBALS['continuation_user_can_upload'][1] = false;
	wp_set_current_user( 0 );
	$owner->process();
	$permission_paused = $owner->status();
	\continuation_assert( 'paused' === $permission_paused['state'] && 'initiator_permission_revoked' === $permission_paused['pause_reason'], 'Cron pauses when the initiating user loses upload permission.' );
	\continuation_assert( 0 === get_current_user_id(), 'Permission failure still restores the previous WordPress user.' );
	$GLOBALS['continuation_user_can_upload'][1] = true;
	wp_set_current_user( 1 );
	\continuation_assert( 'queued' === $owner->resume()['state'], 'An authorized administrator can resume the permission-paused continuation.' );
	$GLOBALS['continuation_options']['npcink_toolbox_media_recognition_continuation']['state'] = 'paused';
	wp_set_current_user( 0 );
	$permission_error = $owner->resume();
	\continuation_assert( is_wp_error( $permission_error ) && 'npcink_toolbox_media_recognition_permission_denied' === $permission_error->get_error_code(), 'An unauthorized caller cannot resume media recognition.' );
}
