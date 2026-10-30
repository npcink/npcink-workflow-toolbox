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
	return $GLOBALS['npcink_toolbox_pause_options'][ $key ] ?? $default;
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
			if ( isset( $GLOBALS['npcink_toolbox_status_coverage_fixture'] ) ) {
				return array( 'status' => 'ok', 'data' => array( 'result' => array( 'status' => 'ready', 'coverage' => $GLOBALS['npcink_toolbox_status_coverage_fixture'] ) ) );
			}
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

require_once dirname( __DIR__ ) . '/includes/Cloud_Error_Plain_Messages.php';
require_once dirname( __DIR__ ) . '/includes/Settings.php';
require_once dirname( __DIR__ ) . '/tests/load-provider-client.php';
require_once dirname( __DIR__ ) . '/includes/Publish_Preflight_Service.php';
require_once dirname( __DIR__ ) . '/includes/Editor_Content_Format.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Controller_Support.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Editor_Flow_Cache.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Editor_Taxonomy_Shaping.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Editor_Summary_Terms.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Editor_Writing_Pack_Shaping.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Editor_Progressive_Recommendations.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Editor_Media_Alt.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Surface_Bridges.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Editor_Internal_Links.php';
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
require_once dirname( __DIR__ ) . '/includes/Insight_Pack_Answer_Guidance.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Site_Ops_Insight_Bridges.php';
require_once dirname( __DIR__ ) . '/includes/Content_Structure_Insight_Builder.php';
require_once dirname( __DIR__ ) . '/includes/Page_Quality_Insight_Builder.php';
require_once dirname( __DIR__ ) . '/includes/Environment_Performance_Insight_Builder.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Content_Structure_Insight_Bridges.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Page_Quality_Insight_Bridges.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Environment_Performance_Insight_Bridges.php';
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


// Verify the default pause before exercising dormant implementation below.
$pause_before_calls = $npcink_toolbox_progressive_cloud_calls;
$paused_inspection = $client->submit_nightly_inspection_cloud_batch( array() );
$paused_site_ops = $client->run_site_ops_cloud_analysis( array() );
npcink_toolbox_provider_services_assert( is_wp_error( $paused_inspection ) && 'npcink_toolbox_feature_paused' === $paused_inspection->get_error_code(), 'Cloud inspection is paused before sampling or transport.' );
npcink_toolbox_provider_services_assert( is_wp_error( $paused_site_ops ) && 'npcink_toolbox_feature_paused' === $paused_site_ops->get_error_code(), 'Site Ops Cloud analysis is paused before transport.' );
npcink_toolbox_provider_services_assert( $pause_before_calls === $npcink_toolbox_progressive_cloud_calls, 'Paused Cloud features perform zero provider calls.' );
$paused_audio = $client->run_audio_generation( array( 'intent' => 'article_narration' ) );
npcink_toolbox_provider_services_assert( is_wp_error( $paused_audio ) && 'npcink_toolbox_feature_paused' === $paused_audio->get_error_code(), 'Audio is paused before summary generation or transport.' );
$paused_media = $client->build_media_derivative_handoff( array() );
npcink_toolbox_provider_services_assert( is_wp_error( $paused_media ) && 'npcink_toolbox_feature_paused' === $paused_media->get_error_code(), 'Media optimization planning is paused before provider or Core handoff.' );
$retained_feature_policy = new ReflectionProperty( Npcink_Toolbox\Settings::class, 'paused_cloud_features' );
$retained_feature_policy->setAccessible( true );
$retained_feature_snapshot = $retained_feature_policy->getValue();
$retained_feature_policy->setValue( null, array() );

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

// ---- Site Knowledge status round trip -----------------------------------

$status_bridge = new Npcink_Toolbox\Rest_Site_Knowledge_Bridges( $client );
$status_projection = new ReflectionMethod( $status_bridge, 'site_knowledge_article_index_statuses' );
$status_projection->setAccessible( true );
$status_service = new Npcink_Toolbox\Provider_Site_Knowledge_Service( $settings, $client );
foreach ( array( 80, 81, 1000, 1001 ) as $status_size ) {
	$status_ids = range( 10000, 10000 + $status_size - 1 );
	$compared_ids = array_slice( $status_ids, 0, 1000 );
	$GLOBALS['npcink_toolbox_status_coverage_fixture'] = array(
		'indexed_post_ids' => $compared_ids,
		'indexed_post_ids_requested' => count( $compared_ids ),
		'unrelated_items' => range( 1, 100 ),
	);
	$status_response = $client->get_site_knowledge_status( array( 'post_ids' => $status_ids, 'include_coverage' => true ) );
	$status_sent = end( $npcink_toolbox_progressive_site_knowledge_inputs );
	npcink_toolbox_provider_services_assert( $compared_ids === $status_sent['post_ids'] && $compared_ids === $status_response['coverage']['indexed_post_ids'], "Status round trip preserves the bounded manifest for {$status_size} input IDs." );
	npcink_toolbox_provider_services_assert( 80 === count( $status_response['coverage']['unrelated_items'] ), 'Unrelated coverage arrays retain the generic 80-item limit.' );
}

