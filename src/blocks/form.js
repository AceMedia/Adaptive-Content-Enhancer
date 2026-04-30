import { registerBlockType } from '@wordpress/blocks';
import {
	InnerBlocks,
	useBlockProps,
	InspectorControls,
} from '@wordpress/block-editor';
import { useEffect } from '@wordpress/element';
import { dispatch, select } from '@wordpress/data';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
	SelectControl,
	Panel,
} from '@wordpress/components';

const hasWooProductContext = Boolean( window.aceWooProductContext?.isAvailable );

const FIELD_STYLE_DEFAULTS = {
	inputPadding: '0.875rem 1rem',
	fieldGap: '1rem',
	labelGap: '0.5rem',
	inputBorderRadius: '0.5rem',
	inputBorderWidth: '1px',
	inputBorderColor: '#cfd4dc',
	inputBackgroundColor: '#ffffff',
	inputTextColor: '#1e1e1e',
	placeholderColor: '#6b7280',
	focusBorderColor: '#1d4ed8',
	focusBackgroundColor: '#ffffff',
	useFloatingPlaceholders: false,
	showRequiredIndicator: true,
};

registerBlockType( 'adaptive-content-enhancer/form', {
	title: 'Form Container',
	icon: 'feedback',
	category: 'layout',
	attributes: {
		formId: { type: 'string', default: '' },
		recipient: { type: 'string', default: '' },
		subject: { type: 'string', default: '' },
		successMessage: {
			type: 'string',
			default: 'Thank you for your message!',
		},
		errorMessage: {
			type: 'string',
			default: 'There was an error sending your message.',
		},
		enableRecaptcha: { type: 'boolean', default: false },
		enableFileUpload: { type: 'boolean', default: false },
		maxFileSize: { type: 'number', default: 2 },
		allowedFileTypes: { type: 'array', default: [ 'pdf', 'doc', 'docx' ] },
		emailTemplate: { type: 'string', default: '' },
		customClass: { type: 'string', default: '' },
		formAction: { type: 'string', default: 'email' },
		smtpSettings: {
			type: 'object',
			default: {
				host: '',
				port: '',
				username: '',
				password: '',
				encryption: 'tls',
			},
		},
		validation: {
			type: 'object',
			default: {
				requireValidation: true,
				validateEmail: true,
				validatePhone: false,
			},
		},
		fieldStyles: {
			type: 'object',
			default: FIELD_STYLE_DEFAULTS,
		},
	},
	supports: {
		html: false,
		styles: true,
		customClassName: true,
		attributes: true,
		inserter: true,
		align: true,
		color: {
			text: true,
			background: true,
			gradients: true,
			link: true,
		},
		typography: {
			fontSize: true,
			lineHeight: true,
			textAlign: true,
			__experimentalFontWeights: true,
			__experimentalFontFamily: true,
			__experimentalFontStyle: true,
			__experimentalFontVariant: true,
			__experimentalLetterSpacing: true,
			__experimentalTextTransform: true,
			customLineHeight: true,
			__experimentalCustomFontSize: true,
		},
		spacing: {
			blockGap: true,
			padding: true,
			margin: true,
		},
		__experimentalBorder: {
			color: true,
			radius: true,
			style: true,
			width: true,
			__experimentalDefaultControls: {
				color: true,
				radius: true,
				style: true,
				width: true,
			},
		},
		layout: true,
		dimensions: {
			minHeight: true,
		},
		shadow: true,
	},
	providesContext: {
		'adaptive-content-enhancer/isInputContext': true,
	},
	edit: ( { clientId, attributes, setAttributes } ) => {
		const TEMPLATE = [
			[ 'core/heading', { level: 3, content: 'Contact us' } ],
			[ 'core/paragraph', { content: 'Add a short intro or instructions here.' } ],
			[ 'adaptive-content-enhancer/text-field', {} ],
			[ 'core/button', { text: 'Submit' } ],
		];

		const fieldStyles = {
			...FIELD_STYLE_DEFAULTS,
			...( attributes.fieldStyles || {} ),
		};

		const updateFieldStyles = ( nextStyles ) => {
			setAttributes( {
				fieldStyles: {
					...fieldStyles,
					...nextStyles,
				},
			} );
		};

		const blockProps = useBlockProps( {
			className: `ace-form-container${
				fieldStyles.useFloatingPlaceholders
					? ' ace-form-container--floating-placeholders'
					: ''
			}${
				fieldStyles.showRequiredIndicator
					? ' ace-form-container--show-required'
					: ''
			}`,
			style: {
				display: 'grid',
				gap: attributes.style?.spacing?.blockGap || undefined,
				'--ace-form-field-gap': fieldStyles.fieldGap,
				'--ace-form-label-gap': fieldStyles.labelGap,
				'--ace-form-input-padding': fieldStyles.inputPadding,
				'--ace-form-input-radius': fieldStyles.inputBorderRadius,
				'--ace-form-input-border-width': fieldStyles.inputBorderWidth,
				'--ace-form-input-border-color': fieldStyles.inputBorderColor,
				'--ace-form-input-background': fieldStyles.inputBackgroundColor,
				'--ace-form-input-color': fieldStyles.inputTextColor,
				'--ace-form-placeholder-color': fieldStyles.placeholderColor,
				'--ace-form-focus-border-color': fieldStyles.focusBorderColor,
				'--ace-form-focus-background': fieldStyles.focusBackgroundColor,
			},
		} );

		useEffect( () => {
			const formBlock = select( 'core/block-editor' ).getBlock( clientId );
			if ( formBlock && formBlock.innerBlocks ) {
				formBlock.innerBlocks.forEach( ( block ) => {
					if ( block.name === 'core/button' ) {
						dispatch( 'core/block-editor' ).updateBlockAttributes(
							block.clientId,
							{
								lock: {
									move: true,
									remove: true,
								},
							}
						);
					}
				} );
			}
		}, [ clientId ] );

		return (
			<>
				<InspectorControls>
					<Panel>
						<PanelBody title="Basic Settings" initialOpen={ true }>
							<TextControl
								label="Form ID"
								value={ attributes.formId }
								onChange={ ( value ) =>
									setAttributes( { formId: value } )
								}
							/>
							<TextControl
								label="Recipient Email"
								value={ attributes.recipient }
								onChange={ ( value ) =>
									setAttributes( { recipient: value } )
								}
							/>
							<TextControl
								label="Subject Line"
								value={ attributes.subject }
								onChange={ ( value ) =>
									setAttributes( { subject: value } )
								}
							/>
							<TextareaControl
								label="Success Message"
								value={ attributes.successMessage }
								onChange={ ( value ) =>
									setAttributes( { successMessage: value } )
								}
							/>
							<TextareaControl
								label="Error Message"
								value={ attributes.errorMessage }
								onChange={ ( value ) =>
									setAttributes( { errorMessage: value } )
								}
							/>
						</PanelBody>

						<PanelBody title="Field Style Settings" initialOpen={ false }>
							<ToggleControl
								label="Use floating placeholders"
								checked={ fieldStyles.useFloatingPlaceholders }
								onChange={ ( value ) =>
									updateFieldStyles( {
										useFloatingPlaceholders: value,
									} )
								}
							/>
							<ToggleControl
								label="Show required indicator"
								checked={ fieldStyles.showRequiredIndicator }
								onChange={ ( value ) =>
									updateFieldStyles( {
										showRequiredIndicator: value,
									} )
								}
							/>
							<TextControl
								label="Field gap"
								help="Any CSS spacing value, for example 1rem."
								value={ fieldStyles.fieldGap }
								onChange={ ( value ) =>
									updateFieldStyles( { fieldGap: value } )
								}
							/>
							<TextControl
								label="Label gap"
								help="Space between a label and its control."
								value={ fieldStyles.labelGap }
								onChange={ ( value ) =>
									updateFieldStyles( { labelGap: value } )
								}
							/>
							<TextControl
								label="Input padding"
								help="Shorthand CSS padding, for example 0.875rem 1rem."
								value={ fieldStyles.inputPadding }
								onChange={ ( value ) =>
									updateFieldStyles( { inputPadding: value } )
								}
							/>
							<TextControl
								label="Input border radius"
								value={ fieldStyles.inputBorderRadius }
								onChange={ ( value ) =>
									updateFieldStyles( {
										inputBorderRadius: value,
									} )
								}
							/>
							<TextControl
								label="Input border width"
								value={ fieldStyles.inputBorderWidth }
								onChange={ ( value ) =>
									updateFieldStyles( {
										inputBorderWidth: value,
									} )
								}
							/>
							<TextControl
								label="Input border color"
								value={ fieldStyles.inputBorderColor }
								onChange={ ( value ) =>
									updateFieldStyles( {
										inputBorderColor: value,
									} )
								}
							/>
							<TextControl
								label="Input background color"
								value={ fieldStyles.inputBackgroundColor }
								onChange={ ( value ) =>
									updateFieldStyles( {
										inputBackgroundColor: value,
									} )
								}
							/>
							<TextControl
								label="Input text color"
								value={ fieldStyles.inputTextColor }
								onChange={ ( value ) =>
									updateFieldStyles( {
										inputTextColor: value,
									} )
								}
							/>
							<TextControl
								label="Placeholder color"
								value={ fieldStyles.placeholderColor }
								onChange={ ( value ) =>
									updateFieldStyles( {
										placeholderColor: value,
									} )
								}
							/>
							<TextControl
								label="Focus border color"
								value={ fieldStyles.focusBorderColor }
								onChange={ ( value ) =>
									updateFieldStyles( {
										focusBorderColor: value,
									} )
								}
							/>
							<TextControl
								label="Focus background color"
								value={ fieldStyles.focusBackgroundColor }
								onChange={ ( value ) =>
									updateFieldStyles( {
										focusBackgroundColor: value,
									} )
								}
							/>
						</PanelBody>

						<PanelBody title="Validation Settings" initialOpen={ false }>
							<ToggleControl
								label="Require field validation"
								checked={ attributes.validation.requireValidation }
								onChange={ ( value ) =>
									setAttributes( {
										validation: {
											...attributes.validation,
											requireValidation: value,
										},
									} )
								}
							/>
							<ToggleControl
								label="Validate email fields"
								checked={ attributes.validation.validateEmail }
								onChange={ ( value ) =>
									setAttributes( {
										validation: {
											...attributes.validation,
											validateEmail: value,
										},
									} )
								}
							/>
							<ToggleControl
								label="Validate phone numbers"
								checked={ attributes.validation.validatePhone }
								onChange={ ( value ) =>
									setAttributes( {
										validation: {
											...attributes.validation,
											validatePhone: value,
										},
									} )
								}
							/>
						</PanelBody>

						<PanelBody title="Email Settings" initialOpen={ false }>
							<TextControl
								label="SMTP Host"
								value={ attributes.smtpSettings.host }
								onChange={ ( value ) =>
									setAttributes( {
										smtpSettings: {
											...attributes.smtpSettings,
											host: value,
										},
									} )
								}
							/>
							<TextControl
								label="SMTP Port"
								type="number"
								value={ attributes.smtpSettings.port }
								onChange={ ( value ) =>
									setAttributes( {
										smtpSettings: {
											...attributes.smtpSettings,
											port: value,
										},
									} )
								}
							/>
							<TextControl
								label="SMTP Username"
								value={ attributes.smtpSettings.username }
								onChange={ ( value ) =>
									setAttributes( {
										smtpSettings: {
											...attributes.smtpSettings,
											username: value,
										},
									} )
								}
							/>
							<TextControl
								label="SMTP Password"
								type="password"
								value={ attributes.smtpSettings.password }
								onChange={ ( value ) =>
									setAttributes( {
										smtpSettings: {
											...attributes.smtpSettings,
											password: value,
										},
									} )
								}
							/>
							<SelectControl
								label="Encryption"
								value={ attributes.smtpSettings.encryption }
								options={ [
									{ label: 'TLS', value: 'tls' },
									{ label: 'SSL', value: 'ssl' },
									{ label: 'None', value: 'none' },
								] }
								onChange={ ( value ) =>
									setAttributes( {
										smtpSettings: {
											...attributes.smtpSettings,
											encryption: value,
										},
									} )
								}
							/>
							<TextareaControl
								label="Email Template"
								help="Use {field_name} to insert form fields."
								value={ attributes.emailTemplate }
								onChange={ ( value ) =>
									setAttributes( { emailTemplate: value } )
								}
							/>
						</PanelBody>

						<PanelBody title="Security Settings" initialOpen={ false }>
							<ToggleControl
								label="Enable reCAPTCHA"
								checked={ attributes.enableRecaptcha }
								onChange={ ( value ) =>
									setAttributes( { enableRecaptcha: value } )
								}
							/>
							<ToggleControl
								label="Enable file upload"
								checked={ attributes.enableFileUpload }
								onChange={ ( value ) =>
									setAttributes( { enableFileUpload: value } )
								}
							/>
							{ attributes.enableFileUpload && (
								<>
									<TextControl
										label="Max File Size (MB)"
										type="number"
										value={ attributes.maxFileSize }
										onChange={ ( value ) =>
											setAttributes( {
												maxFileSize: parseInt( value, 10 ) || 0,
											} )
										}
									/>
									<SelectControl
										multiple
										label="Allowed File Types"
										value={ attributes.allowedFileTypes }
										options={ [
											{ label: 'PDF', value: 'pdf' },
											{ label: 'Word (DOC)', value: 'doc' },
											{ label: 'Word (DOCX)', value: 'docx' },
											{ label: 'Images', value: 'image' },
										] }
										onChange={ ( value ) =>
											setAttributes( { allowedFileTypes: value } )
										}
									/>
								</>
							) }
						</PanelBody>
					</Panel>
				</InspectorControls>

				<div { ...blockProps }>
					<InnerBlocks
						allowedBlocks={ [
							'adaptive-content-enhancer/text-field',
							'adaptive-content-enhancer/option',
							...( hasWooProductContext
								? [ 'adaptive-content-enhancer/product-context' ]
								: [] ),
							'core/paragraph',
							'core/heading',
							'core/list',
							'core/separator',
							'core/spacer',
							'core/group',
							'core/buttons',
							'core/button',
						] }
						template={ TEMPLATE }
						templateLock={ false }
						renderAppender={ InnerBlocks.ButtonBlockAppender }
					/>
				</div>
			</>
		);
	},
	save: ( attributes ) => {
		const fieldStyles = {
			...FIELD_STYLE_DEFAULTS,
			...( attributes.fieldStyles || {} ),
		};

		const blockProps = useBlockProps.save( {
			className: `ace-form-container${
				fieldStyles.useFloatingPlaceholders
					? ' ace-form-container--floating-placeholders'
					: ''
			}${
				fieldStyles.showRequiredIndicator
					? ' ace-form-container--show-required'
					: ''
			}`,
			style: {
				display: 'grid',
				gap: attributes.style?.spacing?.blockGap || undefined,
				'--ace-form-field-gap': fieldStyles.fieldGap,
				'--ace-form-label-gap': fieldStyles.labelGap,
				'--ace-form-input-padding': fieldStyles.inputPadding,
				'--ace-form-input-radius': fieldStyles.inputBorderRadius,
				'--ace-form-input-border-width': fieldStyles.inputBorderWidth,
				'--ace-form-input-border-color': fieldStyles.inputBorderColor,
				'--ace-form-input-background': fieldStyles.inputBackgroundColor,
				'--ace-form-input-color': fieldStyles.inputTextColor,
				'--ace-form-placeholder-color': fieldStyles.placeholderColor,
				'--ace-form-focus-border-color': fieldStyles.focusBorderColor,
				'--ace-form-focus-background': fieldStyles.focusBackgroundColor,
			},
		} );

		return (
			<form { ...blockProps } data-is-form="true">
				<InnerBlocks.Content />
			</form>
		);
	},
} );
