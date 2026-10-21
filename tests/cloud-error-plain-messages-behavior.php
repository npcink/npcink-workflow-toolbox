<?php
/**
 * Behavior checks: Cloud failures become one plain sentence aligned with Npcink Cloud Addon.
 *
 * @package Npcink_Toolbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wp-stub/' );
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) { // phpcs:ignore
		return $text;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( ...$args ) { // phpcs:ignore
		return true;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error { // phpcs:ignore
		private $c; private $m; private $d; // phpcs:ignore
		public function __construct( $c = '', $m = '', $d = array() ) { $this->c = $c; $this->m = $m; $this->d = $d; } // phpcs:ignore
		public function get_error_code() { return $this->c; } // phpcs:ignore
		public function get_error_message() { return $this->m; } // phpcs:ignore
		public function get_error_data() { return $this->d; } // phpcs:ignore
	}
}
if ( ! class_exists( 'WP_HTTP_Response' ) ) {
	class WP_HTTP_Response { // phpcs:ignore
		public $data; public $status; // phpcs:ignore
		public function __construct( $data = null, $status = 200 ) { $this->data = $data; $this->status = $status; } // phpcs:ignore
		public function get_data() { return $this->data; } // phpcs:ignore
		public function set_data( $d ) { $this->data = $d; } // phpcs:ignore
		public function get_status() { return $this->status; } // phpcs:ignore
	}
}
if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request { // phpcs:ignore
		private $r; // phpcs:ignore
		public function __construct( $r = '' ) { $this->r = $r; } // phpcs:ignore
		public function get_route() { return $this->r; } // phpcs:ignore
	}
}

require_once dirname( __DIR__ ) . '/includes/Plugin.php';
require_once dirname( __DIR__ ) . '/includes/Cloud_Error_Plain_Messages.php';

use Npcink_Toolbox\Cloud_Error_Plain_Messages as M;

$failures = 0;
$assert   = static function ( bool $ok, string $label ) use ( &$failures ): void {
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		++$failures;
	}
};

// Strings must match Npcink Cloud Addon (master 76274ef) source msgids exactly.
$addon_strings = array(
	M::KEY_CREDITS_EXHAUSTED   => 'Your AI credits have run out. Top up on Npcink Cloud, then try again.',
	M::KEY_NOT_CONNECTED       => 'This site is no longer connected to Npcink Cloud. Reconnect it on the Npcink AI > Cloud Addon page, then try again.',
	M::KEY_SITE_INACTIVE       => 'This site is turned off in Npcink Cloud. Turn it on in Npcink Cloud, then try again.',
	M::KEY_RATE_LIMITED        => 'Too many AI requests right now. Wait a minute, then try again.',
	M::KEY_SERVICE_UNAVAILABLE => 'Npcink Cloud is temporarily unavailable. Try again in a few minutes.',
	M::KEY_FAILED              => 'The AI request did not succeed. Try again later; if it keeps failing, copy the information on the Checks tab and contact support.',
);
foreach ( $addon_strings as $key => $text ) {
	$assert( M::message( $key ) === $text, "message($key) matches Addon wording" );
}

$cases = array(
	array( 'cloud_commercial_concurrency_exceeded', '', 429, M::KEY_BUSY ),
	array( 'cloud_insufficient_credits', '', 0, M::KEY_CREDITS_EXHAUSTED ),
	array( 'cloud_request_failed', 'payment_required', 402, M::KEY_CREDITS_EXHAUSTED ),
	array( 'cloud_site_inactive', '', 403, M::KEY_SITE_INACTIVE ),
	array( 'cloud_invalid_key', '', 401, M::KEY_NOT_CONNECTED ),
	array( 'npcink_cloud_not_connected', '', 0, M::KEY_NOT_CONNECTED ),
	array( 'cloud_rate_limited', '', 0, M::KEY_RATE_LIMITED ),
	array( 'cloud_service_unavailable', '', 503, M::KEY_SERVICE_UNAVAILABLE ),
	array( 'cloud_runtime_unavailable', '', 0, M::KEY_SERVICE_UNAVAILABLE ),
	array( 'cloud_validation_failed', 'connection pool', 502, M::KEY_FAILED ),
);
foreach ( $cases as $c ) {
	$assert( M::classify( $c[0], $c[1], $c[2] ) === $c[3], "classify({$c[0]}) => {$c[3]}" );
}

$assert( M::is_cloud_code( 'cloud_insufficient_credits' ), 'cloud_* codes are Cloud errors' );
$assert( M::is_cloud_code( 'npcink_toolbox_insight_pack_qa_cloud_failed' ), 'Ask fallback code is a Cloud error' );
$assert( ! M::is_cloud_code( 'rest_forbidden' ), 'rest_forbidden stays untouched' );
$assert( ! M::is_cloud_code( 'npcink_toolbox_request_cancelled' ), 'local cancel stays untouched' );
$assert( ! M::is_cloud_code( 'npcink_toolbox_invalid_question' ), 'local validation stays untouched' );

// REST rewrite: message plain, code only in diagnostics, nested Cloud status wins over 502 wrapper.
$resp = new WP_HTTP_Response(
	array(
		'code'    => 'cloud_request_failed',
		'message' => 'HTTP 402: insufficient_credits for tenant xyz',
		'data'    => array( 'status' => 502, 'pack_run_id' => 'p1', 'error_data' => array( 'status' => 402 ) ),
	),
	502
);
$out  = M::filter_rest_response( $resp, null, new WP_REST_Request( '/npcink-toolbox/v1/site-ops/insight-pack-qa' ) )->get_data();
$assert( 'AI 积分已用完' !== '' && $addon_strings[ M::KEY_CREDITS_EXHAUSTED ] === $out['message'], 'REST error message rewritten to credits sentence' );
$assert( false === strpos( $out['message'], 'cloud_' ) && false === strpos( $out['message'], 'HTTP' ), 'visible message has no code or HTTP status' );
$assert( 'cloud_request_failed' === $out['data']['diagnostic_code'] && 'p1' === $out['data']['diagnostic']['pack_run_id'], 'code and run id kept in diagnostics' );
$assert( ! isset( $out['data']['error_data'] ), 'raw upstream error_data dropped from response' );

$other = new WP_HTTP_Response( array( 'code' => 'cloud_x', 'message' => 'raw' ), 500 );
$assert( 'raw' === M::filter_rest_response( $other, null, new WP_REST_Request( '/wp/v2/posts' ) )->get_data()['message'], 'non-Toolbox routes untouched' );

$err = M::humanize_error( new WP_Error( 'cloud_commercial_concurrency_exceeded', 'max active runs', array( 'status' => 429 ) ) );
$assert( M::message( M::KEY_BUSY ) === $err->get_error_message() && true === $err->get_error_data()['busy_slot'], 'busy slot keeps a plain hint and busy flag' );

// JS surfaces: no visible Code:/Status:/pack_run_id= lines.
$admin_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin.js' );
$assert( false === strpos( $admin_js, "t('Code: ')" ) && false === strpos( $admin_js, "'pack_run_id=' +" ), 'admin.js no longer prints codes or run ids in error text' );
$assert( false !== strpos( $admin_js, 'plain_message' ), 'admin.js prefers the server plain_message' );
$editor_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/editor-content-support.js' );
$assert( false !== strpos( $editor_js, 'error.data.plain_message' ), 'editor prefers the server plain_message' );

$po = (string) file_get_contents( dirname( __DIR__ ) . '/languages/npcink-workflow-toolbox-zh_CN.po' );
foreach ( array( 'AI 积分已用完。请去 Npcink Cloud 充值后再试。', '此站点已与 Npcink Cloud 断开。请在“Npcink AI > 云端扩展”页面重新连接后再试。', 'AI 请求没有成功。请稍后再试，若一直失败请到「检查」页复制信息联系客服。' ) as $zh ) {
	$assert( false !== strpos( $po, $zh ), "zh_CN translation present: $zh" );
}

// Sweep (#304): embedded 200 failures and visible-message helper.
$assert( M::message( M::KEY_CREDITS_EXHAUSTED ) === M::embedded_message( 'failed', 'Provider quota exhausted for tenant', 'insufficient_credits' ), 'embedded failure with code maps to plain sentence' );
$assert( M::message( M::KEY_FAILED ) === M::embedded_message( 'error', 'routing profile image-source.managed missing' ), 'embedded failure without code maps to fallback sentence' );
$assert( 'Found 3 images.' === M::embedded_message( 'ready', 'Found 3 images.' ), 'success message passes through' );
$assert( M::message( M::KEY_NOT_CONNECTED ) === M::visible_message( new WP_Error( 'cloud_runtime_unconfigured', 'Npcink Cloud is not configured.' ) ), 'visible_message maps Cloud WP_Error' );
$assert( 'Pick a post first.' === M::visible_message( new WP_Error( 'npcink_toolbox_missing_post', 'Pick a post first.' ) ), 'visible_message keeps local message' );
$editor_js2 = (string) file_get_contents( dirname( __DIR__ ) . '/assets/editor-content-support.js' );
foreach ( array( 'Check the Cloud image Provider', 'image Provider credentials', 'Configure image-source.managed', 'runtime envelope', 'Cloud marked this runtime response' ) as $old ) {
	$assert( false === strpos( $editor_js2, $old ), "editor JS no longer shows: $old" );
}
$admin_js2 = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin.js' );
foreach ( array( 'Latest retry candidate: ', 'Check run_id / Addon', 'Continuing status checks for Cloud run ', 'Cloud runtime quota is exhausted' ) as $old ) {
	$assert( false === strpos( $admin_js2, $old ), "admin JS no longer shows: $old" );
}
$assert( false !== strpos( $admin_js2, 'function appendSupportMeta' ) && false !== strpos( $admin_js2, "appendSupportMeta(meta, t('Error code')" ), 'admin error codes and run ids fold into support details' );
$main = (string) file_get_contents( dirname( __DIR__ ) . '/npcink-workflow-toolbox.php' );
$assert( false !== strpos( $main, "includes/Cloud_Error_Plain_Messages.php" ), 'plugin entry loads Cloud_Error_Plain_Messages' );

exit( $failures > 0 ? 1 : 0 );
