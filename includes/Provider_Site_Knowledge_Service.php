<?php
/**
 * Cloud-managed Site Knowledge service for the provider client.
 *
 * Owns the Site Knowledge search/status/sync ability calls, the Cloud
 * boundary and ownership projections, and display filtering of Cloud agent
 * handoffs. The shared Cloud runtime execution stays on the facade because
 * the media recognition continuation reuses it. Embeddings, indexing, and
 * collection lifecycle stay Cloud owned; nothing here writes WordPress data.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Provider_Site_Knowledge_Service extends Provider_Client_Support {
	private Provider_Client $client;

	public function __construct( Settings $settings, Provider_Client $client ) {
		parent::__construct( $settings );
		$this->client = $client;
	}

	public function search_site_knowledge( array $input ) {
		$query = trim( sanitize_textarea_field( (string) ( $input['query'] ?? '' ) ) );
		if ( '' === $query ) {
			return new WP_Error(
				'npcink_toolbox_missing_site_knowledge_query',
				__( 'A query is required for site knowledge search.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$intent = sanitize_key( (string) ( $input['intent'] ?? 'site_search' ) );
		if (
			! in_array(
				$intent,
				array(
					'site_search',
					'related_content',
					'writing_context',
					'internal_links',
					'refresh_suggestions',
					'image_context',
					'faq_candidates',
					'content_gap_analysis',
					'duplicate_check',
					'summary_context',
					'writing_support_plan',
					'media_library_search',
				),
				true
			)
			) {
			$intent = 'site_search';
		}

		$filters            = is_array( $input['filters'] ?? null ) ? $this->sanitize_payload( $input['filters'] ) : array();
		$result_granularity = sanitize_key( (string) ( $input['result_granularity'] ?? '' ) );
		$payload            = array(
			'contract_version'       => 'site_knowledge_search.v1',
			'query'                  => $query,
			'intent'                 => $intent,
			'current_post_id'        => absint( $input['current_post_id'] ?? 0 ),
			'max_results'            => max( 1, min( 20, absint( $input['max_results'] ?? 8 ) ) ),
			'filters'                => is_array( $filters ) ? $filters : array(),
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
		);
		if ( in_array( $result_granularity, array( 'chunk', 'document' ), true ) ) {
			$payload['result_granularity'] = $result_granularity;
		}
		if ( 'internal_links' === $intent ) {
			$source_passages = $this->site_knowledge_source_passages( $input['source_passages'] ?? array() );
			if ( array() !== $source_passages ) {
				$payload['source_passages'] = $source_passages;
			}
		}

		return $this->client->execute_site_knowledge_cloud_request(
			'npcink-cloud/site-knowledge-search',
			'site_knowledge_search.v1',
			'inline',
			$payload,
			'site_knowledge_results',
			'site_knowledge_context'
		);
	}


	public function get_site_knowledge_status( array $input ) {
		$payload = array(
			'contract_version'       => 'site_knowledge_status.v1',
			'include_coverage'       => ! empty( $input['include_coverage'] ),
			'post_ids'               => array_slice( $this->sanitize_absint_list( $input['post_ids'] ?? array() ), 0, 1000 ),
			'media_attachment_ids'   => array_slice( $this->sanitize_absint_list( $input['media_attachment_ids'] ?? array() ), 0, 20 ),
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
		);

		return $this->client->execute_site_knowledge_cloud_request(
			'npcink-cloud/site-knowledge-status',
			'site_knowledge_status.v1',
			'inline',
			$payload,
			'site_knowledge_status',
			'site_knowledge_status'
		);
	}


	public function request_site_knowledge_sync( array $input ) {
		$sync_mode = sanitize_key( (string) ( $input['sync_mode'] ?? 'refresh' ) );
		if ( 'refresh' !== $sync_mode ) {
			return new WP_Error(
				'npcink_toolbox_site_knowledge_sync_mode_not_allowed',
				__( 'Toolbox only forwards public Site Knowledge refresh requests. Rebuild, delete, and collection lifecycle operations belong in Cloud Site Knowledge.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$payload = array(
			'contract_version'       => 'site_knowledge_sync.v1',
			'sync_mode'              => 'refresh',
			'post_ids'               => $this->sanitize_absint_list( $input['post_ids'] ?? array() ),
			'max_posts'              => max( 1, min( 50, absint( $input['max_posts'] ?? 20 ) ) ),
			'documents'              => array(),
			'payload_limits'         => array(
				'content_excerpt_chars' => self::SITE_KNOWLEDGE_CONTENT_CHARS,
				'max_payload_bytes'     => self::SITE_KNOWLEDGE_SYNC_MAX_BYTES,
				'max_comment_documents' => 100,
			),
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
		);

		$payload['documents'] = $this->client->collect_site_knowledge_documents( $payload['post_ids'], $payload['max_posts'] );

		return $this->client->execute_site_knowledge_cloud_request(
			'npcink-cloud/site-knowledge-sync',
			'site_knowledge_sync.v1',
			'whole_run_offload',
			$payload,
			'site_knowledge_sync_request',
			'site_knowledge_sync_request'
		);
	}


	public function normalize_site_knowledge_cloud_response( array $response, string $artifact_type, string $composition_role, array $runtime_payload ): array {
		$result = $this->extract_cloud_runtime_result( $response );

		$results        = is_array( $result['results'] ?? null ) ? $this->sanitize_payload( $result['results'] ) : array();
		$results        = $this->filter_current_public_site_knowledge_results( $results );
		$results        = $this->sanitize_item_url_fields( $results );
		$agent_handoff  = is_array( $result['agent_handoff'] ?? null ) ? $this->sanitize_item_url_fields( $this->sanitize_payload( $result['agent_handoff'] ) ) : array();
		$cloud_boundary = $this->normalize_site_knowledge_cloud_boundary( $result, $response, $runtime_payload );

		$payload = $this->with_output_contract(
			array(
				'provider'             => 'npcink_cloud',
				'contract_version'     => sanitize_text_field( (string) ( $runtime_payload['contract_version'] ?? '' ) ),
				'cloud_ability'        => sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? '' ) ),
				'execution_pattern'    => sanitize_key( (string) ( $runtime_payload['execution_pattern'] ?? 'inline' ) ),
				'status'               => sanitize_key( (string) ( $result['status'] ?? ( $response['status'] ?? 'unknown' ) ) ),
				'run_id'               => sanitize_text_field( (string) ( $response['run_id'] ?? ( ( $response['data']['run_id'] ?? null ) ?: ( $result['run_id'] ?? '' ) ) ) ),
				'results'              => $results,
				'coverage'             => is_array( $result['coverage'] ?? null ) ? $this->sanitize_payload( $result['coverage'] ) : array(),
				'media_evidence_items' => is_array( $result['media_evidence_items'] ?? null ) ? $this->sanitize_item_url_fields( $this->sanitize_payload( $result['media_evidence_items'] ) ) : array(),
				'sync'                 => is_array( $result['sync'] ?? null ) ? $this->sanitize_payload( $result['sync'] ) : array(),
				'progress'             => is_array( $result['progress'] ?? null ) ? $this->sanitize_payload( $result['progress'] ) : array(),
				'active_run'           => is_array( $result['active_run'] ?? null ) ? $this->sanitize_payload( $result['active_run'] ) : array(),
				'intent'               => sanitize_key( (string) ( $result['intent'] ?? '' ) ),
				'result_granularity'   => sanitize_key( (string) ( $result['result_granularity'] ?? 'chunk' ) ),
				'result_grouping'      => is_array( $result['result_grouping'] ?? null ) ? $this->sanitize_payload( $result['result_grouping'] ) : array(),
				'evidence_gate'        => is_array( $result['evidence_gate'] ?? null ) ? $this->sanitize_payload( $result['evidence_gate'] ) : array(),
				'retrieval_readiness'  => is_array( $result['retrieval_readiness'] ?? null ) ? $this->sanitize_payload( $result['retrieval_readiness'] ) : array(),
				'agent_handoff'        => $agent_handoff,
				'handoff'              => $this->site_knowledge_handoff_for_display( $agent_handoff ),
			),
			$artifact_type,
			$composition_role
		);

		if ( array() !== $cloud_boundary ) {
			$payload['site_knowledge_cloud_boundary'] = $cloud_boundary;
		}

		if ( $this->settings->raw_responses_enabled() ) {
			$payload['cloud_response'] = $this->sanitize_debug_payload( $response );
		}

		return $payload;
	}


	private function normalize_site_knowledge_cloud_boundary( array $result, array $response, array $runtime_payload ): array {
		$contract_version = sanitize_text_field( (string) ( $runtime_payload['contract_version'] ?? 'site_knowledge_status.v1' ) );
		$candidates       = array( $result, $response );

		foreach ( array( $result, $response ) as $source ) {
			if ( is_array( $source['site_knowledge_cloud_boundary'] ?? null ) ) {
				$candidates[] = $source['site_knowledge_cloud_boundary'];
			}
			if ( is_array( $source['data'] ?? null ) ) {
				$candidates[] = $source['data'];
				if ( is_array( $source['data']['site_knowledge_cloud_boundary'] ?? null ) ) {
					$candidates[] = $source['data']['site_knowledge_cloud_boundary'];
				}
				if ( is_array( $source['data']['result'] ?? null ) ) {
					$candidates[] = $source['data']['result'];
				}
			}
			if ( is_array( $source['run']['result'] ?? null ) ) {
				$candidates[] = $source['run']['result'];
			}
		}

		foreach ( $candidates as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}

			$source           = is_array( $candidate['site_knowledge_cloud_boundary'] ?? null )
				? $candidate['site_knowledge_cloud_boundary']
				: $candidate;
			$ownership        = $this->normalize_site_knowledge_ownership_map( is_array( $source['ownership'] ?? null ) ? $source['ownership'] : array() );
			$truth_boundaries = $this->normalize_site_knowledge_truth_boundaries( is_array( $source['truth_boundaries'] ?? null ) ? $source['truth_boundaries'] : array() );

			if ( array() === $ownership && array() === $truth_boundaries ) {
				continue;
			}

			return array(
				'contract_version' => sanitize_text_field( (string) ( $source['contract_version'] ?? $contract_version ) ),
				'ownership'        => $ownership,
				'truth_boundaries' => $truth_boundaries,
				'projection_owner' => 'toolbox_read_only_consumer',
			);
		}

		return array();
	}

	/**
	 * @param array<string,mixed> $ownership Raw ownership map.
	 * @return array<string,string>
	 */
	private function normalize_site_knowledge_ownership_map( array $ownership ): array {
		$allowed_keys = array(
			'source_content_owner',
			'delivery_bridge_owner',
			'index_execution_owner',
			'index_lifecycle_owner',
			'freshness_policy_owner',
			'diagnostics_detail_owner',
			'vector_storage_owner',
			'embedding_execution_owner',
			'approval_owner',
			'final_write_owner',
			'wordpress_write_owner',
		);
		$normalized   = array();

		foreach ( $allowed_keys as $key ) {
			$value = sanitize_key( (string) ( $ownership[ $key ] ?? '' ) );
			if ( '' !== $value ) {
				$normalized[ $key ] = $value;
			}
		}

		return $normalized;
	}

	/**
	 * @param array<string,mixed> $truth_boundaries Raw truth boundary map.
	 * @return array<string,bool>
	 */
	private function normalize_site_knowledge_truth_boundaries( array $truth_boundaries ): array {
		$allowed_keys = array(
			'cloud_is_index_truth',
			'cloud_is_freshness_truth',
			'cloud_is_diagnostics_truth',
			'cloud_is_wordpress_control_plane',
			'cloud_creates_wordpress_writes',
			'cloud_owns_local_approval',
			'cloud_owns_ability_registry',
			'cloud_owns_workflow_registry',
		);
		$normalized   = array();

		foreach ( $allowed_keys as $key ) {
			if ( array_key_exists( $key, $truth_boundaries ) ) {
				$normalized[ $key ] = $this->normalize_site_knowledge_bool( $truth_boundaries[ $key ] );
			}
		}

		return $normalized;
	}


	private function normalize_site_knowledge_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
		}

		return (bool) $value;
	}


	private function site_knowledge_handoff_for_display( array $agent_handoff = array() ): array {
		$handoff = array(
			'cloud_runtime'          => 'npcink_cloud_addon',
			'final_writes'           => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'write_posture'          => 'suggestion_only',
		);

		if ( array() === $agent_handoff ) {
			return $handoff;
		}

		$proposal_input = is_array( $agent_handoff['proposal_input'] ?? null ) ? $this->sanitize_payload( $agent_handoff['proposal_input'] ) : array();
		$handoff_type   = sanitize_key( (string) ( $agent_handoff['handoff_type'] ?? 'suggestion_only' ) );
		$next_action    = is_array( $proposal_input ) ? sanitize_key( (string) ( $proposal_input['local_next_action'] ?? '' ) ) : '';
		$next_steps     = array(
			__( 'Review returned site knowledge evidence before creating any local proposal.', 'npcink-workflow-toolbox' ),
		);

		if ( 'proposal_input' === $handoff_type ) {
			$next_steps[] = __( 'Use this as a Core proposal candidate only after operator review.', 'npcink-workflow-toolbox' );
			$next_steps[] = __( 'Keep final approval, preflight, audit, and WordPress writes in Core.', 'npcink-workflow-toolbox' );
		}

		return array_merge(
			$handoff,
			array(
				'agent_id'                => sanitize_key( (string) ( $agent_handoff['agent_id'] ?? '' ) ),
				'agent_version'           => sanitize_text_field( (string) ( $agent_handoff['agent_version'] ?? '' ) ),
				'handoff_type'            => $handoff_type,
				'handoff_owner'           => sanitize_key( (string) ( $agent_handoff['handoff_owner'] ?? 'wordpress_local' ) ),
				'requires_local_approval' => ! empty( $agent_handoff['requires_local_approval'] ),
				'workflow'                => sanitize_key( (string) ( $agent_handoff['workflow'] ?? '' ) ),
				'cloud_output'            => sanitize_key( (string) ( $agent_handoff['cloud_output'] ?? '' ) ),
				'evidence_gate_status'    => sanitize_key( (string) ( $agent_handoff['evidence_gate_status'] ?? '' ) ),
				'evidence_count'          => absint( $agent_handoff['evidence_count'] ?? 0 ),
				'local_next_action'       => $next_action,
				'proposal_input'          => $proposal_input,
				'next_steps'              => $next_steps,
			)
		);
	}


	private function filter_current_public_site_knowledge_results( array $results ): array {
		if ( ! function_exists( 'get_post_status' ) || ! function_exists( 'get_post_type' ) ) {
			return $results;
		}

		return array_values(
			array_filter(
				$results,
				function ( $result ): bool {
					if ( ! is_array( $result ) ) {
						return false;
					}

					$source_type = sanitize_key( (string) ( $result['source_type'] ?? '' ) );
					$post_id     = absint( $result['post_id'] ?? 0 );
					if ( 0 >= $post_id ) {
						return false;
					}

					if ( 'comment' === $source_type ) {
						if ( ! function_exists( 'get_comment' ) ) {
							return false;
						}
						$comment = get_comment( absint( $result['source_id'] ?? 0 ) );
						if ( ! $comment || 'approve' !== (string) $comment->comment_approved ) {
							return false;
						}
					}
					if ( 'media' === $source_type ) {
						$mime_type = function_exists( 'get_post_mime_type' ) ? (string) get_post_mime_type( $post_id ) : '';
						return 'attachment' === get_post_type( $post_id )
							&& 0 === strpos( $mime_type, 'image/' )
							&& current_user_can( 'edit_post', $post_id );
					}

					return 'publish' === get_post_status( $post_id )
						&& in_array( get_post_type( $post_id ), $this->client->site_knowledge_post_types(), true );
				}
			)
		);
	}


	public function site_knowledge_active_run_response( string $artifact_type, string $composition_role, array $runtime_payload ): array {
		return $this->with_output_contract(
			array(
				'provider'          => 'npcink_cloud',
				'contract_version'  => sanitize_text_field( (string) ( $runtime_payload['contract_version'] ?? '' ) ),
				'cloud_ability'     => sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? '' ) ),
				'execution_pattern' => sanitize_key( (string) ( $runtime_payload['execution_pattern'] ?? 'inline' ) ),
				'status'            => 'syncing',
				'results'           => array(),
				'coverage'          => array(),
				'sync'              => array(
					'sync_mode'          => sanitize_key( (string) ( $runtime_payload['input']['sync_mode'] ?? 'refresh' ) ),
					'accepted_documents' => 0,
					'indexed_documents'  => 0,
					'indexed_chunks'     => 0,
					'failed_documents'   => 0,
				),
				'progress'          => array(
					'status'              => 'running',
					'stage'               => 'queued',
					'message'             => __( 'Cloud indexing is already running for this site.', 'npcink-workflow-toolbox' ),
					'processed_documents' => 0,
					'total_documents'     => 0,
					'indexed_chunks'      => 0,
					'failed_documents'    => 0,
					'percent'             => 0,
				),
				'message'           => __( 'A Cloud run is already active for this site. Refresh status before starting another sync.', 'npcink-workflow-toolbox' ),
				'handoff'           => array(
					'cloud_runtime'          => 'npcink_cloud_addon',
					'final_writes'           => 'core_proposal_required',
					'direct_wordpress_write' => false,
				),
			),
			$artifact_type,
			$composition_role
		);
	}


	private function site_knowledge_source_passages( $value ): array {
		$passages    = array();
		$total_chars = 0;
		foreach ( array_slice( is_array( $value ) ? $value : array(), 0, 24 ) as $item ) {
			$text = trim( $this->bounded_text( (string) $item, 1200 ) );
			if ( '' === $text ) {
				continue;
			}
			$text_length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
			if ( $total_chars + $text_length > 12000 ) {
				break;
			}
			$passages[]   = $text;
			$total_chars += $text_length;
		}

		return $passages;
	}
}
