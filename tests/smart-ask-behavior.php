<?php
/** Bounded routing, permissions and no-data-before-selection contracts. */
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	class WP_Error {
		public function __construct( private string $code, private string $message = '', private array $data = array() ) {}
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
		public function get_error_data() { return $this->data; }
	}
	class WP_REST_Response {
		public array $headers = array();
		public function __construct( private array $data ) {}
		public function get_data() { return $this->data; }
		public function header( $key, $value ) { $this->headers[$key] = $value; }
	}
	class WP_REST_Request {
		public $input;
		public function __construct( private string $method = 'POST' ) {}
		public function get_json_params() { return $this->input; }
		public function get_param( $key ) { return $this->input[$key] ?? null; }
		public function get_route() { return '/npcink-toolbox/v1/smart-ask'; }
		public function get_method() { return $this->method; }
		public function set_header( ...$args ) {}
		public function set_body( $body ) { $this->input = json_decode( $body, true ); }
	}
	function __( $text, $domain = '' ) { return $text; }
	function current_user_can( $cap ) { return 'manage_options' === $cap && $GLOBALS['admin']; }
	function is_wp_error( $value ) { return $value instanceof WP_Error; }
	function sanitize_textarea_field( $value ) { return trim( strip_tags( $value ) ); }
	function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
	function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
	function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
	function wp_generate_uuid4() { return 'uuid_' . ++$GLOBALS['uuid']; }
	function rest_ensure_response( $value ) { return $value instanceof WP_REST_Response || is_wp_error( $value ) ? $value : new WP_REST_Response( $value ); }
	function register_rest_route( $namespace, $route, $args ) { $GLOBALS['routes'][$route] = $args; }
	function apply_filters( $filter, $value, ...$args ) { return $value; }
	function wp_get_ability( $name ) { return new class {
		public function execute( $input ) { return dispatch_answer( 'site_content', $input ); }
	}; }
	function dispatch_answer( $source, $input ) {
		$GLOBALS['dispatch'][] = array( $source, $input );
		if ( $GLOBALS['answer_error'] ) return $GLOBALS['answer_error'];
		$data = array( 'ok' => true, 'answer' => 'Existing verified answer', 'run_id' => 'run_answer' );
		if ( 'site_content' === $source ) $data['grounded_evidence'] = array( 'status' => 'available', 'sources' => array() );
		return $data;
	}
	class Npcink_Cloud_Runtime_Client_Factory {
		public static function configured() { return $GLOBALS['configured'] ? new class {
			public function execute_runtime( ...$args ) { $GLOBALS['calls'][] = $args; return $GLOBALS['reply']; }
		} : null; }
	}
}
namespace Npcink_Toolbox {
	class Plugin { public const REST_NAMESPACE = 'npcink-toolbox/v1'; }
	class Bridge {
		public function __construct( private string $source ) {}
		public function __call( $name, $args ) { return \rest_ensure_response( \dispatch_answer( $this->source, $args[0]->get_json_params() ) ); }
	}
	class Rest_Site_Ops_Insight_Bridges extends Bridge {}
	class Rest_Content_Structure_Insight_Bridges extends Bridge {}
	class Rest_Page_Quality_Insight_Bridges extends Bridge {}
	class Rest_Environment_Performance_Insight_Bridges extends Bridge {}
}
namespace {
	require dirname( __DIR__ ) . '/includes/Cloud_Error_Plain_Messages.php';
	require dirname( __DIR__ ) . '/includes/Rest_Controller_Support.php';
	require dirname( __DIR__ ) . '/includes/Site_Content_Answer.php';
	require dirname( __DIR__ ) . '/includes/Smart_Ask.php';
	require dirname( __DIR__ ) . '/includes/Rest_Controller.php';
	use Npcink_Toolbox\Rest_Controller;
	use Npcink_Toolbox\Smart_Ask;
	function check( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, "FAIL: $message\n" ); exit(1); } }
	function reset_case( $action = 'route', $source = 'site_ops', $message = '' ) {
		$GLOBALS['admin'] = true; $GLOBALS['configured'] = true; $GLOBALS['calls'] = array(); $GLOBALS['dispatch'] = array(); $GLOBALS['answer_error'] = null;
		$GLOBALS['reply'] = array( 'data' => array( 'status' => 'completed', 'run_id' => 'run_decision', 'result' => array( 'output_text' => json_encode( compact( 'action', 'source', 'message' ) ) ) ) );
	}
	$GLOBALS['uuid'] = 0;
	$reflect = new ReflectionClass( Rest_Controller::class );
	$controller = $reflect->newInstanceWithoutConstructor();
	foreach ( array( 'site_ops_insight_bridges' => new Npcink_Toolbox\Rest_Site_Ops_Insight_Bridges('site_ops'), 'content_structure_bridges' => new Npcink_Toolbox\Rest_Content_Structure_Insight_Bridges('content_structure'), 'page_quality_bridges' => new Npcink_Toolbox\Rest_Page_Quality_Insight_Bridges('page_quality'), 'environment_performance_bridges' => new Npcink_Toolbox\Rest_Environment_Performance_Insight_Bridges('environment_performance') ) as $key => $bridge ) {
		$property = $reflect->getProperty( $key ); $property->setAccessible( true ); $property->setValue( $controller, $bridge );
	}
	$request = new WP_REST_Request(); $request->input = array( 'question' => 'How can I improve this site?' );
	$controller->register_routes();
	$route = $GLOBALS['routes']['/smart-ask'];
	check( 'POST' === $route['methods'] && $route['args']['question']['required'], 'One authenticated POST accepts a required question.' );
	reset_case(); $GLOBALS['admin'] = false;
	check( ! $controller->permission( $request ) && is_wp_error( $controller->smart_ask( $request ) ), 'Authors and unauthenticated callers are denied, including direct callbacks.' );
	check( ! $GLOBALS['calls'] && ! $GLOBALS['dispatch'], 'Denial precedes data assembly and Cloud.' );
	reset_case(); check( $controller->permission( $request ), 'Existing manage_options permission is retained.' );
	foreach ( array( null, array(), array('question'=>array()), array('question'=>' '), array('question'=>str_repeat('a',2001)), array('question'=>'q','source_hint'=>array()), array('question'=>'q','source_hint'=>'publish'), array('question'=>'q','model'=>'override'), array('question'=>'q','site_id'=>'other'), array('question'=>'q','content'=>'private') ) as $input ) {
		$request->input = $input;
		check( is_wp_error( $controller->smart_ask( $request ) ), 'Invalid or extra inputs fail before selection.' );
	}
	check( ! $GLOBALS['calls'] && ! $GLOBALS['dispatch'], 'Invalid input has zero reads or paid calls.' );
	foreach ( Smart_Ask::SOURCES as $source ) {
		reset_case( 'route', $source );
		$request->input = array( 'question'=>'Changed question in my own words', 'source_hint'=>'site_ops' );
		$response = $controller->smart_ask( $request ); check( ! is_wp_error($response), 'Existing source is reachable: '.$source );
		$data=$response->get_data();
		check( count($GLOBALS['calls'])===1 && count($GLOBALS['dispatch'])===1 && $GLOBALS['dispatch'][0]===array($source,array('question'=>$request->input['question'])), 'Exactly one decision and selected source; hints never override the returned decision.' );
		check( $data['run_id']==='run_answer' && $data['decision_run_id']==='run_decision' && $response->headers['Cache-Control']==='no-store', 'Both correlation IDs and response privacy survive.' );
		check( $data['direct_wordpress_write']===false && $data['proposal_created']===false, 'Every result remains read-only.' );
		$payload=$GLOBALS['calls'][0][0];
		check( $payload['storage_mode']==='no_store' && $payload['data_classification']==='pii' && $payload['retry_max']===0 && $payload['timeout_seconds']===15 && $payload['policy']['allow_fallback']===false, 'Decision is no-store, bounded, single-shot and no fallback.' );
		check( json_decode($payload['input']['messages'][1]['content'],true)===$request->input && !isset($payload['input']['source']), 'Decision receives question and entry hint only, never site data.' );
	}
	foreach ( array('clarify','guidance') as $action ) {
		reset_case($action,'','A short clarification or general answer.');
		$data=$controller->smart_ask($request)->get_data();
		check( !$GLOBALS['dispatch'] && count($GLOBALS['calls'])===1 && $data['answer_basis']===($action==='clarify'?'clarification':'general_guidance'), 'Clarification/general guidance neither scans nor claims site evidence.' );
	}
	foreach ( array( '{bad json', '{"action":"route","source":"/publish","message":""}', '{"action":"route","source":"site_ops","message":"invented answer"}', '{"action":"execute","source":"site_ops","message":""}', '{"action":"route","source":"site_ops","message":"","endpoint":"/publish"}', '{"action":"clarify","source":"site_ops","message":"q"}', '{"action":"guidance","source":"","message":""}' ) as $output ) {
		reset_case(); $GLOBALS['reply']['data']['result']['output_text']=$output;
		check( is_wp_error($controller->smart_ask($request)) && !$GLOBALS['dispatch'], 'Malformed/unknown/overbroad decisions never dispatch or fall back.' );
	}
	foreach ( array('running','failed','cancelled','') as $status ) {
		reset_case(); $GLOBALS['reply']['data']['status']=$status;
		check( is_wp_error($controller->smart_ask($request)) && !$GLOBALS['dispatch'], 'Noncompleted decisions never dispatch.' );
	}
	reset_case(); $GLOBALS['reply']=new WP_Error('cloud_commercial_concurrency_exceeded','secret upstream detail',array('status'=>429,'cloud_error_data'=>array('run_id'=>'run_busy','input'=>'secret')));
	$error=$controller->smart_ask($request);
	check( is_wp_error($error) && $error->get_error_data()['status']===429 && $error->get_error_data()['run_id']==='run_busy' && strpos(json_encode($error->get_error_data()),'secret')===false && !$GLOBALS['dispatch'], 'Busy errors preserve safe correlation without raw data or retry.' );
	reset_case(); $GLOBALS['answer_error']=new WP_Error('upstream_failed','secret body',array('status'=>503,'run_id'=>'run_failed_answer','private'=>'secret'));
	$error=$controller->smart_ask($request);
	check( is_wp_error($error) && $error->get_error_data()['run_id']==='run_failed_answer' && !isset($error->get_error_data()['private']) && count($GLOBALS['dispatch'])===1, 'Selected-source failure is final and bounded.' );
	reset_case(); $GLOBALS['answer_error']=new WP_Error('qa_cloud_failed','secret body',array('status'=>502,'error_data'=>array('cloud_error_data'=>array('run_id'=>'run_nested','input'=>'secret'))));
	$error=$controller->smart_ask($request);
	check( $error->get_error_data()['run_id']==='run_nested' && strpos(json_encode($error->get_error_data()),'secret')===false, 'Nested bridge errors retain the final Cloud run without retaining raw error data.' );
	reset_case(); $GLOBALS['reply']=new WP_Error('cloud_routing_no_candidates','internal routing detail',array('status'=>400));
	$error=$controller->smart_ask($request);
	check( $error->get_error_data()['diagnostic_code']==='cloud_routing_no_candidates' && !$GLOBALS['dispatch'], 'Unavailable routing stops before any evidence collection and does not change privacy or provider routing.' );
	reset_case(); $GLOBALS['configured']=false;
	check( is_wp_error($controller->smart_ask($request)) && !$GLOBALS['calls'] && !$GLOBALS['dispatch'], 'Disconnected Cloud never collects site evidence or substitutes a fake answer.' );
	echo "Smart Ask: permissions, strict inputs/decisions, five existing sources, no-data selection, clarification, general guidance and fail-closed errors passed.\n";
}
