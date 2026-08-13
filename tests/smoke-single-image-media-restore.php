<?php
/**
 * Real WordPress smoke for the local single-image replace/list/restore lane.
 *
 * Run with WP-CLI:
 * wp eval-file tests/smoke-single-image-media-restore.php
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "FAIL: Run this script through WP-CLI eval-file.\n" );
	exit( 1 );
}

$attachment_id = 0;
$reference_post_id = 0;
$owned_paths = array();

$cleanup = static function () use ( &$attachment_id, &$reference_post_id, &$owned_paths ): void {
	if ( $attachment_id > 0 ) {
		$history = get_post_meta( $attachment_id, '_npcink_ai_media_file_replacement_history', true );
		foreach ( (array) $history as $record ) {
			foreach ( array( 'before', 'after', 'backup' ) as $field ) {
				$relative = is_array( $record[ $field ] ?? null ) ? (string) ( $record[ $field ]['relative_file'] ?? '' ) : '';
				if ( '' !== $relative ) {
					$upload = wp_upload_dir();
					$owned_paths[] = trailingslashit( (string) $upload['basedir'] ) . ltrim( $relative, '/' );
				}
			}
		}
		wp_delete_attachment( $attachment_id, true );
	}
	if ( $reference_post_id > 0 ) {
		wp_delete_post( $reference_post_id, true );
	}
	foreach ( array_unique( $owned_paths ) as $path ) {
		if ( is_string( $path ) && is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}
};

$fail = static function ( string $message ) use ( $cleanup ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	$cleanup();
	exit( 1 );
};
$assert = static function ( bool $condition, string $message ) use ( $fail ): void {
	if ( ! $condition ) {
		$fail( $message );
	}
	echo "PASS: {$message}\n";
};
$ability = static function ( string $ability_id, array $input ) use ( $fail ) {
	$registered = function_exists( 'npcink_abilities_toolkit_get_registered' ) ? npcink_abilities_toolkit_get_registered() : array();
	$callback = is_array( $registered[ $ability_id ] ?? null ) ? ( $registered[ $ability_id ]['execute_callback'] ?? null ) : null;
	if ( ! is_callable( $callback ) ) {
		$fail( "Toolkit ability {$ability_id} is unavailable." );
	}
	return call_user_func( $callback, $input );
};
$rest = static function ( string $method, string $route, array $params = array() ): array {
	$request = new WP_REST_Request( $method, $route );
	if ( 'POST' === $method ) {
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
	} else {
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
	}
	$response = rest_do_request( $request );
	return array( 'status' => $response->get_status(), 'data' => $response->get_data() );
};

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
$subscribers = get_users( array( 'role' => 'subscriber', 'number' => 1, 'fields' => 'ids' ) );
$admin_id = absint( $admins[0] ?? 0 );
$subscriber_id = absint( $subscribers[0] ?? 0 );
$assert( $admin_id > 0 && $subscriber_id > 0, 'Administrator and subscriber fixtures are available.' );
$assert( function_exists( 'imagecreatetruecolor' ), 'GD is available for real image fixtures.' );

wp_set_current_user( $admin_id );
$upload = wp_upload_dir();
$dir = trailingslashit( (string) $upload['path'] );
$url = trailingslashit( (string) $upload['url'] );
$stamp = gmdate( 'YmdHis' ) . '-' . substr( md5( (string) microtime( true ) ), 0, 8 );
$original_path = $dir . "toolbox-local-restore-{$stamp}.png";
$derivative_path = $dir . "toolbox-local-restore-{$stamp}-optimized.png";
$owned_paths[] = $original_path;
$owned_paths[] = $derivative_path;

foreach ( array( array( $original_path, 120, 72, 25, 90, 150 ), array( $derivative_path, 80, 48, 30, 145, 90 ) ) as $fixture ) {
	$image = imagecreatetruecolor( $fixture[1], $fixture[2] );
	$assert( false !== $image, 'Image fixture canvas is created.' );
	$color = imagecolorallocate( $image, $fixture[3], $fixture[4], $fixture[5] );
	imagefilledrectangle( $image, 0, 0, $fixture[1], $fixture[2], $color );
	$assert( imagepng( $image, $fixture[0] ) && is_readable( $fixture[0] ), 'Image fixture bytes are written.' );
	imagedestroy( $image );
}

if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
}
$attachment_id = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'Toolbox local restore smoke',
		'post_status'    => 'inherit',
		'guid'           => $url . basename( $original_path ),
	),
	$original_path
);
$assert( ! is_wp_error( $attachment_id ) && $attachment_id > 0, 'Real WordPress image attachment is inserted.' );
$metadata = wp_generate_attachment_metadata( $attachment_id, $original_path );
wp_update_attachment_metadata( $attachment_id, is_array( $metadata ) ? $metadata : array() );
$original_relative = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
$derivative_relative = ltrim( str_replace( trailingslashit( (string) $upload['basedir'] ), '', $derivative_path ), '/' );
$original_url = wp_get_attachment_url( $attachment_id );
$derivative_url = trailingslashit( (string) $upload['baseurl'] ) . $derivative_relative;

update_post_meta(
	$attachment_id,
	'_npcink_ai_media_optimized_derivatives',
	array(
		array(
			'format' => 'png', 'mime_type' => 'image/png', 'file_basename' => basename( $derivative_path ),
			'relative_file' => $derivative_relative, 'url' => $derivative_url, 'width' => 80, 'height' => 48,
			'quality' => 100, 'filesize_bytes' => filesize( $derivative_path ), 'generated_at_gmt' => gmdate( 'c' ),
		),
	)
);
$reference_post_id = wp_insert_post(
	array(
		'post_title' => 'Toolbox local restore reference smoke',
		'post_status' => 'draft',
		'post_type' => 'post',
		'post_content' => '<!-- wp:image --><figure class="wp-block-image"><img src="' . esc_url( $original_url ) . '" /></figure><!-- /wp:image -->',
	),
	true
);
$assert( ! is_wp_error( $reference_post_id ) && $reference_post_id > 0, 'Referencing post fixture is inserted.' );

$replace_input = array(
	'attachment_id' => $attachment_id,
	'derivative_relative_file' => $derivative_relative,
	'expected_current_relative_file' => $original_relative,
	'expected_current_mime_type' => 'image/png',
	'expected_derivative_mime_type' => 'image/png',
);
$replace_preview = $ability( 'npcink-abilities-toolkit/replace-media-file', $replace_input );
$assert( is_array( $replace_preview ) && ! empty( $replace_preview['dry_run'] ), 'Toolkit replace-media-file dry-run succeeds on real WordPress state.' );
$authorize_replace = static fn( bool $allowed, string $ability_id ): bool => 'npcink-abilities-toolkit/replace-media-file' === $ability_id ? true : $allowed;
add_filter( 'npcink_abilities_toolkit_write_commit_allowed', $authorize_replace, 10, 2 );
$replace_input['dry_run'] = false;
$replace_input['commit'] = true;
$replace = $ability( 'npcink-abilities-toolkit/replace-media-file', $replace_input );
remove_filter( 'npcink_abilities_toolkit_write_commit_allowed', $authorize_replace, 10 );
$assert( is_array( $replace ) && ! empty( $replace['replaced'] ), 'Toolkit replace-media-file commit succeeds on real WordPress state.' );
$assert( $derivative_relative === (string) get_post_meta( $attachment_id, '_wp_attached_file', true ), 'Real attachment pointer changes to the derivative.' );
$assert( false !== strpos( (string) get_post_field( 'post_content', $reference_post_id ), $derivative_url ), 'Real post content reference changes to the derivative URL.' );
$replacement_id = (string) ( $replace['replacement_id'] ?? '' );
$assert( '' !== $replacement_id && is_readable( trailingslashit( (string) $upload['basedir'] ) . (string) ( $replace['backup']['relative_file'] ?? '' ) ), 'Replacement exposes a readable rollback backup.' );

$listed = $ability( 'npcink-abilities-toolkit/list-media-backups', array( 'attachment_id' => $attachment_id ) );
$listed_data = is_array( $listed['data'] ?? null ) ? $listed['data'] : $listed;
$listed_ids = array_column( (array) ( $listed_data['backups'] ?? array() ), 'backup_id' );
$assert( in_array( $replacement_id, $listed_ids, true ), 'Toolkit list-media-backups returns the committed replacement backup.' );

wp_set_current_user( $subscriber_id );
$denied = $rest( 'GET', '/npcink-toolbox/v1/strong-local-confirmation/media-derivative-backups/' . $attachment_id, array( 'attachment_id' => $attachment_id ) );
$assert( 403 === (int) $denied['status'], 'Toolbox backup route denies a subscriber.' );

wp_set_current_user( $admin_id );
$toolbox_list = $rest( 'GET', '/npcink-toolbox/v1/strong-local-confirmation/media-derivative-backups/' . $attachment_id, array( 'attachment_id' => $attachment_id ) );
$assert( 200 === (int) $toolbox_list['status'], 'Toolbox administrator backup route succeeds.' );
$toolbox_ids = array_column( (array) ( $toolbox_list['data']['backups'] ?? array() ), 'backup_id' );
$assert( in_array( $replacement_id, $toolbox_ids, true ), 'Toolbox projects the Toolkit backup id.' );

$restore = $rest(
	'POST',
	'/npcink-toolbox/v1/strong-local-confirmation/media-derivative-restore',
	array(
		'attachment_id' => $attachment_id,
		'backup_id' => $replacement_id,
		'confirmed_backup_id' => $replacement_id,
		'preview_verified' => true,
		'confirm_restore' => true,
	)
);
$assert( 200 === (int) $restore['status'], 'Toolbox confirmed restore route succeeds.' );
$assert( ! empty( $restore['data']['restore']['restored'] ) && ! empty( $restore['data']['restore']['rolled_back'] ), 'Toolbox returns verified restored and rolled_back evidence.' );
$assert( $original_relative === (string) get_post_meta( $attachment_id, '_wp_attached_file', true ), 'Restore returns the real attachment pointer to the original.' );
$assert( false !== strpos( (string) get_post_field( 'post_content', $reference_post_id ), (string) $original_url ), 'Restore returns the real post content reference to the original URL.' );
$assert( is_readable( trailingslashit( (string) $upload['basedir'] ) . (string) ( $restore['data']['restore']['current_backup']['relative_file'] ?? '' ) ), 'Restore retains a readable backup of the replaced current file.' );

$cleanup();
echo "Single-image local media restore smoke passed.\n";
