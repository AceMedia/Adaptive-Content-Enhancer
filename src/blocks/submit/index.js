import { registerBlockType } from '@wordpress/blocks';
import { RichText, useBlockProps } from '@wordpress/block-editor';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => (
		<div { ...useBlockProps() }>
			<RichText tagName="span" value={ attributes.text } onChange={ ( text ) => setAttributes( { text } ) } />
		</div>
	),
	save: () => null,
} );
