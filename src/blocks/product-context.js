import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder, Notice } from '@wordpress/components';

const productContext = window.aceWooProductContext || {};
const sampleProduct = productContext.sampleProduct || null;

function Edit() {
	const blockProps = useBlockProps( {
		className: 'ace-product-context-preview',
	} );

	return (
		<div { ...blockProps }>
			<Placeholder
				label="Woo Product Context"
				instructions="On WooCommerce product pages this block injects product details into the form as locked hidden fields. On other pages it outputs nothing."
			>
				{ sampleProduct ? (
					<ul>
						<li>
							<strong>ID:</strong> { sampleProduct.id }
						</li>
						<li>
							<strong>Name:</strong> { sampleProduct.name }
						</li>
						<li>
							<strong>SKU:</strong>{ ' ' }
							{ sampleProduct.sku || 'No SKU set' }
						</li>
						<li>
							<strong>Price:</strong>{ ' ' }
							{ sampleProduct.price || 'No price available' }
						</li>
					</ul>
				) : (
					<Notice status="warning" isDismissible={ false }>
						No published WooCommerce product was found for editor
						preview.
					</Notice>
				) }
			</Placeholder>
		</div>
	);
}

registerBlockType( 'adaptive-content-enhancer/product-context', {
	title: 'Woo Product Context',
	description:
		'Includes the current WooCommerce product details in the form when used on a product page.',
	icon: 'products',
	category: 'widgets',
	parent: [ 'adaptive-content-enhancer/form' ],
	supports: {
		html: false,
		multiple: false,
		reusable: false,
		customClassName: false,
	},
	edit: Edit,
	save: () => null,
} );
