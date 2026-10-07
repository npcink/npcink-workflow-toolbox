<?php
/**
 * Site Check (site ops) render cluster for the Toolbox admin page.
 *
 * Extracted from Admin_Page under the Provider Split Refactor Standard v1:
 * unchanged method bodies, protected family API, private internals. Internal
 * plumbing behind the admin page facade, not an extension point.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

abstract class Admin_Page_Site_Ops_Panel {

	/** Stable Toolbox menu slug shared by site-check URL builders. */
	protected const MENU_SLUG = 'npcink-toolbox';

	/** Shared settings dependency injected by the admin page facade. */
	protected Settings $settings;

	protected function query_text_param( string $key ): string {
		$value = filter_input( INPUT_GET, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	protected function site_ops_insights_preview_url(): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'                      => self::MENU_SLUG,
					'toolbox_tab'               => 'operations-insights',
					'site_check_tab'            => 'current-check',
					'site_ops_insights_preview' => '1',
				),
				admin_url( 'admin.php' )
			),
			'npcink_toolbox_site_ops_insights_preview'
		);
	}

	protected function site_ops_cloud_analysis_url(): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'                      => self::MENU_SLUG,
					'toolbox_tab'               => 'operations-insights',
					'site_check_tab'            => 'current-check',
					'site_ops_insights_preview' => '1',
					'site_ops_cloud_analysis'   => '1',
				),
				admin_url( 'admin.php' )
			),
			'npcink_toolbox_site_ops_insights_preview'
		);
	}

	/**
	 * @param array<string,mixed> $content_context Content context.
	 * @return array<string,mixed>|null
	 */
	protected function site_ops_insights_preview_from_request( array $content_context, bool $cloud_ready ): ?array {
		$requested = $this->query_text_param( 'site_ops_insights_preview' );
		if ( '1' !== $requested ) {
			return null;
		}

		$nonce = $this->query_text_param( '_wpnonce' );
		if ( ! wp_verify_nonce( $nonce, 'npcink_toolbox_site_ops_insights_preview' ) ) {
			return array(
				'error' => __( 'The Site Check preview link expired. Reload the page and try again.', 'npcink-workflow-toolbox' ),
			);
		}

		try {
			$collector                  = new Site_Ops_Snapshot_Collector();
			$builder                    = new Site_Ops_Insight_Builder();
			$request_builder            = new Site_Ops_Cloud_Request_Builder();
			$snapshot                   = $collector->collect();
			$internal_link_graph_health = $this->site_ops_internal_link_graph_health();
			$runtime_context            = array(
				'content_context_ready'      => $this->content_context_ready( $content_context ),
				'cloud_ready'                => $cloud_ready,
				'internal_link_graph_health' => $internal_link_graph_health,
			);
			$pack                       = $builder->build(
				$snapshot,
				$runtime_context
			);
			$cloud_request              = $request_builder->build(
				$snapshot,
				$pack,
				$runtime_context
			);
			$cloud_analysis             = null;
			$cloud_requested            = $this->query_text_param( 'site_ops_cloud_analysis' );
			if ( '1' === $cloud_requested ) {
				if ( ! $cloud_ready ) {
					$cloud_analysis = new \WP_Error(
						'npcink_toolbox_site_ops_cloud_not_ready',
						__( 'Connect or verify Npcink Cloud before running Cloud Site Check detail.', 'npcink-workflow-toolbox' ),
						array( 'status' => 503 )
					);
				} else {
					$client         = new Provider_Client( $this->settings );
					$cloud_analysis = $client->run_site_ops_cloud_analysis( $cloud_request );
				}
			}

			return array(
				'snapshot'       => $snapshot,
				'pack'           => $pack,
				'cloud_request'  => $cloud_request,
				'cloud_analysis' => $cloud_analysis,
			);
		} catch ( \Throwable $throwable ) {
			return array(
				'error' => __( 'Could not build the local Site Check preview.', 'npcink-workflow-toolbox' ),
			);
		}
	}

	/**
	 * Calls the existing Toolkit internal-link graph read ability during an
	 * explicit Site Check only. A missing or failed Toolkit remains non-fatal.
	 *
	 * @return array<string,mixed>
	 */
	private function site_ops_internal_link_graph_health(): array {
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

	/**
	 * @param array<int,mixed>         $findings Findings.
	 * @param array<string,mixed>      $summary Summary payload.
	 * @param array<string,mixed>|null $cloud_analysis Cloud analysis payload.
	 */
	protected function render_site_ops_operator_brief( array $findings, array $summary, ?array $cloud_analysis, bool $cloud_ready ): void {
		$queue = array();
		foreach ( $findings as $finding ) {
			if ( is_array( $finding ) ) {
				$queue[] = $finding;
			}
			if ( count( $queue ) >= 3 ) {
				break;
			}
		}
		$deferred = array();
		foreach ( array_slice( $findings, 3 ) as $finding ) {
			if ( is_array( $finding ) ) {
				$deferred[] = $finding;
			}
			if ( count( $deferred ) >= 2 ) {
				break;
			}
		}
		$review_count             = $this->count_site_ops_findings_by_boundary( $findings, 'core_handoff_candidate' );
		$manual_count             = $this->count_site_ops_findings_by_boundary( $findings, 'manual_review_only' );
		$cloud_result             = is_array( $cloud_analysis['result'] ?? null ) ? $cloud_analysis['result'] : array();
		$executive_summary        = is_array( $cloud_result['executive_summary'] ?? null ) ? $cloud_result['executive_summary'] : array();
		$cloud_headline           = trim( (string) ( $executive_summary['headline'] ?? '' ) );
		$cloud_summary            = trim( (string) ( $executive_summary['summary'] ?? '' ) );
		$cloud_priority_queue     = is_array( $cloud_result['priority_queue'] ?? null ) ? array_slice( $cloud_result['priority_queue'], 0, 3 ) : array();
		$semantic_ranked_findings = is_array( $cloud_result['semantic_ranked_findings'] ?? null ) ? array_slice( $cloud_result['semantic_ranked_findings'], 0, 3 ) : array();
		$cloud_next_actions       = is_array( $cloud_result['operator_next_actions'] ?? null ) ? array_slice( $cloud_result['operator_next_actions'], 0, 3 ) : array();
		$analysis_closure         = is_array( $cloud_result['analysis_closure'] ?? null ) ? $cloud_result['analysis_closure'] : array();
		$confidence               = is_array( $cloud_result['confidence'] ?? null ) ? $cloud_result['confidence'] : array();
		$cloud_has_detail         = null !== $cloud_analysis;
		$cloud_queue              = array();
		foreach ( array_merge( $cloud_priority_queue, $semantic_ranked_findings ) as $finding ) {
			if ( is_array( $finding ) ) {
				$cloud_queue[] = $finding;
			}
			if ( count( $cloud_queue ) >= 3 ) {
				break;
			}
		}
		$brief_queue    = $cloud_has_detail && array() !== $cloud_queue ? $cloud_queue : $queue;
		$primary        = is_array( $brief_queue[0] ?? null ) ? $brief_queue[0] : array();
		$primary_title  = array() !== $primary ? $this->site_ops_finding_title( $primary ) : __( 'No urgent site issue found', 'npcink-workflow-toolbox' );
		$ai_next_action = '';
		if ( is_array( $cloud_next_actions[0] ?? null ) ) {
			$first_next     = $cloud_next_actions[0];
			$ai_next_action = $this->site_ops_dynamic_label( (string) ( $first_next['label'] ?? $first_next['target'] ?? $first_next['id'] ?? '' ) );
		}
		$closure_next     = $this->site_ops_dynamic_label( (string) ( $analysis_closure['next_step'] ?? $analysis_closure['loop_status'] ?? '' ) );
		$confidence_level = $this->site_ops_dynamic_label( (string) ( $confidence['level'] ?? '' ) );
		?>
		<section class="npcink-toolbox__ops-operator-brief" aria-label="<?php esc_attr_e( 'Site action brief', 'npcink-workflow-toolbox' ); ?>">
			<div class="npcink-toolbox__ops-operator-brief-header">
				<div>
					<h3><?php esc_html_e( 'Site action brief', 'npcink-workflow-toolbox' ); ?></h3>
					<p>
						<?php
						printf(
							/* translators: 1: primary finding title, 2: review-workflow count, 3: manual-check count. */
							esc_html__( 'Start with %1$s. %2$d items may need a review workflow and %3$d are manual checks.', 'npcink-workflow-toolbox' ),
							esc_html( $primary_title ),
							(int) $review_count,
							(int) $manual_count
						);
						?>
					</p>
					<?php if ( '' !== $cloud_headline ) : ?>
						<p><?php echo esc_html( $this->site_ops_dynamic_label( $cloud_headline ) ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $cloud_summary ) : ?>
						<p><?php echo esc_html( $this->site_ops_dynamic_label( $cloud_summary ) ); ?></p>
					<?php endif; ?>
				</div>
				<span class="npcink-toolbox__ops-brief-source">
					<?php echo esc_html( $cloud_has_detail ? __( 'AI detail added', 'npcink-workflow-toolbox' ) : __( 'Local brief', 'npcink-workflow-toolbox' ) ); ?>
				</span>
			</div>
			<div class="npcink-toolbox__ops-operator-brief-grid">
				<div>
					<strong><?php esc_html_e( 'Do first', 'npcink-workflow-toolbox' ); ?></strong>
					<?php if ( array() === $brief_queue ) : ?>
						<p><?php esc_html_e( 'No priority task was produced by this bounded scan.', 'npcink-workflow-toolbox' ); ?></p>
					<?php else : ?>
						<ol>
							<?php foreach ( $brief_queue as $finding ) : ?>
								<li>
									<b><?php echo esc_html( $this->site_ops_finding_title( $finding ) ); ?></b>
									<span><?php echo esc_html( $this->site_ops_finding_recommended_action( $finding ) ); ?></span>
									<?php $this->render_site_ops_action_buttons( $finding, 'brief' ); ?>
								</li>
							<?php endforeach; ?>
						</ol>
					<?php endif; ?>
				</div>
				<div>
					<strong><?php esc_html_e( 'Defer for now', 'npcink-workflow-toolbox' ); ?></strong>
					<?php if ( array() === $deferred ) : ?>
						<p><?php esc_html_e( 'Do not expand scope until the first tasks are reviewed.', 'npcink-workflow-toolbox' ); ?></p>
					<?php else : ?>
						<ul>
							<?php foreach ( $deferred as $finding ) : ?>
								<li><?php echo esc_html( $this->site_ops_finding_title( $finding ) ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
				<div>
					<strong><?php esc_html_e( 'AI assist', 'npcink-workflow-toolbox' ); ?></strong>
					<?php if ( $cloud_has_detail ) : ?>
						<p><?php esc_html_e( 'AI summary and ranking are folded into this brief. Use Cloud detail for the evidence trail before expanding work.', 'npcink-workflow-toolbox' ); ?></p>
						<?php if ( '' !== $ai_next_action ) : ?>
							<p>
								<?php
								printf(
									/* translators: %s: AI suggested next action. */
									esc_html__( 'AI next step: %s', 'npcink-workflow-toolbox' ),
									esc_html( $ai_next_action )
								);
								?>
							</p>
						<?php endif; ?>
						<?php if ( '' !== $confidence_level ) : ?>
							<p>
								<?php
								printf(
									/* translators: %s: AI confidence level. */
									esc_html__( 'AI confidence: %s', 'npcink-workflow-toolbox' ),
									esc_html( $confidence_level )
								);
								?>
							</p>
						<?php endif; ?>
					<?php elseif ( $cloud_ready ) : ?>
						<p><?php esc_html_e( 'Need a clearer explanation or semantic ranking? Use Cloud detail after reviewing the local top items.', 'npcink-workflow-toolbox' ); ?></p>
						<a class="button button-small" href="<?php echo esc_url( $this->site_ops_cloud_analysis_url() ); ?>"><?php esc_html_e( 'Use Cloud detail', 'npcink-workflow-toolbox' ); ?></a>
					<?php else : ?>
						<p><?php esc_html_e( 'Cloud is not ready, so this brief uses local rules only. Connect Cloud when you need AI summary or semantic ranking.', 'npcink-workflow-toolbox' ); ?></p>
					<?php endif; ?>
				</div>
				<div>
					<strong><?php esc_html_e( 'Close the loop', 'npcink-workflow-toolbox' ); ?></strong>
					<?php if ( '' !== $closure_next ) : ?>
						<p>
							<?php
							printf(
								/* translators: %s: Cloud-reported analysis closure or next step. */
								esc_html__( 'AI closure: %s', 'npcink-workflow-toolbox' ),
								esc_html( $closure_next )
							);
							?>
						</p>
					<?php else : ?>
						<p><?php esc_html_e( 'Open the affected examples, decide manual handling or review workflow, then leave evidence in the normal editorial path. Nothing changes automatically.', 'npcink-workflow-toolbox' ); ?></p>
					<?php endif; ?>
					<?php /* translators: %d: number of scanned posts and pages. */ ?>
					<span><?php printf( esc_html__( 'Current scan: %d posts/pages.', 'npcink-workflow-toolbox' ), (int) ( $summary['scanned_posts'] ?? 0 ) ); ?></span>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * @param array<int,mixed>    $findings Findings.
	 */
	protected function render_site_ops_handling_path_panel( array $findings ): void {
		$manual_count = $this->count_site_ops_findings_by_boundary( $findings, 'manual_review_only' );
		$review_count = $this->count_site_ops_findings_by_boundary( $findings, 'core_handoff_candidate' );
		$cloud_count  = $this->count_site_ops_findings_by_boundary( $findings, 'blocked_until_cloud_ready' );
		$watch_count  = max( 0, count( $findings ) - $manual_count - $review_count );
		$paths        = array(
			array(
				'key'        => 'manual',
				'label'      => __( 'Handle manually', 'npcink-workflow-toolbox' ),
				'count'      => $manual_count,
				'finding'    => $this->site_ops_first_finding_by_boundary( $findings, array( 'manual_review_only' ) ),
				'summary'    => __( 'Open the affected content, media, comments, taxonomy, or settings in WordPress and check it yourself.', 'npcink-workflow-toolbox' ),
				'next_step'  => __( 'Use this path for simple review notes and small operator fixes. If it becomes a write workflow, move it to review.', 'npcink-workflow-toolbox' ),
				'empty_text' => __( 'No manual-only issues in this scan.', 'npcink-workflow-toolbox' ),
			),
			array(
				'key'        => 'review',
				'label'      => __( 'Send to review workflow', 'npcink-workflow-toolbox' ),
				'count'      => $review_count,
				'finding'    => $this->site_ops_first_finding_by_boundary( $findings, array( 'core_handoff_candidate' ) ),
				'summary'    => __( 'Use this path when the next step may change article content, media metadata, SEO fields, taxonomy, or site content.', 'npcink-workflow-toolbox' ),
				'next_step'  => __( 'Choose one affected item, confirm the evidence, write the accepted note, then prepare a governed handoff outside this report.', 'npcink-workflow-toolbox' ),
				'empty_text' => __( 'No review-workflow candidates in this scan.', 'npcink-workflow-toolbox' ),
			),
			array(
				'key'        => 'watch',
				'label'      => __( 'Watch for now', 'npcink-workflow-toolbox' ),
				'count'      => $watch_count,
				'finding'    => $this->site_ops_first_finding_by_boundary( $findings, array( 'blocked_until_cloud_ready', 'suggestion_only' ) ),
				'summary'    => 0 < $cloud_count ? __( 'Keep these as notes unless Cloud detail is needed to rank or explain the issue.', 'npcink-workflow-toolbox' ) : __( 'Keep these as notes unless they block readers, search, or daily site operations.', 'npcink-workflow-toolbox' ),
				'next_step'  => __( 'Do not start a workflow yet. Review again after the first priority items are handled.', 'npcink-workflow-toolbox' ),
				'empty_text' => __( 'No observe-only issues in this scan.', 'npcink-workflow-toolbox' ),
			),
		);
		?>
		<section class="npcink-toolbox__ops-path-panel" aria-label="<?php esc_attr_e( 'Treatment paths', 'npcink-workflow-toolbox' ); ?>">
			<div class="npcink-toolbox__section-heading npcink-toolbox__section-heading--compact">
				<div>
					<h3><?php esc_html_e( 'Choose a treatment path', 'npcink-workflow-toolbox' ); ?></h3>
					<p><?php esc_html_e( 'Sort each issue before opening details: handle it manually, prepare it for review, or watch it for now.', 'npcink-workflow-toolbox' ); ?></p>
				</div>
			</div>
			<div class="npcink-toolbox__ops-path-grid">
				<?php foreach ( $paths as $path ) : ?>
					<?php $finding = is_array( $path['finding'] ?? null ) ? $path['finding'] : array(); ?>
					<div class="npcink-toolbox__ops-path-row npcink-toolbox__ops-path-row--<?php echo esc_attr( (string) $path['key'] ); ?>">
						<div class="npcink-toolbox__ops-path-head">
							<strong><?php echo esc_html( (string) $path['label'] ); ?></strong>
							<span>
								<?php
								printf(
									/* translators: %d: number of findings for this treatment path. */
									esc_html__( '%d issues', 'npcink-workflow-toolbox' ),
									(int) $path['count']
								);
								?>
							</span>
						</div>
						<p><?php echo esc_html( (string) $path['summary'] ); ?></p>
						<?php if ( array() !== $finding ) : ?>
							<p class="npcink-toolbox__ops-path-first">
								<?php
								printf(
									/* translators: %s: first issue title for this treatment path. */
									esc_html__( 'First item: %s', 'npcink-workflow-toolbox' ),
									esc_html( $this->site_ops_finding_title( $finding ) )
								);
								?>
							</p>
						<?php else : ?>
							<p class="npcink-toolbox__ops-path-first"><?php echo esc_html( (string) $path['empty_text'] ); ?></p>
						<?php endif; ?>
						<p class="npcink-toolbox__ops-path-next"><?php echo esc_html( (string) $path['next_step'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="npcink-toolbox__ops-path-note"><?php esc_html_e( 'This panel does not create tasks, proposals, queues, or WordPress changes.', 'npcink-workflow-toolbox' ); ?></p>
		</section>
		<?php
	}

	/**
	 * @param array<int,mixed>    $findings Findings.
	 * @param array<int,string>   $boundaries Boundaries.
	 * @return array<string,mixed>
	 */
	private function site_ops_first_finding_by_boundary( array $findings, array $boundaries ): array {
		foreach ( $findings as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}
			$boundary = (string) ( $finding['write_boundary'] ?? 'suggestion_only' );
			if ( in_array( $boundary, $boundaries, true ) ) {
				return $finding;
			}
		}

		return array();
	}

	/**
	 * @param array<string,mixed> $summary Summary payload.
	 * @param array<int,mixed>    $findings Findings.
	 */
	protected function render_site_ops_local_analysis_summary( array $summary, array $findings ): void {
		$finding_count      = count( $findings );
		$high_count         = (int) ( $summary['high_priority_findings'] ?? $this->count_site_ops_findings_by_priority( $findings, 90, 101 ) );
		$taxonomy_terms     = (int) ( $summary['category_terms'] ?? 0 ) + (int) ( $summary['tag_terms'] ?? 0 );
		$link_graph_scanned = (int) ( $summary['internal_link_graph_scanned_posts'] ?? 0 );
		$link_graph_issues  = (int) ( $summary['internal_link_graph_issue_count'] ?? 0 );
		$dimension_counts   = array(
			__( 'Content coverage', 'npcink-workflow-toolbox' )   => count( $this->site_ops_findings_by_category( $findings, array( 'content_freshness', 'content_quality', 'internal_link_health', 'metadata' ) ) ),
			__( 'Media coverage', 'npcink-workflow-toolbox' )     => count( $this->site_ops_findings_by_category( $findings, array( 'media' ) ) ),
			__( 'Comment coverage', 'npcink-workflow-toolbox' )   => count( $this->site_ops_findings_by_category( $findings, array( 'comments' ) ) ),
			__( 'Structure coverage', 'npcink-workflow-toolbox' ) => count( $this->site_ops_findings_by_category( $findings, array( 'taxonomy', 'site_context', 'site_knowledge' ) ) ),
		);
		?>
		<div class="npcink-toolbox__ops-summary-bar" aria-label="<?php esc_attr_e( 'Local analysis summary', 'npcink-workflow-toolbox' ); ?>">
			<div>
				<strong><?php esc_html_e( 'Coverage snapshot', 'npcink-workflow-toolbox' ); ?></strong>
				<span>
					<?php
					printf(
						/* translators: 1: number of findings, 2: suggested first focus area. */
						esc_html__( 'Local analysis summary: %1$d findings across content, media, comments, and structure. First focus: %2$s.', 'npcink-workflow-toolbox' ),
						(int) $finding_count,
						esc_html( $this->site_ops_analysis_focus_label( $findings ) )
					);
					?>
				</span>
				<span><?php esc_html_e( 'Deterministic local analysis only; use Cloud only for AI summary, semantic ranking, trends, or heavier runtime detail.', 'npcink-workflow-toolbox' ); ?></span>
			</div>
			<div class="npcink-toolbox__ops-scope">
				<?php /* translators: %d: number of high priority findings. */ ?>
				<span><?php printf( esc_html__( '%d high priority', 'npcink-workflow-toolbox' ), (int) $high_count ); ?></span>
				<?php /* translators: %d: number of taxonomy terms. */ ?>
				<span><?php printf( esc_html__( '%d taxonomy terms', 'npcink-workflow-toolbox' ), (int) $taxonomy_terms ); ?></span>
				<?php if ( $link_graph_scanned > 0 ) : ?>
					<?php /* translators: 1: scanned post count, 2: internal-link graph issue count. */ ?>
					<span><?php printf( esc_html__( '%1$d posts checked; %2$d internal-link issues', 'npcink-workflow-toolbox' ), (int) $link_graph_scanned, (int) $link_graph_issues ); ?></span>
				<?php endif; ?>
				<span><?php echo esc_html( $this->site_ops_local_report_status( $finding_count, $high_count ) ); ?></span>
			</div>
		</div>
		<div class="npcink-toolbox__ops-detail-grid" aria-label="<?php esc_attr_e( 'Local coverage by area', 'npcink-workflow-toolbox' ); ?>">
			<?php foreach ( $dimension_counts as $label => $count ) : ?>
				<div>
					<strong><?php echo esc_html( (string) $label ); ?></strong>
					<?php /* translators: %d: number of findings in this analysis area. */ ?>
					<span><?php printf( esc_html__( '%d findings', 'npcink-workflow-toolbox' ), (int) $count ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * @param array<int,mixed> $findings Findings.
	 */
	protected function render_site_ops_decision_queue( array $findings ): void {
		$queue = array();
		foreach ( $findings as $finding ) {
			if ( is_array( $finding ) ) {
				$queue[] = $finding;
			}
			if ( count( $queue ) >= 3 ) {
				break;
			}
		}
		if ( array() === $queue ) {
			return;
		}
		?>
		<section class="npcink-toolbox__ops-decision-queue" aria-label="<?php esc_attr_e( 'Priority decision queue', 'npcink-workflow-toolbox' ); ?>">
			<div class="npcink-toolbox__section-heading npcink-toolbox__section-heading--compact">
				<div>
					<h3><?php esc_html_e( 'Handle these first', 'npcink-workflow-toolbox' ); ?></h3>
					<p><?php esc_html_e( 'Each card explains why it matters, shows sampled affected items, and gives the first safe action.', 'npcink-workflow-toolbox' ); ?></p>
				</div>
			</div>
			<div class="npcink-toolbox__ops-decision-list">
				<?php foreach ( $queue as $index => $finding ) : ?>
					<?php
					$boundary  = (string) ( $finding['write_boundary'] ?? 'suggestion_only' );
					$follow_up = $this->site_ops_follow_up_path_detail( $finding );
					$score     = (int) ( $finding['priority_score'] ?? 0 );
					$owner     = $this->site_ops_owner_label( (string) ( $finding['owner_label'] ?? '' ) );
					?>
					<article class="npcink-toolbox__ops-decision-card">
						<div class="npcink-toolbox__ops-decision-rank">
							<span><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
							<em><?php echo esc_html( $this->site_ops_priority_action_label( $score ) ); ?></em>
						</div>
						<div class="npcink-toolbox__ops-decision-body">
							<div class="npcink-toolbox__ops-decision-header">
								<div>
									<span class="npcink-toolbox__ops-priority-pill"><?php echo esc_html( $this->site_ops_priority_action_label( (int) ( $finding['priority_score'] ?? 0 ) ) ); ?></span>
									<h4><?php echo esc_html( $this->site_ops_finding_title( $finding ) ); ?></h4>
								</div>
								<span class="npcink-toolbox__ops-handling-pill">
									<strong><?php esc_html_e( 'Handling', 'npcink-workflow-toolbox' ); ?></strong>
									<?php echo esc_html( $this->site_ops_boundary_label( $boundary ) ); ?>
								</span>
							</div>
							<dl class="npcink-toolbox__ops-decision-main">
								<div>
									<dt><?php esc_html_e( 'Why it matters', 'npcink-workflow-toolbox' ); ?></dt>
									<dd><?php echo esc_html( $this->site_ops_finding_impact( $finding ) ); ?></dd>
								</div>
								<div>
									<dt><?php esc_html_e( 'Affected examples', 'npcink-workflow-toolbox' ); ?></dt>
									<dd><?php $this->render_site_ops_affected_examples( $finding ); ?></dd>
								</div>
								<div>
									<dt><?php esc_html_e( 'First safe action', 'npcink-workflow-toolbox' ); ?></dt>
									<dd><?php echo esc_html( $this->site_ops_finding_recommended_action( $finding ) ); ?></dd>
								</div>
								<?php if ( '' !== $owner ) : ?>
									<div>
										<dt><?php esc_html_e( 'Owner', 'npcink-workflow-toolbox' ); ?></dt>
										<dd><?php echo esc_html( $owner ); ?></dd>
									</div>
								<?php endif; ?>
							</dl>
							<?php $this->render_site_ops_action_buttons( $finding, 'decision' ); ?>
							<?php $this->render_site_ops_acceptance_line( 'decision' ); ?>
							<?php if ( 'core_handoff_candidate' === $boundary ) : ?>
								<?php $this->render_site_ops_handoff_candidate_preview( $finding ); ?>
							<?php endif; ?>
							<details class="npcink-toolbox__ops-follow-up" aria-label="<?php esc_attr_e( 'Handling rules and limits', 'npcink-workflow-toolbox' ); ?>">
								<summary><?php esc_html_e( 'View handling rules and limits', 'npcink-workflow-toolbox' ); ?></summary>
								<div class="npcink-toolbox__ops-follow-up-copy">
									<p><strong><?php esc_html_e( 'What this means', 'npcink-workflow-toolbox' ); ?></strong><?php echo esc_html( $follow_up['meaning'] ); ?></p>
									<p><strong><?php esc_html_e( 'Before you act', 'npcink-workflow-toolbox' ); ?></strong><?php echo esc_html( $follow_up['needs'] ); ?></p>
									<p><strong><?php esc_html_e( 'Limit', 'npcink-workflow-toolbox' ); ?></strong><?php echo esc_html( $follow_up['limit'] ); ?></p>
								</div>
							</details>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 */
	private function render_site_ops_handoff_candidate_preview( array $finding ): void {
		$source_refs = isset( $finding['source_refs'] ) && is_array( $finding['source_refs'] ) ? array_slice( $finding['source_refs'], 0, 3 ) : array();
		$evidence    = $this->site_ops_finding_evidence_summary( $finding );
		$action      = $this->site_ops_finding_recommended_action( $finding );
		?>
		<details class="npcink-toolbox__ops-handoff-preview" aria-label="<?php esc_attr_e( 'Review workflow candidate preview', 'npcink-workflow-toolbox' ); ?>">
			<summary><?php esc_html_e( 'View review candidate', 'npcink-workflow-toolbox' ); ?></summary>
			<div class="npcink-toolbox__ops-handoff-preview-body">
				<p><?php esc_html_e( 'Use this as a handoff draft only after choosing one affected item and confirming the evidence.', 'npcink-workflow-toolbox' ); ?></p>
				<?php if ( array() !== $source_refs ) : ?>
					<div>
						<strong><?php esc_html_e( 'Candidate objects', 'npcink-workflow-toolbox' ); ?></strong>
						<ul class="npcink-toolbox__ops-handoff-candidates">
							<?php foreach ( $source_refs as $ref ) : ?>
								<?php
								if ( ! is_array( $ref ) ) {
									continue; }
								?>
								<?php
								$object_id   = (int) ( $ref['object_id'] ?? 0 );
								$object_type = sanitize_key( (string) ( $ref['object_type'] ?? 'post' ) );
								$title       = trim( (string) ( $ref['title'] ?? '' ) );
								$title       = '' !== $title ? $title : __( 'Untitled item', 'npcink-workflow-toolbox' );
								$link        = $object_id > 0 && current_user_can( 'edit_post', $object_id ) ? get_edit_post_link( $object_id, '' ) : '';
								?>
								<li>
									<?php if ( is_string( $link ) && '' !== $link ) : ?>
										<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
									<?php else : ?>
										<span><?php echo esc_html( $title ); ?></span>
									<?php endif; ?>
									<em><?php echo esc_html( $this->site_ops_object_type_label( $object_type ) ); ?></em>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<div class="npcink-toolbox__ops-handoff-copy-grid">
					<div>
						<strong><?php esc_html_e( 'Evidence to carry forward', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php echo esc_html( '' !== $evidence ? $evidence : __( 'Use the finding evidence and selected object before preparing a review workflow.', 'npcink-workflow-toolbox' ) ); ?></span>
					</div>
					<div>
						<strong><?php esc_html_e( 'Suggested review note', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php echo esc_html( $action ); ?></span>
					</div>
					<div>
						<strong><?php esc_html_e( 'Boundary', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Draft only. Toolbox does not create proposals, queue work, approve changes, or write WordPress data.', 'npcink-workflow-toolbox' ); ?></span>
					</div>
				</div>
			</div>
		</details>
		<?php
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 */
	private function render_site_ops_affected_examples( array $finding ): void {
		$source_refs = isset( $finding['source_refs'] ) && is_array( $finding['source_refs'] ) ? array_slice( $finding['source_refs'], 0, 3 ) : array();
		if ( array() === $source_refs ) {
			echo esc_html( $this->site_ops_finding_evidence_summary( $finding ) );
			return;
		}
		?>
		<span class="npcink-toolbox__ops-evidence-summary"><?php echo esc_html( $this->site_ops_finding_evidence_summary( $finding ) ); ?></span>
		<ul class="npcink-toolbox__ops-affected-list">
			<?php foreach ( $source_refs as $ref ) : ?>
				<?php
				if ( ! is_array( $ref ) ) {
					continue; }
				?>
				<?php
				$object_id   = (int) ( $ref['object_id'] ?? 0 );
				$object_type = sanitize_key( (string) ( $ref['object_type'] ?? 'post' ) );
				$title       = trim( (string) ( $ref['title'] ?? '' ) );
				$title       = '' !== $title ? $title : __( 'Untitled item', 'npcink-workflow-toolbox' );
				$link        = $object_id > 0 && current_user_can( 'edit_post', $object_id ) ? get_edit_post_link( $object_id, '' ) : '';
				?>
				<li>
					<?php if ( is_string( $link ) && '' !== $link ) : ?>
						<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
					<?php else : ?>
						<span><?php echo esc_html( $title ); ?></span>
					<?php endif; ?>
					<em><?php echo esc_html( $this->site_ops_object_type_label( $object_type ) ); ?></em>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	private function site_ops_object_type_label( string $object_type ): string {
		if ( 'attachment' === $object_type ) {
			return __( 'Media item', 'npcink-workflow-toolbox' );
		}
		if ( 'page' === $object_type ) {
			return __( 'Page', 'npcink-workflow-toolbox' );
		}
		return __( 'Post', 'npcink-workflow-toolbox' );
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 * @return array{meaning:string,needs:string,limit:string}
	 */
	private function site_ops_follow_up_path_detail( array $finding ): array {
		$boundary = (string) ( $finding['write_boundary'] ?? 'suggestion_only' );
		if ( 'core_handoff_candidate' === $boundary ) {
			return array(
				'meaning' => __( 'This report only marks a candidate. Pick the exact content and confirm the evidence before using the normal review flow outside this report.', 'npcink-workflow-toolbox' ),
				'needs'   => __( 'A selected item, accepted edits or notes, and a matching review path.', 'npcink-workflow-toolbox' ),
				'limit'   => __( 'This report will not create the review task or change WordPress.', 'npcink-workflow-toolbox' ),
			);
		}
		if ( 'manual_review_only' === $boundary ) {
			return array(
				'meaning' => __( 'Use this as an editorial or operator checklist before deciding whether a formal review path is needed.', 'npcink-workflow-toolbox' ),
				'needs'   => __( 'Human review of the affected content, media, comments, taxonomy, or settings.', 'npcink-workflow-toolbox' ),
				'limit'   => __( 'Nothing is changed automatically from this report.', 'npcink-workflow-toolbox' ),
			);
		}
		if ( 'blocked_until_cloud_ready' === $boundary ) {
			return array(
				'meaning' => __( 'Local evidence is enough to show the blocker, but semantic ranking or trend explanation needs Cloud runtime/detail.', 'npcink-workflow-toolbox' ),
				'needs'   => __( 'Cloud readiness and an explicit Use Cloud detail action by the operator.', 'npcink-workflow-toolbox' ),
				'limit'   => __( 'Toolbox will not retry, queue, or run Cloud detail automatically.', 'npcink-workflow-toolbox' ),
			);
		}
		return array(
			'meaning' => __( 'Use this as decision support for the current report only.', 'npcink-workflow-toolbox' ),
			'needs'   => __( 'Operator judgment before choosing any follow-up workflow.', 'npcink-workflow-toolbox' ),
			'limit'   => __( 'No automatic action, proposal, queue, or WordPress write is created.', 'npcink-workflow-toolbox' ),
		);
	}

	/**
	 * @param array<string,mixed> $summary Summary payload.
	 * @param array<int,mixed>    $findings Findings.
	 */
	protected function render_site_ops_visual_summary( array $summary, array $findings ): void {
		$priority_counts  = array(
			'high'   => $this->count_site_ops_findings_by_priority( $findings, 90, 101 ),
			'medium' => $this->count_site_ops_findings_by_priority( $findings, 75, 90 ),
			'review' => $this->count_site_ops_findings_by_priority( $findings, 0, 75 ),
		);
		$priority_max     = max( 1, $priority_counts['high'], $priority_counts['medium'], $priority_counts['review'] );
		$core_count       = $this->count_site_ops_findings_by_boundary( $findings, 'core_handoff_candidate' );
		$manual_count     = $this->count_site_ops_findings_by_boundary( $findings, 'manual_review_only' );
		$suggestion_count = max( 0, count( $findings ) - $core_count - $manual_count );
		$boundary_total   = max( 1, $core_count + $manual_count + $suggestion_count );
		$core_degrees     = (int) round( 360 * $core_count / $boundary_total );
		$manual_degrees   = (int) round( 360 * ( $core_count + $manual_count ) / $boundary_total );
		$scope_counts     = array(
			__( 'Posts/pages', 'npcink-workflow-toolbox' ) => (int) ( $summary['scanned_posts'] ?? 0 ),
			__( 'Media', 'npcink-workflow-toolbox' )       => (int) ( $summary['scanned_media'] ?? 0 ),
			__( 'Comments', 'npcink-workflow-toolbox' )    => (int) ( $summary['recent_comment_sample'] ?? 0 ),
			__( 'Findings', 'npcink-workflow-toolbox' )    => (int) ( $summary['top_finding_count'] ?? count( $findings ) ),
		);
		$scope_max        = max( 1, ...array_values( $scope_counts ) );
		?>
		<div class="npcink-toolbox__ops-chart-grid" aria-label="<?php esc_attr_e( 'Site Check charts', 'npcink-workflow-toolbox' ); ?>">
			<section class="npcink-toolbox__ops-chart">
				<h4><?php esc_html_e( 'Priority distribution', 'npcink-workflow-toolbox' ); ?></h4>
				<div class="npcink-toolbox__ops-bar-chart" aria-label="<?php esc_attr_e( 'Findings by priority', 'npcink-workflow-toolbox' ); ?>">
					<?php foreach ( $priority_counts as $bucket => $count ) : ?>
						<?php $height = (int) round( 100 * $count / $priority_max ); ?>
						<div class="npcink-toolbox__ops-bar" style="<?php echo esc_attr( '--bar-height:' . $height . '%;' ); ?>">
							<span><?php echo esc_html( (string) $count ); ?></span>
							<em><?php echo esc_html( $this->site_ops_priority_bucket_label( (string) $bucket ) ); ?></em>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
			<section class="npcink-toolbox__ops-chart">
				<h4><?php esc_html_e( 'Review path mix', 'npcink-workflow-toolbox' ); ?></h4>
				<div class="npcink-toolbox__ops-donut-row">
					<span class="npcink-toolbox__ops-donut" style="<?php echo esc_attr( '--core-deg:' . $core_degrees . 'deg;--manual-deg:' . $manual_degrees . 'deg;' ); ?>"></span>
					<ul class="npcink-toolbox__ops-legend">
						<?php /* translators: %d: number of findings that are Core planning candidates. */ ?>
						<li><span class="is-core"></span><?php printf( esc_html__( '%d Core planning', 'npcink-workflow-toolbox' ), (int) $core_count ); ?></li>
						<?php /* translators: %d: number of findings that require manual review. */ ?>
						<li><span class="is-manual"></span><?php printf( esc_html__( '%d manual review', 'npcink-workflow-toolbox' ), (int) $manual_count ); ?></li>
						<?php /* translators: %d: number of suggestion-only findings. */ ?>
						<li><span class="is-suggestion"></span><?php printf( esc_html__( '%d suggestion only', 'npcink-workflow-toolbox' ), (int) $suggestion_count ); ?></li>
					</ul>
				</div>
			</section>
			<section class="npcink-toolbox__ops-chart">
				<h4><?php esc_html_e( 'Scan scope', 'npcink-workflow-toolbox' ); ?></h4>
				<div class="npcink-toolbox__ops-scope-bars">
					<?php foreach ( $scope_counts as $label => $count ) : ?>
						<?php $width = (int) round( 100 * $count / $scope_max ); ?>
						<div class="npcink-toolbox__ops-scope-bar" style="<?php echo esc_attr( '--bar-width:' . $width . '%;' ); ?>">
							<strong><?php echo esc_html( (string) $label ); ?></strong>
							<span><i></i></span>
							<em><?php echo esc_html( (string) $count ); ?></em>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * @param array<string,mixed> $summary Summary payload.
	 * @param array<int,mixed>    $findings Findings.
	 * @param array<int,string>   $categories Finding categories.
	 */
	protected function render_site_ops_dimension_panel( string $title, string $description, array $summary, array $findings, array $categories ): void {
		$dimension_findings = $this->site_ops_findings_by_category( $findings, $categories );
		?>
		<div class="npcink-toolbox__ops-summary-bar npcink-toolbox__ops-summary-bar--dimension">
			<div>
				<strong><?php echo esc_html( $title ); ?></strong>
				<span><?php echo esc_html( $description ); ?></span>
			</div>
			<div class="npcink-toolbox__ops-scope">
				<?php /* translators: %d: number of scanned posts and pages. */ ?>
				<span><?php printf( esc_html__( '%d posts/pages', 'npcink-workflow-toolbox' ), (int) ( $summary['scanned_posts'] ?? 0 ) ); ?></span>
				<?php /* translators: %d: number of scanned media items. */ ?>
				<span><?php printf( esc_html__( '%d media', 'npcink-workflow-toolbox' ), (int) ( $summary['scanned_media'] ?? 0 ) ); ?></span>
				<?php /* translators: %d: number of sampled comments. */ ?>
				<span><?php printf( esc_html__( '%d comments', 'npcink-workflow-toolbox' ), (int) ( $summary['recent_comment_sample'] ?? 0 ) ); ?></span>
				<?php /* translators: %d: number of findings related to this analysis area. */ ?>
				<span><?php printf( esc_html__( '%d related findings', 'npcink-workflow-toolbox' ), (int) count( $dimension_findings ) ); ?></span>
			</div>
		</div>
		<?php if ( array() === $dimension_findings ) : ?>
			<div class="npcink-toolbox__result-notice is-success"><?php esc_html_e( 'No priority findings were produced for this analysis area from the current bounded sample.', 'npcink-workflow-toolbox' ); ?></div>
		<?php else : ?>
			<div class="npcink-toolbox__ops-priority-list">
				<?php foreach ( $dimension_findings as $finding ) : ?>
					<?php $this->render_site_ops_finding_row( $finding ); ?>
				<?php endforeach; ?>
			</div>
			<?php $this->render_site_ops_dimension_rules( $dimension_findings ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders the shared, folded handling guidance once per analysis dimension.
	 *
	 * @param array<int,mixed> $findings Findings for the current dimension.
	 */
	private function render_site_ops_dimension_rules( array $findings ): void {
		$boundaries = array();
		foreach ( $findings as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}
			$boundary = (string) ( $finding['write_boundary'] ?? 'suggestion_only' );
			if ( ! isset( $boundaries[ $boundary ] ) ) {
				$boundaries[ $boundary ] = $this->site_ops_boundary_guidance( $boundary );
			}
		}
		if ( array() === $boundaries ) {
			return;
		}
		?>
		<details class="npcink-toolbox__ops-dimension-rules" aria-label="<?php esc_attr_e( 'Handling rules and limits', 'npcink-workflow-toolbox' ); ?>">
			<summary><?php esc_html_e( 'View handling rules and limits', 'npcink-workflow-toolbox' ); ?></summary>
			<ul>
				<?php foreach ( $boundaries as $boundary => $guidance ) : ?>
					<li>
						<strong><?php echo esc_html( $this->site_ops_boundary_label( (string) $boundary ) ); ?></strong>
						<span><?php echo esc_html( (string) $guidance ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</details>
		<?php
	}

	/**
	 * @param mixed $finding Finding payload.
	 */
	protected function render_site_ops_finding_row( $finding ): void {
		if ( ! is_array( $finding ) ) {
			return;
		}
		$title    = $this->site_ops_finding_title( $finding );
		$summary  = $this->site_ops_finding_evidence_summary( $finding );
		$action   = $this->site_ops_finding_recommended_action( $finding );
		$score    = (int) ( $finding['priority_score'] ?? 0 );
		$owner    = $this->site_ops_owner_label( (string) ( $finding['owner_label'] ?? '' ) );
		$boundary = (string) ( $finding['write_boundary'] ?? 'suggestion_only' );
		?>
		<article class="npcink-toolbox__ops-priority-row">
			<div class="npcink-toolbox__ops-priority-main">
				<span class="npcink-toolbox__priority-label"><?php echo esc_html( $this->site_ops_priority_label( $score ) ); ?></span>
				<div>
					<div class="npcink-toolbox__ops-priority-heading">
						<h3><?php echo esc_html( $title ); ?></h3>
						<span class="npcink-toolbox__priority-score"><?php echo esc_html( $this->site_ops_priority_action_label( $score ) ); ?></span>
						<span class="npcink-toolbox__ops-handling-pill"><?php echo esc_html( $this->site_ops_boundary_label( $boundary ) ); ?></span>
					</div>
					<p><?php echo esc_html( $summary ); ?></p>
				</div>
			</div>
			<div class="npcink-toolbox__ops-action-line">
				<strong><?php esc_html_e( 'Next', 'npcink-workflow-toolbox' ); ?></strong>
				<span><?php echo esc_html( $action ); ?></span>
				<?php $this->render_site_ops_action_buttons( $finding, 'row' ); ?>
				<?php if ( '' !== $owner ) : ?>
					<strong><?php esc_html_e( 'Owner', 'npcink-workflow-toolbox' ); ?></strong>
					<span><?php echo esc_html( $owner ); ?></span>
				<?php endif; ?>
			</div>
			<?php $this->render_site_ops_acceptance_line(); ?>
		</article>
		<?php
	}

	/**
	 * Renders the bounded local rescan step that verifies a handled finding.
	 */
	private function render_site_ops_acceptance_line( string $context = 'row' ): void {
		?>
		<div class="npcink-toolbox__ops-acceptance-line npcink-toolbox__ops-acceptance-line--<?php echo esc_attr( sanitize_html_class( $context ) ); ?>">
			<strong><?php esc_html_e( 'Acceptance check', 'npcink-workflow-toolbox' ); ?></strong>
			<span><?php esc_html_e( 'After handling the selected item through the allowed manual or review path, rescan and confirm it no longer matches this finding or the affected count has decreased. This is a current bounded check, not a saved completion record.', 'npcink-workflow-toolbox' ); ?></span>
			<a class="button button-small" href="<?php echo esc_url( $this->site_ops_insights_preview_url() ); ?>"><?php esc_html_e( 'Rescan to verify', 'npcink-workflow-toolbox' ); ?></a>
		</div>
		<?php
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 */
	private function render_site_ops_action_buttons( array $finding, string $context = 'row' ): void {
		$actions = $this->site_ops_action_links( $finding );
		if ( array() === $actions ) {
			return;
		}
		?>
		<div class="npcink-toolbox__ops-next-actions npcink-toolbox__ops-next-actions--<?php echo esc_attr( sanitize_html_class( $context ) ); ?>">
			<?php foreach ( $actions as $action ) : ?>
				<a class="<?php echo esc_attr( (string) $action['class'] ); ?>" href="<?php echo esc_url( (string) $action['url'] ); ?>"><?php echo esc_html( (string) $action['label'] ); ?></a>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 * @return array<int,array{label:string,url:string,class:string}>
	 */
	private function site_ops_action_links( array $finding ): array {
		$actions    = array();
		$source_ref = $this->site_ops_first_actionable_ref( $finding );
		if ( array() !== $source_ref ) {
			$object_id   = (int) ( $source_ref['object_id'] ?? 0 );
			$object_type = sanitize_key( (string) ( $source_ref['object_type'] ?? 'post' ) );
			$link        = $object_id > 0 ? get_edit_post_link( $object_id, '' ) : '';
			if ( is_string( $link ) && '' !== $link ) {
				$actions[] = array(
					'label' => 'attachment' === $object_type ? __( 'Open first media item', 'npcink-workflow-toolbox' ) : ( 'internal_link_health' === $this->site_ops_finding_id( $finding ) ? __( 'Open first link review', 'npcink-workflow-toolbox' ) : __( 'Open first affected item', 'npcink-workflow-toolbox' ) ),
					'url'   => $link,
					'class' => 'button button-small',
				);
			}
		}

		$issue_type = sanitize_key( (string) ( $finding['issue_type'] ?? $finding['category'] ?? '' ) );
		if ( array() === $actions ) {
			$fallback = $this->site_ops_fallback_action_for_issue_type( $issue_type );
			if ( null !== $fallback ) {
				$actions[] = $fallback;
			}
		}

		if ( 'blocked_until_cloud_ready' === (string) ( $finding['write_boundary'] ?? '' ) ) {
			$actions[] = array(
				'label' => __( 'Use Cloud detail', 'npcink-workflow-toolbox' ),
				'url'   => $this->site_ops_cloud_analysis_url(),
				'class' => 'button button-small',
			);
		}

		return array_slice( $actions, 0, 2 );
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 * @return array<string,mixed>
	 */
	private function site_ops_first_actionable_ref( array $finding ): array {
		$source_refs = isset( $finding['source_refs'] ) && is_array( $finding['source_refs'] ) ? $finding['source_refs'] : array();
		foreach ( $source_refs as $ref ) {
			if ( ! is_array( $ref ) ) {
				continue;
			}
			$object_id = (int) ( $ref['object_id'] ?? 0 );
			if ( $object_id > 0 && current_user_can( 'edit_post', $object_id ) ) {
				return $ref;
			}
		}
		return array();
	}

	/**
	 * @return array{label:string,url:string,class:string}|null
	 */
	private function site_ops_fallback_action_for_issue_type( string $issue_type ): ?array {
		$actions = array(
			'comments'             => array( __( 'Open comments', 'npcink-workflow-toolbox' ), admin_url( 'edit-comments.php' ) ),
			'media'                => array( __( 'Open media library', 'npcink-workflow-toolbox' ), admin_url( 'upload.php' ) ),
			'taxonomy'             => array( __( 'Review categories', 'npcink-workflow-toolbox' ), admin_url( 'edit-tags.php?taxonomy=category' ) ),
			'site_context'         => array( __( 'Open site profile', 'npcink-workflow-toolbox' ), admin_url( 'admin.php?page=npcink-toolbox&toolbox_tab=context' ) ),
			'site_knowledge'       => array( __( 'Open Site Knowledge in Cloud Addon', 'npcink-workflow-toolbox' ), admin_url( 'admin.php?page=npcink-cloud-addon&tab=site_knowledge' ) ),
			'content_freshness'    => array( __( 'Open posts', 'npcink-workflow-toolbox' ), admin_url( 'edit.php' ) ),
			'content_quality'      => array( __( 'Open posts', 'npcink-workflow-toolbox' ), admin_url( 'edit.php' ) ),
			'internal_link_health' => array( __( 'Open posts for link review', 'npcink-workflow-toolbox' ), admin_url( 'edit.php' ) ),
			'metadata'             => array( __( 'Open posts', 'npcink-workflow-toolbox' ), admin_url( 'edit.php' ) ),
		);
		if ( ! isset( $actions[ $issue_type ] ) ) {
			return null;
		}
		return array(
			'label' => (string) $actions[ $issue_type ][0],
			'url'   => (string) $actions[ $issue_type ][1],
			'class' => 'button button-small',
		);
	}

	/**
	 * @param array<int,mixed> $findings Findings.
	 */
	protected function render_site_ops_evidence_panel( array $findings ): void {
		$rendered = false;
		foreach ( $findings as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}
			$source_refs = isset( $finding['source_refs'] ) && is_array( $finding['source_refs'] ) ? $finding['source_refs'] : array();
			if ( '' === (string) ( $finding['impact'] ?? '' ) && array() === $source_refs ) {
				continue;
			}
			$rendered = true;
			$title    = $this->site_ops_finding_title( $finding, __( 'Impact and evidence', 'npcink-workflow-toolbox' ) );
			$impact   = $this->site_ops_finding_impact( $finding );
			?>
			<details class="npcink-toolbox__result-details">
				<summary><?php echo esc_html( $title ); ?></summary>
				<p><?php echo esc_html( $impact ); ?></p>
				<?php if ( array() !== $source_refs ) : ?>
					<ul class="npcink-toolbox__usage-list">
						<?php foreach ( $source_refs as $ref ) : ?>
							<?php
							if ( ! is_array( $ref ) ) {
								continue; }
							?>
							<li>
								<strong><?php echo esc_html( (string) ( $ref['title'] ?? __( 'Untitled item', 'npcink-workflow-toolbox' ) ) ); ?></strong>
								<span><?php echo esc_html( (string) ( $ref['object_type'] ?? 'post' ) . ' #' . (string) (int) ( $ref['object_id'] ?? 0 ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</details>
			<?php
		}
		if ( ! $rendered ) {
			?>
			<div class="npcink-toolbox__result-notice"><?php esc_html_e( 'No evidence references were returned for this local preview.', 'npcink-workflow-toolbox' ); ?></div>
			<?php
		}
	}

	/**
	 * @param array<int,mixed> $findings Findings.
	 */
	private function count_site_ops_findings_by_boundary( array $findings, string $boundary ): int {
		$count = 0;
		foreach ( $findings as $finding ) {
			if ( is_array( $finding ) && $boundary === (string) ( $finding['write_boundary'] ?? '' ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * @param array<int,mixed>  $findings Findings.
	 * @param array<int,string> $categories Finding categories.
	 * @return array<int,array<string,mixed>>
	 */
	private function site_ops_findings_by_category( array $findings, array $categories ): array {
		$category_map = array_fill_keys( $categories, true );
		$matches      = array();
		foreach ( $findings as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}
			$category = (string) ( $finding['issue_type'] ?? $finding['category'] ?? '' );
			if ( isset( $category_map[ $category ] ) ) {
				$matches[] = $finding;
			}
		}
		return $matches;
	}

	/**
	 * @param array<int,mixed> $findings Findings.
	 */
	private function site_ops_analysis_focus_label( array $findings ): string {
		$priority_titles = $this->site_ops_priority_titles( $findings, 1 );
		if ( array() !== $priority_titles ) {
			return $priority_titles[0];
		}
		return __( 'the current evidence sample', 'npcink-workflow-toolbox' );
	}

	private function site_ops_local_report_status( int $finding_count, int $high_count ): string {
		if ( 0 === $finding_count ) {
			return __( 'No priority findings', 'npcink-workflow-toolbox' );
		}
		if ( $high_count > 0 ) {
			return __( 'Needs focused review', 'npcink-workflow-toolbox' );
		}
		return __( 'Review when planning', 'npcink-workflow-toolbox' );
	}

	/**
	 * @param array<int,mixed> $findings Findings.
	 */
	private function count_site_ops_findings_by_priority( array $findings, int $min, int $max ): int {
		$count = 0;
		foreach ( $findings as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}
			$score = (int) ( $finding['priority_score'] ?? 0 );
			if ( $score >= $min && $score < $max ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * @param array<int,mixed> $findings Findings.
	 * @return array<int,string>
	 */
	private function site_ops_priority_titles( array $findings, int $limit ): array {
		$titles = array();
		foreach ( $findings as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}
			$title = trim( $this->site_ops_finding_title( $finding, '' ) );
			if ( '' !== $title ) {
				$titles[] = $title;
			}
			if ( count( $titles ) >= $limit ) {
				break;
			}
		}
		return $titles;
	}

	private function site_ops_priority_label( int $score ): string {
		if ( $score >= 90 ) {
			return __( 'High', 'npcink-workflow-toolbox' );
		}
		if ( $score >= 75 ) {
			return __( 'Medium', 'npcink-workflow-toolbox' );
		}
		return __( 'Review', 'npcink-workflow-toolbox' );
	}

	private function site_ops_priority_action_label( int $score ): string {
		if ( $score >= 90 ) {
			return __( 'High priority', 'npcink-workflow-toolbox' );
		}
		if ( $score >= 75 ) {
			return __( 'Medium priority', 'npcink-workflow-toolbox' );
		}
		return __( 'Needs review', 'npcink-workflow-toolbox' );
	}

	private function site_ops_priority_bucket_label( string $bucket ): string {
		if ( 'high' === $bucket ) {
			return __( 'High', 'npcink-workflow-toolbox' );
		}
		if ( 'medium' === $bucket ) {
			return __( 'Medium', 'npcink-workflow-toolbox' );
		}
		return __( 'Review', 'npcink-workflow-toolbox' );
	}

	private function site_ops_boundary_label( string $boundary ): string {
		if ( 'core_handoff_candidate' === $boundary ) {
			return __( 'Needs review workflow', 'npcink-workflow-toolbox' );
		}
		if ( 'manual_review_only' === $boundary ) {
			return __( 'Manual check only', 'npcink-workflow-toolbox' );
		}
		return __( 'Advice only', 'npcink-workflow-toolbox' );
	}

	private function site_ops_boundary_guidance( string $boundary ): string {
		if ( 'core_handoff_candidate' === $boundary ) {
			return __( 'Changes must be reviewed before anything is written. This report only recommends.', 'npcink-workflow-toolbox' );
		}
		if ( 'manual_review_only' === $boundary ) {
			return __( 'Use this as a checklist. Nothing changes automatically.', 'npcink-workflow-toolbox' );
		}
		if ( 'blocked_until_cloud_ready' === $boundary ) {
			return __( 'Connect Cloud before expecting deeper AI ranking or trend detail.', 'npcink-workflow-toolbox' );
		}
		return __( 'Use this as decision support only. No WordPress data changes here.', 'npcink-workflow-toolbox' );
	}

	private function site_ops_owner_label( string $owner ): string {
		$owners = array(
			'human_editor' => __( 'Human editor', 'npcink-workflow-toolbox' ),
		);
		return $owners[ sanitize_key( $owner ) ] ?? '';
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 */
	private function site_ops_finding_id( array $finding ): string {
		$id = (string) ( $finding['id'] ?? $finding['finding_id'] ?? '' );
		return sanitize_key( $id );
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 */
	private function site_ops_finding_title( array $finding, string $fallback = '' ): string {
		$id     = $this->site_ops_finding_id( $finding );
		$titles = array(
			'stale_content_backlog'            => __( 'Old content refresh backlog', 'npcink-workflow-toolbox' ),
			'content_depth_and_linking_gap'    => __( 'Content depth and internal-link gaps', 'npcink-workflow-toolbox' ),
			'internal_link_health'             => __( 'Internal-link health needs review', 'npcink-workflow-toolbox' ),
			'metadata_review_backlog'          => __( 'Post metadata review backlog', 'npcink-workflow-toolbox' ),
			'comment_signal_review'            => __( 'Comment signal review', 'npcink-workflow-toolbox' ),
			'media_metadata_debt'              => __( 'Media metadata review backlog', 'npcink-workflow-toolbox' ),
			'taxonomy_structure_drift'         => __( 'Taxonomy structure review', 'npcink-workflow-toolbox' ),
			'site_context_incomplete'          => __( 'Site Context brief is incomplete', 'npcink-workflow-toolbox' ),
			'site_knowledge_cloud_unavailable' => __( 'Cloud Site Knowledge unavailable', 'npcink-workflow-toolbox' ),
		);
		if ( isset( $titles[ $id ] ) ) {
			return $titles[ $id ];
		}

		$title = trim( (string) ( $finding['title'] ?? $finding['finding_id'] ?? $finding['id'] ?? '' ) );
		if ( '' !== $title ) {
			return $this->site_ops_dynamic_label( $title );
		}

		return '' !== $fallback ? $fallback : __( 'Site analysis finding', 'npcink-workflow-toolbox' );
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 */
	private function site_ops_finding_evidence_summary( array $finding ): string {
		$id      = $this->site_ops_finding_id( $finding );
		$summary = trim( (string) ( $finding['evidence_summary'] ?? '' ) );
		if ( '' === $summary ) {
			return '';
		}

		if ( 'stale_content_backlog' === $id && preg_match( '/(\d+)\s+sampled public posts or pages have not been modified for 180\+ days;\s+(\d+)\s+still have approved comments\./i', $summary, $matches ) ) {
			return sprintf(
				/* translators: 1: stale item count, 2: stale item count with comments. */
				__( 'The current scan found %1$d posts/pages older than 180 days; %2$d still have approved comments.', 'npcink-workflow-toolbox' ),
				(int) $matches[1],
				(int) $matches[2]
			);
		}
		if ( 'content_depth_and_linking_gap' === $id && preg_match( '/(\d+)\s+sampled items are short;\s+(\d+)\s+have no recorded internal links\./i', $summary, $matches ) ) {
			return sprintf(
				/* translators: 1: thin item count, 2: missing internal link count. */
				__( 'The current scan found %1$d short items; %2$d have no recorded internal links.', 'npcink-workflow-toolbox' ),
				(int) $matches[1],
				(int) $matches[2]
			);
		}
		if ( 'metadata_review_backlog' === $id && preg_match( '/(\d+)\s+sampled items need excerpt or meta-description review;\s+(\d+)\s+need category or tag review\./i', $summary, $matches ) ) {
			return sprintf(
				/* translators: 1: missing meta count, 2: missing taxonomy count. */
				__( 'The current scan found %1$d items needing excerpt or meta-description review; %2$d need category or tag review.', 'npcink-workflow-toolbox' ),
				(int) $matches[1],
				(int) $matches[2]
			);
		}
		if ( 'comment_signal_review' === $id && preg_match( '/The approved comment sample includes\s+(\d+)\s+question-like comments and\s+(\d+)\s+longer comments;\s+(\d+)\s+comments are pending moderation\./i', $summary, $matches ) ) {
			return sprintf(
				/* translators: 1: question-like comments, 2: long comments, 3: pending comments. */
				__( 'The approved comment sample includes %1$d question-like comments and %2$d longer comments; %3$d comments are pending moderation.', 'npcink-workflow-toolbox' ),
				(int) $matches[1],
				(int) $matches[2],
				(int) $matches[3]
			);
		}
		if ( 'media_metadata_debt' === $id && preg_match( '/(\d+)\s+sampled attachments lack ALT text,\s+(\d+)\s+lack captions,\s+and sampled posts reference\s+(\d+)\s+image ALT gaps\./i', $summary, $matches ) ) {
			return sprintf(
				/* translators: 1: missing attachment alt count, 2: missing caption count, 3: referenced image alt gaps. */
				__( 'The current scan found %1$d media items without ALT text, %2$d without captions, and %3$d referenced image ALT gaps.', 'npcink-workflow-toolbox' ),
				(int) $matches[1],
				(int) $matches[2],
				(int) $matches[3]
			);
		}
		if ( 'taxonomy_structure_drift' === $id && preg_match( '/(\d+)\s+category\/tag terms are empty and\s+(\d+)\s+are used once in the sampled taxonomy summary\./i', $summary, $matches ) ) {
			return sprintf(
				/* translators: 1: empty term count, 2: low-use term count. */
				__( 'The current scan found %1$d empty category/tag terms and %2$d terms used only once.', 'npcink-workflow-toolbox' ),
				(int) $matches[1],
				(int) $matches[2]
			);
		}

		return $this->site_ops_dynamic_label( $summary );
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 */
	private function site_ops_finding_impact( array $finding ): string {
		$id      = $this->site_ops_finding_id( $finding );
		$impacts = array(
			'stale_content_backlog'            => __( 'Older but still active content can reduce reader trust and search freshness.', 'npcink-workflow-toolbox' ),
			'content_depth_and_linking_gap'    => __( 'Thin pages and missing internal paths make it harder for readers and AI systems to understand the site map.', 'npcink-workflow-toolbox' ),
			'internal_link_health'             => __( 'Weak internal paths can make related content harder for readers and editors to discover.', 'npcink-workflow-toolbox' ),
			'metadata_review_backlog'          => __( 'Weak metadata reduces snippet quality and makes suggestion workflows less grounded.', 'npcink-workflow-toolbox' ),
			'comment_signal_review'            => __( 'Comment patterns can reveal missing FAQ, troubleshooting, or follow-up content needs.', 'npcink-workflow-toolbox' ),
			'media_metadata_debt'              => __( 'Image metadata affects accessibility, editorial reuse, and media search quality.', 'npcink-workflow-toolbox' ),
			'taxonomy_structure_drift'         => __( 'Sparse vocabulary can fragment content discovery and weaken recommendation quality.', 'npcink-workflow-toolbox' ),
			'site_context_incomplete'          => __( 'Weak site context makes downstream SEO/AEO/GEO and content support suggestions less consistent.', 'npcink-workflow-toolbox' ),
			'site_knowledge_cloud_unavailable' => __( 'Without Cloud, recommendations stay local and cannot use semantic related-content evidence.', 'npcink-workflow-toolbox' ),
		);
		if ( isset( $impacts[ $id ] ) ) {
			return $impacts[ $id ];
		}

		return $this->site_ops_dynamic_label( (string) ( $finding['impact'] ?? '' ) );
	}

	/**
	 * @param array<string,mixed> $finding Finding payload.
	 */
	private function site_ops_finding_recommended_action( array $finding ): string {
		$id      = $this->site_ops_finding_id( $finding );
		$actions = array(
			'stale_content_backlog'            => __( 'Open the oldest active items first, then write refresh notes before choosing any review workflow.', 'npcink-workflow-toolbox' ),
			'content_depth_and_linking_gap'    => __( 'Prioritize internal-link review and content-depth review before creating new articles on the same topics.', 'npcink-workflow-toolbox' ),
			'internal_link_health'             => __( 'Open an affected post, review internal-link candidates, and place only contextually useful links manually.', 'npcink-workflow-toolbox' ),
			'metadata_review_backlog'          => __( 'Review one post at a time in the editor, then send accepted values through the review workflow.', 'npcink-workflow-toolbox' ),
			'comment_signal_review'            => __( 'Review high-signal public comments manually; convert repeated needs into FAQ or article-refresh notes.', 'npcink-workflow-toolbox' ),
			'media_metadata_debt'              => __( 'Start with a media ALT/caption review set; do not update media metadata until a governed path is selected.', 'npcink-workflow-toolbox' ),
			'taxonomy_structure_drift'         => __( 'Review taxonomy consolidation separately; do not create, merge, or assign terms from this panel.', 'npcink-workflow-toolbox' ),
			'site_context_incomplete'          => __( 'Fill the Site Context brief before relying on repeated AI recommendations.', 'npcink-workflow-toolbox' ),
			'site_knowledge_cloud_unavailable' => __( 'Connect or verify Cloud Addon before expecting deeper semantic analysis.', 'npcink-workflow-toolbox' ),
		);
		if ( isset( $actions[ $id ] ) ) {
			return $actions[ $id ];
		}

		return $this->site_ops_dynamic_label( (string) ( $finding['recommended_action'] ?? '' ) );
	}

	private function site_ops_dynamic_label( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^Cloud runtime\/detail ranked (\d+) findings across (.+); review the first item before expanding work\.$/i', $value, $matches ) ) {
			return sprintf(
				/* translators: 1: finding count, 2: dimension list. */
				__( 'Cloud runtime/detail ranked %1$d findings across %2$s; review the first item before expanding work.', 'npcink-workflow-toolbox' ),
				(int) $matches[1],
				$this->site_ops_dynamic_dimension_list_label( (string) $matches[2] )
			);
		}
		if ( preg_match( '/^([a-z_]+) has (\d+) ranked finding\(s\) in the current request\.$/i', $value, $matches ) ) {
			return sprintf(
				/* translators: 1: dimension label, 2: finding count. */
				__( '%1$s has %2$d ranked findings in the current request.', 'npcink-workflow-toolbox' ),
				$this->site_ops_dynamic_label( (string) $matches[1] ),
				(int) $matches[2]
			);
		}
		if ( preg_match( '/^([a-z_]+) has aggregate signals but no ranked local finding\.$/i', $value, $matches ) ) {
			return sprintf(
				/* translators: 1: dimension label. */
				__( '%s has aggregate signals but no ranked local finding.', 'npcink-workflow-toolbox' ),
				$this->site_ops_dynamic_label( (string) $matches[1] )
			);
		}
		if ( preg_match( '/^([a-z_]+) has no priority signal in the current aggregate sample\.$/i', $value, $matches ) ) {
			return sprintf(
				/* translators: 1: dimension label. */
				__( '%s has no priority signal in the current aggregate sample.', 'npcink-workflow-toolbox' ),
				$this->site_ops_dynamic_label( (string) $matches[1] )
			);
		}

		$labels = array(
			'stale_content_backlog'                      => __( 'Old content refresh backlog', 'npcink-workflow-toolbox' ),
			'content_depth_and_linking_gap'              => __( 'Content depth and internal-link gaps', 'npcink-workflow-toolbox' ),
			'metadata_review_backlog'                    => __( 'Post metadata review backlog', 'npcink-workflow-toolbox' ),
			'comment_signal_review'                      => __( 'Comment signal review', 'npcink-workflow-toolbox' ),
			'media_metadata_debt'                        => __( 'Media metadata review backlog', 'npcink-workflow-toolbox' ),
			'taxonomy_structure_drift'                   => __( 'Taxonomy structure review', 'npcink-workflow-toolbox' ),
			'site_context_incomplete'                    => __( 'Site Context brief is incomplete', 'npcink-workflow-toolbox' ),
			'site_knowledge_cloud_unavailable'           => __( 'Cloud Site Knowledge unavailable', 'npcink-workflow-toolbox' ),
			'cloud_semantic_analysis'                    => __( 'Cloud semantic analysis', 'npcink-workflow-toolbox' ),
			'cloud_runtime_unavailable'                  => __( 'Cloud runtime is unavailable', 'npcink-workflow-toolbox' ),
			'connect_or_verify_cloud_addon'              => __( 'Connect or verify Cloud Addon', 'npcink-workflow-toolbox' ),
			'content'                                    => __( 'Content', 'npcink-workflow-toolbox' ),
			'media'                                      => __( 'Media', 'npcink-workflow-toolbox' ),
			'comments'                                   => __( 'Comments', 'npcink-workflow-toolbox' ),
			'structure'                                  => __( 'Structure', 'npcink-workflow-toolbox' ),
			'high'                                       => __( 'High', 'npcink-workflow-toolbox' ),
			'medium'                                     => __( 'Medium', 'npcink-workflow-toolbox' ),
			'low'                                        => __( 'Low', 'npcink-workflow-toolbox' ),
			'review'                                     => __( 'Review', 'npcink-workflow-toolbox' ),
			'runtime_detail'                             => __( 'Runtime/detail', 'npcink-workflow-toolbox' ),
			'collect_stronger_site_context'              => __( 'Collect stronger Site Context', 'npcink-workflow-toolbox' ),
			'blocked_until_operator_review'              => __( 'Blocked until operator review', 'npcink-workflow-toolbox' ),
			'ready_for_operator_prioritization'          => __( 'Ready for operator prioritization', 'npcink-workflow-toolbox' ),
			'no_priority_findings'                       => __( 'No priority findings', 'npcink-workflow-toolbox' ),
			'clear_blocked_items_then_repeat_cloud_analysis' => __( 'Clear blocked items, then repeat Cloud detail', 'npcink-workflow-toolbox' ),
			'review_top_ranked_finding_then_choose_manual_or_core_handoff' => __( 'Review the top ranked finding, then choose manual review or Core handoff', 'npcink-workflow-toolbox' ),
			'keep_as_current_snapshot_or_refresh_after_site_changes' => __( 'Keep this as the current snapshot, or refresh after site changes', 'npcink-workflow-toolbox' ),
			'content_quality_and_discoverability'        => __( 'Content quality and discoverability', 'npcink-workflow-toolbox' ),
			'media_accessibility_and_reuse'              => __( 'Media accessibility and reuse', 'npcink-workflow-toolbox' ),
			'audience_demand_signal'                     => __( 'Audience demand signal', 'npcink-workflow-toolbox' ),
			'site_structure_and_context'                 => __( 'Site structure and context', 'npcink-workflow-toolbox' ),
			'general_site_review'                        => __( 'General site review', 'npcink-workflow-toolbox' ),
			'content_refresh_trend'                      => __( 'Content refresh trend', 'npcink-workflow-toolbox' ),
			'comment_question_trend'                     => __( 'Comment question trend', 'npcink-workflow-toolbox' ),
			'media_metadata_trend'                       => __( 'Media metadata trend', 'npcink-workflow-toolbox' ),
			'taxonomy_drift_trend'                       => __( 'Taxonomy drift trend', 'npcink-workflow-toolbox' ),
			'insufficient_signal'                        => __( 'Insufficient signal', 'npcink-workflow-toolbox' ),
			'compare_stale_items_with_recent_comment_activity' => __( 'Compare stale items with recent comment activity', 'npcink-workflow-toolbox' ),
			'group_repeated_comment_questions_without_raw_text' => __( 'Group repeated comment questions without raw text', 'npcink-workflow-toolbox' ),
			'sample_media_alt_and_caption_review_set'    => __( 'Sample a media ALT and caption review set', 'npcink-workflow-toolbox' ),
			'review_empty_and_low_use_terms'             => __( 'Review empty and low-use terms', 'npcink-workflow-toolbox' ),
			'complete_site_context_and_repeat_local_preview' => __( 'Complete Site Context and repeat the local preview', 'npcink-workflow-toolbox' ),
			'repeat_cloud_detail_after_next_local_scan'  => __( 'Repeat Cloud detail after the next local scan', 'npcink-workflow-toolbox' ),
			'Old content needs a refresh queue'          => __( 'Old content refresh backlog', 'npcink-workflow-toolbox' ),
			'Some content lacks depth or internal paths' => __( 'Content depth and internal-link gaps', 'npcink-workflow-toolbox' ),
			'Metadata review backlog is visible'         => __( 'Post metadata review backlog', 'npcink-workflow-toolbox' ),
			'Comments contain support and follow-up signals' => __( 'Comment signal review', 'npcink-workflow-toolbox' ),
			'Media metadata needs review'                => __( 'Media metadata review backlog', 'npcink-workflow-toolbox' ),
			'Taxonomy structure may need cleanup'        => __( 'Taxonomy structure review', 'npcink-workflow-toolbox' ),
			'Site Context needs a stronger brief'        => __( 'Site Context brief is incomplete', 'npcink-workflow-toolbox' ),
			'Cloud Site Knowledge is not available'      => __( 'Cloud Site Knowledge unavailable', 'npcink-workflow-toolbox' ),
			'Review blockers before turning findings into an action plan.' => __( 'Review blockers before turning findings into an action plan.', 'npcink-workflow-toolbox' ),
			'Prioritize the strongest full-site signals before creating new work.' => __( 'Use the strongest site check signals to choose the next fixed workflow.', 'npcink-workflow-toolbox' ),
			'No priority full-site findings were detected in the current aggregate sample.' => __( 'No priority site check findings were detected in the current aggregate sample.', 'npcink-workflow-toolbox' ),
			'The analysis found prerequisites that should be cleared before repeated review.' => __( 'The analysis found prerequisites that should be cleared before repeated review.', 'npcink-workflow-toolbox' ),
			'The current aggregate sample is reviewable, but it did not produce a priority queue.' => __( 'The current aggregate sample is reviewable, but it did not produce a priority queue.', 'npcink-workflow-toolbox' ),
			'Media metadata affects accessibility, reuse, and evidence quality.' => __( 'Media metadata affects accessibility, reuse, and evidence quality.', 'npcink-workflow-toolbox' ),
			'Approved comment signals can reveal unanswered audience needs.' => __( 'Approved comment signals can reveal unanswered audience needs.', 'npcink-workflow-toolbox' ),
			'Older active content should be refreshed before expanding similar work.' => __( 'Older active content should be refreshed before expanding similar work.', 'npcink-workflow-toolbox' ),
			'This finding is ranked from aggregate local evidence and operator review value.' => __( 'This finding is ranked from aggregate local evidence and operator review value.', 'npcink-workflow-toolbox' ),
			'Refresh planning should start with active stale pages.' => __( 'Refresh planning should start with active stale pages.', 'npcink-workflow-toolbox' ),
			'Repeated questions can become FAQ or article-refresh work.' => __( 'Repeated questions can become FAQ or article-refresh work.', 'npcink-workflow-toolbox' ),
			'Accessibility and media search quality may be weaker.' => __( 'Accessibility and media search quality may be weaker.', 'npcink-workflow-toolbox' ),
			'Sparse vocabulary can fragment discovery and recommendations.' => __( 'Sparse vocabulary can fragment discovery and recommendations.', 'npcink-workflow-toolbox' ),
			'Review the aggregate signal before creating new work.' => __( 'Review the aggregate signal before creating new work.', 'npcink-workflow-toolbox' ),
			'No aggregate signal was strong enough for trend explanation.' => __( 'No aggregate signal was strong enough for trend explanation.', 'npcink-workflow-toolbox' ),
			'Run the local scan after more public content evidence is available.' => __( 'Run the local scan after more public content evidence is available.', 'npcink-workflow-toolbox' ),
			'Review the oldest active items first, then prepare refresh notes or a Core-governed update plan.' => __( 'Review the oldest active items first, then prepare refresh notes or a Core-governed update plan.', 'npcink-workflow-toolbox' ),
			'Start with a media ALT/caption review and make metadata visible before broader adoption.' => __( 'Start with a media ALT/caption review set; do not update media metadata until a governed path is selected.', 'npcink-workflow-toolbox' ),
		);
		if ( isset( $labels[ $value ] ) ) {
			return $labels[ $value ];
		}

		$key = sanitize_key( $value );
		if ( isset( $labels[ $key ] ) ) {
			return $labels[ $key ];
		}

		return $value;
	}

	private function site_ops_dynamic_dimension_list_label( string $value ): string {
		$parts  = preg_split( '/\s*,\s*/', trim( $value ) );
		$labels = array();
		foreach ( is_array( $parts ) ? $parts : array() as $part ) {
			$label = $this->site_ops_dynamic_label( (string) $part );
			if ( '' !== $label ) {
				$labels[] = $label;
			}
		}
		return implode( ' / ', $labels );
	}

	/**
	 * @param mixed $cloud_analysis Cloud analysis result or WP_Error.
	 */
	protected function render_site_ops_cloud_analysis_result( $cloud_analysis ): void {
		if ( null === $cloud_analysis ) {
			return;
		}
		if ( is_wp_error( $cloud_analysis ) ) {
			?>
			<div class="npcink-toolbox__result-notice is-warning"><?php echo esc_html( $cloud_analysis->get_error_message() ); ?></div>
			<?php
			return;
		}
		if ( ! is_array( $cloud_analysis ) ) {
			return;
		}

		$result                   = is_array( $cloud_analysis['result'] ?? null ) ? $cloud_analysis['result'] : array();
		$executive_summary        = is_array( $result['executive_summary'] ?? null ) ? $result['executive_summary'] : array();
		$priority_queue           = is_array( $result['priority_queue'] ?? null ) ? array_slice( $result['priority_queue'], 0, 5 ) : array();
		$dimension_summaries      = is_array( $result['dimension_summaries'] ?? null ) ? array_slice( $result['dimension_summaries'], 0, 4 ) : array();
		$semantic_ranked_findings = is_array( $result['semantic_ranked_findings'] ?? null ) ? array_slice( $result['semantic_ranked_findings'], 0, 5 ) : array();
		$trend_notes              = is_array( $result['trend_notes'] ?? null ) ? array_slice( $result['trend_notes'], 0, 5 ) : array();
		$trend_explanations       = is_array( $result['trend_explanations'] ?? null ) ? array_slice( $result['trend_explanations'], 0, 5 ) : array();
		$analysis_closure         = is_array( $result['analysis_closure'] ?? null ) ? $result['analysis_closure'] : array();
		$blocked_items            = is_array( $result['blocked_items'] ?? null ) ? array_slice( $result['blocked_items'], 0, 5 ) : array();
		$next_actions             = is_array( $result['operator_next_actions'] ?? null ) ? array_slice( $result['operator_next_actions'], 0, 5 ) : array();
		$handoff_candidates       = is_array( $result['core_handoff_candidates'] ?? null ) ? array_slice( $result['core_handoff_candidates'], 0, 5 ) : array();
		$confidence               = is_array( $result['confidence'] ?? null ) ? $result['confidence'] : array();
		$cloud_run                = is_array( $cloud_analysis['cloud_run'] ?? null ) ? $cloud_analysis['cloud_run'] : array();
		$cloud_error              = is_array( $cloud_analysis['cloud_error'] ?? null ) ? $cloud_analysis['cloud_error'] : array();
		$status                   = sanitize_key( (string) ( $cloud_run['status'] ?? $cloud_analysis['status'] ?? 'submitted' ) );
		$error_code               = sanitize_key( (string) ( $cloud_error['error_code'] ?? '' ) );
		$error_message            = (string) ( $cloud_error['error_message'] ?? '' );
		$confidence_level         = sanitize_key( (string) ( $confidence['level'] ?? '' ) );
		$is_failed                = in_array( $status, array( 'failed', 'error' ), true ) || '' !== $error_code;
		$cloud_focus              = array();
		foreach ( $priority_queue as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$label = $this->site_ops_finding_title( $item, '' );
			if ( '' !== $label ) {
				$cloud_focus[] = $label;
			}
			if ( count( $cloud_focus ) >= 3 ) {
				break;
			}
		}
		?>
		<section class="npcink-toolbox__card npcink-toolbox__insight-cloud-result">
			<div class="npcink-toolbox__section-heading">
				<div>
					<h3><?php esc_html_e( 'Cloud detail result', 'npcink-workflow-toolbox' ); ?></h3>
					<p><?php esc_html_e( 'Cloud adds runtime/detail ranking only. Review results locally before any Core handoff.', 'npcink-workflow-toolbox' ); ?></p>
				</div>
				<span class="npcink-toolbox__pill"><?php echo esc_html( '' !== $status ? $status : 'submitted' ); ?></span>
			</div>

			<div class="npcink-toolbox__ops-summary-bar">
				<div>
					<?php /* translators: %d: number of Cloud-ranked priority items. */ ?>
					<strong><?php printf( esc_html__( 'Cloud returned %d priority items', 'npcink-workflow-toolbox' ), (int) count( $priority_queue ) ); ?></strong>
					<span><?php esc_html_e( 'Runtime/detail output is review guidance only and does not create Core proposals or WordPress writes.', 'npcink-workflow-toolbox' ); ?></span>
				</div>
				<div class="npcink-toolbox__ops-scope">
					<?php /* translators: %s: Cloud runtime run identifier, or not returned. */ ?>
					<span><?php printf( esc_html__( 'Run: %s', 'npcink-workflow-toolbox' ), esc_html( (string) ( $cloud_run['run_id'] ?? __( 'not returned', 'npcink-workflow-toolbox' ) ) ) ); ?></span>
					<?php /* translators: %s: Cloud runtime confidence level, or not reported. */ ?>
					<span><?php printf( esc_html__( 'Confidence: %s', 'npcink-workflow-toolbox' ), esc_html( '' !== $confidence_level ? $confidence_level : __( 'not reported', 'npcink-workflow-toolbox' ) ) ); ?></span>
					<span><?php esc_html_e( 'Review: local operator required', 'npcink-workflow-toolbox' ); ?></span>
				</div>
			</div>
			<?php if ( array() !== $executive_summary ) : ?>
				<div class="npcink-toolbox__ops-summary-bar" aria-label="<?php esc_attr_e( 'Cloud executive summary', 'npcink-workflow-toolbox' ); ?>">
					<div>
						<strong><?php esc_html_e( 'Cloud executive summary', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $executive_summary['headline'] ?? __( 'Cloud detail is ready for operator review.', 'npcink-workflow-toolbox' ) ) ) ); ?></span>
						<?php if ( '' !== (string) ( $executive_summary['summary'] ?? '' ) ) : ?>
							<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) $executive_summary['summary'] ) ); ?></span>
						<?php endif; ?>
					</div>
					<div class="npcink-toolbox__ops-scope">
						<?php if ( '' !== (string) ( $executive_summary['primary_focus'] ?? '' ) ) : ?>
							<?php /* translators: %s: Cloud-reported primary focus label. */ ?>
							<span><?php printf( esc_html__( 'Focus: %s', 'npcink-workflow-toolbox' ), esc_html( $this->site_ops_dynamic_label( (string) $executive_summary['primary_focus'] ) ) ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== (string) ( $analysis_closure['loop_status'] ?? '' ) ) : ?>
							<?php /* translators: %s: Cloud-reported analysis loop status. */ ?>
							<span><?php printf( esc_html__( 'Loop: %s', 'npcink-workflow-toolbox' ), esc_html( $this->site_ops_dynamic_label( (string) $analysis_closure['loop_status'] ) ) ); ?></span>
						<?php endif; ?>
						<span><?php esc_html_e( 'Cloud role: runtime/detail', 'npcink-workflow-toolbox' ); ?></span>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( array() !== $dimension_summaries ) : ?>
				<div class="npcink-toolbox__ops-detail-grid" aria-label="<?php esc_attr_e( 'Cloud dimension summaries', 'npcink-workflow-toolbox' ); ?>">
					<?php foreach ( $dimension_summaries as $dimension ) : ?>
						<?php
						if ( ! is_array( $dimension ) ) {
							continue; }
						?>
						<div>
							<strong><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $dimension['dimension'] ?? __( 'Analysis area', 'npcink-workflow-toolbox' ) ) ) ); ?></strong>
							<?php /* translators: 1: Cloud-reported priority label, 2: number of findings in this analysis dimension. */ ?>
							<span><?php printf( esc_html__( '%1$s priority, %2$d findings', 'npcink-workflow-toolbox' ), esc_html( $this->site_ops_dynamic_label( (string) ( $dimension['priority'] ?? 'review' ) ) ), (int) ( $dimension['finding_count'] ?? 0 ) ); ?></span>
							<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $dimension['summary'] ?? '' ) ) ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $is_failed ) : ?>
				<div class="npcink-toolbox__result-notice is-error">
					<strong><?php esc_html_e( 'Cloud detail failed in runtime/detail.', 'npcink-workflow-toolbox' ); ?></strong>
					<?php if ( '' !== $error_code || '' !== $error_message ) : ?>
						<span><?php echo esc_html( trim( $error_code . ( '' !== $error_message ? ': ' . $error_message : '' ) ) ); ?></span>
					<?php endif; ?>
					<span><?php esc_html_e( 'Toolbox did not retry locally, create a local run table, create Core proposals, or write WordPress data.', 'npcink-workflow-toolbox' ); ?></span>
				</div>
			<?php elseif ( 'low' === $confidence_level ) : ?>
				<div class="npcink-toolbox__result-notice is-warning">
					<strong><?php esc_html_e( 'Cloud returned low-confidence detail.', 'npcink-workflow-toolbox' ); ?></strong>
					<span><?php esc_html_e( 'Review the blockers and Site Context before treating the result as an operations priority list.', 'npcink-workflow-toolbox' ); ?></span>
				</div>
			<?php elseif ( array() === $priority_queue && array() === $trend_notes ) : ?>
				<div class="npcink-toolbox__result-notice">
					<?php esc_html_e( 'Cloud returned no priority queue or trend notes for this bounded request.', 'npcink-workflow-toolbox' ); ?>
				</div>
			<?php endif; ?>
			<?php if ( array() !== $blocked_items || array() !== $next_actions || array() !== $handoff_candidates ) : ?>
				<div class="npcink-toolbox__ops-detail-grid" aria-label="<?php esc_attr_e( 'Cloud follow-up summary', 'npcink-workflow-toolbox' ); ?>">
					<div>
						<strong><?php esc_html_e( 'Blockers', 'npcink-workflow-toolbox' ); ?></strong>
						<span>
							<?php
							if ( array() === $blocked_items ) {
								esc_html_e( 'No Cloud blockers reported.', 'npcink-workflow-toolbox' );
							} else {
								$first_blocker = is_array( $blocked_items[0] ?? null ) ? $blocked_items[0] : array();
								printf(
									/* translators: 1: number of Cloud blockers, 2: first blocker label. */
									esc_html__( '%1$d reported; first: %2$s.', 'npcink-workflow-toolbox' ),
									(int) count( $blocked_items ),
									esc_html( $this->site_ops_dynamic_label( (string) ( $first_blocker['reason'] ?? $first_blocker['id'] ?? '' ) ) )
								);
							}
							?>
						</span>
					</div>
					<div>
						<strong><?php esc_html_e( 'Suggested next path', 'npcink-workflow-toolbox' ); ?></strong>
						<span>
							<?php
							if ( array() === $next_actions ) {
								esc_html_e( 'Review the top priority queue item locally.', 'npcink-workflow-toolbox' );
							} else {
								$first_action = is_array( $next_actions[0] ?? null ) ? $next_actions[0] : array();
								echo esc_html( $this->site_ops_dynamic_label( (string) ( $first_action['label'] ?? $first_action['target'] ?? $first_action['id'] ?? '' ) ) );
							}
							?>
						</span>
					</div>
					<div>
						<strong><?php esc_html_e( 'Core handoff candidates', 'npcink-workflow-toolbox' ); ?></strong>
						<span>
							<?php
							printf(
								/* translators: %d: number of Core handoff planning hints. */
								esc_html__( '%d planning hints; proposal creation remains outside this report.', 'npcink-workflow-toolbox' ),
								(int) count( $handoff_candidates )
							);
							?>
						</span>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( array() !== $cloud_focus ) : ?>
				<div class="npcink-toolbox__ops-focus">
					<strong><?php esc_html_e( 'Cloud focus', 'npcink-workflow-toolbox' ); ?></strong>
					<span><?php echo esc_html( implode( ' / ', $cloud_focus ) ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( array() !== $priority_queue ) : ?>
				<div class="npcink-toolbox__ops-priority-list">
					<?php foreach ( $priority_queue as $item ) : ?>
						<?php
						if ( ! is_array( $item ) ) {
							continue; }
						?>
						<?php
						$title   = $this->site_ops_finding_title( $item, __( 'Cloud priority', 'npcink-workflow-toolbox' ) );
						$summary = $this->site_ops_finding_evidence_summary( $item );
						$action  = $this->site_ops_finding_recommended_action( $item );
						?>
						<article class="npcink-toolbox__ops-priority-row">
							<div class="npcink-toolbox__ops-priority-main">
								<span class="npcink-toolbox__priority-label"><?php echo esc_html( $this->site_ops_priority_label( (int) ( $item['cloud_priority_score'] ?? 0 ) ) ); ?></span>
								<div>
									<h3><?php echo esc_html( $title ); ?></h3>
									<p><?php echo esc_html( $summary ); ?></p>
								</div>
								<span class="npcink-toolbox__priority-score"><?php echo esc_html( (string) (int) ( $item['cloud_priority_score'] ?? 0 ) ); ?></span>
							</div>
							<div class="npcink-toolbox__ops-action-line">
								<strong><?php esc_html_e( 'Next', 'npcink-workflow-toolbox' ); ?></strong>
								<span><?php echo esc_html( $action ); ?></span>
								<em><?php esc_html_e( 'Cloud-ranked suggestion', 'npcink-workflow-toolbox' ); ?></em>
								<span><?php echo esc_html( $this->site_ops_boundary_guidance( (string) ( $item['write_boundary'] ?? 'suggestion_only' ) ) ); ?></span>
							</div>
					</article>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
			<?php if ( array() !== $semantic_ranked_findings ) : ?>
				<details class="npcink-toolbox__result-details">
					<summary><?php esc_html_e( 'Semantic ranking detail', 'npcink-workflow-toolbox' ); ?></summary>
					<ul class="npcink-toolbox__usage-list">
						<?php foreach ( $semantic_ranked_findings as $item ) : ?>
							<?php
							if ( ! is_array( $item ) ) {
								continue; }
							?>
							<li>
								<strong><?php echo esc_html( $this->site_ops_finding_title( $item, __( 'Semantic finding', 'npcink-workflow-toolbox' ) ) ); ?></strong>
								<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $item['semantic_cluster'] ?? '' ) ) ); ?></span>
								<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $item['reason'] ?? '' ) ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>
			<?php if ( array() !== $trend_explanations ) : ?>
				<details class="npcink-toolbox__result-details">
					<summary><?php esc_html_e( 'Trend explanations', 'npcink-workflow-toolbox' ); ?></summary>
					<ul class="npcink-toolbox__usage-list">
							<?php foreach ( $trend_explanations as $item ) : ?>
								<?php
								if ( ! is_array( $item ) ) {
									continue; }
								?>
								<li>
									<strong><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $item['id'] ?? __( 'Trend explanation', 'npcink-workflow-toolbox' ) ) ) ); ?></strong>
									<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $item['operator_impact'] ?? $item['summary'] ?? '' ) ) ); ?></span>
									<?php if ( '' !== (string) ( $item['next_check'] ?? '' ) ) : ?>
										<?php /* translators: %s: suggested next trend check. */ ?>
										<span><?php printf( esc_html__( 'Next check: %s', 'npcink-workflow-toolbox' ), esc_html( $this->site_ops_dynamic_label( (string) $item['next_check'] ) ) ); ?></span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>
			<?php if ( array() !== $trend_notes ) : ?>
				<details class="npcink-toolbox__result-details">
					<summary><?php esc_html_e( 'Trend notes', 'npcink-workflow-toolbox' ); ?></summary>
					<ul class="npcink-toolbox__usage-list">
						<?php foreach ( $trend_notes as $note ) : ?>
							<?php
							if ( ! is_array( $note ) ) {
								continue; }
							?>
							<li>
								<strong><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $note['id'] ?? __( 'Trend note', 'npcink-workflow-toolbox' ) ) ) ); ?></strong>
								<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $note['summary'] ?? '' ) ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>
			<?php if ( array() !== $blocked_items || array() !== $next_actions || array() !== $handoff_candidates ) : ?>
				<details class="npcink-toolbox__result-details">
					<summary><?php esc_html_e( 'Blocked items, next actions, and Core handoff candidates', 'npcink-workflow-toolbox' ); ?></summary>
					<div class="npcink-toolbox__ops-detail-grid" aria-label="<?php esc_attr_e( 'Cloud detail review', 'npcink-workflow-toolbox' ); ?>">
						<?php if ( array() !== $blocked_items ) : ?>
							<div>
								<strong><?php esc_html_e( 'Blocked items', 'npcink-workflow-toolbox' ); ?></strong>
								<ul class="npcink-toolbox__usage-list">
									<?php foreach ( $blocked_items as $item ) : ?>
										<?php
										if ( ! is_array( $item ) ) {
											continue; }
										?>
										<li>
											<strong><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $item['id'] ?? __( 'Blocked item', 'npcink-workflow-toolbox' ) ) ) ); ?></strong>
											<span>
												<?php
												$reason = $this->site_ops_dynamic_label( (string) ( $item['reason'] ?? '' ) );
												$next   = isset( $item['next'] ) ? $this->site_ops_dynamic_label( (string) $item['next'] ) : '';
												echo esc_html( '' !== $next ? $reason . ' - ' . $next : $reason );
												?>
											</span>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
						<?php if ( array() !== $next_actions ) : ?>
							<div>
								<strong><?php esc_html_e( 'Operator next actions', 'npcink-workflow-toolbox' ); ?></strong>
								<ul class="npcink-toolbox__usage-list">
									<?php foreach ( $next_actions as $action ) : ?>
										<?php
										if ( ! is_array( $action ) ) {
											continue; }
										?>
										<li>
											<strong><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $action['id'] ?? __( 'Review action', 'npcink-workflow-toolbox' ) ) ) ); ?></strong>
											<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $action['label'] ?? $action['target'] ?? '' ) ) ); ?></span>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
						<?php if ( array() !== $handoff_candidates ) : ?>
							<div>
								<strong><?php esc_html_e( 'Core handoff candidates', 'npcink-workflow-toolbox' ); ?></strong>
								<ul class="npcink-toolbox__usage-list">
									<?php foreach ( $handoff_candidates as $candidate ) : ?>
										<?php
										if ( ! is_array( $candidate ) ) {
											continue; }
										?>
										<li>
											<strong><?php echo esc_html( $this->site_ops_finding_title( $candidate, __( 'Handoff candidate', 'npcink-workflow-toolbox' ) ) ); ?></strong>
											<span><?php esc_html_e( 'Planning hint only; proposal_ready=false and Core still owns review.', 'npcink-workflow-toolbox' ); ?></span>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
					</div>
				</details>
			<?php endif; ?>
			<?php if ( array() !== $analysis_closure ) : ?>
				<details class="npcink-toolbox__result-details">
					<summary><?php esc_html_e( 'Analysis closure', 'npcink-workflow-toolbox' ); ?></summary>
					<div class="npcink-toolbox__ops-detail-grid" aria-label="<?php esc_attr_e( 'Cloud detail closure', 'npcink-workflow-toolbox' ); ?>">
						<div>
							<strong><?php esc_html_e( 'Loop status', 'npcink-workflow-toolbox' ); ?></strong>
							<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $analysis_closure['loop_status'] ?? '' ) ) ); ?></span>
						</div>
						<div>
							<strong><?php esc_html_e( 'Next step', 'npcink-workflow-toolbox' ); ?></strong>
							<span><?php echo esc_html( $this->site_ops_dynamic_label( (string) ( $analysis_closure['next_step'] ?? '' ) ) ); ?></span>
						</div>
						<div>
							<strong><?php esc_html_e( 'Boundary', 'npcink-workflow-toolbox' ); ?></strong>
							<span><?php esc_html_e( 'Cloud detail only; Core and WordPress writes stay local-governed.', 'npcink-workflow-toolbox' ); ?></span>
						</div>
					</div>
				</details>
			<?php endif; ?>
			<details class="npcink-toolbox__result-details">
				<summary><?php esc_html_e( 'Advanced: Copy Cloud result JSON', 'npcink-workflow-toolbox' ); ?></summary>
				<p class="description"><?php esc_html_e( 'This result is suggestion-only and does not create Core proposals or WordPress writes.', 'npcink-workflow-toolbox' ); ?></p>
				<textarea class="large-text code" rows="12" readonly><?php echo esc_textarea( (string) wp_json_encode( $cloud_analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
			</details>
		</section>
		<?php
	}

	protected function content_context_ready( array $content_context ): bool {
		$required_fields = array( 'site_positioning', 'target_audience', 'brand_voice', 'primary_keywords' );

		foreach ( $required_fields as $field ) {
			$value = $content_context[ $field ] ?? '';
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
}
