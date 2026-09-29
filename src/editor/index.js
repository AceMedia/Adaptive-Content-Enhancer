import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { MediaUpload, MediaUploadCheck, RichText } from '@wordpress/block-editor';
import { Button, SelectControl, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

function AssociationControl( { field, value, onChange } ) {
	const [ search, setSearch ] = useState( '' );
	const [ options, setOptions ] = useState( [] );
	const multiple = field.type === 'relationship' || !! field.association?.multiple || !! field.acf?.settings?.multiple || [ 'multi_select', 'checkbox' ].includes( field.acf?.settings?.field_type );
	const selected = multiple ? ( Array.isArray( value ) ? value : [] ) : ( value ? [ value ] : [] );
	const postTypes = field.association?.postTypes || field.acf?.settings?.post_type || [];
	const taxonomies = field.association?.taxonomies || field.acf?.settings?.taxonomy || [];
	useEffect( () => {
		let active = true;
		const params = new URLSearchParams( { type: field.type, search } );
		for ( const slug of Array.isArray( postTypes ) ? postTypes : [ postTypes ] ) if ( slug ) params.append( 'postTypes[]', slug );
		for ( const slug of Array.isArray( taxonomies ) ? taxonomies : [ taxonomies ] ) if ( slug ) params.append( 'taxonomies[]', slug );
		for ( const item of selected ) params.append( 'selected[]', item );
		apiFetch( { path: `/ace-fields/v1/association-options?${ params }` } ).then( ( result ) => { if ( active ) setOptions( result ); } ).catch( () => { if ( active ) setOptions( [] ); } );
		return () => { active = false; };
	}, [ field.type, search, JSON.stringify( postTypes ), JSON.stringify( taxonomies ), selected.join( ',' ) ] );
	return <div className="ace-association-control">
		<TextControl label={ __( 'Search choices', 'adaptive-content-enhancer' ) } value={ search } onChange={ setSearch } />
		<SelectControl label={ field.label || field.key } multiple={ multiple } value={ multiple ? selected.map( String ) : String( value || '' ) } options={ [ ...( multiple ? [] : [ { value: '', label: __( 'Choose…', 'adaptive-content-enhancer' ) } ] ), ...options ] } onChange={ ( chosen ) => onChange( multiple ? chosen.map( ( item ) => [ 'post-type', 'taxonomy-type' ].includes( field.type ) ? item : Number( item ) ) : ( [ 'post-type', 'taxonomy-type' ].includes( field.type ) ? chosen : Number( chosen ) || 0 ) ) } help={ field.help } />
	</div>;
}

function FieldControl( { field, value, onChange } ) {
	const type = field.type;
	const label = field.label || field.key;
	const choices = field.choices || [];
	if ( type === 'true-false' ) return <ToggleControl label={ label } checked={ value === true || value === 1 || value === '1' } onChange={ ( checked ) => onChange( checked ? '1' : '0' ) } />;
	if ( [ 'select', 'radio' ].includes( type ) ) return <SelectControl label={ label } value={ value || '' } options={ [ { value: '', label: __( 'Choose…', 'adaptive-content-enhancer' ) }, ...choices ] } onChange={ onChange } />;
	if ( type === 'checkbox' ) return <fieldset><legend>{ label }</legend>{ choices.map( ( choice ) => <ToggleControl key={ choice.value } label={ choice.label } checked={ ( value || [] ).includes( choice.value ) } onChange={ ( checked ) => onChange( checked ? [ ...( value || [] ), choice.value ] : ( value || [] ).filter( ( item ) => item !== choice.value ) ) } /> ) }</fieldset>;
	if ( [ 'image', 'file', 'gallery' ].includes( type ) ) {
		const multiple = type === 'gallery';
		const ids = multiple ? ( Array.isArray( value ) ? value : [] ) : ( value ? [ Number( value ) ] : [] );
		return <div className="ace-meta-media"><p><strong>{ label }</strong></p><MediaUploadCheck><MediaUpload multiple={ multiple } gallery={ multiple } allowedTypes={ type === 'file' ? undefined : [ 'image' ] } value={ multiple ? ids : ids[ 0 ] } onSelect={ ( media ) => onChange( multiple ? media.map( ( item ) => item.id ) : media.id ) } render={ ( { open } ) => <Button variant="secondary" onClick={ open }>{ ids.length ? __( 'Change media', 'adaptive-content-enhancer' ) : __( 'Choose media', 'adaptive-content-enhancer' ) }</Button> } /></MediaUploadCheck>{ ids.length > 0 && <p>{ ids.length === 1 ? `#${ ids[ 0 ] }` : `${ ids.length } items` } <Button variant="tertiary" isDestructive onClick={ () => onChange( multiple ? [] : '' ) }>{ __( 'Remove', 'adaptive-content-enhancer' ) }</Button></p> }</div>;
	}
	if ( type === 'rich-text' ) return <div><p><strong>{ label }</strong></p><RichText tagName="div" value={ value || '' } onChange={ onChange } placeholder={ label } /></div>;
	if ( type === 'textarea' ) return <TextareaControl label={ label } value={ value || '' } onChange={ onChange } help={ field.help } />;
	if ( type === 'location' ) return <fieldset><legend>{ label }</legend><TextControl label={ __( 'Address', 'adaptive-content-enhancer' ) } value={ value?.address || '' } onChange={ ( address ) => onChange( { ...( value || {} ), address } ) } /><TextControl label={ __( 'Latitude', 'adaptive-content-enhancer' ) } type="number" value={ value?.lat || '' } onChange={ ( lat ) => onChange( { ...( value || {} ), lat } ) } /><TextControl label={ __( 'Longitude', 'adaptive-content-enhancer' ) } type="number" value={ value?.lng || '' } onChange={ ( lng ) => onChange( { ...( value || {} ), lng } ) } /></fieldset>;
	if ( type === 'link' ) return <fieldset><legend>{ label }</legend><TextControl label={ __( 'URL', 'adaptive-content-enhancer' ) } type="url" value={ value?.url || '' } onChange={ ( url ) => onChange( { ...( value || {} ), url } ) } /><TextControl label={ __( 'Link text', 'adaptive-content-enhancer' ) } value={ value?.title || '' } onChange={ ( title ) => onChange( { ...( value || {} ), title } ) } /><ToggleControl label={ __( 'Open in new tab', 'adaptive-content-enhancer' ) } checked={ value?.target === '_blank' } onChange={ ( checked ) => onChange( { ...( value || {} ), target: checked ? '_blank' : '' } ) } /></fieldset>;
	if ( [ 'relationship', 'post-object', 'taxonomy', 'post-type', 'taxonomy-type', 'user' ].includes( type ) ) return <AssociationControl field={ field } value={ value } onChange={ onChange } />;
	return <TextControl label={ label } type={ { email: 'email', url: 'url', number: 'number', range: 'number', date: 'date', time: 'time', datetime: 'datetime-local', colour: 'color' }[ type ] || 'text' } value={ value ?? '' } onChange={ onChange } help={ field.help } />;
}

function FieldsPanel() {
	const postType = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostType(), [] );
	const postId = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );
	const [ meta, setMeta ] = useEntityProp( 'postType', postType || 'post', 'meta' );
	const [ groups, setGroups ] = useState( [] );
	useEffect( () => {
		if ( ! postType || postType === 'ace_field_group' ) return;
		let active = true;
		apiFetch( { path: `/ace-fields/v1/groups?post_type=${ encodeURIComponent( postType ) }&post_id=${ postId || 0 }` } ).then( ( result ) => { if ( active ) setGroups( result ); } ).catch( () => { if ( active ) setGroups( [] ); } );
		return () => { active = false; };
	}, [ postType, postId ] );
	if ( ! meta || ! groups.length ) return null;
	return <>{ groups.filter( ( group ) => group.presentation !== 'bindings' ).map( ( group ) => <PluginDocumentSettingPanel key={ group.key } name={ `ace-fields-${ group.key }` } title={ group.title }>
		{ group.fields.filter( ( field ) => ! [ 'repeater', 'flexible', 'group' ].includes( field.type ) ).map( ( field ) => <FieldControl key={ field.key } field={ field } value={ meta[ field.key ] } onChange={ ( value ) => setMeta( { ...meta, [ field.key ]: value } ) } /> ) }
	</PluginDocumentSettingPanel> ) }</>;
}

registerPlugin( 'ace-field-values', { render: FieldsPanel } );