// Reuse the real REST row projection with a bounded local-row cache fixture.
$status_rows = array_map( static fn( int $id ): array => array( 'post_id' => $id, 'title' => 'Fixture article', 'url' => 'https://example.test/article/' . $id, 'modified_gmt' => '' ), $compared_ids );
$status_cache_key = 'npcink_toolbox_site_knowledge_rows_' . md5( count( $compared_ids ) . '||' . md5( implode( ',', $compared_ids ) ) );
set_transient( $status_cache_key, $status_rows, 300 );
$status_projected = $status_projection->invoke( $status_bridge, $status_response, $compared_ids );
npcink_toolbox_provider_services_assert( 1000 === count( $status_projected ) && array( 'indexed' ) === array_values( array_unique( array_column( $status_projected, 'status' ) ) ), 'All 1,000 indexed articles remain indexed beyond row 80.' );

foreach ( array( 'missing', 'non_array', 'nested', 'boolean', 'zero', 'oversized', 'mismatched', 'empty', 'subset' ) as $receipt_case ) {
	$coverage = array( 'indexed_post_ids_requested' => 1000, 'indexed_post_ids' => $compared_ids );
	if ( 'missing' === $receipt_case ) {
		unset( $coverage['indexed_post_ids'] );
	} elseif ( 'non_array' === $receipt_case ) {
		$coverage['indexed_post_ids'] = 'invalid';
	} elseif ( in_array( $receipt_case, array( 'nested', 'boolean', 'zero' ), true ) ) {
		$coverage['indexed_post_ids'][999] = 'nested' === $receipt_case ? array( 10999 ) : ( 'boolean' === $receipt_case ? true : 0 );
	} elseif ( 'oversized' === $receipt_case ) {
		$coverage['indexed_post_ids'][] = 99999;
	} elseif ( 'mismatched' === $receipt_case ) {
		$coverage['indexed_post_ids_requested'] = 80;
	} elseif ( 'empty' === $receipt_case ) {
		$coverage['indexed_post_ids'] = array();
	} elseif ( 'subset' === $receipt_case ) {
		$coverage['indexed_post_ids'] = array_slice( $compared_ids, 500 );
	}
	$GLOBALS['npcink_toolbox_status_coverage_fixture'] = $coverage;
	$status_response = $client->get_site_knowledge_status( array( 'post_ids' => $compared_ids ) );
	$projected = $status_projection->invoke( $status_bridge, $status_response, $compared_ids );
	if ( in_array( $receipt_case, array( 'empty', 'subset' ), true ) ) {
		$expected_indexed = 'subset' === $receipt_case ? 500 : 0;
		npcink_toolbox_provider_services_assert( 1000 === count( $projected ) && $expected_indexed === count( array_filter( $projected, static fn( array $row ): bool => 'indexed' === $row['status'] ) ), "Complete {$receipt_case} Cloud evidence distinguishes indexed from not indexed." );
	} else {
		npcink_toolbox_provider_services_assert( array() === $projected, "The {$receipt_case} receipt suppresses article verdicts." );
	}
}
unset( $GLOBALS['npcink_toolbox_status_coverage_fixture'] );
$client->get_site_knowledge_status( array( 'post_ids' => array( 5, '5', 0, 9 ), 'media_attachment_ids' => range( 1, 21 ) ) );
$status_sent = end( $npcink_toolbox_progressive_site_knowledge_inputs );
npcink_toolbox_provider_services_assert( array( 5, 9 ) === $status_sent['post_ids'] && range( 1, 20 ) === $status_sent['media_attachment_ids'], 'The public status producer normalizes IDs and retains the 20-media-ID cap.' );

