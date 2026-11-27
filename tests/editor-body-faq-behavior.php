<?php
/** Focused editor FAQ request, provider and evidence behaviors. */

define( 'ABSPATH', __DIR__ );

class WP_Error {
	public string $code;
	private string $message;
	private array $data;
	public function __construct( string $code, string $message, array $data ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
	public function get_error_data(): array { return $this->data; }
}
class WP_REST_Request {
	private array $params;
	public function __construct( array $params ) { $this->params = $params; }
	public function get_json_params(): array { return $this->params; }
}
class FAQ_Test_Response {
	public array $data;
	public array $headers = array();
	public function __construct( array $data ) { $this->data = $data; }
	public function header( string $name, string $value ): void { $this->headers[ $name ] = $value; }
}
class FAQ_Test_Controller {
	public static bool $allowed = true;
	public static function user_can_use_editor_support(): bool { return self::$allowed; }
}
class_alias( FAQ_Test_Controller::class, 'Npcink_Toolbox\Rest_Controller' );

function __( string $text, string $domain ): string { return $text; }
function current_user_can( string $cap, int $id ): bool { return 'edit_post' === $cap && 77 === $id; }
function wp_strip_all_tags( string $html ): string { return strip_tags( preg_replace( '~<(script|style)[^>]*>.*?</\1>~is', '', $html ) ); }
function wp_json_encode( $data, int $flags = 0 ) { return json_encode( $data, $flags ); }
function wp_generate_uuid4(): string { return 'uuid'; }
function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function rest_ensure_response( array $data ): FAQ_Test_Response { return new FAQ_Test_Response( $data ); }
function npcink_cloud_addon_execute_toolbox_content_support_runtime( array $payload, string $trace, string $key ) {
	$GLOBALS['faq_calls'][] = $payload;
	return $GLOBALS['faq_remote'];
}

require_once dirname( __DIR__ ) . '/includes/Editor_Body_FAQ.php';

function faq_assert( bool $condition, string $label ): void {
	if ( ! $condition ) { throw new RuntimeException( $label ); }
}
function faq_request( array $replace = array() ) {
	return Npcink_Toolbox\Editor_Body_FAQ::request( new WP_REST_Request( array_replace( $GLOBALS['faq_request'], $replace ) ) );
}
function faq_remote_items( array $items ): void {
	$GLOBALS['faq_remote'] = array( 'data' => array( 'status' => 'succeeded', 'result' => array( 'output_text' => json_encode( array( 'items' => $items ) ) ) ) );
}

$faq_calls   = array();
$faq_request = array(
	'intent'  => 'body_faq',
	'post_id' => 77,
	'content' => '<!-- wp:paragraph {"metadata":"hidden data"} --><p>The limit is <code>100</code> tags &amp; no more.</p><!-- /wp:paragraph --><script>internal script</script><p>Can the limit change?</p><p>Yes.</p>',
);
$candidate   = array( 'question' => 'Why are some tags missing?', 'answer' => 'The example shows at most 100 tags.', 'evidence' => 'The limit is 100 tags & no more.' );
faq_remote_items( array( $candidate ) );

FAQ_Test_Controller::$allowed = false;
faq_assert( 'npcink_toolbox_faq_forbidden' === faq_request()->code, 'Require editor route capability.' );
FAQ_Test_Controller::$allowed = true;
faq_assert( 'npcink_toolbox_faq_forbidden' === faq_request( array( 'post_id' => 61 ) )->code, 'Require per-object capability.' );
faq_assert( 'npcink_toolbox_faq_input' === faq_request( array( 'title' => 'separate field' ) )->code, 'Reject unrelated request data.' );
faq_assert( 'npcink_toolbox_faq_input' === faq_request( array( 'post_id' => 0 ) )->code, 'Require a positive current post ID.' );
faq_assert( 'npcink_toolbox_faq_empty_body' === faq_request( array( 'content' => '<p>&nbsp;</p><img src="example">' ) )->code, 'Empty article never contacts Cloud.' );
faq_assert( 'npcink_toolbox_faq_too_long' === faq_request( array( 'content' => str_repeat( '字', 4001 ) ) )->code, 'Oversize article is not truncated.' );
faq_assert( 'npcink_toolbox_faq_too_long' === faq_request( array( 'content' => str_repeat( ' ', 262145 ) ) )->code, 'Bound serialized input before parsing.' );
faq_assert( 'npcink_toolbox_faq_too_long' === faq_request( array( 'content' => str_repeat( '𠀀', 4000 ) ) )->code, 'Enforce transport byte cap before the Addon.' );
faq_assert( array() === $faq_calls, 'No provider work on rejected requests.' );

$result  = faq_request();
$payload = $faq_calls[0];
faq_assert( array( $candidate ) === $result->data['items'] && 'ready' === $result->data['status'] && false === $result->data['direct_wordpress_write'], 'Return only a verified suggestion.' );
faq_assert( 'no-store' === $result->headers['Cache-Control'], 'Do not cache response.' );
faq_assert( array( 'body' => "The limit is 100 tags & no more.\nCan the limit change?\nYes." ) === json_decode( $payload['input']['messages'][1]['content'], true ), 'Only current plain body is sent, without block metadata or script.' );
faq_assert( 'pii' === $payload['data_classification'] && 'no_store' === $payload['storage_mode'] && 0 === $payload['retention_ttl'] && 0 === $payload['retry_max'] && false === $payload['policy']['allow_fallback'], 'Private synchronous payload and no retries, retention or fallback.' );
faq_assert( 30 === $payload['timeout_seconds'] && ! array_key_exists( 'http_timeout_seconds', $payload ) && ! array_key_exists( 'connect_timeout_seconds', $payload ), 'Keep the Cloud timeout without adding either transport-only field.' );
faq_assert( array( 'messages', 'params' ) === array_keys( $payload['input'] ), 'No extra context is sent downstream.' );

faq_remote_items( array( $candidate, array_replace( $candidate, array( 'question' => 'why are some tags missing!' ) ), array_replace( $candidate, array( 'question' => 'Can the limit change?' ) ) ) );
faq_assert( array( $candidate ) === faq_request()->data['items'], 'Skip duplicate proposals and questions already in body.' );
$export = array( 'question' => 'Which plan supports export?', 'answer' => 'Only the Pro plan.', 'evidence' => 'Only the Pro plan includes export.' );
faq_remote_items( array( $export ) );
faq_assert( array( $export ) === faq_request( array( 'content' => '<p>Compare which plan supports export before choosing. Only the Pro plan includes export.</p>' ) )->data['items'], 'Do not treat a matching phrase in ordinary prose as an existing question.' );
faq_assert( 'empty' === faq_request( array( 'content' => '<p>Compare the plans. Which plan supports export？ Only the Pro plan includes export.</p>' ) )->data['status'], 'A whole question sentence with full-width punctuation still suppresses a duplicate.' );
faq_assert( 'empty' === faq_request( array( 'content' => '<p>Only the Pro plan includes export.</p><h2>Frequently asked questions</h2><h3>Q: Which plan supports export?</h3><p>A: Only the Pro plan includes export.</p>' ) )->data['status'], 'Suppress a question already in the editor’s native Q&A blocks.' );
faq_assert( 'empty' === faq_request( array( 'content' => '<p>Only the Pro plan includes export.</p><h2>FAQ</h2><h3>Q: Which plan supports export</h3><p>A: Only the Pro plan includes export.</p>' ) )->data['status'], 'An explicit Q label also catches a previous FAQ without final punctuation.' );
faq_remote_items( array( array_replace( $candidate, array( 'evidence' => 'This is absent.' ) ), $candidate ) );
faq_assert( array( $candidate ) === faq_request()->data['items'], 'Drop unsupported candidates individually.' );
faq_remote_items( array( array_replace( $candidate, array( 'evidence' => 'This is absent.' ) ) ) );
faq_assert( 'npcink_toolbox_faq_result' === faq_request()->code, 'All invalid is not a successful empty result.' );
faq_remote_items( array( $candidate, $candidate, $candidate, $candidate ) );
faq_assert( 'npcink_toolbox_faq_result' === faq_request()->code, 'Overlong result is invalid.' );
faq_remote_items( array() );
faq_assert( 'empty' === faq_request()->data['status'], 'Model may legitimately abstain.' );
$faq_remote = array( 'data' => array( 'status' => 'succeeded', 'result' => array( 'output_text' => 'not JSON' ) ) );
faq_assert( 'npcink_toolbox_faq_result' === faq_request()->code, 'Malformed result is not empty.' );
$faq_remote['data']['result']['output_text'] = '{"items":{}}';
faq_assert( 'npcink_toolbox_faq_result' === faq_request()->code, 'An empty object is not a list of candidates.' );
$faq_remote['data']['status'] = 'running';
$faq_remote['data']['result']['output_text'] = '{"items":[]}';
faq_assert( 'npcink_toolbox_faq_cloud' === faq_request()->code, 'An unfinished run is not a successful empty result.' );
$faq_remote = new WP_Error( 'cloud_unavailable', 'upstream details', array() );
faq_assert( 'npcink_toolbox_faq_cloud' === faq_request()->code, 'Hide upstream failures and return a stable error.' );

$time_limits = array(
	array( 'data' => array( 'status' => 'failed', 'error_code' => 'provider.timeout' ) ),
	new WP_Error( 'cloud_runtime_failed', 'upstream details', array( 'cloud_error_code' => 'provider.timeout' ) ),
	new WP_Error( 'cloud_runtime_failed', 'upstream details', array( 'cloud_error_data' => array( 'error_code' => 'provider.timeout' ) ) ),
	new WP_Error( 'cloud_runtime_request_failed', 'Cannot connect. Original error: cURL error 28: Operation timed out.', array( 'status' => 502 ) ),
);
foreach ( $time_limits as $faq_remote ) {
	$before = count( $faq_calls );
	$error  = faq_request();
	faq_assert( 'npcink_toolbox_faq_timeout' === $error->code && 504 === $error->get_error_data()['status'] && 'FAQ generation timed out. Nothing was written.' === $error->get_error_message(), 'Use safe timeout guidance for known timeouts.' );
	faq_assert( $before + 1 === count( $faq_calls ), 'No automatic retry after timeout.' );
}
$faq_remote = new WP_Error( 'cloud_runtime_request_failed', 'cURL error 7: connection refused', array( 'status' => 502 ) );
faq_assert( 'npcink_toolbox_faq_cloud' === faq_request()->code, 'Other transport failures stay distinct from timeouts.' );

echo "Editor body FAQ request behavior passed.\n";
