<?php
/** Test-only transport and shared boundary assertions for insight questions. */

if ( ! class_exists( 'Npcink_Cloud_Runtime_Client_Factory' ) ) {
	class Npcink_Cloud_Runtime_Client_Factory {
		public static array $requests = array();

		public static function configured(): self {
			return new self();
		}

		public function execute_runtime( array $payload, string $trace, string $idempotency_key ): array {
			self::$requests[] = $payload;
			return array(
				'run_id' => 'run_insight_question_fixture',
				'status' => 'succeeded',
				'result' => array( 'output_text' => 'Fixture answer' ),
			);
		}
	}
}

function insight_question_message_omits_site_id( string $content ): bool {
	if ( ! preg_match( '/\n\n---\nDe-identified [^\n]+ \(JSON\):\n(.*)\z/s', $content, $matches ) ) {
		return false;
	}
	$pack = json_decode( $matches[1] );
	return is_object( $pack ) && ! property_exists( $pack, 'site_id' );
}

function assert_insight_question_budget( callable $handle, callable $assert ): void {
	// These tests run in isolated CLI processes; refuse to exercise a real client.
	if ( __FILE__ !== ( new ReflectionClass( 'Npcink_Cloud_Runtime_Client_Factory' ) )->getFileName() ) {
		throw new RuntimeException( 'The insight budget test requires the local Cloud fixture.' );
	}
	$assert( ! insight_question_message_omits_site_id( "Question\n\n---\nDe-identified fixture pack (JSON):\n" . '{"site_id":null}' ), 'The privacy assertion rejects an embedded site_id key, even with a null value.' );
	foreach ( array( '中', '😀', 'a' ) as $character ) {
		Npcink_Cloud_Runtime_Client_Factory::$requests = array();
		$request  = new WP_REST_Request();
		$question = str_repeat( $character, 2000 );
		$request->set_param( 'question', $question );
		$response = $handle( $request );
		$payload  = Npcink_Cloud_Runtime_Client_Factory::$requests[0] ?? array();
		$assert( $response instanceof WP_REST_Response && true === $response->get_data()['ok'], '2000 Unicode characters return an answer from the fixture.' );
		$user_content = $payload['input']['messages'][1]['content'] ?? '';
		$assert( str_starts_with( $user_content, '站长的问题：' . $question . "\n\n" ), 'The full question reaches the Cloud request.' );
		$assert( insight_question_message_omits_site_id( $user_content ), 'The embedded outbound pack is valid JSON and omits site_id.' );
		$request->set_param( 'question', $question . $character );
		$response = $handle( $request );
		$assert( is_wp_error( $response ) && str_ends_with( $response->get_error_code(), '_question_too_long' ), '2001 characters fail with the limit error.' );
		$assert( 1 === count( Npcink_Cloud_Runtime_Client_Factory::$requests ), 'The oversized question never reaches the Cloud client.' );
	}
}
