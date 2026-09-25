import { RichText, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { getFieldType } from '../../field-types';

export default function Edit( { attributes, setAttributes } ) {
	const type = getFieldType( attributes.type );

	return (
		<div { ...useBlockProps( { className: `ace-field ace-field--${ attributes.type }` } ) }>
			<RichText
				tagName="label"
				value={ attributes.label }
				placeholder={ type?.label || __( 'Field label', 'adaptive-content-enhancer' ) }
				onChange={ ( label ) => setAttributes( { label } ) }
			/>
			<input type="text" disabled placeholder={ attributes.placeholder } />
		</div>
	);
}
