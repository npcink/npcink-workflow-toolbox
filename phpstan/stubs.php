<?php
/**
 * PHPStan bootstrap stubs for runtime symbols not discoverable from the
 * analysed paths or php-stubs/wordpress-stubs.
 *
 * @package Npcink_Toolbox
 */

// WordPress defines ARRAY_A as a global constant at runtime; the generated
// stubs only carry the wpdb class constant.
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

// WordPress time/size constants are runtime defines the generated stubs
// do not export (same gap class as ARRAY_A).
if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'MB_IN_BYTES' ) ) {
	define( 'MB_IN_BYTES', 1048576 );
}

// Cross-plugin Cloud Addon seam, present only while npcink-cloud-addon is
// active; Toolbox guards the call with function_exists() at runtime.
if ( ! function_exists( 'npcink_cloud_addon_site_knowledge_change_bridge_health' ) ) {
	function npcink_cloud_addon_site_knowledge_change_bridge_health() {
		return array();
	}
}

// Plugin constants defined in the guarded bootstrap at the repository root;
// PHPStan's collector does not see past the early-return guard.
if ( ! defined( 'NPCINK_TOOLBOX_VERSION' ) ) {
	define( 'NPCINK_TOOLBOX_VERSION', '0.3.0' );
}
if ( ! defined( 'NPCINK_TOOLBOX_FILE' ) ) {
	define( 'NPCINK_TOOLBOX_FILE', __FILE__ );
}
if ( ! defined( 'NPCINK_TOOLBOX_DIR' ) ) {
	define( 'NPCINK_TOOLBOX_DIR', __DIR__ . '/../' );
}
if ( ! defined( 'NPCINK_TOOLBOX_URL' ) ) {
	define( 'NPCINK_TOOLBOX_URL', 'http://example.test/wp-content/plugins/npcink-workflow-toolbox/' );
}
