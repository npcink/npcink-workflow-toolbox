<?php
/**
 * Behavior checks for content_structure_insight_pack REST (pack 2).
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wp-stub/' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		/** @var array<string,mixed> */
		private array $data;

		public function __construct( string $code, string $message = '', array $data = array() ) {
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

		/** @return array<string,mixed> */
		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		/** @var mixed */
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
		private string $route  = '';
		private string $method = 'GET';
		/** @var array<string,mixed> */
		private array $params = array();

		public function __construct( string $route = '', string $method = 'GET' ) {
			$this->route  = $route;
			$this->method = $method;
		}

		public function get_route(): string {
			return $this->route;
		}

		public function get_method(): string {
			return $this->method;
		}

		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}

		public function set_param( string $key, $value ): void {
			$this->params[ $key ] = $value;
		}
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int $ID                   = 0;
		public string $post_type         = 'post';
		public string $post_title        = '';
		public string $post_content      = '';
		public string $post_excerpt      = '';
		public string $post_date_gmt     = '';
		public string $post_modified_gmt = '';
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		return $text;
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ): bool {
		return $value instanceof WP_Error;
	}
}
if ( ! function_exists( 'rest_ensure_response' ) ) {
	function rest_ensure_response( $value ) {
		return $value instanceof WP_REST_Response ? $value : new WP_REST_Response( $value );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $value ) {
		return strip_tags( (string) $value );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ): int {
		return abs( (int) $value );
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return 'manage_options' === $capability || 'edit_posts' === $capability;
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $name, $default = false ) {
		return $GLOBALS['content_structure_rest_options'][ $name ] ?? $default;
	}
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( string $show ) {
		$map = array(
			'name'        => 'Example Site',
			'description' => 'Example description',
		);
		return $map[ $show ] ?? '';
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '/' ): string {
		return 'https://example.test' . $path;
	}
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		$parts = parse_url( (string) $url );
		if ( -1 === $component ) {
			return $parts;
		}
		$map = array(
			PHP_URL_SCHEME => 'scheme',
			PHP_URL_HOST   => 'host',
			PHP_URL_PORT   => 'port',
			PHP_URL_PATH   => 'path',
		);
		$key = $map[ $component ] ?? null;
		return null === $key ? null : ( $parts[ $key ] ?? null );
	}
}
if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( array $args = array() ): array {
		$post                    = new WP_Post();
		$post->ID                = 11;
		$post->post_type         = 'post';
		$post->post_title        = 'Sample Public Title';
		$post->post_content      = str_repeat( 'word ', 80 );
		$post->post_excerpt      = '';
		$post->post_date_gmt     = '2024-01-01 00:00:00';
		$post->post_modified_gmt = '2024-01-01 00:00:00';
		return array( $post );
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		return $single ? '' : array();
	}
}
if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	function get_post_thumbnail_id( $post_id ) {
		return 0;
	}
}
if ( ! function_exists( 'wp_get_post_terms' ) ) {
	function wp_get_post_terms( $post_id, $taxonomy, $args = array() ) {
		return array();
	}
}
if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( array $args = array() ) {
		$term = (object) array(
			'name'  => 'News',
			'count' => 3,
		);
		return array( $term );
	}
}
if ( ! function_exists( 'wp_count_posts' ) ) {
	function wp_count_posts( $type = 'post' ) {
		return (object) array(
			'publish' => 'post' === $type ? 4 : 1,
			'draft'   => 1,
			'pending' => 0,
			'private' => 0,
			'future'  => 0,
		);
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options );
	}
}
if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4(): string {
		return '00000000-0000-4000-8000-000000000001';
	}
}

$GLOBALS['content_structure_rest_options'] = array();

function content_structure_rest_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

require_once dirname( __DIR__ ) . '/includes/Plugin.php';
require_once dirname( __DIR__ ) . '/includes/Settings.php';
require_once dirname( __DIR__ ) . '/includes/Content_Structure_Insight_Builder.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Content_Structure_Insight_Bridges.php';

$bridge_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Rest_Content_Structure_Insight_Bridges.php' );
content_structure_rest_assert( false === strpos( $bridge_source, 'run_site_ops_cloud_analysis' ), 'Bridge never calls run_site_ops_cloud_analysis.' );
content_structure_rest_assert( false === strpos( $bridge_source, 'cloud_inspection' ), 'Bridge does not bypass cloud_inspection pause.' );
content_structure_rest_assert( false !== strpos( $bridge_source, 'execute_runtime' ), 'QA path uses execute_runtime.' );
content_structure_rest_assert( false !== strpos( $bridge_source, 'hosted_ai_content_support.v1' ), 'QA path uses hosted_ai_content_support.v1.' );
content_structure_rest_assert( false === strpos( $bridge_source, 'summary_prompt_mode' ), 'QA omits summary_prompt_mode.' );
content_structure_rest_assert( false === strpos( $bridge_source, 'http_timeout_seconds' ), 'QA omits http_timeout_seconds.' );
content_structure_rest_assert( false === strpos( $bridge_source, 'connect_timeout_seconds' ), 'QA omits connect_timeout_seconds.' );

$builder_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Content_Structure_Insight_Builder.php' );
content_structure_rest_assert( false !== strpos( $builder_source, 'word_count' ), 'Builder derives word_count locally (body never shipped).' );
content_structure_rest_assert( false !== strpos( $builder_source, "'post_body_returned'            => false" ), 'Pack safety marks post_body_returned false.' );

