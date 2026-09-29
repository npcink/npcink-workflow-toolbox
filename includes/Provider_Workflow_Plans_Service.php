<?php
/**
 * Fixed-flow planning service for the provider client.
 *
 * Owns the review-only planning artifacts: article write/batch/media-batch
 * plans, image candidate adoption, article audio adoption, Site Knowledge
 * and Nightly Inspection review plans, content metadata apply-plan
 * normalization, media ALT/caption review-plan preview, media briefs,
 * derivative handoffs, AI article writing packs, and the content
 * discoverability brief. Every artifact is planning output only; proposal
 * submission, approval, execution, and WordPress writes stay outside
 * Toolbox.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Provider_Workflow_Plans_Service extends Provider_Client_Support {
	private const ARTICLE_PLAN_CONTENT_CHARS = 60000;
	private const ARTICLE_PLAN_NOTES_CHARS = 12000;

	private Provider_Client $client;

	public function __construct( Settings $settings, Provider_Client $client ) {
		parent::__construct( $settings );
		$this->client = $client;
	}

	public function build_article_write_plan( array $input ) {
		$title   = trim( sanitize_text_field( (string) ( $input['title'] ?? '' ) ) );
		$content = trim( $this->bounded_text( (string) ( $input['content_markdown'] ?? ( $input['content'] ?? '' ) ), self::ARTICLE_PLAN_CONTENT_CHARS ) );
		if ( '' === $title || '' === $content ) {
			return new WP_Error(
				'npcink_toolbox_missing_article_plan_input',
				__( 'A title and content_markdown are required to build an article write plan.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$topic   = trim( sanitize_text_field( (string) ( $input['topic'] ?? $title ) ) );
		$context = $this->settings->get_content_context_for_ability();
		$forbidden_claims = $this->sanitize_string_list( $context['claims']['forbidden'] ?? array() );
		$blocked_claims = $this->sanitize_string_list( $input['blocked_claims'] ?? array() );
		foreach ( $forbidden_claims as $claim ) {
			if ( '' !== $claim && false !== stripos( $content, $claim ) ) {
				$blocked_claims[] = $claim;
			}
		}
		$blocked_claims = array_values( array_unique( array_filter( $blocked_claims ) ) );

		$risk_level = sanitize_key( (string) ( $input['risk_level'] ?? ( empty( $blocked_claims ) ? 'low' : 'high' ) ) );
		if ( ! in_array( $risk_level, array( 'low', 'medium', 'high' ), true ) ) {
			$risk_level = 'medium';
		}
		$ready_for_proposal = empty( $blocked_claims ) && 'high' !== $risk_level;

		$goal_brief = is_array( $input['article_goal_brief'] ?? null ) ? $this->sanitize_payload( $input['article_goal_brief'] ) : array(
			'topic'           => $topic,
			'target_audience' => $this->sanitize_payload( $context['target_audience'] ?? array() ),
			'brand_voice'     => sanitize_textarea_field( (string) ( $context['brand_voice'] ?? '' ) ),
		);
		$evidence_pack = is_array( $input['research_evidence_pack'] ?? null ) ? $this->sanitize_payload( $input['research_evidence_pack'] ) : array(
			'sources' => is_array( $input['sources'] ?? null ) ? $this->sanitize_payload( $input['sources'] ) : array(),
		);
		$outline = is_array( $input['article_outline'] ?? null ) ? $this->sanitize_payload( $input['article_outline'] ) : array(
			'title'    => $title,
			'sections' => array(),
		);
		$draft_candidate = is_array( $input['article_draft_candidate'] ?? null ) ? $this->sanitize_payload( $input['article_draft_candidate'] ) : array(
			'content_markdown'  => $content,
			'used_sources'      => $this->sanitize_string_list( $input['used_sources'] ?? array() ),
			'unverified_claims' => $this->sanitize_string_list( $input['unverified_claims'] ?? array() ),
			'needs_human_input' => $this->sanitize_string_list( $input['needs_human_input'] ?? array() ),
		);
		$discoverability_pack = is_array( $input['discoverability_pack'] ?? null ) ? $this->sanitize_payload( $input['discoverability_pack'] ) : array(
			'seo_title'       => sanitize_text_field( (string) ( $input['seo_title'] ?? $title ) ),
			'seo_description' => sanitize_textarea_field( (string) ( $input['seo_description'] ?? wp_trim_words( wp_strip_all_tags( $content ), 24, '' ) ) ),
			'excerpt'         => sanitize_textarea_field( (string) ( $input['excerpt'] ?? wp_trim_words( wp_strip_all_tags( $content ), 35, '' ) ) ),
		);

		$risk_report = array(
			'risk_level'         => $risk_level,
			'blocked_claims'     => $blocked_claims,
			'needs_review'       => $this->sanitize_string_list( $input['needs_review'] ?? array() ),
			'ready_for_proposal' => $ready_for_proposal,
		);

		return array(
			'artifact_type'          => 'article_write_plan',
			'composition_role'       => 'core_article_write_plan',
			'version'                => 1,
			'source_recipe_id'       => 'article_draft_v1',
			'source_recipe_ref'      => 'npcink-abilities-toolkit/recipes/article-draft',
			'source_recipe_provider' => 'npcink-abilities-toolkit',
			'recipe_execution'       => 'local_operator_orchestration',
			'write_posture'          => 'core_proposal_handoff',
			'direct_wordpress_write' => false,
			'batch_id'               => 'article_write_' . substr( md5( $title . '|' . $content ), 0, 12 ),
			'requires_approval'      => true,
			'dry_run'                => true,
			'commit_execution'       => false,
			'proposal_mode'          => 'single',
			'article_goal_brief'     => $goal_brief,
			'research_evidence_pack' => $evidence_pack,
			'article_outline'        => $outline,
			'article_draft_candidate' => $draft_candidate,
			'discoverability_pack'   => $discoverability_pack,
			'article_risk_report'    => $risk_report,
			'write_actions'          => array(
				array(
					'action_id'         => 'create_article_draft',
					'target_ability_id' => 'npcink-abilities-toolkit/create-draft',
					'recipe_step'       => 'host_governed_create_draft',
					'input'             => array(
						'title'          => $title,
						'content'        => $content,
						'content_format' => 'markdown',
						'excerpt'        => (string) ( $discoverability_pack['excerpt'] ?? '' ),
						'status'         => 'draft',
						'dry_run'        => true,
						'commit'         => false,
					),
					'risk'              => 'medium',
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => $ready_for_proposal,
					'reason'            => __( 'Create a reviewed AI-assisted article draft through Core governance.', 'npcink-workflow-toolbox' ),
				),
			),
			'handoff'                => array(
				'plan_ability_id'        => 'npcink-toolbox/build-article-write-plan',
				'recipe_id'              => 'article_draft_v1',
				'recipe_ref'             => 'npcink-abilities-toolkit/recipes/article-draft',
				'core_route'             => '/wp-json/npcink-governance-core/v1/proposals/from-plan',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
		);
	}


	public function build_article_batch_write_plan( array $input ) {
		$articles = is_array( $input['articles'] ?? null ) ? array_values( $input['articles'] ) : array();
		if ( count( $articles ) < 2 || count( $articles ) > 5 ) {
			return new WP_Error(
				'npcink_toolbox_article_batch_size_invalid',
				__( 'Article batch write plans require 2 to 5 reviewed draft articles.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$topic          = sanitize_text_field( (string) ( $input['topic'] ?? 'Article batch draft plan' ) );
		$blocked_claims = $this->sanitize_string_list( $input['blocked_claims'] ?? array() );
		$risk_level    = sanitize_key( (string) ( $input['risk_level'] ?? ( empty( $blocked_claims ) ? 'medium' : 'high' ) ) );
		if ( ! in_array( $risk_level, array( 'low', 'medium', 'high' ), true ) ) {
			$risk_level = 'medium';
		}
		$ready_for_proposal = empty( $blocked_claims ) && 'high' !== $risk_level;
		$article_artifacts  = array();
		$write_actions      = array();
		$preview            = array();

		foreach ( $articles as $index => $article ) {
			$article = is_array( $article ) ? $article : array();
			$title   = trim( sanitize_text_field( (string) ( $article['title'] ?? '' ) ) );
			$content = trim( $this->bounded_text( (string) ( $article['content_markdown'] ?? ( $article['content'] ?? '' ) ), self::ARTICLE_PLAN_CONTENT_CHARS ) );
			if ( '' === $title || '' === $content ) {
				return new WP_Error(
					'npcink_toolbox_article_batch_item_invalid',
					__( 'Every article batch item requires title and content_markdown.', 'npcink-workflow-toolbox' ),
					array(
						'status' => 400,
						'index'  => $index,
					)
				);
			}

			$action_id = 'create_article_draft_' . ( $index + 1 );
			$excerpt   = sanitize_textarea_field( (string) ( $article['excerpt'] ?? wp_trim_words( wp_strip_all_tags( $content ), 35, '' ) ) );
			$article_artifacts[] = array(
				'article_goal_brief'      => is_array( $article['article_goal_brief'] ?? null ) ? $this->sanitize_payload( $article['article_goal_brief'] ) : array(
					'topic' => $topic,
					'title' => $title,
				),
				'research_evidence_pack'  => is_array( $article['research_evidence_pack'] ?? null ) ? $this->sanitize_payload( $article['research_evidence_pack'] ) : array(
					'sources' => is_array( $article['sources'] ?? null ) ? $this->sanitize_payload( $article['sources'] ) : array(),
				),
				'article_outline'         => is_array( $article['article_outline'] ?? null ) ? $this->sanitize_payload( $article['article_outline'] ) : array(
					'title'    => $title,
					'sections' => array(),
				),
				'article_draft_candidate' => is_array( $article['article_draft_candidate'] ?? null ) ? $this->sanitize_payload( $article['article_draft_candidate'] ) : array(
					'content_markdown' => $content,
				),
				'discoverability_pack'    => is_array( $article['discoverability_pack'] ?? null ) ? $this->sanitize_payload( $article['discoverability_pack'] ) : array(
					'excerpt' => $excerpt,
				),
				'article_risk_report'     => is_array( $article['article_risk_report'] ?? null ) ? $this->sanitize_payload( $article['article_risk_report'] ) : array(
					'risk_level'         => $risk_level,
					'blocked_claims'     => $blocked_claims,
					'ready_for_proposal' => $ready_for_proposal,
				),
			);
			$write_actions[] = array(
				'action_id'         => $action_id,
				'target_ability_id' => 'npcink-abilities-toolkit/create-draft',
				'recipe_step'       => 'host_governed_create_draft',
			'input'             => array(
					'title'          => $title,
					'content'        => $content,
					'content_format' => sanitize_key( (string) ( $article['content_format'] ?? 'plain' ) ),
					'excerpt'        => $excerpt,
					'status'         => 'draft',
					'dry_run'        => true,
					'commit'         => false,
				),
				'risk'              => 'medium',
				'requires_approval' => true,
				'commit_execution'  => false,
				'proposal_ready'    => $ready_for_proposal,
				'reason'            => __( 'Create one reviewed AI-assisted article draft through Core governance.', 'npcink-workflow-toolbox' ),
			);
			$preview[] = array(
				'action_id' => $action_id,
				'title'     => $title,
				'status'    => 'draft',
				'excerpt'   => $excerpt,
			);
		}

		return array(
			'artifact_type'             => 'article_batch_write_plan',
			'composition_role'          => 'core_article_batch_write_plan',
			'version'                   => 1,
			'source_recipe_id'          => 'article_batch_draft_v1',
			'source_recipe_ref'         => 'npcink-toolbox/recipes/article-batch-draft',
			'source_recipe_provider'    => 'npcink-toolbox',
			'recipe_execution'          => 'local_operator_orchestration',
			'write_posture'             => 'core_proposal_handoff',
			'direct_wordpress_write'    => false,
			'batch_id'                  => 'article_batch_write_' . substr( md5( $topic . '|' . wp_json_encode( $preview ) ), 0, 12 ),
			'requires_approval'         => true,
			'dry_run'                   => true,
			'commit_execution'          => false,
			'proposal_mode'             => 'batch',
			'batch_approval'            => true,
			'publish_allowed'           => false,
			'partial_success'           => false,
			'action_count'              => count( $write_actions ),
			'articles'                  => $article_artifacts,
			'preview'                   => $preview,
			'article_batch_risk_report' => array(
				'risk_level'         => $risk_level,
				'blocked_claims'     => $blocked_claims,
				'needs_review'       => $this->sanitize_string_list( $input['needs_review'] ?? array() ),
				'ready_for_proposal' => $ready_for_proposal,
			),
			'write_actions'             => $write_actions,
			'handoff'                   => array(
				'plan_ability_id'        => 'npcink-toolbox/build-article-batch-write-plan',
				'recipe_id'              => 'article_batch_draft_v1',
				'recipe_ref'             => 'npcink-toolbox/recipes/article-batch-draft',
				'core_route'             => '/wp-json/npcink-governance-core/v1/proposals/from-plan',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
		);
	}


	public function build_article_media_batch_write_plan( array $input ) {
		$articles = is_array( $input['articles'] ?? null ) ? array_values( $input['articles'] ) : array();
		if ( count( $articles ) < 1 || count( $articles ) > 5 ) {
			return new WP_Error(
				'npcink_toolbox_article_media_batch_size_invalid',
				__( 'Article media batch write plans require 1 to 5 reviewed draft articles.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$topic          = sanitize_text_field( (string) ( $input['topic'] ?? 'Article media batch draft plan' ) );
		$search_images  = true === (bool) ( $input['search_images'] ?? false );
		$image_provider = sanitize_key( (string) ( $input['image_provider'] ?? $input['provider'] ?? '' ) );
		$blocked_claims = $this->sanitize_string_list( $input['blocked_claims'] ?? array() );
		$risk_level     = sanitize_key( (string) ( $input['risk_level'] ?? ( empty( $blocked_claims ) ? 'medium' : 'high' ) ) );
		if ( ! in_array( $risk_level, array( 'low', 'medium', 'high' ), true ) ) {
			$risk_level = 'medium';
		}
		$ready_for_proposal = empty( $blocked_claims ) && 'high' !== $risk_level;
		$article_artifacts  = array();
		$write_actions      = array();
		$preview            = array();
		$media_workflow     = array();

		foreach ( $articles as $index => $article ) {
			$article = is_array( $article ) ? $article : array();
			$title   = trim( sanitize_text_field( (string) ( $article['title'] ?? '' ) ) );
			$content = trim( $this->bounded_text( (string) ( $article['content_markdown'] ?? ( $article['content'] ?? '' ) ), self::ARTICLE_PLAN_CONTENT_CHARS ) );
			if ( '' === $title || '' === $content ) {
				return new WP_Error(
					'npcink_toolbox_article_media_batch_item_invalid',
					__( 'Every article media batch item requires title and content_markdown.', 'npcink-workflow-toolbox' ),
					array(
						'status' => 400,
						'index'  => $index,
					)
				);
			}

			$candidate = $this->resolve_article_media_candidate( $article, $title, $topic, $search_images, $image_provider );
			if ( is_wp_error( $candidate ) ) {
				$candidate->add_data(
					array_merge(
						(array) $candidate->get_error_data(),
						array(
							'status' => 400,
							'index'  => $index,
						)
					)
				);
				return $candidate;
			}

			$image_url = (string) ( $candidate['regular_url'] ?? $candidate['small_url'] ?? $candidate['url'] ?? '' );
			if ( '' === $image_url ) {
				return new WP_Error(
					'npcink_toolbox_article_media_url_missing',
					__( 'Every article media batch item requires a selected image URL.', 'npcink-workflow-toolbox' ),
					array(
						'status' => 400,
						'index'  => $index,
					)
				);
			}

			$position      = $index + 1;
			$create_id     = 'create_article_draft_' . $position;
			$upload_id     = 'upload_featured_image_' . $position;
			$metadata_id   = 'update_featured_image_details_' . $position;
			$featured_id   = 'set_featured_image_' . $position;
			$excerpt       = sanitize_textarea_field( (string) ( $article['excerpt'] ?? wp_trim_words( wp_strip_all_tags( $content ), 35, '' ) ) );
			$provider      = sanitize_key( (string) ( $candidate['provider'] ?? 'external' ) );
			$candidate_source_type = sanitize_key( (string) ( $candidate['source_type'] ?? '' ) );
			if ( 'ai_generated' === $provider || 'ai_generated' === $candidate_source_type ) {
				$source_type = 'ai_generated';
			} elseif ( in_array( $provider, array( 'unsplash', 'pixabay', 'pexels' ), true ) || 'stock' === $candidate_source_type ) {
				$source_type = 'stock';
			} else {
				$source_type = 'external';
			}
			$source_url    = esc_url_raw( (string) ( $candidate['source_url'] ?? $candidate['html_url'] ?? '' ) );
			$photographer  = sanitize_text_field( (string) ( $candidate['photographer'] ?? $candidate['photographer_name'] ?? '' ) );
			$attribution   = sanitize_textarea_field( (string) ( $candidate['attribution'] ?? $candidate['attribution_text'] ?? '' ) );
			$alt           = sanitize_textarea_field( (string) ( $candidate['alt_description'] ?? $candidate['description'] ?? $title ) );
			$description   = sanitize_textarea_field( (string) ( $candidate['description'] ?? $alt ) );
			$file_name     = sanitize_file_name( (string) ( $article['file_name'] ?? $candidate['file_name'] ?? '' ) );

			$article_artifacts[] = array(
				'article_goal_brief'      => is_array( $article['article_goal_brief'] ?? null ) ? $this->sanitize_payload( $article['article_goal_brief'] ) : array(
					'topic'       => $topic,
					'title'       => $title,
					'image_query' => sanitize_text_field( (string) ( $article['image_query'] ?? $title ) ),
				),
				'research_evidence_pack'  => is_array( $article['research_evidence_pack'] ?? null ) ? $this->sanitize_payload( $article['research_evidence_pack'] ) : array(
					'sources' => is_array( $article['sources'] ?? null ) ? $this->sanitize_payload( $article['sources'] ) : array(),
				),
				'article_outline'         => is_array( $article['article_outline'] ?? null ) ? $this->sanitize_payload( $article['article_outline'] ) : array(
					'title'    => $title,
					'sections' => array(),
				),
				'article_draft_candidate' => is_array( $article['article_draft_candidate'] ?? null ) ? $this->sanitize_payload( $article['article_draft_candidate'] ) : array(
					'content_markdown' => $content,
				),
				'discoverability_pack'    => is_array( $article['discoverability_pack'] ?? null ) ? $this->sanitize_payload( $article['discoverability_pack'] ) : array(
					'excerpt' => $excerpt,
				),
				'article_risk_report'     => is_array( $article['article_risk_report'] ?? null ) ? $this->sanitize_payload( $article['article_risk_report'] ) : array(
					'risk_level'         => $risk_level,
					'blocked_claims'     => $blocked_claims,
					'ready_for_proposal' => $ready_for_proposal,
				),
				'featured_image_candidate' => $this->sanitize_payload( $candidate ),
			);

			$write_actions[] = array(
				'action_id'         => $create_id,
				'target_ability_id' => 'npcink-abilities-toolkit/create-draft',
				'recipe_step'       => 'host_governed_create_draft',
			'input'             => array(
					'title'          => $title,
					'content'        => $content,
					'content_format' => sanitize_key( (string) ( $article['content_format'] ?? 'plain' ) ),
					'excerpt'        => $excerpt,
					'status'         => 'draft',
					'dry_run'        => true,
					'commit'         => false,
					'idempotency_key' => 'article-media-draft-' . $position,
				),
				'risk'              => 'medium',
				'requires_approval' => true,
				'commit_execution'  => false,
				'proposal_ready'    => $ready_for_proposal,
				'reason'            => __( 'Create one reviewed AI-assisted article draft through Core governance.', 'npcink-workflow-toolbox' ),
			);
			$write_actions[] = array(
				'action_id'         => $upload_id,
				'target_ability_id' => 'npcink-abilities-toolkit/upload-media-from-url',
				'recipe_step'       => 'host_governed_upload_featured_image',
				'depends_on'        => array( $create_id ),
			'input'             => array(
					'url'               => $image_url,
					'title'             => $title,
					'file_name'         => $file_name,
					'alt'               => $alt,
					'caption'           => $attribution,
					'description'       => $description,
					'source_type'       => $source_type,
					'source_page_url'   => $source_url,
					'photographer_name' => $photographer,
					'attribution_text'  => $attribution,
					'copyright_notice'  => sanitize_text_field( (string) ( $candidate['copyright_notice'] ?? '' ) ),
					'attach_to_post_id' => '$outputs.' . $create_id . '.post_id',
					'dry_run'           => true,
					'commit'            => false,
					'idempotency_key'   => 'article-media-upload-' . $position,
				),
				'risk'              => 'medium',
				'requires_approval' => true,
				'commit_execution'  => false,
				'proposal_ready'    => $ready_for_proposal,
				'reason'            => __( 'Upload the reviewed image-source candidate into the media library after Core approval.', 'npcink-workflow-toolbox' ),
			);
			$write_actions[] = array(
				'action_id'         => $metadata_id,
				'target_ability_id' => 'npcink-abilities-toolkit/update-media-details',
				'recipe_step'       => 'host_governed_update_featured_image_metadata',
				'depends_on'        => array( $upload_id ),
			'input'             => array(
					'attachment_id'     => '$outputs.' . $upload_id . '.attachment_id',
					'alt'               => $alt,
					'caption'           => $attribution,
					'description'       => $description,
					'source_type'       => $source_type,
					'source_page_url'   => $source_url,
					'photographer_name' => $photographer,
					'attribution_text'  => $attribution,
					'dry_run'           => true,
					'commit'            => false,
					'idempotency_key'   => 'article-media-details-' . $position,
				),
				'risk'              => 'medium',
				'requires_approval' => true,
				'commit_execution'  => false,
				'proposal_ready'    => $ready_for_proposal,
				'reason'            => __( 'Apply reviewed image attribution and accessibility metadata after upload.', 'npcink-workflow-toolbox' ),
			);
			$write_actions[] = array(
				'action_id'         => $featured_id,
				'target_ability_id' => 'npcink-abilities-toolkit/set-post-featured-image',
				'recipe_step'       => 'host_governed_set_featured_image',
				'depends_on'        => array( $create_id, $upload_id ),
			'input'             => array(
					'post_id'        => '$outputs.' . $create_id . '.post_id',
					'attachment_id'  => '$outputs.' . $upload_id . '.attachment_id',
					'dry_run'        => true,
					'commit'         => false,
					'idempotency_key' => 'article-media-featured-' . $position,
				),
				'risk'              => 'medium',
				'requires_approval' => true,
				'commit_execution'  => false,
				'proposal_ready'    => $ready_for_proposal,
				'reason'            => __( 'Set the uploaded, reviewed media item as the draft featured image after Core approval.', 'npcink-workflow-toolbox' ),
			);

			$media_workflow[] = array(
				'article_index'      => $index,
				'title'              => $title,
				'image_query'        => sanitize_text_field( (string) ( $article['image_query'] ?? $title ) ),
				'candidate_provider' => $provider,
				'source_url'         => $source_url,
				'download_location'  => esc_url_raw( (string) ( $candidate['download_location'] ?? '' ) ),
				'attribution'        => $attribution,
				'action_ids'         => array( $create_id, $upload_id, $metadata_id, $featured_id ),
			);
			$preview[] = array(
				'action_id'         => $create_id,
				'title'             => $title,
				'status'            => 'draft',
				'excerpt'           => $excerpt,
				'featured_image_url' => $image_url,
				'attribution'       => $attribution,
			);
		}

		return array(
			'artifact_type'             => 'article_media_batch_write_plan',
			'composition_role'          => 'core_article_media_batch_write_plan',
			'version'                   => 1,
			'source_recipe_id'          => 'article_media_batch_draft_v1',
			'source_recipe_ref'         => 'npcink-toolbox/recipes/article-media-batch-draft',
			'source_recipe_provider'    => 'npcink-toolbox',
			'recipe_execution'          => 'local_operator_orchestration',
			'write_posture'             => 'core_proposal_handoff',
			'direct_wordpress_write'    => false,
			'batch_id'                  => 'article_media_batch_write_' . substr( md5( $topic . '|' . wp_json_encode( $preview ) ), 0, 12 ),
			'requires_approval'         => true,
			'dry_run'                   => true,
			'commit_execution'          => false,
			'proposal_mode'             => 'batch',
			'batch_approval'            => true,
			'publish_allowed'           => false,
			'partial_success'           => false,
			'action_count'              => count( $write_actions ),
			'articles'                  => $article_artifacts,
			'media_workflow'            => $media_workflow,
			'preview'                   => $preview,
			'article_batch_risk_report' => array(
				'risk_level'         => $risk_level,
				'blocked_claims'     => $blocked_claims,
				'needs_review'       => $this->sanitize_string_list( $input['needs_review'] ?? array() ),
				'ready_for_proposal' => $ready_for_proposal,
			),
			'write_actions'             => $write_actions,
			'handoff'                   => array(
				'plan_ability_id'        => 'npcink-toolbox/build-article-media-batch-write-plan',
				'recipe_id'              => 'article_media_batch_draft_v1',
				'recipe_ref'             => 'npcink-toolbox/recipes/article-media-batch-draft',
				'core_route'             => '/wp-json/npcink-governance-core/v1/proposals/from-plan',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
		);
	}


	public function build_image_candidate_adoption_plan( array $input ) {
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return new WP_Error(
				'npcink_toolbox_image_candidate_toolkit_unavailable',
				__( 'The Toolkit image candidate adoption-plan ability is not currently available.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$ability    = is_array( $registered ) ? ( $registered['npcink-abilities-toolkit/build-image-candidate-adoption-plan'] ?? null ) : null;
		$callback   = is_array( $ability ) ? ( $ability['execute_callback'] ?? null ) : null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error(
				'npcink_toolbox_image_candidate_toolkit_plan_unavailable',
				__( 'The Toolkit image candidate adoption-plan ability is not currently callable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$result = call_user_func( $callback, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'npcink_toolbox_image_candidate_toolkit_plan_invalid',
				__( 'The Toolkit image candidate adoption-plan ability returned an invalid response.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$data = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
		if ( empty( $data['artifact_type'] ) || 'image_candidate_adoption_plan' !== (string) $data['artifact_type'] ) {
			return new WP_Error(
				'npcink_toolbox_image_candidate_toolkit_plan_invalid_artifact',
				__( 'The Toolkit image candidate adoption-plan ability did not return the expected artifact.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return $data;
	}


	public function build_article_audio_adoption_plan( array $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return new WP_Error(
				'npcink_toolbox_article_audio_post_required',
				__( 'A post_id is required before preparing an article audio adoption plan.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$candidate = is_array( $input['audio_candidate'] ?? null ) ? $input['audio_candidate'] : array();
		$audio_url = esc_url_raw( (string) ( $candidate['url'] ?? ( $candidate['audio_url'] ?? ( $input['audio_url'] ?? '' ) ) ) );
		if ( '' === $audio_url ) {
			return new WP_Error(
				'npcink_toolbox_article_audio_url_required',
				__( 'Select an audio candidate with a playable URL before preparing Core review.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$candidate_type = sanitize_key( (string) ( $input['candidate_type'] ?? ( $candidate['candidate_type'] ?? 'article_narration' ) ) );
		if ( ! in_array( $candidate_type, array( 'article_narration', 'article_audio_summary' ), true ) ) {
			$candidate_type = 'article_narration';
		}

		$title = sanitize_text_field( (string) ( $candidate['name'] ?? ( $candidate['title'] ?? ( 'article_audio_summary' === $candidate_type ? __( 'Audio summary', 'npcink-workflow-toolbox' ) : __( 'Article narration', 'npcink-workflow-toolbox' ) ) ) ) );
		if ( '' === $title ) {
			$title = 'article_audio_summary' === $candidate_type ? __( 'Audio summary', 'npcink-workflow-toolbox' ) : __( 'Article narration', 'npcink-workflow-toolbox' );
		}

		$format = sanitize_key( (string) ( $candidate['format'] ?? ( $input['format'] ?? 'mp3' ) ) );
		if ( '' === $format ) {
			$format = 'mp3';
		}
		$mime_type = sanitize_mime_type( (string) ( $candidate['mime_type'] ?? ( $input['mime_type'] ?? '' ) ) );
		if ( '' === $mime_type ) {
			$mime_type = 'wav' === $format ? 'audio/wav' : 'audio/mpeg';
		}

		$duration_seconds = is_numeric( $candidate['duration_seconds'] ?? null ) ? (float) $candidate['duration_seconds'] : 0.0;
		if ( $duration_seconds <= 0 && is_numeric( $input['duration_seconds'] ?? null ) ) {
			$duration_seconds = (float) $input['duration_seconds'];
		}

		$script = $this->trim_chars(
			sanitize_textarea_field( (string) ( $input['script'] ?? ( $candidate['script'] ?? '' ) ) ),
			self::AUDIO_GENERATION_TEXT_CHARS
		);
		$source_audio_generation = is_array( $input['source_audio_generation'] ?? null ) ? $this->sanitize_payload( $input['source_audio_generation'] ) : array();
		$post_type               = sanitize_key( (string) ( $input['post_type'] ?? ( get_post_type( $post_id ) ?: 'post' ) ) );
		$source_content          = $this->article_audio_normalized_source_text( (string) ( $input['source_content'] ?? ( $input['source_content_text'] ?? '' ) ) );
		$source_content_hash     = sanitize_text_field( (string) ( $input['source_content_hash'] ?? '' ) );
		if ( '' === $source_content_hash && '' !== $source_content ) {
			$source_content_hash = $this->article_audio_content_hash( $source_content );
		}
		$source_word_count = absint( $input['source_word_count'] ?? 0 );
		if ( $source_word_count <= 0 && '' !== $source_content ) {
			$source_word_count = $this->article_audio_word_count( $source_content );
		}
		$source_generated_at = sanitize_text_field(
			(string) (
				$candidate['generated_at']
				?? ( $source_audio_generation['generated_at'] ?? ( $source_audio_generation['created_at'] ?? gmdate( 'c' ) ) )
			)
		);
		$voice_id                = sanitize_text_field( (string) ( $candidate['voice_id'] ?? ( $source_audio_generation['voice_id'] ?? '' ) ) );
		$model_id                = sanitize_text_field( (string) ( $candidate['model_id'] ?? ( $source_audio_generation['model_id'] ?? '' ) ) );
		$provider                = sanitize_key( (string) ( $candidate['provider'] ?? ( $source_audio_generation['provider'] ?? 'cloud_audio' ) ) );
		$trace_id                = sanitize_text_field( (string) ( $source_audio_generation['trace_id'] ?? ( $source_audio_generation['trace'] ?? '' ) ) );
		$import_media            = array_key_exists( 'import_media', $input ) ? ! empty( $input['import_media'] ) : true;
		$media_file_name         = sanitize_text_field( (string) ( $input['media_file_name'] ?? '' ) );
		$planner_id              = 'npcink-abilities-toolkit/build-article-audio-adoption-plan';
		$write_ability_id        = 'npcink-abilities-toolkit/adopt-article-audio';
		$planner_available       = $this->registered_ability_callable( $planner_id );
		$write_available         = $this->registered_ability_callable( $write_ability_id );
		$proposal_ready          = $planner_available && $write_available;
		$idempotency_key         = 'article-audio-adoption-' . substr( md5( $post_id . '|' . $candidate_type . '|' . $audio_url ), 0, 16 );
		$audio_hash              = md5( $audio_url );

		$missing_dependencies = array();
		if ( ! $planner_available ) {
			$missing_dependencies[] = array(
				'ability_id' => $planner_id,
				'status'     => 'not_registered_or_not_callable',
			);
		}
		if ( ! $write_available ) {
			$missing_dependencies[] = array(
				'ability_id' => $write_ability_id,
				'status'     => 'not_registered_or_not_callable',
			);
		}

		$meta_projection = array(
			'_npcink_toolbox_article_audio_url'              => $audio_url,
			'_npcink_toolbox_article_audio_title'            => $title,
			'_npcink_toolbox_article_audio_kind'             => $candidate_type,
			'_npcink_toolbox_article_audio_duration_seconds' => $duration_seconds,
			'_npcink_toolbox_article_audio_mime_type'        => $mime_type,
			'_npcink_toolbox_article_audio_source_content_hash' => $source_content_hash,
			'_npcink_toolbox_article_audio_source_word_count' => $source_word_count,
			'_npcink_toolbox_article_audio_source_generated_at' => $source_generated_at,
		);

		$audio_candidate = array(
			'url'              => $audio_url,
			'title'            => $title,
			'name'             => $title,
			'candidate_type'   => $candidate_type,
			'format'           => $format,
			'mime_type'        => $mime_type,
			'duration_seconds' => $duration_seconds,
			'voice_id'         => $voice_id,
			'model_id'         => $model_id,
			'provider'         => $provider,
		);

		return array(
			'artifact_type'            => 'article_audio_adoption_plan.v1',
			'composition_role'         => 'core_article_audio_adoption_plan',
			'version'                  => 1,
			'post_id'                  => $post_id,
			'post_type'                => $post_type,
			'candidate_type'           => $candidate_type,
			'write_posture'            => 'core_proposal_handoff',
			'final_write_path'         => 'core_proposal_required',
			'direct_wordpress_write'   => false,
			'proposal_ready'           => $proposal_ready,
			'requires_approval'        => true,
			'dry_run'                  => true,
			'commit_execution'         => false,
			'proposal_mode'            => 'single',
			'target_plan_ability_id'   => $planner_id,
			'target_write_ability_id'  => $write_ability_id,
			'missing_dependencies'     => $missing_dependencies,
			'audio_candidate'          => $this->sanitize_payload( $audio_candidate ),
			'script'                   => $script,
			'source_audio_generation'  => $source_audio_generation,
			'evidence_refs'            => array(
				array(
					'kind'        => 'article_audio_candidate',
					'post_id'     => $post_id,
					'audio_hash'  => $audio_hash,
					'provider'    => $provider,
					'model_id'    => $model_id,
					'voice_id'    => $voice_id,
					'trace_id'    => $trace_id,
					'url_host'    => sanitize_text_field( (string) wp_parse_url( $audio_url, PHP_URL_HOST ) ),
					'import_media' => $import_media,
					'script_hash' => '' !== $script ? md5( $script ) : '',
					'source_content_hash' => $source_content_hash,
					'source_word_count' => $source_word_count,
					'source_generated_at' => $source_generated_at,
				),
			),
			'preview'                  => array(
				array(
					'action_id'        => 'adopt_article_audio',
					'post_id'          => $post_id,
					'candidate_type'   => $candidate_type,
					'audio_title'      => $title,
					'audio_url'        => $audio_url,
					'storage_mode'     => $import_media ? 'wordpress_media_library' : 'remote_url',
					'meta_projection'  => $meta_projection,
					'audio_freshness'  => array(
						'initial_status'      => '' !== $source_content_hash ? 'current' : 'unknown',
						'source_content_hash' => $source_content_hash,
						'source_word_count'   => $source_word_count,
						'source_generated_at' => $source_generated_at,
						'policy'              => 'hash_match_current_else_word_count_delta_thresholds',
					),
					'proposal_ready'   => $proposal_ready,
					'write_owner'      => 'npcink-abilities-toolkit',
					'governance_owner' => 'npcink-governance-core',
				),
			),
			'write_actions'            => array(
				array(
					'action_id'         => 'adopt_article_audio',
					'target_ability_id' => $write_ability_id,
					'recipe_step'       => 'host_governed_article_audio_adoption',
					'input'             => array(
						'post_id'             => $post_id,
						'audio_url'           => $audio_url,
						'audio_title'         => $title,
						'audio_kind'          => $candidate_type,
						'duration_seconds'    => $duration_seconds,
						'mime_type'           => $mime_type,
						'source_content_hash' => $source_content_hash,
						'source_word_count'   => $source_word_count,
						'source_generated_at' => $source_generated_at,
						'provider'            => $provider,
						'model'               => $model_id,
						'trace_id'            => $trace_id,
						'import_media'        => $import_media,
						'media_file_name'     => $media_file_name,
						'dry_run'             => true,
						'commit'              => false,
						'idempotency_key'     => $idempotency_key,
					),
					'risk'              => 'low',
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => $proposal_ready,
					'reason'            => __( 'Adopting generated article audio imports the reviewed audio into the local media library when requested and writes playback metadata through Core governance before Adapter execution.', 'npcink-workflow-toolbox' ),
				),
			),
			'blocked_actions'          => array(
				'no_audio_meta_write_in_toolbox',
				'no_media_import_in_toolbox',
				'no_post_content_patch',
				'no_direct_wordpress_write',
			),
			'handoff'                  => array(
				'plan_ability_id'        => $planner_id,
				'recipe_id'              => 'article_audio_adoption_v1',
				'recipe_ref'             => 'workflow/article_audio_adoption',
				'core_route'             => '/wp-json/npcink-governance-core/v1/proposals/from-plan',
				'adapter_route'          => '/wp-json/npcink-openclaw-adapter/v1/proposals/from-plan',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'proposal_ready'         => $proposal_ready,
			),
		);
	}


	public function build_site_knowledge_review_plan( array $input ) {
		$proposal_input = $input['proposal_input'] ?? array();
		if ( is_string( $proposal_input ) ) {
			$decoded        = json_decode( $proposal_input, true );
			$proposal_input = is_array( $decoded ) ? $decoded : array();
		}
		$proposal_input = is_array( $proposal_input ) ? $proposal_input : array();

		$handoff = $input['handoff'] ?? array();
		if ( is_string( $handoff ) ) {
			$decoded = json_decode( $handoff, true );
			$handoff = is_array( $decoded ) ? $decoded : array();
		}
		$handoff = is_array( $handoff ) ? $handoff : array();

		$evidence_refs = is_array( $proposal_input['evidence_refs'] ?? null ) ? array_values( $proposal_input['evidence_refs'] ) : array();
		if ( empty( $evidence_refs ) && is_array( $handoff['proposal_input']['evidence_refs'] ?? null ) ) {
			$evidence_refs = array_values( $handoff['proposal_input']['evidence_refs'] );
		}
		if ( empty( $evidence_refs ) ) {
			return new WP_Error(
				'npcink_toolbox_site_knowledge_review_evidence_required',
				__( 'Site Knowledge review plans require evidence_refs from the Cloud handoff.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$blocked_outputs = is_array( $proposal_input['blocked_outputs'] ?? null ) ? array_values( $proposal_input['blocked_outputs'] ) : array();
		$workflow        = sanitize_key( (string) ( $handoff['workflow'] ?? ( $proposal_input['workflow'] ?? 'site_knowledge_review' ) ) );
		$intent          = sanitize_key( (string) ( $proposal_input['intent'] ?? $workflow ) );
		$cloud_output    = sanitize_key( (string) ( $handoff['cloud_output'] ?? ( $proposal_input['cloud_output'] ?? 'proposal_candidate' ) ) );
		$next_action     = sanitize_key( (string) ( $proposal_input['local_next_action'] ?? ( $handoff['local_next_action'] ?? 'operator_review' ) ) );
		$title_hint      = sanitize_text_field( (string) ( $proposal_input['title_hint'] ?? ( $input['title_hint'] ?? '' ) ) );
		$content_hint    = sanitize_textarea_field( (string) ( $proposal_input['content_hint'] ?? ( $input['content_hint'] ?? '' ) ) );
		if ( '' === trim( $title_hint ) ) {
			$title_hint = __( 'Site Knowledge review draft requires a human title', 'npcink-workflow-toolbox' );
		}
		if ( '' === trim( $content_hint ) ) {
			$content_hint = __( 'Human draft content is required before this Site Knowledge review proposal can proceed.', 'npcink-workflow-toolbox' );
		}
		$agent_id        = sanitize_key( (string) ( $handoff['agent_id'] ?? ( $proposal_input['agent_id'] ?? 'site_knowledge_suggestion_agent' ) ) );
		$agent_version   = sanitize_text_field( (string) ( $handoff['agent_version'] ?? ( $proposal_input['agent_version'] ?? '' ) ) );
		$evidence_status = sanitize_key( (string) ( $handoff['evidence_gate_status'] ?? ( $proposal_input['evidence_gate_status'] ?? '' ) ) );
		$evidence_count  = absint( $handoff['evidence_count'] ?? ( $proposal_input['evidence_count'] ?? count( $evidence_refs ) ) );
		$action_id       = 'review_site_knowledge_gap';

		$preview = array(
			array(
				'action_id'            => $action_id,
				'workflow'             => $workflow,
				'intent'               => $intent,
				'cloud_output'         => $cloud_output,
				'local_next_action'    => $next_action,
				'evidence_count'       => $evidence_count,
				'evidence_gate_status' => $evidence_status,
				'proposal_ready'       => false,
			),
		);

		return array(
			'artifact_type'          => 'site_knowledge_review_plan',
			'composition_role'       => 'core_site_knowledge_review_plan',
			'version'                => 1,
			'source_recipe_id'       => 'site_knowledge_review_v1',
			'source_recipe_ref'      => 'workflow/site_knowledge_review',
			'source_recipe_provider' => 'npcink-toolbox',
			'recipe_execution'       => 'local_operator_orchestration',
			'write_posture'          => 'core_proposal_handoff',
			'direct_wordpress_write' => false,
			'batch_id'               => 'site_knowledge_review_' . substr( md5( $workflow . '|' . $intent . '|' . wp_json_encode( $evidence_refs ) ), 0, 12 ),
			'requires_approval'      => true,
			'dry_run'                => true,
			'commit_execution'       => false,
			'proposal_mode'          => 'single',
			'agent_id'               => $agent_id,
			'agent_version'          => $agent_version,
			'workflow'               => $workflow,
			'intent'                 => $intent,
			'cloud_output'           => $cloud_output,
			'local_next_action'      => $next_action,
			'evidence_gate_status'   => $evidence_status,
			'evidence_count'         => $evidence_count,
			'evidence_refs'          => $this->sanitize_payload( $evidence_refs ),
			'blocked_outputs'        => $this->sanitize_payload( $blocked_outputs ),
			'proposal_input'         => $this->sanitize_payload( $proposal_input ),
			'preview'                => $preview,
			'manual_review'          => array(
				array(
					'code'   => 'human_draft_required',
					'fields' => array( 'title', 'content' ),
					'reason' => __( 'Site Knowledge evidence can justify a review proposal, but a human must decide the final draft title and content before commit preflight.', 'npcink-workflow-toolbox' ),
				),
			),
			'write_actions'          => array(
				array(
					'action_id'         => $action_id,
					'target_ability_id' => 'npcink-abilities-toolkit/create-draft',
					'recipe_step'       => 'host_governed_review_draft',
					'input'             => array(
						'title'           => $title_hint,
						'content'         => $content_hint,
						'status'          => 'draft',
						'meta'            => array(
							'site_knowledge_evidence_refs' => $this->sanitize_payload( $evidence_refs ),
							'site_knowledge_workflow'      => $workflow,
							'site_knowledge_intent'        => $intent,
						),
						'dry_run'         => true,
						'commit'          => false,
						'idempotency_key' => 'site-knowledge-review-' . substr( md5( $workflow . '|' . $intent . '|' . wp_json_encode( $evidence_refs ) ), 0, 12 ),
					),
					'risk'              => 'medium',
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => false,
					'requires_input'    => array( 'title', 'content' ),
					'reason'            => __( 'Create a blocked Core review proposal from evidence-backed Site Knowledge suggestions; human draft input is required before execution can be considered.', 'npcink-workflow-toolbox' ),
				),
			),
			'handoff'                => array(
				'plan_ability_id'        => 'npcink-toolbox/build-site-knowledge-review-plan',
				'recipe_id'              => 'site_knowledge_review_v1',
				'recipe_ref'             => 'workflow/site_knowledge_review',
				'core_route'             => '/wp-json/npcink-governance-core/v1/proposals/from-plan',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'proposal_ready'         => false,
			),
		);
	}


	public function build_nightly_inspection_review_plan( array $input ) {
		$selected_items = is_array( $input['selected_items'] ?? null ) ? array_values( $input['selected_items'] ) : array();
		if ( empty( $selected_items ) ) {
			return new WP_Error(
				'npcink_toolbox_nightly_inspection_review_items_required',
				__( 'Select at least one scheduled review item before creating a Core proposal.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$selected_items = array_slice( $selected_items, 0, 5 );
		$cloud_run_id   = sanitize_text_field( (string) ( $input['cloud_run_id'] ?? ( $input['run_id'] ?? '' ) ) );
		$agent_version  = sanitize_text_field( (string) ( $input['agent_version'] ?? 'nightly_site_inspection_cloud_runtime.v1' ) );
		$core_intake_package = is_array( $input['core_intake_package'] ?? null ) ? $this->sanitize_payload( $input['core_intake_package'] ) : array();
		$core_intake_summary = array(
			'contract_version'                 => sanitize_text_field( (string) ( $core_intake_package['contract_version'] ?? '' ) ),
			'target_route'                     => sanitize_text_field( (string) ( $core_intake_package['target_route'] ?? '' ) ),
			'target_plan_ability_id'           => sanitize_text_field( (string) ( $core_intake_package['target_plan_ability_id'] ?? '' ) ),
			'target_plan_contract'             => sanitize_text_field( (string) ( $core_intake_package['target_plan_contract'] ?? '' ) ),
			'core_review_plan_idempotency_key' => sanitize_text_field( (string) ( $core_intake_package['core_review_plan_idempotency_key'] ?? '' ) ),
			'proposal_state_owner'             => sanitize_key( (string) ( $core_intake_package['proposal_state_owner'] ?? '' ) ),
			'approval_truth'                   => sanitize_key( (string) ( $core_intake_package['approval_truth'] ?? '' ) ),
			'final_write_truth'                => sanitize_key( (string) ( $core_intake_package['final_write_truth'] ?? '' ) ),
			'receipt_expectation'              => is_array( $core_intake_package['receipt_expectation'] ?? null ) ? $this->sanitize_payload( $core_intake_package['receipt_expectation'] ) : array(),
			'direct_wordpress_write'           => false,
			'proposal_created'                 => false,
		);
		$evidence_refs  = array();
		$issue_types    = array();
		$max_score      = null;

		foreach ( $selected_items as $index => $raw_item ) {
			$item = is_array( $raw_item ) ? $raw_item : array();
			$action_id = sanitize_text_field( (string) ( $item['action_id'] ?? '' ) );
			if ( '' === $action_id ) {
				$action_id = 'morning_brief_review_' . ( $index + 1 );
			}
			$object_type  = sanitize_key( (string) ( $item['object_type'] ?? 'content' ) );
			$object_id    = sanitize_text_field( (string) ( $item['object_id'] ?? '' ) );
			$reason_codes = $this->sanitize_string_list( $item['reason_codes'] ?? array() );
			$score        = is_numeric( $item['score'] ?? null ) ? (float) $item['score'] : null;
			if ( null !== $score ) {
				$max_score = null === $max_score ? $score : max( $max_score, $score );
			}
			$issue_types = array_merge( $issue_types, $reason_codes );

			$evidence_refs[] = array(
				'action_id'               => $action_id,
				'title'                   => $this->bounded_text( sanitize_text_field( (string) ( $item['title'] ?? __( 'Scheduled review item', 'npcink-workflow-toolbox' ) ) ), 160 ),
				'object_type'             => $object_type,
				'object_id'               => $object_id,
				'post_id'                 => absint( $item['post_id'] ?? ( 'post' === $object_type ? $object_id : 0 ) ),
				'score'                   => null === $score ? null : $score,
				'severity'                => sanitize_key( (string) ( $item['severity'] ?? '' ) ),
				'reason_codes'            => $reason_codes,
				'evidence_summary'        => $this->bounded_text( sanitize_textarea_field( (string) ( $item['evidence_summary'] ?? '' ) ), 500 ),
				'recommended_next_action' => sanitize_key( (string) ( $item['recommended_next_action'] ?? 'operator_review' ) ),
				'suggested_use'           => 'morning_brief_review_evidence',
			);
		}

		$run_basis       = '' !== $cloud_run_id ? $cloud_run_id : wp_json_encode( $evidence_refs );
		$idempotency_key = 'nightly-inspection-review-' . substr( md5( (string) $run_basis ), 0, 16 );
		$issue_types     = array_values( array_unique( array_filter( $issue_types ) ) );
		if ( empty( $issue_types ) ) {
			$issue_types = array( 'nightly_site_inspection' );
		}

		return array(
			'artifact_type'          => 'nightly_site_inspection_review_plan',
			'contract_version'       => 'nightly_site_inspection_core_review_plan.v1',
			'version'                => 1,
			'batch_id'               => '' !== $cloud_run_id ? $cloud_run_id : $idempotency_key,
			'cloud_run_id'           => $cloud_run_id,
			'requires_approval'      => true,
			'dry_run'                => true,
			'commit_execution'       => false,
			'proposal_mode'          => 'single',
			'write_posture'          => 'core_proposal_handoff',
			'direct_wordpress_write' => false,
			'runtime_owner'          => 'npcink-local-automation-runtime',
			'agent_id'               => 'nightly_site_inspection_cloud_runtime',
			'agent_version'          => $agent_version,
			'workflow'               => 'nightly_site_inspection',
			'intent'                 => 'morning_review_preparation',
			'cloud_output'           => 'proposal_candidate',
			'local_next_action'      => 'operator_review',
			'evidence_gate_status'   => 'passed',
			'evidence_refs'          => $this->sanitize_payload( $evidence_refs ),
			'source_context'         => array(
				'cloud_intake_package_available' => ! empty( array_filter( $core_intake_summary ) ),
				'cloud_core_intake_package'      => $this->sanitize_payload( $core_intake_summary ),
				'direct_wordpress_write'         => false,
				'proposal_created'               => false,
				'approval_truth'                 => 'wordpress_local',
				'final_write_truth'              => 'wordpress_local',
			),
			'blocked_outputs'        => array(
				'direct_wordpress_write',
				'article_body',
				'article_write_plan',
				'final_seo_copy',
				'automatic_publish',
			),
			'issue_types'            => $this->sanitize_payload( $issue_types ),
			'risk'                   => array(
				'level'  => null !== $max_score && $max_score >= 80 ? 'high' : 'medium',
				'reason' => 'operator_review_required',
			),
			'preview'                => array(
				array(
					'action_id'          => 'review_nightly_site_inspection',
					'proposal_ready'     => false,
					'evidence_ref_count' => count( $evidence_refs ),
				),
			),
			'write_actions'          => array(
				array(
					'action_id'         => 'review_nightly_site_inspection',
					'target_ability_id' => 'npcink-abilities-toolkit/create-draft',
					'recipe_step'       => 'host_governed_review_draft',
					'input'             => array(
						'title'           => '',
						'content'         => '',
						'status'          => 'draft',
						'meta'            => array(
							'nightly_inspection_cloud_run_id' => $cloud_run_id,
							'nightly_inspection_evidence_refs' => $this->sanitize_payload( $evidence_refs ),
							'nightly_inspection_core_intake_package' => $this->sanitize_payload( $core_intake_summary ),
						),
						'dry_run'         => true,
						'commit'          => false,
						'idempotency_key' => $idempotency_key,
					),
					'risk'              => null !== $max_score && $max_score >= 80 ? 'high' : 'medium',
					'requires_approval' => true,
					'commit_execution'  => false,
					'proposal_ready'    => false,
					'requires_input'    => array( 'title', 'content' ),
					'reason'            => __( 'Scheduled review found reviewable content quality signals. Human draft title and content are required before execution can be considered.', 'npcink-workflow-toolbox' ),
				),
			),
			'handoff'                => array(
				'plan_ability_id'        => 'npcink-toolbox/build-nightly-inspection-review-plan',
				'recipe_id'              => 'nightly_inspection_review_v1',
				'recipe_ref'             => 'workflow/nightly_site_inspection_review',
				'core_route'             => '/wp-json/npcink-governance-core/v1/proposals/from-plan',
				'core_intake_package'    => $this->sanitize_payload( $core_intake_summary ),
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'proposal_ready'         => false,
			),
		);
	}


	public function build_content_metadata_apply_plan( array $input ) {
		$ability_id = 'npcink-abilities-toolkit/build-content-metadata-apply-plan';
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return new WP_Error(
				'npcink_toolbox_content_metadata_toolkit_unavailable',
				__( 'Npcink Abilities Toolkit is required to build a content metadata apply plan.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$registered = npcink_abilities_toolkit_get_registered();
		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();
		$callback   = $definition['execute_callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return new WP_Error(
				'npcink_toolbox_content_metadata_toolkit_plan_unavailable',
				__( 'The Toolkit content metadata apply-plan ability is not currently callable.', 'npcink-workflow-toolbox' ),
				array( 'status' => 503 )
			);
		}

		$result = call_user_func( $callback, $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'npcink_toolbox_content_metadata_toolkit_plan_invalid',
				__( 'The Toolkit content metadata apply-plan ability returned an invalid response.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		$data = is_array( $result['data'] ?? null ) ? $result['data'] : $result;
		if ( empty( $data['artifact_type'] ) || 'content_metadata_apply_plan' !== (string) $data['artifact_type'] ) {
			return new WP_Error(
				'npcink_toolbox_content_metadata_toolkit_plan_invalid_artifact',
				__( 'The Toolkit content metadata apply-plan ability did not return the expected artifact.', 'npcink-workflow-toolbox' ),
				array( 'status' => 500 )
			);
		}

		return $this->normalize_content_metadata_apply_plan_contract( $data );
	}


	public function build_media_alt_caption_review_plan( array $input ): array {
		$selected_items = is_array( $input['selected_items'] ?? null ) ? $input['selected_items'] : array();
		$actions        = array();
		$blocked_actions = array();

		foreach ( $selected_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$attachment_id = absint( $item['attachment_id'] ?? 0 );
			if ( 0 >= $attachment_id ) {
				continue;
			}

			$alt_candidates = is_array( $item['alt_candidates'] ?? null ) ? $item['alt_candidates'] : array();
			$raw_alt        = array_key_exists( 'accepted_alt', $item ) ? (string) $item['accepted_alt'] : (string) ( $alt_candidates[0] ?? '' );
			$proposed_alt   = $this->client->media_alt_caption_clean_candidate( $raw_alt );
			$proposed_caption = $this->client->media_alt_caption_clean_candidate( (string) ( $item['accepted_caption'] ?? '' ) );
			$alt_rejection = '' !== $proposed_alt ? $this->client->media_alt_caption_candidate_rejection_reason( $proposed_alt, $item, 'alt' ) : '';
			if ( '' !== $alt_rejection ) {
				$blocked_actions[] = array(
					'action_id'            => 'media-alt-caption:' . $attachment_id . ':alt',
					'attachment_id'        => $attachment_id,
					'rejected_field'       => 'alt',
					'blocked_reason'       => $alt_rejection,
					'operator_next_action' => 'revise_alt_before_core_handoff',
				);
				$proposed_alt = '';
			}
			$caption_rejection = '' !== $proposed_caption ? $this->client->media_alt_caption_candidate_rejection_reason( $proposed_caption, $item, 'caption' ) : '';
			if ( '' !== $caption_rejection ) {
				$blocked_actions[] = array(
					'action_id'            => 'media-alt-caption:' . $attachment_id . ':caption',
					'attachment_id'        => $attachment_id,
					'rejected_field'       => 'caption',
					'blocked_reason'       => $caption_rejection,
					'operator_next_action' => 'revise_caption_before_core_handoff',
				);
				$proposed_caption = '';
			}
			if ( '' !== $proposed_caption ) {
				$blocked_actions[] = array(
					'action_id'            => 'media-alt-caption:' . $attachment_id . ':caption',
					'attachment_id'        => $attachment_id,
					'rejected_field'       => 'caption',
					'blocked_reason'       => 'caption_requires_manual_review',
					'operator_next_action' => 'submit_alt_only_or_review_caption_manually',
				);
				$proposed_caption = '';
			}
			if ( '' === $proposed_alt ) {
				continue;
			}

			$title             = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$filename          = sanitize_text_field( (string) ( $item['filename'] ?? '' ) );
			$current_alt       = sanitize_text_field( (string) ( $item['current_alt'] ?? ( $item['alt'] ?? '' ) ) );
			$current_caption   = sanitize_textarea_field( (string) ( $item['current_caption'] ?? ( $item['caption'] ?? '' ) ) );
			$current_alt_status = sanitize_key( (string) ( $item['current_alt_status'] ?? '' ) );
			$candidate_basis   = $this->sanitize_string_list( $item['candidate_basis'] ?? array() );
			$candidate_flags   = $this->sanitize_string_list( $item['candidate_quality_flags'] ?? array() );
			$candidate_fact_types    = $this->sanitize_string_list( $item['candidate_fact_types'] ?? array() );
			$candidate_confidence    = sanitize_key( (string) ( $item['candidate_confidence'] ?? '' ) );
			$candidate_review_status = sanitize_key( (string) ( $item['candidate_review_status'] ?? '' ) );
			$needs_context_confirmation = ! empty( $item['needs_context_confirmation'] )
				|| in_array( 'needs_context_confirmation', $candidate_flags, true )
				|| 'needs_context_confirmation' === $candidate_review_status;
			$context_confirmed = $this->is_truthy( $item['context_confirmed'] ?? false );
			if ( $needs_context_confirmation && $this->client->media_alt_caption_candidate_needs_context_confirmation( $proposed_alt ) && ! $context_confirmed ) {
				$blocked_actions[] = array(
					'action_id'                  => 'media-alt-caption:' . $attachment_id . ':alt',
					'attachment_id'              => $attachment_id,
					'rejected_field'             => 'alt',
					'blocked_reason'             => 'context_confirmation_required',
					'candidate_review_status'    => 'needs_context_confirmation',
					'needs_context_confirmation' => true,
					'operator_next_action'       => 'confirm_context_terms_or_edit_alt',
				);
				continue;
			}
			$proposal_input    = array(
				'attachment_id'   => $attachment_id,
				'alt'             => $proposed_alt,
				'dry_run'         => true,
				'commit'          => false,
				'idempotency_key' => 'toolbox-media-alt-' . $attachment_id . '-' . substr( md5( $proposed_alt ), 0, 12 ),
			);
			$proposal_preview  = array(
				'artifact_type'                 => 'media_alt_caption_review_item',
				'contract_version'             => 'media_alt_caption_review_item.v1',
				'review_set_contract'          => 'media_alt_caption_review_set.v1',
				'source'                       => array(
					'type'    => 'toolbox_media_alt_caption_review',
					'surface' => 'npcink_toolbox_batch_alt',
				),
				'attachment_id'                => $attachment_id,
				'title'                        => $title,
				'filename'                     => $filename,
				'current_alt_status'           => $current_alt_status,
				'current_alt'                  => $current_alt,
				'proposed_alt'                 => $proposed_alt,
				'candidate_basis'              => $candidate_basis,
				'candidate_quality_flags'      => $candidate_flags,
				'candidate_fact_types'         => $candidate_fact_types,
				'candidate_confidence'         => $candidate_confidence,
				'candidate_review_status'      => $needs_context_confirmation && ! $context_confirmed ? 'needs_context_confirmation' : $candidate_review_status,
				'needs_context_confirmation'   => $needs_context_confirmation,
				'context_confirmed'            => $context_confirmed,
				'operator_reviewed'            => true,
				'operator_visual_review_confirmed' => true,
				'visual_confirmation_required' => true,
				'direct_wordpress_write'       => false,
			);

			$actions[] = array(
				'action_id'                   => 'media-alt-caption:' . $attachment_id,
				'attachment_id'               => $attachment_id,
				'title'                       => $title,
				'filename'                    => $filename,
				'current_alt_status'          => $current_alt_status,
				'current_caption_status'      => sanitize_key( (string) ( $item['current_caption_status'] ?? '' ) ),
				'current_alt'                 => $current_alt,
				'current_caption'             => $current_caption,
				'thumbnail_url'               => esc_url_raw( (string) ( $item['thumbnail_url'] ?? '' ) ),
				'accepted_alt'                => $proposed_alt,
				'accepted_caption'            => '',
				'needs_human_visual_check'    => true,
				'visual_confirmation_required' => true,
				'candidate_basis'             => $candidate_basis,
				'candidate_quality_flags'     => $candidate_flags,
				'candidate_fact_types'        => $candidate_fact_types,
				'candidate_confidence'        => $candidate_confidence,
				'candidate_review_status'     => $needs_context_confirmation && ! $context_confirmed ? 'needs_context_confirmation' : $candidate_review_status,
				'needs_context_confirmation'  => $needs_context_confirmation,
				'context_confirmed'           => $context_confirmed,
				'target_ability_id'           => 'npcink-abilities-toolkit/update-media-details',
					'target_write_path'           => 'core_proposal_required',
					'auto_execution_supported'    => false,
					'submission_status'           => 'preview_only_not_submitted',
					'target_contract_status'      => 'future_or_unavailable',
					'proposal_created'            => false,
					'execution_created'           => false,
					'not_submittable'             => true,
					'future_contract_preview'     => array(
						'ability_id'              => 'npcink-abilities-toolkit/update-media-details',
						'submission_status'       => 'preview_only_not_submitted',
						'target_contract_status'  => 'future_or_unavailable',
						'not_submittable'         => true,
						'proposal_created'        => false,
						'execution_created'       => false,
						'direct_wordpress_write'  => false,
						'title'                   => sprintf( 'Preview ALT update for attachment #%d', $attachment_id ),
						'summary'                 => 'Preview one reviewed ALT text suggestion for a media-library image. No proposal is created from this Toolbox preview.',
						'input'                   => $proposal_input,
						'preview'                 => $proposal_preview,
					),
				'direct_wordpress_write'      => false,
			);
		}

		$review_set = is_array( $input['review_set'] ?? null ) ? $this->sanitize_payload( $input['review_set'] ) : array();
		return array(
			'artifact_type'          => 'media_alt_caption_core_handoff_plan',
			'contract_version'      => 'media_alt_caption_core_handoff_plan.v1',
			'composition_role'       => 'core_handoff_draft',
			'write_posture'          => 'suggestion_only',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'proposal_created'       => false,
				'core_submission'        => 'preview_only_not_submitted',
				'workflow_runtime'       => false,
			'queue_created'          => false,
			'selected_count'         => count( $actions ),
			'selected_actions'       => $actions,
			'blocked_actions'        => $blocked_actions,
			'review_set_summary'     => array(
				'contract_version' => sanitize_text_field( (string) ( $review_set['contract_version'] ?? '' ) ),
				'source_policy'    => sanitize_key( (string) ( $review_set['source_policy'] ?? '' ) ),
				'media_scope'      => sanitize_key( (string) ( $review_set['media_scope'] ?? '' ) ),
				'selected_count'   => absint( $review_set['selected_count'] ?? count( $actions ) ),
			),
			'core_auto_approval_policy' => array(
				'request_supported'          => false,
				'toolbox_direct_apply'       => false,
					'approval_owner'             => 'npcink-governance-core',
					'execution_owner'            => 'wordpress_abilities',
					'safe_action_candidate'      => 'fill_missing_or_weak_alt_only',
					'current_stage'              => 'future_policy_only',
					'required_policy_checks'     => array(
					'operator_enabled_core_policy',
					'missing_or_weak_alt_only',
					'candidate_quality_gate_passed',
					'context_terms_confirmed_or_removed',
					'no_runtime_provenance_text',
					'no_source_attribution_text',
					'operator_visual_confirmation',
					'bounded_batch_size',
					'old_value_audit_and_rollback_evidence',
				),
			),
			'handoff'                => array(
				'plan_route'             => '/wp-json/npcink-toolbox/v1/flows/media-alt-caption-review-plan',
				'plan_surface'           => 'toolbox_rest_route',
				'target_ability_id'      => 'npcink-abilities-toolkit/update-media-details',
				'recipe_id'              => 'media_alt_caption_review_v1',
				'core_route'             => '/wp-json/npcink-governance-core/v1/proposals/from-plan',
					'proposal_ready'         => false,
					'preview_available'      => 0 < count( $actions ),
				'core_submission'        => 'preview_only_not_submitted',
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
			'operator_next_action'   => 0 < count( $actions )
					? 'review_handoff_preview_before_future_core_submission'
				: 'select_reviewed_media_alt_caption_items',
			'guardrails'             => array(
				'no_media_metadata_write_in_toolbox',
				'no_toolbox_auto_approval',
					'no_adapter_or_core_submission_from_preview',
					'core_policy_owns_auto_approval',
					'alt_only_auto_execution_candidate_future_only',
				'human_visual_confirmation_required',
				'core_approval_required_before_final_write',
			),
		);
	}

	/**
	 * Normalizes delegated Toolkit content metadata plans for Core from-plan intake.
	 *
	 * @param array<string,mixed> $data Toolkit plan data.
	 * @return array<string,mixed>
	 */
	private function normalize_content_metadata_apply_plan_contract( array $data ): array {
		$authorization = is_array( $data['authorization'] ?? null ) ? $data['authorization'] : array();
		$classification = sanitize_key( (string) ( $authorization['classification'] ?? Operation_Classifier::CORE_PROPOSAL_REQUIRED ) );
		if ( '' === $classification ) {
			$classification = Operation_Classifier::CORE_PROPOSAL_REQUIRED;
		}

		$reasons = $this->sanitize_string_list( $authorization['reasons'] ?? array() );
		if ( empty( $reasons ) ) {
			$reasons = array( 'excerpt_or_taxonomy_mutation', 'core_proposal_required' );
		}

		$required_evidence = $this->sanitize_string_list( $authorization['required_evidence'] ?? array() );
		if ( empty( $required_evidence ) ) {
			$required_evidence = array(
				'target_ability_id',
				'target_input_or_safe_summary',
				'before_after_or_dry_run_evidence',
				'reason_risk_required_scopes',
				'caller_source_metadata',
				'batch_item_details_when_applicable',
			);
		}

		$decision_version = sanitize_text_field(
			(string) (
				$authorization['decision_version']
				?? ( $authorization['policy_version'] ?? 'operation-classification-v1' )
			)
		);
		if ( '' === $decision_version ) {
			$decision_version = 'operation-classification-v1';
		}

		$decision_envelope = is_array( $authorization['decision_envelope'] ?? null ) ? $authorization['decision_envelope'] : array();
		$decision_envelope = array_merge(
			array(
				'decision_version'  => $decision_version,
				'classification'    => $classification,
				'reasons'           => $reasons,
				'required_evidence' => $required_evidence,
			),
			$decision_envelope
		);
		$decision_envelope['decision_version']       = $decision_version;
		$decision_envelope['classification']         = $classification;
		$decision_envelope['reasons']                = $reasons;
		$decision_envelope['required_evidence']      = $required_evidence;
		$decision_envelope['final_write_path']       = 'core_proposal_required';
		$decision_envelope['direct_wordpress_write'] = false;

		$authorization['classification']    = $classification;
		$authorization['requires_proposal'] = true;
		$authorization['requires_approval'] = true;
		$authorization['policy_version']    = $decision_version;
		$authorization['decision_version']  = $decision_version;
		$authorization['reasons']           = $reasons;
		$authorization['required_evidence'] = $required_evidence;
		$authorization['decision_envelope'] = $this->sanitize_payload( $decision_envelope );
		$data['authorization']             = $authorization;
		$data['classification_evidence']   = $authorization;
		$data['direct_wordpress_write']     = false;
		$data['requires_approval']          = true;
		$data['dry_run']                    = true;
		$data['commit_execution']           = false;

		return $data;
	}


	public function build_content_discoverability_brief( array $input ) {
		$source = $this->client->resolve_discoverability_source( $input );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$context           = $this->settings->get_content_context_for_ability();
		$validation        = $this->settings->validate_content_context_for_ability();
		$allowed_fields    = $this->sanitize_string_list( $context['proposal_allowed_fields'] ?? array() );
		$exceptions        = is_array( $context['exceptions'] ?? null ) ? $this->sanitize_payload( $context['exceptions'] ) : array();
		$proposal_template = array();
		$candidates        = array();
		$include_external_search = ! array_key_exists( 'include_external_search', $input ) || ! empty( $input['include_external_search'] );
		$external_search_intent  = sanitize_key( (string) ( $input['external_search_intent'] ?? 'writing_context' ) );
		if ( ! in_array( $external_search_intent, array( 'article_background', 'fact_check', 'news', 'writing_context', 'competitor_research', 'pricing_snapshot', 'product_comparison', 'source_discovery', 'external_links' ), true ) ) {
			$external_search_intent = 'writing_context';
		}
		$external_research = $include_external_search
			? $this->client->cloud_web_search_for_content( sanitize_text_field( (string) ( $source['topic'] ?? $source['title'] ?? '' ) ), $external_search_intent, 3 )
			: $this->client->cloud_web_search_notice();
		$cloud_evidence   = $this->client->cloud_web_search_evidence( $external_research );
		$sections          = array(
			'seo' => array(
				'rules'              => sanitize_textarea_field( (string) ( $context['rules']['seo'] ?? '' ) ),
				'allowed_fields'     => array(),
				'proposal_template'  => array(),
				'candidate_suggestions' => array(),
			),
			'aeo' => array(
				'rules'              => sanitize_textarea_field( (string) ( $context['rules']['aeo'] ?? '' ) ),
				'allow_faq_generation' => ! empty( $context['rules']['allow_faq_generation'] ),
				'allow_answer_summary' => ! empty( $context['rules']['allow_aeo_summary'] ),
				'allowed_fields'     => array(),
				'proposal_template'  => array(),
				'candidate_suggestions' => array(),
			),
			'geo' => array(
				'rules'              => sanitize_textarea_field( (string) ( $context['rules']['geo'] ?? '' ) ),
				'allow_geo_summary'  => ! empty( $context['rules']['allow_geo_summary'] ),
				'allow_structured_data_suggestions' => ! empty( $context['rules']['allow_structured_data_suggestions'] ),
				'allowed_fields'     => array(),
				'proposal_template'  => array(),
				'candidate_suggestions' => array(),
			),
		);

		foreach ( $allowed_fields as $field ) {
			$proposal_template[ $field ] = array(
				'instruction' => $this->client->content_discoverability_field_instruction( $field ),
				'value'       => null,
			);
			$group = $this->client->content_discoverability_field_group( $field );
			$sections[ $group ]['allowed_fields'][] = $field;
			$sections[ $group ]['proposal_template'][ $field ] = $proposal_template[ $field ];

			$candidate = $this->client->content_discoverability_candidate( $field, $source, $context );
			if ( null !== $candidate ) {
				$candidates[ $field ] = $candidate;
				$sections[ $group ]['candidate_suggestions'][ $field ] = $candidate;
			}
		}

		return array(
			'artifact_type'          => 'content_discoverability_brief',
			'composition_role'       => 'seo_aeo_geo_brief',
			'version'                => 1,
			'primary_contract'       => true,
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'context_validation'     => $validation,
			'content_context'        => $context,
			'exceptions'             => $exceptions,
			'special_cases'          => $exceptions,
			'source'                 => $source,
			'external_research'      => $external_research,
			'cloud_evidence'         => $cloud_evidence,
			'seo'                    => $sections['seo'],
			'aeo'                    => $sections['aeo'],
			'geo'                    => $sections['geo'],
			'ai_instructions'        => array(
				'Use the content_context as the site-level rule source.',
				'Use only facts present in the supplied source, public site context, or cited evidence.',
				'Use external_research only as suggestion evidence and preserve source URLs for operator review.',
				'Do not invent customer cases, ranking guarantees, source citations, or unavailable product features.',
				'Return suggestions only for proposal_allowed_fields.',
				'Respect forbidden claims and preserve the requested brand voice.',
				'Apply exceptions and special_cases before generating FAQ, HowTo, schema, or confident product claims.',
				'Final WordPress writes must go through Core proposal approval.',
			),
			'proposal_allowed_fields' => $allowed_fields,
			'proposal_template'      => $proposal_template,
			'candidate_suggestions'  => $candidates,
			'handoff'                => array(
				'brief_ability_id'       => 'npcink-toolbox/build-content-discoverability-brief',
				'context_ability_id'     => 'npcink-toolbox/get-content-discoverability-context',
				'validation_ability_id'  => 'npcink-toolbox/validate-content-discoverability-context',
				'final_writes'           => 'core_proposal_required',
				'direct_wordpress_write' => false,
			),
		);
	}


	public function build_ai_article_writing_pack( array $input ) {
		$brief = $this->build_content_discoverability_brief( $input );
		if ( is_wp_error( $brief ) ) {
			return $brief;
		}

		$brief              = is_array( $brief ) ? $brief : array();
		$source             = is_array( $brief['source'] ?? null ) ? $brief['source'] : array();
		$context            = is_array( $brief['content_context'] ?? null ) ? $brief['content_context'] : array();
		$validation         = is_array( $brief['context_validation'] ?? null ) ? $brief['context_validation'] : array();
		$rules              = is_array( $context['rules'] ?? null ) ? $context['rules'] : array();
		$keywords           = is_array( $context['keywords'] ?? null ) ? $context['keywords'] : array();
		$claims             = is_array( $context['claims'] ?? null ) ? $context['claims'] : array();
		$topic              = sanitize_text_field( (string) ( $source['topic'] ?? ( $input['topic'] ?? '' ) ) );
		$title              = sanitize_text_field( (string) ( $source['title'] ?? ( $input['title'] ?? $topic ) ) );
		$language           = sanitize_text_field( (string) ( $input['language'] ?? 'zh-CN' ) );
		$article_type       = sanitize_key( (string) ( $input['article_type'] ?? 'practical_guide' ) );
		$target_word_count  = absint( $input['target_word_count'] ?? 1200 );
		$target_word_count  = max( 500, min( 5000, $target_word_count ) );
		$context_status     = sanitize_key( (string) ( $validation['status'] ?? 'needs_attention' ) );
		$ready_for_writing  = in_array( $context_status, array( 'ready', 'ready_with_warnings' ), true );
		$proposal_fields    = $this->sanitize_string_list( $brief['proposal_allowed_fields'] ?? array() );
		$primary_keywords   = $this->sanitize_string_list( $keywords['primary'] ?? array() );
		$long_tail_keywords = $this->sanitize_string_list( $keywords['long_tail'] ?? array() );
		$entity_keywords    = $this->sanitize_string_list( $keywords['entities'] ?? array() );
		$forbidden_claims   = $this->sanitize_string_list( $claims['forbidden'] ?? array() );
		$external_research  = is_array( $brief['external_research'] ?? null ) ? $brief['external_research'] : array();
		$cloud_evidence     = is_array( $brief['cloud_evidence'] ?? null ) ? $brief['cloud_evidence'] : $this->client->cloud_web_search_evidence( $external_research );

		return array(
			'artifact_type'          => 'ai_article_writing_pack',
			'composition_role'       => 'ai_article_writing_pack',
			'version'                => 1,
			'primary_contract'       => false,
			'contract_role'          => 'openclaw_natural_language_fallback',
			'write_posture'          => 'suggestion_only',
			'final_write_path'       => 'core_proposal_required',
			'direct_wordpress_write' => false,
			'provider_execution'     => 'none',
			'ready_for_writing'      => $ready_for_writing,
			'context_status'         => $context_status,
			'source'                 => $source,
			'topic'                  => $topic,
			'title'                  => $title,
			'language'               => $language,
			'article_type'           => $article_type,
			'target_word_count'      => $target_word_count,
			'content_context'        => $context,
			'context_validation'     => $validation,
			'discoverability_brief'  => $brief,
			'external_research'      => $external_research,
			'cloud_evidence'         => $cloud_evidence,
			'exceptions'             => is_array( $brief['exceptions'] ?? null ) ? $brief['exceptions'] : array(),
			'special_cases'          => is_array( $brief['special_cases'] ?? null ) ? $brief['special_cases'] : array(),
			'article_prompt_pack'    => array(
				'user_intent'      => sanitize_textarea_field( (string) ( $input['user_intent'] ?? 'Write one article from the supplied topic and site rules.' ) ),
				'writing_goal'     => sprintf(
					'Write one %1$s article in %2$s about: %3$s.',
					$article_type,
					$language,
					'' !== $topic ? $topic : $title
				),
				'style_rules'      => array_filter(
					array(
						(string) ( $context['brand_voice'] ?? '' ),
						(string) ( $rules['seo'] ?? '' ),
						(string) ( $rules['aeo'] ?? '' ),
						(string) ( $rules['geo'] ?? '' ),
					)
				),
				'keyword_targets'  => array(
					'primary'   => $primary_keywords,
					'long_tail' => $long_tail_keywords,
					'entities'  => $entity_keywords,
				),
				'proposal_fields'  => $proposal_fields,
				'forbidden_claims' => $forbidden_claims,
			),
			'suggested_article_structure' => $this->article_writing_pack_structure( $rules ),
			'ai_instructions'      => array(
				'Use this pack as the local site-context source before writing.',
				'If ready_for_writing is false, stop and ask the operator to complete Toolbox Content Context.',
				'Write from the supplied source and topic; do not invent product facts, customer cases, rankings, citations, or unavailable features.',
				'Respect forbidden claims, brand voice, SEO rules, AEO rules, and GEO rules.',
				'Return article draft text and proposal-ready SEO/AEO/GEO suggestions only.',
				'Do not write WordPress data. Final WordPress writes must go through Core proposal approval and commit preflight.',
			),
			'handoff'              => array(
				'pack_ability_id'       => 'npcink-toolbox/build-ai-article-writing-pack',
				'brief_ability_id'      => 'npcink-toolbox/build-content-discoverability-brief',
				'write_plan_ability_id' => 'npcink-toolbox/build-article-write-plan',
				'final_writes'          => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'next_steps'            => array(
					'Use the pack to draft one article and SEO/AEO/GEO suggestions.',
					'After human review, convert the reviewed draft with build-article-write-plan.',
					'Send write-like outcomes through Core proposal, approval, and commit preflight.',
				),
			),
		);
	}


	public function build_media_brief( string $post_context, array $options = array() ) {
		$decoded_context = json_decode( $post_context, true );
		if ( ! is_array( $decoded_context ) ) {
			$decoded_context = array();
		}
		$refresh_variant = sanitize_text_field( (string) ( $options['refresh_variant'] ?? '' ) );
		$image_mode      = sanitize_key( (string) ( $options['image_mode'] ?? 'featured_image' ) );
		if ( ! in_array( $image_mode, array( 'featured_image', 'paragraph_image', 'inline_image', 'setting_image' ), true ) ) {
			$image_mode = 'featured_image';
		}
		$visual_context = array(
			'post_id'         => absint( $decoded_context['id'] ?? 0 ),
			'title'           => sanitize_text_field( (string) ( $decoded_context['title'] ?? '' ) ),
			'excerpt'         => sanitize_textarea_field( (string) ( $decoded_context['excerpt'] ?? '' ) ),
			'content_summary' => sanitize_textarea_field( (string) ( $decoded_context['content'] ?? '' ) ),
			'image_use'       => $image_mode,
			'refresh_variant' => $refresh_variant,
			'query_intent'    => array(
				'rewrite_abstract_terms'       => true,
				'prefer_concrete_visual_scene' => true,
				'return_alternate_queries'     => true,
				'direction_count'              => 4,
				'prompt_candidate_count'       => 4,
			),
		);
		return $this->client->image_candidates(
			$this->post_context_to_image_query( $post_context ),
			array(
				'per_page'                     => 8,
				'runtime_data_classification' => 'pii',
				'image_mode'                   => $image_mode,
				'refresh_variant'              => $refresh_variant,
				'visual_context'               => $visual_context,
			)
		);
	}


	public function build_media_derivative_handoff( array $input ) {
		$attachment_id = absint( $input['attachment_id'] ?? 0 );
		if ( $attachment_id <= 0 ) {
			return new WP_Error(
				'npcink_toolbox_missing_attachment_id',
				__( 'An attachment_id is required to build a media derivative handoff.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		$overrides = array( 'attachment_id' => $attachment_id );
		if ( '' !== trim( (string) ( $input['target_format'] ?? '' ) ) ) {
			$overrides['target_format'] = sanitize_key( (string) $input['target_format'] );
		}
		if ( '' !== trim( (string) ( $input['max_width'] ?? '' ) ) ) {
			$overrides['max_width'] = absint( $input['max_width'] );
		}
		if ( '' !== trim( (string) ( $input['quality'] ?? '' ) ) ) {
			$overrides['quality'] = absint( $input['quality'] );
		}
		$overrides = array_merge( $overrides, $this->media_derivative_crop_overrides( $input ) );
		$overrides = array_merge( $overrides, $this->media_derivative_watermark_overrides( $input ) );

		$toolbox_policy = $this->settings->media_optimization_policy_summary();
		$ability_input  = $this->settings->build_media_derivative_ability_input( $overrides );

		$warnings = array();
		$watermark_mode = sanitize_key( (string) ( $input['watermark_mode'] ?? $input['watermark_type'] ?? 'core' ) );
		if ( ! empty( $toolbox_policy['watermark_enabled'] ) && empty( $toolbox_policy['watermark_configured'] ) && ! in_array( $watermark_mode, array( 'off', 'text' ), true ) ) {
			$warnings[] = __( 'Toolbox watermark policy is enabled but no logo attachment is configured.', 'npcink-workflow-toolbox' );
		}

		return array(
			'artifact_type'          => 'media_derivative_handoff',
			'composition_role'       => 'media_derivative_operator_handoff',
			'version'                => 1,
			'workflow_projection'    => array(
				'definition_owner'            => 'npcink-abilities-toolkit',
				'projection_role'             => 'fixed_button',
				'recipe_id'                    => 'npcink-abilities-toolkit/recipes/media-optimization',
				'recipe_alias'                 => 'media_optimization_v1',
				'contract_version'             => 'v1',
				'entrypoint_ability_id'        => 'npcink-abilities-toolkit/build-media-optimization-plan',
				'required_scope'               => 'media.read',
				'required_inputs'              => array( 'attachment_id', 'media_details_input', 'derivative_artifact' ),
				'handoff_kind'                 => 'approval_request',
				'failure_policy'               => 'fail_closed',
				'host_governed_write_boundary' => true,
				'canonical_definition_storage' => false,
			),
			'write_posture'          => 'core_proposal_handoff',
			'direct_wordpress_write' => false,
			'provider'               => 'toolbox',
			'attachment_id'          => $attachment_id,
			'toolbox_policy_available' => true,
			'toolbox_policy'         => $this->sanitize_payload( $toolbox_policy ),
			'ability_id'             => 'npcink-abilities-toolkit/build-media-derivative-cloud-request',
			'ability_input'          => $this->sanitize_payload( $ability_input ),
			'optimization_plan_ability_id' => 'npcink-abilities-toolkit/build-media-optimization-plan',
			'preferred_core_route'   => '/wp-json/npcink-openclaw-adapter/v1/proposals/from-plan',
			'required_reviewed_input' => array( 'media_details_input', 'derivative_artifact' ),
			'warnings'               => $warnings,
			'handoff'                => array(
				'final_write_path'       => 'core_proposal_required',
				'direct_wordpress_write' => false,
				'default_user_intent'    => 'optimize_this_media_item',
				'do_not_split_user_intent' => true,
				'legacy_derivative_only' => 'lower_level_review_only',
				'next_steps'             => array(
					'Run the local media derivative request ability with ability_input.',
					'Use Cloud Addon only as a verified transport when available.',
					'Add reviewed media_details_input before Core proposal submission.',
					'Submit Adapter from_plan_request to /proposals/from-plan so Core creates one media optimization proposal.',
					'If Core lacks npcink-abilities-toolkit/build-media-optimization-plan, update Core and Abilities instead of splitting the same user intent into two proposals.',
				),
			),
		);
	}


	private function media_derivative_watermark_overrides( array $input ): array {
		$mode = sanitize_key( (string) ( $input['watermark_mode'] ?? $input['watermark_type'] ?? 'core' ) );
		if ( 'off' === $mode ) {
			return array( 'watermark_enabled' => false );
		}
		if ( 'override' === $mode ) {
			$mode = 'image';
		}
		if ( ! in_array( $mode, array( 'text', 'image' ), true ) ) {
			return array();
		}

		$position = sanitize_key( (string) ( $input['watermark_position'] ?? 'bottom_right' ) );
		if ( ! in_array( $position, array( 'top_left', 'top_right', 'center', 'bottom_left', 'bottom_right' ), true ) ) {
			$position = 'bottom_right';
		}
		$opacity = '' !== trim( (string) ( $input['watermark_opacity'] ?? '' ) )
			? absint( $input['watermark_opacity'] )
			: 80;
		$margin = max( 0, min( 1000, absint( $input['watermark_margin'] ?? 24 ) ) );

		if ( 'text' === $mode ) {
			$text = trim( sanitize_text_field( (string) ( $input['watermark_text'] ?? 'AI' ) ) );
			if ( '' === $text ) {
				$text = 'AI';
			}
			$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 64 ) : substr( $text, 0, 64 );

			return array(
				'watermark_enabled' => true,
				'watermark'         => array(
					'type'       => 'text',
					'text'       => $text,
					'position'   => $position,
					'opacity'    => round( max( 0, min( 100, $opacity ) ) / 100, 3 ),
					'font_size'  => max( 8, min( 256, absint( $input['watermark_font_size'] ?? 48 ) ) ),
					'color'      => $this->sanitize_media_derivative_watermark_color( $input['watermark_color'] ?? '#FFFFFF', '#FFFFFF' ),
					'background' => $this->sanitize_media_derivative_watermark_color( $input['watermark_background'] ?? 'rgba(0,0,0,0.35)', 'rgba(0,0,0,0.35)' ),
					'margin_px'  => $margin,
				),
			);
		}

		return array(
			'watermark_enabled' => true,
			'watermark'         => array(
				'type'          => 'image',
				'position'      => $position,
				'opacity'       => round( max( 0, min( 100, $opacity ) ) / 100, 3 ),
				'scale_percent' => max( 1, min( 100, absint( $input['watermark_scale'] ?? 20 ) ) ),
				'margin_px'     => $margin,
			),
		);
	}


	private function media_derivative_crop_overrides( array $input ): array {
		$aspect_ratio = trim( sanitize_text_field( (string) ( $input['crop_aspect_ratio'] ?? '' ) ) );
		if ( '' === $aspect_ratio ) {
			return array();
		}
		if ( 1 !== preg_match( '/^([1-9][0-9]{0,2}):([1-9][0-9]{0,2})$/', $aspect_ratio, $matches ) || (int) $matches[1] > 100 || (int) $matches[2] > 100 ) {
			$aspect_ratio = '16:9';
		}

		$position = sanitize_key( (string) ( $input['crop_position'] ?? 'center' ) );
		if ( ! in_array( $position, array( 'top_left', 'top', 'top_right', 'left', 'center', 'right', 'bottom_left', 'bottom', 'bottom_right' ), true ) ) {
			$position = 'center';
		}

		return array(
			'crop' => array(
				'type'         => 'aspect_ratio',
				'aspect_ratio' => $aspect_ratio,
				'position'     => $position,
			),
		);
	}


	private function sanitize_media_derivative_watermark_color( $value, string $default ): string {
		$color = trim( sanitize_text_field( (string) $value ) );
		if ( 'transparent' === strtolower( $color ) ) {
			return 'transparent';
		}
		if ( 1 === preg_match( '/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $color ) ) {
			return strtoupper( $color );
		}
		if ( 1 === preg_match( '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(0|1|0?\.\d+))?\s*\)$/', $color, $matches ) ) {
			$r     = max( 0, min( 255, (int) $matches[1] ) );
			$g     = max( 0, min( 255, (int) $matches[2] ) );
			$b     = max( 0, min( 255, (int) $matches[3] ) );
			$alpha = isset( $matches[4] ) && '' !== $matches[4] ? max( 0, min( 1, (float) $matches[4] ) ) : null;

			return null === $alpha
				? sprintf( 'rgb(%d,%d,%d)', $r, $g, $b )
				: sprintf( 'rgba(%d,%d,%d,%s)', $r, $g, $b, rtrim( rtrim( sprintf( '%.3F', $alpha ), '0' ), '.' ) );
		}

		return $default;
	}


	private function article_writing_pack_structure( array $rules ): array {
		$structure = array(
			array(
				'section' => 'title',
				'purpose' => 'Use a clear article title aligned with the primary keyword and source topic.',
			),
			array(
				'section' => 'direct_answer',
				'purpose' => 'Open with a concise answer or definition that an answer engine can extract.',
			),
			array(
				'section' => 'context',
				'purpose' => 'Explain why the topic matters to the target audience using only supported facts.',
			),
			array(
				'section' => 'main_body',
				'purpose' => 'Use practical headings, steps, examples, comparisons, or checklists where the source supports them.',
			),
			array(
				'section' => 'geo_summary',
				'purpose' => 'Include a fact-dense summary suitable for generated search citation.',
			),
			array(
				'section' => 'conclusion',
				'purpose' => 'Close with a practical next step without claiming guaranteed ranking or outcomes.',
			),
		);

		if ( ! empty( $rules['allow_faq_generation'] ) ) {
			$structure[] = array(
				'section' => 'faq',
				'purpose' => 'Add 3 to 5 grounded FAQ items only when the brief allows FAQ suggestions.',
			);
		}

		return $structure;
	}


	private function resolve_article_media_candidate( array $article, string $title, string $topic, bool $search_images, string $image_provider ) {
		$candidate = array();
		foreach ( array( 'image_candidate', 'featured_image', 'featured_image_candidate' ) as $key ) {
			if ( is_array( $article[ $key ] ?? null ) ) {
				$candidate = $article[ $key ];
				break;
			}
		}

		if ( empty( $candidate ) && ! empty( $article['image_url'] ) ) {
			$candidate = array(
				'url'             => esc_url_raw( (string) $article['image_url'] ),
				'regular_url'     => esc_url_raw( (string) $article['image_url'] ),
				'description'     => sanitize_textarea_field( (string) ( $article['image_alt'] ?? $title ) ),
				'alt_description' => sanitize_textarea_field( (string) ( $article['image_alt'] ?? $title ) ),
				'provider'        => sanitize_key( (string) ( $article['image_provider'] ?? 'external' ) ),
				'source_url'      => esc_url_raw( (string) ( $article['image_source_url'] ?? '' ) ),
				'photographer'    => sanitize_text_field( (string) ( $article['photographer_name'] ?? '' ) ),
				'attribution'     => sanitize_textarea_field( (string) ( $article['attribution_text'] ?? '' ) ),
			);
		}

		if ( empty( $candidate ) && $search_images ) {
			$query  = trim( sanitize_text_field( (string) ( $article['image_query'] ?? $title . ' ' . $topic ) ) );
			$result = $this->client->image_candidates(
				$query,
				array(
					'provider' => $image_provider,
					'per_page' => 1,
				)
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$images = is_array( $result['images'] ?? null ) ? array_values( $result['images'] ) : array();
			if ( empty( $images ) || ! is_array( $images[0] ?? null ) ) {
				return new WP_Error(
					'npcink_toolbox_article_media_candidate_missing',
					__( 'Image-source search did not return a usable candidate for an article media batch item.', 'npcink-workflow-toolbox' ),
					array( 'status' => 502 )
				);
			}
			$candidate = $images[0];
		}

		if ( empty( $candidate ) ) {
			return new WP_Error(
				'npcink_toolbox_article_media_candidate_required',
				__( 'Every article media batch item requires image_candidate, featured_image, image_url, or search_images=true.', 'npcink-workflow-toolbox' ),
				array( 'status' => 400 )
			);
		}

		return $this->sanitize_payload( $candidate );
	}


	private function registered_ability_callable( string $ability_id ): bool {
		if ( ! function_exists( 'npcink_abilities_toolkit_get_registered' ) ) {
			return false;
		}

		$registered = npcink_abilities_toolkit_get_registered();
		if ( ! is_array( $registered ) ) {
			return false;
		}

		$definition = is_array( $registered[ $ability_id ] ?? null ) ? $registered[ $ability_id ] : array();

		return is_callable( $definition['execute_callback'] ?? null );
	}


	private function article_audio_normalized_source_text( string $content ): string {
		$content = trim( wp_strip_all_tags( $content ) );
		$content = preg_replace( '/\s+/u', ' ', $content );

		return is_string( $content ) ? trim( $content ) : '';
	}


	private function article_audio_content_hash( string $content ): string {
		$content = $this->article_audio_normalized_source_text( $content );

		return '' === $content ? '' : hash( 'sha256', $content );
	}


	private function article_audio_word_count( string $content ): int {
		$content = $this->article_audio_normalized_source_text( $content );
		if ( '' === $content ) {
			return 0;
		}

		$word_count = str_word_count( $content );
		if ( $word_count > 0 ) {
			return $word_count;
		}

		return function_exists( 'mb_strlen' ) ? mb_strlen( $content, 'UTF-8' ) : strlen( $content );
	}


	private function post_context_to_image_query( string $post_context ): string {
		$decoded = json_decode( $post_context, true );
		if ( is_array( $decoded ) ) {
			$title = trim( sanitize_text_field( (string) ( $decoded['title'] ?? '' ) ) );
			if ( '' !== $title ) {
				return $title;
			}

			$excerpt = trim( sanitize_textarea_field( (string) ( $decoded['excerpt'] ?? '' ) ) );
			if ( '' !== $excerpt ) {
				return wp_trim_words( $excerpt, 12, '' );
			}
		}

		return wp_trim_words( wp_strip_all_tags( $post_context ), 12, '' );
	}
}
