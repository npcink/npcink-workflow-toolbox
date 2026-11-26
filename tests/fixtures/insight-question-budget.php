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

function assert_insight_question_budget( callable $handle, callable $assert ): void {
	// These tests run in isolated CLI processes; refuse to exercise a real client.
	if ( __FILE__ !== ( new ReflectionClass( 'Npcink_Cloud_Runtime_Client_Factory' ) )->getFileName() ) {
		throw new RuntimeException( 'The insight budget test requires the local Cloud fixture.' );
	}
	foreach ( array( '中', '😀', 'a' ) as $character ) {
		Npcink_Cloud_Runtime_Client_Factory::$requests = array();
		$request  = new WP_REST_Request();
		$question = str_repeat( $character, 2000 );
		$request->set_param( 'question', $question );
		$response = $handle( $request );
		$payload  = Npcink_Cloud_Runtime_Client_Factory::$requests[0] ?? array();
		$assert( $response instanceof WP_REST_Response && true === $response->get_data()['ok'], '2000 Unicode characters return an answer from the fixture.' );
		$assert( str_starts_with( $payload['input']['messages'][1]['content'] ?? '', '站长的问题：' . $question . "\n\n" ), 'The full question reaches the Cloud request.' );
		$messages = $payload['input']['messages'] ?? null;
		$assert( is_array( $messages ) && false === strpos( (string) wp_json_encode( $messages ), '"site_id"' ), 'Outbound Cloud input.messages omit site_id.' );
		$request->set_param( 'question', $question . $character );
		$response = $handle( $request );
		$assert( is_wp_error( $response ) && str_ends_with( $response->get_error_code(), '_question_too_long' ), '2001 characters fail with the limit error.' );
		$assert( 1 === count( Npcink_Cloud_Runtime_Client_Factory::$requests ), 'The oversized question never reaches the Cloud client.' );
	}
}
