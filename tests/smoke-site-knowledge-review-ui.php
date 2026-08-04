<?php
/**
 * No-write smoke checks for the Site Knowledge review handoff UI.
 *
 * This script intentionally reads source files only. It must not call REST
 * routes, Adapter endpoints, Core proposal intake, or WordPress write paths.
 *
 * @package NpcinkToolbox
 */

$root = dirname( __DIR__ );

/**
 * Assertion helper.
 *
 * @param bool   $condition Condition.
 * @param string $message Message.
 * @return void
 */
function npcink_toolbox_sk_review_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, '[fail] ' . $message . "\n" );
		exit( 1 );
	}

	echo '[ok] ' . $message . "\n";
}

$admin_js = file_get_contents( $root . '/assets/admin.js' );
$admin_page = file_get_contents( $root . '/includes/Admin_Page.php' );
$client   = file_get_contents( $root . '/includes/Provider_Client.php' );
$rest     = file_get_contents( $root . '/includes/Rest_Controller.php' );
$abilities = file_get_contents( $root . '/includes/Abilities.php' );

npcink_toolbox_sk_review_smoke_assert(
	false !== $admin_js && false !== $admin_page && false !== $client && false !== $rest && false !== $abilities,
	'Site Knowledge review smoke can read the required source files.'
);

npcink_toolbox_sk_review_smoke_assert(
	false !== strpos( $admin_page, 'Review handoff' )
	&& false !== strpos( $admin_page, 'Evidence first' )
	&& false !== strpos( $admin_page, 'Core review only' )
	&& false !== strpos( $admin_page, 'No direct write' ),
	'Site Knowledge page explains the narrow evidence-backed Agent handoff before operator submission.'
);

npcink_toolbox_sk_review_smoke_assert(
	false !== strpos( $admin_js, 'function submitSiteKnowledgeReviewProposal' )
	&& false !== strpos( $admin_js, "postJson(config.restUrl, 'flows/site-knowledge-review-plan'" )
	&& false !== strpos( $admin_js, "postJson(config.adapterRestUrl, 'proposals/from-plan'" )
	&& false !== strpos( $admin_js, "plan_ability_id: 'npcink-toolbox/build-site-knowledge-review-plan'" ),
	'Admin UI wires Site Knowledge review submission through Toolbox plan build and Adapter from-plan intake.'
);

npcink_toolbox_sk_review_smoke_assert(
	false !== strpos( $admin_js, 'toolbox_core_handoff_receipt.v1' )
	&& false !== strpos( $admin_js, 'wordpress_toolbox_local' )
	&& false !== strpos( $admin_js, 'ephemeral_response_only' )
	&& false !== strpos( $admin_js, "handoffType: 'site_knowledge_review_plan'" )
	&& false !== strpos( $admin_js, "sourceItemId: 'site_knowledge_agent_handoff'" )
	&& false !== strpos( $admin_js, 'Core remains the canonical approval, preflight, execution, and audit record.' ),
	'Admin UI renders a local Site Knowledge Core handoff receipt without claiming canonical governance storage.'
);

npcink_toolbox_sk_review_smoke_assert(
	false !== strpos( $admin_js, "submitButton.setAttribute('data-toolbox-site-knowledge-review-submit', 'true')" )
	&& false !== strpos( $admin_js, "submitButton.addEventListener('click', () => submitSiteKnowledgeReviewProposal(container, handoff, submitButton))" ),
	'Admin UI submits Site Knowledge review only from the explicit operator button.'
);

npcink_toolbox_sk_review_smoke_assert(
	false === strpos( $admin_js, 'function submitSiteKnowledgeAgentFeedback' )
	&& false === strpos( $admin_js, 'data-toolbox-site-knowledge-agent-feedback' )
	&& false === strpos( $admin_js, 'data-toolbox-agent-feedback-quick' )
	&& false === strpos( $admin_js, 'renderAgentFeedbackSummaryNode' )
	&& false === strpos( $admin_js, "postJson(config.restUrl, 'agent-feedback/summary'" ),
	'Admin UI removes manual Site Knowledge feedback and its developer-oriented quality summary.'
);

