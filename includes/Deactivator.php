<?php
/**
 * Deactivation tasks.
 *
 * Data and capabilities deliberately remain available on deactivation.
 *
 * @package ACE\AdaptiveContentEnhancer
 */

namespace ACE\AdaptiveContentEnhancer;

defined( 'ABSPATH' ) || exit;

final class Deactivator {
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'ace_forms_retention' );
	}
}
