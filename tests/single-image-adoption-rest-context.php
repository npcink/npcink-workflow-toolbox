<?php
/**
 * Verifies image adoption in a REST-like PHP process without wp_tempnam().
 *
 * Unlike the WordPress smoke tests, this process does not run through WP-CLI,
 * so the admin file helper is not preloaded before the service is invoked.
 *
 * @package Npcink_Toolbox
 */

function toolbox_image_adoption_rest_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}

	echo "PASS: {$message}\n";
}

function get_temp_dir(): string {
	return sys_get_temp_dir() . '/';
}

define( 'ABSPATH', sys_get_temp_dir() . '/npcink-toolbox-rest-context/' );
toolbox_image_adoption_rest_assert( ! function_exists( 'wp_tempnam' ), 'The REST-like process starts without the WP-CLI-preloaded helper.' );

require_once dirname( __DIR__ ) . '/includes/Single_Article_Image_Adoption.php';

$service = new Npcink_Toolbox\Single_Article_Image_Adoption();
$method  = new ReflectionMethod( $service, 'create_temporary_file' );
$method->setAccessible( true );
$result  = $method->invoke( $service, 'image.png' );

toolbox_image_adoption_rest_assert( is_string( $result ) && '' !== $result, 'Image adoption creates a temporary file without the WP-CLI-preloaded helper.' );
toolbox_image_adoption_rest_assert( is_file( $result ), 'The REST fallback returns a real writable temporary file.' );

unlink( $result );

echo "Single-image adoption REST-context regression passed.\n";
