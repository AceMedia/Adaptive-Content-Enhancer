<?php
/** Bounded, read-only scan of active code for ACF helper calls. */
namespace ACE\AdaptiveContentEnhancer\Fields;

defined( 'ABSPATH' ) || exit;

final class CompatibilityScan {
	private const SUPPORTED = array( 'get_field', 'the_field', 'have_rows', 'the_row', 'get_sub_field' );
	private const REVIEW = array( 'get_fields', 'get_field_object', 'get_sub_field_object', 'update_field', 'delete_field', 'update_sub_field', 'delete_sub_field', 'get_row_layout', 'acf_form', 'acf_form_head', 'acf_add_local_field_group', 'acf_register_block_type' );
	private const MAX_FILES = 2400;
	private const MAX_FILES_PER_ROOT = 80;
	private const MAX_BYTES = 30000000;

	private static function roots(): array {
		$roots = array( get_stylesheet_directory(), WPMU_PLUGIN_DIR );
		if ( get_template_directory() !== get_stylesheet_directory() ) {
			$roots[] = get_template_directory();
		}
		$plugins = array_merge( (array) get_option( 'active_plugins', array() ), array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		sort( $plugins );
		foreach ( array_unique( $plugins ) as $plugin ) {
			$directory = strtok( (string) $plugin, '/' );
			if ( in_array( $directory, array( 'adaptive-content-enhancer', 'advanced-custom-fields', 'advanced-custom-fields-pro', 'acf-pro', 'contact-form-7' ), true ) ) {
				continue;
			}
			$roots[] = str_contains( (string) $plugin, '/' ) ? WP_PLUGIN_DIR . '/' . $directory : WP_PLUGIN_DIR . '/' . $plugin;
		}
		return array_values( array_unique( array_filter( $roots, static fn( string $path ): bool => is_dir( $path ) || is_file( $path ) ) ) );
	}

	/** @return array{supported:int,review:int,samples:array,files:int,truncated:bool} */
	public static function results( bool $force = false ): array {
		$roots = self::roots();
		$key = 'ace_acf_scan_' . substr( md5( wp_json_encode( $roots ) ), 0, 12 );
		$cached = $force ? false : get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$result = array( 'supported' => 0, 'review' => 0, 'samples' => array(), 'files' => 0, 'truncated' => false );
		$bytes = 0;
		foreach ( $roots as $root ) {
			$root_files = 0;
			if ( is_file( $root ) ) {
				$iterator = new \ArrayIterator( array( new \SplFileInfo( $root ) ) );
			} else {
				try {
					$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
				} catch ( \UnexpectedValueException $error ) {
					continue;
				}
			}
			foreach ( $iterator as $file ) {
				$path = $file->getPathname();
				if ( ! $file->isFile() || ! $file->isReadable() || 'php' !== strtolower( $file->getExtension() ) || preg_match( '~/(vendor|node_modules|build|dist|tests?|cache|languages|uploads)/~i', $path ) ) {
					continue;
				}
				$size = $file->getSize();
				if ( $size <= 0 || $size > 250000 ) {
					continue;
				}
				if ( $result['files'] >= self::MAX_FILES || $bytes + $size > self::MAX_BYTES ) {
					$result['truncated'] = true;
					break 2;
				}
				if ( $root_files >= self::MAX_FILES_PER_ROOT ) {
					$result['truncated'] = true;
					break;
				}
				$contents = file_get_contents( $path );
				if ( false === $contents ) {
					continue;
				}
				++$result['files'];
				++$root_files;
				$bytes += $size;
				self::inspect( $contents, $path, $result );
			}
		}
		set_transient( $key, $result, 15 * MINUTE_IN_SECONDS );
		return $result;
	}

	private static function inspect( string $source, string $path, array &$result ): void {
		$names = array_merge( self::SUPPORTED, self::REVIEW );
		if ( ! preg_match( '/\b(?:' . implode( '|', $names ) . ')\s*\(/i', $source ) ) {
			return;
		}
		$tokens = token_get_all( $source );
		$previous = null;
		$count = count( $tokens );
		for ( $index = 0; $index < $count; ++$index ) {
			$token = $tokens[ $index ];
			if ( ! is_array( $token ) ) {
				$previous = $token;
				continue;
			}
			if ( in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$name = strtolower( ltrim( $token[1], '\\' ) );
			$kind = in_array( $name, self::SUPPORTED, true ) ? 'supported' : ( in_array( $name, self::REVIEW, true ) ? 'review' : '' );
			if ( $kind && in_array( $token[0], array( T_STRING, T_NAME_FULLY_QUALIFIED ), true ) && ! in_array( $previous, array( T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NEW ), true ) ) {
				$next = $index + 1;
				while ( $next < $count && is_array( $tokens[ $next ] ) && T_WHITESPACE === $tokens[ $next ][0] ) {
					++$next;
				}
				if ( '(' === ( $tokens[ $next ] ?? null ) ) {
					++$result[ $kind ];
					if ( count( $result['samples'] ) < 16 && count( array_filter( $result['samples'], static fn( array $sample ): bool => $kind === $sample['kind'] ) ) < 8 ) {
						$result['samples'][] = array( 'kind' => $kind, 'call' => $name . '()', 'file' => ltrim( str_replace( WP_CONTENT_DIR, '', $path ), '/' ), 'line' => $token[2] );
					}
				}
			}
			$previous = $token[0];
		}
	}
}
