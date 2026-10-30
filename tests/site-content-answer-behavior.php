<?php
/** Isolated behavior checks; WordPress schema validation is a controlled dependency. */
define( 'ABSPATH', __DIR__ . '/' );
class WP_Error {
	public function __construct( private string $code, private string $message = '', private array $data = array() ) {}
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
	public function add_data( $data ) { $this->data = $data; }
}
function __( $text, $domain = '' ) { return $text; }
function current_user_can( $cap ) { return $GLOBALS['admin']; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function sanitize_textarea_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function wp_generate_uuid4() { return uniqid(); }
function rest_validate_value_from_schema( $value, $schema, $name ) {
	$GLOBALS['schema_value'] = $value;
	return $GLOBALS['schema_valid'] ? true : new WP_Error( 'rest_invalid_type' );
}
function npcink_cloud_addon_execute_registered_ai_task_runtime( ...$args ) {
	$GLOBALS['calls'][] = $args;
	return $GLOBALS['reply'];
}
require dirname( __DIR__ ) . '/includes/Site_Content_Answer.php';
require dirname( __DIR__ ) . '/includes/Cloud_Error_Plain_Messages.php';
use Npcink_Toolbox\Site_Content_Answer;
function check( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
}
function fixture() {
	return array( 'data' => array( 'run_id' => 'run_fixture', 'result' => array( 'output' => array(
		'output_text' => json_encode( array( 'answer' => 'The launch is in May. [1]', 'citations' => array( array( 'id' => 'source-1', 'quote' => 'The launch is in May.' ) ) ) ),
		'grounded_evidence' => array( 'contract_version' => 'grounded_answer_evidence.v1', 'status' => 'available', 'verification' => 'citation_identity_and_exact_quote', 'sources' => array( array( 'id' => 'source-1', 'title' => '<script>bad()</script>', 'url' => 'javascript:bad()', 'source_completeness' => 'complete', 'text' => 'must not leak the full source', 'unexpected' => 'must not leak' ) ) ),
	) ) ) );
}
function answer() { return Site_Content_Answer::answer( array( 'question' => 'When is the launch?' ) ); }
function rejected( $result, $code ) { check( is_wp_error( $result ) && $result->get_error_code() === $code, $code ); }
$GLOBALS['admin'] = false;
$GLOBALS['calls'] = array();
$GLOBALS['schema_valid'] = true;
rejected( answer(), 'npcink_toolbox_site_answer_forbidden' );
$GLOBALS['admin'] = true;
foreach ( array( null, array(), array( 'question' => array() ), array( 'question' => 'q', 'site_id' => 'other' ), array( 'question' => 'q', 'model' => 'override' ) ) as $input ) {
	rejected( Site_Content_Answer::answer( $input ), 'npcink_toolbox_site_answer_input' );
}
foreach ( array( ' ', str_repeat( '汉', 2001 ), str_repeat( 'a', 2001 ) ) as $q ) {
	rejected( Site_Content_Answer::answer( array( 'question' => $q ) ), 'npcink_toolbox_site_answer_question' );
}
check( ! $GLOBALS['calls'], 'Permissions and invalid inputs never reach Cloud.' );
$GLOBALS['reply'] = fixture();
$r = answer();
check( ! is_wp_error( $r ) && $r['answer'] === 'The launch is in May. [1]' && $r['run_id'] === 'run_fixture', 'Verified answer survives.' );
check( ! isset( $r['grounded_evidence']['sources'][0]['text'] ) && ! isset( $r['grounded_evidence']['sources'][0]['unexpected'] ), 'Only the reference projection is returned.' );
$call = $GLOBALS['calls'][0];
check( $call[0] === Site_Content_Answer::ABILITY && array_keys( $call[1] ) === array( 'prompt', 'max_tokens', 'system_instruction' ), 'Registered contract transports a question without local corpus, route, or model overrides.' );
check( $call[2] !== $call[3], 'Trace and idempotency are independent.' );
$GLOBALS['reply']['data']['result']['output']['grounded_evidence']['status'] = 'partial';
$GLOBALS['reply']['data']['result']['output']['grounded_evidence']['sources'][0]['source_completeness'] = 'partial_or_unknown';
check( answer()['grounded_evidence']['status'] === 'partial', 'Partial evidence is not promoted to complete.' );
foreach ( array( 'verification', 'sources', 'contract_version' ) as $field ) {
	$GLOBALS['reply'] = fixture();
	unset( $GLOBALS['reply']['data']['result']['output']['grounded_evidence'][ $field ] );
	rejected( answer(), 'npcink_toolbox_site_answer_unverified' );
}
$GLOBALS['reply'] = fixture();
$GLOBALS['reply']['data']['result']['output']['grounded_evidence']['sources'][0]['id'] = 'wrong-source';
rejected( answer(), 'npcink_toolbox_site_answer_unverified' );
$GLOBALS['reply'] = fixture();
$GLOBALS['schema_valid'] = false;
rejected( answer(), 'npcink_toolbox_site_answer_unverified' );
$GLOBALS['schema_valid'] = true;
$GLOBALS['reply']['data']['result']['output']['output_text'] = '{"answer":"forged","citations":[],"grounded_evidence":{}}';
rejected( answer(), 'npcink_toolbox_site_answer_unverified' );
foreach ( array( 'no_evidence', 'retrieval_unavailable', 'retrieval_failed' ) as $reason ) {
	$GLOBALS['reply'] = new WP_Error( 'cloud_failure', '', array( 'cloud_error_code' => 'grounded_answer.' . $reason, 'cloud_error_data' => array( 'run_id' => 'run_failure' ) ) );
	$r = answer();
	rejected( $r, 'npcink_toolbox_site_answer_evidence' );
	check( $r->get_error_data()['run_id'] === 'run_failure' && $r->get_error_data()['diagnostic_code'] === 'grounded_answer.' . $reason, 'Evidence failures preserve correlation and reason.' );
}
$GLOBALS['reply'] = new WP_Error( 'cloud_busy', 'raw upstream detail', array( 'status' => 429, 'cloud_error_data' => array( 'run_id' => 'run_busy', 'run_state' => array( 'input' => 'must not leak' ) ) ) );
$r = answer();
check( $r !== $GLOBALS['reply'] && $r->get_error_data()['run_id'] === 'run_busy' && $r->get_error_data()['status'] === 429, 'Other failures preserve their original status and run in a new bounded error.' );
check( ! isset( $r->get_error_data()['cloud_error_data'] ) && ! isset( $r->get_error_data()['additional_data'] ) && $r->get_error_data()['plain_message'] !== 'raw upstream detail', 'Raw Cloud run details and old WP_Error data are not exposed.' );
echo "Site content answer behavior checks passed.\n";