$settings = new Npcink_Toolbox\Settings();
$bridge   = new Npcink_Toolbox\Rest_Content_Structure_Insight_Bridges( $settings );
$pack     = $bridge->assemble_pack();

content_structure_rest_assert( is_array( $pack ), 'assemble_pack returns an array.' );
content_structure_rest_assert( Npcink_Toolbox\Content_Structure_Insight_Builder::CONTRACT_VERSION === ( $pack['contract_version'] ?? null ), 'Pack contract_version is content_structure_insight_pack.v1.' );
content_structure_rest_assert( 'content_structure_insight_pack' === ( $pack['artifact_type'] ?? null ), 'Pack artifact_type is content_structure_insight_pack.' );
content_structure_rest_assert( false === (bool) ( $pack['direct_wordpress_write'] ?? true ), 'Pack declares no direct WordPress write.' );
content_structure_rest_assert( 'suggestion_only' === ( $pack['write_posture'] ?? null ), 'Pack write_posture is suggestion_only.' );
content_structure_rest_assert( empty( $pack['safety']['post_body_returned'] ), 'Pack omits post body.' );
content_structure_rest_assert( empty( $pack['safety']['comment_text_returned'] ), 'Pack omits comment text.' );
content_structure_rest_assert( empty( $pack['safety']['non_public_titles_returned'] ), 'Pack omits non-public titles.' );
content_structure_rest_assert( isset( $pack['summary'], $pack['post_counts'], $pack['taxonomies'], $pack['structure_signals'], $pack['top_findings'] ), 'Pack includes summary, post_counts, taxonomies, structure_signals, top_findings.' );
content_structure_rest_assert( isset( $pack['post_counts']['post']['publish'] ), 'post_counts include published posts.' );
content_structure_rest_assert( ! isset( $pack['posts'] ), 'Pack does not ship a full posts array with bodies.' );

// Ensure sample titles exist but no raw body key on sample items.
$samples = $pack['structure_signals']['title_samples'] ?? array();
content_structure_rest_assert( is_array( $samples ), 'title_samples is an array.' );
foreach ( $samples as $sample ) {
	content_structure_rest_assert( is_array( $sample ) && ! array_key_exists( 'content', $sample ) && ! array_key_exists( 'post_content', $sample ), 'title_samples never include post body fields.' );
}

$response = $bridge->content_structure_insight_pack( new WP_REST_Request( '/npcink-toolbox/v1/content-structure/insight-pack', 'GET' ) );
content_structure_rest_assert( $response instanceof WP_REST_Response, 'REST GET returns WP_REST_Response.' );
$data = $response->get_data();
content_structure_rest_assert( is_array( $data ) && Npcink_Toolbox\Content_Structure_Insight_Builder::CONTRACT_VERSION === ( $data['contract_version'] ?? null ), 'REST body carries contract version.' );

$empty_q = new WP_REST_Request( '/npcink-toolbox/v1/content-structure/insight-pack-qa', 'POST' );
$empty_q->set_param( 'question', '' );
$empty_res = $bridge->content_structure_insight_pack_qa( $empty_q );
content_structure_rest_assert( is_wp_error( $empty_res ), 'QA rejects empty question.' );
content_structure_rest_assert( 'npcink_toolbox_content_structure_qa_question_required' === $empty_res->get_error_code(), 'Empty question uses required error code.' );

$controller_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Rest_Controller.php' );
content_structure_rest_assert( false !== strpos( $controller_source, "\$this->get( '/content-structure/insight-pack', 'content_structure_insight_pack' )" ), 'Rest_Controller registers GET /content-structure/insight-pack.' );
content_structure_rest_assert( false !== strpos( $controller_source, "\$this->post( '/content-structure/insight-pack-qa', 'content_structure_insight_pack_qa' )" ), 'Rest_Controller registers POST QA.' );
content_structure_rest_assert( false !== strpos( $controller_source, "'/content-structure/insight-pack'           => 'cap.toolbox.admin'" ), 'GET scoped to cap.toolbox.admin.' );
content_structure_rest_assert( false !== strpos( $controller_source, "'/content-structure/insight-pack-qa'        => 'cap.toolbox.admin'" ), 'QA scoped to cap.toolbox.admin.' );

$admin_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Admin_Page.php' );
$js_source    = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin.js' );
content_structure_rest_assert( false !== strpos( $admin_source, 'data-toolbox-content-structure-qa' ), 'Site Profile renders content-structure Ask UI.' );
content_structure_rest_assert( false !== strpos( $admin_source, 'Ask about content structure' ), 'Ask UI labeled as content structure.' );
content_structure_rest_assert( false !== strpos( $js_source, 'initContentStructurePackQa' ) && false !== strpos( $js_source, 'content-structure/insight-pack-qa' ), 'admin.js wires content-structure Ask to REST.' );
content_structure_rest_assert( false !== strpos( $js_source, 'is-ask-busy' ) && false !== strpos( $bridge_source, 'busy_slot' ), 'Ask UI / bridge share busy-slot handling.' );

$boundary = (string) file_get_contents( dirname( __DIR__ ) . '/docs/deidentified-assistant-boundary-v1.md' );
content_structure_rest_assert( false !== strpos( $boundary, 'content_structure_insight_pack.v1' ), 'Boundary doc names second pack contract.' );

echo "All content-structure insight pack REST behavior checks passed.\n";
