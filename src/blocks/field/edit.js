import { RichText, useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { getFieldType, getFieldTypes } from '../../field-types';

export default function Edit( { attributes, setAttributes, context } ) {
	const type = getFieldType( attributes.type );
	const contextType = context?.[ 'ace/fieldGroup' ] ? 'meta' : 'form';
	const floating = context?.[ 'ace/appearance' ]?.labels !== 'standard' && [ 'text', 'email', 'tel', 'url', 'number', 'textarea', 'select' ].includes( attributes.type );
	const associationType = [ 'post-object', 'relationship', 'taxonomy', 'post-type', 'taxonomy-type', 'user' ].includes( attributes.type );
	const multiple = attributes.type === 'relationship' || !! attributes.association?.multiple;
	const [ sources, setSources ] = useState( { postTypes: [], taxonomies: [] } );
	const [ previewOptions, setPreviewOptions ] = useState( [] );
	useEffect( () => {
		apiFetch( { path: '/ace-fields/v1/association-sources' } ).then( setSources ).catch( () => {} );
	}, [] );
	useEffect( () => {
		if ( ! associationType ) return;
		const params = new URLSearchParams( { type: attributes.type } );
		for ( const slug of attributes.association?.postTypes || [] ) params.append( 'postTypes[]', slug );
		for ( const slug of attributes.association?.taxonomies || [] ) params.append( 'taxonomies[]', slug );
		apiFetch( { path: `/ace-fields/v1/association-options?${ params }` } ).then( setPreviewOptions ).catch( () => setPreviewOptions( [] ) );
	}, [ attributes.type, attributes.association?.postTypes?.join( ',' ), attributes.association?.taxonomies?.join( ',' ) ] );
	const placeholder = floating ? ' ' : attributes.placeholder;
	const preview = associationType
		? <select multiple={ multiple } value={ multiple ? [] : '' } onChange={ () => {} }><option value="">{ __( 'Choose…', 'adaptive-content-enhancer' ) }</option>{ previewOptions.map( ( option ) => <option key={ option.value } value={ option.value }>{ option.label }</option> ) }</select>
		: attributes.type === 'textarea'
		? <textarea readOnly placeholder={ placeholder } defaultValue={ attributes.defaultValue?.value || '' } />
		: attributes.type === 'select'
			? <select defaultValue=""><option value="">{ __( 'Choose…', 'adaptive-content-enhancer' ) }</option>{ ( attributes.choices || [] ).map( ( choice, index ) => <option key={ index } value={ choice.value }>{ choice.label }</option> ) }</select>
			: [ 'radio', 'checkbox' ].includes( attributes.type )
				? <div className="ace-field-choices">{ ( attributes.choices || [] ).map( ( choice, index ) => <label key={ index }><input type={ attributes.type } name={ attributes.key } /> { choice.label }</label> ) }</div>
				: [ 'consent', 'true-false' ].includes( attributes.type )
					? <input type="checkbox" />
					: <input type={ [ 'email', 'tel', 'url', 'number', 'range', 'date', 'time', 'file' ].includes( attributes.type ) ? attributes.type : ( attributes.type === 'datetime' ? 'datetime-local' : attributes.type === 'colour' ? 'color' : 'text' ) } readOnly={ attributes.type !== 'file' } placeholder={ placeholder } defaultValue={ attributes.type === 'file' ? undefined : attributes.defaultValue?.value || '' } />;

	return (
		<>
			<InspectorControls><PanelBody title={ __( 'Field settings', 'adaptive-content-enhancer' ) }>
				<SelectControl label={ __( 'Type', 'adaptive-content-enhancer' ) } value={ attributes.type } options={ getFieldTypes( contextType ).map( ( item ) => ( { label: item.label, value: item.slug } ) ) } onChange={ ( value ) => setAttributes( { type: value } ) } />
				<TextControl label={ __( 'Field key', 'adaptive-content-enhancer' ) } value={ attributes.key } onChange={ ( key ) => setAttributes( { key: key.toLowerCase().replace( /[^a-z0-9_-]/g, '' ) } ) } />
				<TextControl label={ __( 'Placeholder', 'adaptive-content-enhancer' ) } value={ attributes.placeholder } onChange={ ( placeholder ) => setAttributes( { placeholder } ) } />
				<ToggleControl label={ __( 'Required', 'adaptive-content-enhancer' ) } checked={ attributes.required } onChange={ ( required ) => setAttributes( { required } ) } />
				{ contextType === 'form' && <>
					<SelectControl label={ __( 'Default value from', 'adaptive-content-enhancer' ) } value={ attributes.defaultValue?.source || 'static' } options={ [ { label: __( 'Fixed value', 'adaptive-content-enhancer' ), value: 'static' }, { label: __( 'URL parameter', 'adaptive-content-enhancer' ), value: 'query' }, { label: __( 'Current post title', 'adaptive-content-enhancer' ), value: 'post_title' } ] } onChange={ ( source ) => setAttributes( { defaultValue: { ...attributes.defaultValue, source } } ) } />
					{ ( ! attributes.defaultValue?.source || attributes.defaultValue.source === 'static' ) && <TextControl label={ __( 'Default value', 'adaptive-content-enhancer' ) } value={ attributes.defaultValue?.value || '' } onChange={ ( value ) => setAttributes( { defaultValue: { source: 'static', value } } ) } /> }
					{ attributes.defaultValue?.source === 'query' && <TextControl label={ __( 'URL parameter name', 'adaptive-content-enhancer' ) } value={ attributes.defaultValue?.param || '' } onChange={ ( param ) => setAttributes( { defaultValue: { source: 'query', param: param.toLowerCase().replace( /[^a-z0-9_-]/g, '' ) } } ) } /> }
				</> }
				{ [ 'select', 'radio', 'checkbox' ].includes( attributes.type ) && <TextareaControl label={ __( 'Choices', 'adaptive-content-enhancer' ) } help={ __( 'One choice per line.', 'adaptive-content-enhancer' ) } value={ ( attributes.choices || [] ).map( ( choice ) => choice.label ).join( '\n' ) } onChange={ ( value ) => setAttributes( { choices: value.split( '\n' ).filter( Boolean ).map( ( label ) => ( { label, value: label } ) ) } ) } /> }
				{ [ 'post-object', 'relationship' ].includes( attributes.type ) && <SelectControl multiple label={ __( 'Allowed post types', 'adaptive-content-enhancer' ) } help={ __( 'Leave empty to allow every public post type.', 'adaptive-content-enhancer' ) } value={ attributes.association?.postTypes || [] } options={ sources.postTypes } onChange={ ( postTypes ) => setAttributes( { association: { ...attributes.association, postTypes } } ) } /> }
				{ attributes.type === 'taxonomy' && <SelectControl multiple label={ __( 'Allowed taxonomies', 'adaptive-content-enhancer' ) } help={ __( 'Leave empty to allow every public taxonomy.', 'adaptive-content-enhancer' ) } value={ attributes.association?.taxonomies || [] } options={ sources.taxonomies } onChange={ ( taxonomies ) => setAttributes( { association: { ...attributes.association, taxonomies } } ) } /> }
				{ [ 'post-object', 'taxonomy', 'post-type', 'taxonomy-type' ].includes( attributes.type ) && <ToggleControl label={ __( 'Allow multiple selections', 'adaptive-content-enhancer' ) } checked={ !! attributes.association?.multiple } onChange={ ( multipleValue ) => setAttributes( { association: { ...attributes.association, multiple: multipleValue } } ) } /> }
				{ contextType === 'form' && <>
					<ToggleControl label={ __( 'Show conditionally', 'adaptive-content-enhancer' ) } checked={ attributes.conditions?.field !== undefined } onChange={ ( checked ) => setAttributes( { conditions: checked ? { field: '', operator: '==', value: '' } : {} } ) } />
					{ attributes.conditions?.field !== undefined && <>
						<TextControl label={ __( 'Other field key', 'adaptive-content-enhancer' ) } value={ attributes.conditions.field || '' } onChange={ ( field ) => setAttributes( { conditions: { ...attributes.conditions, field: field.toLowerCase().replace( /[^a-z0-9_-]/g, '' ) } } ) } />
						<SelectControl label={ __( 'Comparison', 'adaptive-content-enhancer' ) } value={ attributes.conditions.operator || '==' } options={ [ { label: __( 'Equals', 'adaptive-content-enhancer' ), value: '==' }, { label: __( 'Does not equal', 'adaptive-content-enhancer' ), value: '!=' } ] } onChange={ ( operator ) => setAttributes( { conditions: { ...attributes.conditions, operator } } ) } />
						<TextControl label={ __( 'Value', 'adaptive-content-enhancer' ) } value={ attributes.conditions.value || '' } onChange={ ( value ) => setAttributes( { conditions: { ...attributes.conditions, value } } ) } />
					</> }
				</> }
			</PanelBody></InspectorControls>
			<div { ...useBlockProps( { className: `ace-field ace-form-field ace-form-field--${ attributes.type }${ floating ? ' ace-field--floating' : '' }${ attributes.required ? ' ace-form-field--required' : '' }` } ) }>
				{ attributes.type === 'hidden' && contextType === 'form' ? <span className="ace-hidden-field-preview">{ __( 'Hidden input', 'adaptive-content-enhancer' ) }: { attributes.key }</span> : <>
					<RichText tagName="label" className="ace-form-field__label" value={ attributes.label } placeholder={ type?.label || __( 'Field label', 'adaptive-content-enhancer' ) } onChange={ ( label ) => setAttributes( { label, ...( ! attributes.key ? { key: label.toLowerCase().replace( /[^a-z0-9]+/g, '-' ).replace( /^-|-$/g, '' ) } : {} ) } ) } />
					{ attributes.required && contextType === 'form' && <span className="ace-form-field__requirement" aria-hidden="true">{ __( 'Required', 'adaptive-content-enhancer' ) }</span> }
					{ contextType === 'meta' ? <span className="ace-meta-type-preview">{ type?.label || attributes.type } · { attributes.key }</span> : preview }
				</> }
			</div>
		</>
	);
}
