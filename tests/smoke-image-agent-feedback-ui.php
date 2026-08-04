<?php
/**
 * Smoke checks for passive image candidate feedback capture.
 *
 * @package Npcink_Toolbox
 */

$root     = dirname( __DIR__ );
$admin_js = file_get_contents( $root . '/assets/admin.js' );
$client   = file_get_contents( $root . '/includes/Provider_Client.php' );

function npcink_toolbox_image_feedback_smoke_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "[fail] {$message}\n" );
		exit( 1 );
	}

	fwrite( STDOUT, "[ok] {$message}\n" );
}

npcink_toolbox_image_feedback_smoke_assert(
	false !== $admin_js && false !== $client,
	'Image feedback smoke can read the required source files.'
);

npcink_toolbox_image_feedback_smoke_assert(
	false === strpos( $admin_js, 'function imageAgentFeedbackPayload' )
	&& false === strpos( $admin_js, 'appendImageAgentFeedbackControls' )
	&& false === strpos( $admin_js, 'data-toolbox-image-agent-feedback' )
	&& false === strpos( $admin_js, 'Quick image feedback' ),
	'Admin image results do not expose manual quality feedback controls.'
);

npcink_toolbox_image_feedback_smoke_assert(
	false !== strpos( $client, "'visual_quality_low'" )
	&& false !== strpos( $client, "'source_or_license_risk'" )
	&& false !== strpos( $client, "'production_mutation'      => false" )
	&& false !== strpos( $client, "'final_write_truth'        => 'wordpress_local'" ),
	'The metadata-only feedback contract remains available for passive observations without moving media write truth.'
);

fwrite( STDOUT, "Image Agent feedback UI smoke: ok\n" );
