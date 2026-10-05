<?php
/**
 * Cloud web-search REST bridge cluster, moved verbatim from
 * Rest_Controller behind one-line facade delegates.
 *
 * Both routes are Cloud-managed web search transports; Toolbox stores no
 * provider keys, verifies no truth, and writes no WordPress data here.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class Rest_Web_Search_Bridges extends Rest_Controller_Support {

	private Provider_Client $client;

	public function __construct( Provider_Client $client ) {
		$this->client = $client;
	}

	public function web_search_test( WP_REST_Request $request ) {
		$query = $this->required_text( $request, 'query' );
		if ( is_wp_error( $query ) ) {
			return $query;
		}
		$intent            = sanitize_key( (string) ( $request->get_param( 'intent' ) ?: 'news' ) );
		$recency_param     = $request->get_param( 'recency_days' );
		$default_recency   = in_array( $intent, array( 'pricing_snapshot', 'product_comparison' ), true ) ? 0 : ( 'news' === $intent ? 7 : 30 );
		$recency_days      = null === $recency_param || '' === $recency_param ? $default_recency : (int) $recency_param;

		return rest_ensure_response(
			$this->client->test_cloud_web_search(
				array(
					'query'               => $query,
					'intent'              => $intent,
					'managed_source'      => sanitize_key( (string) $request->get_param( 'managed_source' ) ),
					'max_results'         => max( 1, min( 5, (int) ( $request->get_param( 'max_results' ) ?: 3 ) ) ),
					'recency_days'        => max( 0, min( 30, $recency_days ) ),
				)
			)
		);
	}

	public function web_search_diagnostics( WP_REST_Request $request ) {
		$topic = $this->required_text( $request, 'topic' );
		if ( is_wp_error( $topic ) ) {
			return $topic;
		}

		return rest_ensure_response(
			$this->client->diagnose_automatic_web_search(
				array(
					'topic'    => $topic,
					'title'    => sanitize_text_field( (string) ( $request->get_param( 'title' ) ?: $topic ) ),
					'scenario' => sanitize_key( (string) ( $request->get_param( 'scenario' ) ?: 'discoverability' ) ),
				)
			)
		);
	}

}
