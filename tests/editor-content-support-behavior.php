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

		public function __construct( array $params = array() ) {
			$this->params = $params;
		}

		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}

		public function get_params(): array {
			return $this->params;
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
		global $npcink_toolbox_progressive_site_knowledge_inputs;
		$site_knowledge_runtime = is_array( $args[0] ?? null ) ? $args[0] : array();
		$npcink_toolbox_progressive_site_knowledge_inputs[] = is_array( $site_knowledge_runtime['input'] ?? null ) ? $site_knowledge_runtime['input'] : array();
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

function npcink_abilities_toolkit_get_registered(): array {
	return array(
		'npcink-abilities-toolkit/suggest-post-taxonomy-terms' => array(
			'execute_callback' => 'npcink_toolbox_progressive_taxonomy_suggestions',
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
	}
	class_alias( Npcink_Toolbox_Progressive_Plugin_Stub::class, 'Npcink_Toolbox\\Plugin' );
}

require_once dirname( __DIR__ ) . '/includes/Settings.php';
require_once dirname( __DIR__ ) . '/tests/load-provider-client.php';
require_once dirname( __DIR__ ) . '/includes/Publish_Preflight_Service.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Controller.php';

$settings   = new Npcink_Toolbox\Settings();
$client     = new Npcink_Toolbox\Provider_Client( $settings );
$preflight  = new Npcink_Toolbox\Publish_Preflight_Service();
$controller = new Npcink_Toolbox\Rest_Controller( $settings, $client, $preflight );

/**
 * Behavior coverage for the default editor sidebar button intents on
 * /editor/content-support. Pins dispatch, suggestion-only posture, fail-closed
 * Cloud/Toolkit absence, and the absence of any WordPress write call so the
 * handler can be refactored safely.
 */

$npcink_toolbox_editor_flow_writes = array();

function remove_accents( string $text ): string {
	return $text;
}

function wp_html_excerpt( string $str, int $count, string $more = '' ): string {
	$words = preg_split( '/\s+/', trim( $str ) );
	$short = is_array( $words ) ? implode( ' ', array_slice( $words, 0, $count ) ) : $str;
	return $short . ( is_array( $words ) && count( $words ) > $count ? $more : '' );
}

function current_user_can( string $capability ): bool {
	return true;
}

function npcink_toolbox_editor_flow_record_write( string $fn ): void {
	global $npcink_toolbox_editor_flow_writes;
	$npcink_toolbox_editor_flow_writes[] = $fn;
}

function wp_insert_post( $postarr, $wp_error = false ) {
	npcink_toolbox_editor_flow_record_write( 'wp_insert_post' );
	return 0;
}
function wp_update_post( $postarr = array() ) {
	npcink_toolbox_editor_flow_record_write( 'wp_update_post' );
	return 0;
}
function wp_delete_post( $postid = 0, $force = false ) {
	npcink_toolbox_editor_flow_record_write( 'wp_delete_post' );
	return false;
}
function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) {
	npcink_toolbox_editor_flow_record_write( 'wp_set_object_terms' );
	return array();
}
function set_post_thumbnail( $post, $thumbnail_id ) {
	npcink_toolbox_editor_flow_record_write( 'set_post_thumbnail' );
	return false;
}
function update_post_meta( $post_id, $meta_key, $meta_value ) {
	npcink_toolbox_editor_flow_record_write( 'update_post_meta' );
	return false;
}
function wp_update_attachment_metadata( $attachment_id, $data ) {
	npcink_toolbox_editor_flow_record_write( 'wp_update_attachment_metadata' );
	return false;
}
function wp_insert_attachment( ...$args ) {
	npcink_toolbox_editor_flow_record_write( 'wp_insert_attachment' );
	return 0;
}

function npcink_toolbox_editor_flow_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

/**
 * @return array{error?: WP_Error, data?: array}
 */
function npcink_toolbox_editor_flow_call( Npcink_Toolbox\Rest_Controller $controller, array $payload ): array {
	$response = $controller->editor_content_support( new WP_REST_Request( $payload ) );
	if ( $response instanceof WP_Error ) {
		return array( 'error' => $response );
	}
	return array( 'data' => is_array( $response->get_data() ) ? $response->get_data() : array() );
}

function npcink_toolbox_editor_flow_error_status( WP_Error $error ): int {
	$data = $error->get_error_data();
	return is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;
}

$npcink_toolbox_editor_flow_draft = array(
	'title'   => 'AI Workflow 渐进推荐快速建议',
	'excerpt' => 'Recommendation workflow for operators.',
	'content' => '渐进推荐 workflow with recommendation steps, internal link guidance, and publish readiness checks for WordPress operators.',
);

// 1. Unknown intent is rejected before any context or Cloud work.
$unknown = npcink_toolbox_editor_flow_call( $controller, array( 'intent' => 'definitely_not_an_intent' ) + $npcink_toolbox_editor_flow_draft );
npcink_toolbox_editor_flow_assert( isset( $unknown['error'] ) && 'npcink_toolbox_invalid_editor_support_intent' === $unknown['error']->get_error_code(), 'Unknown editor intent is rejected with the invalid-intent error.' );
npcink_toolbox_editor_flow_assert( 400 === npcink_toolbox_editor_flow_error_status( $unknown['error'] ), 'Unknown editor intent rejection is a 400.' );

// 2. Empty draft context is rejected for context-dependent intents.
$empty_context = npcink_toolbox_editor_flow_call( $controller, array( 'intent' => 'internal_links' ) );
npcink_toolbox_editor_flow_assert( isset( $empty_context['error'] ) && 'npcink_toolbox_missing_editor_context' === $empty_context['error']->get_error_code(), 'Empty draft context is rejected for internal links.' );

// 3. Paragraph review requires an explicit selection.
$no_selection = npcink_toolbox_editor_flow_call( $controller, array( 'intent' => 'polish_notes' ) + $npcink_toolbox_editor_flow_draft );
npcink_toolbox_editor_flow_assert( isset( $no_selection['error'] ) && 'npcink_toolbox_missing_editor_selection' === $no_selection['error']->get_error_code(), 'Paragraph review without a selection is rejected.' );

/**
 * Checks shared by every successful default-button intent.
 */
function npcink_toolbox_editor_flow_assert_flow_contract( array $data, string $intent, string $message ): void {
	npcink_toolbox_editor_flow_assert( 'editor_content_support_flow' === (string) ( $data['artifact_type'] ?? '' ), $message . ': artifact stays editor_content_support_flow.' );
	npcink_toolbox_editor_flow_assert( $intent === (string) ( $data['intent'] ?? '' ), $message . ': response records the requested intent.' );
	npcink_toolbox_editor_flow_assert( 'suggestion_only' === (string) ( $data['write_posture'] ?? '' ), $message . ': write posture stays suggestion_only.' );
	npcink_toolbox_editor_flow_assert( false === ( $data['direct_wordpress_write'] ?? true ), $message . ': no direct WordPress write flag.' );
	npcink_toolbox_editor_flow_assert( 'core_proposal_required' === (string) ( $data['final_write_path'] ?? '' ), $message . ': final writes stay on the Core proposal path.' );
	npcink_toolbox_editor_flow_assert( false === ( $data['remote_execution_policy']['workflow_runtime'] ?? true ), $message . ': no workflow runtime claim.' );
	npcink_toolbox_editor_flow_assert( isset( $data['recommendation_set']['content_fingerprint'] ) && '' !== (string) $data['recommendation_set']['content_fingerprint'], $message . ': recommendation set carries a content fingerprint.' );
}

// 4. Category suggestions use the Toolkit ranking path suggestion-only.
$category = npcink_toolbox_editor_flow_call( $controller, array( 'intent' => 'category_suggestions' ) + $npcink_toolbox_editor_flow_draft );
npcink_toolbox_editor_flow_assert( isset( $category['data'] ), 'Category suggestions return a flow response.' );
npcink_toolbox_editor_flow_assert_flow_contract( $category['data'], 'category_suggestions', 'Category suggestions' );
npcink_toolbox_editor_flow_assert( isset( $category['data']['sections']['summary_terms_optimization'] ), 'Category suggestions fill the summary terms optimization section.' );

// 5. Tag suggestions keep the same contract.
$tags = npcink_toolbox_editor_flow_call( $controller, array( 'intent' => 'tag_suggestions' ) + $npcink_toolbox_editor_flow_draft );
npcink_toolbox_editor_flow_assert( isset( $tags['data'] ), 'Tag suggestions return a flow response.' );
npcink_toolbox_editor_flow_assert_flow_contract( $tags['data'], 'tag_suggestions', 'Tag suggestions' );

// 6. Internal links fail soft when the Toolkit resolver ability is absent.
$links = npcink_toolbox_editor_flow_call( $controller, array( 'intent' => 'internal_links' ) + $npcink_toolbox_editor_flow_draft );
npcink_toolbox_editor_flow_assert( isset( $links['data'] ), 'Internal links return a flow response even without the Toolkit resolver.' );
npcink_toolbox_editor_flow_assert_flow_contract( $links['data'], 'internal_links', 'Internal links' );
npcink_toolbox_editor_flow_assert( isset( $links['data']['sections']['internal_links'] ) && is_array( $links['data']['sections']['internal_links'] ), 'Internal links fill their section without a fatal.' );

// 7. Image candidates fail closed when Cloud transport is unavailable.
$images = npcink_toolbox_editor_flow_call( $controller, array( 'intent' => 'image_candidates' ) + $npcink_toolbox_editor_flow_draft );
npcink_toolbox_editor_flow_assert( isset( $images['data'] ), 'Image candidates return a flow response even when Cloud is unavailable.' );
npcink_toolbox_editor_flow_assert_flow_contract( $images['data'], 'image_candidates', 'Image candidates' );
npcink_toolbox_editor_flow_assert( isset( $images['data']['sections']['image_candidates'] ) && is_array( $images['data']['sections']['image_candidates'] ), 'Image candidates fill their section without a fatal.' );

// 8. Publish preflight stays advisory with Cloud-dependent sections degraded.
$preflight_run = npcink_toolbox_editor_flow_call( $controller, array( 'intent' => 'publish_preflight' ) + $npcink_toolbox_editor_flow_draft );
npcink_toolbox_editor_flow_assert( isset( $preflight_run['data'] ), 'Publish preflight returns a flow response.' );
npcink_toolbox_editor_flow_assert_flow_contract( $preflight_run['data'], 'publish_preflight', 'Publish preflight' );
npcink_toolbox_editor_flow_assert( isset( $preflight_run['data']['sections']['discoverability'] ), 'Publish preflight includes the discoverability section.' );

// 9. No default-button intent performed any WordPress write, and none of them
//    fired a hosted-AI writing-pack/draft request or an external source-reader
//    fetch: those stay explicit follow-up actions for the writing-pack flow.
npcink_toolbox_editor_flow_assert( array() === $npcink_toolbox_editor_flow_writes, 'No editor content-support intent performed a WordPress write (' . implode( ', ', $npcink_toolbox_editor_flow_writes ) . ').' );
npcink_toolbox_editor_flow_assert( array() === $npcink_toolbox_progressive_writing_pack_inputs, 'No default-button intent fired a hosted-AI writing-pack or draft request.' );
npcink_toolbox_editor_flow_assert( 1 === $npcink_toolbox_progressive_source_reader_calls, 'Only publish preflight requests external search evidence; other default intents fire none.' );

echo "Editor content support behavior checks passed.\n";
