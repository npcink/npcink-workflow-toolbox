<?php
/**
 * Behavior test for the internal-link review set service.
 *
 * @package Npcink_Toolbox
 */
error_reporting( E_ALL );
$root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', $root . '/' ); }

function get_post( $id ) { return null; }
function wp_trim_words( $text, $num_words = 55, $more = '…' ) { return $text; }
function sanitize_title( $title ) { return $title; }
function sanitize_textarea_field( $str ) { return trim( (string) $str ); }
function __( $text, $domain = 'default' ) { return $text; }
function absint( $maybeint ) { return abs( (int) $maybeint ); }
function sanitize_key( $key ) { return $key; }
function sanitize_text_field( $str ) { return trim( (string) $str ); }
function esc_url_raw( $url ) { return (string) $url; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }

require_once $root . '/includes/Provider_Client_Support.php';
require_once $root . '/includes/Provider_Internal_Link_Review_Service.php';

$passed = 0; $failed = 0;
function il_assert( bool $condition, string $message ): void {
	global $passed, $failed;
	if ( $condition ) { ++$passed; echo "PASS: {$message}\n"; }
	else { ++$failed; fwrite( STDERR, "FAIL: {$message}\n" ); }
}

$source = file_get_contents( $root . '/includes/Provider_Internal_Link_Review_Service.php' );
il_assert( false === strpos( $source, 'wp_update_post' ), 'Service never calls wp_update_post' );
il_assert( false === strpos( $source, 'wp_insert_post' ), 'Service never calls wp_insert_post' );
il_assert( false === strpos( $source, 'wp_set_post_terms' ), 'Service never calls wp_set_post_terms' );
il_assert( false !== strpos( $source, "'post_content_unchanged'" ), 'Posture: post_content_unchanged' );
il_assert( false !== strpos( $source, "'write_posture'          => 'suggestion_only'" ), 'Posture: suggestion_only' );
il_assert( false !== strpos( $source, 'internal_link_review_set.v1' ), 'Contract version v1' );
il_assert( false !== strpos( $source, 'MAX_POSTS_PER_REQUEST = 50' ), 'Bounded to 50 posts' );
il_assert( false !== strpos( $source, 'SPARSE_LINK_THRESHOLD = 3' ), 'Sparse threshold: 3 links' );
il_assert( false !== strpos( $source, 'extract_internal_link_targets' ), 'Extracts existing internal links' );

if ( $failed > 0 ) { fwrite( STDERR, "Internal-link review set: {$failed} failure(s).\n" ); exit( 1 ); }
echo "Internal-link review set behavior: ok\n";
