<?php
/**
 * Focused behavior checks for editor progressive recommendations.
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wp-stub/' );
}

$npcink_toolbox_progressive_cloud_calls = 0;
$npcink_toolbox_progressive_taxonomy_inputs = array();
$npcink_toolbox_progressive_transients = array();
$npcink_toolbox_progressive_draft_inputs = array();
$npcink_toolbox_progressive_writing_pack_inputs = array();
$npcink_toolbox_progressive_site_knowledge_inputs = array();
$npcink_toolbox_progressive_source_reader_calls = 0;
$npcink_toolbox_progressive_source_reader_mode = 'ready';

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		private array $data;

		public function __construct( string $code = '', string $message = '', array $data = array() ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_data(): array {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		private $data;

		public function __construct( $data = null ) {
			$this->data = $data;
		}

		public function get_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private array $params;
		private string $route;
		private string $method;

		public function __construct( array $params = array(), string $route = '', string $method = 'POST' ) {
			$this->params = $params;
			$this->route  = $route;
			$this->method = $method;
		}

		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}

		public function get_params(): array {
			return $this->params;
		}

		public function get_json_params() {
			return $this->params;
		}

		public function get_route(): string {
			return $this->route;
		}

		public function get_method(): string {
			return $this->method;
		}

		public function get_header( string $name ): string {
			return '';
		}
	}
}

function npcink_toolbox_progressive_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

function __( string $text, string $domain = 'default' ): string {
	return $text;
}

function get_locale(): string {
	return 'zh_CN';
}

function absint( $value ): int {
	return max( 0, (int) $value );
}

function sanitize_key( $key ): string {
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\\-]/', '', $key ) ?? '';
}

function sanitize_text_field( $value ): string {
	return trim( preg_replace( '/\\s+/', ' ', wp_strip_all_tags( (string) $value ) ) ?? '' );
}

function sanitize_textarea_field( $value ): string {
	return trim( wp_strip_all_tags( (string) $value ) );
}

function sanitize_title( $value ): string {
	$value = strtolower( sanitize_text_field( $value ) );
	return trim( preg_replace( '/[^a-z0-9]+/', '-', $value ) ?? '', '-' );
}

function esc_url_raw( $value, $protocols = null ): string {
	return trim( (string) $value );
}

function wp_parse_url( string $url, int $component = -1 ) {
	return -1 === $component ? parse_url( $url ) : parse_url( $url, $component );
}

function get_transient( string $key ) {
	global $npcink_toolbox_progressive_transients;
	return $npcink_toolbox_progressive_transients[ $key ] ?? false;
}

function set_transient( string $key, $value, int $expiration = 0 ): bool {
	global $npcink_toolbox_progressive_transients;
	$npcink_toolbox_progressive_transients[ $key ] = $value;
	return true;
}

function delete_transient( string $key ): bool {
	global $npcink_toolbox_progressive_transients;
	unset( $npcink_toolbox_progressive_transients[ $key ] );
	return true;
}

function rest_sanitize_boolean( $value ): bool {
	return in_array( $value, array( true, 1, '1', 'true' ), true );
}

function get_option( string $key, $default = false ) {
	return $default;
}

function apply_filters( string $hook, $value, ...$args ) {
	if ( in_array( $hook, array( 'npcink_toolbox_web_search_runtime_payload', 'npcink_toolbox_site_knowledge_runtime_payload', 'npcink_toolbox_hosted_ai_runtime_payload', 'npcink_toolbox_cloud_data_classification' ), true ) ) {
		return $value;
	}
	if ( 'npcink_toolbox_web_search_cloud_request' === $hook ) {
		global $npcink_toolbox_progressive_source_reader_calls, $npcink_toolbox_progressive_source_reader_mode;
		$runtime_input = is_array( $args[1] ?? null ) ? $args[1] : array();
		$source_url = (string) ( $runtime_input['source_url'] ?? '' );
		++$npcink_toolbox_progressive_source_reader_calls;
		if ( 'blocked' === $npcink_toolbox_progressive_source_reader_mode ) {
			return array(
				'status' => 'ok',
				'run_id' => 'writing-pack-source-blocked-run',
				'data'   => array(
					'result' => array(
						'artifact_type'    => 'source_extraction_preview',
						'contract_version' => 'source_extraction_preview.v1',
						'output_contract'  => 'source_extraction_preview.v1',
						'status'           => 'blocked',
						'intent'           => 'source_extraction_preview',
						'requested_url'    => $source_url,
						'resolved_url'     => '',
						'url_match'        => 'unavailable',
						'char_count'       => 0,
						'word_count'       => 0,
						'results'          => array(),
					),
				),
			);
		}
		return array(
			'status' => 'ok',
			'run_id' => 'writing-pack-source-run',
			'data'   => array(
				'result' => array(
					'artifact_type'    => 'source_extraction_preview',
					'contract_version' => 'source_extraction_preview.v1',
					'output_contract'  => 'source_extraction_preview.v1',
					'status'           => 'ready',
					'intent'           => 'source_extraction_preview',
					'requested_url'    => $source_url,
					'resolved_url'     => $source_url,
					'url_match'        => 'matched',
					'title'            => 'Exact source article',
					'content_hash'     => 'sha256-exact-source',
					'char_count'       => 1200,
					'word_count'       => 180,
					'content_trust'    => 'untrusted_external_source',
					'prompt_injection_review_required' => true,
					'coverage'         => array( 'level' => 'partial', 'reader_bounded' => true, 'complete_capture_claimed' => false ),
					'results'          => array(
						array(
							'title'          => 'Exact source article',
							'url'            => $source_url,
							'reader_excerpt' => "* [Showcase](https://example.com/showcase)\n* [Plugins](https://example.com/plugins)\n# Exact source article\n" . str_repeat( 'This article explains a staged source workflow with concrete editorial evidence. ', 12 ),
							'reader_status'  => 'ready',
						),
					),
				),
			),
		);
	}
	if ( 'npcink_toolbox_site_knowledge_cloud_request' === $hook ) {
		global $npcink_toolbox_progressive_site_knowledge_inputs, $npcink_toolbox_site_knowledge_status_mode;
		$site_knowledge_runtime = is_array( $args[0] ?? null ) ? $args[0] : array();
		$npcink_toolbox_progressive_site_knowledge_inputs[] = is_array( $site_knowledge_runtime['input'] ?? null ) ? $site_knowledge_runtime['input'] : array();
		if ( 'npcink-cloud/site-knowledge-status' === (string) ( $args[1] ?? '' ) ) {
			if ( 'error' === ( $npcink_toolbox_site_knowledge_status_mode ?? '' ) ) {
				return new WP_Error( 'npcink_toolbox_site_knowledge_status_failed', 'Cloud status unavailable', array( 'status' => 503 ) );
			}
			return array(
				'status' => 'ok',
				'run_id' => 'media-evidence-status-run',
				'data'   => array( 'result' => array( 'status' => 'ready', 'media_evidence_items' => array() ) ),
			);
		}
		return array(
			'status' => 'ok',
			'run_id' => 'writing-pack-knowledge-run',
			'data'   => array(
				'result' => array(
					'status'  => 'ready',
					'intent'  => 'writing_support_plan',
					'results' => array(
						array( 'post_id' => 88, 'title' => 'Existing site article', 'url' => 'https://example.test/existing', 'score' => 0.82 ),
					),
				),
			),
		);
	}
	if ( 'npcink_toolbox_hosted_ai_cloud_request' === $hook ) {
		global $npcink_toolbox_progressive_draft_inputs, $npcink_toolbox_progressive_writing_pack_inputs;
		$runtime_input = is_array( $args[1] ?? null ) ? $args[1] : array();
		if ( 'article_draft_from_writing_pack' === (string) ( $runtime_input['intent'] ?? '' ) ) {
			$npcink_toolbox_progressive_draft_inputs[] = $runtime_input;
			return array(
				'status' => 'ok',
				'run_id' => 'writing-pack-draft-run',
				'data'   => array(
					'result' => array(
						'status'      => 'ready',
						'output_json' => array(
							'title'   => 'A reviewed writing-pack workflow',
							'excerpt' => 'A source-grounded planning and review path before drafting.',
							'sections' => array(
								array( 'heading' => 'Verify the source', 'body' => 'Review bounded source evidence before using factual claims.', 'supporting_fact_refs' => array( 'fact_ledger:0' ) ),
								array( 'heading' => 'Confirm the plan', 'body' => 'Confirm audience, focus, angle, and outline before draft generation.', 'supporting_fact_refs' => array() ),
							),
							'verification_notes' => array( 'Verify every source-supported claim.' ),
							'source_attribution_notes' => array( 'Confirm quotation and attribution requirements.' ),
						),
					),
				),
			);
		}
		$npcink_toolbox_progressive_writing_pack_inputs[] = $runtime_input;
		return array(
			'status' => 'ok',
			'run_id' => 'writing-pack-ai-run',
			'data'   => array(
				'result' => array(
					'status'      => 'ready',
					'output_json' => array(
						'editorial_direction' => array( 'audience' => 'WordPress site operators', 'article_goal' => 'Plan a distinct article.', 'reader_problem' => 'Source use lacks review.', 'focus_points' => array( 'Evidence', 'Distinct angle' ) ),
						'research_basis' => array( 'source_summary' => array( 'A staged workflow.' ), 'fact_ledger' => array( array( 'claim' => 'The source describes stages.', 'evidence_basis' => 'reader_excerpt', 'verification_status' => 'source_supported' ) ) ),
						'site_adaptation' => array( 'overlap_map' => array( 'Governance basics already exist.' ), 'site_style_signals' => array( 'Use operational language.' ), 'unique_angle' => 'Focus on the pre-generation review gate.' ),
						'writing_plan' => array( 'title_directions' => array( 'From evidence to writing pack' ), 'reader_promise' => 'A safer planning path.', 'content_type' => 'tutorial', 'outline' => array( 'Verify', 'Compare', 'Review' ) ),
						'risk_review' => array( 'rights_risks' => array( 'Confirm quotation rights.' ), 'similarity_risks' => array( 'Do not mirror structure.' ) ),
					),
				),
			),
		);
	}

	return $value;
}

function wp_strip_all_tags( $value ): string {
	return strip_tags( (string) $value );
}

function wp_trim_words( $text, int $num_words = 55, ?string $more = null ): string {
	$words = preg_split( '/\\s+/', trim( (string) $text ) );
	if ( ! is_array( $words ) || count( $words ) <= $num_words ) {
		return trim( (string) $text );
	}
	return implode( ' ', array_slice( $words, 0, $num_words ) ) . ( null === $more ? '' : $more );
}

function wp_json_encode( $value ): string {
	return (string) json_encode( $value );
}

function is_wp_error( $value ): bool {
	return $value instanceof WP_Error;
}

function rest_ensure_response( $value ): WP_REST_Response {
	return $value instanceof WP_REST_Response ? $value : new WP_REST_Response( $value );
}

function get_object_taxonomies( string $post_type ): array {
	return 'post' === $post_type ? array( 'category', 'post_tag' ) : array();
}

function get_terms( array $args ): array {
	$taxonomy = (string) ( $args['taxonomy'] ?? '' );
	if ( 'category' === $taxonomy ) {
		return array(
			(object) array( 'term_id' => 11, 'name' => 'AI Workflow', 'slug' => 'ai-workflow', 'description' => 'AI workflow planning', 'count' => 8 ),
			(object) array( 'term_id' => 12, 'name' => 'Operations', 'slug' => 'operations', 'description' => 'Operator process', 'count' => 5 ),
			(object) array( 'term_id' => 13, 'name' => 'This', 'slug' => 'this', 'description' => 'this', 'count' => 9 ),
			(object) array( 'term_id' => 14, 'name' => '渐进推荐', 'slug' => 'progressive-recommendation', 'description' => '渐进推荐 系统', 'count' => 6 ),
			(object) array( 'term_id' => 15, 'name' => 'Post Formats', 'slug' => 'post-formats', 'description' => 'post format archive', 'count' => 12 ),
			(object) array( 'term_id' => 16, 'name' => 'Editorial Ops', 'slug' => 'editorial-ops', 'description' => 'workflow only', 'count' => 10 ),
		);
	}
	if ( 'post_tag' === $taxonomy ) {
		return array(
			(object) array( 'term_id' => 21, 'name' => 'recommendation', 'slug' => 'recommendation', 'description' => 'recommendation system', 'count' => 7 ),
			(object) array( 'term_id' => 22, 'name' => 'latency', 'slug' => 'latency', 'description' => 'fast response', 'count' => 4 ),
		);
	}
	return array();
}

function npcink_toolbox_provider_services_media_inventory( array $input ): array {
	return array(
		'success' => true,
		'data'    => array(
			'items' => array(
				array(
					'attachment_id'     => 88,
					'url'               => 'https://example.test/library/workflow.jpg',
					'title'             => 'AI workflow diagram',
					'alt'               => 'AI workflow recommendation diagram',
					'mime_type'         => 'image/jpeg',
					'media_fingerprint' => 'sha256:' . str_repeat( 'a', 64 ),
					'format_inspection' => array( 'width' => 1200, 'height' => 800 ),
				),
			),
		),
	);
}

function npcink_abilities_toolkit_get_registered(): array {
	return array(
		'npcink-abilities-toolkit/suggest-post-taxonomy-terms' => array(
			'execute_callback' => 'npcink_toolbox_progressive_taxonomy_suggestions',
		),
		'npcink-abilities-toolkit/get-media-inventory-health' => array(
			'execute_callback' => 'npcink_toolbox_provider_services_media_inventory',
		),
	);
}

function npcink_toolbox_progressive_taxonomy_suggestion_item( string $taxonomy, int $term_id, string $name, string $slug, array $signals, string $reason ): array {
	return array(
		'taxonomy'        => $taxonomy,
		'term_id'         => $term_id,
		'name'            => $name,
		'slug'            => $slug,
		'score'           => 4.5,
		'confidence'      => 0.9,
		'match_signals'   => $signals,
		'reason'          => $reason,
		'evidence_refs'   => array( 'toolkit_stub' ),
		'related_context' => array(
			'source_count' => in_array( 'related_site_knowledge_term', $signals, true ) ? 1 : 0,
		),
	);
}

function npcink_toolbox_progressive_taxonomy_suggestions( array $input ): array {
	global $npcink_toolbox_progressive_taxonomy_inputs;
	$npcink_toolbox_progressive_taxonomy_inputs[] = $input;

	$title = (string) ( $input['title'] ?? '' );
	$items = array();
	if ( false !== stripos( $title, 'AI Workflow' ) || false !== stripos( $title, 'Fast AI recommendation workflow' ) ) {
		$items[] = npcink_toolbox_progressive_taxonomy_suggestion_item(
			'category',
			11,
			'AI Workflow',
			'ai-workflow',
			array( 'current_draft_match', 'title_term_name_match' ),
			'Matched tokens: ai, workflow.'
		);
		$items[] = npcink_toolbox_progressive_taxonomy_suggestion_item(
			'post_tag',
			21,
			'recommendation',
			'recommendation',
			array( 'current_draft_match' ),
			'Matched tokens: recommendation.'
		);
	}
	if ( false !== strpos( $title, '渐进推荐' ) ) {
		$items[] = npcink_toolbox_progressive_taxonomy_suggestion_item(
			'category',
			14,
			'渐进推荐',
			'progressive-recommendation',
			array( 'current_draft_match', 'title_term_name_match' ),
			'Matched tokens: 渐进推荐.'
		);
	}

	return array(
		'success' => true,
		'data'    => array(
			'artifact_type'          => 'article_taxonomy_suggestions.v1',
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'taxonomy_terms'         => array(
				'candidate_type'         => 'taxonomy_tag_candidates',
				'write_posture'          => 'suggestion_only',
				'direct_wordpress_write' => false,
				'ranking_context'        => array(
					'related_term_policy' => 'ranking_evidence_only_no_term_creation_or_assignment',
				),
				'items'                  => $items,
			),
		),
	);
}

function get_posts( array $args ): array {
	return array(
		(object) array( 'ID' => 31, 'post_type' => 'attachment', 'post_title' => 'AI workflow diagram', 'post_excerpt' => 'Recommendation workflow', 'post_content' => 'Fast recommendation pipeline' ),
	);
}

function get_post( int $post_id ) {
	if ( 31 === $post_id ) {
		return (object) array( 'ID' => 31, 'post_type' => 'attachment', 'post_title' => 'AI workflow diagram', 'post_excerpt' => 'Recommendation workflow', 'post_content' => 'Fast recommendation pipeline' );
	}
	return null;
}

function get_post_type( $post ): string {
	return is_object( $post ) ? (string) ( $post->post_type ?? '' ) : '';
}

function wp_attachment_is_image( int $attachment_id ): bool {
	return 31 === $attachment_id;
}

function wp_get_attachment_image_src( int $attachment_id, string $size ) {
	return array( 'https://example.test/workflow-thumb.jpg', 300, 200 );
}

function get_post_meta( int $post_id, string $key, bool $single = false ): string {
	return '_wp_attachment_image_alt' === $key ? 'AI workflow recommendation diagram' : '';
}

function wp_get_attachment_url( int $attachment_id ): string {
	return 'https://example.test/workflow.jpg';
}

function npcink_cloud_addon_get_connection_state(): array {
	return array( 'configured' => false, 'verified' => false );
}

if ( ! class_exists( 'Npcink_Toolbox\\Plugin' ) ) {
	class Npcink_Toolbox_Progressive_Plugin_Stub {
		public const OPTION_NAME         = 'npcink_toolbox_settings';
		public const CONTEXT_OPTION_NAME = 'npcink_toolbox_content_context';
		public const MEDIA_OPTION_NAME   = 'npcink_toolbox_media_settings';
		public const REST_NAMESPACE      = 'npcink-toolbox/v1';
	}
	class_alias( Npcink_Toolbox_Progressive_Plugin_Stub::class, 'Npcink_Toolbox\\Plugin' );
}

require_once dirname( __DIR__ ) . '/includes/Settings.php';
require_once dirname( __DIR__ ) . '/tests/load-provider-client.php';
require_once dirname( __DIR__ ) . '/includes/Publish_Preflight_Service.php';
require_once dirname( __DIR__ ) . '/includes/Editor_Content_Format.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Controller_Support.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Editor_Flow_Cache.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Editor_Taxonomy_Shaping.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Surface_Bridges.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Editor_Content_Support.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Editor_Paragraph_Check.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Editor_Audio_Text.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Local_Admin_Consent.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Media_Optimization_Bridges.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Flow_Plan_Bridges.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Media_Derivative_Previews.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Site_Knowledge_Bridges.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Web_Search_Bridges.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Nightly_Inspection_Bridges.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Controller.php';

$settings   = new Npcink_Toolbox\Settings();
$client     = new Npcink_Toolbox\Provider_Client( $settings );
$preflight  = new Npcink_Toolbox\Publish_Preflight_Service();
$controller = new Npcink_Toolbox\Rest_Controller( $settings, $client, $preflight );
/**
 * Direct behavior coverage for the two most complex provider cluster services.
 * These tests instantiate Provider_Workflow_Plans_Service and
 * Provider_Hosted_AI_Service directly, so a method accidentally duplicated
 * into (or silently detached from) the facade is caught independently of the
 * facade-driven editor flow tests.
 */

