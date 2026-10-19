<?php
/**
 * Bounded observation fields for the frozen recommendation baselines.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Recommendation_Metadata {

	public const VERSIONS          = array(
		'internal_links'  => 'internal_links.v1',
		'related_content' => 'related_content.v1',
	);
	public const SUPPORTED_VERSIONS = array( 'internal_links.v1', 'related_content.v1', 'internal_links.v2', 'related_content.v2' );
	public const SCORE_BUCKETS     = array( 'low', 'medium', 'high', 'unknown' );
	public const RETRIEVAL_SOURCES = array( 'cloud_vector', 'cloud_vector_rerank', 'local_fallback', 'no_evidence', 'unavailable', 'unknown' );

	public static function project_section( array $section, string $intent ): array {
		if ( ! in_array( $intent, array( 'internal_links', 'related_content' ), true ) ) {
			return $section;
		}
		$knowledge        = is_array( $section['source_knowledge'] ?? null ) ? $section['source_knowledge'] : $section;
		$rerank           = is_array( $knowledge['rerank'] ?? null ) ? $knowledge['rerank'] : array();
		$rerank_applied   = 'succeeded' === (string) ( $rerank['status'] ?? '' );
		$retrieval_status = (string) ( $section['retrieval_status'] ?? $knowledge['status'] ?? '' );
		$source           = (string) ( $section['candidate_source'] ?? '' );
		$items_key        = 'internal_links' === $intent ? 'recommendation_candidates' : 'results';
		$items            = is_array( $section[ $items_key ] ?? null ) ? $section[ $items_key ] : array();
		$retrieval_source = 'unknown';
		if ( in_array( $retrieval_status, array( 'cloud_unavailable', 'error', 'failed' ), true ) ) {
			$retrieval_source = 'unavailable';
		} elseif ( 'local_fallback' === $source && ! empty( $items ) ) {
			$retrieval_source = 'local_fallback';
		} elseif ( in_array( $retrieval_status, array( 'ready', 'cloud_vector_evidence' ), true ) && ! empty( $items ) ) {
			$retrieval_source = $rerank_applied ? 'cloud_vector_rerank' : 'cloud_vector';
		} elseif ( in_array( $retrieval_status, array( 'ready', 'no_cloud_evidence' ), true ) ) {
			$retrieval_source = 'no_evidence';
		}
		// Missing version means the legacy runtime; unknown/cross-family versions stay unknown.
		$cloud_version = $knowledge['result_grouping']['ranking_version'] ?? null;
		$version = null === $cloud_version ? self::VERSIONS[ $intent ] : $cloud_version;
		if ( in_array( $version, array( $intent . '.v1', $intent . '.v2' ), true ) ) {
			$section['ranking_version'] = $version;
		} else {
			unset( $section['ranking_version'] );
		}
		$section['retrieval_source'] = $retrieval_source;
		// Missing rerank evidence stays unknown instead of becoming false.
		if ( in_array( $rerank['status'] ?? '', array( 'succeeded', 'disabled', 'skipped', 'failed' ), true ) ) {
			$section['rerank_applied'] = $rerank_applied;
		}
		foreach ( $items as $index => &$item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( isset( $section['ranking_version'] ) ) {
				$item['ranking_version'] = $section['ranking_version'];
			} else {
				unset( $item['ranking_version'] );
			}
			$item['candidate_rank']  = $index + 1;
			$has_quality_score       = is_numeric( $item['quality_score'] ?? null );
			$score                   = $has_quality_score ? $item['quality_score'] : ( $item['score'] ?? null );
			$score                   = is_numeric( $score ) ? (float) $score : null;
			if ( null !== $score && ! $has_quality_score ) {
				$score *= 100;
			}
			$item['score_bucket'] = null === $score ? 'unknown' : ( $score >= 80 ? 'high' : ( $score >= 50 ? 'medium' : 'low' ) );
		}
		unset( $item );
		$section[ $items_key ] = $items;
		return $section;
	}

	/** Validates optional fields; legacy events remain unchanged. */
	public static function feedback_fields( array $input ) {
		$fields = array_intersect_key( $input, array_flip( array( 'ranking_version', 'candidate_rank', 'score_bucket', 'retrieval_source', 'rerank_applied' ) ) );
		if ( empty( $fields ) ) {
			return array();
		}
		$valid = in_array( $fields['ranking_version'] ?? null, self::SUPPORTED_VERSIONS, true );
		foreach ( array(
			'score_bucket'     => self::SCORE_BUCKETS,
			'retrieval_source' => self::RETRIEVAL_SOURCES,
		) as $key => $allowed ) {
			if ( array_key_exists( $key, $fields ) && ! in_array( $fields[ $key ], $allowed, true ) ) {
				$valid = false;
			}
		}
		if ( isset( $fields['candidate_rank'] ) && ( ! is_int( $fields['candidate_rank'] ) || $fields['candidate_rank'] < 1 || $fields['candidate_rank'] > 20 ) ) {
			$valid = false;
		}
		if ( array_key_exists( 'rerank_applied', $fields ) && ! is_bool( $fields['rerank_applied'] ) ) {
			$valid = false;
		}
		return $valid ? $fields : new \WP_Error( 'npcink_toolbox_recommendation_metadata_invalid', 'Invalid recommendation metadata.', array( 'status' => 400 ) );
	}
}
