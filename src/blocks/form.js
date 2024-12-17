import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks, useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { useEffect } from '@wordpress/element';
import { select, dispatch } from '@wordpress/data';
import { 
    PanelBody, 
    TextControl, 
    TextareaControl, 
    ToggleControl, 
    SelectControl,
    Button,
    Panel,
    PanelRow
} from '@wordpress/components';

registerBlockType('adaptive-content-enhancer/form', {
    title: 'Form Container',
    icon: 'feedback',
    category: 'layout',
    attributes: {
        formId: { type: 'string', default: '' },
        recipient: { type: 'string', default: '' },
        subject: { type: 'string', default: '' },
        successMessage: { type: 'string', default: 'Thank you for your message!' },
        errorMessage: { type: 'string', default: 'There was an error sending your message.' },
        enableRecaptcha: { type: 'boolean', default: false },
        enableFileUpload: { type: 'boolean', default: false },
        maxFileSize: { type: 'number', default: 2 },
        allowedFileTypes: { type: 'array', default: ['pdf', 'doc', 'docx'] },
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
                encryption: 'tls'
            }
        },
        validation: {
            type: 'object',
            default: {
                requireValidation: true,
                validateEmail: true,
                validatePhone: false
            }
        }
    },
    
    supports: {
        html: false,
        styles: true,
        customClassName: true,
        attributes: true,
        inserter: true,
        color: {
            text: true,
            background: true,
            gradients: true, // Enable gradient options
            link: true, // Enable link color
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
            __experimentalDefaultControls: {
                blockGap: true,
                default: '1em',
            },
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
        align: true, // Enable alignment options (left, center, right, wide, full)
    },
    providesContext: {
        'adaptive-content-enhancer/isInputContext': true,
    },
    edit: ({ clientId, attributes, setAttributes }) => {
        const TEMPLATE = [
            ['adaptive-content-enhancer/text-field', {}],
            ['core/button', { text: 'Submit' }],
        ];

        const blockProps = useBlockProps({
            className: 'ace-form-container',
            style: {
                display: 'grid',
                gap: attributes.blockGap,
            },
        });

        useEffect(() => {
            const formBlock = select('core/block-editor').getBlock(clientId);
            if (formBlock && formBlock.innerBlocks) {
                formBlock.innerBlocks.forEach((block) => {
                    if (block.name === 'core/button') {
                        dispatch('core/block-editor').updateBlockAttributes(block.clientId, {
                            lock: {
                                move: true,
                                remove: true
                            }
                        });
                    }
                });
            }
        }, [clientId]);

        return (
            <>
                <InspectorControls>
                    <Panel>
                        <PanelBody title="Basic Settings" initialOpen={true}>
                            <TextControl
                                label="Form ID"
                                value={attributes.formId}
                                onChange={(value) => setAttributes({ formId: value })}
                            />
                            <TextControl
                                label="Recipient Email"
                                value={attributes.recipient}
                                onChange={(value) => setAttributes({ recipient: value })}
                            />
                            <TextControl
                                label="Subject Line"
                                value={attributes.subject}
                                onChange={(value) => setAttributes({ subject: value })}
                            />
                            <TextareaControl
                                label="Success Message"
                                value={attributes.successMessage}
                                onChange={(value) => setAttributes({ successMessage: value })}
                            />
                            <TextareaControl
                                label="Error Message"
                                value={attributes.errorMessage}
                                onChange={(value) => setAttributes({ errorMessage: value })}
                            />
                        </PanelBody>
        
                        <PanelBody title="Email Settings" initialOpen={false}>
                            <TextControl
                                label="SMTP Host"
                                value={attributes.smtpSettings.host}
                                onChange={(value) => setAttributes({ 
                                    smtpSettings: { ...attributes.smtpSettings, host: value }
                                })}
                            />
                            <TextControl
                                label="SMTP Port"
                                type="number"
                                value={attributes.smtpSettings.port}
                                onChange={(value) => setAttributes({ 
                                    smtpSettings: { ...attributes.smtpSettings, port: value }
                                })}
                            />
                            <TextControl
                                label="SMTP Username"
                                value={attributes.smtpSettings.username}
                                onChange={(value) => setAttributes({ 
                                    smtpSettings: { ...attributes.smtpSettings, username: value }
                                })}
                            />
                            <TextControl
                                label="SMTP Password"
                                type="password"
                                value={attributes.smtpSettings.password}
                                onChange={(value) => setAttributes({ 
                                    smtpSettings: { ...attributes.smtpSettings, password: value }
                                })}
                            />
                            <SelectControl
                                label="Encryption"
                                value={attributes.smtpSettings.encryption}
                                options={[
                                    { label: 'TLS', value: 'tls' },
                                    { label: 'SSL', value: 'ssl' },
                                    { label: 'None', value: 'none' }
                                ]}
                                onChange={(value) => setAttributes({ 
                                    smtpSettings: { ...attributes.smtpSettings, encryption: value }
                                })}
                            />
                            <TextareaControl
                                label="Email Template"
                                help="Use {field_name} to insert form fields"
                                value={attributes.emailTemplate}
                                onChange={(value) => setAttributes({ emailTemplate: value })}
                            />
                        </PanelBody>
        
                        <PanelBody title="Validation Settings" initialOpen={false}>
                            <ToggleControl
                                label="Require Field Validation"
                                checked={attributes.validation.requireValidation}
                                onChange={(value) => setAttributes({ 
                                    validation: { ...attributes.validation, requireValidation: value }
                                })}
                            />
                            <ToggleControl
                                label="Validate Email Fields"
                                checked={attributes.validation.validateEmail}
                                onChange={(value) => setAttributes({ 
                                    validation: { ...attributes.validation, validateEmail: value }
                                })}
                            />
                            <ToggleControl
                                label="Validate Phone Numbers"
                                checked={attributes.validation.validatePhone}
                                onChange={(value) => setAttributes({ 
                                    validation: { ...attributes.validation, validatePhone: value }
                                })}
                            />
                        </PanelBody>
        
                        <PanelBody title="Security Settings" initialOpen={false}>
                            <ToggleControl
                                label="Enable reCAPTCHA"
                                checked={attributes.enableRecaptcha}
                                onChange={(value) => setAttributes({ enableRecaptcha: value })}
                            />
                            <ToggleControl
                                label="Enable File Upload"
                                checked={attributes.enableFileUpload}
                                onChange={(value) => setAttributes({ enableFileUpload: value })}
                            />
                            {attributes.enableFileUpload && (
                                <>
                                    <TextControl
                                        label="Max File Size (MB)"
                                        type="number"
                                        value={attributes.maxFileSize}
                                        onChange={(value) => setAttributes({ maxFileSize: parseInt(value) })}
                                    />
                                    <SelectControl
                                        multiple
                                        label="Allowed File Types"
                                        value={attributes.allowedFileTypes}
                                        options={[
                                            { label: 'PDF', value: 'pdf' },
                                            { label: 'Word (DOC)', value: 'doc' },
                                            { label: 'Word (DOCX)', value: 'docx' },
                                            { label: 'Images', value: 'image' }
                                        ]}
                                        onChange={(value) => setAttributes({ allowedFileTypes: value })}
                                    />
                                </>
                            )}
                        </PanelBody>
                    </Panel>
                </InspectorControls>
        
                    <InnerBlocks
                    style={{
                        display: 'grid',
                        gap: attributes.blockGap,
                    }}
                        allowedBlocks={[
                            'adaptive-content-enhancer/text-field',
                            'adaptive-content-enhancer/option',
                            'core/button',
                        ]}
                        template={TEMPLATE}
                        templateLock={false}
                        renderAppender={InnerBlocks.ButtonBlockAppender}
                    />
            </>
        );
    },
    save: ( attributes ) => {
        const {
            blockGap,
            padding,
            margin,
            borderColor,
            borderRadius,
            borderStyle,
            borderWidth,
            textAlign,
            fontSize,
            lineHeight,
            fontWeight,
            fontFamily,
            fontStyle,
            fontVariant,
            letterSpacing,
            textTransform,
            textColor,
            backgroundColor,
            gradient,
        } = attributes;

        const blockProps = useBlockProps.save({
            className: 'ace-form-container',
            style: {
                display: 'grid',
            gap: attributes.blockGap,
            padding: attributes.padding,
            margin: attributes.margin,
            borderColor: attributes.borderColor,
            borderRadius: attributes.borderRadius,
            borderStyle: attributes.borderStyle,
            borderWidth: attributes.borderWidth,
            textAlign: attributes.textAlign,
            fontSize: attributes.fontSize,
            lineHeight: attributes.lineHeight,
            fontWeight: attributes.fontWeight,
            fontFamily: attributes.fontFamily,
            fontStyle: attributes.fontStyle,
            fontVariant: attributes.fontVariant,
            letterSpacing: attributes.letterSpacing,
            textTransform: attributes.textTransform,
            color: attributes.textColor,
            backgroundColor: attributes.backgroundColor,
            background: attributes.gradient,
            },
        });

        return (
            <form {...blockProps} data-is-form="true">
            <InnerBlocks.Content />
            </form>
        );
    },
});
