<?php
/**
 * Plugin Name: Npcink Workflow Toolbox
 * Description: Fixed AI workflow buttons for WordPress operators, with review-only suggestions and governed handoff plans.
 * Version: 0.5.3
 * Requires at least: 6.9
 * Requires PHP: 8.0
 * Author: Npcink
 * License: GPL-2.0-or-later
 * Text Domain: npcink-workflow-toolbox
 * Domain Path: /languages
 *
 * @package Npcink_Toolbox
 */

defined( 'ABSPATH' ) || exit;

define( 'NPCINK_TOOLBOX_VERSION', '0.4.0' );
define( 'NPCINK_TOOLBOX_FILE', __FILE__ );
define( 'NPCINK_TOOLBOX_DIR', plugin_dir_path( __FILE__ ) );
define( 'NPCINK_TOOLBOX_URL', plugin_dir_url( __FILE__ ) );

require_once NPCINK_TOOLBOX_DIR . 'includes/Settings.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Operation_Classifier.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Cloud_Image_Artifact_Transport.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Media_Optimization_Batches.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Client_Support.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Nightly_Inspection_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Ai_Image_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Web_Search_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Media_Alt_Caption_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Comment_Moderation_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Flagged_Media_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Hosted_AI_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Site_Knowledge_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Content_Collector_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Discoverability_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Workflow_Plans_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Agent_Feedback_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Site_Ops_Cloud_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Article_Audio_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Image_Source_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Media_Recognition_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Taxonomy_Tag_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Internal_Link_Review_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Provider_Client.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Media_Recognition_Continuation.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Media_Fingerprint_Scan.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Hot_Topic_Pool.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Site_Knowledge_Auto_Sync.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Site_Ops_Snapshot_Collector.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Site_Ops_Insight_Builder.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Site_Ops_Cloud_Request_Builder.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Ability_Surface_Metadata.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Publish_Preflight_Service.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Rest_Nightly_Inspection_Bridges.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Rest_Controller_Support.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Rest_Web_Search_Bridges.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Rest_Site_Knowledge_Bridges.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Rest_Media_Derivative_Previews.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Rest_Flow_Plan_Bridges.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Rest_Media_Optimization_Bridges.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Rest_Surface_Bridges.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Rest_Local_Admin_Consent.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Rest_Editor_Flow_Cache.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Rest_Editor_Paragraph_Check.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Rest_Editor_Audio_Text.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Rest_Editor_Taxonomy_Shaping.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Rest_Editor_Summary_Terms.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Rest_Editor_Content_Support.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Rest_Controller.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Editor_Content_Format.php';
require_once NPCINK_TOOLBOX_DIR . 'modules/local-automation-runtime/src/Contract/Replay_Validator.php';
require_once NPCINK_TOOLBOX_DIR . 'modules/local-automation-runtime/src/NightlyInspection/Rule_Scorer.php';
require_once NPCINK_TOOLBOX_DIR . 'modules/local-automation-runtime/src/NightlyInspection/Morning_Brief_Builder.php';
require_once NPCINK_TOOLBOX_DIR . 'modules/local-automation-runtime/src/NightlyInspection/Cloud_Batch_Result_Merger.php';
require_once NPCINK_TOOLBOX_DIR . 'modules/local-automation-runtime/src/NightlyInspection/Manual_Dry_Run_Planner.php';
require_once NPCINK_TOOLBOX_DIR . 'modules/local-automation-runtime/src/NightlyInspection/Snapshot_Collector.php';
require_once NPCINK_TOOLBOX_DIR . 'modules/local-automation-runtime/src/NightlyInspection/Basic_WP_Cron_Dry_Run.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Admin_Page_Site_Ops_Panel.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Admin_Page.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Editor_Content_Support.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Article_Audio_Playback.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Dashboard_Widget.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Abilities.php';
require_once NPCINK_TOOLBOX_DIR . 'includes/Plugin.php';

register_deactivation_hook( NPCINK_TOOLBOX_FILE, array( \Npcink_Toolbox\Site_Knowledge_Auto_Sync::class, 'deactivate' ) );
register_deactivation_hook( NPCINK_TOOLBOX_FILE, array( \Npcink\LocalAutomationRuntime\NightlyInspection\Basic_WP_Cron_Dry_Run::class, 'deactivate' ) );
register_deactivation_hook( NPCINK_TOOLBOX_FILE, array( \Npcink_Toolbox\Media_Recognition_Continuation::class, 'deactivate' ) );
register_deactivation_hook( NPCINK_TOOLBOX_FILE, array( \Npcink_Toolbox\Media_Fingerprint_Scan::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		\Npcink_Toolbox\Plugin::instance()->register_hooks();
	}
);
