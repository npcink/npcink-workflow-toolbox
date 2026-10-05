<?php
/**
 * Thin flows/plan REST bridge cluster, moved verbatim from
 * Rest_Controller behind one-line facade delegates.
 *
 * Every method forwards validated params to one Provider_Client plan
 * builder and returns its suggestion-only artifact; none of them calls
 * Core, approves proposals, executes plans, or writes WordPress data.
 * The historical double-tab indentation on three of the moved methods
 * is preserved verbatim from the facade.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class Rest_Flow_Plan_Bridges {

	private Provider_Client $client;

	public function __construct( Provider_Client $client ) {
		$this->client = $client;
	}

	public function article_plan( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->build_article_write_plan( is_array( $params ) ? $params : array() ) );
	}

	public function image_candidate_adoption_plan( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->build_image_candidate_adoption_plan( is_array( $params ) ? $params : array() ) );
	}

	public function article_audio_adoption_plan( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->build_article_audio_adoption_plan( is_array( $params ) ? $params : array() ) );
	}

	public function nightly_inspection_review_plan( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->build_nightly_inspection_review_plan( is_array( $params ) ? $params : array() ) );
	}

		public function content_metadata_apply_plan( WP_REST_Request $request ) {
			$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
			return rest_ensure_response( $this->client->build_content_metadata_apply_plan( is_array( $params ) ? $params : array() ) );
		}

		public function media_alt_caption_review_plan( WP_REST_Request $request ) {
			$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
			return rest_ensure_response( $this->client->build_media_alt_caption_review_plan( is_array( $params ) ? $params : array() ) );
		}
}
