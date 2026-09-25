import { __ } from '@wordpress/i18n';

const types = new Map();

export const registerFieldType = ( definition ) => {
	types.set( definition.slug, definition );
};

export const getFieldType = ( slug ) => types.get( slug );
export const getFieldTypes = () => [ ...types.values() ];

[
	[ 'text', __( 'Text', 'adaptive-content-enhancer' ) ],
	[ 'textarea', __( 'Text area', 'adaptive-content-enhancer' ) ],
	[ 'email', __( 'Email', 'adaptive-content-enhancer' ) ],
	[ 'tel', __( 'Telephone', 'adaptive-content-enhancer' ) ],
	[ 'url', __( 'URL', 'adaptive-content-enhancer' ) ],
	[ 'number', __( 'Number', 'adaptive-content-enhancer' ) ],
	[ 'date', __( 'Date', 'adaptive-content-enhancer' ) ],
	[ 'time', __( 'Time', 'adaptive-content-enhancer' ) ],
	[ 'select', __( 'Select', 'adaptive-content-enhancer' ) ],
	[ 'radio', __( 'Radio', 'adaptive-content-enhancer' ) ],
	[ 'checkbox', __( 'Checkboxes', 'adaptive-content-enhancer' ) ],
	[ 'consent', __( 'Consent', 'adaptive-content-enhancer' ) ],
	[ 'hidden', __( 'Hidden', 'adaptive-content-enhancer' ) ],
	[ 'file', __( 'File', 'adaptive-content-enhancer' ) ],
].forEach( ( [ slug, label ] ) => registerFieldType( { slug, label } ) );
