<?php
/**
 * Cloud-managed Site Knowledge REST bridge cluster, moved verbatim from
 * Rest_Controller behind one-line facade delegates.
 *
 * Boundary posture is unchanged: refresh-only manifest transport, no
 * rebuild/delete modes, no local indexing queue or lifecycle, no
 * server-side run state, and no WordPress writes.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

final class Rest_Site_Knowledge_Bridges extends Rest_Controller_Support {

	private Provider_Client $client;

	public function __construct( Provider_Client $client ) {
		$this->client = $client;
	}

	public function site_knowledge_status( WP_REST_Request $request ) {
		$public_post_ids = $this->public_site_knowledge_post_ids();
		$status = $this->client->get_site_knowledge_status(
			array(
				'include_coverage' => true,
				'post_ids'         => $public_post_ids,
			)
		);

		if ( is_array( $status ) ) {
			$status['article_index_statuses'] = $this->site_knowledge_article_index_statuses( $status, $public_post_ids );
			$change_bridge = Site_Knowledge_Auto_Sync::health_snapshot();
			$status['change_bridge'] = $change_bridge;
			$status['auto_sync']     = $change_bridge;
			if ( ! is_array( $status['site_knowledge_cloud_boundary'] ?? null ) ) {
				$boundary = Site_Knowledge_Auto_Sync::cloud_boundary_projection( $change_bridge );
				if ( array() !== $boundary ) {
					$status['site_knowledge_cloud_boundary'] = $boundary;
				}
			}
		}

		return rest_ensure_response( $status );
	}

	/**
	 * Returns the bounded local manifest used to compare public WordPress posts
	 * with the Cloud-owned indexed ID projection.
	 *
	 * @return int[]
	 */
	private function public_site_knowledge_post_ids(): array {
		$posts = get_posts(
			array(
				'post_type'              => array( 'post', 'page' ),
				'post_status'            => 'publish',
				'posts_per_page'         => 1000,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
			)
		);

		return array_values( array_filter( array_map( 'absint', is_array( $posts ) ? $posts : array() ) ) );
	}

	/**
	 * @param array<string,mixed> $status          Cloud status response.
	 * @param int[]               $public_post_ids Local comparison manifest sent to Cloud.
	 * @return array<int,array<string,mixed>>
	 */
	private function site_knowledge_article_index_statuses( array $status, array $public_post_ids ): array {
		$coverage = is_array( $status['coverage'] ?? null ) ? $status['coverage'] : array();
		$public_post_ids = array_values( array_unique( array_filter( array_map( 'absint', $public_post_ids ) ) ) );
		if (
			array() !== $public_post_ids
			&& (
				! is_array( $coverage['indexed_post_ids'] ?? null )
				|| count( $public_post_ids ) !== absint( $coverage['indexed_post_ids_requested'] ?? 0 )
			)
		) {
			return array();
		}
		$indexed_ids = array_fill_keys( array_map( 'absint', is_array( $coverage['indexed_post_ids'] ?? null ) ? $coverage['indexed_post_ids'] : array() ), true );
		$statuses = array();
		foreach ( $public_post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}
			$statuses[] = array(
				'post_id'       => $post_id,
				'title'         => sanitize_text_field( get_the_title( $post ) ),
				'url'           => esc_url_raw( get_permalink( $post ) ),
				'modified_gmt'  => sanitize_text_field( (string) $post->post_modified_gmt ),
				'status'        => isset( $indexed_ids[ $post_id ] ) ? 'indexed' : 'not_indexed',
			);
		}

		return $statuses;
	}

	public function site_knowledge_sync( WP_REST_Request $request ) {
		$sync_mode = sanitize_key( (string) ( $request->get_param( 'sync_mode' ) ?: 'refresh' ) );
		if ( 'refresh' !== $sync_mode ) {
			return new WP_Error(
				'npcink_toolbox_site_knowledge_sync_mode_not_allowed',
				__( 'Toolbox only forwards public Site Knowledge refresh requests. Rebuild, delete, and collection lifecycle operations belong in Cloud Site Knowledge.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response(
			$this->client->request_site_knowledge_sync(
				array(
					'sync_mode' => 'refresh',
					'post_ids'  => $this->csv_absint_list( (string) $request->get_param( 'post_ids' ) ),
					'max_posts' => max( 1, min( 50, (int) ( $request->get_param( 'max_posts' ) ?: 20 ) ) ),
				)
			)
		);
	}

	public function site_knowledge_search( WP_REST_Request $request ) {
		$query = $this->required_text( $request, 'query' );
		if ( is_wp_error( $query ) ) {
			return $query;
		}

		return rest_ensure_response(
			$this->client->search_site_knowledge(
				array(
					'query'           => $query,
					'intent'          => sanitize_key( (string) ( $request->get_param( 'intent' ) ?: 'site_search' ) ),
					'current_post_id' => absint( $request->get_param( 'current_post_id' ) ),
					'max_results'     => max( 1, min( 20, (int) ( $request->get_param( 'max_results' ) ?: 8 ) ) ),
					'filters'         => array(
						'source_types' => $this->csv_list( (string) $request->get_param( 'source_types' ) ),
					),
				)
			)
		);
	}

	public function site_knowledge_review_plan( WP_REST_Request $request ) {
		$params = method_exists( $request, 'get_params' ) ? $request->get_params() : array();
		return rest_ensure_response( $this->client->build_site_knowledge_review_plan( is_array( $params ) ? $params : array() ) );
	}

}