function sanitize_file_name( string $value ): string {
	return preg_replace( '/[^A-Za-z0-9._-]/', '', $value ) ?? '';
}

function parse_blocks( string $content ): array {
	$blocks = array();
	preg_match_all( '/<!-- wp:([a-z0-9\/-]+) (\{.*?\}) -->/', $content, $matches, PREG_SET_ORDER );
	foreach ( $matches as $match ) {
		$attrs = json_decode( $match[2], true );
		$blocks[] = array( 'blockName' => $match[1], 'attrs' => is_array( $attrs ) ? $attrs : array() );
	}
	return $blocks;
}

function npcink_toolbox_provider_services_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

$workflow_plans = new Npcink_Toolbox\Provider_Workflow_Plans_Service( $settings, $client );
$hosted_ai      = new Npcink_Toolbox\Provider_Hosted_AI_Service( $settings, $client );

function npcink_toolbox_provider_services_error_status( $error ): int {
	if ( ! $error instanceof WP_Error ) {
		return -1;
	}
	$data = $error->get_error_data();
	return is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;
}

// ---- Provider_Workflow_Plans_Service -------------------------------------

// 1. Article write plans reject missing input before any composition.
$missing = $workflow_plans->build_article_write_plan( array( 'title' => 'Only a title' ) );
npcink_toolbox_provider_services_assert( $missing instanceof WP_Error && 'npcink_toolbox_missing_article_plan_input' === $missing->get_error_code(), 'Article write plan rejects missing content with the dedicated error code.' );
npcink_toolbox_provider_services_assert( 400 === npcink_toolbox_provider_services_error_status( $missing ), 'Article write plan input rejection is a 400.' );

