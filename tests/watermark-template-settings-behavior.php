<?php
/** Focused checks for the local watermark template settings catalog. */

namespace {
	define( 'ABSPATH', __DIR__ . '/wp-stub/' );
	$npcink_toolbox_test_options = array();
	$npcink_toolbox_test_image_ids = array( 77 );

	function __( string $value ): string { return $value; }
	function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
	function sanitize_key( string $value ): string { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ) ?? ''; }
	function absint( $value ): int { return abs( (int) $value ); }
	function get_option( string $name, $default = false ) {
		global $npcink_toolbox_test_options;
		return array_key_exists( $name, $npcink_toolbox_test_options ) ? $npcink_toolbox_test_options[ $name ] : $default;
	}
	function wp_attachment_is_image( int $attachment_id ): bool {
		global $npcink_toolbox_test_image_ids;
		return in_array( $attachment_id, $npcink_toolbox_test_image_ids, true );
	}

	function watermark_template_assert( bool $condition, string $message ): void {
		if ( ! $condition ) {
			fwrite( STDERR, "FAIL: {$message}\n" );
			exit( 1 );
		}
		echo "PASS: {$message}\n";
	}
}

namespace Npcink_Toolbox {
	final class Plugin {
		public const WATERMARK_OPTION_NAME = 'npcink_toolbox_watermark_templates';
	}
}

namespace {
	require_once dirname( __DIR__ ) . '/includes/Settings.php';

	$settings = new \Npcink_Toolbox\Settings();
	$sanitized = $settings->sanitize_watermark_template_settings(
		array(
			'default_template' => 'user_brand',
			'custom_templates' => array(
				array(
					'id'                 => 'user_brand',
					'label'              => '<b>Brand corner</b>',
					'type'               => 'text',
					'text'               => 'NPCINK',
					'position'           => 'not_allowed',
					'opacity'            => 999,
					'font_size'          => 999,
					'color'              => '#abc',
					'background_color'   => '#123456',
					'background_opacity' => 42,
					'margin'             => 5000,
				),
				array(
					'id'            => 'unsafe id',
					'label'         => 'Logo',
					'type'          => 'image',
					'attachment_id' => 77,
					'position'      => 'top_left',
					'scale'         => 18,
				),
			),
		)
	);

	watermark_template_assert( 'user_brand' === $sanitized['default_template'], 'A valid user template can be selected as the local default.' );
	watermark_template_assert( 2 === count( $sanitized['custom_templates'] ), 'The sanitizer preserves only valid named custom templates.' );
	watermark_template_assert( 'Brand corner' === $sanitized['custom_templates'][0]['label'], 'Template labels are sanitized for local settings storage.' );
	watermark_template_assert( 'bottom_right' === $sanitized['custom_templates'][0]['position'], 'Unknown watermark positions fail closed to bottom right.' );
	watermark_template_assert( 100 === $sanitized['custom_templates'][0]['opacity'] && 256 === $sanitized['custom_templates'][0]['font_size'] && 1000 === $sanitized['custom_templates'][0]['margin'], 'Numeric template fields are bounded.' );
	watermark_template_assert( 'rgba(18,52,86,0.42)' === $sanitized['custom_templates'][0]['background'], 'Operator-friendly background color and opacity normalize to the canonical RGBA contract.' );
	watermark_template_assert( 0 === strpos( $sanitized['custom_templates'][1]['id'], 'user_' ) && 77 === $sanitized['custom_templates'][1]['attachment_id'], 'Unsafe ids are replaced while a bounded local logo attachment id is retained.' );

	$invalid_logo = $settings->sanitize_watermark_template_settings(
		array(
			'default_template' => 'user_invalid_logo',
			'custom_templates' => array(
				array( 'id' => 'user_invalid_logo', 'label' => 'Invalid logo', 'type' => 'image', 'attachment_id' => 88 ),
			),
		)
	);
	watermark_template_assert( 0 === $invalid_logo['custom_templates'][0]['attachment_id'], 'Deleted or non-image logo attachments fail closed before entering the runtime catalog.' );
	watermark_template_assert( 'toolbox_default' === $invalid_logo['default_template'], 'A custom logo template with no valid local image cannot remain the default.' );

	$overflow_templates = array();
	for ( $index = 0; $index < 25; ++$index ) {
		$overflow_templates[] = array( 'id' => 'user_' . $index, 'label' => 'Template ' . $index );
	}
	$bounded = $settings->sanitize_watermark_template_settings(
		array(
			'default_template' => 'user_24',
			'custom_templates' => $overflow_templates,
		)
	);
	watermark_template_assert( 20 === count( $bounded['custom_templates'] ), 'The local catalog keeps at most twenty custom templates.' );
	watermark_template_assert( 'toolbox_default' === $bounded['default_template'], 'A deleted or truncated custom default falls back to the Toolbox default.' );

	$npcink_toolbox_test_options['npcink_toolbox_watermark_templates'] = $sanitized;
	$catalog = $settings->media_watermark_templates();
	$catalog_ids = array_column( $catalog, 'id' );
	watermark_template_assert( in_array( 'subtle_text', $catalog_ids, true ) && in_array( 'user_brand', $catalog_ids, true ) && 'custom' === end( $catalog_ids ), 'The runtime catalog merges immutable presets, saved user templates, and the one-run custom entry.' );

	echo "Watermark template settings behavior checks passed.\n";
}
