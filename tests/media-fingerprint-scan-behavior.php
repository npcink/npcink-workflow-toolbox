<?php
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DAY_IN_SECONDS', 86400 );
	function absint( $value ): int { return max( 0, (int) $value ); }
	function sanitize_text_field( $value ): string { return trim( (string) $value ); }
	function sanitize_key( $value ): string { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ) ?? ''; }
	function get_posts( array $args ): array { return 'attachment' === ( $args['post_type'] ?? '' ) ? array( 101, 102 ) : array( 201 ); }
	function get_post_field( string $field, int $post_id ): string { return 'content-' . $post_id; }
	function parse_blocks( string $content ): array { return array( array( 'blockName' => 'core/image', 'attrs' => array( 'id' => 303 ), 'innerBlocks' => array() ), array( 'blockName' => 'core/gallery', 'attrs' => array( 'images' => array( array( 'id' => 304 ) ) ), 'innerBlocks' => array() ) ); }
	function apply_filters( string $tag, $value, ...$args ) { return 'npcink_toolbox_media_fingerprint_scan_evidence_attachment_ids' === $tag ? array( 305, 101 ) : $value; }
	function wp_list_pluck( array $list, string $field ): array { return array(); }
	function is_wp_error( $value ): bool { return false; }
	class WP_Error {}
	function scan_assert( bool $condition, string $message ): void { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } echo "PASS: {$message}\n"; }
}
namespace Npcink_Toolbox { final class Settings {} }
namespace {
	require_once dirname( __DIR__ ) . '/includes/Provider_Client.php';
	$reflection = new ReflectionClass( new Npcink_Toolbox\Provider_Client( new Npcink_Toolbox\Settings() ) );
	$method = $reflection->getMethod( 'media_fingerprint_scan_candidate_ids' );
	$method->setAccessible( true );
	$ids = $method->invoke( $reflection->newInstance( new Npcink_Toolbox\Settings() ), 10 );
	scan_assert( array( 305, 101, 102, 303, 304 ) === $ids, 'The weekly scan de-duplicates the bounded union and prioritizes projected evidence IDs.' );
	scan_assert( array( 305, 101, 102 ) === $method->invoke( $reflection->newInstance( new Npcink_Toolbox\Settings() ), 3 ), 'The weekly scan honors its bounded limit.' );
}
