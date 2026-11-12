<?php
/**
 * Opt-in installed Cloud -> Core -> new Media Library attachment acceptance.
 * Run with NPCINK_PROCESSED_IMAGE_LIVE=1 wp --path=<site> eval-file <this-file>.
 * Uses disposable source/result attachments. Retains Core receipts as evidence.
 */
if ( ! defined( 'ABSPATH' ) || '1' !== getenv( 'NPCINK_PROCESSED_IMAGE_LIVE' ) ) {
	throw new RuntimeException( 'Explicit live image smoke opt-in and WordPress are required.' );
}
$users = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $users[0]->ID );
$call = static function ( string $method, string $route, array $params = array() ): array {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) { $request->set_param( $key, $value ); }
	$response = rest_do_request( $request );
	$data = $response->get_data();
	if ( $response->get_status() >= 300 ) {
		throw new RuntimeException( $route . ': HTTP ' . $response->get_status() . ' ' . ( $data['code'] ?? 'unknown' ) . ' ' . ( $data['message'] ?? '' ) );
	}
	return $data;
};
$check = static function ( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } echo "PASS: $message\n"; };
$source_id = 0;
$created_ids = array();
$path = '';
try {
	$uploads = wp_upload_dir();
	$path = $uploads['path'] . '/toolbox-new-media-smoke-' . wp_generate_uuid4() . '.png';
	$image = imagecreatetruecolor( 640, 360 );
	for ( $y = 0; $y < 360; ++$y ) { imagefilledrectangle( $image, 0, $y, 639, $y, imagecolorallocate( $image, $y % 256, 90, 160 ) ); }
	imagepng( $image, $path );
	$source_id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Disposable save-as-new acceptance', 'post_status' => 'inherit' ), $path, 0, true );
	if ( is_wp_error( $source_id ) ) { throw new RuntimeException( $source_id->get_error_message() ); }
	wp_update_attachment_metadata( $source_id, wp_generate_attachment_metadata( $source_id, $path ) );
	$source_sha = hash_file( 'sha256', $path );
	$source_meta = get_post_meta( $source_id );
	$source_files = array();
	foreach ( glob( dirname( $path ) . '/' . pathinfo( $path, PATHINFO_FILENAME ) . '*' ) as $source_file ) { $source_files[ $source_file ] = hash_file( 'sha256', $source_file ); }
	foreach ( array( 'compression', 'watermark', 'logo' ) as $task ) {
		$input = array( 'attachment_id' => $source_id, 'preferred_format' => 'webp', 'quality' => 82 );
		if ( 'watermark' === $task ) { $input['watermark'] = array( 'type' => 'text', 'text' => 'Source preserved', 'position' => 'bottom_right', 'opacity' => 0.8, 'font_size' => 24, 'color' => '#FFFFFF', 'background' => 'rgba(0,0,0,0.35)', 'margin_px' => 16 ); }
		if ( 'logo' === $task ) { $input['watermark'] = array( 'type' => 'image', 'position' => 'bottom_right', 'opacity' => 0.6, 'scale_percent' => 15, 'margin_px' => 16 ); $input['watermark_attachment_id'] = $source_id; }
		$start = $call( 'POST', '/npcink-toolbox/v1/processed-image-preview', array( 'task' => 'logo' === $task ? 'watermark' : $task, 'input' => $input ) );
		$run_id = $start['run_id'];
		echo "Cloud $task run_id=$run_id\n";
		$finished = false;
		for ( $attempt = 0; $attempt < 40; ++$attempt ) {
			$state = $call( 'GET', '/npcink-toolbox/v1/media-derivative-preview/' . $run_id );
			$status = $state['cloud_run']['status'] ?? '';
			if ( in_array( $status, array( 'failed', 'error' ), true ) ) { throw new RuntimeException( 'Cloud processing failed: ' . $run_id ); }
			if ( in_array( $status, array( 'completed', 'complete', 'succeeded' ), true ) ) { $finished = true; break; }
			usleep( 500000 );
		}
		$check( $finished, "$task Cloud processing completed" );
		$result = $call( 'GET', '/npcink-toolbox/v1/media-derivative-preview/' . $run_id . '/result' );
		$artifact = $result['local_review']['artifact'];
		$check( $artifact['transform_facts']['source_checksum'] === 'sha256:' . $source_sha, 'Live artifact binds the unchanged source' );
		$check( $artifact['transform_facts']['watermark_applied'] === ( 'compression' !== $task ), 'Live result matches the requested task' );
		$before = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1 ) );
		$proposal = $call( 'POST', '/npcink-openclaw-adapter/v1/proposals', array(
			'ability_id' => 'npcink-abilities-toolkit/import-cloud-media-derivative',
			'title' => 'Development acceptance: save processed image as new media',
			'input' => array( 'source_attachment_id' => $source_id, 'expected_source_media_fingerprint' => 'sha256:' . $source_sha, 'derivative_artifact' => $artifact, 'dry_run' => true, 'commit' => false, 'idempotency_key' => 'live-import-' . $artifact['artifact_id'] ),
		) );
		$proposal = $proposal['proposal'] ?? $proposal;
		$proposal_id = $proposal['proposal_id'] ?? '';
		$check( '' !== $proposal_id, 'Core created a save-as-new proposal' );
		$after = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1 ) );
		$check( $before === $after, 'Preview and proposal submission created no attachment' );
		$execution = $call( 'POST', '/npcink-openclaw-adapter/v1/proposals/' . $proposal_id . '/approve-and-execute' );
		// Find only this new artifact's provenance, never rely on response nesting.
		$ids = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'posts_per_page' => 10, 'meta_key' => '_npcink_cloud_import_key', 'meta_value' => hash( 'sha256', $source_id . '|sha256:' . $source_sha . '|' . $artifact['artifact_id'] . '|' . $artifact['sha256'] ) ) );
		foreach ( $ids as $id ) { $created_ids[] = (int) $id; }
		$check( ! empty( $execution['success'] ) && count( $ids ) === 1 && (int) $ids[0] !== $source_id, 'Real approved execution created exactly one distinct attachment' );
		$id = (int) $ids[0];
		$check( hash_file( 'sha256', get_attached_file( $id ) ) === $artifact['sha256'] && wp_get_attachment_metadata( $id )['width'] === $artifact['width'], 'Saved bytes and WordPress metadata match the reviewed Cloud result' );
		$repeat_request = new WP_REST_Request( 'POST', '/npcink-openclaw-adapter/v1/proposals/' . $proposal_id . '/execute' );
		$check( 409 === rest_do_request( $repeat_request )->get_status(), 'Completed proposal rejects repeated execution' );
		$check( get_post_meta( $source_id ) === $source_meta && hash_file( 'sha256', $path ) === $source_sha, 'Real save preserved source metadata and bytes' );
		foreach ( $source_files as $file => $sha ) { $check( hash_file( 'sha256', $file ) === $sha, 'Original and existing thumbnail files remain unchanged' ); }
		echo wp_json_encode( array( 'task' => $task, 'run_id' => $run_id, 'proposal_id' => $proposal_id, 'source_attachment_id' => $source_id, 'new_attachment_id' => $id, 'source_bytes' => filesize( $path ), 'new_bytes' => filesize( get_attached_file( $id ) ) ) ) . "\n";
	}
} finally {
	foreach ( array_unique( $created_ids ) as $id ) {
		$dir = dirname( (string) get_attached_file( $id ) );
		wp_delete_attachment( $id, true );
		if ( str_contains( basename( $dir ), 'npcink-processed-' ) && is_dir( $dir ) && count( scandir( $dir ) ) === 2 ) { rmdir( $dir ); }
	}
	if ( $source_id > 0 ) { wp_delete_attachment( $source_id, true ); } elseif ( $path && is_file( $path ) ) { wp_delete_file( $path ); }
}
