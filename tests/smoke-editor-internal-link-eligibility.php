<?php
/** Read-only WP-CLI smoke for editor candidate evidence eligibility. */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run with wp eval-file in a local WordPress installation.\n" );
	exit( 1 );
}

$targets = get_posts( array( 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) );
if ( empty( $targets ) ) {
	throw new RuntimeException( 'A published local post is required.' );
}
$target_id = (int) $targets[0];
$before    = get_post_field( 'post_content', $target_id );
$class     = new ReflectionClass( \Npcink_Toolbox\Rest_Controller::class );
$instance  = $class->newInstanceWithoutConstructor();
$project   = $class->getMethod( 'editor_internal_link_recommendation_candidates' );
$project->setAccessible( true );
$item = array(
	'title'          => get_the_title( $target_id ),
	'target_post_id' => $target_id,
	'anchor_or_context' => '内容工作流',
	'source_match'   => array(
		'block_client_id' => 'eligibility-smoke',
		'block_name'      => 'core/paragraph',
		'matched_text'    => '内容工作流',
		'expected_text'   => '内容工作流需要人工审阅。',
		'text_offset'     => 0,
	),
);

foreach ( array( true, false ) as $cloud_evidence ) {
	$items = $project->invoke( $instance, array( $item ), $cloud_evidence );
	if ( 1 !== count( $items ) || $cloud_evidence !== $items[0]['can_apply_to_editor'] ) {
		throw new RuntimeException( 'Exact matches must remain reference-only without Cloud evidence.' );
	}
	$policy = $cloud_evidence ? 'operator_confirmed_visible_editor_apply' : 'operator_review_copy_or_open_only';
	if ( $policy !== $items[0]['action_policy'] || false !== $items[0]['direct_wordpress_write'] || empty( $items[0]['target_ref']['url'] ) ) {
		throw new RuntimeException( 'Apply policy, copy URL, and no-write contract must agree.' );
	}
}
if ( $before !== get_post_field( 'post_content', $target_id ) ) {
	throw new RuntimeException( 'Read-only eligibility smoke changed post content.' );
}
echo "PASS: Cloud evidence gates exact-match Apply; copy/open and no-write are preserved.\n";
