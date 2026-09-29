import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import './style.scss';

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes } ) {
		return <>
			<InspectorControls><PanelBody title={ __( 'Step settings', 'adaptive-content-enhancer' ) }>
				<TextControl label={ __( 'Step title', 'adaptive-content-enhancer' ) } value={ attributes.title } onChange={ ( title ) => setAttributes( { title } ) } />
				<TextControl label={ __( 'Description', 'adaptive-content-enhancer' ) } value={ attributes.description } onChange={ ( description ) => setAttributes( { description } ) } />
			</PanelBody></InspectorControls>
			<section { ...useBlockProps( { className: 'ace-form-step-editor' } ) }>
				<h3>{ attributes.title || __( 'Form step', 'adaptive-content-enhancer' ) }</h3>
				{ attributes.description && <p>{ attributes.description }</p> }
				<InnerBlocks allowedBlocks={ [ 'adaptive-content-enhancer/field', 'core/paragraph', 'core/heading', 'core/group' ] } template={ [ [ 'adaptive-content-enhancer/field', { key: 'step_field', label: 'Step field' } ] ] } />
			</section>
		</>;
	},
	save() { return <InnerBlocks.Content />; },
} );