foreach ( array( array( 'npcink-cloud/site-knowledge-search', 'site_knowledge_status.v1' ), array( 'npcink-cloud/site-knowledge-status', 'site_knowledge_search.v1' ) ) as $wrong_contract ) {
	$bounded = $status_service->sanitize_runtime_input( array( 'post_ids' => range( 1, 1000 ), 'nested' => array( 'post_ids' => range( 1, 1000 ) ) ), $wrong_contract[0], $wrong_contract[1] );
	npcink_toolbox_provider_services_assert( 80 === count( $bounded['post_ids'] ) && 80 === count( $bounded['nested']['post_ids'] ), 'Both exact ability and status contract are required to widen the manifest bound.' );
	$normalized = $status_service->normalize_site_knowledge_cloud_response( array( 'coverage' => array( 'indexed_post_ids' => range( 1, 1000 ) ) ), 'site_knowledge_results', 'site_knowledge_context', array( 'ability_name' => $wrong_contract[0], 'contract_version' => $wrong_contract[1] ) );
	npcink_toolbox_provider_services_assert( 80 === count( $normalized['coverage']['indexed_post_ids'] ), 'Other response contracts retain generic bounds.' );
}
$bounded = $status_service->sanitize_runtime_input( array( 'post_ids' => array( 5, '5', 0, 9 ), 'media_attachment_ids' => range( 1, 20 ), 'nested' => array( 'post_ids' => range( 1, 1000 ) ) ), 'npcink-cloud/site-knowledge-status', 'site_knowledge_status.v1' );
npcink_toolbox_provider_services_assert( array( 5, 9 ) === $bounded['post_ids'] && 20 === count( $bounded['media_attachment_ids'] ) && 80 === count( $bounded['nested']['post_ids'] ), 'Status preserves ID normalization and media bounds without widening nested fields.' );

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

// Pause rejects specialist work before any paid call, while preserving stored settings.
$pause_settings = new Npcink_Toolbox\Settings();
$GLOBALS['npcink_toolbox_pause_options']['npcink_toolbox_content_context'] = array(
	'seo_rules'               => 'old search rules',
	'aeo_rules'               => 'old answer rules',
	'geo_rules'               => 'old entity rules',
	'allow_faq_generation'    => true,
	'allow_aeo_summary'       => true,
	'allow_geo_summary'       => true,
	'proposal_allowed_fields' => array( 'seo_title', 'seo_description', 'geo_summary', 'excerpt', 'slug' ),
);
$pause_saved = $pause_settings->sanitize_content_context( array( 'site_positioning' => 'New positioning' ) );
npcink_toolbox_progressive_assert( 'old search rules' === $pause_saved['seo_rules'] && true === $pause_saved['allow_geo_summary'], 'Hidden specialist rules and flags survive a normal profile save.' );
npcink_toolbox_progressive_assert( $pause_saved['proposal_allowed_fields'] === $GLOBALS['npcink_toolbox_pause_options']['npcink_toolbox_content_context']['proposal_allowed_fields'], 'The saved specialist field selection is preserved exactly.' );
$pause_projection = $pause_settings->get_content_context_for_ability();
npcink_toolbox_progressive_assert( '' === $pause_projection['rules']['seo'] && false === $pause_projection['rules']['allow_geo_summary'] && array( 'excerpt', 'slug' ) === $pause_projection['proposal_allowed_fields'], 'Runtime context masks specialist preferences without mutating stored data.' );
$pause_client = new Npcink_Toolbox\Provider_Client( $pause_settings );
$pause_calls  = $npcink_toolbox_progressive_cloud_calls;
$pause_result = $pause_client->build_content_discoverability_brief( array( 'topic' => 'SEO pause' ) );
npcink_toolbox_progressive_assert( is_wp_error( $pause_result ) && 'npcink_toolbox_feature_paused' === $pause_result->get_error_code() && $pause_calls === $npcink_toolbox_progressive_cloud_calls, 'Specialist brief is rejected before Cloud search or Provider work.' );

$pause_plan = $pause_client->build_content_metadata_apply_plan(
	array(
		'post_id'   => 1,
		'seo_title' => 'Old candidate',
	)
);
npcink_toolbox_progressive_assert( is_wp_error( $pause_plan ) && 'npcink_toolbox_feature_paused' === $pause_plan->get_error_code(), 'A stale structured SEO candidate cannot pass through the generic metadata planner.' );
if ( ! function_exists( 'remove_accents' ) ) {
	function remove_accents( $text ): string {
		return (string) $text; }
}
$pause_pack = $pause_client->build_ai_article_writing_pack(
	array(
		'title'                   => 'General writing',
		'topic'                   => 'General writing',
		'include_external_search' => false,
	)
);
npcink_toolbox_progressive_assert( is_array( $pause_pack ) && ! isset( $pause_pack['discoverability_brief']['seo'] ) && ! in_array( 'geo_summary', array_column( $pause_pack['suggested_article_structure'], 'section' ), true ), 'General writing preparation remains available without specialist brief sections or GEO instructions.' );