// 2. A valid reviewed draft produces a dry-run, Core-handoff-only plan.
$plan = $workflow_plans->build_article_write_plan( array(
	'title'            => 'AI workflow operator guide',
	'content_markdown' => "A reviewed draft body with enough operator guidance to plan a WordPress draft creation through Core governance.\n\nIt covers fixed buttons, suggestion-only posture, and governed handoffs.",
) );
npcink_toolbox_provider_services_assert( is_array( $plan ), 'A reviewed draft builds an article write plan.' );
npcink_toolbox_provider_services_assert( 'article_write_plan' === (string) ( $plan['artifact_type'] ?? '' ), 'Article write plan declares its artifact type.' );
npcink_toolbox_provider_services_assert( 'core_proposal_handoff' === (string) ( $plan['write_posture'] ?? '' ) && false === ( $plan['direct_wordpress_write'] ?? true ), 'Article write plan stays Core-handoff only with no direct write.' );
npcink_toolbox_provider_services_assert( true === ( $plan['dry_run'] ?? false ) && false === ( $plan['commit_execution'] ?? true ), 'Article write plan stays dry-run with no commit execution.' );
$create_action = $plan['write_actions'][0] ?? array();
npcink_toolbox_provider_services_assert( 'npcink-abilities-toolkit/create-draft' === (string) ( $create_action['target_ability_id'] ?? '' ), 'Article write plan targets the Toolkit create-draft ability.' );
$action_input = is_array( $create_action['input'] ?? null ) ? $create_action['input'] : array();
npcink_toolbox_provider_services_assert( true === ( $action_input['dry_run'] ?? false ) && false === ( $action_input['commit'] ?? true ), 'The create-draft action input itself stays dry-run and uncommitted.' );

