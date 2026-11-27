<?php
/** Local editor and administrator-only article credential checks. @package Npcink_Toolbox */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

final class Article_Credential_Check {

	use Toolbox_Asset_Paths;

	private const ACTION     = 'npcink_toolbox_article_credential_scan';
	private const BATCH_SIZE = 10;

	public function register_hooks(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'ajax_batch' ) );
		add_filter( 'rest_dispatch_request', array( $this, 'guard_editor_transfer' ), 10, 3 );
	}

	/** Runs after the existing route permission check and before the provider callback. */
	public function guard_editor_transfer( $response, \WP_REST_Request $request, string $route ) {
		if ( null !== $response || 'POST' !== $request->get_method()
			|| ! in_array( $route, array( '/npcink-toolbox/v1/editor/content-support', '/npcink-toolbox/v1/ai/content-support' ), true ) ) {
			return $response;
		}
		$intent = $request->get_param( 'intent' );
		$intent = is_string( $intent ) ? sanitize_key( $intent ) : '';
		if ( ! in_array( $intent, array( 'publish_preflight', 'body_faq' ), true ) ) {
			return $response;
		}
		$fields = array( $request->get_param( 'content' ) );
		if ( 'publish_preflight' === $intent ) {
			foreach ( array( 'title', 'excerpt', 'selected_text', 'selected_block_text', 'user_instruction' ) as $field ) {
				$fields[] = $request->get_param( $field );
			}
			$snapshot = $request->get_param( 'article_review_snapshot' );
			if ( is_array( $snapshot ) && is_array( $snapshot['blocks'] ?? null ) ) {
				foreach ( array_slice( $snapshot['blocks'], 0, 80 ) as $block ) {
					$fields[] = is_array( $block ) ? ( $block['text'] ?? null ) : null;
				}
			}
		}
		return self::guard_fields( $fields );
	}

	/** Checks either the raw input or the derived fields immediately before transfer. */
	public static function guard_fields( array $fields ): ?\WP_Error {
		$total = 0;
		foreach ( $fields as $text ) {
			if ( ! is_string( $text ) ) {
				continue;
			}
			$total += strlen( $text );
			if ( $total > Article_Credential_Rules::MAX_BYTES * 2 ) {
				return new \WP_Error( 'npcink_article_credential_check_limit', __( 'The article is too large for the credential check. Nothing was sent to Cloud.', 'npcink-workflow-toolbox' ), array( 'status' => 413 ) );
			}
			if ( Article_Credential_Rules::categories( $text, true ) ) {
				return new \WP_Error( 'npcink_article_credential_check_hit', __( 'Possible credential found. Review it with Check credentials before sending the article to Cloud.', 'npcink-workflow-toolbox' ), array( 'status' => 422 ) );
			}
		}
		return null;
	}

	public function enqueue_admin(): void {
		$file = $this->asset_path( 'assets/article-credential-admin.js' );
		wp_enqueue_script( 'npcink-toolbox-credential-admin', NPCINK_TOOLBOX_URL . $file, array( 'wp-i18n' ), $this->asset_version( $file ), true );
		wp_set_script_translations( 'npcink-toolbox-credential-admin', 'npcink-workflow-toolbox', NPCINK_TOOLBOX_DIR . 'languages' );
		wp_localize_script(
			'npcink-toolbox-credential-admin',
			'NpcinkArticleCredentialAdmin',
			array(
				'url'       => admin_url( 'admin-ajax.php' ),
				'action'    => self::ACTION,
				'nonce'     => wp_create_nonce( self::ACTION ),
				'labels'    => array_map( static fn( array $rule ): string => $rule['label'], Article_Credential_Rules::definitions() ),
				'locations' => array(
					'title'   => __( 'Title', 'npcink-workflow-toolbox' ),
					'excerpt' => __( 'Excerpt', 'npcink-workflow-toolbox' ),
					'body'    => __( 'Body', 'npcink-workflow-toolbox' ),
				),
				'advisory'  => array_keys( array_filter( Article_Credential_Rules::definitions(), static fn( array $rule ): bool => ! empty( $rule['advisory'] ) ) ),
			)
		);
	}

	public static function render_admin(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to scan articles.', 'npcink-workflow-toolbox' ) );
		}
		?>
		<div id="npcink-article-credential-admin">
			<p><?php esc_html_e( 'Checks saved article titles, excerpts and bodies, including drafts and private articles you can edit. Nothing is sent to Cloud or changed.', 'npcink-workflow-toolbox' ); ?></p>
			<p><?php esc_html_e( 'If a real credential was already shared, revoke or rotate it before editing the article.', 'npcink-workflow-toolbox' ); ?></p>
			<button type="button" class="button button-primary" data-scan disabled><?php esc_html_e( 'Scan site articles', 'npcink-workflow-toolbox' ); ?></button>
			<span hidden><button type="button" class="button" data-stop><?php esc_html_e( 'Stop scan', 'npcink-workflow-toolbox' ); ?></button></span>
			<p role="status" aria-live="polite" data-status></p>
			<table class="widefat striped" data-results hidden>
				<thead><tr><th scope="col"><?php esc_html_e( 'Article', 'npcink-workflow-toolbox' ); ?></th><th scope="col"><?php esc_html_e( 'Possible credential types', 'npcink-workflow-toolbox' ); ?></th></tr></thead>
				<tbody></tbody>
			</table>
			<noscript><p><?php esc_html_e( 'Enable JavaScript to run the scan.', 'npcink-workflow-toolbox' ); ?></p></noscript>
		</div>
		<?php
	}

	public function ajax_batch(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( self::ACTION, 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}
		$after = filter_input( INPUT_POST, 'after', FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );
		$upper = filter_input( INPUT_POST, 'upper', FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );
		if ( ! is_int( $after ) || ! is_int( $upper ) || $after > $upper ) {
			wp_send_json_error( null, 400 );
		}
		$result = $this->batch( $after, $upper );
		if ( null === $result ) {
			wp_send_json_error( null, 503 );
		}
		wp_send_json_success( $result );
	}

	/** @return array<string,mixed>|null Null means the storage read failed. */
	public function batch( int $after, int $upper ): ?array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return null;
		}
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Bounded, uncached keyset scan of the current site's posts.
		if ( 0 === $upper ) {
			$upper = (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" );
			if ( '' !== $wpdb->last_error ) {
				return null;
			}
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, (OCTET_LENGTH(post_title) + OCTET_LENGTH(post_excerpt) + OCTET_LENGTH(post_content)) AS article_bytes FROM {$wpdb->posts}
				 WHERE ID > %d AND ID <= %d AND post_type = 'post' AND post_status IN ('publish','future','draft','pending','private')
				 ORDER BY ID ASC LIMIT %d",
				$after,
				$upper,
				self::BATCH_SIZE + 1
			)
		);
		if ( '' !== $wpdb->last_error || ! is_array( $rows ) ) {
			return null;
		}
		$results = array();
		$scanned = 0;
		$skipped = 0;
		foreach ( array_slice( $rows, 0, self::BATCH_SIZE ) as $row ) {
			$after = (int) $row->ID;
			if ( (int) $row->article_bytes > Article_Credential_Rules::MAX_BYTES || ! current_user_can( 'edit_post', $after ) ) {
				++$skipped;
				continue;
			}
			$saved = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT post_title, post_excerpt, post_content FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'post' AND post_status IN ('publish','future','draft','pending','private') AND (OCTET_LENGTH(post_title) + OCTET_LENGTH(post_excerpt) + OCTET_LENGTH(post_content)) <= %d",
					$after,
					Article_Credential_Rules::MAX_BYTES
				)
			);
			if ( $this->database_read_failed( $wpdb ) ) {
				return null;
			}
			if ( ! is_string( $saved->post_content ?? null ) ) {
				++$skipped;
				continue;
			}
			++$scanned;
			$findings = array();
			foreach ( array(
				'title'   => $saved->post_title,
				'excerpt' => $saved->post_excerpt,
				'body'    => $saved->post_content,
			) as $field => $text ) {
				if ( '' === $text ) {
					continue;
				}
				$types = array_unique( array_merge( Article_Credential_Rules::categories( $text ), Article_Credential_Rules::categories( Article_Credential_Rules::without_markup( $text ) ) ) );
				foreach ( $types as $type ) {
					$findings[] = array(
						'field' => $field,
						'type'  => $type,
					);
				}
			}
			if ( $findings ) {
				$results[] = array(
					'id'       => $after,
					'findings' => $findings,
					'edit_url' => admin_url( 'post.php?post=' . $after . '&action=edit' ),
				);
			}
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
		return array(
			'after'   => $after,
			'upper'   => $upper,
			'done'    => count( $rows ) <= self::BATCH_SIZE,
			'scanned' => $scanned,
			'skipped' => $skipped,
			'items'   => $results,
		);
	}

	private function database_read_failed( \wpdb $database ): bool {
		return '' !== $database->last_error;
	}
}
