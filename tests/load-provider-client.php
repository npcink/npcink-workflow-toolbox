<?php
/**
 * Loads the Provider_Client facade with its shared support base and every
 * cluster service for behavior tests that instantiate the real client
 * outside a booted WordPress site.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox\Tests;

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/includes/Provider_Client_Support.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Nightly_Inspection_Service.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Ai_Image_Service.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Web_Search_Service.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Media_Alt_Caption_Service.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Comment_Moderation_Service.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Flagged_Media_Service.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Taxonomy_Tag_Service.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Internal_Link_Review_Service.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Hosted_AI_Service.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Site_Knowledge_Service.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Content_Collector_Service.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Discoverability_Service.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Workflow_Plans_Service.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Agent_Feedback_Service.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Site_Ops_Cloud_Service.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Article_Audio_Service.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Image_Source_Service.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Media_Recognition_Service.php';
require_once dirname( __DIR__ ) . '/includes/Provider_Client.php';
