import { registerBlockType, registerBlockVariation } from '@wordpress/blocks';
import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { ColorPalette, PanelBody, RangeControl, SelectControl, TextControl, TextareaControl, ToggleControl } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import './style.scss';

const TEMPLATE = [
	[ 'adaptive-content-enhancer/field', { key: 'name', label: 'Name', required: true } ],
	[ 'adaptive-content-enhancer/field', { key: 'email', type: 'email', label: 'Email address', required: true } ],
	[ 'adaptive-content-enhancer/field', { key: 'message', type: 'textarea', label: 'Message', required: true } ],
	[ 'adaptive-content-enhancer/submit', { text: 'Send enquiry' } ],
];

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes } ) {
		const palette = window.aceFormsPalette || [];
		useEffect( () => {
			if ( ! attributes.formId ) {
				setAttributes( { formId: window.crypto.randomUUID() } );
			}
		}, [ attributes.formId, setAttributes ] );

		const settings = attributes.settings || {};
		const update = ( key, value ) => setAttributes( { settings: { ...settings, [ key ]: value } } );
		const appearance = attributes.appearance || {};
		const updateAppearance = ( key, value ) => setAttributes( { appearance: { ...appearance, [ key ]: value } } );
		const colourValue = ( part ) => palette.find( ( item ) => item.slug === appearance[ part ] )?.color;
		const colourControl = ( part, label ) => <div className="ace-form-colour-control">
			<p><strong>{ label }</strong></p>
			<ColorPalette colors={ palette } value={ colourValue( part ) } onChange={ ( value ) => updateAppearance( part, palette.find( ( item ) => item.color === value )?.slug || '' ) } disableCustomColors clearable />
		</div>;
		const appearanceClasses = `ace-form-editor ace-input--${ appearance.style || 'outlined' } ace-labels--${ appearance.labels || 'floating' } ace-density--${ appearance.density || 'comfortable' }`;
		const appearanceStyle = {
			...( appearance.accent ? { '--ace-form-accent': `var(--wp--preset--color--${ appearance.accent })` } : {} ),
			...( appearance.surface ? { '--ace-form-surface': `var(--wp--preset--color--${ appearance.surface })` } : {} ),
			...( appearance.text ? { '--ace-form-text': `var(--wp--preset--color--${ appearance.text })` } : {} ),
			'--ace-form-radius': `${ appearance.radius ?? 8 }px`,
		};
		return <>
			<InspectorControls>
				<PanelBody title={ __( 'Form settings', 'adaptive-content-enhancer' ) }>
					<TextControl label={ __( 'Form name', 'adaptive-content-enhancer' ) } value={ attributes.name } onChange={ ( name ) => setAttributes( { name } ) } />
					{ window.aceFormsCanManage && <>
					<TextControl label={ __( 'Recipients', 'adaptive-content-enhancer' ) } help={ __( 'Separate email addresses with commas.', 'adaptive-content-enhancer' ) } value={ settings.to || '' } onChange={ ( value ) => update( 'to', value ) } />
					<TextControl label={ __( 'Sender', 'adaptive-content-enhancer' ) } help={ __( 'Optional. Use a verified address, for example Name <no-reply@{site_domain}>.', 'adaptive-content-enhancer' ) } value={ settings.from || '' } onChange={ ( value ) => update( 'from', value ) } />
					<TextControl label={ __( 'Subject', 'adaptive-content-enhancer' ) } value={ settings.subject || '' } onChange={ ( value ) => update( 'subject', value ) } />
					<TextareaControl label={ __( 'Email body', 'adaptive-content-enhancer' ) } help={ __( 'Use {field:email}, {post_title} and {page_url} as merge tags.', 'adaptive-content-enhancer' ) } value={ settings.body || '' } onChange={ ( value ) => update( 'body', value ) } />
					<TextControl label={ __( 'Reply-To', 'adaptive-content-enhancer' ) } help={ __( 'Optional. Defaults to the sender name and email entered in the form.', 'adaptive-content-enhancer' ) } value={ settings.replyTo || '' } onChange={ ( value ) => update( 'replyTo', value ) } />
					<TextControl label={ __( 'CC', 'adaptive-content-enhancer' ) } value={ settings.cc || '' } onChange={ ( value ) => update( 'cc', value ) } />
					<TextControl label={ __( 'BCC', 'adaptive-content-enhancer' ) } value={ settings.bcc || '' } onChange={ ( value ) => update( 'bcc', value ) } />
					<TextControl label={ __( 'Success message', 'adaptive-content-enhancer' ) } value={ settings.successMessage || '' } onChange={ ( value ) => update( 'successMessage', value ) } />
					<ToggleControl label={ __( 'Send a confirmation email', 'adaptive-content-enhancer' ) } checked={ !! settings.confirmation?.enabled } onChange={ ( enabled ) => update( 'confirmation', { ...( settings.confirmation || {} ), enabled } ) } />
					{ settings.confirmation?.enabled && <>
						<TextControl label={ __( 'Confirmation sender', 'adaptive-content-enhancer' ) } value={ settings.confirmation.from || '' } onChange={ ( from ) => update( 'confirmation', { ...settings.confirmation, from } ) } />
						<TextControl label={ __( 'Confirmation subject', 'adaptive-content-enhancer' ) } value={ settings.confirmation.subject || '' } onChange={ ( subject ) => update( 'confirmation', { ...settings.confirmation, subject } ) } />
						<TextareaControl label={ __( 'Confirmation body', 'adaptive-content-enhancer' ) } value={ settings.confirmation.body || '' } onChange={ ( body ) => update( 'confirmation', { ...settings.confirmation, body } ) } />
					</> }
					</> }
				</PanelBody>
				<PanelBody title={ __( 'Input appearance', 'adaptive-content-enhancer' ) } initialOpen={ false }>
					<SelectControl label={ __( 'Input style', 'adaptive-content-enhancer' ) } value={ appearance.style || 'outlined' } options={ [
						{ label: __( 'Outlined', 'adaptive-content-enhancer' ), value: 'outlined' },
						{ label: __( 'Filled', 'adaptive-content-enhancer' ), value: 'filled' },
						{ label: __( 'Underlined', 'adaptive-content-enhancer' ), value: 'underlined' },
						{ label: __( 'Soft', 'adaptive-content-enhancer' ), value: 'soft' },
					] } onChange={ ( value ) => updateAppearance( 'style', value ) } />
					<SelectControl label={ __( 'Labels', 'adaptive-content-enhancer' ) } value={ appearance.labels || 'floating' } options={ [
						{ label: __( 'Above inputs', 'adaptive-content-enhancer' ), value: 'standard' },
						{ label: __( 'Floating inside inputs', 'adaptive-content-enhancer' ), value: 'floating' },
					] } onChange={ ( value ) => updateAppearance( 'labels', value ) } />
					<SelectControl label={ __( 'Input spacing', 'adaptive-content-enhancer' ) } value={ appearance.density || 'comfortable' } options={ [
						{ label: __( 'Comfortable', 'adaptive-content-enhancer' ), value: 'comfortable' },
						{ label: __( 'Compact', 'adaptive-content-enhancer' ), value: 'compact' },
					] } onChange={ ( value ) => updateAppearance( 'density', value ) } />
					<RangeControl label={ __( 'Corner radius', 'adaptive-content-enhancer' ) } min={ 0 } max={ 24 } value={ appearance.radius ?? 8 } onChange={ ( value ) => updateAppearance( 'radius', value ) } />
					{ colourControl( 'accent', __( 'Focus and button colour', 'adaptive-content-enhancer' ) ) }
					{ colourControl( 'surface', __( 'Input surface colour', 'adaptive-content-enhancer' ) ) }
					{ colourControl( 'text', __( 'Input text colour', 'adaptive-content-enhancer' ) ) }
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps( { className: appearanceClasses, style: appearanceStyle } ) }>
				<InnerBlocks template={ TEMPLATE } />
			</div>
		</>;
	},
	save() {
		return <InnerBlocks.Content />;
	},
} );

registerBlockVariation( metadata.name, {
	name: 'product-enquiry',
	title: __( 'Product enquiry', 'adaptive-content-enhancer' ),
	description: __( 'A form that includes the current product title.', 'adaptive-content-enhancer' ),
	attributes: { name: 'Product enquiry' },
	innerBlocks: [
		[ 'adaptive-content-enhancer/field', { key: 'name', type: 'text', label: 'Name', required: true } ],
		[ 'adaptive-content-enhancer/field', { key: 'email', type: 'email', label: 'Email address', required: true } ],
		[ 'adaptive-content-enhancer/field', { key: 'product', type: 'hidden', defaultValue: { source: 'post_title' } } ],
		[ 'adaptive-content-enhancer/field', { key: 'message', type: 'textarea', label: 'Your enquiry', required: true } ],
		[ 'adaptive-content-enhancer/submit', { text: 'Enquire about this product' } ],
	],
	scope: [ 'inserter' ],
} );
