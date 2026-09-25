<?php
/**
 * Plugin capabilities.
 *
 * @package ACE\AdaptiveContentEnhancer
 */

namespace ACE\AdaptiveContentEnhancer\Security;

defined( 'ABSPATH' ) || exit;

final class Capabilities {
	public const VIEW_ENTRIES   = 'ace_forms_view_entries';
	public const EDIT_ENTRIES   = 'ace_forms_edit_entries';
	public const MANAGE_ENTRIES = 'ace_forms_manage_entries';
	public const SETTINGS       = 'ace_forms_manage_settings';
	public const FIELD_GROUPS   = 'ace_fields_manage_groups';

	public static function grant(): void {
		$administrator = get_role( 'administrator' );

		if ( $administrator ) {
			foreach ( self::all() as $capability ) {
				$administrator->add_cap( $capability );
			}
		}

		$shop_manager = get_role( 'shop_manager' );

		if ( $shop_manager ) {
			$shop_manager->add_cap( self::VIEW_ENTRIES );
			$shop_manager->add_cap( self::EDIT_ENTRIES );
		}
	}

	/** @return array<int, string> */
	private static function all(): array {
		return array( self::VIEW_ENTRIES, self::EDIT_ENTRIES, self::MANAGE_ENTRIES, self::SETTINGS, self::FIELD_GROUPS );
	}
}
