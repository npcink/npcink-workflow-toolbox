<?php
/**
 * Behavior checks for environment_performance_insight_pack REST (pack 4).
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

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = null ) {
		return $text;
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return is_string( $str ) ? trim( $str ) : '';
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return is_string( $str ) ? trim( $str ) : '';
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options );
	}
}
if ( ! function_exists( 'rest_ensure_response' ) ) {
	function rest_ensure_response( $data ) {
		return $data instanceof WP_REST_Response ? $data : new WP_REST_Response( $data );
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		return '00000000-0000-4000-8000-000000000001';
	}
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		return 'version' === $show ? '6.6.2' : '';
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '/' ) {
		return 'https://example.test' . $path;
	}
}
if ( ! function_exists( 'site_url' ) ) {
	function site_url( $path = '/' ) {
		return 'https://example.test' . $path;
	}
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		$parts = parse_url( $url );
		if ( PHP_URL_HOST === $component ) {
			return $parts['host'] ?? null;
		}
		return $parts;
	}
}
if ( ! function_exists( 'is_multisite' ) ) {
	function is_multisite() {
		return false;
	}
}
if ( ! function_exists( 'get_stylesheet' ) ) {
	function get_stylesheet() {
		return 'demo-theme';
	}
}
if ( ! function_exists( 'wp_get_theme' ) ) {
	function wp_get_theme() {
		return new class() {
			public function get( $key ) {
				return 'Version' === $key ? '1.0.0' : '';
			}
			public function get_stylesheet() {
				return 'demo-theme';
			}
		};
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		if ( 'active_plugins' === $name ) {
			return array( 'hello.php', 'akismet/akismet.php' );
		}
		return $default;
	}
}
if ( ! function_exists( 'get_plugins' ) ) {
	function get_plugins() {
		return array(
			'hello.php'           => array( 'Version' => '1.7' ),
			'akismet/akismet.php' => array( 'Version' => '5.0' ),
		);
	}
}
if ( ! function_exists( 'wp_count_posts' ) ) {
	function wp_count_posts( $type = 'post' ) {
		return (object) array( 'publish' => 'post' === $type ? 10 : 2 );
	}
}
if ( ! function_exists( 'wp_count_attachments' ) ) {
	function wp_count_attachments() {
		return (object) array( 'image' => 3 );
	}
}
if ( ! function_exists( 'wp_using_ext_object_cache' ) ) {
	function wp_using_ext_object_cache() {
		return false;
	}
}
if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		return array( 'basedir' => sys_get_temp_dir(), 'error' => false );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		return $value;
	}
}
if ( ! class_exists( 'Npcink_Toolbox\\Settings', false ) ) {
	eval( 'namespace Npcink_Toolbox; class Settings {}' );
}

function environment_performance_rest_assert( bool $condition, string $message ): void {
	if ( $condition ) {
		echo 'PASS: ' . $message . PHP_EOL;
		return;
	}
	fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
	exit( 1 );
}

require_once dirname( __DIR__ ) . '/includes/Environment_Performance_Insight_Builder.php';
require_once dirname( __DIR__ ) . '/includes/Insight_Pack_Answer_Guidance.php';
require_once dirname( __DIR__ ) . '/includes/Rest_Environment_Performance_Insight_Bridges.php';

$bridge_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Rest_Environment_Performance_Insight_Bridges.php' );
environment_performance_rest_assert( false === strpos( $bridge_source, 'run_site_ops_cloud_analysis' ), 'Bridge never calls run_site_ops_cloud_analysis.' );
environment_performance_rest_assert( false === strpos( $bridge_source, 'cloud_inspection' ), 'Bridge does not bypass cloud_inspection pause.' );
environment_performance_rest_assert( false !== strpos( $bridge_source, 'execute_runtime' ), 'QA path uses execute_runtime.' );
environment_performance_rest_assert( false !== strpos( $bridge_source, 'hosted_ai_content_support.v1' ), 'QA path uses hosted_ai_content_support.v1.' );
environment_performance_rest_assert( false === strpos( $bridge_source, 'summary_prompt_mode' ), 'QA omits summary_prompt_mode.' );

$builder_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Environment_Performance_Insight_Builder.php' );
environment_performance_rest_assert( false !== strpos( $builder_source, 'config_and_inventory_only' ), 'Builder marks measurement_kind config_and_inventory_only.' );
environment_performance_rest_assert( false === strpos( $builder_source, 'Lighthouse' ) || false !== strpos( $builder_source, 'Never returns' ), 'Builder docs forbid Lighthouse scores.' );
environment_performance_rest_assert( false !== strpos( $builder_source, "'secrets_returned'         => false" ), 'Safety marks secrets_returned false.' );
environment_performance_rest_assert( false !== strpos( $builder_source, "'speed_score_returned'     => false" ), 'Safety marks speed_score_returned false.' );
environment_performance_rest_assert( false !== strpos( $builder_source, "'absolute_paths_returned'  => false" ), 'Safety marks absolute_paths_returned false.' );

$settings = new Npcink_Toolbox\Settings();
$bridge   = new Npcink_Toolbox\Rest_Environment_Performance_Insight_Bridges( $settings );
$pack     = $bridge->assemble_pack();

environment_performance_rest_assert( is_array( $pack ), 'assemble_pack returns an array.' );
environment_performance_rest_assert( Npcink_Toolbox\Environment_Performance_Insight_Builder::CONTRACT_VERSION === ( $pack['contract_version'] ?? null ), 'Pack contract_version is environment_performance_insight_pack.v1.' );
environment_performance_rest_assert( 'environment_performance_insight_pack' === ( $pack['artifact_type'] ?? null ), 'Pack artifact_type matches.' );
environment_performance_rest_assert( 'suggestion_only' === ( $pack['write_posture'] ?? null ), 'Pack write_posture is suggestion_only.' );
environment_performance_rest_assert( 'config_and_inventory_only' === ( $pack['measurement_kind'] ?? null ), 'Pack measurement_kind is config_and_inventory_only.' );
environment_performance_rest_assert( empty( $pack['safety']['secrets_returned'] ) && empty( $pack['safety']['speed_score_returned'] ), 'Pack omits secrets and speed scores.' );
environment_performance_rest_assert( isset( $pack['runtime'], $pack['plugins'], $pack['inventory_signals'], $pack['top_findings'] ), 'Pack includes runtime, plugins, inventory_signals, top_findings.' );
environment_performance_rest_assert( ! isset( $pack['post_content'] ) && false === strpos( wp_json_encode( $pack ), 'ABSPATH' ), 'Pack JSON omits bodies and ABSPATH token.' );
environment_performance_rest_assert( isset( $pack['runtime']['php_version'] ) && isset( $pack['runtime']['wordpress_version'] ), 'Runtime exposes php and wordpress versions.' );
environment_performance_rest_assert( (int) ( $pack['plugins']['active_plugin_count'] ?? 0 ) >= 1, 'Plugin inventory counts active plugins.' );

$response = $bridge->environment_performance_insight_pack( new WP_REST_Request( '/npcink-toolbox/v1/environment-performance/insight-pack', 'GET' ) );
environment_performance_rest_assert( $response instanceof WP_REST_Response, 'REST GET returns WP_REST_Response.' );
$data = $response->get_data();
environment_performance_rest_assert( is_array( $data ) && Npcink_Toolbox\Environment_Performance_Insight_Builder::CONTRACT_VERSION === ( $data['contract_version'] ?? null ), 'REST body carries contract version.' );

$empty_q = new WP_REST_Request( '/npcink-toolbox/v1/environment-performance/insight-pack-qa', 'POST' );
$empty_q->set_param( 'question', '' );
$empty_res = $bridge->environment_performance_insight_pack_qa( $empty_q );
environment_performance_rest_assert( is_wp_error( $empty_res ), 'QA rejects empty question.' );
environment_performance_rest_assert( 'npcink_toolbox_environment_performance_qa_question_required' === $empty_res->get_error_code(), 'Empty question uses required error code.' );

$controller_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Rest_Controller.php' );
environment_performance_rest_assert( false !== strpos( $controller_source, "\$this->get( '/environment-performance/insight-pack', 'environment_performance_insight_pack' )" ), 'Rest_Controller registers GET route.' );
environment_performance_rest_assert( false !== strpos( $controller_source, "\$this->post( '/environment-performance/insight-pack-qa', 'environment_performance_insight_pack_qa' )" ), 'Rest_Controller registers QA route.' );
environment_performance_rest_assert( false !== strpos( $controller_source, "'/environment-performance/insight-pack'    => 'cap.toolbox.admin'" ), 'GET scoped to cap.toolbox.admin.' );
environment_performance_rest_assert( false !== strpos( $controller_source, "'/environment-performance/insight-pack-qa' => 'cap.toolbox.admin'" ), 'QA scoped to cap.toolbox.admin.' );

$admin_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Admin_Page.php' );
$js_source    = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin.js' );
environment_performance_rest_assert( false === strpos( $admin_source, 'data-toolbox-environment-performance-qa' ), 'No separate Ask card outside Smart Ask.' );
environment_performance_rest_assert( false !== strpos( $admin_source, '环境与性能' ) && false !== strpos( $admin_source, 'environment-performance/insight-pack-qa' ), 'Smart Ask lists plain 环境与性能 option.' );
environment_performance_rest_assert( false !== strpos( $js_source, 'initSmartAsk' ), 'admin.js Smart Ask shell present.' );

$boundary = (string) file_get_contents( dirname( __DIR__ ) . '/docs/deidentified-assistant-boundary-v1.md' );
environment_performance_rest_assert( false !== strpos( $boundary, 'environment_performance_insight_pack.v1' ), 'Boundary doc names environment_performance_insight_pack.v1.' );

$guidance_cls = 'Npcink_Toolbox\\Insight_Pack_Answer_Guidance';
$sys_prompt   = $guidance_cls::system_prompt( $guidance_cls::PACK_ENVIRONMENT_PERFORMANCE );
environment_performance_rest_assert( false !== strpos( $sys_prompt, '环境与性能清单' ) && false !== strpos( $sys_prompt, '不是测速' ), 'Ask prompt labels environment inventory, not speed test.' );
environment_performance_rest_assert( false !== strpos( $sys_prompt, '禁止编造网站得分' ) || false !== strpos( $sys_prompt, 'Lighthouse' ), 'Ask prompt bans fake speed scores.' );
environment_performance_rest_assert( '只列出了前 40 个插件名称（共 55 个在用），结论仅供参考。' === $guidance_cls::plugin_list_sample_note( 40, 55, true ), 'Truncated plugin list yields Chinese caveat.' );
environment_performance_rest_assert( '' === $guidance_cls::plugin_list_sample_note( 40, 40, false ), 'Full plugin list adds no caveat.' );
environment_performance_rest_assert( false !== strpos( $bridge_source, 'Insight_Pack_Answer_Guidance::system_prompt' ), 'Bridge uses shared guidance.' );
environment_performance_rest_assert( 40 === Npcink_Toolbox\Environment_Performance_Insight_Builder::plugin_list_limit(), 'Default plugin list limit is 40.' );


$fake_speed_answers = array(
	'你的网站 Lighthouse 得分 98。',
	'PageSpeed 打分 87，LCP 是 1.2 秒。',
	'实验室测速结果：性能分 90。',
	'测速分数 75，还可以。',
	'实测 LCP 很好。',
);
foreach ( $fake_speed_answers as $fake ) {
	environment_performance_rest_assert( true === $guidance_cls::answer_has_forbidden_speed_claims( $fake ), 'Forbidden-speed helper rejects fake score wording.' );
}
$ok_env = "从这份清单看，调试显示可能开着。\n\n1. 关掉对着访客的调试显示\n为什么：可能拖慢或露出提示。\n怎么做：找主机或开发者处理。";
environment_performance_rest_assert( false === $guidance_cls::answer_has_forbidden_speed_claims( $ok_env ), 'Plain inventory advice is not treated as a fake speed score.' );
environment_performance_rest_assert( false !== strpos( $sys_prompt, '可能拖慢/可能有帮助' ), 'Env Ask prompt requires conditional language for debug/cache signals.' );
environment_performance_rest_assert( empty( $pack['safety']['speed_score_returned'] ) && empty( $pack['safety']['lighthouse_run'] ), 'Safety forbids speed_score_returned and lighthouse_run.' );

echo 'All environment-performance insight pack REST behavior checks passed.' . PHP_EOL;
