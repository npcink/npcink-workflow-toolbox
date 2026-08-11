<?php
/**
 * No-credit WordPress smoke for Cloud artifact preview and local adoption.
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file so WordPress is loaded.\n" );
	exit( 1 );
}

function toolbox_ai_artifact_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

$admin_ids = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
$admin_id  = absint( $admin_ids[0] ?? 0 );
toolbox_ai_artifact_smoke_assert( $admin_id > 0, 'A local administrator is available.' );
wp_set_current_user( $admin_id );

$post_id = absint(
	wp_insert_post(
		array(
			'post_title'  => 'Npcink AI artifact adoption smoke',
			'post_status' => 'draft',
			'post_type'   => 'post',
		)
	)
);
toolbox_ai_artifact_smoke_assert( $post_id > 0, 'A temporary draft article was created.' );

$image_bytes = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true );
toolbox_ai_artifact_smoke_assert( is_string( $image_bytes ), 'The deterministic generated-image fixture decoded.' );

$artifact_id  = 'art_' . str_repeat( 'a', 32 );
$checksum     = 'sha256:' . hash( 'sha256', $image_bytes );
$expires_at   = gmdate( 'Y-m-d\TH:i:s\Z', time() + 1800 );
$artifact     = array(
	'artifact_id'        => $artifact_id,
	'artifact_reference' => array( 'artifact_id' => $artifact_id ),
	'status'             => 'available',
	'media_kind'         => 'image',
	'operation'          => 'image.generate.v1',
	'content_type'       => 'image/png',
	'format'             => 'png',
	'width'              => 1,
	'height'             => 1,
	'filesize_bytes'     => strlen( $image_bytes ),
	'checksum'           => $checksum,
	'expires_at'         => $expires_at,
	'purged_at'          => null,
);

$fake_client = new class( $artifact_id, $checksum, $expires_at, $image_bytes ) {
	public int $pull_count = 0;
	public int $ack_count  = 0;
	private string $artifact_id;
	private string $checksum;
	private string $expires_at;
	private string $image_bytes;

	public function __construct( string $artifact_id, string $checksum, string $expires_at, string $image_bytes ) {
		$this->artifact_id = $artifact_id;
		$this->checksum    = $checksum;
		$this->expires_at  = $expires_at;
		$this->image_bytes = $image_bytes;
	}

	public function pull_media_artifact( string $artifact_id, string $trace_id = '' ): array {
		++$this->pull_count;
		$delivery_id = 'mdl_' . str_pad( dechex( $this->pull_count ), 32, '0', STR_PAD_LEFT );

		return array(
			'body'                  => $this->image_bytes,
			'content_type'          => 'image/png',
			'content_length'        => strlen( $this->image_bytes ),
			'artifact_id'           => $artifact_id,
			'artifact_checksum'     => $this->checksum,
			'delivery_id'           => $delivery_id,
			'delivery_ack_deadline' => gmdate( 'Y-m-d\TH:i:s\Z', time() + 600 ),
		);
	}

	public function acknowledge_media_artifact_delivery( string $artifact_id, array $payload, string $trace_id = '' ): array {
		++$this->ack_count;

		return array(
			'artifact_id'          => $artifact_id,
			'delivery_id'          => (string) $payload['delivery_id'],
			'received_byte_size'   => (int) $payload['received_byte_size'],
			'received_checksum'    => (string) $payload['received_checksum'],
			'byte_size_verified'   => true,
			'checksum_verified'    => true,
			'acknowledged_at'      => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'artifact_expires_at'  => $this->expires_at,
		);
	}
};

$client_filter = static function () use ( $fake_client ) {
	return $fake_client;
};
$runtime_filter = static function () use ( $artifact ) {
	return array(
		'status'   => 'ready',
		'run_id'   => 'run_toolbox_artifact_smoke',
		'trace_id' => 'trace_toolbox_artifact_smoke',
		'data'     => array(
			'result' => array(
				'contract_version'      => 'image_generation_result.v1',
				'artifact_type'         => 'image_generation_artifacts',
				'operation'             => 'image.generate.v1',
				'artifacts'             => array( $artifact ),
				'suggestion_only'       => true,
				'requires_local_review' => true,
			),
		),
	);
};
add_filter( 'npcink_toolbox_cloud_image_artifact_client', $client_filter, 10, 2 );
add_filter( 'npcink_toolbox_ai_image_generation_cloud_request', $runtime_filter, 10, 3 );

$generation_request = new WP_REST_Request( 'POST', '/npcink-toolbox/v1/ai/image-generation' );
$generation_request->set_body_params(
	array(
		'prompt'                      => 'Create one reviewed editorial image.',
		'n'                           => 1,
		'prompt_reviewed_by_operator' => true,
		'media_context'               => array( 'title' => 'Reviewed artifact image' ),
	)
);
$generation_response = rest_do_request( $generation_request );
$generation_data     = $generation_response->get_data();
$candidate           = is_array( $generation_data['images'][0] ?? null ) ? $generation_data['images'][0] : array();

toolbox_ai_artifact_smoke_assert( 200 === absint( $generation_response->get_status() ), 'Artifact-backed AI image generation returns a reviewable candidate.' );
toolbox_ai_artifact_smoke_assert( str_starts_with( (string) ( $candidate['preview_url'] ?? '' ), 'data:image/png;base64,' ), 'The browser candidate contains a verified request-scoped preview.' );
toolbox_ai_artifact_smoke_assert( $artifact_id === (string) ( $candidate['cloud_artifact']['artifact_id'] ?? '' ), 'The candidate preserves the canonical Cloud artifact reference.' );
toolbox_ai_artifact_smoke_assert( 1 === $fake_client->pull_count && 1 === $fake_client->ack_count, 'Preview performs one verified signed pull and ACK.' );

foreach ( array( 'preview_url', 'regular_url', 'small_url', 'thumbnail_url', 'thumb_url', 'download_url', 'url' ) as $preview_key ) {
	unset( $candidate[ $preview_key ] );
}
$tampered_candidate = $candidate;
$tampered_candidate['cloud_artifact']['checksum'] = 'sha256:' . str_repeat( '0', 64 );
$tampered_request = new WP_REST_Request( 'POST', '/npcink-toolbox/v1/strong-local-confirmation/image-adoption' );
$tampered_request->set_body_params(
	array(
		'action'           => 'import_only',
		'confirmed_action' => 'import_only',
		'post_id'          => $post_id,
		'candidate'        => $tampered_candidate,
	)
);
$tampered_response = rest_do_request( $tampered_request );
toolbox_ai_artifact_smoke_assert( 422 === absint( $tampered_response->get_status() ), 'A tampered artifact contract fails closed.' );
toolbox_ai_artifact_smoke_assert( 2 === $fake_client->pull_count && 1 === $fake_client->ack_count, 'Tampered bytes are rejected before delivery ACK or WordPress import.' );

$adoption_request = new WP_REST_Request( 'POST', '/npcink-toolbox/v1/strong-local-confirmation/image-adoption' );
$adoption_request->set_body_params(
	array(
		'action'           => 'import_and_set_featured',
		'confirmed_action' => 'import_and_set_featured',
		'post_id'          => $post_id,
		'candidate'        => $candidate,
	)
);
$adoption_response = rest_do_request( $adoption_request );
$adoption_data     = $adoption_response->get_data();
$attachment_id    = absint( $adoption_data['attachment_id'] ?? 0 );

toolbox_ai_artifact_smoke_assert( 200 === absint( $adoption_response->get_status() ), 'The reviewed Cloud artifact imports through strong local confirmation.' );
toolbox_ai_artifact_smoke_assert( $attachment_id > 0 && wp_attachment_is_image( $attachment_id ), 'Artifact adoption creates one verified image attachment.' );
toolbox_ai_artifact_smoke_assert( $attachment_id === absint( get_post_thumbnail_id( $post_id ) ), 'Artifact adoption sets the current article featured image.' );
toolbox_ai_artifact_smoke_assert( $artifact_id === (string) get_post_meta( $attachment_id, '_npcink_cloud_source_artifact_id', true ), 'The attachment records bounded Cloud artifact provenance.' );
toolbox_ai_artifact_smoke_assert( 3 === $fake_client->pull_count && 2 === $fake_client->ack_count, 'Final adoption independently repeats verified signed pull and ACK.' );

remove_filter( 'npcink_toolbox_ai_image_generation_cloud_request', $runtime_filter, 10 );
remove_filter( 'npcink_toolbox_cloud_image_artifact_client', $client_filter, 10 );
wp_delete_attachment( $attachment_id, true );
wp_delete_post( $post_id, true );

toolbox_ai_artifact_smoke_assert( ! get_post( $attachment_id ) && ! get_post( $post_id ), 'The smoke cleaned up its WordPress fixtures.' );
echo "AI image artifact adoption smoke passed.\n";
