import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, SelectControl, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import './style.scss';

const config = window.aceTermFields;

function FieldInput( { field, value, onChange } ) {
	const label = field.label || field.key;
	const type = field.type;
	if ( type === 'true-false' ) return <ToggleControl label={ label } checked={ value === '1' || value === true || value === 1 } onChange={ ( checked ) => onChange( checked ? '1' : '0' ) } />;
	if ( [ 'image', 'file' ].includes( type ) ) {
		const choose = () => {
			const frame = window.wp.media( { title: label, multiple: false, library: type === 'image' ? { type: 'image' } : {} } );
			frame.on( 'select', () => onChange( frame.state().get( 'selection' ).first()?.get( 'id' ) || 0 ) );
			frame.open();
		};
		return <div className="ace-term-media"><strong>{ label }</strong><p>{ value ? `#${ value }` : __( 'No file selected', 'adaptive-content-enhancer' ) }</p><Button variant="secondary" onClick={ choose }>{ __( 'Choose media', 'adaptive-content-enhancer' ) }</Button>{ !! value && <Button variant="tertiary" onClick={ () => onChange( 0 ) }>{ __( 'Remove', 'adaptive-content-enhancer' ) }</Button> }</div>;
	}
	if ( type === 'location' ) return <fieldset><legend>{ label }</legend>{ [ 'address', 'lat', 'lng' ].map( ( part ) => <TextControl key={ part } label={ part } value={ value?.[ part ] || '' } onChange={ ( next ) => onChange( { ...( value || {} ), [ part ]: next } ) } /> ) }</fieldset>;
	if ( [ 'rich-text', 'textarea' ].includes( type ) ) return <TextareaControl label={ label } value={ value || '' } rows={ type === 'rich-text' ? 6 : 3 } onChange={ onChange } />;
	if ( [ 'select', 'radio' ].includes( type ) && field.choices?.length ) return <SelectControl label={ label } value={ value || '' } options={ [ { label: __( 'Choose…', 'adaptive-content-enhancer' ), value: '' }, ...field.choices ] } onChange={ onChange } />;
	if ( type === 'relationship' ) return <TextControl label={ label } help={ __( 'Enter post IDs separated by commas.', 'adaptive-content-enhancer' ) } value={ Array.isArray( value ) ? value.join( ', ' ) : '' } onChange={ ( text ) => onChange( text.split( ',' ).map( ( item ) => Number( item.trim() ) ).filter( Boolean ) ) } />;
	return <TextControl label={ label } type={ { email: 'email', url: 'url', number: 'number', range: 'number', colour: 'color', date: 'date' }[ type ] || 'text' } value={ value ?? '' } onChange={ onChange } />;
}

function RowsInput( { field, value, onChange } ) {
	const layouts = field.type === 'flexible' ? field.subFields || [] : [ { key: 'row', label: field.label, subFields: field.subFields || [] } ];
	const rows = Array.isArray( value ) ? value : [];
	const setRow = ( index, next ) => onChange( rows.map( ( row, i ) => i === index ? next : row ) );
	return <fieldset className="ace-term-rows"><legend><strong>{ field.label || field.key }</strong></legend>
		{ rows.map( ( row, index ) => {
			const layout = layouts.find( ( item ) => item.key === row.acf_fc_layout ) || layouts[ 0 ];
			return <div className="ace-term-row" key={ index }><h4>{ layout?.label || field.label } { index + 1 }</h4>
				{ field.type === 'flexible' && <SelectControl label={ __( 'Layout', 'adaptive-content-enhancer' ) } value={ row.acf_fc_layout || '' } options={ layouts.map( ( item ) => ( { label: item.label, value: item.key } ) ) } onChange={ ( acf_fc_layout ) => setRow( index, { acf_fc_layout } ) } /> }
				{ ( layout?.subFields || [] ).map( ( subfield ) => <FieldInput key={ subfield.key } field={ subfield } value={ row[ subfield.key ] } onChange={ ( next ) => setRow( index, { ...row, [ subfield.key ]: next } ) } /> ) }
				<Button variant="tertiary" isDestructive onClick={ () => onChange( rows.filter( ( _, i ) => i !== index ) ) }>{ __( 'Remove row', 'adaptive-content-enhancer' ) }</Button>
			</div>;
		} ) }
		<Button variant="secondary" onClick={ () => onChange( [ ...rows, field.type === 'flexible' ? { acf_fc_layout: layouts[ 0 ]?.key || '' } : {} ] ) }>{ __( 'Add row', 'adaptive-content-enhancer' ) }</Button>
	</fieldset>;
}

function App() {
	const [ values, setValues ] = useState( {} );
	const [ rows, setRows ] = useState( {} );
	const [ status, setStatus ] = useState( '' );
	const [ busy, setBusy ] = useState( true );
	const fields = config.groups.flatMap( ( group ) => group.fields );
	const rowFields = fields.filter( ( field ) => [ 'repeater', 'flexible' ].includes( field.type ) );
	const valuesPath = `/ace-fields/v1/values/term/${ config.termId }`;
	useEffect( () => {
		Promise.all( [ apiFetch( { path: valuesPath } ), ...rowFields.map( ( field ) => apiFetch( { path: `/ace-fields/v1/rows/term/${ config.termId }/${ field.key }` } ) ) ] )
			.then( ( results ) => { setValues( results[ 0 ] ); setRows( Object.fromEntries( rowFields.map( ( field, index ) => [ field.key, results[ index + 1 ] ] ) ) ); } )
			.catch( ( error ) => setStatus( error.message ) )
			.finally( () => setBusy( false ) );
	}, [] );
	const save = async () => {
		setBusy( true );
		setStatus( '' );
		try {
			await apiFetch( { path: valuesPath, method: 'POST', data: { values } } );
			for ( const field of rowFields ) await apiFetch( { path: `/ace-fields/v1/rows/term/${ config.termId }/${ field.key }`, method: 'POST', data: { rows: rows[ field.key ] || [] } } );
			setStatus( __( 'Field values saved.', 'adaptive-content-enhancer' ) );
		} catch ( error ) { setStatus( error.message ); }
		finally { setBusy( false ); }
	};
	return <div className="ace-term-fields">
		{ config.groups.map( ( group ) => <section key={ group.id }><h3>{ group.title }</h3>{ group.fields.map( ( field ) => [ 'repeater', 'flexible' ].includes( field.type )
			? <RowsInput key={ field.key } field={ field } value={ rows[ field.key ] } onChange={ ( next ) => setRows( { ...rows, [ field.key ]: next } ) } />
			: <FieldInput key={ field.key } field={ field } value={ values[ field.key ] } onChange={ ( next ) => setValues( { ...values, [ field.key ]: next } ) } /> ) }</section> ) }
		{ status && <Notice status={ status.includes( 'saved' ) ? 'success' : 'error' } isDismissible={ false }>{ status }</Notice> }
		<Button variant="primary" isBusy={ busy } disabled={ busy } onClick={ save }>{ __( 'Save field values', 'adaptive-content-enhancer' ) }</Button>
	</div>;
}

const target = document.getElementById( 'ace-term-fields-editor' );
if ( target && config ) createRoot( target ).render( <App /> );