require_once dirname( __DIR__ ) . '/includes/Site_Content_Answer.php';
require_once dirname( __DIR__ ) . '/includes/Abilities.php';
function wp_register_ability( $id, $definition ) { $GLOBALS['pause_native_abilities'][] = $id; }
function npcink_abilities_toolkit_register_readonly( $id, $definition ) { $GLOBALS['pause_helper_abilities'][] = $id; }
$pause_abilities = new Npcink_Toolbox\Abilities( $pause_settings, $pause_client );
$pause_abilities->register_native_abilities();
$pause_abilities->register_with_npcink_abilities_toolkit();
foreach ( array( 'pause_native_abilities', 'pause_helper_abilities' ) as $registration_lane ) {
    npcink_toolbox_progressive_assert( ! in_array( 'npcink-toolbox/build-content-discoverability-brief', $GLOBALS[ $registration_lane ], true ) && in_array( 'npcink-toolbox/get-content-discoverability-context', $GLOBALS[ $registration_lane ], true ), 'Specialist discovery is absent while generic context remains in ' . $registration_lane . '.' );
}

npcink_toolbox_progressive_assert( ! isset( $pause_pack['handoff']['brief_ability_id'] ) && false === strpos( implode( ' ', $pause_pack['handoff']['next_steps'] ), 'SEO/AEO/GEO' ), 'Retained writing packs do not send clients to a paused specialist ability or next step.' );
foreach ( array( 'discoverability', 'publish_preflight' ) as $diagnostic_scenario ) {
    $diagnostic = $pause_client->diagnose_automatic_web_search( array( 'topic' => 'Generic source research', 'scenario' => $diagnostic_scenario ) );
    npcink_toolbox_progressive_assert( is_array( $diagnostic ) && 'web_search_diagnostics' === ( $diagnostic['artifact_type'] ?? '' ), 'Generic web-search diagnostics remain callable during the specialist pause: ' . $diagnostic_scenario );
}
$pause_surface = new Npcink_Toolbox\Rest_Surface_Bridges( $pause_settings, $pause_client );
$pause_projection_method = new ReflectionMethod( $pause_surface, 'content_operations_projection' );
$pause_projection_method->setAccessible( true );
$pause_projection = $pause_projection_method->invoke( $pause_surface, true );
npcink_toolbox_progressive_assert( ! in_array( 'discoverability', $pause_projection['surfaces']['editor_content_support']['intents'], true ) && 'paused' === $pause_projection['gap_contracts']['seo_metadata_suggestion.v1']['state'] && false === $pause_projection['gap_contracts']['seo_metadata_suggestion.v1']['available'] && ! isset( $pause_projection['gap_contracts']['seo_metadata_suggestion.v1']['target_ability_id'] ), 'Ready Cloud status does not advertise paused specialist intents or SEO write handoffs.' );

$retained_feature_policy->setValue( null, $retained_feature_snapshot );

$GLOBALS['pause_native_abilities'] = array();
$paused_media_abilities = new Npcink_Toolbox\Abilities( $pause_settings, $pause_client );
$paused_media_abilities->register_native_abilities();
npcink_toolbox_progressive_assert( ! in_array( 'npcink-toolbox/build-media-derivative-handoff', $GLOBALS['pause_native_abilities'], true ) && in_array( 'npcink-toolbox/get-site-knowledge-status', $GLOBALS['pause_native_abilities'], true ), 'Paused media planning is undiscoverable while Site Knowledge remains discoverable.' );
$default_projection = $pause_projection_method->invoke( $pause_surface, true );
npcink_toolbox_progressive_assert( false === $default_projection['surfaces']['nightly_inspection']['available'], 'Ready Cloud does not advertise paused inspection availability.' );

$GLOBALS['npcink_toolbox_pause_options'][Npcink_Toolbox\Plugin::OPTION_NAME] = array(
    'nightly_inspection_pro_enabled' => true,
    'nightly_inspection_cloud_payload_mode' => 'excerpt',
    'nightly_inspection_cloud_retention_days' => 14,
);
$paused_saved_settings = $pause_settings->sanitize( array( 'nightly_inspection_enabled' => true ) );
npcink_toolbox_progressive_assert( true === $paused_saved_settings['nightly_inspection_pro_enabled'] && 'excerpt' === $paused_saved_settings['nightly_inspection_cloud_payload_mode'] && 14 === $paused_saved_settings['nightly_inspection_cloud_retention_days'], 'An ordinary local settings save preserves paused Cloud preferences exactly.' );
npcink_toolbox_progressive_assert( false === $pause_settings->get_nightly_inspection_settings()['pro_enabled'], 'Retained enabled preference cannot restore effective Cloud inspection availability.' );