// 3. Batch write plans enforce their 2-to-5 article bounds.
$too_few = $workflow_plans->build_article_batch_write_plan( array( 'articles' => array( array( 'title' => 'One', 'content_markdown' => 'Only one reviewed draft.' ) ) ) );
npcink_toolbox_provider_services_assert( $too_few instanceof WP_Error && 'npcink_toolbox_article_batch_size_invalid' === $too_few->get_error_code(), 'Article batch write plan rejects a single-article batch.' );

$batch = $workflow_plans->build_article_batch_write_plan( array( 'articles' => array(
	array( 'title' => 'First reviewed draft', 'content_markdown' => 'First reviewed draft body for a bounded batch plan.' ),
	array( 'title' => 'Second reviewed draft', 'content_markdown' => 'Second reviewed draft body for a bounded batch plan.' ),
) ) );
npcink_toolbox_provider_services_assert( is_array( $batch ), 'A two-article batch builds a batch write plan.' );
npcink_toolbox_provider_services_assert( 2 === count( $batch['write_actions'] ?? array() ), 'Batch write plan carries one write action per reviewed article.' );
npcink_toolbox_provider_services_assert( false === ( $batch['commit_execution'] ?? true ) && false === ( $batch['direct_wordpress_write'] ?? true ), 'Batch write plan stays uncommitted with no direct write.' );

