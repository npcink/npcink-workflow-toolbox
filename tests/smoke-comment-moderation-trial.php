<?php
/**
 * Local WordPress operator trial for the comment moderation review set.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-comment-moderation-trial.php
 *
 * Creates three temporary hold comments (one on a non-public post), mocks the
 * Cloud site-helper response through the local host filter, dispatches the
 * real /ai/site-helpers route, verifies the review set and privacy omissions,
 * then removes every temporary object. Requires a running local WordPress and
 * stays outside composer test:all.
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

$GLOBALS['toolbox_comment_moderation_trial_comment_ids'] = array();
$GLOBALS['toolbox_comment_moderation_trial_post_ids']    = array();

function toolbox_comment_moderation_trial_cleanup_fixtures(): void {
	if ( ! function_exists( 'wp_delete_comment' ) ) {
		return;
	}
	foreach ( $GLOBALS['toolbox_comment_moderation_trial_comment_ids'] as $comment_id ) {
		wp_delete_comment( (int) $comment_id, true );
	}
	foreach ( $GLOBALS['toolbox_comment_moderation_trial_post_ids'] as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}
	$GLOBALS['toolbox_comment_moderation_trial_comment_ids'] = array();
	$GLOBALS['toolbox_comment_moderation_trial_post_ids']    = array();
}

function toolbox_comment_moderation_trial_pass( string $message ): void {
	echo "PASS: {$message}\n";
}

function toolbox_comment_moderation_trial_fail( string $message ): void {
	toolbox_comment_moderation_trial_cleanup_fixtures();
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function toolbox_comment_moderation_trial_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		toolbox_comment_moderation_trial_fail( $message );
	}

	toolbox_comment_moderation_trial_pass( $message );
}

function toolbox_comment_moderation_trial_rest_request( array $payload ): array {
	$request = new WP_REST_Request( 'POST', '/npcink-toolbox/v1/ai/site-helpers' );
	$request->set_body_params( $payload );
	$response = rest_do_request( $request );
	if ( is_wp_error( $response ) ) {
		toolbox_comment_moderation_trial_fail( 'REST request failed: ' . $response->get_error_code() );
	}

	$data = rest_get_server()->response_to_data( $response, false );
	if ( ! is_array( $data ) ) {
		toolbox_comment_moderation_trial_fail( 'REST response is not an array.' );
	}

	return $data;
}

$admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
$admin_id = absint( $admins[0] ?? 0 );
toolbox_comment_moderation_trial_assert( 0 < $admin_id, 'A local administrator account is available for the REST dispatch.' );
wp_set_current_user( $admin_id );

$published_post_id = (int) wp_insert_post(
	array(
		'post_title'   => 'Comment Moderation Trial Published Post',
		'post_status'  => 'publish',
		'post_type'    => 'post',
		'post_content' => 'Trial post for the comment moderation review.',
	)
);
$private_post_id = (int) wp_insert_post(
	array(
		'post_title'   => 'Comment Moderation Trial Private Post',
		'post_status'  => 'private',
		'post_type'    => 'post',
		'post_content' => 'Trial post that must not leak its title.',
	)
);
toolbox_comment_moderation_trial_assert( 0 < $published_post_id && 0 < $private_post_id, 'Temporary published and private trial posts were created.' );
$GLOBALS['toolbox_comment_moderation_trial_post_ids'] = array( $published_post_id, $private_post_id );

$comment_ids = array();
$trial_rows  = array(
	array( $published_post_id, 'Great article, thanks for sharing the details.', 'Alice Trial', 'https://example.com/alice' ),
	array( $published_post_id, 'Buy cheap sunglasses now, best prices, visit our shop today!', 'Bob Trial', 'https://spam-shop.example' ),
	array( $private_post_id, 'Could you clarify the second section?', 'Carol Trial', '' ),
);
foreach ( $trial_rows as $index => $row ) {
	$comment_id = (int) wp_insert_comment(
		array(
			'comment_post_ID'      => $row[0],
			'comment_author'       => $row[2],
			'comment_author_email' => 'trial-' . $index . '@example.com',
			'comment_author_url'   => $row[3],
			'comment_author_IP'    => '10.20.30.' . ( $index + 1 ),
			'comment_agent'        => 'TrialAgent/1.0',
			'comment_content'      => $row[1],
			'comment_approved'     => 0,
			'comment_type'         => 'comment',
		)
	);
	toolbox_comment_moderation_trial_assert( 0 < $comment_id, 'Temporary hold comment ' . $index . ' was created.' );
	$comment_ids[]                                                           = $comment_id;
	$GLOBALS['toolbox_comment_moderation_trial_comment_ids'][]               = $comment_id;
}

$cloud_filter_calls = 0;
$captured_payloads  = array();
add_filter(
	'npcink_toolbox_hosted_ai_site_helper_cloud_request',
	static function ( $handled, array $runtime_payload, array $input ) use ( &$cloud_filter_calls, &$captured_payloads, $comment_ids ) {
		++$cloud_filter_calls;
		$captured_payloads[] = $runtime_payload;

		return array(
			'status' => 'ready',
			'run_id' => 'local_comment_moderation_trial',
			'result' => array(
				'status'      => 'ready',
				'model_id'    => 'local_trial_no_cloud_runtime',
				'output_text' => 'Local operator trial: classifications are review hints only.',
				'classifications' => array(
					array(
						'comment_id'       => $comment_ids[0],
						'classification'   => 'legitimate',
						'confidence'       => 0.91,
						'reasons'          => array( 'on-topic thank-you without links' ),
						'suggested_action' => 'review_manually',
					),
					array(
						'comment_id'       => $comment_ids[1],
						'classification'   => 'spam',
						'confidence'       => 0.95,
						'reasons'          => array( 'off-topic promotion with shop link' ),
						'suggested_action' => 'open_in_wordpress_moderation_queue',
					),
					array(
						'comment_id'       => $comment_ids[1],
						'classification'   => 'invalid_duplicate',
						'confidence'       => 5,
						'reasons'          => array( 'duplicate id with invalid values must be ignored' ),
						'suggested_action' => 'auto_delete_comment',
					),
				),
			),
		);
	},
	10,
	3
);

$data = toolbox_comment_moderation_trial_rest_request(
	array(
		'intent'               => 'comment_moderation_suggestions',
		'comment_sample_size'  => 10,
	)
);

toolbox_comment_moderation_trial_assert( 1 === $cloud_filter_calls, 'Trial used the local host filter instead of requiring Cloud runtime availability.' );
toolbox_comment_moderation_trial_assert( 'pii' === (string) ( $data['cloud_data_classification'] ?? '' ) || 'pii' === (string) ( $captured_payloads[0]['data_classification'] ?? '' ), 'Comment moderation runtime payload is classified pii for no-store Cloud handling.' );

$prompt_json = wp_json_encode( $captured_payloads[0] ?? array() );
toolbox_comment_moderation_trial_assert( false === strpos( (string) $prompt_json, 'trial-0@example.com' ) && false === strpos( (string) $prompt_json, '10.20.30.' ) && false === strpos( (string) $prompt_json, 'TrialAgent/1.0' ), 'Cloud prompt omits comment author email, IP address, and user agent.' );
toolbox_comment_moderation_trial_assert( false !== strpos( (string) $prompt_json, 'Alice Trial' ) && false !== strpos( (string) $prompt_json, 'Comment Moderation Trial Published Post' ), 'Cloud prompt includes approved-would-be-public fields.' );
toolbox_comment_moderation_trial_assert( false === strpos( (string) $prompt_json, 'Comment Moderation Trial Private Post' ), 'Cloud prompt omits the non-public parent post title.' );

$review_set = is_array( $data['comment_moderation_review_set'] ?? null ) ? $data['comment_moderation_review_set'] : array();
toolbox_comment_moderation_trial_assert( 'comment_moderation_review_set.v1' === (string) ( $review_set['contract_version'] ?? '' ), 'Trial response returns the comment moderation review-set contract.' );
toolbox_comment_moderation_trial_assert( 'ready' === (string) ( $review_set['cloud_status'] ?? '' ), 'Review set reports the Cloud classification as ready.' );
toolbox_comment_moderation_trial_assert( true === (bool) ( $review_set['comment_status_unchanged'] ?? false ), 'Review set declares comment status unchanged.' );
toolbox_comment_moderation_trial_assert( 'suggestion_only' === (string) ( $review_set['write_posture'] ?? '' ), 'Review set stays suggestion-only.' );
toolbox_comment_moderation_trial_assert( false === (bool) ( $review_set['direct_wordpress_write'] ?? true ), 'Review set does not authorize direct WordPress writes.' );
toolbox_comment_moderation_trial_assert( false === (bool) ( $review_set['proposal_created'] ?? true ), 'Review set does not create a proposal.' );

$summary  = is_array( $review_set['eligibility_summary'] ?? null ) ? $review_set['eligibility_summary'] : array();
$selected = is_array( $review_set['selected_items'] ?? null ) ? $review_set['selected_items'] : array();
$blocked  = is_array( $review_set['blocked_items'] ?? null ) ? $review_set['blocked_items'] : array();
$covered_ids = array();
foreach ( $selected as $item ) {
	$covered_ids[] = (int) ( $item['comment_id'] ?? 0 );
}
foreach ( $blocked as $item ) {
	$covered_ids[] = (int) ( $item['comment_id'] ?? 0 );
}
foreach ( $comment_ids as $trial_comment_id ) {
	toolbox_comment_moderation_trial_assert( in_array( $trial_comment_id, $covered_ids, true ), 'Trial comment ' . $trial_comment_id . ' appears in the review set.' );
}
toolbox_comment_moderation_trial_assert( 2 === count( $selected ), 'Exactly the two mocked comments were classified; the duplicate invalid classification entry is ignored.' );
$blocked_trial_ids = array();
foreach ( $blocked as $item ) {
	if ( in_array( (int) ( $item['comment_id'] ?? 0 ), $comment_ids, true ) ) {
		$blocked_trial_ids[] = (int) ( $item['comment_id'] ?? 0 );
		toolbox_comment_moderation_trial_assert( 'classification_missing' === (string) ( $item['blocked_reason'] ?? '' ), 'Blocked trial comment reason is classification_missing, never a guess.' );
	}
}
toolbox_comment_moderation_trial_assert( array( $comment_ids[2] ) === $blocked_trial_ids, 'Only the unclassified trial comment is blocked.' );

foreach ( $selected as $item ) {
	toolbox_comment_moderation_trial_assert( in_array( (string) ( $item['classification'] ?? '' ), array( 'spam', 'legitimate', 'uncertain' ), true ), 'Classification stays inside the allowed triage family.' );
	toolbox_comment_moderation_trial_assert( 'auto_delete_comment' !== (string) ( $item['suggested_action'] ?? '' ), 'Invalid suggested actions never reach the review set.' );
}

foreach ( $comment_ids as $comment_id ) {
	toolbox_comment_moderation_trial_assert( in_array( wp_get_comment_status( $comment_id ), array( 'hold', 'unapproved' ), true ), 'Comment ' . $comment_id . ' status is unchanged after the review.' );
}

remove_all_filters( 'npcink_toolbox_hosted_ai_site_helper_cloud_request' );
$unavailable = toolbox_comment_moderation_trial_rest_request(
	array(
		'intent'              => 'comment_moderation_suggestions',
		'comment_sample_size' => 10,
	)
);
toolbox_comment_moderation_trial_assert( 'cloud_required' === (string) ( $unavailable['status'] ?? '' ), 'Without the Cloud runtime the response fails closed to cloud_required.' );
$unavailable_set = is_array( $unavailable['comment_moderation_review_set'] ?? null ) ? $unavailable['comment_moderation_review_set'] : array();
toolbox_comment_moderation_trial_assert( array() === ( $unavailable_set['selected_items'] ?? null ), 'Fail-closed response contains no fabricated classifications.' );
foreach ( (array) ( $unavailable_set['blocked_items'] ?? array() ) as $blocked_item ) {
	toolbox_comment_moderation_trial_assert( 'cloud_classification_unavailable' === (string) ( $blocked_item['blocked_reason'] ?? '' ), 'Fail-closed blocked reason is cloud_classification_unavailable.' );
}

toolbox_comment_moderation_trial_cleanup_fixtures();
toolbox_comment_moderation_trial_pass( 'Temporary trial comments and posts were removed.' );

echo 'INFO: Comment moderation trial summary=' . wp_json_encode( $summary ) . PHP_EOL;
echo "Comment moderation operator trial passed.\n";
