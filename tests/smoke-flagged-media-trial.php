<?php
/**
 * Local WordPress operator trial for the flagged media review set.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-flagged-media-trial.php
 *
 * Samples the real recent media library through the /ai/site-helpers
 * flagged_media_suggestions intent, mocks Cloud content-safety statuses through
 * the local host filter, verifies the review set, proves attachment metadata
 * stays unchanged, exercises the fail-closed path, and creates no fixtures.
 * Stays outside composer test:all.
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

function toolbox_flagged_media_trial_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_flagged_media_trial_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function toolbox_flagged_media_trial_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_flagged_media_trial_fail( $message );
	}

	toolbox_flagged_media_trial_pass( $message );
}

function toolbox_flagged_media_trial_rest_request( array $payload ): array {
	$request = new WP_REST_Request( 'POST', '/npcink-toolbox/v1/ai/site-helpers' );
	$request->set_body_params( $payload );
	$response = rest_do_request( $request );
	if ( is_wp_error( $response ) ) {
		toolbox_flagged_media_trial_fail( 'REST request failed: ' . $response->get_error_code() );
	}

	$data = rest_get_server()->response_to_data( $response, false );
	if ( ! is_array( $data ) ) {
		toolbox_flagged_media_trial_fail( 'REST response is not an array.' );
	}

	return $data;
}

function toolbox_flagged_media_trial_sample_ids( array $runtime_payload ): array {
	$prompt   = (string) ( $runtime_payload['input']['messages'][1]['content'] ?? '' );
	$decoded  = json_decode( $prompt, true );
	$items    = is_array( $decoded ) ? ( $decoded['source']['flagged_media_sample']['items'] ?? array() ) : array();
	$ids      = array();
	foreach ( is_array( $items ) ? $items : array() as $item ) {
		$attachment_id = absint( $item['attachment_id'] ?? 0 );
		if ( 0 < $attachment_id ) {
			$ids[] = $attachment_id;
		}
	}

	return $ids;
}

function toolbox_flagged_media_trial_attachment_snapshot( array $attachment_ids ): array {
	$snapshot = array();
	foreach ( $attachment_ids as $attachment_id ) {
		$snapshot[ $attachment_id ] = array(
			'title' => sanitize_text_field( (string) get_the_title( $attachment_id ) ),
			'alt'   => sanitize_text_field( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ),
			'status' => get_post_status( $attachment_id ),
		);
	}

	return $snapshot;
}

$admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
toolbox_flagged_media_trial_assert( 0 < absint( $admins[0] ?? 0 ), 'A local administrator account is available for the REST dispatch.' );
wp_set_current_user( absint( $admins[0] ) );

$cloud_filter_calls = 0;
$captured_payloads  = array();
$captured_ids       = array();
add_filter(
	'npcink_toolbox_hosted_ai_site_helper_cloud_request',
	static function ( $handled, array $runtime_payload, array $input ) use ( &$cloud_filter_calls, &$captured_payloads, &$captured_ids ) {
		++$cloud_filter_calls;
		$captured_payloads[] = $runtime_payload;
		$captured_ids        = toolbox_flagged_media_trial_sample_ids( $runtime_payload );

		$statuses = array();
		if ( isset( $captured_ids[0] ) ) {
			$statuses[] = array(
				'attachment_id'    => $captured_ids[0],
				'content_safety'   => 'flagged',
				'confidence'       => 0.92,
				'reasons'          => array( 'projection record matches adult-content policy' ),
				'suggested_action' => 'review_attachment_manually',
			);
			$statuses[] = array(
				'attachment_id'    => $captured_ids[0],
				'content_safety'   => 'invalid_duplicate',
				'confidence'       => 7,
				'reasons'          => array( 'duplicate id with invalid values must be ignored' ),
				'suggested_action' => 'auto_delete_media',
			);
		}
		if ( isset( $captured_ids[1] ) ) {
			$statuses[] = array(
				'attachment_id'    => $captured_ids[1],
				'content_safety'   => 'safe',
				'confidence'       => 0.99,
				'reasons'          => array( 'projection record shows ordinary site imagery' ),
				'suggested_action' => 'review_attachment_manually',
			);
		}

		return array(
			'status' => 'ready',
			'run_id' => 'local_flagged_media_trial',
			'result' => array(
				'status'      => 'ready',
				'model_id'    => 'local_trial_no_cloud_runtime',
				'output_text' => 'Local operator trial: safety statuses are review hints only.',
				'content_safety_statuses' => $statuses,
			),
		);
	},
	10,
	3
);

$pre_ids = array_map(
	'absint',
	get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'number'         => 5,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		)
	)
);
toolbox_flagged_media_trial_assert( 0 < count( $pre_ids ), 'The local media library has recent images for the trial sample.' );
$before = toolbox_flagged_media_trial_attachment_snapshot( $pre_ids );

$data = toolbox_flagged_media_trial_rest_request(
	array(
		'intent'           => 'flagged_media_suggestions',
		'media_sample_size' => 5,
	)
);

toolbox_flagged_media_trial_assert( 1 === $cloud_filter_calls, 'Trial used the local host filter instead of requiring Cloud runtime availability.' );
toolbox_flagged_media_trial_assert( 'pii' === (string) ( $captured_payloads[0]['data_classification'] ?? '' ), 'Flagged media runtime payload is classified pii for no-store Cloud handling.' );
toolbox_flagged_media_trial_assert( 0 < count( $captured_ids ), 'The Cloud prompt carried the bounded media metadata sample.' );
$watch_ids = array_values( array_unique( array_merge( $pre_ids, $captured_ids ) ) );
$after     = toolbox_flagged_media_trial_attachment_snapshot( $watch_ids );
foreach ( $pre_ids as $attachment_id ) {
	toolbox_flagged_media_trial_assert( ( $before[ $attachment_id ] ?? null ) === ( $after[ $attachment_id ] ?? null ), 'Attachment ' . $attachment_id . ' metadata snapshot is unchanged after the first request.' );
}
$first_pass = toolbox_flagged_media_trial_attachment_snapshot( $captured_ids );

$review_set = is_array( $data['flagged_media_review_set'] ?? null ) ? $data['flagged_media_review_set'] : array();
toolbox_flagged_media_trial_assert( 'flagged_media_review_set.v1' === (string) ( $review_set['contract_version'] ?? '' ), 'Trial response returns the flagged media review-set contract.' );
toolbox_flagged_media_trial_assert( 'ready' === (string) ( $review_set['cloud_status'] ?? '' ), 'Review set reports Cloud safety status as ready.' );
toolbox_flagged_media_trial_assert( true === (bool) ( $review_set['media_unchanged'] ?? false ), 'Review set declares media unchanged.' );
toolbox_flagged_media_trial_assert( 'suggestion_only' === (string) ( $review_set['write_posture'] ?? '' ), 'Review set stays suggestion-only.' );
toolbox_flagged_media_trial_assert( false === (bool) ( $review_set['direct_wordpress_write'] ?? true ), 'Review set does not authorize direct WordPress writes.' );
toolbox_flagged_media_trial_assert( false === (bool) ( $review_set['proposal_created'] ?? true ), 'Review set does not create a proposal.' );

$summary  = is_array( $review_set['eligibility_summary'] ?? null ) ? $review_set['eligibility_summary'] : array();
$selected = is_array( $review_set['selected_items'] ?? null ) ? $review_set['selected_items'] : array();
$blocked  = is_array( $review_set['blocked_items'] ?? null ) ? $review_set['blocked_items'] : array();
toolbox_flagged_media_trial_assert( count( $captured_ids ) === (int) ( $summary['sampled_count'] ?? -1 ), 'Eligibility summary sampled count matches the Cloud sample.' );
toolbox_flagged_media_trial_assert( 1 === count( $selected ), 'Exactly one attachment is flagged; the duplicate invalid status entry is ignored.' );
toolbox_flagged_media_trial_assert( isset( $captured_ids[0] ) && (int) ( $selected[0]['attachment_id'] ?? 0 ) === $captured_ids[0], 'The flagged item is the mocked attachment.' );
toolbox_flagged_media_trial_assert( 1 === (int) ( $summary['safe_count'] ?? -1 ), 'The safe attachment is counted but not selected.' );
toolbox_flagged_media_trial_assert( ( count( $captured_ids ) - 2 ) === count( $blocked ), 'Attachments without a status are blocked, never guessed.' );
foreach ( $blocked as $item ) {
	toolbox_flagged_media_trial_assert( 'safety_status_unknown' === (string) ( $item['blocked_reason'] ?? '' ), 'Blocked reason is safety_status_unknown.' );
}
toolbox_flagged_media_trial_assert( 'auto_delete_media' !== (string) ( $selected[0]['suggested_action'] ?? '' ), 'Invalid suggested actions never reach the review set.' );

$final = toolbox_flagged_media_trial_attachment_snapshot( $watch_ids );
foreach ( $watch_ids as $attachment_id ) {
	$baseline = isset( $before[ $attachment_id ] ) ? $before[ $attachment_id ] : ( $first_pass[ $attachment_id ] ?? null );
	toolbox_flagged_media_trial_assert( $baseline === ( $final[ $attachment_id ] ?? null ), 'Attachment ' . $attachment_id . ' metadata snapshot is unchanged.' );
}

remove_all_filters( 'npcink_toolbox_hosted_ai_site_helper_cloud_request' );
$unavailable = toolbox_flagged_media_trial_rest_request(
	array(
		'intent'           => 'flagged_media_suggestions',
		'media_sample_size' => 5,
	)
);
toolbox_flagged_media_trial_assert( 'cloud_required' === (string) ( $unavailable['status'] ?? '' ), 'Without the Cloud runtime the response fails closed to cloud_required.' );
$unavailable_set = is_array( $unavailable['flagged_media_review_set'] ?? null ) ? $unavailable['flagged_media_review_set'] : array();
toolbox_flagged_media_trial_assert( array() === ( $unavailable_set['selected_items'] ?? null ), 'Fail-closed response contains no fabricated safety statuses.' );

echo 'INFO: Flagged media trial summary=' . wp_json_encode( $summary ) . PHP_EOL;
echo "Flagged media operator trial passed.\n";
