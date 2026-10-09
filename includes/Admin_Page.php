<?php
/**
 * WordPress admin page for Toolbox actions.
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

use Npcink\LocalAutomationRuntime\NightlyInspection\Manual_Dry_Run_Planner;
use Npcink\LocalAutomationRuntime\NightlyInspection\Basic_WP_Cron_Dry_Run;
use Npcink\LocalAutomationRuntime\NightlyInspection\Morning_Brief_Builder;
use Npcink\LocalAutomationRuntime\NightlyInspection\Snapshot_Collector;

defined( 'ABSPATH' ) || exit;

final class Admin_Page extends Admin_Page_Site_Ops_Panel {
	private const PARENT_MENU_SLUG = 'npcink-ai';
	private const MENU_CAPABILITY  = 'manage_options';

	private string $hook_suffix = '';

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function register_navigation(): void {
		add_menu_page(
			__( 'Npcink AI', 'npcink-workflow-toolbox' ),
			__( 'Npcink AI', 'npcink-workflow-toolbox' ),
			self::MENU_CAPABILITY,
			self::PARENT_MENU_SLUG,
			array( $this, 'render_suite_overview' ),
			'dashicons-superhero',
			58
		);

		add_submenu_page(
			self::PARENT_MENU_SLUG,
			__( 'Npcink AI Overview', 'npcink-workflow-toolbox' ),
			__( 'Overview', 'npcink-workflow-toolbox' ),
			self::MENU_CAPABILITY,
			self::PARENT_MENU_SLUG,
			array( $this, 'render_suite_overview' ),
			0
		);
	}

	public function register_menu(): void {
		$this->hook_suffix = add_submenu_page(
			self::PARENT_MENU_SLUG,
			__( 'Npcink Workflow Toolbox', 'npcink-workflow-toolbox' ),
			__( 'Toolbox', 'npcink-workflow-toolbox' ),
			self::MENU_CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render' ),
			45
		);
	}

	/** Resumes a paused internal media-recognition continuation. */
	public function handle_resume_media_recognition(): void {
		if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Npcink AI.', 'npcink-workflow-toolbox' ) );
		}
		check_admin_referer( 'npcink_toolbox_resume_media_recognition' );
		apply_filters( 'npcink_toolbox_media_recognition_resume', array() );
		wp_safe_redirect( $this->image_batch_tool_url( 'media-batch-optimize' ) );
		exit;
	}

	/** Confirms recognition for fingerprint changes discovered in the background. */
	public function handle_confirm_changed_media_recognition(): void {
		if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Npcink AI.', 'npcink-workflow-toolbox' ) );
		}
		check_admin_referer( 'npcink_toolbox_confirm_changed_media_recognition' );
		apply_filters( 'npcink_toolbox_changed_media_recognition_confirm', array() );
		wp_safe_redirect( $this->image_batch_tool_url( 'media-batch-optimize' ) );
		exit;
	}

	public function render_suite_overview(): void {
		if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Npcink AI.', 'npcink-workflow-toolbox' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Npcink AI', 'npcink-workflow-toolbox' ); ?></h1>
			<p><?php esc_html_e( 'Installed Npcink WordPress tools and their independent administration surfaces.', 'npcink-workflow-toolbox' ); ?></p>
			<h2><?php esc_html_e( 'Installed Surfaces', 'npcink-workflow-toolbox' ); ?></h2>
			<table class="widefat striped" style="max-width: 920px;">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Surface', 'npcink-workflow-toolbox' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Purpose', 'npcink-workflow-toolbox' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Action', 'npcink-workflow-toolbox' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$this->render_suite_overview_row( __( 'Core', 'npcink-workflow-toolbox' ), __( 'Review proposals, approval decisions, commit preflight, audit, and client access tokens.', 'npcink-workflow-toolbox' ), 'npcink-governance-core' );
					$this->render_suite_overview_row( __( 'Adapter', 'npcink-workflow-toolbox' ), __( 'Connect OpenClaw and compatible AI clients through the Adapter surface.', 'npcink-workflow-toolbox' ), 'npcink-ai-client-adapter' );
					$this->render_suite_overview_row( __( 'Abilities', 'npcink-workflow-toolbox' ), __( 'Inspect WordPress Abilities API packages and bounded ability controls.', 'npcink-workflow-toolbox' ), 'npcink-abilities-toolkit' );
					$this->render_suite_overview_row( __( 'Workflow Toolbox', 'npcink-workflow-toolbox' ), __( 'Open fixed review-only tools, site checks, and governed handoff suggestions.', 'npcink-workflow-toolbox' ), self::MENU_SLUG );
					$this->render_suite_overview_row( __( 'Cloud Addon', 'npcink-workflow-toolbox' ), __( 'Connect this site to Npcink Cloud and inspect connector status.', 'npcink-workflow-toolbox' ), 'npcink-cloud-addon' );
					?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function render_suite_overview_row( string $label, string $description, string $slug ): void {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td><?php echo esc_html( $description ); ?></td>
			<td>
				<?php if ( $this->is_suite_submenu_registered( $slug ) ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>"><?php esc_html_e( 'Open', 'npcink-workflow-toolbox' ); ?></a>
				<?php else : ?>
					<span style="color: #646970;"><?php esc_html_e( 'Not installed', 'npcink-workflow-toolbox' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function is_suite_submenu_registered( string $slug ): bool {
		global $submenu;

		foreach ( (array) ( $submenu[ self::PARENT_MENU_SLUG ] ?? array() ) as $item ) {
			if ( isset( $item[2] ) && $slug === $item[2] ) {
				return true;
			}
		}

		return false;
	}

	public function enqueue( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		$style_version  = $this->asset_version( 'assets/admin.css' );
		$script_version = $this->asset_version( 'assets/admin.js' );

		wp_enqueue_style(
			'npcink-toolbox-admin',
			NPCINK_TOOLBOX_URL . 'assets/admin.css',
			array(),
			$style_version
		);

		wp_enqueue_script(
			'npcink-toolbox-admin',
			NPCINK_TOOLBOX_URL . 'assets/admin.js',
			array( 'wp-i18n' ),
			$script_version,
			true
		);
		wp_set_script_translations(
			'npcink-toolbox-admin',
			'npcink-workflow-toolbox',
			NPCINK_TOOLBOX_DIR . 'languages'
		);
		wp_enqueue_media();

		wp_localize_script(
			'npcink-toolbox-admin',
			'NpcinkToolbox',
			array(
				'restUrl'        => esc_url_raw( rest_url( Plugin::REST_NAMESPACE ) ),
				'adapterRestUrl' => esc_url_raw( rest_url( 'npcink-openclaw-adapter/v1' ) ),
				'coreRestUrl'    => esc_url_raw( rest_url( 'npcink-governance-core/v1' ) ),
				'coreAdminUrl'   => esc_url_raw( admin_url( 'admin.php?page=npcink-governance-core' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'dateTime'       => $this->datetime_display_config(),
				'contextOption'  => Plugin::CONTEXT_OPTION_NAME,
				'contextDrafts'  => array(
					'aiBlog' => $this->get_ai_blog_context_template(),
					'site'   => $this->get_site_content_context_suggestion(),
				),
				'labels'         => array(
					'running' => __( 'Running...', 'npcink-workflow-toolbox' ),
					'error'   => __( 'Request failed.', 'npcink-workflow-toolbox' ),
				),
			)
		);
	}

	private function asset_version( string $relative_path ): string {
		$path     = NPCINK_TOOLBOX_DIR . ltrim( $relative_path, '/' );
		$modified = file_exists( $path ) ? filemtime( $path ) : false;
		return NPCINK_TOOLBOX_VERSION . ( $modified ? '-' . (string) $modified : '' );
	}

	private function toolbox_admin_url( array $args = array() ): string {
		return add_query_arg(
			array_merge(
				array(
					'page' => self::MENU_SLUG,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	private function media_library_url(): string {
		return add_query_arg(
			array(
				'mode' => 'list',
			),
			admin_url( 'upload.php' )
		);
	}

	private function image_batch_tool_url( string $tool, array $attachment_ids = array() ): string {
		$args           = array(
			'toolbox_tab'  => 'tools',
			'toolbox_tool' => $tool,
		);
		$attachment_ids = array_values(
			array_filter(
				array_map( 'absint', $attachment_ids ),
				static function ( int $attachment_id ): bool {
					return $attachment_id > 0;
				}
			)
		);
		if ( ! empty( $attachment_ids ) ) {
			$args['attachment_ids'] = implode( ',', array_slice( array_unique( $attachment_ids ), 0, 50 ) );
			$args['source']         = 'media-library-bulk';
		}

		return $this->toolbox_admin_url( $args );
	}

	/**
	 * Adds contextual AI actions to the WordPress media attachment details panel.
	 *
	 * @param array<string,array<string,mixed>> $form_fields Existing media fields.
	 * @param \WP_Post                         $post Attachment post.
	 * @return array<string,array<string,mixed>>
	 */
	public function add_media_library_attachment_actions( array $form_fields, \WP_Post $post ): array {
		$attachment_id = absint( $post->ID ?? 0 );
		if ( $attachment_id <= 0 || ! current_user_can( 'manage_options' ) ) {
			return $form_fields;
		}
		if ( function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $attachment_id ) ) {
			return $form_fields;
		}

		$form_fields['npcink_toolbox_ai_image_optimization'] = array(
			'label' => __( 'Npcink AI', 'npcink-workflow-toolbox' ),
			'input' => 'html',
			'html'  => sprintf(
				'<div class="npcink-toolbox-media-library-action"><p>%1$s</p><p><a class="button" href="%2$s">%3$s</a></p><p class="description">%4$s</p></div>',
				esc_html__( 'Review this image from the media library.', 'npcink-workflow-toolbox' ),
				esc_url( $this->image_batch_tool_url( 'media-alt-caption-review', array( $attachment_id ) ) ),
				esc_html__( 'Complete ALT for this image', 'npcink-workflow-toolbox' ),
				esc_html__( 'Use the Media Library bulk action for governed image optimization.', 'npcink-workflow-toolbox' )
			),
		);

		return $form_fields;
	}

	/**
	 * Adds contextual Npcink AI actions to each image row in the media list table.
	 *
	 * @param array<string,string> $actions Existing row actions.
	 * @param \WP_Post            $post Attachment post.
	 * @param bool                $detached Whether the attachment is detached.
	 * @return array<string,string>
	 */
	public function filter_media_library_row_actions( array $actions, \WP_Post $post, bool $detached = false ): array {
		$attachment_id = absint( $post->ID ?? 0 );
		if ( $attachment_id <= 0 || ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}
		if ( function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $attachment_id ) ) {
			return $actions;
		}

		$actions['npcink_toolbox_alt'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $this->image_batch_tool_url( 'media-alt-caption-review', array( $attachment_id ) ) ),
			esc_html__( 'Npcink ALT', 'npcink-workflow-toolbox' )
		);
		return $actions;
	}

	/**
	 * Adds batch actions to the media library list table.
	 *
	 * @param array<string,string> $actions Existing actions.
	 * @return array<string,string>
	 */
	public function filter_media_library_bulk_actions( array $actions ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}
		$actions['npcink_toolbox_batch_alt']      = __( 'Npcink: complete ALT for selected images', 'npcink-workflow-toolbox' );
		$actions['npcink_toolbox_batch_optimize'] = __( 'Npcink: optimize selected images', 'npcink-workflow-toolbox' );
		return $actions;
	}

	/**
	 * Redirects selected media items to the governed Toolbox batch workbench.
	 *
	 * @param string                $redirect_to Redirect URL.
	 * @param string                $action Bulk action id.
	 * @param array<int,int|string> $post_ids Selected attachment IDs.
	 * @return string
	 */
	public function handle_media_library_bulk_action( string $redirect_to, string $action, array $post_ids ): string {
		if ( ! in_array( $action, array( 'npcink_toolbox_batch_alt', 'npcink_toolbox_batch_optimize' ), true ) || ! current_user_can( 'manage_options' ) ) {
			return $redirect_to;
		}

		$image_ids = array();
		foreach ( $post_ids as $post_id ) {
			$attachment_id = absint( $post_id );
			if ( $attachment_id <= 0 ) {
				continue;
			}
			if ( function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $attachment_id ) ) {
				continue;
			}
			$image_ids[] = $attachment_id;
		}

		$tool = 'npcink_toolbox_batch_alt' === $action ? 'media-alt-caption-review' : 'media-batch-optimize';
		return $this->image_batch_tool_url( $tool, $image_ids );
	}

	private function datetime_display_config(): array {
		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		$now      = new \DateTimeImmutable( 'now', $timezone );

		return array(
			'format'        => 'Y-m-d H:i:s',
			'timeZone'      => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC',
			'offsetMinutes' => (int) floor( $timezone->getOffset( $now ) / 60 ),
		);
	}

	private function get_ai_blog_context_template(): array {
		return array(
			'site_positioning'                  => __( 'A practical AI technology blog for developers, product teams, and AI tool builders. It focuses on large language model applications, agent workflows, WordPress AI integration, vector search, content automation, and AI product engineering.', 'npcink-workflow-toolbox' ),
			'target_audience'                   => array(
				__( 'AI application developers', 'npcink-workflow-toolbox' ),
				__( 'WordPress plugin developers', 'npcink-workflow-toolbox' ),
				__( 'Technical content operators', 'npcink-workflow-toolbox' ),
				__( 'AI product managers', 'npcink-workflow-toolbox' ),
				__( 'Independent developers', 'npcink-workflow-toolbox' ),
				__( 'Internal tools teams', 'npcink-workflow-toolbox' ),
			),
			'brand_voice'                       => __( 'Professional, pragmatic, clear, and restrained. Explain real use cases, engineering tradeoffs, and boundary risks. Avoid inflated marketing claims. Give direct recommendations with conditions and limits.', 'npcink-workflow-toolbox' ),
			'primary_keywords'                  => array(
				__( 'AI technology blog', 'npcink-workflow-toolbox' ),
				__( 'large language model applications', 'npcink-workflow-toolbox' ),
				__( 'AI Agent', 'npcink-workflow-toolbox' ),
				__( 'WordPress AI', 'npcink-workflow-toolbox' ),
				__( 'vector search', 'npcink-workflow-toolbox' ),
				__( 'RAG', 'npcink-workflow-toolbox' ),
				__( 'AI workflow', 'npcink-workflow-toolbox' ),
				__( 'content automation', 'npcink-workflow-toolbox' ),
			),
			'long_tail_keywords'                => array(
				__( 'how to integrate AI capabilities into WordPress', 'npcink-workflow-toolbox' ),
				__( 'AI Agent workflow design', 'npcink-workflow-toolbox' ),
				__( 'WordPress plugin development with AI tools', 'npcink-workflow-toolbox' ),
				__( 'vector search for content websites', 'npcink-workflow-toolbox' ),
				__( 'RAG and content retrieval practice', 'npcink-workflow-toolbox' ),
				__( 'AI content suggestion workflow', 'npcink-workflow-toolbox' ),
				__( 'large language model application engineering', 'npcink-workflow-toolbox' ),
			),
			'entity_keywords'                   => array( 'OpenAI', 'WordPress', 'Cloud Search', 'Site Knowledge', 'Unsplash', 'REST API', 'WordPress Abilities API', 'Npcink' ),
			'allowed_claims'                    => array(
				__( 'AI tools can assist research, generate suggestions, plan content, and improve editorial efficiency.', 'npcink-workflow-toolbox' ),
				__( 'Vector search, external search, and content context can improve retrieval and suggestion quality.', 'npcink-workflow-toolbox' ),
				__( 'Architecture advice and implementation ideas are suitable for development and testing contexts.', 'npcink-workflow-toolbox' ),
				__( 'Final publishing, SEO writes, and media changes should go through human review or governance.', 'npcink-workflow-toolbox' ),
			),
			'forbidden_claims'                  => array(
				__( 'Do not claim AI output is always correct.', 'npcink-workflow-toolbox' ),
				__( 'Do not claim automatic SEO ranking improvements.', 'npcink-workflow-toolbox' ),
				__( 'Do not claim AI replaces human review, legal review, or expert judgment.', 'npcink-workflow-toolbox' ),
				__( 'Do not imply WordPress permissions, approval, or governance can be bypassed.', 'npcink-workflow-toolbox' ),
				__( 'Do not describe image-source search as AI image generation.', 'npcink-workflow-toolbox' ),
				__( 'Do not describe vector search as a complete knowledge base or automatic indexing system.', 'npcink-workflow-toolbox' ),
			),
			'disallowed_topics'                 => array(
				__( 'Unsupported customer stories, rankings, benchmark results, or legal/medical/financial advice.', 'npcink-workflow-toolbox' ),
			),
			'cautious_topics'                   => array(
				__( 'Model comparisons, provider pricing, product roadmap, security posture, and production-readiness claims require current verification.', 'npcink-workflow-toolbox' ),
			),
			'no_structured_output_topics'       => array(
				__( 'Do not generate FAQ, HowTo, or schema suggestions when the source does not clearly support every answer or step.', 'npcink-workflow-toolbox' ),
			),
			'human_confirmation_required'       => array(
				__( 'Claims about implemented features, integrations, customer usage, benchmark quality, ranking impact, or availability must be confirmed by the operator.', 'npcink-workflow-toolbox' ),
			),
			'seo_rules'                         => __( "Titles should include the main topic keyword and avoid clickbait.\nDescriptions should state the problem, audience, and core conclusion.\nUse clear headings, steps, caveats, and engineering boundary notes.\nPrefer internal links to related tutorials, architecture notes, and tool reviews.", 'npcink-workflow-toolbox' ),
			'aeo_rules'                         => __( "Start with a direct answer, then add conditions, steps, and limits.\nPrefer FAQ, short definitions, comparison tables, and actionable checklists.\nAvoid abstract-only answers; include practical guidance.", 'npcink-workflow-toolbox' ),
			'geo_rules'                         => __( "Make key conclusions clear, standalone, and easy for AI systems to summarize.\nDefine important terms when they first appear.\nDistinguish implemented features, development-stage behavior, and future plans.\nAvoid inflated claims; state boundaries, inputs, outputs, and limits.", 'npcink-workflow-toolbox' ),
			'allow_faq_generation'              => true,
			'allow_aeo_summary'                 => true,
			'allow_geo_summary'                 => true,
			'allow_structured_data_suggestions' => true,
			'proposal_allowed_fields'           => array( 'seo_title', 'seo_description', 'slug', 'excerpt', 'faq', 'answer_summary', 'geo_summary', 'structured_data_hints' ),
		);
	}

	private function get_site_content_context_suggestion(): array {
		$site_name    = wp_strip_all_tags( (string) get_bloginfo( 'name' ) );
		$tagline      = wp_strip_all_tags( (string) get_bloginfo( 'description' ) );
		$recent_posts = get_posts(
			array(
				'numberposts' => 8,
				'post_status' => 'publish',
				'post_type'   => 'post',
				'orderby'     => 'date',
				'order'       => 'DESC',
				'fields'      => 'ids',
			)
		);
		$titles       = array();

		foreach ( $recent_posts as $post_id ) {
			$title = trim( wp_strip_all_tags( (string) get_the_title( $post_id ) ) );
			if ( '' !== $title ) {
				$titles[] = $title;
			}
		}

		$terms      = get_terms(
			array(
				'taxonomy'   => array( 'category', 'post_tag' ),
				'hide_empty' => true,
				'number'     => 12,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		$term_names = array();

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_names[] = wp_strip_all_tags( (string) $term->name );
			}
		}

		$primary_keywords   = array_slice( $this->unique_non_empty( $term_names ), 0, 8 );
		$entity_keywords    = array_slice( $this->unique_non_empty( array_merge( array( $site_name ), $term_names ) ), 0, 10 );
		$long_tail_keywords = array();

		foreach ( array_slice( $titles, 0, 6 ) as $title ) {
			$long_tail_keywords[] = sprintf(
				/* translators: %s: post title. */
				__( 'Practical guide: %s', 'npcink-workflow-toolbox' ),
				$title
			);
		}

		if ( empty( $primary_keywords ) ) {
			$primary_keywords = array_filter( array( $site_name, $tagline ) );
		}

		$position_parts = array_filter( array( $site_name, $tagline ) );
		$positioning    = ! empty( $position_parts )
			? sprintf(
				/* translators: %s: site name and tagline. */
				__( '%s. Use recent public posts, categories, and tags as non-secret guidance for content suggestions. Keep the site brief editable and verify recommendations before saving.', 'npcink-workflow-toolbox' ),
				implode( ' - ', $position_parts )
			)
			: __( 'A WordPress site with public content available for operator-reviewed AI content suggestions. Keep recommendations editable and verify them before saving.', 'npcink-workflow-toolbox' );

		return array(
			'site_positioning'                  => $positioning,
			'target_audience'                   => array( __( 'Current site readers', 'npcink-workflow-toolbox' ), __( 'Editors', 'npcink-workflow-toolbox' ), __( 'Site operators', 'npcink-workflow-toolbox' ) ),
			'brand_voice'                       => __( 'Use the tone implied by existing public posts. Prefer clear, accurate, and reviewable suggestions over promotional claims.', 'npcink-workflow-toolbox' ),
			'primary_keywords'                  => $primary_keywords,
			'long_tail_keywords'                => $this->unique_non_empty( $long_tail_keywords ),
			'entity_keywords'                   => $entity_keywords,
			'allowed_claims'                    => array(
				__( 'Suggestions may use public post titles, public categories, and public tags as context.', 'npcink-workflow-toolbox' ),
				__( 'Suggestions should be treated as drafts for operator review.', 'npcink-workflow-toolbox' ),
			),
			'forbidden_claims'                  => array(
				__( 'Do not infer private business facts from public content.', 'npcink-workflow-toolbox' ),
				__( 'Do not claim the generated suggestions have been verified unless an operator verifies them.', 'npcink-workflow-toolbox' ),
				__( 'Do not bypass WordPress permissions, approval, or governance.', 'npcink-workflow-toolbox' ),
			),
			'disallowed_topics'                 => array(
				__( 'Unsupported private facts, unverified business claims, and claims outside current public site content.', 'npcink-workflow-toolbox' ),
			),
			'cautious_topics'                   => array(
				__( 'Product status, pricing, customer examples, legal/medical/financial claims, and time-sensitive facts require operator confirmation.', 'npcink-workflow-toolbox' ),
			),
			'no_structured_output_topics'       => array(
				__( 'Do not generate FAQ, HowTo, or schema suggestions unless the sampled source clearly supports them.', 'npcink-workflow-toolbox' ),
			),
			'human_confirmation_required'       => array(
				__( 'Any claim not visible in public post titles, categories, tags, or supplied source content must be confirmed by the operator.', 'npcink-workflow-toolbox' ),
			),
			'seo_rules'                         => __( "Use public categories, tags, and recent article themes as keyword candidates.\nTitles should stay specific to the article topic and avoid clickbait.\nDescriptions should summarize the reader problem and expected value.\nSuggest internal links only when the target content is clearly related.", 'npcink-workflow-toolbox' ),
			'aeo_rules'                         => __( "Answer likely reader questions directly before giving details.\nPrefer concise definitions, steps, checklists, and FAQ suggestions.\nMark assumptions clearly when the site content does not provide enough evidence.", 'npcink-workflow-toolbox' ),
			'geo_rules'                         => __( "Use public entity names from categories, tags, and recent titles as entity hints.\nKeep conclusions standalone and easy to quote.\nDistinguish observed site content from generated recommendations.", 'npcink-workflow-toolbox' ),
			'allow_faq_generation'              => true,
			'allow_aeo_summary'                 => true,
			'allow_geo_summary'                 => true,
			'allow_structured_data_suggestions' => true,
			'proposal_allowed_fields'           => array( 'seo_title', 'seo_description', 'slug', 'excerpt', 'faq', 'answer_summary', 'geo_summary', 'structured_data_hints' ),
		);
	}

	private function unique_non_empty( array $items ): array {
		$normalized = array();

		foreach ( $items as $item ) {
			$value = trim( (string) $item );
			if ( '' !== $value ) {
				$normalized[] = $value;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'npcink-workflow-toolbox' ) );
		}

		$settings              = $this->settings->get_all();
		$content_context       = $this->settings->get_content_context();
		$cloud_ready           = $this->settings->cloud_runtime_available();
		$active_tab            = $this->requested_toolbox_tab();
		$active_site_check_tab = $this->requested_site_check_tab();
		$nightly_preview       = 'operations-insights' === $active_tab ? $this->nightly_inspection_preview_from_request() : null;
		$site_ops_preview      = 'operations-insights' === $active_tab ? $this->site_ops_insights_preview_from_request( $content_context, $cloud_ready ) : null;
		?>
		<div class="wrap npcink-toolbox">
			<h1><?php esc_html_e( 'Npcink Workflow Toolbox', 'npcink-workflow-toolbox' ); ?></h1>
			<p class="npcink-toolbox__scope"><?php esc_html_e( 'Check and optimize Media Library images. Other AI suggestions still require human review.', 'npcink-workflow-toolbox' ); ?></p>
			<?php
			if ( ! $cloud_ready ) {
				$this->render_cloud_runtime_notice();
			}
			if ( '' !== $this->query_text_param( 'settings-updated' ) ) {
				settings_errors();
			}
			?>

			<nav class="npcink-ai-tabs npcink-toolbox__tabs" data-toolbox-tabs aria-label="<?php esc_attr_e( 'Toolbox sections', 'npcink-workflow-toolbox' ); ?>">
				<button type="button" class="npcink-ai-tab npcink-toolbox__tab<?php echo 'start' === $active_tab ? ' npcink-ai-tab-active is-active' : ''; ?>" data-toolbox-tab-target="start" aria-selected="<?php echo 'start' === $active_tab ? 'true' : 'false'; ?>" <?php echo 'start' === $active_tab ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Overview', 'npcink-workflow-toolbox' ); ?></button>
				<button type="button" class="npcink-ai-tab npcink-toolbox__tab<?php echo 'context' === $active_tab ? ' npcink-ai-tab-active is-active' : ''; ?>" data-toolbox-tab-target="context" aria-selected="<?php echo 'context' === $active_tab ? 'true' : 'false'; ?>" <?php echo 'context' === $active_tab ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Site Profile', 'npcink-workflow-toolbox' ); ?></button>
				<button type="button" class="npcink-ai-tab npcink-toolbox__tab<?php echo 'tools' === $active_tab ? ' npcink-ai-tab-active is-active' : ''; ?>" data-toolbox-tab-target="tools" aria-selected="<?php echo 'tools' === $active_tab ? 'true' : 'false'; ?>" <?php echo 'tools' === $active_tab ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Image Handling', 'npcink-workflow-toolbox' ); ?></button>
			</nav>

			<section class="npcink-toolbox__panel" data-toolbox-tab-panel="start" aria-label="<?php esc_attr_e( 'Toolbox start', 'npcink-workflow-toolbox' ); ?>"<?php echo 'start' === $active_tab ? '' : ' hidden'; ?>>
				<?php $this->render_start_panel( $content_context, $cloud_ready ); ?>
			</section>

			<section class="npcink-toolbox__panel" data-toolbox-tab-panel="context" aria-label="<?php esc_attr_e( 'Site context', 'npcink-workflow-toolbox' ); ?>"<?php echo 'context' === $active_tab ? '' : ' hidden'; ?>>
				<?php $this->render_content_context_form( $content_context ); ?>
			</section>

			<section class="npcink-toolbox__panel npcink-toolbox__panel--secondary" data-toolbox-tab-panel="operations-insights" aria-label="<?php esc_attr_e( 'Site check', 'npcink-workflow-toolbox' ); ?>"<?php echo 'operations-insights' === $active_tab ? '' : ' hidden'; ?>>
				<?php $this->render_operations_insights_panel( $site_ops_preview, $content_context, $cloud_ready, $settings, $nightly_preview, $active_site_check_tab ); ?>
			</section>

			<section class="npcink-toolbox__panel" data-toolbox-tab-panel="tools" aria-label="<?php esc_attr_e( 'Image handling', 'npcink-workflow-toolbox' ); ?>"<?php echo 'tools' === $active_tab ? '' : ' hidden'; ?>>
				<?php $this->render_tool_cards( $cloud_ready, 'image' ); ?>
			</section>

		</div>
		<?php
	}

	private function requested_toolbox_tab(): string {
		$requested = sanitize_key( $this->query_text_param( 'toolbox_tab' ) );

		$allowed = array(
			'start'               => true,
			'context'             => true,
			'tools'               => true,
			'operations-insights' => true,
		);
		return isset( $allowed[ $requested ] ) ? $requested : 'start';
	}

	private function requested_site_check_tab(): string {
		$requested       = sanitize_key( $this->query_text_param( 'site_check_tab' ) );
		$nightly_preview = $this->query_text_param( 'nightly_inspection_preview' );

		if ( 'scheduled-review' === $requested || '1' === $nightly_preview ) {
			return 'scheduled-review';
		}

		return 'current-check';
	}

	private function requested_toolbox_tool(): string {
		$requested = sanitize_key( $this->query_text_param( 'toolbox_tool' ) );

		$allowed = array(
			'media-batch-optimize'     => true,
			'media-alt-caption-review' => true,
			'image-settings'           => true,
		);
		return isset( $allowed[ $requested ] ) ? $requested : 'media-batch-optimize';
	}

	private function render_start_panel( array $content_context, bool $cloud_ready ): void {
		?>
		<div class="npcink-toolbox__panel-header">
			<h2><?php esc_html_e( 'Overview', 'npcink-workflow-toolbox' ); ?></h2>
			<p><?php esc_html_e( 'Use Site Profile and Image Handling for currently supported operator tasks.', 'npcink-workflow-toolbox' ); ?></p>
		</div>

		<div class="npcink-toolbox__start" data-toolbox-start>
			<?php if ( ! $cloud_ready ) : ?>
				<?php $this->render_getting_started_steps( $content_context ); ?>
			<?php endif; ?>
			<details class="npcink-toolbox__start-advanced">
				<summary>
					<span><?php esc_html_e( 'System status', 'npcink-workflow-toolbox' ); ?></span>
					<small><?php esc_html_e( 'Workflow readiness and support-facing details.', 'npcink-workflow-toolbox' ); ?></small>
				</summary>
				<?php $this->render_npcink_capability_health_summary( $content_context, $cloud_ready ); ?>
			</details>
		</div>
		<?php
	}

	private function render_getting_started_steps( array $content_context ): void {
		$addon_installed = $this->is_suite_submenu_registered( 'npcink-cloud-addon' );
		$profile_ready   = $this->content_context_ready( $content_context );
		$steps           = array(
			array(
				'done'   => false,
				/* translators: %d: getting-started step number. */
				'title'  => sprintf( __( 'Step %d', 'npcink-workflow-toolbox' ), 1 ),
				'label'  => __( 'Connect Npcink Cloud', 'npcink-workflow-toolbox' ),
				'help'   => __( 'Install and verify the Cloud Addon so hosted search, images, and checks can run.', 'npcink-workflow-toolbox' ),
				'url'    => $addon_installed ? $this->cloud_addon_details_url() : admin_url( 'plugins.php' ),
				'action' => $addon_installed ? __( 'Open Cloud Addon settings', 'npcink-workflow-toolbox' ) : __( 'Install the Cloud Addon plugin', 'npcink-workflow-toolbox' ),
			),
			array(
				'done'   => $profile_ready,
				/* translators: %d: getting-started step number. */
				'title'  => sprintf( __( 'Step %d', 'npcink-workflow-toolbox' ), 2 ),
				'label'  => __( 'Fill the site profile', 'npcink-workflow-toolbox' ),
				'help'   => __( 'A short site brief makes AI suggestions match your audience. This works even before Cloud is connected.', 'npcink-workflow-toolbox' ),
				'url'    => add_query_arg(
					array(
						'page'        => self::MENU_SLUG,
						'toolbox_tab' => 'context',
					),
					admin_url( 'admin.php' )
				),
				'action' => __( 'Open site profile', 'npcink-workflow-toolbox' ),
			),
			array(
				'done'   => false,
				/* translators: %d: getting-started step number. */
				'title'  => sprintf( __( 'Step %d', 'npcink-workflow-toolbox' ), 3 ),
				'label'  => __( 'Try your first image task', 'npcink-workflow-toolbox' ),
				'help'   => __( 'Check optimizable Media Library images and review Cloud-qualified previews before anything changes.', 'npcink-workflow-toolbox' ),
				'url'    => add_query_arg(
					array(
						'page'        => self::MENU_SLUG,
						'toolbox_tab' => 'tools',
					),
					admin_url( 'admin.php' )
				),
				'action' => __( 'Open image handling', 'npcink-workflow-toolbox' ),
			),
		);
		?>
			<section class="npcink-toolbox__card" id="npcink-toolbox-tour-getting-started" aria-label="<?php esc_attr_e( 'Getting started', 'npcink-workflow-toolbox' ); ?>">
				<div class="npcink-toolbox__section-heading">
					<div>
						<h3><?php esc_html_e( 'Getting started', 'npcink-workflow-toolbox' ); ?></h3>
						<p><?php esc_html_e( 'Three steps to your first AI-assisted task. Nothing is written without your review.', 'npcink-workflow-toolbox' ); ?></p>
					</div>
					<div class="npcink-toolbox__inline-actions">
						<a id="npcink-toolbox-tour-restart" class="button-link" href="#" hidden><?php esc_html_e( 'Restart tour', 'npcink-workflow-toolbox' ); ?></a>
					</div>
				</div>
				<ol style="margin:0;padding-left:20px;display:grid;gap:10px;">
					<?php foreach ( $steps as $step ) : ?>
						<li style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;">
							<span>
								<strong><?php echo esc_html( $step['title'] . ': ' . $step['label'] ); ?></strong>
								<?php if ( $step['done'] ) : ?>
									<span style="color:#00a32a;font-weight:600;"> — <?php esc_html_e( 'Done', 'npcink-workflow-toolbox' ); ?></span>
								<?php endif; ?>
								<br /><span class="description"><?php echo esc_html( $step['help'] ); ?></span>
							</span>
							<a class="button" href="<?php echo esc_url( $step['url'] ); ?>"><?php echo esc_html( $step['action'] ); ?></a>
						</li>
					<?php endforeach; ?>
				</ol>
				<p style="margin:12px 0 0;">
					<a id="npcink-toolbox-tour-start" class="button-link" href="#"><?php esc_html_e( 'Take the 2-minute tour', 'npcink-workflow-toolbox' ); ?></a>
				</p>
			</section>
		<?php
	}

	private function render_npcink_capability_health_summary( array $content_context, bool $cloud_ready ): void {
		$rows = Ability_Surface_Metadata::health_summary(
			array(
				'cloud_ready'        => $cloud_ready,
				'site_profile_ready' => $this->content_context_ready( $content_context ),
			)
		);
		?>
			<section class="npcink-toolbox__ability-health" id="npcink-toolbox-tour-system-status" aria-label="<?php esc_attr_e( 'Workflow readiness summary', 'npcink-workflow-toolbox' ); ?>">
			<div class="npcink-toolbox__section-heading npcink-toolbox__section-heading--compact">
				<div>
					<h3><?php esc_html_e( 'Workflow readiness', 'npcink-workflow-toolbox' ); ?></h3>
					<p><?php esc_html_e( 'Read-only status for setup and support. It does not change WordPress content.', 'npcink-workflow-toolbox' ); ?></p>
				</div>
			</div>
				<div class="npcink-toolbox__start-status-list">
					<?php
					foreach ( $rows as $row ) {
						$this->render_start_status_row(
							(string) ( $row['label'] ?? '' ),
							(string) ( $row['status'] ?? 'neutral' ),
							(string) ( $row['status_text'] ?? '' ),
							(string) ( $row['description'] ?? '' ),
							'cloud_runtime' === (string) ( $row['id'] ?? '' ) ? 'npcink-toolbox-tour-cloud-status' : ''
						);
					}
					?>
				</div>
		</section>
		<?php
	}

	private function cloud_addon_details_url(): string {
		return add_query_arg(
			array(
				'page' => 'npcink-cloud-addon',
				'tab'  => 'details',
			),
			admin_url( 'admin.php' )
		);
	}

	private function cloud_addon_runtime_runs_url(): string {
		return add_query_arg(
			array(
				'page' => 'npcink-cloud-addon',
				'tab'  => 'runtime_runs',
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * @param array<string,mixed>|null $preview Preview payload.
	 * @param array<string,mixed>      $content_context Content context.
	 * @param array<string,mixed>      $settings Settings.
	 */
	private function render_operations_insights_panel( ?array $preview, array $content_context, bool $cloud_ready, array $settings, ?array $nightly_preview, string $active_site_check_tab ): void {
		$context_ready      = $this->content_context_ready( $content_context );
		$pack               = isset( $preview['pack'] ) && is_array( $preview['pack'] ) ? $preview['pack'] : array();
		$cloud_request      = isset( $preview['cloud_request'] ) && is_array( $preview['cloud_request'] ) ? $preview['cloud_request'] : array();
		$cloud_analysis     = $preview['cloud_analysis'] ?? null;
		$summary            = isset( $pack['summary'] ) && is_array( $pack['summary'] ) ? $pack['summary'] : array();
		$findings           = isset( $pack['top_findings'] ) && is_array( $pack['top_findings'] ) ? array_slice( $pack['top_findings'], 0, 8 ) : array();
		$finding_count      = count( $findings );
		$has_cloud_analysis = null !== $cloud_analysis;
		?>
		<div class="npcink-toolbox__panel-header">
			<p style="margin:0 0 6px;">
				<a class="button" href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page'        => self::MENU_SLUG,
							'toolbox_tab' => 'start',
						),
						admin_url( 'admin.php' )
					)
				);
				?>
										">
					&larr; <?php esc_html_e( 'Back to Overview', 'npcink-workflow-toolbox' ); ?>
				</a>
			</p>
			<h2><?php esc_html_e( 'Site Check', 'npcink-workflow-toolbox' ); ?></h2>
			<p><?php esc_html_e( 'Run one read-only check that routes current site issues to the right fixed workflow, manual review, or optional Cloud detail. It does not create Core proposals or WordPress writes.', 'npcink-workflow-toolbox' ); ?></p>
		</div>

		<div class="npcink-toolbox__ops-workspace" data-toolbox-site-check-tabs>
			<nav class="npcink-toolbox__ops-tabs" aria-label="<?php esc_attr_e( 'Site Check sections', 'npcink-workflow-toolbox' ); ?>">
				<button type="button" class="npcink-toolbox__ops-tab<?php echo 'current-check' === $active_site_check_tab ? ' is-active' : ''; ?>" data-toolbox-site-check-target="current-check" aria-selected="<?php echo 'current-check' === $active_site_check_tab ? 'true' : 'false'; ?>"><?php esc_html_e( 'Current check', 'npcink-workflow-toolbox' ); ?></button>
				<button type="button" class="npcink-toolbox__ops-tab<?php echo 'scheduled-review' === $active_site_check_tab ? ' is-active' : ''; ?>" data-toolbox-site-check-target="scheduled-review" aria-selected="<?php echo 'scheduled-review' === $active_site_check_tab ? 'true' : 'false'; ?>"><?php esc_html_e( 'Scheduled review', 'npcink-workflow-toolbox' ); ?></button>
			</nav>

			<section class="npcink-toolbox__ops-panel" data-toolbox-site-check-panel="current-check"<?php echo 'current-check' === $active_site_check_tab ? '' : ' hidden'; ?>>
				<section class="npcink-toolbox__ops-status-row" aria-label="<?php esc_attr_e( 'Site Check readiness', 'npcink-workflow-toolbox' ); ?>">
					<div class="npcink-toolbox__ops-status-main">
						<span><strong><?php esc_html_e( 'Local data', 'npcink-workflow-toolbox' ); ?></strong><?php echo esc_html( null === $preview ? __( 'Ready to scan', 'npcink-workflow-toolbox' ) : __( 'Scanned', 'npcink-workflow-toolbox' ) ); ?></span>
						<span><strong><?php esc_html_e( 'Site Context', 'npcink-workflow-toolbox' ); ?></strong><?php echo esc_html( $context_ready ? __( 'Ready', 'npcink-workflow-toolbox' ) : __( 'Needs brief', 'npcink-workflow-toolbox' ) ); ?></span>
						<span><strong><?php esc_html_e( 'Cloud', 'npcink-workflow-toolbox' ); ?></strong><?php echo esc_html( $cloud_ready ? __( 'Ready on request', 'npcink-workflow-toolbox' ) : __( 'Optional', 'npcink-workflow-toolbox' ) ); ?></span>
						<span><strong><?php esc_html_e( 'Writes', 'npcink-workflow-toolbox' ); ?></strong><?php esc_html_e( 'Disabled', 'npcink-workflow-toolbox' ); ?></span>
					</div>
					<div class="npcink-toolbox__ops-status-actions">
						<?php if ( null === $preview || isset( $preview['error'] ) ) : ?>
							<a class="button button-primary" href="<?php echo esc_url( $this->site_ops_insights_preview_url() ); ?>"><?php esc_html_e( 'Generate site check', 'npcink-workflow-toolbox' ); ?></a>
						<?php else : ?>
							<span><?php esc_html_e( 'Current snapshot is ready.', 'npcink-workflow-toolbox' ); ?></span>
							<a class="button button-small" href="<?php echo esc_url( $this->site_ops_insights_preview_url() ); ?>"><?php esc_html_e( 'Rescan', 'npcink-workflow-toolbox' ); ?></a>
						<?php endif; ?>
					</div>
				</section>

				<details class="npcink-toolbox__ops-loop-disclosure"<?php echo null === $preview ? ' open' : ''; ?>>
					<summary><?php esc_html_e( 'How to use Site Check', 'npcink-workflow-toolbox' ); ?></summary>
					<section class="npcink-toolbox__ops-detail-grid" aria-label="<?php esc_attr_e( 'Site Check operator loop', 'npcink-workflow-toolbox' ); ?>">
						<div>
							<strong><?php esc_html_e( '1. Scan local data', 'npcink-workflow-toolbox' ); ?></strong>
							<span><?php esc_html_e( 'Build a current snapshot from public content, approved comment signals, media metadata, taxonomy, Site Context, and Cloud readiness.', 'npcink-workflow-toolbox' ); ?></span>
						</div>
						<div>
							<strong><?php esc_html_e( '2. Pick the next fixed workflow', 'npcink-workflow-toolbox' ); ?></strong>
							<span><?php esc_html_e( 'Use the brief and treatment paths to decide whether the next step is manual review, an existing Toolbox workflow, or Cloud detail.', 'npcink-workflow-toolbox' ); ?></span>
						</div>
						<div>
							<strong><?php esc_html_e( '3. Add Cloud detail only when useful', 'npcink-workflow-toolbox' ); ?></strong>
							<span><?php esc_html_e( 'Cloud may add AI summary, semantic ranking, trend explanation, and closure detail; Toolbox still treats it as review guidance only.', 'npcink-workflow-toolbox' ); ?></span>
						</div>
						<div>
							<strong><?php esc_html_e( '4. Choose the follow-up path', 'npcink-workflow-toolbox' ); ?></strong>
							<span><?php esc_html_e( 'Handle simple items manually, or turn eligible items into reviewed handoff plans outside this report.', 'npcink-workflow-toolbox' ); ?></span>
						</div>
					</section>
				</details>

				<section class="npcink-toolbox__card" data-toolbox-site-ops-insights>
			<?php if ( null === $preview ) : ?>
				<div class="npcink-toolbox__section-heading">
					<div>
						<h3><?php esc_html_e( 'Site action checklist', 'npcink-workflow-toolbox' ); ?></h3>
						<p><?php esc_html_e( 'Scan the current site, then start with the few issues most likely to affect readers, search, and daily operations.', 'npcink-workflow-toolbox' ); ?></p>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( isset( $preview['error'] ) ) : ?>
				<div class="npcink-toolbox__result-notice is-warning"><?php echo esc_html( (string) $preview['error'] ); ?></div>
			<?php elseif ( null === $preview ) : ?>
				<div class="npcink-toolbox__result-notice"><?php esc_html_e( 'No scan has run in this view yet. Generate a local site check when you need a current priority queue for fixed workflows.', 'npcink-workflow-toolbox' ); ?></div>
			<?php else : ?>
				<div class="npcink-toolbox__ops-workspace" data-toolbox-ops-tabs>
					<nav class="npcink-toolbox__ops-tabs" aria-label="<?php esc_attr_e( 'Site Check views', 'npcink-workflow-toolbox' ); ?>">
						<button type="button" class="npcink-toolbox__ops-tab is-active" data-toolbox-ops-target="overview" aria-selected="true"><?php esc_html_e( 'Overview', 'npcink-workflow-toolbox' ); ?></button>
						<button type="button" class="npcink-toolbox__ops-tab" data-toolbox-ops-target="content" aria-selected="false"><?php esc_html_e( 'Content', 'npcink-workflow-toolbox' ); ?></button>
						<button type="button" class="npcink-toolbox__ops-tab" data-toolbox-ops-target="media" aria-selected="false"><?php esc_html_e( 'Media', 'npcink-workflow-toolbox' ); ?></button>
						<button type="button" class="npcink-toolbox__ops-tab" data-toolbox-ops-target="comments" aria-selected="false"><?php esc_html_e( 'Comments', 'npcink-workflow-toolbox' ); ?></button>
						<button type="button" class="npcink-toolbox__ops-tab" data-toolbox-ops-target="structure" aria-selected="false"><?php esc_html_e( 'Structure', 'npcink-workflow-toolbox' ); ?></button>
						<button type="button" class="npcink-toolbox__ops-tab" data-toolbox-ops-target="findings" aria-selected="false"><?php esc_html_e( 'Findings', 'npcink-workflow-toolbox' ); ?></button>
						<button type="button" class="npcink-toolbox__ops-tab" data-toolbox-ops-target="evidence" aria-selected="false"><?php esc_html_e( 'Evidence', 'npcink-workflow-toolbox' ); ?></button>
						<button type="button" class="npcink-toolbox__ops-tab" data-toolbox-ops-target="cloud" aria-selected="false"><?php esc_html_e( 'Cloud detail', 'npcink-workflow-toolbox' ); ?></button>
						<button type="button" class="npcink-toolbox__ops-tab" data-toolbox-ops-target="advanced" aria-selected="false"><?php esc_html_e( 'Advanced', 'npcink-workflow-toolbox' ); ?></button>
					</nav>
					<section class="npcink-toolbox__ops-panel" data-toolbox-ops-panel="overview">
						<?php $this->render_site_ops_operator_brief( $findings, $summary, $cloud_analysis, $cloud_ready ); ?>
						<?php $this->render_site_ops_decision_queue( $findings ); ?>
						<?php $this->render_site_ops_handling_path_panel( $findings ); ?>
						<details class="npcink-toolbox__ops-scan-details">
							<summary>
								<strong><?php esc_html_e( 'View scan scope and charts', 'npcink-workflow-toolbox' ); ?></strong>
								<span>
									<?php
									printf(
										/* translators: 1: number of scanned posts/pages, 2: number of scanned media items, 3: number of sampled comments, 4: number of findings. */
										esc_html__( '%1$d posts/pages, %2$d media, %3$d comments, %4$d findings', 'npcink-workflow-toolbox' ),
										(int) ( $summary['scanned_posts'] ?? 0 ),
										(int) ( $summary['scanned_media'] ?? 0 ),
										(int) ( $summary['recent_comment_sample'] ?? 0 ),
										(int) ( $summary['top_finding_count'] ?? $finding_count )
									);
									?>
								</span>
							</summary>
							<?php $this->render_site_ops_local_analysis_summary( $summary, $findings ); ?>
						<?php $this->render_site_ops_visual_summary( $summary, $findings ); ?>
					</details>
					<?php if ( array() === $findings ) : ?>
						<div class="npcink-toolbox__result-notice is-success"><?php esc_html_e( 'No priority site check findings were produced from this bounded local sample.', 'npcink-workflow-toolbox' ); ?></div>
					<?php endif; ?>
					</section>
					<section class="npcink-toolbox__ops-panel" data-toolbox-ops-panel="content" hidden>
						<?php $this->render_site_ops_dimension_panel( __( 'Content analysis', 'npcink-workflow-toolbox' ), __( 'Posts and pages: freshness, depth, metadata, and internal paths.', 'npcink-workflow-toolbox' ), $summary, $findings, array( 'content_freshness', 'content_quality', 'internal_link_health', 'metadata' ) ); ?>
					</section>
					<section class="npcink-toolbox__ops-panel" data-toolbox-ops-panel="media" hidden>
						<?php $this->render_site_ops_dimension_panel( __( 'Media analysis', 'npcink-workflow-toolbox' ), __( 'Image attachments and referenced media metadata, including ALT and captions.', 'npcink-workflow-toolbox' ), $summary, $findings, array( 'media' ) ); ?>
					</section>
					<section class="npcink-toolbox__ops-panel" data-toolbox-ops-panel="comments" hidden>
						<?php $this->render_site_ops_dimension_panel( __( 'Comment analysis', 'npcink-workflow-toolbox' ), __( 'Approved comment signals, question-like comments, long comments, and pending moderation load.', 'npcink-workflow-toolbox' ), $summary, $findings, array( 'comments' ) ); ?>
					</section>
					<section class="npcink-toolbox__ops-panel" data-toolbox-ops-panel="structure" hidden>
						<?php $this->render_site_ops_dimension_panel( __( 'Structure analysis', 'npcink-workflow-toolbox' ), __( 'Taxonomy shape, Site Context readiness, and Cloud-managed Site Knowledge readiness.', 'npcink-workflow-toolbox' ), $summary, $findings, array( 'taxonomy', 'site_context', 'site_knowledge' ) ); ?>
					</section>
					<section class="npcink-toolbox__ops-panel" data-toolbox-ops-panel="findings" hidden>
						<?php if ( array() === $findings ) : ?>
							<div class="npcink-toolbox__result-notice is-success"><?php esc_html_e( 'No priority site check findings were produced from this bounded local sample.', 'npcink-workflow-toolbox' ); ?></div>
						<?php else : ?>
							<div class="npcink-toolbox__ops-priority-list">
								<?php foreach ( $findings as $finding ) : ?>
									<?php $this->render_site_ops_finding_row( $finding ); ?>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</section>
					<section class="npcink-toolbox__ops-panel" data-toolbox-ops-panel="evidence" hidden>
						<?php $this->render_site_ops_evidence_panel( $findings ); ?>
					</section>
					<section class="npcink-toolbox__ops-panel" data-toolbox-ops-panel="cloud" hidden>
						<?php if ( $has_cloud_analysis ) : ?>
							<?php $this->render_site_ops_cloud_analysis_result( $cloud_analysis ); ?>
						<?php else : ?>
							<div class="npcink-toolbox__result-notice">
								<?php esc_html_e( 'Cloud detail has not run for this local preview. Use it only when AI summary, semantic ranking, trend explanation, or heavier runtime/detail analysis is needed.', 'npcink-workflow-toolbox' ); ?>
								<?php if ( $cloud_ready ) : ?>
									<p><a class="button" href="<?php echo esc_url( $this->site_ops_cloud_analysis_url() ); ?>"><?php esc_html_e( 'Use Cloud detail', 'npcink-workflow-toolbox' ); ?></a></p>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</section>
					<section class="npcink-toolbox__ops-panel" data-toolbox-ops-panel="advanced" hidden>
						<details class="npcink-toolbox__result-details">
							<summary><?php esc_html_e( 'Copy site check JSON', 'npcink-workflow-toolbox' ); ?></summary>
							<p class="description"><?php esc_html_e( 'This local preview is not stored automatically and does not create a run, queue, Core proposal, or WordPress write.', 'npcink-workflow-toolbox' ); ?></p>
							<p><button type="button" class="button" data-toolbox-copy-json><?php esc_html_e( 'Copy JSON to clipboard', 'npcink-workflow-toolbox' ); ?></button></p>
							<textarea class="large-text code" rows="12" readonly data-toolbox-copy-json-source><?php echo esc_textarea( (string) wp_json_encode( $pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
						</details>
						<?php if ( array() !== $cloud_request ) : ?>
							<details class="npcink-toolbox__result-details">
								<summary><?php esc_html_e( 'Copy Cloud detail request JSON', 'npcink-workflow-toolbox' ); ?></summary>
								<p class="description"><?php esc_html_e( 'This contract is prepared for Cloud runtime detail. Copying it does not call Cloud, schedule work, store a local run, create Core proposals, or write WordPress data.', 'npcink-workflow-toolbox' ); ?></p>
								<p><button type="button" class="button" data-toolbox-copy-json><?php esc_html_e( 'Copy JSON to clipboard', 'npcink-workflow-toolbox' ); ?></button></p>
								<textarea class="large-text code" rows="12" readonly data-toolbox-copy-json-source><?php echo esc_textarea( (string) wp_json_encode( $cloud_request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
							</details>
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'No local trend chart is shown because Toolbox does not store historical Site Check runs. Cross-run trend analysis belongs in Cloud runtime/detail output.', 'npcink-workflow-toolbox' ); ?></p>
					</section>
				</div>
			<?php endif; ?>
		</section>
			<?php $this->render_comment_moderation_review_tool(); ?>
			<?php $this->render_taxonomy_tag_review_tool(); ?>
			<?php $this->render_internal_link_review_tool(); ?>
		</section>

			<section class="npcink-toolbox__ops-panel" data-toolbox-site-check-panel="scheduled-review"<?php echo 'scheduled-review' === $active_site_check_tab ? '' : ' hidden'; ?>>
				<?php $this->render_morning_brief_panel( $settings, $cloud_ready, $nightly_preview, true ); ?>
			</section>
		</div>
		<?php
	}

	private function nightly_inspection_preview_url(): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'                       => self::MENU_SLUG,
					'toolbox_tab'                => 'operations-insights',
					'site_check_tab'             => 'scheduled-review',
					'nightly_inspection_preview' => '1',
				),
				admin_url( 'admin.php' )
			),
			'npcink_toolbox_nightly_inspection_preview'
		);
	}

	private function scheduled_review_dry_run_download_url(): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'npcink_toolbox_download_scheduled_review_dry_run',
				),
				admin_url( 'admin-post.php' )
			),
			'npcink_toolbox_download_scheduled_review_dry_run'
		);
	}

	public function download_scheduled_review_dry_run(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to download this scheduled review preview.', 'npcink-workflow-toolbox' ),
				esc_html__( 'Permission denied', 'npcink-workflow-toolbox' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( 'npcink_toolbox_download_scheduled_review_dry_run' );

		try {
			$collector = new Snapshot_Collector();
			$planner   = new Manual_Dry_Run_Planner();
			$replay    = $planner->plan( $collector->collect() );
		} catch ( \Throwable $throwable ) {
			wp_die(
				esc_html__( 'Could not build the local scheduled review preview.', 'npcink-workflow-toolbox' ),
				esc_html__( 'Scheduled review download failed', 'npcink-workflow-toolbox' ),
				array( 'response' => 500 )
			);
		}

		if ( ! headers_sent() ) {
			$charset = (string) get_option( 'blog_charset', 'UTF-8' );
			header( 'Content-Type: application/json; charset=' . ( '' !== $charset ? $charset : 'UTF-8' ) );
			header( 'Content-Disposition: attachment; filename="scheduled-review-dry-run.json"' );
			header( 'X-Content-Type-Options: nosniff' );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download response; HTML escaping would corrupt the file.
		echo (string) wp_json_encode( $replay, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function nightly_inspection_preview_from_request(): ?array {
		$requested = $this->query_text_param( 'nightly_inspection_preview' );
		if ( '1' !== $requested ) {
			return null;
		}

		$nonce = $this->query_text_param( '_wpnonce' );
		if ( ! wp_verify_nonce( $nonce, 'npcink_toolbox_nightly_inspection_preview' ) ) {
			return array(
				'error' => __( 'The scheduled review preview link expired. Reload the page and try again.', 'npcink-workflow-toolbox' ),
			);
		}

		try {
			$collector = new Snapshot_Collector();
			$planner   = new Manual_Dry_Run_Planner();
			$snapshot  = $collector->collect();
			$replay    = $planner->plan( $snapshot );

			return array(
				'snapshot' => $snapshot,
				'replay'   => $replay,
			);
		} catch ( \Throwable $throwable ) {
			return array(
				'error' => __( 'Could not build the local scheduled review preview.', 'npcink-workflow-toolbox' ),
			);
		}
	}

	/**
	 * @param array<string,mixed>|null $preview Preview payload.
	 */
	private function render_nightly_inspection_preview( ?array $preview ): void {
		if ( null === $preview ) {
			return;
		}

		if ( isset( $preview['error'] ) ) {
			?>
			<section class="npcink-toolbox__card" data-toolbox-nightly-inspection-preview>
				<h3><?php esc_html_e( 'Scheduled review preview', 'npcink-workflow-toolbox' ); ?></h3>
				<div class="npcink-toolbox__result-notice is-warning"><?php echo esc_html( (string) $preview['error'] ); ?></div>
			</section>
			<?php
			return;
		}

		$replay            = isset( $preview['replay'] ) && is_array( $preview['replay'] ) ? $preview['replay'] : array();
		$download          = $this->scheduled_review_dry_run_download_url();
		$brief             = isset( $replay['preview']['morning_brief'] ) && is_array( $replay['preview']['morning_brief'] ) ? $replay['preview']['morning_brief'] : array();
		$summary           = isset( $brief['summary'] ) && is_array( $brief['summary'] ) ? $brief['summary'] : array();
		$review_item_count = (int) ( $summary['actions_total'] ?? 0 );
		?>
		<section class="npcink-toolbox__card" data-toolbox-nightly-inspection-preview>
			<div class="npcink-toolbox__section-heading">
				<div>
					<h3><?php esc_html_e( 'Scheduled review preview', 'npcink-workflow-toolbox' ); ?></h3>
					<p><?php esc_html_e( 'Manual dry-run only. The preview reads local content, produces review signals, and does not schedule, call Cloud, create Core proposals, or write WordPress data.', 'npcink-workflow-toolbox' ); ?></p>
				</div>
				<a class="button" href="<?php echo esc_url( $this->nightly_inspection_preview_url() ); ?>"><?php esc_html_e( 'Refresh preview', 'npcink-workflow-toolbox' ); ?></a>
			</div>
			<div class="npcink-toolbox__readiness-strip" aria-label="<?php esc_attr_e( 'Scheduled review preview summary', 'npcink-workflow-toolbox' ); ?>">
				<?php
				$this->render_start_status_item( __( 'Scanned posts', 'npcink-workflow-toolbox' ), 'neutral', (string) (int) ( $summary['scanned_posts'] ?? 0 ), __( 'Oldest modified public posts and pages.', 'npcink-workflow-toolbox' ) );
				$this->render_start_status_item( __( 'Scanned media', 'npcink-workflow-toolbox' ), 'neutral', (string) (int) ( $summary['scanned_media'] ?? 0 ), __( 'Recent image attachments.', 'npcink-workflow-toolbox' ) );
				$this->render_start_status_item( __( 'Preview signals', 'npcink-workflow-toolbox' ), (int) ( $summary['actions_total'] ?? 0 ) > 0 ? 'warning' : 'ok', (string) (int) ( $summary['actions_total'] ?? 0 ), __( 'Read-only hints; not tasks.', 'npcink-workflow-toolbox' ) );
				$this->render_start_status_item( __( 'Execution', 'npcink-workflow-toolbox' ), 'ok', __( 'Disabled', 'npcink-workflow-toolbox' ), __( 'No cron, worker, Cloud call, Core proposal, or write.', 'npcink-workflow-toolbox' ) );
				?>
			</div>
			<?php if ( $review_item_count <= 0 ) : ?>
				<div class="npcink-toolbox__result-notice is-success"><?php esc_html_e( 'No priority review items were found in this bounded preview.', 'npcink-workflow-toolbox' ); ?></div>
			<?php else : ?>
				<div class="npcink-toolbox__result-notice">
					<?php
					printf(
						/* translators: %d: number of preview signals. */
						esc_html__( 'Generated %d preview signals. This only confirms scheduled review can read local content; use Current check for ordinary site maintenance.', 'npcink-workflow-toolbox' ),
						absint( $review_item_count )
					);
					?>
				</div>
			<?php endif; ?>
			<details class="npcink-toolbox__result-details">
				<summary><?php esc_html_e( 'Advanced: download dry-run JSON', 'npcink-workflow-toolbox' ); ?></summary>
				<p class="description"><?php esc_html_e( 'Download the replay payload only for support or debugging. It is not embedded in this page, saved automatically, or used to create scheduled work.', 'npcink-workflow-toolbox' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( $download ); ?>"><?php esc_html_e( 'Download dry-run JSON', 'npcink-workflow-toolbox' ); ?></a></p>
			</details>
		</section>
		<?php
	}

	private function render_nightly_inspection_basic_settings( array $settings ): void {
		$latest_preview = Basic_WP_Cron_Dry_Run::latest_preview();
		$brief          = isset( $latest_preview['preview']['morning_brief'] ) && is_array( $latest_preview['preview']['morning_brief'] ) ? $latest_preview['preview']['morning_brief'] : array();
		$summary        = isset( $brief['summary'] ) && is_array( $brief['summary'] ) ? $brief['summary'] : array();
		?>
		<section class="npcink-toolbox__card" data-toolbox-nightly-inspection-basic-settings>
			<div class="npcink-toolbox__section-heading">
				<div>
					<h3><?php esc_html_e( 'Local fallback preview (optional)', 'npcink-workflow-toolbox' ); ?></h3>
					<p><?php esc_html_e( 'Optional WordPress-side WP-Cron dry-run fallback. Use it only when Cloud is unavailable or during trials; it is not the Cloud scheduled inspection.', 'npcink-workflow-toolbox' ); ?></p>
				</div>
			</div>
			<form class="npcink-toolbox__settings-form" method="post" action="options.php">
				<?php settings_fields( 'npcink_toolbox' ); ?>
				<?php if ( ! empty( $settings['include_raw_responses'] ) ) : ?>
					<input type="hidden" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[include_raw_responses]" value="1" />
				<?php endif; ?>
				<?php if ( ! empty( $settings['enable_image_source'] ) ) : ?>
					<input type="hidden" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[enable_image_source]" value="1" />
				<?php endif; ?>
				<label class="npcink-toolbox__check">
					<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[nightly_inspection_enabled]" value="1" <?php checked( ! empty( $settings['nightly_inspection_enabled'] ) ); ?> />
					<span><?php esc_html_e( 'Enable optional local WP-Cron fallback preview', 'npcink-workflow-toolbox' ); ?></span>
				</label>
				<div class="npcink-toolbox__split">
					<label>
						<span><?php esc_html_e( 'Run time', 'npcink-workflow-toolbox' ); ?></span>
						<input type="time" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[nightly_inspection_time]" value="<?php echo esc_attr( (string) $settings['nightly_inspection_time'] ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Post/page scan limit', 'npcink-workflow-toolbox' ); ?></span>
						<input type="number" min="1" max="50" step="1" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[nightly_inspection_post_limit]" value="<?php echo esc_attr( (string) $settings['nightly_inspection_post_limit'] ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Media scan limit', 'npcink-workflow-toolbox' ); ?></span>
						<input type="number" min="1" max="50" step="1" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[nightly_inspection_media_limit]" value="<?php echo esc_attr( (string) $settings['nightly_inspection_media_limit'] ); ?>" />
					</label>
				</div>
				<?php submit_button( __( 'Save local fallback', 'npcink-workflow-toolbox' ) ); ?>
			</form>
			<?php if ( array() !== $latest_preview ) : ?>
				<div class="npcink-toolbox__result-notice is-success">
					<?php
					printf(
						/* translators: 1: generated time, 2: action count. */
						esc_html__( 'Latest scheduled dry-run preview: %1$s, %2$d review items.', 'npcink-workflow-toolbox' ),
						esc_html( (string) ( $latest_preview['generated_at'] ?? '' ) ),
						(int) ( $summary['actions_total'] ?? 0 )
					);
					?>
				</div>
			<?php else : ?>
				<div class="npcink-toolbox__result-notice is-neutral"><?php esc_html_e( 'No cron dry-run preview has been generated yet.', 'npcink-workflow-toolbox' ); ?></div>
			<?php endif; ?>
		</section>
		<?php
	}

	private function render_start_status_item( string $title, string $status, string $label, string $description ): void {
		?>
		<div class="npcink-toolbox__readiness-item is-<?php echo esc_attr( $status ); ?>">
			<span><?php echo esc_html( $title ); ?></span>
			<strong><?php echo esc_html( $label ); ?></strong>
			<small><?php echo esc_html( $description ); ?></small>
		</div>
		<?php
	}

	private function render_start_status_row( string $title, string $status, string $label, string $description, string $anchor_id = '' ): void {
		?>
		<div class="npcink-toolbox__start-status-row is-<?php echo esc_attr( $status ); ?>"<?php echo '' !== $anchor_id ? ' id="' . esc_attr( $anchor_id ) . '"' : ''; ?>>
			<div>
				<span><?php echo esc_html( $title ); ?></span>
				<strong><?php echo esc_html( $label ); ?></strong>
			</div>
			<small><?php echo esc_html( $description ); ?></small>
		</div>
		<?php
	}

	private function render_cloud_runtime_notice(): void {
		?>
		<div class="npcink-toolbox__result-notice is-warning">
			<strong><?php esc_html_e( 'AI service is not connected.', 'npcink-workflow-toolbox' ); ?></strong>
			<span>
				<?php esc_html_e( 'AI-powered search, image suggestions, content library search, and hosted checks stay unavailable until the service is connected. Basic site profile editing remains available.', 'npcink-workflow-toolbox' ); ?>
				<?php echo esc_html( $this->cloud_runtime_unavailable_reason_label() ); ?>
			</span>
			<p style="margin:8px 0 0;">
				<?php if ( $this->is_suite_submenu_registered( 'npcink-cloud-addon' ) ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( $this->cloud_addon_details_url() ); ?>"><?php esc_html_e( 'Open Cloud Addon settings', 'npcink-workflow-toolbox' ); ?></a>
				<?php else : ?>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Install the Cloud Addon plugin', 'npcink-workflow-toolbox' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	private function cloud_runtime_unavailable_reason_label(): string {
		$reason = $this->settings->cloud_runtime_unavailable_reason();

		if ( 'cloud_addon_not_installed' === $reason ) {
			return __( 'Install and connect the Cloud Addon to enable hosted execution.', 'npcink-workflow-toolbox' );
		}

		if ( 'cloud_addon_not_connected' === $reason ) {
			return __( 'Save and verify Cloud Addon credentials to enable hosted execution.', 'npcink-workflow-toolbox' );
		}

		return __( 'Check Cloud Addon transport before running hosted execution.', 'npcink-workflow-toolbox' );
	}

	private function render_morning_brief_panel( array $settings, bool $cloud_ready, ?array $nightly_preview, bool $embedded = false ): void {
		?>
		<?php if ( ! $embedded ) : ?>
			<div class="npcink-toolbox__panel-header">
				<h2><?php esc_html_e( 'Scheduled review', 'npcink-workflow-toolbox' ); ?></h2>
				<p><?php esc_html_e( 'Low-frequency scheduled-review preview only. Daily site maintenance starts with Current check.', 'npcink-workflow-toolbox' ); ?></p>
			</div>
		<?php endif; ?>
		<?php
		if ( ! $cloud_ready ) {
			$this->render_cloud_runtime_notice();
		}
		?>
		<div class="npcink-toolbox__cloud-check-workspace" data-toolbox-morning-brief>
			<section class="npcink-toolbox__card">
				<div class="npcink-toolbox__section-heading">
					<div>
						<h3><?php esc_html_e( 'Scheduled review status', 'npcink-workflow-toolbox' ); ?></h3>
						<p><?php esc_html_e( 'Use this only to preview whether the scheduled review can read local content. Cloud run history and recovery open in Cloud Addon.', 'npcink-workflow-toolbox' ); ?></p>
					</div>
					<div class="npcink-toolbox__inline-actions">
						<a class="button button-primary" href="<?php echo esc_url( $this->nightly_inspection_preview_url() ); ?>"><?php esc_html_e( 'Preview scheduled review', 'npcink-workflow-toolbox' ); ?></a>
						<?php if ( ! $embedded ) : ?>
							<a class="button" href="<?php echo esc_url( $this->site_ops_insights_preview_url() ); ?>"><?php esc_html_e( 'Open current check', 'npcink-workflow-toolbox' ); ?></a>
						<?php endif; ?>
						<a class="button" href="<?php echo esc_url( $this->cloud_addon_runtime_runs_url() ); ?>"><?php esc_html_e( 'Open Cloud run recovery', 'npcink-workflow-toolbox' ); ?></a>
					</div>
				</div>
			</section>
			<?php $this->render_nightly_inspection_preview( $nightly_preview ); ?>
			<details class="npcink-toolbox__start-advanced">
				<summary>
					<span><?php esc_html_e( 'Advanced: optional local fallback preview', 'npcink-workflow-toolbox' ); ?></span>
					<small><?php esc_html_e( 'WP-Cron fallback settings stay here because they control local WordPress dry-run preview only.', 'npcink-workflow-toolbox' ); ?></small>
				</summary>
				<?php $this->render_nightly_inspection_basic_settings( $settings ); ?>
			</details>
		</div>
		<?php
	}

	private function render_content_context_form( array $context ): void {
		$proposal_fields = array(
			'seo_title'             => __( 'SEO title', 'npcink-workflow-toolbox' ),
			'seo_description'       => __( 'SEO description', 'npcink-workflow-toolbox' ),
			'slug'                  => __( 'Slug', 'npcink-workflow-toolbox' ),
			'excerpt'               => __( 'Excerpt', 'npcink-workflow-toolbox' ),
			'faq'                   => __( 'FAQ', 'npcink-workflow-toolbox' ),
			'answer_summary'        => __( 'Answer summary', 'npcink-workflow-toolbox' ),
			'geo_summary'           => __( 'GEO summary', 'npcink-workflow-toolbox' ),
			'structured_data_hints' => __( 'Structured data hints', 'npcink-workflow-toolbox' ),
		);
		$preview         = wp_json_encode( $this->settings->get_content_context_for_ability(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		?>
		<div class="npcink-toolbox__panel-header">
			<h2><?php esc_html_e( 'Site Profile', 'npcink-workflow-toolbox' ); ?></h2>
			<p><?php esc_html_e( 'Fill the short site brief used by AI suggestions. Most sites can keep the optional preferences unchanged.', 'npcink-workflow-toolbox' ); ?></p>
		</div>

		<form class="npcink-toolbox__settings-form" method="post" action="options.php" data-toolbox-context-form>
			<?php settings_fields( 'npcink_toolbox_content_context' ); ?>

			<div class="npcink-toolbox__draft-actions" aria-label="<?php esc_attr_e( 'Content context draft actions', 'npcink-workflow-toolbox' ); ?>">
				<button type="button" class="button" data-toolbox-context-draft="aiBlog"><?php esc_html_e( 'Use AI tech blog template', 'npcink-workflow-toolbox' ); ?></button>
				<button type="button" class="button" data-toolbox-context-draft="site"><?php esc_html_e( 'Draft from current site content', 'npcink-workflow-toolbox' ); ?></button>
				<button type="button" class="button" data-toolbox-context-clear><?php esc_html_e( 'Clear form', 'npcink-workflow-toolbox' ); ?></button>
				<span><?php esc_html_e( 'Drafts only prefill this form. They do not change posts, media, SEO meta, or provider settings.', 'npcink-workflow-toolbox' ); ?></span>
			</div>

			<div class="npcink-toolbox__context-workspace" data-toolbox-context-sections>
				<section class="npcink-toolbox__context-brief-panel">
					<div class="npcink-toolbox__section-heading">
						<div>
							<h3><?php esc_html_e( 'Site brief', 'npcink-workflow-toolbox' ); ?></h3>
							<p><?php esc_html_e( 'These four fields are enough for normal operation.', 'npcink-workflow-toolbox' ); ?></p>
						</div>
					</div>
					<div class="npcink-toolbox__context-brief-grid">
						<?php $this->render_context_textarea( 'site_positioning', __( 'Site positioning', 'npcink-workflow-toolbox' ), $context ); ?>
						<?php $this->render_context_list_field( 'target_audience', __( 'Target audience', 'npcink-workflow-toolbox' ), $context ); ?>
						<?php $this->render_context_textarea( 'brand_voice', __( 'Brand voice', 'npcink-workflow-toolbox' ), $context ); ?>
						<?php $this->render_context_list_field( 'primary_keywords', __( 'Primary keywords', 'npcink-workflow-toolbox' ), $context ); ?>
					</div>
				</section>

					<details class="npcink-toolbox__context-advanced">
						<summary>
							<span><?php esc_html_e( 'Optional AI suggestion preferences', 'npcink-workflow-toolbox' ); ?></span>
							<small><?php esc_html_e( 'Keep defaults unless a support or editorial policy needs tighter guidance.', 'npcink-workflow-toolbox' ); ?></small>
						</summary>
					<nav class="npcink-toolbox__context-tabs" aria-label="<?php esc_attr_e( 'Advanced content context sections', 'npcink-workflow-toolbox' ); ?>">
						<button type="button" class="npcink-toolbox__context-tab is-active" data-toolbox-context-target="seo" aria-selected="true">
							<span><?php esc_html_e( 'SEO', 'npcink-workflow-toolbox' ); ?></span>
							<small><?php esc_html_e( 'Search snippets', 'npcink-workflow-toolbox' ); ?></small>
						</button>
						<button type="button" class="npcink-toolbox__context-tab" data-toolbox-context-target="aeo" aria-selected="false">
							<span><?php esc_html_e( 'AEO', 'npcink-workflow-toolbox' ); ?></span>
							<small><?php esc_html_e( 'Answer shape', 'npcink-workflow-toolbox' ); ?></small>
						</button>
						<button type="button" class="npcink-toolbox__context-tab" data-toolbox-context-target="geo" aria-selected="false">
							<span><?php esc_html_e( 'GEO', 'npcink-workflow-toolbox' ); ?></span>
							<small><?php esc_html_e( 'AI citation signals', 'npcink-workflow-toolbox' ); ?></small>
						</button>
						<button type="button" class="npcink-toolbox__context-tab" data-toolbox-context-target="boundaries" aria-selected="false">
							<span><?php esc_html_e( 'Boundaries', 'npcink-workflow-toolbox' ); ?></span>
							<small><?php esc_html_e( 'Claims and preview', 'npcink-workflow-toolbox' ); ?></small>
						</button>
					</nav>

					<div class="npcink-toolbox__context-panels">
					<section class="npcink-toolbox__card" data-toolbox-context-panel="seo">
						<h2><?php esc_html_e( 'SEO', 'npcink-workflow-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Control search-oriented metadata, keyword coverage, and which SEO fields AI may suggest.', 'npcink-workflow-toolbox' ); ?></p>
						<div class="npcink-toolbox__context-group-workspace" data-toolbox-context-groups>
							<nav class="npcink-toolbox__context-group-list" aria-label="<?php esc_attr_e( 'SEO fields', 'npcink-workflow-toolbox' ); ?>">
								<button type="button" class="npcink-toolbox__context-group-button is-active" data-toolbox-context-group-target="seo-keywords" aria-selected="true">
									<span><?php esc_html_e( 'Keywords', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Long-tail terms', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="seo-rules" aria-selected="false">
									<span><?php esc_html_e( 'Rules', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Search guidance', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="seo-fields" aria-selected="false">
									<span><?php esc_html_e( 'Suggestion fields', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Allowed output', 'npcink-workflow-toolbox' ); ?></small>
								</button>
							</nav>
							<div class="npcink-toolbox__context-group-panels">
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="seo-keywords">
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'SEO keywords', 'npcink-workflow-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Add supporting long-tail phrases here. Primary keywords stay in the Brief section so the first setup path remains obvious.', 'npcink-workflow-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_list_field( 'long_tail_keywords', __( 'Long-tail keywords', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="seo-rules" hidden>
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'SEO rules', 'npcink-workflow-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Describe title, description, slug, excerpt, and internal-link preferences for proposal-ready suggestions.', 'npcink-workflow-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_textarea( 'seo_rules', __( 'SEO rules', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="seo-fields" hidden>
									<fieldset class="npcink-toolbox__check-grid">
										<legend><?php esc_html_e( 'SEO fields AI may suggest', 'npcink-workflow-toolbox' ); ?></legend>
										<?php foreach ( array( 'seo_title', 'seo_description', 'slug', 'excerpt' ) as $field ) : ?>
											<?php $this->render_proposal_field_checkbox( $field, $proposal_fields[ $field ], $context ); ?>
										<?php endforeach; ?>
									</fieldset>
								</section>
							</div>
						</div>
					</section>

					<section class="npcink-toolbox__card" data-toolbox-context-panel="aeo" hidden>
						<h2><?php esc_html_e( 'AEO', 'npcink-workflow-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Shape answer-engine output: direct answers, FAQs, definitions, and step-style responses.', 'npcink-workflow-toolbox' ); ?></p>
						<div class="npcink-toolbox__context-group-workspace" data-toolbox-context-groups>
							<nav class="npcink-toolbox__context-group-list" aria-label="<?php esc_attr_e( 'AEO fields', 'npcink-workflow-toolbox' ); ?>">
								<button type="button" class="npcink-toolbox__context-group-button is-active" data-toolbox-context-group-target="aeo-rules" aria-selected="true">
									<span><?php esc_html_e( 'Rules', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Answer guidance', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="aeo-toggles" aria-selected="false">
									<span><?php esc_html_e( 'Output toggles', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'FAQ and summary', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="aeo-fields" aria-selected="false">
									<span><?php esc_html_e( 'Suggestion fields', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Allowed output', 'npcink-workflow-toolbox' ); ?></small>
								</button>
							</nav>
							<div class="npcink-toolbox__context-group-panels">
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="aeo-rules">
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'AEO rules', 'npcink-workflow-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Start with a direct answer, then add conditions, steps, limits, and short followups.', 'npcink-workflow-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_textarea( 'aeo_rules', __( 'AEO rules', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="aeo-toggles" hidden>
									<?php $this->render_context_checkbox( 'allow_faq_generation', __( 'Allow FAQ suggestions', 'npcink-workflow-toolbox' ), $context ); ?>
									<?php $this->render_context_checkbox( 'allow_aeo_summary', __( 'Allow AEO answer summary suggestions', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="aeo-fields" hidden>
									<fieldset class="npcink-toolbox__check-grid">
										<legend><?php esc_html_e( 'AEO fields AI may suggest', 'npcink-workflow-toolbox' ); ?></legend>
										<?php foreach ( array( 'faq', 'answer_summary' ) as $field ) : ?>
											<?php $this->render_proposal_field_checkbox( $field, $proposal_fields[ $field ], $context ); ?>
										<?php endforeach; ?>
									</fieldset>
								</section>
							</div>
						</div>
					</section>

					<section class="npcink-toolbox__card" data-toolbox-context-panel="geo" hidden>
						<h2><?php esc_html_e( 'GEO', 'npcink-workflow-toolbox' ); ?></h2>
						<p><?php esc_html_e( 'Guide AI-readable entity signals, standalone conclusions, and citation-friendly summaries.', 'npcink-workflow-toolbox' ); ?></p>
						<div class="npcink-toolbox__context-group-workspace" data-toolbox-context-groups>
							<nav class="npcink-toolbox__context-group-list" aria-label="<?php esc_attr_e( 'GEO fields', 'npcink-workflow-toolbox' ); ?>">
								<button type="button" class="npcink-toolbox__context-group-button is-active" data-toolbox-context-group-target="geo-entities" aria-selected="true">
									<span><?php esc_html_e( 'Entities', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Signals', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="geo-rules" aria-selected="false">
									<span><?php esc_html_e( 'Rules', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Summary guidance', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="geo-toggles" aria-selected="false">
									<span><?php esc_html_e( 'Output toggles', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'GEO and schema', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="geo-fields" aria-selected="false">
									<span><?php esc_html_e( 'Suggestion fields', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Allowed output', 'npcink-workflow-toolbox' ); ?></small>
								</button>
							</nav>
							<div class="npcink-toolbox__context-group-panels">
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="geo-entities">
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'Entities', 'npcink-workflow-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'List people, products, standards, projects, and concepts AI should recognize as important context.', 'npcink-workflow-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_list_field( 'entity_keywords', __( 'Entity keywords', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="geo-rules" hidden>
									<div class="npcink-toolbox__example">
										<strong><?php esc_html_e( 'GEO rules', 'npcink-workflow-toolbox' ); ?></strong>
										<span><?php esc_html_e( 'Keep key conclusions standalone, define important entities, and separate implemented facts from plans.', 'npcink-workflow-toolbox' ); ?></span>
									</div>
									<?php $this->render_context_textarea( 'geo_rules', __( 'GEO rules', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="geo-toggles" hidden>
									<?php $this->render_context_checkbox( 'allow_geo_summary', __( 'Allow GEO summary suggestions', 'npcink-workflow-toolbox' ), $context ); ?>
									<?php $this->render_context_checkbox( 'allow_structured_data_suggestions', __( 'Allow structured data suggestions', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="geo-fields" hidden>
									<fieldset class="npcink-toolbox__check-grid">
										<legend><?php esc_html_e( 'GEO fields AI may suggest', 'npcink-workflow-toolbox' ); ?></legend>
										<?php foreach ( array( 'geo_summary', 'structured_data_hints' ) as $field ) : ?>
											<?php $this->render_proposal_field_checkbox( $field, $proposal_fields[ $field ], $context ); ?>
										<?php endforeach; ?>
									</fieldset>
								</section>
							</div>
						</div>
					</section>

						<section class="npcink-toolbox__card" data-toolbox-context-panel="boundaries" hidden>
							<h2><?php esc_html_e( 'Boundaries', 'npcink-workflow-toolbox' ); ?></h2>
							<p><?php esc_html_e( 'Limit what AI can claim and inspect the read-only technical preview exposed to callers.', 'npcink-workflow-toolbox' ); ?></p>
						<div class="npcink-toolbox__context-group-workspace" data-toolbox-context-groups>
							<nav class="npcink-toolbox__context-group-list" aria-label="<?php esc_attr_e( 'Boundary fields', 'npcink-workflow-toolbox' ); ?>">
								<button type="button" class="npcink-toolbox__context-group-button is-active" data-toolbox-context-group-target="boundaries-allowed" aria-selected="true">
									<span><?php esc_html_e( 'Allowed claims', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Can say', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="boundaries-forbidden" aria-selected="false">
									<span><?php esc_html_e( 'Forbidden claims', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Must not say', 'npcink-workflow-toolbox' ); ?></small>
								</button>
								<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="boundaries-exceptions" aria-selected="false">
									<span><?php esc_html_e( 'Exceptions', 'npcink-workflow-toolbox' ); ?></span>
									<small><?php esc_html_e( 'Special cases', 'npcink-workflow-toolbox' ); ?></small>
									</button>
									<button type="button" class="npcink-toolbox__context-group-button" data-toolbox-context-group-target="boundaries-preview" aria-selected="false">
										<span><?php esc_html_e( 'Technical preview', 'npcink-workflow-toolbox' ); ?></span>
										<small><?php esc_html_e( 'Read-only data', 'npcink-workflow-toolbox' ); ?></small>
									</button>
							</nav>
							<div class="npcink-toolbox__context-group-panels">
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="boundaries-allowed">
									<?php $this->render_context_list_field( 'allowed_claims', __( 'Allowed claims', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="boundaries-forbidden" hidden>
									<?php $this->render_context_list_field( 'forbidden_claims', __( 'Forbidden claims', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="boundaries-exceptions" hidden>
									<?php $this->render_context_list_field( 'disallowed_topics', __( 'Disallowed topics', 'npcink-workflow-toolbox' ), $context ); ?>
									<?php $this->render_context_list_field( 'cautious_topics', __( 'Cautious topics', 'npcink-workflow-toolbox' ), $context ); ?>
									<?php $this->render_context_list_field( 'no_structured_output_topics', __( 'No structured output topics', 'npcink-workflow-toolbox' ), $context ); ?>
									<?php $this->render_context_list_field( 'human_confirmation_required', __( 'Human confirmation required', 'npcink-workflow-toolbox' ), $context ); ?>
								</section>
								<section class="npcink-toolbox__context-group-panel" data-toolbox-context-group-panel="boundaries-preview" hidden>
									<pre class="npcink-toolbox__result"><?php echo esc_html( (string) $preview ); ?></pre>
								</section>
							</div>
						</div>
					</section>
				</div>
				</details>
			</div>

			<p class="description"><?php esc_html_e( 'This profile guides suggestions only. WordPress changes still require review approval.', 'npcink-workflow-toolbox' ); ?></p>
			<?php submit_button( __( 'Save site profile', 'npcink-workflow-toolbox' ) ); ?>
		</form>

		<?php
	}

	private function render_tool_cards( bool $cloud_ready, string $surface = 'image' ): void {
		$tools = array(
			array(
				'surface'     => 'image',
				'group'       => __( 'Media', 'npcink-workflow-toolbox' ),
				'group_id'    => 'media',
				'id'          => 'media-batch-optimize',
				'endpoint'    => 'media-derivative-handoff',
				'title'       => __( 'Media Library Optimization', 'npcink-workflow-toolbox' ),
				'description' => __( 'Choose a range, check the expected results, then confirm once to optimize. Backups remain available for restore.', 'npcink-workflow-toolbox' ),
				'custom'      => 'media_derivative_batch',
			),
			array(
				'surface'     => 'image',
				'group'       => __( 'Image ALT Review', 'npcink-workflow-toolbox' ),
				'group_id'    => 'image-text-review',
				'id'          => 'media-alt-caption-review',
				'endpoint'    => 'ai/site-helpers',
				'title'       => __( 'Image ALT Review', 'npcink-workflow-toolbox' ),
				'description' => __( 'Build a local review preview for missing or weak ALT text. Cloud visual evidence is optional.', 'npcink-workflow-toolbox' ),
				'intent'      => 'media_alt_suggestions',
				'button'      => __( 'Build ALT review preview', 'npcink-workflow-toolbox' ),
				'custom'      => 'media_alt_caption_review',
			),
			array(
				'surface'     => 'image',
				'group'       => __( 'Flagged Media', 'npcink-workflow-toolbox' ),
				'group_id'    => 'image-flagged-review',
				'id'          => 'flagged-media-review',
				'endpoint'    => 'ai/site-helpers',
				'title'       => __( 'Flagged Media Review', 'npcink-workflow-toolbox' ),
				'description' => __( 'Ask Cloud for the stored content-safety status of recent images and review flagged ones. Read-only; deletion is not part of this stage.', 'npcink-workflow-toolbox' ),
				'intent'      => 'flagged_media_suggestions',
				'button'      => __( 'Review flagged media', 'npcink-workflow-toolbox' ),
				'custom'      => 'flagged_media_review',
			),
			array(
				'surface'     => 'image',
				'group'       => __( 'Settings', 'npcink-workflow-toolbox' ),
				'group_id'    => 'image-settings',
				'id'          => 'image-settings',
				'endpoint'    => '',
				'title'       => __( 'Settings', 'npcink-workflow-toolbox' ),
				'description' => __( 'Manage watermark templates and original-image backup retention.', 'npcink-workflow-toolbox' ),
				'custom'      => 'image_settings',
			),
		);

		$tools          = array_values(
			array_filter(
				$tools,
				static function ( array $tool ) use ( $surface ): bool {
					if ( $surface !== (string) ( $tool['surface'] ?? 'image' ) ) {
						return false;
					}
					return true;
				}
			)
		);
		$requested_tool = $this->requested_toolbox_tool();
		$active_tool_id = (string) ( $tools[0]['id'] ?? '' );
		foreach ( $tools as $tool ) {
			if ( $requested_tool === (string) ( $tool['id'] ?? '' ) ) {
				$active_tool_id = $requested_tool;
				break;
			}
		}
		$active_group_id = '';
		foreach ( $tools as $tool ) {
			if ( $active_tool_id === (string) ( $tool['id'] ?? '' ) ) {
				$active_group_id = (string) ( $tool['group_id'] ?? '' );
				break;
			}
		}

		$tool_groups  = array(
			'media'                => array(
				'title'       => __( 'Media Library Optimization', 'npcink-workflow-toolbox' ),
				'description' => __( 'Choose a range, check the expected results, then confirm once to optimize. Restore from history when needed.', 'npcink-workflow-toolbox' ),
			),
			'image-text-review'    => array(
				'title'       => __( 'Image ALT Review', 'npcink-workflow-toolbox' ),
				'description' => __( 'Inspect and edit ALT drafts locally. This stage does not submit or update media.', 'npcink-workflow-toolbox' ),
			),
			'image-flagged-review' => array(
				'title'       => __( 'Flagged Media', 'npcink-workflow-toolbox' ),
				'description' => __( 'Review Cloud content-safety flags for recent images. Read-only.', 'npcink-workflow-toolbox' ),
			),
			'image-settings'       => array(
				'title'       => __( 'Settings', 'npcink-workflow-toolbox' ),
				'description' => __( 'Watermark templates and backup retention.', 'npcink-workflow-toolbox' ),
			),
		);
		$group_counts = array();
		foreach ( $tools as $tool ) {
			$group_id = (string) ( $tool['group_id'] ?? '' );
			if ( '' === $group_id ) {
				continue;
			}
			$group_counts[ $group_id ] = (int) ( $group_counts[ $group_id ] ?? 0 ) + 1;
		}

		$surface_header = array(
			'title'             => __( 'Image Handling', 'npcink-workflow-toolbox' ),
			'description'       => __( 'Review image optimization and ALT suggestions. Full-site content opportunities start from Overview.', 'npcink-workflow-toolbox' ),
			'scope_title'       => __( 'Image tasks', 'npcink-workflow-toolbox' ),
			'scope_description' => __( 'Find images, review previews, then submit only the selected items. Nothing is written automatically.', 'npcink-workflow-toolbox' ),
		);
		?>
		<div class="npcink-toolbox__panel-header npcink-toolbox__panel-header--compact" aria-label="<?php echo esc_attr( (string) $surface_header['title'] ); ?>">
			<h2><?php echo esc_html( (string) $surface_header['title'] ); ?></h2>
		</div>
		<div class="npcink-toolbox__tool-workspace" data-toolbox-tools>
			<div class="npcink-toolbox__tool-group-tabs" aria-label="<?php esc_attr_e( 'Tool groups', 'npcink-workflow-toolbox' ); ?>">
				<?php
				$rendered_groups = array();
				foreach ( $tools as $index => $tool ) :
					$group_id = (string) ( $tool['group_id'] ?? '' );
					if ( '' === $group_id || isset( $rendered_groups[ $group_id ] ) ) {
						continue;
					}
					$rendered_groups[ $group_id ] = true;
					$group_meta                   = $tool_groups[ $group_id ] ?? array(
						'title'       => (string) ( $tool['group'] ?? '' ),
						'description' => '',
					);
					$group_classes                = array( 'npcink-toolbox__tool-group-tab' );
					if ( $active_group_id === $group_id ) {
						$group_classes[] = 'is-active';
					}
					if ( ! empty( $group_meta['secondary'] ) ) {
						$group_classes[] = 'is-secondary';
					}
					?>
					<button type="button" class="<?php echo esc_attr( implode( ' ', $group_classes ) ); ?>" data-toolbox-tool-group-target="<?php echo esc_attr( $group_id ); ?>" aria-selected="<?php echo $active_group_id === $group_id ? 'true' : 'false'; ?>">
						<span><?php echo esc_html( (string) $group_meta['title'] ); ?></span>
						<small><?php echo esc_html( (string) $group_meta['description'] ); ?></small>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="npcink-toolbox__tool-list" aria-label="<?php esc_attr_e( 'Tool actions', 'npcink-workflow-toolbox' ); ?>">
				<?php
				$rendered_groups = array();
				foreach ( $tools as $index => $tool ) :
					$group_id = (string) ( $tool['group_id'] ?? '' );
					if ( '' === $group_id ) {
						continue;
					}
					if ( ! isset( $rendered_groups[ $group_id ] ) ) :
						$rendered_groups[ $group_id ] = true;
						$group_meta                   = $tool_groups[ $group_id ] ?? array(
							'title'       => (string) ( $tool['group'] ?? '' ),
							'description' => '',
						);
						?>
						<div class="npcink-toolbox__tool-group-panel <?php echo 1 === (int) ( $group_counts[ $group_id ] ?? 0 ) ? 'is-single-tool' : ''; ?>" data-toolbox-tool-group-panel="<?php echo esc_attr( $group_id ); ?>" <?php echo $active_group_id === $group_id ? '' : 'hidden'; ?>>
							<div class="npcink-toolbox__tool-group-label">
								<span><?php echo esc_html( (string) $group_meta['title'] ); ?></span>
								<small><?php echo esc_html( (string) $group_meta['description'] ); ?></small>
							</div>
					<?php endif; ?>
						<button type="button" class="npcink-toolbox__tool-button <?php echo $active_tool_id === (string) $tool['id'] ? 'is-active' : ''; ?>" data-toolbox-tool-target="<?php echo esc_attr( (string) $tool['id'] ); ?>" data-toolbox-tool-group="<?php echo esc_attr( $group_id ); ?>" aria-selected="<?php echo $active_tool_id === (string) $tool['id'] ? 'true' : 'false'; ?>">
							<span><?php echo esc_html( (string) $tool['title'] ); ?></span>
							<small><?php echo esc_html( (string) $tool['description'] ); ?></small>
						</button>
					<?php
					$next_tool     = $tools[ $index + 1 ] ?? null;
					$next_group_id = is_array( $next_tool ) ? (string) ( $next_tool['group_id'] ?? '' ) : '';
					if ( $next_group_id !== $group_id ) :
						?>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>

			<div class="npcink-toolbox__tool-panels">
				<?php
				foreach ( $tools as $index => $tool ) {
					if ( 'content_support_flow' === (string) ( $tool['custom'] ?? '' ) ) {
						$this->render_content_support_flow_tool(
							(string) $tool['endpoint'],
							(string) $tool['title'],
							(string) $tool['description'],
							(string) $tool['id'],
							(string) $tool['intent'],
							(string) $tool['button'],
							'hosted_ai' === (string) ( $tool['powered_by'] ?? '' ),
							$active_tool_id === (string) $tool['id'],
							$cloud_ready
						);
						continue;
					}
					if ( 'media_alt_caption_review' === (string) ( $tool['custom'] ?? '' ) ) {
						$this->render_media_alt_caption_review_tool(
							(string) $tool['endpoint'],
							(string) $tool['title'],
							(string) $tool['description'],
							(string) $tool['id'],
							(string) $tool['button'],
							$active_tool_id === (string) $tool['id'],
							$cloud_ready
						);
						continue;
					}
					if ( 'flagged_media_review' === (string) ( $tool['custom'] ?? '' ) ) {
						$this->render_flagged_media_review_tool(
							(string) $tool['endpoint'],
							(string) $tool['title'],
							(string) $tool['description'],
							(string) $tool['id'],
							(string) $tool['button'],
							$active_tool_id === (string) $tool['id']
						);
						continue;
					}
					if ( 'media_derivative_batch' === (string) ( $tool['custom'] ?? '' ) ) {
						$this->render_media_derivative_batch_tool(
							(string) $tool['endpoint'],
							(string) $tool['title'],
							(string) $tool['description'],
							(string) $tool['id'],
							$active_tool_id === (string) $tool['id']
						);
						continue;
					}
					if ( 'watermark_template_library' === (string) ( $tool['custom'] ?? '' ) ) {
						$this->render_watermark_template_library( (string) $tool['id'], $active_tool_id === (string) $tool['id'] );
						continue;
					}
					if ( 'image_settings' === (string) ( $tool['custom'] ?? '' ) ) {
						$this->render_image_settings_panel( (string) $tool['id'], $active_tool_id === (string) $tool['id'] );
						continue;
					}
					$this->render_text_tool(
						(string) $tool['endpoint'],
						(string) $tool['title'],
						(string) $tool['description'],
						(string) ( $tool['field'] ?? '' ),
						(string) ( $tool['placeholder'] ?? '' ),
						(string) $tool['button'],
						$tool['extra_fields'] ?? array(),
						(string) $tool['id'],
						$active_tool_id === (string) $tool['id']
					);
				}
				?>
			</div>
		</div>
		<?php
	}

	private function render_image_settings_panel( string $tool_id, bool $active = false ): void {
		?>
		<div class="npcink-toolbox__image-settings" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
			<div class="npcink-toolbox__panel-header">
				<h2><?php esc_html_e( 'Image Settings', 'npcink-workflow-toolbox' ); ?></h2>
				<p><?php esc_html_e( 'Manage low-frequency image defaults here. Review and execution tools remain in their own tabs.', 'npcink-workflow-toolbox' ); ?></p>
			</div>
		<?php
		$this->render_media_backup_retention_settings();
		$this->render_watermark_template_library( '', true );
		?>
		</div>
		<?php
	}

	private function render_media_backup_retention_settings(): void {
		$settings = $this->settings->get_media_optimization_settings();
		?>
		<section class="npcink-toolbox__card npcink-toolbox__card--compact" data-toolbox-media-backup-retention>
			<form method="post" action="options.php" class="npcink-toolbox__settings-form">
				<?php settings_fields( 'npcink_toolbox_media_optimization' ); ?>
				<div class="npcink-toolbox__section-heading">
					<div>
						<h3><?php esc_html_e( 'Original image backup retention', 'npcink-workflow-toolbox' ); ?></h3>
						<p><?php esc_html_e( 'Backups are retained for 30 days. Choose whether cleanup requires your confirmation or runs automatically for backups created after this setting is enabled. The current Media Library image is never removed.', 'npcink-workflow-toolbox' ); ?></p>
					</div>
				</div>
				<div class="npcink-toolbox__retention-control">
				<label>
					<span><?php esc_html_e( 'Keep backups for', 'npcink-workflow-toolbox' ); ?></span>
					<span><?php esc_html_e( '30 days', 'npcink-workflow-toolbox' ); ?></span>
				</label>
				<label>
					<span><?php esc_html_e( 'Expired backup cleanup', 'npcink-workflow-toolbox' ); ?></span>
					<select name="<?php echo esc_attr( Plugin::MEDIA_OPTION_NAME ); ?>[backup_cleanup_mode]">
						<option value="manual" <?php selected( 'manual', (string) ( $settings['backup_cleanup_mode'] ?? 'manual' ) ); ?>><?php esc_html_e( 'Ask me before cleanup', 'npcink-workflow-toolbox' ); ?></option>
						<option value="automatic" <?php selected( 'automatic', (string) ( $settings['backup_cleanup_mode'] ?? 'manual' ) ); ?>><?php esc_html_e( 'Clean up automatically', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				<div class="npcink-toolbox__retention-actions">
					<?php submit_button( __( 'Save backup retention', 'npcink-workflow-toolbox' ), 'secondary', 'submit', false ); ?>
				</div>
				</div>
			</form>
		</section>
		<?php
	}

	private function watermark_template_definition( array $template, int $fallback_attachment_id = 0, string $fallback_text = 'AI' ): array {
		$type = (string) ( $template['type'] ?? '' );
		if ( ! in_array( $type, array( 'text', 'image' ), true ) ) {
			return array();
		}

		$definition = array(
			'watermark' => array(
				'type'      => $type,
				'position'  => (string) ( $template['position'] ?? 'bottom_right' ),
				'opacity'   => round( (int) ( $template['opacity'] ?? 80 ) / 100, 3 ),
				'margin_px' => (int) ( $template['margin'] ?? 24 ),
			),
		);

		if ( 'text' === $type ) {
			$definition['watermark'] = array_merge(
				$definition['watermark'],
				array(
					'text'       => (string) ( $template['text'] ?? $fallback_text ),
					'font_size'  => (int) ( $template['font_size'] ?? 48 ),
					'color'      => (string) ( $template['color'] ?? '#FFFFFF' ),
					'background' => (string) ( $template['background'] ?? 'rgba(0,0,0,0.35)' ),
				)
			);
		} else {
			$definition['watermark']['scale_percent'] = (int) ( $template['scale'] ?? 20 );
			$attachment_id                            = absint( $template['attachment_id'] ?? $fallback_attachment_id );
			if ( $attachment_id > 0 ) {
				$definition['watermark_attachment_id'] = $attachment_id;
			}
		}

		return $definition;
	}

	private function render_watermark_template_options( array $templates, string $selected, int $fallback_attachment_id = 0, bool $include_run_custom = true, string $fallback_text = 'AI' ): void {
		foreach ( $templates as $template ) {
			if ( ! is_array( $template ) || '' === (string) ( $template['id'] ?? '' ) ) {
				continue;
			}
			$id = (string) $template['id'];
			if ( ! $include_run_custom && 'custom' === $id ) {
				continue;
			}
			$definition    = $this->watermark_template_definition( $template, $fallback_attachment_id, $fallback_text );
			$attachment_id = absint( $definition['watermark_attachment_id'] ?? 0 );
			$logo_url      = $attachment_id > 0 ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
			$logo_missing  = 'image' === (string) ( $definition['watermark']['type'] ?? '' ) && ( $attachment_id <= 0 || ! $logo_url );
			?>
			<option
				value="<?php echo esc_attr( $id ); ?>"
				<?php selected( $selected, $id ); ?>
				<?php disabled( $logo_missing ); ?>
				<?php echo ! empty( $template['user_defined'] ) ? ' data-user-watermark-option="1"' : ''; ?>
				<?php echo $logo_missing ? ' data-watermark-logo-missing="1"' : ''; ?>
				<?php echo $definition ? ' data-watermark-definition="' . esc_attr( (string) wp_json_encode( $definition ) ) . '"' : ''; ?>
				<?php echo $logo_url ? ' data-watermark-logo-url="' . esc_url( (string) $logo_url ) . '"' : ''; ?>
			><?php echo esc_html( (string) ( $template['label'] ?? $id ) ); ?></option>
			<?php
		}
	}

	private function render_watermark_template_library( string $tool_id, bool $active = false ): void {
		$template_settings = $this->settings->get_watermark_template_settings();
		$media_settings    = $this->settings->get_media_optimization_settings();
		$fallback_logo_id  = absint( $media_settings['watermark_attachment_id'] ?? 0 );
		$templates         = $this->settings->media_watermark_templates();
		$custom_templates  = $template_settings['custom_templates'];
		$built_in          = array_values(
			array_filter(
				$templates,
				static function ( array $template ): bool {
					return in_array( (string) ( $template['id'] ?? '' ), array( 'subtle_text', 'prominent_text', 'logo_corner' ), true );
				}
			)
		);
		?>
		<form method="post" action="options.php" class="npcink-toolbox__card npcink-toolbox__watermark-library"<?php echo '' !== $tool_id ? ' data-toolbox-tool-panel="' . esc_attr( $tool_id ) . '"' : ''; ?> data-toolbox-watermark-library data-max-templates="20" <?php echo $active ? '' : 'hidden'; ?>>
			<?php settings_fields( 'npcink_toolbox_watermark_templates' ); ?>
			<div class="npcink-toolbox__watermark-library-heading">
				<div>
					<h2><?php esc_html_e( 'Watermark Templates', 'npcink-workflow-toolbox' ); ?></h2>
					<p><?php esc_html_e( 'Keep reusable watermarks here. Image tools only choose a template and preview the result.', 'npcink-workflow-toolbox' ); ?></p>
				</div>
				<button type="button" class="button" data-toolbox-add-watermark-template <?php disabled( count( $custom_templates ) >= 20 ); ?>><?php esc_html_e( 'Add template', 'npcink-workflow-toolbox' ); ?></button>
			</div>

			<div class="npcink-toolbox__watermark-library-summary">
				<label>
					<span><?php esc_html_e( 'Default template', 'npcink-workflow-toolbox' ); ?></span>
					<select name="<?php echo esc_attr( Plugin::WATERMARK_OPTION_NAME ); ?>[default_template]" data-toolbox-watermark-library-default>
						<?php $this->render_watermark_template_options( $templates, (string) $template_settings['default_template'], $fallback_logo_id, false ); ?>
					</select>
				</label>
				<?php
				/* translators: %d: Number of built-in watermark presets. */
				$built_in_summary = sprintf( __( '%d built-in presets', 'npcink-workflow-toolbox' ), count( $built_in ) );
				/* translators: 1: Number of custom watermark templates, 2: Maximum number of templates. */
				$custom_summary = sprintf( __( '%1$d/%2$d custom templates', 'npcink-workflow-toolbox' ), count( $custom_templates ), 20 );
				?>
				<p><span><?php echo esc_html( $built_in_summary ); ?></span> · <span data-toolbox-watermark-template-count><?php echo esc_html( $custom_summary ); ?></span></p>
			</div>

			<div class="npcink-toolbox__watermark-library-workspace">
				<div class="npcink-toolbox__watermark-library-list">
					<section>
						<h3><?php esc_html_e( 'Built-in presets', 'npcink-workflow-toolbox' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Built-in presets cannot be edited. Copy one to create your own version.', 'npcink-workflow-toolbox' ); ?></p>
						<div class="npcink-toolbox__watermark-preset-list">
							<?php foreach ( $built_in as $template ) : ?>
								<?php $definition = $this->watermark_template_definition( $template, $fallback_logo_id ); ?>
								<div class="npcink-toolbox__watermark-preset-row">
									<div><strong><?php echo esc_html( (string) $template['label'] ); ?></strong><small><?php echo esc_html( 'text' === (string) $template['type'] ? __( 'Text watermark', 'npcink-workflow-toolbox' ) : __( 'Logo watermark', 'npcink-workflow-toolbox' ) ); ?></small></div>
									<div class="npcink-toolbox__watermark-preset-actions"><button type="button" class="button-link" data-toolbox-preview-watermark-template data-template-label="<?php echo esc_attr( (string) $template['label'] ); ?>" data-template-definition="<?php echo esc_attr( (string) wp_json_encode( $definition ) ); ?>" data-template-logo-url="<?php echo esc_url( (string) wp_get_attachment_image_url( absint( $definition['watermark_attachment_id'] ?? 0 ), 'medium' ) ); ?>"><?php esc_html_e( 'Preview', 'npcink-workflow-toolbox' ); ?></button><button type="button" class="button button-small" data-toolbox-copy-watermark-template data-template-label="<?php echo esc_attr( (string) $template['label'] ); ?>" data-template-definition="<?php echo esc_attr( (string) wp_json_encode( $definition ) ); ?>"><?php esc_html_e( 'Copy', 'npcink-workflow-toolbox' ); ?></button></div>
								</div>
							<?php endforeach; ?>
						</div>
					</section>

					<section>
						<h3><?php esc_html_e( 'My templates', 'npcink-workflow-toolbox' ); ?></h3>
						<div data-toolbox-custom-watermark-template-list>
							<?php foreach ( $custom_templates as $index => $template ) : ?>
								<?php $this->render_watermark_template_editor( $template, (int) $index ); ?>
							<?php endforeach; ?>
						</div>
						<p class="npcink-toolbox__watermark-library-empty" data-toolbox-watermark-template-empty <?php echo $custom_templates ? 'hidden' : ''; ?>><?php esc_html_e( 'No custom templates yet. Copy a preset or add a blank template.', 'npcink-workflow-toolbox' ); ?></p>
					</section>
				</div>

				<aside class="npcink-toolbox__watermark-library-preview">
					<div class="npcink-toolbox__watermark-preview-heading">
						<div><h3><?php esc_html_e( 'Effect preview', 'npcink-workflow-toolbox' ); ?></h3><p><?php esc_html_e( 'Guidance only. The exact Cloud result still requires visual confirmation.', 'npcink-workflow-toolbox' ); ?></p></div>
						<button type="button" class="button button-small" data-toolbox-watermark-preview-image><?php esc_html_e( 'Choose preview image', 'npcink-workflow-toolbox' ); ?></button>
					</div>
					<div class="npcink-toolbox__watermark-preview-frame" data-toolbox-watermark-library-preview>
						<img alt="" data-toolbox-watermark-preview-image-element hidden />
						<span class="npcink-toolbox__watermark-effect" data-toolbox-watermark-library-effect></span>
					</div>
					<p class="description" data-toolbox-watermark-preview-name><?php esc_html_e( 'Select or edit a template to preview it.', 'npcink-workflow-toolbox' ); ?></p>
				</aside>
			</div>

			<template data-toolbox-watermark-template-prototype><?php $this->render_watermark_template_editor( array(), 0 ); ?></template>
			<div class="npcink-toolbox__watermark-undo" data-toolbox-watermark-undo hidden aria-live="polite"><span></span><button type="button" class="button-link" data-toolbox-watermark-undo-delete><?php esc_html_e( 'Undo', 'npcink-workflow-toolbox' ); ?></button></div>
			<div class="npcink-toolbox__watermark-save-bar">
				<span data-toolbox-watermark-save-status aria-live="polite"><?php esc_html_e( 'All changes saved', 'npcink-workflow-toolbox' ); ?></span>
				<div><button type="button" class="button-link" data-toolbox-watermark-discard hidden><?php esc_html_e( 'Discard changes', 'npcink-workflow-toolbox' ); ?></button><?php submit_button( __( 'Save watermark templates', 'npcink-workflow-toolbox' ), 'primary', 'submit', false ); ?></div>
			</div>
		</form>
		<?php
	}

	private function render_watermark_template_editor( array $template, int $index ): void {
		$template           = array_merge(
			array(
				'id'            => '',
				'label'         => '',
				'type'          => 'text',
				'text'          => 'AI',
				'attachment_id' => 0,
				'position'      => 'bottom_right',
				'opacity'       => 80,
				'scale'         => 20,
				'font_size'     => 48,
				'color'         => '#FFFFFF',
				'background'    => 'rgba(0,0,0,0.35)',
				'margin'        => 24,
			),
			$template
		);
		$base               = Plugin::WATERMARK_OPTION_NAME . '[custom_templates][' . $index . ']';
		$background_color   = $this->media_derivative_color_input_value( (string) $template['background'], '#000000' );
		$background_opacity = $this->media_derivative_background_opacity_value( (string) $template['background'] );
		$logo_url           = wp_get_attachment_image_url( absint( $template['attachment_id'] ), 'thumbnail' );
		?>
		<details class="npcink-toolbox__watermark-template-editor" data-toolbox-watermark-template-editor<?php echo $logo_url ? ' data-logo-url="' . esc_url( (string) $logo_url ) . '"' : ''; ?>>
			<summary><span data-toolbox-watermark-template-name><?php echo esc_html( (string) ( $template['label'] ?: __( 'New template', 'npcink-workflow-toolbox' ) ) ); ?></span><small data-toolbox-watermark-template-type-label><?php echo esc_html( 'image' === (string) $template['type'] ? __( 'Logo watermark', 'npcink-workflow-toolbox' ) : __( 'Text watermark', 'npcink-workflow-toolbox' ) ); ?></small></summary>
			<div class="npcink-toolbox__watermark-template-editor-body">
				<input type="hidden" name="<?php echo esc_attr( $base . '[id]' ); ?>" value="<?php echo esc_attr( (string) $template['id'] ); ?>" data-template-field="id" />
				<label><span><?php esc_html_e( 'Template name', 'npcink-workflow-toolbox' ); ?></span><input type="text" maxlength="40" name="<?php echo esc_attr( $base . '[label]' ); ?>" value="<?php echo esc_attr( (string) $template['label'] ); ?>" data-template-field="label" required /></label>
				<p class="description npcink-toolbox__field-warning" data-template-name-warning hidden><?php esc_html_e( 'Another template already uses this name.', 'npcink-workflow-toolbox' ); ?></p>
				<label><span><?php esc_html_e( 'Watermark type', 'npcink-workflow-toolbox' ); ?></span><select name="<?php echo esc_attr( $base . '[type]' ); ?>" data-template-field="type"><option value="text" <?php selected( 'text', (string) $template['type'] ); ?>><?php esc_html_e( 'Text watermark', 'npcink-workflow-toolbox' ); ?></option><option value="image" <?php selected( 'image', (string) $template['type'] ); ?>><?php esc_html_e( 'Logo watermark', 'npcink-workflow-toolbox' ); ?></option></select></label>
				<div data-template-text-fields <?php echo 'text' === (string) $template['type'] ? '' : 'hidden'; ?>>
					<label><span><?php esc_html_e( 'Watermark text', 'npcink-workflow-toolbox' ); ?></span><input type="text" maxlength="64" name="<?php echo esc_attr( $base . '[text]' ); ?>" value="<?php echo esc_attr( (string) $template['text'] ); ?>" data-template-field="text" /></label>
					<div class="npcink-toolbox__split"><label><span><?php esc_html_e( 'Font size', 'npcink-workflow-toolbox' ); ?></span><input type="number" min="8" max="256" name="<?php echo esc_attr( $base . '[font_size]' ); ?>" value="<?php echo esc_attr( (string) $template['font_size'] ); ?>" data-template-field="font_size" /></label><label><span><?php esc_html_e( 'Text color', 'npcink-workflow-toolbox' ); ?></span><input type="color" name="<?php echo esc_attr( $base . '[color]' ); ?>" value="<?php echo esc_attr( $this->media_derivative_color_input_value( (string) $template['color'], '#FFFFFF' ) ); ?>" data-template-field="color" /></label></div>
					<div class="npcink-toolbox__split"><label><span><?php esc_html_e( 'Background color', 'npcink-workflow-toolbox' ); ?></span><input type="color" name="<?php echo esc_attr( $base . '[background_color]' ); ?>" value="<?php echo esc_attr( $background_color ); ?>" data-template-field="background_color" /></label><label><span><?php esc_html_e( 'Background opacity', 'npcink-workflow-toolbox' ); ?></span><input type="range" min="0" max="100" name="<?php echo esc_attr( $base . '[background_opacity]' ); ?>" value="<?php echo esc_attr( (string) $background_opacity ); ?>" data-template-field="background_opacity" /><output><?php echo esc_html( (string) $background_opacity ); ?>%</output></label></div>
				</div>
				<div data-template-logo-fields <?php echo 'image' === (string) $template['type'] ? '' : 'hidden'; ?>>
					<input type="hidden" name="<?php echo esc_attr( $base . '[attachment_id]' ); ?>" value="<?php echo esc_attr( (string) absint( $template['attachment_id'] ) ); ?>" data-template-field="attachment_id" />
					<div class="npcink-toolbox__watermark-logo-picker"><span data-template-logo-name><?php echo $logo_url ? esc_html( basename( (string) get_attached_file( absint( $template['attachment_id'] ) ) ) ) : esc_html__( 'No logo selected', 'npcink-workflow-toolbox' ); ?></span><button type="button" class="button" data-toolbox-select-template-logo><?php esc_html_e( 'Select logo', 'npcink-workflow-toolbox' ); ?></button></div>
					<p class="description npcink-toolbox__field-warning" data-template-logo-warning <?php echo $logo_url ? 'hidden' : ''; ?>><?php esc_html_e( 'Select a local Media Library image before using this logo template.', 'npcink-workflow-toolbox' ); ?></p>
					<label><span><?php esc_html_e( 'Logo size', 'npcink-workflow-toolbox' ); ?></span><input type="range" min="1" max="100" name="<?php echo esc_attr( $base . '[scale]' ); ?>" value="<?php echo esc_attr( (string) $template['scale'] ); ?>" data-template-field="scale" /><output><?php echo esc_html( (string) $template['scale'] ); ?>%</output></label>
				</div>
				<label><span><?php esc_html_e( 'Position', 'npcink-workflow-toolbox' ); ?></span><select name="<?php echo esc_attr( $base . '[position]' ); ?>" data-template-field="position">
				<?php
				foreach ( array( 'top_left', 'top_right', 'center', 'bottom_left', 'bottom_right' ) as $position ) :
					?>
					<option value="<?php echo esc_attr( $position ); ?>" <?php selected( $position, (string) $template['position'] ); ?>><?php echo esc_html( $this->media_derivative_position_label( $position ) ); ?></option><?php endforeach; ?></select></label>
				<div class="npcink-toolbox__split"><label><span><?php esc_html_e( 'Opacity', 'npcink-workflow-toolbox' ); ?></span><input type="range" min="0" max="100" name="<?php echo esc_attr( $base . '[opacity]' ); ?>" value="<?php echo esc_attr( (string) $template['opacity'] ); ?>" data-template-field="opacity" /><output><?php echo esc_html( (string) $template['opacity'] ); ?>%</output></label><label><span><?php esc_html_e( 'Margin', 'npcink-workflow-toolbox' ); ?></span><input type="number" min="0" max="1000" name="<?php echo esc_attr( $base . '[margin]' ); ?>" value="<?php echo esc_attr( (string) $template['margin'] ); ?>" data-template-field="margin" /></label></div>
				<button type="button" class="button-link-delete" data-toolbox-delete-watermark-template><?php esc_html_e( 'Delete template', 'npcink-workflow-toolbox' ); ?></button>
			</div>
		</details>
		<?php
	}

	/**
	 * Renders the Site Check comment moderation review form.
	 *
	 * Zero-write surface: the form only requests bounded Cloud classification
	 * hints for pending comments. Toolbox never approves, marks spam, trashes,
	 * or deletes comments here; first-action links open native WordPress
	 * moderation screens.
	 */
	private function render_comment_moderation_review_tool(): void {
		?>
		<form class="npcink-toolbox__card npcink-toolbox__card--comment-moderation" data-toolbox-endpoint="ai/site-helpers" data-toolbox-comment-moderation-review
			data-toolbox-comments-queue-url="<?php echo esc_url( admin_url( 'edit-comments.php?comment_status=moderated' ) ); ?>"
			data-toolbox-comment-edit-url="<?php echo esc_url( admin_url( 'comment.php?action=editcomment' ) ); ?>">
			<div class="npcink-toolbox__section-heading">
				<div>
					<h3><?php esc_html_e( 'Comment moderation review', 'npcink-workflow-toolbox' ); ?></h3>
					<p><?php esc_html_e( 'Cloud AI classifies the newest pending comments as spam, legitimate, or uncertain, with reasons. You decide in WordPress.', 'npcink-workflow-toolbox' ); ?></p>
				</div>
			</div>
			<input type="hidden" name="intent" value="comment_moderation_suggestions" />
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Comments to review', 'npcink-workflow-toolbox' ); ?></span>
					<select name="comment_sample_size">
						<option value="20"><?php esc_html_e( '20 newest pending comments', 'npcink-workflow-toolbox' ); ?></option>
						<option value="50" selected="selected"><?php esc_html_e( '50 newest pending comments', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit-comments.php?comment_status=moderated' ) ); ?>"><?php esc_html_e( 'Open the WordPress moderation queue', 'npcink-workflow-toolbox' ); ?></a>
			</div>
			<div class="npcink-toolbox__result-notice is-pending"><?php esc_html_e( 'This review is read-only: Toolbox never approves, marks spam, trashes, or deletes comments. Handle every moderation action in WordPress.', 'npcink-workflow-toolbox' ); ?></div>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Review pending comments', 'npcink-workflow-toolbox' ); ?></button>
			<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	/**
	 * Renders the taxonomy and tag review form.
	 *
	 * Zero-write surface: asks Cloud for existing-term suggestions for a
	 * bounded sample of published posts with sparse assignments. Term
	 * assignment and new vocabulary creation stay outside Toolbox.
	 */
	private function render_taxonomy_tag_review_tool(): void {
		?>
		<form class="npcink-toolbox__card npcink-toolbox__card--taxonomy-tag" data-toolbox-endpoint="ai/site-helpers" data-toolbox-taxonomy-tag-review
			data-toolbox-post-edit-url="<?php echo esc_url( admin_url( 'post.php?action=edit' ) ); ?>">
			<div class="npcink-toolbox__section-heading">
				<div>
					<h3><?php esc_html_e( 'Taxonomy and tag review', 'npcink-workflow-toolbox' ); ?></h3>
					<p><?php esc_html_e( 'Cloud AI suggests existing categories and tags for published posts with sparse assignments. You assign in WordPress.', 'npcink-workflow-toolbox' ); ?></p>
				</div>
			</div>
			<input type="hidden" name="intent" value="taxonomy_tag_suggestions" />
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Posts to review', 'npcink-workflow-toolbox' ); ?></span>
					<select name="taxonomy_sample_size">
						<option value="10"><?php esc_html_e( '10 sparse posts', 'npcink-workflow-toolbox' ); ?></option>
						<option value="20" selected="selected"><?php esc_html_e( '20 sparse posts', 'npcink-workflow-toolbox' ); ?></option>
						<option value="50"><?php esc_html_e( '50 sparse posts', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=post' ) ); ?>"><?php esc_html_e( 'Open the WordPress posts list', 'npcink-workflow-toolbox' ); ?></a>
			</div>
			<div class="npcink-toolbox__result-notice is-pending"><?php esc_html_e( 'This review is read-only: Toolbox never assigns terms, creates terms, or updates posts. Handle every assignment in the WordPress editor.', 'npcink-workflow-toolbox' ); ?></div>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Review sparse posts', 'npcink-workflow-toolbox' ); ?></button>
			<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}
	/**
	 * Renders the internal-link review form.
	 *
	 * Zero-write surface: asks Cloud for internal-link candidates for a
	 * bounded sample of published posts with sparse linking. Link insertion
	 * stays in the WordPress editor or the sidebar's governed Apply flow.
	 */
	private function render_internal_link_review_tool(): void {
		?>
		<form class="npcink-toolbox__card npcink-toolbox__card--internal-link" data-toolbox-endpoint="ai/site-helpers" data-toolbox-internal-link-review
			data-toolbox-post-edit-url="<?php echo esc_url( admin_url( 'post.php?action=edit' ) ); ?>">
			<div class="npcink-toolbox__section-heading">
				<div>
					<h3><?php esc_html_e( 'Internal-link review', 'npcink-workflow-toolbox' ); ?></h3>
					<p><?php esc_html_e( 'Cloud AI suggests internal links for published posts with sparse linking. You insert links in WordPress.', 'npcink-workflow-toolbox' ); ?></p>
				</div>
			</div>
			<input type="hidden" name="intent" value="internal_link_suggestions" />
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Posts to review', 'npcink-workflow-toolbox' ); ?></span>
					<select name="internal_link_sample_size">
						<option value="10"><?php esc_html_e( '10 sparse posts', 'npcink-workflow-toolbox' ); ?></option>
						<option value="20" selected="selected"><?php esc_html_e( '20 sparse posts', 'npcink-workflow-toolbox' ); ?></option>
						<option value="50"><?php esc_html_e( '50 sparse posts', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=post' ) ); ?>"><?php esc_html_e( 'Open the WordPress posts list', 'npcink-workflow-toolbox' ); ?></a>
			</div>
			<div class="npcink-toolbox__result-notice is-pending"><?php esc_html_e( 'This review is read-only: Toolbox never inserts links or updates post content. Handle every insertion in the WordPress editor.', 'npcink-workflow-toolbox' ); ?></div>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Review sparse posts', 'npcink-workflow-toolbox' ); ?></button>
			<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}


	/**
	 * Renders the flagged media review form.
	 *
	 * Zero-write surface: asks Cloud for stored content-safety statuses of a
	 * bounded recent media sample and renders flagged items for manual review.
	 * Deletion, trashing, detaching, and replacement stay outside Toolbox.
	 */
	private function render_flagged_media_review_tool( string $endpoint, string $title, string $description, string $tool_id, string $button, bool $active = false ): void {
		?>
		<form class="npcink-toolbox__card npcink-toolbox__card--flagged-media" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" data-toolbox-flagged-media-review
			data-toolbox-attachment-edit-url="<?php echo esc_url( admin_url( 'post.php?action=edit' ) ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<input type="hidden" name="intent" value="flagged_media_suggestions" />
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Images to check', 'npcink-workflow-toolbox' ); ?></span>
					<select name="media_sample_size">
						<option value="20"><?php esc_html_e( '20 newest images', 'npcink-workflow-toolbox' ); ?></option>
						<option value="50" selected="selected"><?php esc_html_e( '50 newest images', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
			</div>
			<div class="npcink-toolbox__result-notice is-pending"><?php esc_html_e( 'This review is read-only: Toolbox never deletes, trashes, detaches, or replaces media. Handle flagged images manually in WordPress; deletion belongs to a future governed path.', 'npcink-workflow-toolbox' ); ?></div>
			<button type="submit" class="button button-primary"><?php echo esc_html( $button ); ?></button>
			<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_media_alt_caption_review_tool( string $endpoint, string $title, string $description, string $tool_id, string $button, bool $active = false, bool $cloud_ready = true ): void {
		?>
		<form class="npcink-toolbox__card npcink-toolbox__card--alt-review" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" data-toolbox-media-alt-caption-review <?php echo $active ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<?php if ( ! $cloud_ready ) : ?>
				<div class="npcink-toolbox__result-notice is-pending"><?php esc_html_e( 'Local metadata review is available. Connect Cloud Addon only when optional visual evidence is needed.', 'npcink-workflow-toolbox' ); ?></div>
			<?php endif; ?>
			<input type="hidden" name="intent" value="media_alt_suggestions" />
				<div class="npcink-toolbox__example is-ai">
					<strong><?php esc_html_e( 'Local review preview', 'npcink-workflow-toolbox' ); ?></strong>
					<span><?php esc_html_e( 'Toolbox prepares editable ALT drafts from available metadata and optional visual evidence. Confirmed missing ALT rows can be submitted to Core review; Toolbox does not approve, execute, or change media.', 'npcink-workflow-toolbox' ); ?></span>
				</div>
			<div class="npcink-toolbox__result-actions npcink-toolbox__alt-source-actions">
				<a class="button" href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>"><?php esc_html_e( 'Select images in Media Library', 'npcink-workflow-toolbox' ); ?></a>
				<span class="description"><?php esc_html_e( 'Use the Npcink bulk action there, or enter attachment IDs below.', 'npcink-workflow-toolbox' ); ?></span>
			</div>
			<label>
				<span><?php esc_html_e( 'Images to review', 'npcink-workflow-toolbox' ); ?></span>
				<input type="text" name="attachment_ids" data-toolbox-selected-attachment-ids placeholder="<?php esc_attr_e( 'Optional: 12, 34, 56', 'npcink-workflow-toolbox' ); ?>" />
			</label>
			<p class="description" data-toolbox-selected-attachment-summary><?php esc_html_e( 'No images selected yet. You can use the bounded sample options below.', 'npcink-workflow-toolbox' ); ?></p>
			<details class="npcink-toolbox__result-details npcink-toolbox__alt-sample-options">
				<summary><?php esc_html_e( 'Or scan a bounded sample', 'npcink-workflow-toolbox' ); ?></summary>
				<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Scan range', 'npcink-workflow-toolbox' ); ?></span>
					<select name="media_scope" data-toolbox-media-alt-scope>
						<option value="media_library_sample"><?php esc_html_e( 'Recent media library images', 'npcink-workflow-toolbox' ); ?></option>
						<option value="current_article_used_images"><?php esc_html_e( 'Images used by one article', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				<label data-toolbox-media-alt-post-field hidden>
					<span><?php esc_html_e( 'Article ID', 'npcink-workflow-toolbox' ); ?></span>
					<input type="number" name="post_id" min="1" step="1" placeholder="<?php esc_attr_e( 'Required for article images', 'npcink-workflow-toolbox' ); ?>" />
				</label>
				</div>
				<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Scan count', 'npcink-workflow-toolbox' ); ?></span>
					<select name="sample_size">
						<option value="10"><?php esc_html_e( '10 images', 'npcink-workflow-toolbox' ); ?></option>
						<option value="20"><?php esc_html_e( '20 images', 'npcink-workflow-toolbox' ); ?></option>
						<option value="30"><?php esc_html_e( '30 images', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Rows to review', 'npcink-workflow-toolbox' ); ?></span>
					<select name="review_set_limit">
						<option value="5"><?php esc_html_e( '5 images', 'npcink-workflow-toolbox' ); ?></option>
						<option value="10"><?php esc_html_e( '10 images', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				</div>
				<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Problem type', 'npcink-workflow-toolbox' ); ?></span>
					<select name="media_filter">
						<option value="missing_alt"><?php esc_html_e( 'Missing ALT only', 'npcink-workflow-toolbox' ); ?></option>
						<option value="missing_or_weak_alt"><?php esc_html_e( 'Missing or weak ALT', 'npcink-workflow-toolbox' ); ?></option>
						<option value="all_recent"><?php esc_html_e( 'All recent images', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Focus note', 'npcink-workflow-toolbox' ); ?></span>
					<input type="text" name="focus" placeholder="<?php esc_attr_e( 'Optional: product screenshots, diagrams, or missing captions', 'npcink-workflow-toolbox' ); ?>" />
				</label>
				</div>
			</details>
			<div class="npcink-toolbox__result-notice is-pending"><?php esc_html_e( 'The scan is read-only. After reviewing an image, only a missing ALT row can be submitted to Core; Toolbox never approves, executes, or updates media.', 'npcink-workflow-toolbox' ); ?></div>
			<button type="submit" class="button button-primary"><?php echo esc_html( $button ); ?></button>
			<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_content_support_flow_tool( string $endpoint, string $title, string $description, string $tool_id, string $intent, string $button, bool $hosted_ai = false, bool $active = false, bool $cloud_ready = true ): void {
		?>
		<form class="npcink-toolbox__card" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
				<?php if ( $hosted_ai ) : ?>
					<div class="npcink-toolbox__example is-ai">
						<strong><?php esc_html_e( 'Hosted AI route', 'npcink-workflow-toolbox' ); ?></strong>
						<span><?php esc_html_e( 'Toolbox sends one lightweight draft-support request through the Cloud hosted runtime when the site is connected. The result is a reviewable suggestion, not a finished article.', 'npcink-workflow-toolbox' ); ?></span>
					</div>
					<?php if ( ! $cloud_ready ) : ?>
						<div class="npcink-toolbox__result-notice is-warning"><?php esc_html_e( 'Connect Cloud Addon before running hosted AI support.', 'npcink-workflow-toolbox' ); ?></div>
					<?php endif; ?>
				<?php endif; ?>
			<input type="hidden" name="intent" value="<?php echo esc_attr( $intent ); ?>" />
			<input type="hidden" name="post_type" value="post" />
			<input type="hidden" name="post_status" value="draft" />
			<div class="npcink-toolbox__example">
				<strong><?php esc_html_e( 'Fixed support flow', 'npcink-workflow-toolbox' ); ?></strong>
				<span><?php esc_html_e( 'This runs one bounded suggestion flow from the supplied article, selected text, topic, or brief. It does not write posts, assign terms, insert links, import media, or publish.', 'npcink-workflow-toolbox' ); ?></span>
			</div>
			<label>
				<span><?php esc_html_e( 'Input scope', 'npcink-workflow-toolbox' ); ?></span>
				<select name="context_scope">
					<option value="auto"><?php esc_html_e( 'Auto: selected text when present, otherwise full article', 'npcink-workflow-toolbox' ); ?></option>
					<option value="full_article"><?php esc_html_e( 'Full article context', 'npcink-workflow-toolbox' ); ?></option>
					<option value="selected_text"><?php esc_html_e( 'Selected text or supplied snippet', 'npcink-workflow-toolbox' ); ?></option>
					<option value="topic_only"><?php esc_html_e( 'Topic or short brief only', 'npcink-workflow-toolbox' ); ?></option>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Post ID (optional)', 'npcink-workflow-toolbox' ); ?></span>
				<input type="number" min="0" step="1" name="post_id" placeholder="<?php esc_attr_e( 'Use 0 for topic-only runs', 'npcink-workflow-toolbox' ); ?>" />
			</label>
			<label>
				<span><?php esc_html_e( 'Title or topic', 'npcink-workflow-toolbox' ); ?></span>
				<input type="text" name="title" placeholder="<?php esc_attr_e( 'Working title or article topic', 'npcink-workflow-toolbox' ); ?>" />
			</label>
			<label>
				<span><?php esc_html_e( 'Excerpt or short brief', 'npcink-workflow-toolbox' ); ?></span>
				<textarea name="excerpt" rows="3" placeholder="<?php esc_attr_e( 'Optional summary, angle, audience, or constraints', 'npcink-workflow-toolbox' ); ?>"></textarea>
			</label>
			<label>
				<span><?php esc_html_e( 'Draft text or notes', 'npcink-workflow-toolbox' ); ?></span>
				<textarea name="content" rows="5" placeholder="<?php esc_attr_e( 'Optional draft body, notes, or source outline', 'npcink-workflow-toolbox' ); ?>"></textarea>
			</label>
				<button type="submit" class="button button-primary" <?php echo disabled( $hosted_ai && ! $cloud_ready, true, false ); ?>><?php echo esc_html( $button ); ?></button>
				<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
			</form>
		<?php
	}

	private function get_media_derivative_toolbox_policy(): array {
		return $this->settings->media_optimization_policy_summary();
	}

	private function get_media_derivative_watermark_details( array $toolbox_policy ): string {
		if ( 'text' === (string) ( $toolbox_policy['watermark_type'] ?? '' ) ) {
			return sprintf(
				/* translators: 1: text, 2: position, 3: opacity, 4: font size, 5: margin. */
				__( 'text "%1$s", %2$s, %3$d%% opacity, %4$dpx font, %5$dpx margin', 'npcink-workflow-toolbox' ),
				(string) ( $toolbox_policy['watermark_text'] ?? 'AI' ),
				ucwords( str_replace( '_', ' ', (string) ( $toolbox_policy['watermark_position'] ?? 'bottom_right' ) ) ),
				(int) ( $toolbox_policy['watermark_opacity'] ?? 80 ),
				(int) ( $toolbox_policy['watermark_font_size'] ?? 48 ),
				(int) ( $toolbox_policy['watermark_margin'] ?? 24 )
			);
		}

		if ( empty( $toolbox_policy['watermark_configured'] ) ) {
			return __( 'off or incomplete', 'npcink-workflow-toolbox' );
		}

		return sprintf(
			/* translators: 1: position, 2: opacity, 3: scale, 4: margin. */
			__( '%1$s, %2$d%% opacity, %3$d%% scale, %4$dpx margin', 'npcink-workflow-toolbox' ),
			ucwords( str_replace( '_', ' ', (string) ( $toolbox_policy['watermark_position'] ?? 'bottom_right' ) ) ),
			(int) ( $toolbox_policy['watermark_opacity'] ?? 80 ),
			(int) ( $toolbox_policy['watermark_scale'] ?? 20 ),
			(int) ( $toolbox_policy['watermark_margin'] ?? 24 )
		);
	}

	private function render_media_derivative_toolbox_defaults( array $toolbox_policy ): void {
		?>
		<div class="npcink-toolbox__example">
			<strong><?php esc_html_e( 'Toolbox defaults', 'npcink-workflow-toolbox' ); ?></strong>
			<span>
				<?php
				printf(
					/* translators: 1: format, 2: max width, 3: quality. */
					esc_html__( '%1$s, %2$dpx, quality %3$d. Watermark: %4$s.', 'npcink-workflow-toolbox' ),
					esc_html( strtoupper( (string) $toolbox_policy['target_format'] ) ),
					(int) $toolbox_policy['max_width'],
					(int) $toolbox_policy['quality'],
					esc_html( $this->get_media_derivative_watermark_details( $toolbox_policy ) )
				);
				?>
			</span>
		</div>
		<?php
	}

	private function render_media_derivative_picker_controls(): void {
		?>
		<div class="npcink-toolbox__media-picker">
			<div class="npcink-toolbox__media-preview" data-toolbox-media-preview>
				<span><?php esc_html_e( 'No image selected', 'npcink-workflow-toolbox' ); ?></span>
			</div>
			<div>
				<label>
					<span><?php esc_html_e( 'Attachment ID', 'npcink-workflow-toolbox' ); ?></span>
					<input type="number" min="1" step="1" name="attachment_id" placeholder="<?php esc_attr_e( 'Attachment ID', 'npcink-workflow-toolbox' ); ?>" data-toolbox-media-attachment />
				</label>
				<label>
					<span><?php esc_html_e( 'Image URL', 'npcink-workflow-toolbox' ); ?></span>
					<input type="url" name="attachment_url" placeholder="<?php esc_attr_e( 'Paste a local uploads URL', 'npcink-workflow-toolbox' ); ?>" data-toolbox-media-url />
				</label>
				<div class="npcink-toolbox__inline-actions">
					<button type="button" class="button" data-toolbox-select-media><?php esc_html_e( 'Select from media library', 'npcink-workflow-toolbox' ); ?></button>
					<button type="button" class="button" data-toolbox-resolve-media-url><?php esc_html_e( 'Resolve URL', 'npcink-workflow-toolbox' ); ?></button>
					<span data-toolbox-media-name><?php esc_html_e( 'Choose one local image attachment.', 'npcink-workflow-toolbox' ); ?></span>
				</div>
				<div class="npcink-toolbox__url-resolution" data-toolbox-media-url-resolution hidden></div>
			</div>
		</div>
		<?php
	}

	private function render_media_derivative_format_controls( array $toolbox_policy, bool $single_mode = false ): void {
		?>
		<div class="npcink-toolbox__split">
			<label>
				<span><?php echo esc_html( $single_mode ? __( 'Output format', 'npcink-workflow-toolbox' ) : __( 'Format override', 'npcink-workflow-toolbox' ) ); ?></span>
				<select name="target_format">
					<option value=""><?php echo esc_html( $single_mode ? __( 'Use default setting', 'npcink-workflow-toolbox' ) : __( 'Use Toolbox default', 'npcink-workflow-toolbox' ) ); ?></option>
					<?php foreach ( array( 'webp', 'avif', 'jpeg', 'png', 'original' ) as $format ) : ?>
						<option value="<?php echo esc_attr( $format ); ?>"><?php echo esc_html( strtoupper( $format ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php echo esc_html( $single_mode ? __( 'Max width', 'npcink-workflow-toolbox' ) : __( 'Max width override', 'npcink-workflow-toolbox' ) ); ?></span>
				<input type="number" min="320" max="7680" step="1" name="max_width" placeholder="<?php echo esc_attr( (string) $toolbox_policy['max_width'] ); ?>" />
			</label>
		</div>
		<label>
			<span><?php echo esc_html( $single_mode ? __( 'Image quality', 'npcink-workflow-toolbox' ) : __( 'Quality override', 'npcink-workflow-toolbox' ) ); ?></span>
			<input type="number" min="1" max="100" step="1" name="quality" placeholder="<?php echo esc_attr( (string) $toolbox_policy['quality'] ); ?>" />
		</label>
		<?php
	}

	private function render_media_derivative_watermark_controls( array $toolbox_policy, bool $advanced_only = false ): void {
		$templates = is_array( $toolbox_policy['watermark_templates'] ?? null ) ? $toolbox_policy['watermark_templates'] : array();
		?>
		<div class="npcink-toolbox__batch-panel">
			<?php if ( ! $advanced_only ) : ?>
				<input type="hidden" name="watermark_policy_enabled" value="<?php echo ! empty( $toolbox_policy['watermark_enabled'] ) ? '1' : '0'; ?>" />
				<input type="hidden" name="watermark_policy_type" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_type'] ?? 'image' ) ); ?>" />
				<input type="hidden" name="watermark_attachment_id" value="<?php echo esc_attr( (string) absint( $toolbox_policy['watermark_attachment_id'] ?? 0 ) ); ?>" />
				<input type="hidden" name="watermark_template_definition" data-toolbox-watermark-template-definition />
				<input type="hidden" name="watermark_template_logo_url" data-toolbox-watermark-template-logo-url />
			<?php endif; ?>
			<h3><?php esc_html_e( 'Watermark override', 'npcink-workflow-toolbox' ); ?></h3>
			<p><?php esc_html_e( 'Choose a reusable Toolbox template, or use the detailed fields for this run. Text templates reuse the text below; logo templates use the configured Toolbox logo source.', 'npcink-workflow-toolbox' ); ?></p>
			<?php if ( ! $advanced_only ) : ?>
				<label>
					<span><?php esc_html_e( 'Watermark template', 'npcink-workflow-toolbox' ); ?></span>
					<select name="watermark_template" data-toolbox-watermark-template>
						<?php $this->render_watermark_template_options( $templates, (string) ( $toolbox_policy['default_watermark_template'] ?? 'toolbox_default' ), absint( $toolbox_policy['watermark_attachment_id'] ?? 0 ), true, (string) ( $toolbox_policy['watermark_text'] ?? 'AI' ) ); ?>
					</select>
				</label>
			<?php endif; ?>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Watermark mode', 'npcink-workflow-toolbox' ); ?></span>
					<select name="watermark_mode">
						<option value="default"><?php esc_html_e( 'Use Toolbox default', 'npcink-workflow-toolbox' ); ?></option>
						<option value="off"><?php esc_html_e( 'No watermark', 'npcink-workflow-toolbox' ); ?></option>
						<option value="text"><?php esc_html_e( 'Text watermark', 'npcink-workflow-toolbox' ); ?></option>
						<option value="image"><?php esc_html_e( 'Image/logo watermark', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Position', 'npcink-workflow-toolbox' ); ?></span>
					<select name="watermark_position">
						<?php foreach ( array( 'top_left', 'top_right', 'center', 'bottom_left', 'bottom_right' ) as $position ) : ?>
							<option value="<?php echo esc_attr( $position ); ?>" <?php selected( (string) ( $toolbox_policy['watermark_position'] ?? 'bottom_right' ), $position ); ?>><?php echo esc_html( $this->media_derivative_position_label( $position ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
			<input type="hidden" name="attachment_ids" data-toolbox-selected-attachment-ids value="" />
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Text', 'npcink-workflow-toolbox' ); ?></span>
					<input type="text" maxlength="64" name="watermark_text" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_text'] ?? 'AI' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Font size', 'npcink-workflow-toolbox' ); ?></span>
					<input type="number" min="8" max="256" step="1" name="watermark_font_size" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_font_size'] ?? 48 ) ); ?>" />
				</label>
			</div>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Text color', 'npcink-workflow-toolbox' ); ?></span>
					<input type="text" name="watermark_color" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_color'] ?? '#FFFFFF' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Background', 'npcink-workflow-toolbox' ); ?></span>
					<input type="text" name="watermark_background" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_background'] ?? 'rgba(0,0,0,0.35)' ) ); ?>" />
				</label>
			</div>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Opacity', 'npcink-workflow-toolbox' ); ?></span>
					<input type="number" min="0" max="100" step="1" name="watermark_opacity" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_opacity'] ?? 80 ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Image scale', 'npcink-workflow-toolbox' ); ?></span>
					<input type="number" min="1" max="100" step="1" name="watermark_scale" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_scale'] ?? 20 ) ); ?>" />
				</label>
			</div>
			<label>
				<span><?php esc_html_e( 'Margin', 'npcink-workflow-toolbox' ); ?></span>
				<input type="number" min="0" max="1000" step="1" name="watermark_margin" value="<?php echo esc_attr( (string) ( $toolbox_policy['watermark_margin'] ?? 24 ) ); ?>" />
			</label>
		</div>
		<?php
	}

	private function render_media_derivative_crop_controls(): void {
		?>
		<div class="npcink-toolbox__batch-panel">
			<h3><?php esc_html_e( 'Crop override', 'npcink-workflow-toolbox' ); ?></h3>
			<p><?php esc_html_e( 'Optional one-run crop for common publishing ratios. Cloud returns only a preview; final adoption still requires review.', 'npcink-workflow-toolbox' ); ?></p>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Crop ratio', 'npcink-workflow-toolbox' ); ?></span>
					<select name="crop_aspect_ratio">
						<option value=""><?php esc_html_e( 'No crop', 'npcink-workflow-toolbox' ); ?></option>
						<option value="16:9"><?php esc_html_e( '16:9 landscape', 'npcink-workflow-toolbox' ); ?></option>
						<option value="4:3"><?php esc_html_e( '4:3 landscape', 'npcink-workflow-toolbox' ); ?></option>
						<option value="1:1"><?php esc_html_e( '1:1 square', 'npcink-workflow-toolbox' ); ?></option>
						<option value="3:4"><?php esc_html_e( '3:4 portrait', 'npcink-workflow-toolbox' ); ?></option>
						<option value="9:16"><?php esc_html_e( '9:16 portrait', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Crop anchor', 'npcink-workflow-toolbox' ); ?></span>
					<select name="crop_position">
						<?php foreach ( array( 'center', 'top', 'bottom', 'left', 'right', 'top_left', 'top_right', 'bottom_left', 'bottom_right' ) as $position ) : ?>
							<option value="<?php echo esc_attr( $position ); ?>"><?php echo esc_html( $this->media_derivative_position_label( $position ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
		</div>
		<?php
	}

	private function render_media_derivative_output_controls(): void {
		?>
		<div class="npcink-toolbox__batch-panel">
			<h3><?php esc_html_e( 'Output and replacement', 'npcink-workflow-toolbox' ); ?></h3>
			<label>
				<span><?php esc_html_e( 'Output filename (optional)', 'npcink-workflow-toolbox' ); ?></span>
				<input type="text" name="output_filename" maxlength="96" placeholder="<?php esc_attr_e( 'Example: product-guide-cover', 'npcink-workflow-toolbox' ); ?>" />
			</label>
			<p class="description"><?php esc_html_e( 'Enter a basename only. Toolbox removes unsafe path characters and matches the extension to the generated format; the WordPress write ability performs the final sanitize-and-unique check.', 'npcink-workflow-toolbox' ); ?></p>
		</div>
		<?php
	}

	private function media_derivative_position_label( string $position ): string {
		$labels = array(
			'top_left'     => __( 'Top left', 'npcink-workflow-toolbox' ),
			'top'          => __( 'Top', 'npcink-workflow-toolbox' ),
			'top_right'    => __( 'Top right', 'npcink-workflow-toolbox' ),
			'left'         => __( 'Left', 'npcink-workflow-toolbox' ),
			'center'       => __( 'Center', 'npcink-workflow-toolbox' ),
			'right'        => __( 'Right', 'npcink-workflow-toolbox' ),
			'bottom_left'  => __( 'Bottom left', 'npcink-workflow-toolbox' ),
			'bottom'       => __( 'Bottom', 'npcink-workflow-toolbox' ),
			'bottom_right' => __( 'Bottom right', 'npcink-workflow-toolbox' ),
		);

		return $labels[ $position ] ?? ucwords( str_replace( '_', ' ', $position ) );
	}

	private function media_derivative_color_input_value( string $value, string $fallback ): string {
		$value = trim( $value );
		if ( preg_match( '/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $value, $matches ) ) {
			$hex = $matches[1];
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}

			return '#' . strtoupper( $hex );
		}

		if ( preg_match( '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})/i', $value, $matches ) ) {
			return sprintf(
				'#%02X%02X%02X',
				min( 255, (int) $matches[1] ),
				min( 255, (int) $matches[2] ),
				min( 255, (int) $matches[3] )
			);
		}

		return $fallback;
	}

	private function media_derivative_background_opacity_value( string $value ): int {
		if ( preg_match( '/^rgba\([^,]+,[^,]+,[^,]+,\s*(0(?:\.\d+)?|1(?:\.0+)?)\s*\)$/i', trim( $value ), $matches ) ) {
			return (int) round( (float) $matches[1] * 100 );
		}

		return 35;
	}

	private function render_media_derivative_batch_controls( array $toolbox_policy ): void {
		?>
		<div class="npcink-toolbox__batch-panel">
			<h3><?php esc_html_e( 'Optimize Media Library images', 'npcink-workflow-toolbox' ); ?></h3>
			<div class="npcink-toolbox__split">
				<label>
					<span><?php esc_html_e( 'Time range', 'npcink-workflow-toolbox' ); ?></span>
					<select name="batch_scope_preset">
						<option value="one_month"><?php esc_html_e( 'Last month', 'npcink-workflow-toolbox' ); ?></option>
						<option value="three_months"><?php esc_html_e( 'Last three months', 'npcink-workflow-toolbox' ); ?></option>
						<option value="this_year"><?php esc_html_e( 'This year', 'npcink-workflow-toolbox' ); ?></option>
						<option value="all"><?php esc_html_e( 'All images', 'npcink-workflow-toolbox' ); ?></option>
						<option value="custom"><?php esc_html_e( 'Custom range', 'npcink-workflow-toolbox' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Image type', 'npcink-workflow-toolbox' ); ?></span>
					<select name="batch_image_type">
						<option value="recommended"><?php esc_html_e( 'Recommended images', 'npcink-workflow-toolbox' ); ?></option>
						<option value="jpeg">JPEG</option>
						<option value="png">PNG</option>
						<option value="webp">WebP</option>
					</select>
				</label>
			</div>
			<div class="npcink-toolbox__split" data-toolbox-custom-media-dates hidden>
				<label><span><?php esc_html_e( 'From', 'npcink-workflow-toolbox' ); ?></span><input type="date" name="batch_date_from" /></label>
				<label><span><?php esc_html_e( 'To', 'npcink-workflow-toolbox' ); ?></span><input type="date" name="batch_date_to" /></label>
			</div>
			<div class="npcink-toolbox__inline-actions">
				<button type="button" class="button button-primary" data-toolbox-build-media-batch-plan><?php esc_html_e( 'Check optimizable images', 'npcink-workflow-toolbox' ); ?></button>
				<button type="button" class="button button-primary" data-toolbox-submit-media-batch-proposals disabled hidden><?php esc_html_e( 'Start optimization', 'npcink-workflow-toolbox' ); ?></button>
			</div>
			<div data-toolbox-media-resize-choice hidden>
				<strong><?php esc_html_e( 'Large images found', 'npcink-workflow-toolbox' ); ?></strong>
				<label><input type="radio" name="batch_resize_mode" value="preserve" checked /> <?php esc_html_e( 'Keep original dimensions', 'npcink-workflow-toolbox' ); ?></label>
				<label><input type="radio" name="batch_resize_mode" value="fit" /> <?php esc_html_e( 'Limit longest side to 1920px', 'npcink-workflow-toolbox' ); ?></label>
			</div>
			<div class="npcink-toolbox__batch-plan" data-toolbox-media-batch-plan hidden></div>
			<div class="npcink-toolbox__batch-progress" data-toolbox-media-batch-progress hidden aria-live="polite"></div>
			<section class="npcink-toolbox__media-batch-history" data-toolbox-media-batch-history>
				<h3><?php esc_html_e( 'History and restore', 'npcink-workflow-toolbox' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Completed batches remain recoverable for 30 days. Single-image backup files expire through daily maintenance; batch backups stay until explicitly cleaned up. The current Media Library image is never removed.', 'npcink-workflow-toolbox' ); ?></p>
			</section>
		</div>
		<?php
	}

	private function render_media_derivative_batch_tool( string $endpoint, string $title, string $description, string $tool_id, bool $active = false ): void {
		$toolbox_policy = $this->get_media_derivative_toolbox_policy();
		if ( $active ) {
			$this->render_media_recognition_recovery();
		}
		?>
		<form class="npcink-toolbox__card npcink-toolbox__card--media-batch" id="npcink-toolbox-tour-batch-optimize" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" data-toolbox-media-derivative <?php echo $active ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<?php $this->render_media_derivative_batch_controls( $toolbox_policy ); ?>
			<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	/** Renders recovery only after the internal continuation has paused. */
	private function render_media_recognition_recovery(): void {
		$scan_status = apply_filters( 'npcink_toolbox_media_fingerprint_scan_status', array() );
		if ( is_array( $scan_status ) && ! empty( $scan_status['overdue'] ) ) {
			?>
			<div class="npcink-toolbox__result-notice is-warning" data-toolbox-media-fingerprint-scan-overdue>
				<p><?php esc_html_e( 'The weekly media fingerprint scan is overdue. Check the site server cron before relying on background freshness checks.', 'npcink-workflow-toolbox' ); ?></p>
			</div>
			<?php
		}
		$status = apply_filters( 'npcink_toolbox_media_recognition_status', array() );
		if ( ! is_array( $status ) ) {
			return;
		}
		$state = sanitize_key( (string) ( $status['state'] ?? '' ) );
		if ( 'awaiting_confirmation' === $state && 'changed_attachments' === sanitize_key( (string) ( $status['scope'] ?? '' ) ) ) {
			?>
			<div class="npcink-toolbox__result-notice is-warning" data-toolbox-changed-media-recognition-confirmation>
				<?php /* translators: %d: Number of changed image files. */ ?>
				<p><?php echo esc_html( sprintf( __( '%d image file(s) changed and need fresh visual evidence. No Cloud recognition has run.', 'npcink-workflow-toolbox' ), count( (array) ( $status['attachment_ids'] ?? array() ) ) ) ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'npcink_toolbox_confirm_changed_media_recognition' ); ?>
					<input type="hidden" name="action" value="npcink_toolbox_confirm_changed_media_recognition" />
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Confirm visual recognition', 'npcink-workflow-toolbox' ); ?></button>
				</form>
			</div>
			<?php
			return;
		}
		if ( 'paused' !== $state ) {
			return;
		}
		?>
		<div class="npcink-toolbox__result-notice is-warning" data-toolbox-media-recognition-recovery>
			<p><?php esc_html_e( 'Background image recognition paused after repeated failures. Review the Cloud connection, then resume the same batch.', 'npcink-workflow-toolbox' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'npcink_toolbox_resume_media_recognition' ); ?>
				<input type="hidden" name="action" value="npcink_toolbox_resume_media_recognition" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Resume background recognition', 'npcink-workflow-toolbox' ); ?></button>
			</form>
		</div>
		<?php
	}

	private function render_text_tool( string $endpoint, string $title, string $description, string $field, string $placeholder, string $button, array $extra_fields = array(), string $tool_id = '', bool $active = false ): void {
		?>
		<form class="npcink-toolbox__card" data-toolbox-endpoint="<?php echo esc_attr( $endpoint ); ?>" data-toolbox-tool-panel="<?php echo esc_attr( $tool_id ); ?>" <?php echo $active ? '' : 'hidden'; ?>>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<label>
				<span><?php echo esc_html( $placeholder ); ?></span>
				<textarea name="<?php echo esc_attr( $field ); ?>" rows="4"></textarea>
			</label>
			<?php foreach ( $extra_fields as $extra ) : ?>
				<label>
					<span><?php echo esc_html( (string) $extra['label'] ); ?></span>
					<input type="text" name="<?php echo esc_attr( (string) $extra['name'] ); ?>" placeholder="<?php echo esc_attr( (string) $extra['placeholder'] ); ?>" />
				</label>
			<?php endforeach; ?>
			<button type="submit" class="button button-primary"><?php echo esc_html( $button ); ?></button>
			<div class="npcink-toolbox__result is-empty" aria-live="polite" hidden></div>
		</form>
		<?php
	}

	private function render_checkbox( string $key, string $label, array $settings ): void {
		?>
		<label class="npcink-toolbox__check">
			<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?> />
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	private function render_context_textarea( string $key, string $label, array $context ): void {
		?>
		<label>
			<span><?php echo esc_html( $label ); ?></span>
			<textarea name="<?php echo esc_attr( Plugin::CONTEXT_OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" rows="3"><?php echo esc_textarea( (string) ( $context[ $key ] ?? '' ) ); ?></textarea>
		</label>
		<?php
	}

	private function render_context_list_field( string $key, string $label, array $context ): void {
		$value = implode( "\n", (array) ( $context[ $key ] ?? array() ) );
		?>
		<label>
			<span><?php echo esc_html( $label ); ?></span>
			<textarea name="<?php echo esc_attr( Plugin::CONTEXT_OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" rows="3"><?php echo esc_textarea( $value ); ?></textarea>
		</label>
		<?php
	}

	private function render_context_checkbox( string $key, string $label, array $context ): void {
		?>
		<label class="npcink-toolbox__check">
			<input type="checkbox" name="<?php echo esc_attr( Plugin::CONTEXT_OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $context[ $key ] ) ); ?> />
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	private function render_proposal_field_checkbox( string $field, string $label, array $context ): void {
		?>
		<label class="npcink-toolbox__check">
			<input type="checkbox" name="<?php echo esc_attr( Plugin::CONTEXT_OPTION_NAME ); ?>[proposal_allowed_fields][]" value="<?php echo esc_attr( $field ); ?>" <?php checked( in_array( $field, (array) $context['proposal_allowed_fields'], true ) ); ?> />
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}
}
