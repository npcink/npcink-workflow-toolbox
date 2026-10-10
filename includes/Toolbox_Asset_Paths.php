<?php
/**
 * Shared asset path and version helpers for the enqueue surfaces.
 *
 * Release packages ship .min siblings next to the readable sources (see
 * scripts/minify-assets.sh); enqueues prefer the .min sibling unless
 * SCRIPT_DEBUG is on, and fall back to the readable source when the .min
 * file is absent (development checkouts).
 *
 * @package Npcink_Toolbox
 */

namespace Npcink_Toolbox;

defined( 'ABSPATH' ) || exit;

trait Toolbox_Asset_Paths {

	private function asset_path( string $relative_path ): string {
		$min_path = (string) preg_replace( '/\.(js|css)$/i', '.min.$1', $relative_path );
		if ( ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) || ! file_exists( NPCINK_TOOLBOX_DIR . $min_path ) ) {
			return $relative_path;
		}

		return $min_path;
	}

	private function asset_version( string $relative_path ): string {
		$path     = NPCINK_TOOLBOX_DIR . ltrim( $relative_path, '/' );
		$modified = file_exists( $path ) ? filemtime( $path ) : false;
		return NPCINK_TOOLBOX_VERSION . ( $modified ? '-' . (string) $modified : '' );
	}
}