npcink_toolbox_sk_review_smoke_assert(
	false !== strpos( $admin_js, 'Submit Core review proposal' )
	&& false !== strpos( $admin_js, 'Open Core review' ),
	'Site Knowledge keeps the operator review and governed handoff actions after feedback controls are removed.'
);

npcink_toolbox_sk_review_smoke_assert(
	false !== strpos( $admin_js, 'Site Knowledge review proposal submitted' )
	&& false !== strpos( $admin_js, 'Human title and content input are required before approval, preflight, or execution can proceed.' )
	&& false !== strpos( $admin_js, 'Could not submit the Site Knowledge review proposal.' )
	&& false !== strpos( $admin_js, 'Site Knowledge Core handoff failed' )
	&& false !== strpos( $admin_js, 'Site Knowledge Core handoff error payload' )
	&& false !== strpos( $admin_js, 'review_adapter_core_error' ),
	'Admin UI exposes success and failure copy for the blocked Core review proposal.'
);

npcink_toolbox_sk_review_smoke_assert(
	false !== strpos( $client, 'npcink_toolbox_site_knowledge_review_evidence_required' )
	&& false !== strpos( $client, "'artifact_type'          => 'site_knowledge_review_plan'" )
	&& false !== strpos( $client, "'target_ability_id' => 'npcink-abilities-toolkit/create-draft'" )
	&& false !== strpos( $client, "'proposal_ready'    => false" )
	&& false !== strpos( $client, "'requires_input'    => array( 'title', 'content' )" ),
	'Provider client builds an evidence-gated blocked review plan that still requires human draft input.'
);

npcink_toolbox_sk_review_smoke_assert(
	false !== strpos( $client, "'site_knowledge_evidence_refs' => \$this->sanitize_payload( \$evidence_refs )" )
	&& false !== strpos( $client, "'dry_run'         => true" )
	&& false !== strpos( $client, "'commit'          => false" )
	&& false !== strpos( $client, "'direct_wordpress_write' => false" ),
	'Provider client preserves evidence and keeps the generated plan no-write.'
);

npcink_toolbox_sk_review_smoke_assert(
	false !== strpos( $rest, "\$this->post( '/flows/site-knowledge-review-plan', 'site_knowledge_review_plan' );" )
	&& false !== strpos( $rest, "\$this->post( '/agent-feedback', 'agent_feedback' );" )
	&& false !== strpos( $rest, "\$this->post( '/agent-feedback/summary', 'agent_feedback_summary' );" )
	&& false !== strpos( $rest, 'public function agent_feedback' )
	&& false !== strpos( $rest, 'public function agent_feedback_summary' )
	&& false !== strpos( $rest, 'public function site_knowledge_review_plan' )
	&& false !== strpos( $abilities, "'npcink-toolbox/build-site-knowledge-review-plan'" ),
	'Toolbox exposes the narrow REST routes and ability used by the Site Knowledge review UI.'
);

npcink_toolbox_sk_review_smoke_assert(
	false !== strpos( $client, 'submit_agent_feedback' )
	&& false !== strpos( $client, 'cloud_agent_feedback.v1' )
	&& false !== strpos( $client, 'send_agent_feedback_event' )
	&& false !== strpos( $client, 'get_agent_feedback_summary' )
	&& false !== strpos( $client, 'npcink_toolbox_agent_feedback_summary_cloud_request' )
	&& false !== strpos( $client, "'quality_trend'" )
	&& false !== strpos( $client, "'low_quality_labels'" )
	&& false !== strpos( $client, "'production_mutation'      => false" )
	&& false !== strpos( $client, "'approval_truth'           => 'wordpress_local'" ),
	'Provider client submits Agent feedback as Cloud eval metadata without moving approval or write truth.'
);

echo "Site Knowledge review UI smoke: ok\n";
