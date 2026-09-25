<?php
/**
 * Plugin Name:       Adaptive Content Enhancer
 * Plugin URI:        https://acemedia.ninja/
 * Description:       Block-native forms and custom fields for WordPress.
 * Version:           0.500.0
 * Requires at least: 7.1
 * Requires PHP:      8.4
 * Author:            Shane Rounce
 * Author URI:        https://acemedia.ninja/
 * License:           GPL-2.0-or-later
 * Text Domain:       adaptive-content-enhancer
 *
 * @package ACE\AdaptiveContentEnhancer
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ACE_ACE_FILE' ) ) {
	if ( ! defined( 'ACE_ACE_LOADER_FILE' ) ) {
		define( 'ACE_ACE_LOADER_FILE', __FILE__ );
	}
	define( 'ACE_ACE_FILE', __FILE__ );
	define( 'ACE_ACE_DIR', plugin_dir_path( __FILE__ ) );
	define( 'ACE_ACE_URL', plugin_dir_url( __FILE__ ) );
	define( 'ACE_ACE_VERSION', '0.500.0' );
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'ACE\\AdaptiveContentEnhancer\\';

		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}

		$file = ACE_ACE_DIR . 'includes/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( ACE_ACE_LOADER_FILE, array( 'ACE\\AdaptiveContentEnhancer\\Activator', 'activate' ) );
register_deactivation_hook( ACE_ACE_LOADER_FILE, array( 'ACE\\AdaptiveContentEnhancer\\Deactivator', 'deactivate' ) );

\ACE\AdaptiveContentEnhancer\Plugin::instance()->boot();

// Load the procedural compatibility layer until its public functions and old
// block registrations have been replaced by namespaced adapters.
require_once ACE_ACE_DIR . 'ace-field-options.php';
