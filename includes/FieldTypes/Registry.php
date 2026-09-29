<?php
/**
 * Field-type registry.
 *
 * @package ACE\AdaptiveContentEnhancer
 */

namespace ACE\AdaptiveContentEnhancer\FieldTypes;

use ACE\AdaptiveContentEnhancer\FieldTypes\Types\StandardType;

defined( 'ABSPATH' ) || exit;

final class Registry {
	private static ?self $instance = null;
	/** @var array<string, FieldType> */
	private array $types = array();
	private bool $defaults_registered = false;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function register( FieldType $type ): void {
		$this->types[ $type->slug() ] = $type;
	}

	public function get( string $slug ): ?FieldType {
		return $this->types[ $slug ] ?? null;
	}

	/** @return array<string, FieldType> */
	public function all(): array {
		return $this->types;
	}

	public function register_defaults(): void {
		if ( $this->defaults_registered ) {
			return;
		}

		$labels = array(
			'text' => 'Text', 'textarea' => 'Text area', 'rich-text' => 'Rich text', 'email' => 'Email',
			'tel' => 'Telephone', 'url' => 'URL', 'number' => 'Number', 'range' => 'Range', 'date' => 'Date',
			'time' => 'Time', 'datetime' => 'Date and time', 'colour' => 'Colour', 'select' => 'Select',
			'radio' => 'Radio', 'true-false' => 'True/false', 'consent' => 'Consent', 'hidden' => 'Hidden',
			'file' => 'File', 'image' => 'Image', 'link' => 'Link', 'post-object' => 'Post object',
			'taxonomy' => 'Taxonomy term', 'taxonomy-type' => 'Taxonomy', 'post-type' => 'Post type', 'user' => 'User', 'location' => 'Location',
			'group' => 'Group', 'name' => 'Name', 'address' => 'Address',
		);

		foreach ( $labels as $slug => $label ) {
			$type = in_array( $slug, array( 'number', 'range' ), true ) ? 'number' : ( 'true-false' === $slug ? 'boolean' : ( in_array( $slug, array( 'link', 'location', 'group', 'name', 'address' ), true ) ? 'object' : 'string' ) );
			$this->register( new StandardType( $slug, __( $label, 'adaptive-content-enhancer' ), $type ) );
		}

		foreach ( array( 'checkbox' => 'Checkboxes', 'gallery' => 'Gallery', 'relationship' => 'Relationship list', 'repeater' => 'Repeater', 'flexible' => 'Flexible content' ) as $slug => $label ) {
			$this->register( new StandardType( $slug, __( $label, 'adaptive-content-enhancer' ), 'array' ) );
		}

		$this->defaults_registered = true;
		do_action( 'ace_field_types_register', $this );
	}
}
