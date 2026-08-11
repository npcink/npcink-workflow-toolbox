<?php
/**
 * Strong local confirmation smoke for one reviewed remote image.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-single-article-image-adoption.php
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

function toolbox_single_image_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}

	echo "PASS: {$message}\n";
}

$admin_ids = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
$admin_id = absint( $admin_ids[0] ?? 0 );
toolbox_single_image_smoke_assert( $admin_id > 0, 'A local administrator is available.' );
wp_set_current_user( $admin_id );

$post_id = wp_insert_post(
	array(
		'post_title'  => 'Npcink single image adoption smoke',
		'post_status' => 'draft',
		'post_type'   => 'post',
	)
);
$post_id = absint( $post_id );
toolbox_single_image_smoke_assert( $post_id > 0, 'A temporary draft article was created.' );

$image_bytes = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true );
toolbox_single_image_smoke_assert( is_string( $image_bytes ), 'The deterministic PNG fixture decoded.' );

$source_url = 'https://example.com/npcink-smoke-reviewed-image.png';
$http_mock = static function ( $preempt, array $parsed_args, string $url ) use ( $source_url, $image_bytes ) {
	if ( $source_url !== $url ) {
		return $preempt;
	}

	$filename = (string) ( $parsed_args['filename'] ?? '' );
	if ( '' === $filename || false === file_put_contents( $filename, $image_bytes ) ) {
		return new WP_Error( 'npcink_toolbox_single_image_smoke_stream_failed', 'Could not write the mocked streamed image.' );
	}

	return array(
		'headers'  => array( 'content-type' => 'image/png' ),
		'body'     => '',
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => $filename,
	);
};
add_filter( 'pre_http_request', $http_mock, 10, 3 );

$unconfirmed = new WP_REST_Request( 'POST', '/npcink-toolbox/v1/strong-local-confirmation/image-adoption' );
$unconfirmed->set_body_params(
	array(
		'action'           => 'import_only',
		'confirmed_action' => '',
		'post_id'          => $post_id,
		'candidate'        => array( 'download_url' => $source_url ),
	)
);
$unconfirmed_response = rest_do_request( $unconfirmed );
toolbox_single_image_smoke_assert( 409 === absint( $unconfirmed_response->get_status() ), 'A missing action-bound confirmation fails closed before download.' );

$request = new WP_REST_Request( 'POST', '/npcink-toolbox/v1/strong-local-confirmation/image-adoption' );
$request->set_body_params(
	array(
		'action'           => 'import_and_set_featured',
		'confirmed_action' => 'import_and_set_featured',
		'post_id'          => $post_id,
		'candidate'        => array(
			'download_url'         => $source_url,
			'source_url'           => 'https://example.com/npcink-smoke-source-page',
			'provider'             => 'smoke_provider',
			'source_type'          => 'ai_generated',
			'license_review_status' => 'reviewed',
			'attribution'          => 'Npcink smoke fixture',
			'suggested_filename'   => 'reviewed-smoke-image.png',
			'title'                => 'Reviewed smoke image',
			'alt'                  => 'Reviewed one pixel smoke image',
			'caption'              => 'Smoke caption',
			'description'          => 'Smoke description',
		),
	)
);

$response = rest_do_request( $request );
$status   = absint( $response->get_status() );
$data     = $response->get_data();
toolbox_single_image_smoke_assert( 200 === $status, 'The strong-local-confirmation image adoption route succeeds.' );
toolbox_single_image_smoke_assert( is_array( $data ) && 'single_article_image_adoption_result.v1' === (string) ( $data['artifact_type'] ?? '' ), 'The route returns the v1 single-article adoption result.' );
toolbox_single_image_smoke_assert( 'strong_local_confirmation' === (string) ( $data['classification']['classification'] ?? '' ), 'Media import is classified as strong local confirmation.' );
toolbox_single_image_smoke_assert( false === (bool) ( $data['proposal_created'] ?? true ) && false === (bool) ( $data['core_proposal_required'] ?? true ), 'The bounded local action creates no Core proposal.' );

$attachment_id = absint( $data['attachment_id'] ?? 0 );
toolbox_single_image_smoke_assert( $attachment_id > 0 && wp_attachment_is_image( $attachment_id ), 'One verified image attachment was created.' );
toolbox_single_image_smoke_assert( $attachment_id === absint( get_post_thumbnail_id( $post_id ) ), 'The new attachment became the temporary article featured image.' );
toolbox_single_image_smoke_assert( 'Reviewed one pixel smoke image' === (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ), 'The reviewed ALT value was stored on the new attachment.' );
toolbox_single_image_smoke_assert( 'smoke_provider' === (string) get_post_meta( $attachment_id, '_npcink_image_provider', true ), 'The bounded provider evidence was stored locally.' );
toolbox_single_image_smoke_assert( 'https://example.com/npcink-smoke-source-page' === (string) get_post_meta( $attachment_id, '_npcink_image_source_url', true ), 'The reviewed source evidence was stored locally.' );

$existing_request = new WP_REST_Request( 'POST', '/npcink-toolbox/v1/strong-local-confirmation/image-adoption' );
$existing_request->set_body_params(
	array(
		'action'           => 'set_featured_existing',
		'confirmed_action' => 'set_featured_existing',
		'post_id'          => $post_id,
		'attachment_id'    => $attachment_id,
	)
);
$existing_response = rest_do_request( $existing_request );
$existing_data     = $existing_response->get_data();
toolbox_single_image_smoke_assert( 200 === absint( $existing_response->get_status() ), 'Reconfirming the current Media Library featured image is idempotent.' );
toolbox_single_image_smoke_assert( 'strong_local_confirmation' === (string) ( $existing_data['classification']['classification'] ?? '' ), 'Existing-image adoption remains in the ADR-010 strong confirmation lane.' );

$rollback_attachment_id = 0;
$capture_attachment     = static function ( int $created_attachment_id ) use ( &$rollback_attachment_id, $attachment_id ): void {
	if ( $created_attachment_id !== $attachment_id ) {
		$rollback_attachment_id = $created_attachment_id;
	}
};
$block_new_featured_write = static function ( $check, int $object_id, string $meta_key, $meta_value ) use ( $post_id, $attachment_id ) {
	if ( $post_id === $object_id && '_thumbnail_id' === $meta_key && $attachment_id !== absint( $meta_value ) ) {
		return false;
	}

	return $check;
};
add_action( 'add_attachment', $capture_attachment, 10, 1 );
add_filter( 'update_post_metadata', $block_new_featured_write, 10, 4 );

$rollback_request = new WP_REST_Request( 'POST', '/npcink-toolbox/v1/strong-local-confirmation/image-adoption' );
$rollback_request->set_body_params(
	array(
		'action'           => 'import_and_set_featured',
		'confirmed_action' => 'import_and_set_featured',
		'post_id'          => $post_id,
		'candidate'        => array(
			'download_url'       => $source_url,
			'suggested_filename' => 'rollback-smoke-image.png',
		),
	)
);
$rollback_response = rest_do_request( $rollback_request );
$rollback_data     = $rollback_response->get_data();

remove_filter( 'update_post_metadata', $block_new_featured_write, 10 );
remove_action( 'add_attachment', $capture_attachment, 10 );

toolbox_single_image_smoke_assert( 500 === absint( $rollback_response->get_status() ), 'A failed combined featured-image write returns an explicit failure.' );
toolbox_single_image_smoke_assert( 'completed' === (string) ( $rollback_data['data']['rollback_status'] ?? '' ), 'The failed combined action reports completed compensation.' );
toolbox_single_image_smoke_assert( $rollback_attachment_id > 0 && ! get_post( $rollback_attachment_id ), 'The failed combined action deletes its newly created attachment.' );
toolbox_single_image_smoke_assert( $attachment_id === absint( get_post_thumbnail_id( $post_id ) ), 'The failed combined action preserves the previous featured image.' );

remove_filter( 'pre_http_request', $http_mock, 10 );
wp_delete_attachment( $attachment_id, true );
wp_delete_post( $post_id, true );

toolbox_single_image_smoke_assert( ! get_post( $attachment_id ) && ! get_post( $post_id ), 'The smoke cleaned up its attachment and article fixtures.' );
echo "Single-article strong local image adoption smoke passed.\n";
