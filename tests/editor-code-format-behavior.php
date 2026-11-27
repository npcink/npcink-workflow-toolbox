<?php
/**
 * Single-block preview contract with a fake Addon transport.
 *
 * @package Npcink_Toolbox
 */

// Standalone WordPress and Toolbox shims share this process.
// phpcs:disable Universal.Namespaces, Generic.Files.OneObjectStructurePerFile
namespace {
	use Npcink_Toolbox\Editor_Code_Format;

	class Code_Preview_Controller {
		public static function user_can_use_editor_support(): bool {
			return true;
		}
	}
	class_alias( Code_Preview_Controller::class, 'Npcink_Toolbox\Rest_Controller' );

	define( 'ABSPATH', __DIR__ );
	class WP_Error {
		public function __construct( public string $code, public string $message, public array $data ) {}
	}
	class WP_REST_Request {
		public function __construct( private array $params ) {}
		public function get_json_params(): array {
			return $this->params;
		}
	}
	class Code_Response {
		public array $headers = array();
		public function __construct( public array $data ) {}
		public function header( string $name, string $value ): void {
			$this->headers[ $name ] = $value;
		}
	}
	class Code_Preview_Transport {
		public static array $requests = array();
		public static array $result   = array();
	}
	function npcink_cloud_addon_execute_toolbox_code_preview_runtime( array $payload ): array {
		Code_Preview_Transport::$requests[] = $payload;
		return array(
			'data' => array(
				'status' => 'succeeded',
				'result' => Code_Preview_Transport::$result,
			),
		);
	}
	function __( string $message, string $domain ): string {
		return $message;
	}
	function current_user_can( string $cap, int $id ): bool {
		return 'edit_post' === $cap && 42 === $id;
	}
	function get_post( int $id ): object {
		return (object) array( 'ID' => $id );
	}
	function is_wp_error( $response ): bool {
		return $response instanceof WP_Error;
	}
	function rest_ensure_response( array $result ): Code_Response {
		return new Code_Response( $result );
	}
	function check_code_contract( bool $value, string $reason ): void {
		if ( ! $value ) {
			throw new RuntimeException( $reason );
		}
	}

	require __DIR__ . '/../includes/Editor_Code_Format.php';
	require __DIR__ . '/../includes/Toolbox_Asset_Paths.php';
	require __DIR__ . '/../includes/Article_Credential_Rules.php';
	require __DIR__ . '/../includes/Article_Credential_Check.php';
	$source    = 'a{color:red}';
	$candidate = "a {\n  color: red;\n}\n";
	$request   = array(
		'intent'   => 'format_code_block',
		'post_id'  => 42,
		'code'     => $source,
		'language' => 'css',
	);
	check_code_contract( Editor_Code_Format::request( new WP_REST_Request( $request ) )->data['status'] === 404, 'Pilot defaults off.' );
	define( 'NPCINK_TOOLBOX_CODE_FORMAT_PILOT', true );
	foreach ( array( array( 'post_id' => 43 ), array( 'language' => 'php' ), array( 'article' => 'must not travel' ), array( 'code' => str_repeat( 'a', 16385 ) ) ) as $change ) {
		check_code_contract( is_wp_error( Editor_Code_Format::request( new WP_REST_Request( array_replace( $request, $change ) ) ) ), 'Invalid or forbidden input.' );
	}
	check_code_contract( array() === Code_Preview_Transport::$requests, 'Invalid inputs never reach Cloud.' );
	$key        = 'ghp_' . str_repeat( 'a', 36 );
	$credential = Editor_Code_Format::request(
		new WP_REST_Request(
			array_replace(
				$request,
				array(
					'code'     => 'const token = "' . $key . '";',
					'language' => 'javascript',
				)
			)
		)
	);
	check_code_contract( $credential instanceof WP_Error && 'npcink_article_credential_check_hit' === $credential->code && array() === Code_Preview_Transport::$requests, 'Recognized credentials never reach the Addon.' );
	Code_Preview_Transport::$result = array(
		'contract_version'       => 'code_format_candidate.v1',
		'language'               => 'css',
		'source_sha256'          => hash( 'sha256', $source ),
		'candidate_sha256'       => hash( 'sha256', $candidate ),
		'candidate'              => $candidate,
		'status'                 => 'CHANGED',
		'direct_wordpress_write' => false,
	);
	$preview                        = Editor_Code_Format::request( new WP_REST_Request( $request ) );
	check_code_contract( $preview instanceof Code_Response && false === $preview->data['persisted'] && 'no-store' === $preview->headers['Cache-Control'], 'Validated transient preview.' );
	$payload = Code_Preview_Transport::$requests[0];
	check_code_contract(
		array(
			'code'          => $source,
			'language'      => 'css',
			'source_sha256' => hash( 'sha256', $source ),
		) === $payload,
		'Only code, language and hash are handed to the Addon.'
	);
	foreach ( array( array( 'status' => 'SKIPPED' ), array( 'source_sha256' => 'mismatch' ), array( 'direct_wordpress_write' => true ), array( 'language' => 'json' ), array( 'candidate' => 'tampered' ) ) as $change ) {
		check_code_contract( ! Editor_Code_Format::valid_result( $source, 'css', array_replace( Code_Preview_Transport::$result, $change ) ), 'Invalid upstream candidate.' );
	}
	echo "PASS: opt-in, post scope, minimal Cloud payload and candidate rejection.\n";
}
