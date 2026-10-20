<?php
/**
 * Read-only Site Ops insight pack REST bridge.
 *
 * Assembles site_ops_insight_pack.v1 from the local snapshot collector and
 * insight builder. Does not call Cloud site-ops analysis, does not write to
 * WordPress, and does not own run state.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

final class Rest_Site_Ops_Insight_Bridges {

	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * GET /site-ops/insight-pack — local insight pack only.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function site_ops_insight_pack( WP_REST_Request $request ) {
		unset( $request );

		try {
			$pack = $this->assemble_insight_pack();
		} catch ( \Throwable $throwable ) {
			return new WP_Error(
				'npcink_toolbox_site_ops_insight_pack_failed',
				__( 'Could not build the local Site Check insight pack.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( $pack );
	}

	/**
	 * Builds the same local pack the Site Check admin preview uses, without
	 * Cloud detail or cloud request assembly.
	 *
	 * @return array<string,mixed>
	 */
	public function assemble_insight_pack(): array {
		$collector = new Site_Ops_Snapshot_Collector();
		$builder   = new Site_Ops_Insight_Builder();
		$snapshot  = $collector->collect();
		$context   = array(
			'content_context_ready'      => $this->content_context_ready(),
			'cloud_ready'                => $this->settings->cloud_runtime_available(),
			'internal_link_graph_health' => $this->internal_link_graph_health(),
		);

		return $builder->build( $snapshot, $context );
	}

	private function content_context_ready(): bool {
		$context         = $this->settings->get_content_context();
		$required_fields = array( 'site_positioning', 'target_audience', 'brand_voice', 'primary_keywords' );

		foreach ( $required_fields as $field ) {
			$value = $context[ $field ] ?? '';
			if ( is_array( $value ) ) {
				$parts = array();
				foreach ( $value as $item ) {
					if ( is_scalar( $item ) ) {
						$parts[] = trim( (string) $item );
					}
				}
				$value = implode( ' ', array_filter( $parts ) );
			}
			if ( '' === trim( (string) $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Optional Toolkit internal-link graph health; missing Toolkit is non-fatal.
	 *
	 * @return array<string,mixed>
	 */
	private function internal_link_graph_health(): array {
		$ability_id = 'npcink-abilities-toolkit/get-internal-link-graph-health';
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) || ! current_user_can( 'edit_posts' ) ) {
			return array(
				'available'         => false,
				'source_ability_id' => $ability_id,
			);
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();
		$callback   = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return array(
				'available'         => false,
				'source_ability_id' => $ability_id,
			);
		}

		$result = call_user_func(
			$callback,
			array(
				'post_type'          => 'post',
				'status'             => 'publish',
				'per_page'           => 24,
				'page'               => 1,
				'min_outbound_links' => 1,
				'max_outbound_links' => 20,
			)
		);
		if ( is_wp_error( $result ) || ! is_array( $result ) || empty( $result['success'] ) || ! is_array( $result['data'] ?? null ) ) {
			return array(
				'available'         => false,
				'source_ability_id' => $ability_id,
			);
		}

		return array(
			'available'         => true,
			'source_ability_id' => $ability_id,
			'data'              => $result['data'],
		);
	}
}
