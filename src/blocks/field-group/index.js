import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Button, PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import './style.scss';

const PARAMS = [
	[ 'post_type', 'Post type' ], [ 'taxonomy', 'Taxonomy' ], [ 'attachment', 'Attachment' ],
	[ 'block', 'Block' ], [ 'user_role', 'User role' ], [ 'options_page', 'Options page' ],
	[ 'page_template', 'Page template' ], [ 'post_status', 'Post status' ],
].map( ( [ value, label ] ) => ( { value, label: __( label, 'adaptive-content-enhancer' ) } ) );

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes } ) {
		const groups = attributes.location || [];
		const setRule = ( groupIndex, ruleIndex, key, value ) => setAttributes( { location: groups.map( ( group, gi ) => gi === groupIndex ? group.map( ( rule, ri ) => ri === ruleIndex ? { ...rule, [ key ]: value } : rule ) : group ) } );
		const addGroup = () => setAttributes( { location: [ ...groups, [ { param: 'post_type', operator: '==', value: 'post' } ] ] } );
		return <>
			<InspectorControls>
				<PanelBody title={ __( 'Field group', 'adaptive-content-enhancer' ) }>
					<TextControl label={ __( 'Group key', 'adaptive-content-enhancer' ) } value={ attributes.groupKey } onChange={ ( value ) => setAttributes( { groupKey: value.toLowerCase().replace( /[^a-z0-9_-]/g, '' ) } ) } />
					<SelectControl label={ __( 'Show values', 'adaptive-content-enhancer' ) } value={ attributes.presentation } options={ [ { value: 'sidebar', label: __( 'Editor sidebar', 'adaptive-content-enhancer' ) }, { value: 'main', label: __( 'Editor main column', 'adaptive-content-enhancer' ) }, { value: 'bindings', label: __( 'Block bindings only', 'adaptive-content-enhancer' ) } ] } onChange={ ( presentation ) => setAttributes( { presentation } ) } />
				</PanelBody>
				<PanelBody title={ __( 'Location rules', 'adaptive-content-enhancer' ) }>
					<p>{ __( 'A post matches when every rule in any one set matches.', 'adaptive-content-enhancer' ) }</p>
					{ groups.map( ( group, gi ) => <div className="ace-location-set" key={ gi }>
						<strong>{ __( 'Rule set', 'adaptive-content-enhancer' ) } { gi + 1 }</strong>
						{ group.map( ( rule, ri ) => <div className="ace-location-rule" key={ ri }>
							<SelectControl label={ __( 'Where', 'adaptive-content-enhancer' ) } value={ rule.param } options={ PARAMS } onChange={ ( value ) => setRule( gi, ri, 'param', value ) } />
							<SelectControl label={ __( 'Comparison', 'adaptive-content-enhancer' ) } value={ rule.operator || '==' } options={ [ { value: '==', label: __( 'Is', 'adaptive-content-enhancer' ) }, { value: '!=', label: __( 'Is not', 'adaptive-content-enhancer' ) } ] } onChange={ ( value ) => setRule( gi, ri, 'operator', value ) } />
							<TextControl label={ __( 'Value', 'adaptive-content-enhancer' ) } value={ rule.value || '' } onChange={ ( value ) => setRule( gi, ri, 'value', value ) } />
							<Button isDestructive variant="tertiary" onClick={ () => setAttributes( { location: groups.map( ( g, i ) => i === gi ? g.filter( ( _, j ) => j !== ri ) : g ).filter( ( g ) => g.length ) } ) }>{ __( 'Remove rule', 'adaptive-content-enhancer' ) }</Button>
						</div> ) }
						<Button variant="secondary" onClick={ () => setAttributes( { location: groups.map( ( g, i ) => i === gi ? [ ...g, { param: 'post_type', operator: '==', value: 'post' } ] : g ) } ) }>{ __( 'Add AND rule', 'adaptive-content-enhancer' ) }</Button>
					</div> ) }
					<Button variant="secondary" onClick={ addGroup }>{ __( 'Add OR rule set', 'adaptive-content-enhancer' ) }</Button>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps( { className: 'ace-field-group-editor' } ) }>
				<p><strong>{ __( 'Fields in this group', 'adaptive-content-enhancer' ) }</strong></p>
				<InnerBlocks allowedBlocks={ [ 'adaptive-content-enhancer/field', 'adaptive-content-enhancer/field-repeater', 'core/heading', 'core/paragraph', 'core/group' ] } template={ [ [ 'adaptive-content-enhancer/field', { key: 'new_field', label: 'New field' } ] ] } />
			</div>
		</>;
	},
	save() { return <InnerBlocks.Content />; },
} );