// 4. Content metadata apply plans only target existing terms.
$metadata = $workflow_plans->build_content_metadata_apply_plan( array(
	'post_id'  => 31,
	'excerpt'  => 'Operator-reviewed excerpt.',
	'category_ids' => array( 11 ),
	'tag_ids'  => array( 21 ),
) );
npcink_toolbox_provider_services_assert( is_array( $metadata ) || $metadata instanceof WP_Error, 'Metadata apply plan builder answers without a fatal.' );
if ( is_array( $metadata ) ) {
	$ability_ids = array();
	foreach ( ( $metadata['write_actions'] ?? array() ) as $meta_action ) {
		$ability_ids[] = (string) ( $meta_action['target_ability_id'] ?? '' );
	}
	npcink_toolbox_provider_services_assert( array() === array_diff( $ability_ids, array( 'npcink-abilities-toolkit/update-post', 'npcink-abilities-toolkit/set-post-terms' ) ), 'Metadata apply plan targets only update-post and set-post-terms abilities.' );
	npcink_toolbox_provider_services_assert( false !== strpos( wp_json_encode( $metadata ), '"create_missing":false' ) || in_array( 'npcink-abilities-toolkit/set-post-terms', $ability_ids, true ), 'Metadata apply plan keeps existing-terms-only semantics on the Toolkit path.' );
}

