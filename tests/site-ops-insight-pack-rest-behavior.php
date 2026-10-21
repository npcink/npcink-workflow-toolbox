<?php
/**
 * Behavior checks for the Site Ops insight pack REST bridge (read-only pack + MVP QA).
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

		public function get_header( string $key ) {
			return null;
		}

		public function get_param( string $key ) {
			return null;
		}
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
		return $GLOBALS['site_ops_rest_options'][ $name ] ?? $default;
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
		return array();
	}
}
if ( ! function_exists( 'get_comments' ) ) {
	function get_comments( array $args = array() ): array {
		return array();
	}
}
if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( array $args = array() ) {
		return array();
	}
}
if ( ! function_exists( 'wp_count_posts' ) ) {
	function wp_count_posts( $type = 'post' ) {
		return (object) array( 'publish' => 0 );
	}
}
if ( ! function_exists( 'wp_count_attachments' ) ) {
	function wp_count_attachments() {
		return (object) array( 'image' => 0 );
	}
}
if ( ! function_exists( 'wp_count_comments' ) ) {
	function wp_count_comments() {
		return (object) array( 'approved' => 0, 'moderated' => 0, 'spam' => 0, 'trash' => 0, 'total_comments' => 0 );
	}
}
if ( ! function_exists( 'has_filter' ) ) {
	function has_filter( $hook ) {
		return false;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		return $value;
	}
}
if ( ! function_exists( 'is_plugin_active' ) ) {
	function is_plugin_active( $plugin ): bool {
		return false;
	}
}
if ( ! function_exists( 'get_plugins' ) ) {
	function get_plugins(): array {
		return array();
	}
}

$GLOBALS['site_ops_rest_options'] = array();

function site_ops_rest_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

require_once dirname( __DIR__ ) . '/includes/Plugin.php';
require_once dirname( __DIR__ ) . '/includes/Settings.php';
require_once dirname( __DIR__ ) . '/includes/Site_Ops_Snapshot_Collector.php';
require_once dirname( __DIR__ ) . '/includes/Site_Ops_Insight_Builder.php';
require_once dirname( __DIR__ ) . '/includes/Insight_Pack_Answer_Guidance.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Site_Ops_Insight_Bridges.php';

$bridge_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Rest_Site_Ops_Insight_Bridges.php' );
site_ops_rest_assert( false === strpos( $bridge_source, 'run_site_ops_cloud_analysis' ), 'Bridge source never calls run_site_ops_cloud_analysis.' );
site_ops_rest_assert( false === strpos( $bridge_source, 'cloud_inspection' ), 'Bridge source does not reference cloud_inspection pause bypass.' );
site_ops_rest_assert( false === strpos( $bridge_source, 'Site_Ops_Cloud_Request_Builder' ), 'Bridge source does not assemble Cloud request payloads.' );
site_ops_rest_assert( false !== strpos( $bridge_source, 'site_ops_insight_pack_qa' ), 'Bridge exposes site_ops_insight_pack_qa for MVP UI.' );
site_ops_rest_assert( false !== strpos( $bridge_source, 'execute_runtime' ), 'QA path uses Runtime Client execute_runtime.' );
site_ops_rest_assert( false !== strpos( $bridge_source, 'hosted_ai_content_support.v1' ), 'QA path uses hosted_ai_content_support.v1.' );
site_ops_rest_assert( false === strpos( $bridge_source, 'summary_prompt_mode' ), 'QA payload omits rejected summary_prompt_mode.' );
site_ops_rest_assert( false === strpos( $bridge_source, 'http_timeout_seconds' ), 'QA payload omits rejected http_timeout_seconds.' );
site_ops_rest_assert( false === strpos( $bridge_source, 'connect_timeout_seconds' ), 'QA payload omits rejected connect_timeout_seconds.' );

$settings = new Npcink_Toolbox\Settings();
$bridge   = new Npcink_Toolbox\Rest_Site_Ops_Insight_Bridges( $settings );
$pack     = $bridge->assemble_insight_pack();

site_ops_rest_assert( is_array( $pack ), 'assemble_insight_pack returns an array.' );
site_ops_rest_assert( Npcink_Toolbox\Site_Ops_Insight_Builder::CONTRACT_VERSION === ( $pack['contract_version'] ?? null ), 'Pack carries site_ops_insight_pack.v1 contract_version.' );
site_ops_rest_assert( 'site_ops_insight_pack' === ( $pack['artifact_type'] ?? null ), 'Pack artifact_type is site_ops_insight_pack.' );
site_ops_rest_assert( false === (bool) ( $pack['direct_wordpress_write'] ?? true ), 'Pack declares no direct WordPress write.' );
site_ops_rest_assert( 'suggestion_only' === ( $pack['write_posture'] ?? null ), 'Pack write_posture is suggestion_only.' );
site_ops_rest_assert( empty( $pack['safety']['cloud_called'] ), 'Pack safety marks cloud_called false.' );
site_ops_rest_assert( empty( $pack['safety']['comment_text_returned'] ), 'Pack omits comment text.' );
site_ops_rest_assert( empty( $pack['safety']['comment_author_email_returned'] ), 'Pack omits comment author email.' );
site_ops_rest_assert( empty( $pack['safety']['comment_ip_returned'] ), 'Pack omits comment IP.' );
site_ops_rest_assert( isset( $pack['summary'], $pack['top_findings'], $pack['data_sources'] ), 'Pack includes summary, top_findings, and data_sources.' );

$response = $bridge->site_ops_insight_pack( new WP_REST_Request( '/npcink-toolbox/v1/site-ops/insight-pack', 'GET' ) );
site_ops_rest_assert( $response instanceof WP_REST_Response, 'REST handler returns WP_REST_Response.' );
$data = $response->get_data();
site_ops_rest_assert( is_array( $data ) && Npcink_Toolbox\Site_Ops_Insight_Builder::CONTRACT_VERSION === ( $data['contract_version'] ?? null ), 'REST response body is the insight pack with contract version.' );

$controller_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Rest_Controller.php' );
site_ops_rest_assert( false !== strpos( $controller_source, "\$this->get( '/site-ops/insight-pack', 'site_ops_insight_pack' )" ), 'Rest_Controller registers GET /site-ops/insight-pack.' );
site_ops_rest_assert( false !== strpos( $controller_source, "\$this->post( '/site-ops/insight-pack-qa', 'site_ops_insight_pack_qa' )" ), 'Rest_Controller registers POST /site-ops/insight-pack-qa.' );
// Whitespace-insensitive: phpcs aligns this route map, so the number of spaces
// before => depends on the neighbouring keys. Pinning an exact run of spaces
// would encode formatting instead of behaviour.
site_ops_rest_assert( 1 === preg_match( "~'/site-ops/insight-pack'\s+=> 'cap\.toolbox\.admin'~", $controller_source ), 'Rest_Controller scopes insight-pack to cap.toolbox.admin (manage_options default).' );
site_ops_rest_assert( 1 === preg_match( "~'/site-ops/insight-pack-qa'\s+=> 'cap\.toolbox\.admin'~", $controller_source ), 'Rest_Controller scopes insight-pack-qa to cap.toolbox.admin.' );
site_ops_rest_assert( false !== strpos( $controller_source, 'return $this->site_ops_insight_bridges->site_ops_insight_pack( $request );' ), 'Rest_Controller delegates to the Site Ops insight bridge.' );
site_ops_rest_assert( false !== strpos( $controller_source, 'return $this->site_ops_insight_bridges->site_ops_insight_pack_qa( $request );' ), 'Rest_Controller delegates insight-pack-qa to the Site Ops insight bridge.' );

$admin_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Admin_Page.php' );
$js_source    = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin.js' );
site_ops_rest_assert( false !== strpos( $admin_source, 'data-toolbox-smart-ask' ) && false !== strpos( $admin_source, "'smart-ask'           => true" ) && false !== strpos( $admin_source, 'data-toolbox-tab-target="smart-ask"' ), 'Smart Ask tab renders the unified Ask shell.' );
site_ops_rest_assert( false === strpos( $admin_source, 'data-toolbox-insight-pack-qa' ) && false !== strpos( $admin_source, "render_smart_ask_link( 'site_ops' )" ) && false !== strpos( $admin_source, 'Go to Smart Ask' ), 'Site Check Current check links to Smart Ask instead of hosting its own Ask card.' );
site_ops_rest_assert( false !== strpos( $js_source, 'initSmartAsk' ) && false !== strpos( $js_source, 'postJson(config.restUrl, endpoint, { question })' ) && false !== strpos( $admin_source, "'endpoint'         => 'site-ops/insight-pack-qa'" ) && false !== strpos( $admin_source, "'contract'         => 'site_ops_insight_pack.v1'" ), 'Smart Ask registry routes the site check pack to insight-pack-qa REST.' );
site_ops_rest_assert( false !== strpos( $admin_source, 'Answers are for reference only and will not change your site.' ) && false !== strpos( $admin_source, 'data-toolbox-smart-ask-pack' ) && false !== strpos( $admin_source, "query_text_param( 'ask_pack' )" ), 'Smart Ask shows the reference-only notice, a pack selector, and preselect via ask_pack.' );
site_ops_rest_assert( false !== strpos( $js_source, 'renderSafeMarkdown' ) && false !== strpos( $js_source, 'setMarkdownContent' ) && false !== strpos( $js_source, 'escapeHtml' ), 'Ask UI renders Cloud answers through a sanitizing Markdown helper.' );
site_ops_rest_assert( false !== strpos( $admin_source, 'npcink-toolbox__markdown' ) && false !== strpos( $admin_source, 'data-toolbox-smart-ask-answer' ) && false === strpos( $admin_source, 'data-toolbox-smart-ask-answer style=' ), 'Ask UI answer surface is a Markdown container, not a raw <pre> dump.' );
site_ops_rest_assert( false !== strpos( $admin_source, 'aria-live="polite"' ) && false !== strpos( $js_source, 'is-ask-busy' ) && false !== strpos( $js_source, 'In progress: building the pack and asking Cloud…' ), 'Ask UI shows in-progress / busy loading state while a question is in flight.' );
// Whitespace-insensitive: phpcs requires this assignment block to be aligned, and
// how many spaces that takes depends on the neighbouring keys. Asserting an exact
// run of spaces would pin a formatting accident, not behaviour.
$busy_slot_bridge_wired = false !== strpos( $bridge_source, 'busy_slot' )
	&& 1 === preg_match( '/\$data\[\'status\'\]\s+= 429/', $bridge_source );
site_ops_rest_assert( false !== strpos( $js_source, 'isBusySlotError' ) && false !== strpos( $js_source, 'Another Cloud run is using this site' ) && $busy_slot_bridge_wired, 'Ask UI maps Cloud busy-slot / 429 to Chinese-friendly copy without raw status dumps.' );


// Plain-language answer guidance (shared by both packs).
$guidance_cls = 'Npcink_Toolbox\\Insight_Pack_Answer_Guidance';
$sys_prompt   = $guidance_cls::system_prompt( $guidance_cls::PACK_SITE_OPS );
site_ops_rest_assert( false !== strpos( $sys_prompt, '不懂技术' ) && false !== strpos( $sys_prompt, '日常口语' ), 'Ask prompt targets a non-technical Chinese site owner.' );
site_ops_rest_assert( false !== strpos( $sys_prompt, '不要出现 JSON 字段名' ) && false !== strpos( $sys_prompt, '搜索结果里的简介' ) && false !== strpos( $sys_prompt, '内容太短的文章' ), 'Ask prompt bans raw field names and maps jargon to everyday words.' );
site_ops_rest_assert( false !== strpos( $sys_prompt, '结论：1–2 句话' ) && false !== strpos( $sys_prompt, '最多 3 件' ) && false !== strpos( $sys_prompt, '做什么 / 为什么 / 怎么做' ), 'Ask prompt enforces conclusion-first, max 3 what/why/how items.' );
site_ops_rest_assert( false !== strpos( $sys_prompt, '不含流量' ) && false !== strpos( $sys_prompt, '高流量页面' ) && false !== strpos( $sys_prompt, '回答里直接不谈这些' ), 'Ask prompt forbids citing metrics absent from the pack (traffic).' );
site_ops_rest_assert( false !== strpos( $sys_prompt, '不要在回答里解释或复述这些规则' ) && false === strpos( $sys_prompt, '需要时直说' ) && false === strpos( $sys_prompt, '摘要里没有流量数据' ), 'Ask prompt tells the model not to restate rules or missing data in the answer.' );
site_ops_rest_assert( false !== strpos( $sys_prompt, '只给建议' ), 'Ask prompt keeps suggestion-only posture.' );
site_ops_rest_assert( '只抽查了 24 篇（全站已发布 180 篇），结论仅供参考。' === $guidance_cls::sample_note( 24, 180 ), 'Small sample yields a top-of-answer caveat.' );
site_ops_rest_assert( '' === $guidance_cls::sample_note( 24, 24 ), 'Full-coverage sample adds no caveat.' );
site_ops_rest_assert( 0 === strpos( $guidance_cls::user_message( 'Q', 'x', '{}', '只抽查了 1 篇' ), '站长的问题：Q' ) && false !== strpos( $guidance_cls::user_message( 'Q', 'x', '{}', '只抽查了 1 篇' ), '第一行要写的话' ) && false === strpos( $guidance_cls::user_message( 'Q', 'x', '{}', '只抽查了 1 篇' ), '抽样提示' ), 'User message carries the sample caveat ahead of the pack JSON without an echo-able label.' );
site_ops_rest_assert( false !== strpos( $bridge_source, 'Insight_Pack_Answer_Guidance::system_prompt' ), 'Bridge uses the shared plain-language guidance.' );

echo "All Site Ops insight pack REST behavior checks passed.\n";
