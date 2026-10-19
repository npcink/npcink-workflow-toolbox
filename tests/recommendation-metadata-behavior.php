<?php
/** Frozen baseline projection and feedback validation. @package Npcink_Toolbox */
define( 'ABSPATH', __DIR__ . '/wp-stub/' );
class WP_Error {
	public function __construct( $code, $message, $data ) {}
}
require_once dirname( __DIR__ ) . '/includes/Recommendation_Metadata.php';
use Npcink_Toolbox\Recommendation_Metadata;
$check = static function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
$items = array( array( 'post_id' => 9, 'score' => 0.82 ), array( 'post_id' => 4, 'score' => 0.6 ), array( 'post_id' => 3, 'score' => 0.3 ) );
$section = Recommendation_Metadata::project_section( array( 'status' => 'ready', 'results' => $items, 'rerank' => array( 'status' => 'disabled' ) ), 'related_content' );
$check( array_column( $section['results'], 'post_id' ) === array( 9, 4, 3 ), 'Metadata preserves ranking order.' );
$check( array_column( $section['results'], 'score' ) === array_column( $items, 'score' ), 'Metadata preserves scores.' );
$check( array_column( $section['results'], 'candidate_rank' ) === array( 1, 2, 3 ), 'Candidate rank is one-based.' );
$check( array_column( $section['results'], 'score_bucket' ) === array( 'high', 'medium', 'low' ), 'Only bounded score buckets enter telemetry.' );
$check( 'related_content.v1' === $section['ranking_version'] && false === $section['rerank_applied'], 'Related baseline records explicit disabled rerank.' );
$internal = Recommendation_Metadata::project_section( array( 'retrieval_status' => 'cloud_vector_evidence', 'recommendation_candidates' => array( array( 'quality_score' => 90 ) ), 'source_knowledge' => array( 'rerank' => array( 'status' => 'succeeded' ) ) ), 'internal_links' );
$check( 'internal_links.v1' === $internal['ranking_version'] && 'cloud_vector_rerank' === $internal['retrieval_source'], 'Internal baseline is separate and retains rerank truth.' );
$unknown = Recommendation_Metadata::project_section( array( 'status' => 'ready', 'results' => $items ), 'related_content' );
$check( ! array_key_exists( 'rerank_applied', $unknown ), 'Missing rerank evidence stays unknown.' );
$nullable_score = Recommendation_Metadata::project_section( array( 'results' => array( array( 'quality_score' => null, 'score' => 0.82 ) ) ), 'related_content' );
$check( 'high' === $nullable_score['results'][0]['score_bucket'], 'Missing quality score falls back to the normalized vector score.' );
$check( array() === Recommendation_Metadata::feedback_fields( array() ), 'Legacy feedback is unchanged.' );
$null_position = Recommendation_Metadata::feedback_fields( array( 'ranking_version' => 'internal_links.v1', 'candidate_rank' => null ) );
$check( is_array( $null_position ) && array_key_exists( 'candidate_rank', $null_position ) && null === $null_position['candidate_rank'], 'A null position means no singular candidate observation.' );
foreach ( array( 'ranking_version', 'score_bucket', 'retrieval_source', 'rerank_applied' ) as $nonnull_field ) {
	$check( Recommendation_Metadata::feedback_fields( array_merge( array( 'ranking_version' => 'internal_links.v1' ), array( $nonnull_field => null ) ) ) instanceof WP_Error, 'Unknown enum and boolean observations are omitted rather than null.' );
}
foreach ( array( array( 'ranking_version' => 'private article' ), array( 'ranking_version' => 'internal_links.v1', 'candidate_rank' => 0 ), array( 'ranking_version' => 'internal_links.v1', 'candidate_rank' => true ), array( 'ranking_version' => 'internal_links.v1', 'score_bucket' => 'https://example.test' ), array( 'ranking_version' => 'internal_links.v1', 'rerank_applied' => 'false' ) ) as $invalid ) {
	$check( Recommendation_Metadata::feedback_fields( $invalid ) instanceof WP_Error, 'Unbounded or mistyped metadata is rejected.' );
}
foreach ( array( 'internal_links', 'related_content' ) as $family ) {
 $v2 = Recommendation_Metadata::project_section( array( 'status' => 'ready', 'source_knowledge' => array( 'status' => 'ready', 'result_grouping' => array( 'ranking_version' => $family . '.v2' ) ) ), $family );
 $check( $v2['ranking_version'] === $family . '.v2' && $v2['retrieval_source'] === 'no_evidence', 'Healthy v2 empty results retain their version and no-evidence source.' );
 $check( is_array( Recommendation_Metadata::feedback_fields( array( 'ranking_version' => $family . '.v2', 'retrieval_source' => 'no_evidence' ) ) ), 'v2 metadata-only empty impressions are accepted.' );
 $mismatch = Recommendation_Metadata::project_section( array( 'result_grouping' => array( 'ranking_version' => 'other.v2' ) ), $family );
 $check( ! isset( $mismatch['ranking_version'] ), 'Unknown or cross-family versions never masquerade as v2.' );
}
echo "Recommendation metadata behavior checks passed.\n";
