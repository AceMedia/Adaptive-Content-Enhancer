<?php
/**
 * Activation tasks.
 *
 * @package ACE\AdaptiveContentEnhancer
 */

namespace ACE\AdaptiveContentEnhancer;

use ACE\AdaptiveContentEnhancer\Database\Schema;
use ACE\AdaptiveContentEnhancer\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

final class Activator {
	public static function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_site();
				restore_current_blog();
			}

			return;
		}

		self::activate_site();
	}

	private static function activate_site(): void {
		Schema::install();
		Capabilities::grant();
	}
}
