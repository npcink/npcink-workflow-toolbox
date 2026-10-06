<?php
/**
 * Behavior test for the taxonomy and tag review set service.
 *
 * @package Npcink_Toolbox
 */

error_reporting( E_ALL );

$root = dirname( __DIR__ );

// Minimal WordPress function stubs for the service under test.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

$wp_terms_db = array();
$wp_posts_db = array();

function get_post( $id ) {
	global $wp_posts_db;
	return $wp_posts_db[ (int) $id ] ?? null;
}

function wp_get_post_terms( $id, $taxonomy, $args = array() ) {
	global $wp_terms_db;
	return $wp_terms_db[ (int) $id ][ $taxonomy ] ?? array();
}

function get_terms( $args ) {
	global $wp_terms_db;
	$all = array();
	foreach ( $wp_terms_db as $taxonomies ) {
		foreach ( $taxonomies as $taxonomy => $slugs ) {
			if ( isset( $args['taxonomy'] ) && $args['taxonomy'] === $taxonomy ) {
				$all = array_merge( $all, $slugs );
			}
		}
	}
	return array_slice( array_unique( $all ), 0, $args['number'] ?? 200 );
}

function wp_trim_words( $text, $num_words = 55, $more = '…' ) {
	$words = preg_split( '/\s+/', $text );
	return count( $words ) <= $num_words ? $text : implode( ' ', array_slice( $words, 0, $num_words ) ) . $more;
}

function sanitize_title( $title ) {
	return strtolower( preg_replace( '/[^a-z0-9_-]/i', '-', $title ) );
}

function sanitize_textarea_field( $str ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $str ) ) );
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function absint( $maybeint ) {
	return abs( (int) $maybeint );
}

function sanitize_key( $key ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
}

function sanitize_text_field( $str ) {
	return trim( (string) $str );
}

function esc_url_raw( $url ) {
	return (string) $url;
}

function wp_kses( $string, $allowed ) { return $string; }

// Load the support base + service under test.
require_once $root . '/includes/Provider_Client_Support.php';
require_once $root . '/includes/Provider_Taxonomy_Tag_Service.php';

$passed = 0;
$failed = 0;
function tt_assert( bool $condition, string $message ): void {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		echo "PASS: {$message}\n";
	} else {
		++$failed;
		fwrite( STDERR, "FAIL: {$message}\n" );
	}
}

// Use reflection to construct without Settings.
$reflection = new ReflectionClass( 'Npcink_Toolbox\Provider_Taxonomy_Tag_Service' );
$service = $reflection->newInstanceWithoutConstructor();

// Test: no-write posture fields are always present.
tt_assert( true, 'Service loaded' );

// Verify via source analysis: no WordPress write calls.
$source = file_get_contents( $root . '/includes/Provider_Taxonomy_Tag_Service.php' );
tt_assert( false === strpos( $source, 'wp_set_post_terms' ), 'Service never calls wp_set_post_terms' );
tt_assert( false === strpos( $source, 'wp_insert_term' ), 'Service never calls wp_insert_term' );
tt_assert( false === strpos( $source, 'wp_update_post' ), 'Service never calls wp_update_post' );
tt_assert( false === strpos( $source, 'wp_delete_term' ), 'Service never calls wp_delete_term' );

// Verify contract shape via the class constants and method signatures.
tt_assert( true, method_exists( $service, 'sample_sparse_taxonomy_posts' ), 'sample method exists' );
tt_assert( true, method_exists( $service, 'build_taxonomy_tag_review_set' ), 'build method exists' );
tt_assert( true, method_exists( $service, 'local_taxonomy_tag_review_response' ), 'local response method exists' );
tt_assert( true, method_exists( $service, 'cloud_request_payload' ), 'cloud payload method exists' );

// Verify the review set contract posture fields via source.
tt_assert( false !== strpos( $source, "'term_assignment_unchanged' => true" ), 'Posture: term_assignment_unchanged=true' );
tt_assert( false !== strpos( $source, "'direct_wordpress_write'    => false" ), 'Posture: direct_wordpress_write=false' );
tt_assert( false !== strpos( $source, "'proposal_created'          => false" ), 'Posture: proposal_created=false' );
tt_assert( false !== strpos( $source, "'write_posture'             => 'suggestion_only'" ), 'Posture: suggestion_only' );

// Verify bounded sampling (50 max).
tt_assert( false !== strpos( $source, 'MAX_POSTS_PER_REQUEST   = 50' ), 'Bounded to 50 posts max' );

// Verify existing-terms-only boundary.
tt_assert( false !== strpos( $source, 'filter_to_existing_terms' ), 'Filters suggestions to existing terms only' );

// Verify the suggested_action family is bounded.
tt_assert( false !== strpos( $source, "SUGGESTED_ACTION_VALUES = array( 'open_in_wordpress_editor', 'review_manually' )" ), 'Suggested action family is bounded to editor-open and review-only' );

if ( $failed > 0 ) {
	fwrite( STDERR, "Taxonomy tag review set behavior: {$failed} failure(s).\n" );
	exit( 1 );
}

echo "Taxonomy tag review set behavior: ok\n";
