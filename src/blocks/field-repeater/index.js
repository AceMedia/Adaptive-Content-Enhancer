import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes } ) {
		return <>
			<InspectorControls><PanelBody title={ __( 'Nested field', 'adaptive-content-enhancer' ) }>
				<TextControl label={ __( 'Field key', 'adaptive-content-enhancer' ) } value={ attributes.key } onChange={ ( value ) => setAttributes( { key: value.toLowerCase().replace( /[^a-z0-9_-]/g, '' ) } ) } />
				<TextControl label={ __( 'Label', 'adaptive-content-enhancer' ) } value={ attributes.label } onChange={ ( label ) => setAttributes( { label } ) } />
				<SelectControl label={ __( 'Type', 'adaptive-content-enhancer' ) } value={ attributes.type } options={ [ { value: 'group', label: __( 'Group', 'adaptive-content-enhancer' ) }, { value: 'repeater', label: __( 'Repeater', 'adaptive-content-enhancer' ) }, { value: 'flexible', label: __( 'Flexible content', 'adaptive-content-enhancer' ) } ] } onChange={ ( type ) => setAttributes( { type } ) } />
			</PanelBody></InspectorControls>
			<div { ...useBlockProps( { className: 'ace-field-repeater-editor' } ) }><h3>{ attributes.label || __( 'Nested fields', 'adaptive-content-enhancer' ) }</h3><InnerBlocks allowedBlocks={ [ 'adaptive-content-enhancer/field', 'adaptive-content-enhancer/field-repeater' ] } /></div>
		</>;
	},
	save() { return <InnerBlocks.Content />; },
} );