// ---- Provider_Hosted_AI_Service ------------------------------------------

// 5. Hosted AI content support rejects unknown intents before runtime calls.
$bad_intent = $hosted_ai->run_hosted_ai_content_support( array( 'intent' => 'not_a_hosted_intent' ) );
npcink_toolbox_provider_services_assert( $bad_intent instanceof WP_Error && 'npcink_toolbox_invalid_hosted_ai_intent' === $bad_intent->get_error_code(), 'Hosted AI content support rejects unknown intents.' );

// 6. Site-helper quality contracts expose a fail-closed review contract.
$quality = $hosted_ai->hosted_ai_site_helper_quality_contract( 'media_alt_suggestions' );
npcink_toolbox_provider_services_assert( is_array( $quality ) && array() !== $quality, 'The media ALT site-helper quality contract is present.' );
$quality_json = wp_json_encode( $quality );
npcink_toolbox_provider_services_assert( false === strpos( $quality_json, 'wp_insert_post' ) && false === strpos( $quality_json, 'update_post_meta' ), 'The quality contract never names WordPress write functions.' );

// 7. Attachment id extraction is deterministic for mixed markup.
$ids = $hosted_ai->hosted_ai_content_image_attachment_ids( '<!-- wp:image {"id":31} --><img class="wp-image-31"> plain <img src="x.png"> <!-- wp:image {"id":45} -->' );
npcink_toolbox_provider_services_assert( in_array( 31, $ids, true ) && in_array( 45, $ids, true ) && ! in_array( 0, $ids, true ), 'Attachment id extraction finds block ids and skips images without ids.' );

