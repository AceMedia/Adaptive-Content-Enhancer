import { __ } from '@wordpress/i18n';

const types = new Map();

export const registerFieldType = ( definition ) => {
	types.set( definition.slug, definition );
};

export const getFieldType = ( slug ) => types.get( slug );
export const getFieldTypes = ( context = 'form' ) => [ ...types.values() ].filter( ( item ) => item.contexts.includes( context ) );

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
	[ 'range', __( 'Range', 'adaptive-content-enhancer' ) ],
	[ 'datetime', __( 'Date and time', 'adaptive-content-enhancer' ) ],
	[ 'colour', __( 'Colour', 'adaptive-content-enhancer' ) ],
	[ 'true-false', __( 'True or false', 'adaptive-content-enhancer' ) ],
	[ 'name', __( 'Name', 'adaptive-content-enhancer' ) ],
	[ 'address', __( 'Address', 'adaptive-content-enhancer' ) ],
	[ 'post-type', __( 'Post type', 'adaptive-content-enhancer' ) ],
	[ 'taxonomy-type', __( 'Taxonomy', 'adaptive-content-enhancer' ) ],
].forEach( ( [ slug, label ] ) => registerFieldType( { slug, label, contexts: [ 'form', 'meta' ] } ) );

[ 'consent', 'hidden' ].forEach( ( slug ) => { types.get( slug ).contexts = [ 'form' ]; } );
[ 'name', 'address' ].forEach( ( slug ) => { types.get( slug ).contexts = [ 'meta' ]; } );

[
	[ 'rich-text', __( 'Rich text', 'adaptive-content-enhancer' ) ],
	[ 'image', __( 'Image', 'adaptive-content-enhancer' ) ],
	[ 'gallery', __( 'Gallery', 'adaptive-content-enhancer' ) ],
	[ 'link', __( 'Link', 'adaptive-content-enhancer' ) ],
	[ 'post-object', __( 'Post', 'adaptive-content-enhancer' ) ],
	[ 'relationship', __( 'Related posts', 'adaptive-content-enhancer' ) ],
	[ 'taxonomy', __( 'Taxonomy terms', 'adaptive-content-enhancer' ) ],
	[ 'user', __( 'User', 'adaptive-content-enhancer' ) ],
	[ 'location', __( 'Map location', 'adaptive-content-enhancer' ) ],
	[ 'group', __( 'Group', 'adaptive-content-enhancer' ) ],
	[ 'repeater', __( 'Repeater', 'adaptive-content-enhancer' ) ],
	[ 'flexible', __( 'Flexible content', 'adaptive-content-enhancer' ) ],
].forEach( ( [ slug, label ] ) => registerFieldType( { slug, label, contexts: [ 'meta' ] } ) );

[ 'post-object', 'relationship', 'taxonomy' ].forEach( ( slug ) => { types.get( slug ).contexts = [ 'form', 'meta' ]; } );
