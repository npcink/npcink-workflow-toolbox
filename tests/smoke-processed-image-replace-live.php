<?php
/**
 * Opt-in disposable Cloud -> Core -> replace -> restore acceptance.
 * NPCINK_PROCESSED_IMAGE_REPLACE_LIVE=1 wp --path=<site> eval-file <this-file>
 * Only test-owned attachments, draft and files are removed; Core receipts remain.
 */
if ( ! defined( 'ABSPATH' ) || '1' !== getenv( 'NPCINK_PROCESSED_IMAGE_REPLACE_LIVE' ) ) {
	throw new RuntimeException( 'Explicit replacement smoke opt-in and WordPress are required.' );
}
$users = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
if ( ! $users ) { throw new RuntimeException( 'Test administrator is unavailable.' ); }
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
$ids = static function () { return get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ); };
$propose = static function ( $ability, $input ) use ( $call, $check ) {
	$data = $call( 'POST', '/npcink-openclaw-adapter/v1/proposals', array( 'ability_id' => 'npcink-abilities-toolkit/' . $ability, 'title' => 'Disposable replacement acceptance: ' . $ability, 'input' => $input ) );
	$proposal = $data['proposal'] ?? $data;
	$id = $proposal['proposal_id'] ?? '';
	$check( '' !== $id, 'Core created a ' . $ability . ' proposal' );
	return $id;
};
$source_id = 0;
$post_id = 0;
$owned_dirs = array();
try {
	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) { throw new RuntimeException( $uploads['error'] ); }
	$relative = 'npcink-replace-smoke-' . wp_generate_uuid4();
	$dir = $uploads['basedir'] . '/' . $relative;
	$owned_dirs[] = $dir;
	$owned_dirs[] = $uploads['basedir'] . '/npcink-abilities-toolkit-backups/' . $relative;
	$check( wp_mkdir_p( $dir ), 'Created a unique test-owned uploads directory' );
	$path = $dir . '/source.png';
	$image = imagecreatetruecolor( 640, 360 );
	for ( $y = 0; $y < 360; ++$y ) { imagefilledrectangle( $image, 0, $y, 639, $y, imagecolorallocate( $image, $y % 256, 90, 160 ) ); }
	$check( imagepng( $image, $path ), 'Created disposable source bytes' );
	$source_id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Disposable replacement acceptance', 'post_status' => 'inherit' ), $path, 0, true );
	if ( is_wp_error( $source_id ) ) { $error = $source_id; $source_id = 0; throw new RuntimeException( $error->get_error_message() ); }
	wp_update_attachment_metadata( $source_id, wp_generate_attachment_metadata( $source_id, $path ) );
	$source_sha = hash_file( 'sha256', $path );
	$original_metadata = wp_get_attachment_metadata( $source_id );
	$source_url = wp_get_attachment_url( $source_id );
	$thumbnail = wp_get_attachment_image_url( $source_id, 'thumbnail' );
	$original_content = '<!-- wp:image {"id":' . $source_id . '} --><figure class="wp-block-image"><img src="' . esc_url( $source_url ) . '" class="wp-image-' . $source_id . '"/></figure><!-- /wp:image --><p><img src="' . esc_url( $thumbnail ) . '"/></p>';
	$post_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Disposable replacement reference acceptance', 'post_content' => $original_content ), true );
	if ( is_wp_error( $post_id ) ) { $error = $post_id; $post_id = 0; throw new RuntimeException( $error->get_error_message() ); }
	$before_ids = $ids();
	$start = $call( 'POST', '/npcink-toolbox/v1/processed-image-preview', array( 'task' => 'compression', 'input' => array( 'attachment_id' => $source_id, 'preferred_format' => 'webp', 'quality' => 82 ) ) );
	$run_id = $start['run_id'];
	echo "Cloud replacement run_id=$run_id\n";
	$finished = false;
	for ( $attempt = 0; $attempt < 40; ++$attempt ) {
		$state = $call( 'GET', '/npcink-toolbox/v1/media-derivative-preview/' . $run_id );
		$status = $state['cloud_run']['status'] ?? '';
		if ( in_array( $status, array( 'failed', 'error' ), true ) ) { throw new RuntimeException( 'Cloud processing failed: ' . $run_id ); }
		if ( in_array( $status, array( 'completed', 'complete', 'succeeded' ), true ) ) { $finished = true; break; }
		usleep( 500000 );
	}
	$check( $finished, 'Live compression preview completed' );
	$result = $call( 'GET', '/npcink-toolbox/v1/media-derivative-preview/' . $run_id . '/result' );
	$artifact = $result['local_review']['artifact'];
	$input = array( 'attachment_id' => $source_id, 'expected_current_media_fingerprint' => 'sha256:' . $source_sha, 'backup_lifecycle' => 'temporary_7_days.v1', 'derivative_artifact' => $artifact, 'dry_run' => true, 'commit' => false, 'idempotency_key' => 'live-replace-' . $artifact['artifact_id'] );
	$check( $artifact['transform_facts']['source_checksum'] === $input['expected_current_media_fingerprint'], 'Reviewed artifact is bound to source bytes' );
	$wrong = $input;
	$wrong['expected_current_media_fingerprint'] = 'sha256:' . str_repeat( '0', 64 );
	$rejected = wp_get_ability( 'npcink-abilities-toolkit/adopt-cloud-media-derivative' )->execute( $wrong );
	$check( is_wp_error( $rejected ) && 409 === ( $rejected->get_error_data()['status'] ?? 0 ), 'Changed source fingerprint is rejected before adoption' );
	$proposal_id = $propose( 'adopt-cloud-media-derivative', $input );
	$check( $before_ids === $ids() && get_attached_file( $source_id ) === $path && hash_file( 'sha256', $path ) === $source_sha && get_post_field( 'post_content', $post_id ) === $original_content, 'Preview and replacement proposal perform no media or article writes' );
	$execution = $call( 'POST', '/npcink-openclaw-adapter/v1/proposals/' . $proposal_id . '/approve-and-execute' );
	$new_path = get_attached_file( $source_id );
	$check( ! empty( $execution['success'] ) && $before_ids === $ids() && $new_path !== $path && hash_file( 'sha256', $new_path ) === $artifact['sha256'], 'Approved replacement keeps attachment ID and adopts exact reviewed bytes' );
	$check( get_post_mime_type( $source_id ) === 'image/webp' && wp_get_attachment_metadata( $source_id )['width'] === $artifact['width'], 'Replacement updates MIME and WordPress image metadata' );
	$content = get_post_field( 'post_content', $post_id );
	$check( ! str_contains( $content, $source_url ) && ! str_contains( $content, $thumbnail ) && str_contains( $content, wp_get_attachment_url( $source_id ) ), 'Recognized main and thumbnail article URLs are repaired' );
	$history = get_post_meta( $source_id, '_npcink_ai_media_file_replacement_history', true );
	$record = end( $history );
	$backup_path = $uploads['basedir'] . '/' . $record['backup']['relative_file'];
	$check( $backup_path === $path && is_file( $backup_path ) && hash_file( 'sha256', $backup_path ) === $source_sha && count( $record['temporary_backup']['files'] ) > 1, 'One temporary group retains original and sizes without a duplicate original copy' );
	$check( abs( strtotime( $record['temporary_backup']['expires_at_gmt'] ) - time() - 7 * DAY_IN_SECONDS ) < 60, 'New replacement freezes a seven-day recovery window' );
	$restore_id = $propose( 'restore-media-backup', array( 'attachment_id' => $source_id, 'backup_id' => $record['replacement_id'], 'dry_run' => true, 'commit' => false ) );
	$restored = $call( 'POST', '/npcink-openclaw-adapter/v1/proposals/' . $restore_id . '/approve-and-execute' );
	$check( ! empty( $restored['success'] ) && get_attached_file( $source_id ) === $path && wp_get_attachment_url( $source_id ) === $source_url && hash_file( 'sha256', $path ) === $source_sha && get_post_mime_type( $source_id ) === 'image/png', 'Approved backup restore recovers original bytes, URL and MIME on the same attachment' );
	// Existing adoption consolidates sized URLs to the main URL; restore does not
	// retain per-occurrence size history. Verify recovery, not a byte-exact post undo.
	$restored_content = str_replace( $thumbnail, $source_url, $original_content );
	$check( get_post_field( 'post_content', $post_id ) === $restored_content && $ids() === $before_ids, 'Restore repairs recognized article URLs to the original main image without new attachments' );
	$check( wp_get_attachment_metadata( $source_id ) === $original_metadata, 'Temporary backup restore recovers exact original metadata without regenerating old thumbnails' );
	echo wp_json_encode( array( 'run_id' => $run_id, 'replacement_proposal_id' => $proposal_id, 'restore_proposal_id' => $restore_id, 'attachment_id' => $source_id, 'post_id' => $post_id ) ) . "\n";
} finally {
	if ( $post_id > 0 ) { wp_delete_post( $post_id, true ); }
	if ( $source_id > 0 ) { wp_delete_attachment( $source_id, true ); }
	// Each directory was created solely by this run; never scan shared uploads/backups.
	foreach ( array_reverse( $owned_dirs ) as $owned_dir ) {
		if ( ! is_dir( $owned_dir ) ) { continue; }
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $owned_dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $files as $file ) { if ( $file->isDir() ) { rmdir( $file->getPathname() ); } else { wp_delete_file( $file->getPathname() ); } }
		rmdir( $owned_dir );
	}
}