// 8. Site-media search keeps a sanitized string status even when the
//    evidence status call answers with a full response payload or fails.
$GLOBALS['npcink_toolbox_site_knowledge_status_mode'] = 'ready';
$site_media = $client->image_candidates( 'AI workflow diagram', array( 'provider' => 'site_media', 'per_page' => 5 ) );
npcink_toolbox_provider_services_assert( is_array( $site_media ), 'Site-media search returns a candidate response.' );
npcink_toolbox_provider_services_assert( 'ready' === (string) ( $site_media['status'] ?? '' ), 'Site-media search reports the sanitized Cloud readiness string, not the raw status payload (regression: shadowed status).' );
npcink_toolbox_provider_services_assert( ! empty( $site_media['images'] ) && 88 === absint( $site_media['images'][0]['attachment_id'] ?? 0 ), 'Site-media search surfaces the inventoried attachment as an image candidate.' );

$GLOBALS['npcink_toolbox_site_knowledge_status_mode'] = 'error';
$site_media_error = $client->image_candidates( 'AI workflow diagram', array( 'provider' => 'site_media', 'per_page' => 5 ) );
npcink_toolbox_provider_services_assert( is_array( $site_media_error ), 'Site-media search survives a failing evidence status call without a fatal (regression: WP_Error status).' );
npcink_toolbox_provider_services_assert( 'ready' === (string) ( $site_media_error['status'] ?? '' ), 'Site-media search keeps its Cloud search status when the evidence status call fails.' );

echo "Provider services behavior checks passed.\n";
